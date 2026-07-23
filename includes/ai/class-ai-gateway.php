<?php
/**
 * Single entry point for all AI calls.
 *
 * Feature handlers never talk to providers directly: the gateway resolves the
 * configured provider, forces structured (tool) output, retries once on
 * malformed tool args, and aggregates usage per day/feature for the admin.
 *
 * @package Houzi Mobile Api
 * @since Houzi 1.5.0
 * @author Adil Soomro
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Houzi_AI_Gateway {

	const USAGE_OPTION = 'houzi_ai_usage_log';
	const USAGE_DAYS   = 30;

	/**
	 * Run a completion for a feature.
	 *
	 * @param string $feature  One of Houzi_AI_Settings::FEATURES (used for usage log).
	 * @param string $system   System prompt.
	 * @param array  $messages [['role' => ..., 'content' => ...], ...].
	 * @param array  $tool     Tool spec (forced) — pass null only for plain text output.
	 * @param array  $options  Provider options overrides.
	 *
	 * @return array|WP_Error Normalized provider result, or WP_Error with an
	 *                        error code from the uniform contract
	 *                        (ai_disabled|no_api_key|ai_timeout|ai_error).
	 */
	public static function complete( $feature, $system, $messages, $tool = null, $options = array() ) {
		if ( ! Houzi_AI_Settings::is_enabled() || ! Houzi_AI_Settings::feature_enabled( $feature ) ) {
			return new WP_Error( 'ai_disabled', 'AI is not enabled for this feature.' );
		}
		if ( '' === Houzi_AI_Settings::api_key() ) {
			return new WP_Error( 'no_api_key', 'No AI API key is configured.' );
		}

		$model = Houzi_AI_Settings::model();
		if ( '' !== $model && empty( $options['model'] ) ) {
			$options['model'] = $model;
		}

		$provider = Houzi_AI_Settings::make_provider();
		$started  = microtime( true );

		try {
			$result = $provider->complete( $system, $messages, $tool, $options );

			// One retry when we forced a tool but got no parseable args back.
			if ( ! empty( $tool ) && null === $result['tool_args'] ) {
				$result = $provider->complete( $system, $messages, $tool, $options );
			}
		} catch ( \GuzzleHttp\Exception\ConnectException $e ) {
			self::log_usage( $feature, 0, 0, microtime( true ) - $started, true );
			return new WP_Error( 'ai_timeout', 'The AI provider timed out.' );
		} catch ( \Exception $e ) {
			self::log_usage( $feature, 0, 0, microtime( true ) - $started, true );
			error_log( 'Houzi AI (' . $feature . '): ' . $e->getMessage() );
			return new WP_Error( 'ai_error', 'The AI provider returned an error.' );
		}

		if ( ! empty( $tool ) && null === $result['tool_args'] ) {
			self::log_usage( $feature, 0, 0, microtime( true ) - $started, true );
			return new WP_Error( 'ai_error', 'The AI response could not be parsed.' );
		}

		self::log_usage(
			$feature,
			isset( $result['usage']['input_tokens'] ) ? $result['usage']['input_tokens'] : 0,
			isset( $result['usage']['output_tokens'] ) ? $result['usage']['output_tokens'] : 0,
			microtime( true ) - $started,
			false
		);

		return $result;
	}

	/**
	 * Aggregate usage per day per feature (calls, tokens, errors, latency) so the
	 * site owner can see what their key is spending. Kept small: last 30 days.
	 */
	private static function log_usage( $feature, $input_tokens, $output_tokens, $seconds, $is_error ) {
		$log = get_option( self::USAGE_OPTION );
		if ( ! is_array( $log ) ) {
			$log = array();
		}

		$day = gmdate( 'Y-m-d' );
		if ( ! isset( $log[ $day ][ $feature ] ) ) {
			$log[ $day ][ $feature ] = array(
				'calls'         => 0,
				'errors'        => 0,
				'input_tokens'  => 0,
				'output_tokens' => 0,
				'total_ms'      => 0,
			);
		}

		$entry                   = &$log[ $day ][ $feature ];
		$entry['calls']         += 1;
		$entry['errors']        += $is_error ? 1 : 0;
		$entry['input_tokens']  += intval( $input_tokens );
		$entry['output_tokens'] += intval( $output_tokens );
		$entry['total_ms']      += intval( round( $seconds * 1000 ) );

		// Trim to the newest USAGE_DAYS days.
		if ( count( $log ) > self::USAGE_DAYS ) {
			krsort( $log );
			$log = array_slice( $log, 0, self::USAGE_DAYS, true );
		}

		update_option( self::USAGE_OPTION, $log, false );
	}
}
