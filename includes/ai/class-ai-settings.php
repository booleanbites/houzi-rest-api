<?php
/**
 * Typed accessors over the `houzi_ai_options` option array.
 *
 * The AI options live in their own option (separate from houzi_rest_api_options)
 * so the API key can be handled write-only and the whole feature removed cleanly.
 *
 * @package Houzi Mobile Api
 * @since Houzi 1.5.0
 * @author Adil Soomro
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Houzi_AI_Settings {

	const OPTION_NAME = 'houzi_ai_options';

	/** Feature keys used across settings, guards and touch-base. */
	const FEATURES = array( 'search', 'describe', 'ask_listing', 'crm', 'suggestions' );

	private static function options() {
		$options = get_option( self::OPTION_NAME );
		return is_array( $options ) ? $options : array();
	}

	public static function is_enabled() {
		$options = self::options();
		return isset( $options['ai_enabled'] ) && 'ai_enabled' === $options['ai_enabled'];
	}

	public static function feature_enabled( $feature ) {
		if ( ! self::is_enabled() ) {
			return false;
		}
		$options = self::options();
		// Never saved -> all features default on once the master switch is enabled.
		if ( ! isset( $options['features_saved'] ) ) {
			return true;
		}
		return ! empty( $options[ 'feature_' . $feature ] );
	}

	public static function provider() {
		$options  = self::options();
		$provider = isset( $options['provider'] ) ? $options['provider'] : 'openai';
		return in_array( $provider, array( 'openai', 'anthropic', 'gemini' ), true ) ? $provider : 'openai';
	}

	public static function api_key() {
		$options = self::options();
		return isset( $options['api_key'] ) ? trim( $options['api_key'] ) : '';
	}

	/**
	 * Model override. Empty string means "use the provider adapter's default".
	 */
	public static function model() {
		$options = self::options();
		return isset( $options['model'] ) ? trim( $options['model'] ) : '';
	}

	/**
	 * Cheaper/faster "lite" model for lightweight generations (e.g. the
	 * "Tailored for You" taxonomy subtitles). Empty string means "fall back to
	 * model() and then the provider adapter's default".
	 */
	public static function lite_model() {
		$options = self::options();
		return isset( $options['lite_model'] ) ? trim( $options['lite_model'] ) : '';
	}

	public static function rate_per_user_hour() {
		$options = self::options();
		$value   = isset( $options['rate_per_user_hour'] ) ? intval( $options['rate_per_user_hour'] ) : 0;
		return $value > 0 ? $value : 30;
	}

	public static function rate_per_site_day() {
		$options = self::options();
		$value   = isset( $options['rate_per_site_day'] ) ? intval( $options['rate_per_site_day'] ) : 0;
		return $value > 0 ? $value : 500;
	}

	/**
	 * Default output language. 'auto' means: follow the request `language` param.
	 */
	public static function output_language() {
		$options = self::options();
		$lang    = isset( $options['output_language'] ) ? trim( $options['output_language'] ) : '';
		return '' === $lang ? 'auto' : $lang;
	}

	/**
	 * Resolve the language a generative reply should be written in.
	 *
	 * @param string $requested Language code sent by the app (may be empty).
	 */
	public static function resolve_language( $requested ) {
		$default = self::output_language();
		if ( 'auto' !== $default ) {
			return $default;
		}
		$requested = sanitize_text_field( (string) $requested );
		return '' !== $requested ? $requested : 'en';
	}

	/**
	 * Instantiate the configured provider adapter.
	 *
	 * @return Houzi_AI_Provider_Interface
	 */
	public static function make_provider() {
		$api_key = self::api_key();
		switch ( self::provider() ) {
			case 'anthropic':
				return new Houzi_AI_Provider_Claude( $api_key );
			case 'gemini':
				return new Houzi_AI_Provider_Gemini( $api_key );
			case 'openai':
			default:
				return new Houzi_AI_Provider_OpenAI( $api_key );
		}
	}
}
