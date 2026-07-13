<?php
/**
 * Anthropic Claude provider adapter (messages API + forced tool use) over Guzzle.
 *
 * @package Houzi Mobile Api
 * @since Houzi 1.5.0
 * @author Adil Soomro
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Houzi_AI_Provider_Claude implements Houzi_AI_Provider_Interface {

	const API_URL       = 'https://api.anthropic.com/v1/messages';
	const API_VERSION   = '2023-06-01';
	const DEFAULT_MODEL = 'claude-haiku-4-5';

	private $api_key;

	public function __construct( $api_key ) {
		$this->api_key = $api_key;
	}

	public function complete( $system, $messages, $tool = null, $options = array() ) {
		$payload = array(
			'model'      => ! empty( $options['model'] ) ? $options['model'] : self::DEFAULT_MODEL,
			'max_tokens' => ! empty( $options['max_tokens'] ) ? intval( $options['max_tokens'] ) : 1024,
			'system'     => $system,
			'messages'   => $messages,
		);
		if ( isset( $options['temperature'] ) ) {
			$payload['temperature'] = floatval( $options['temperature'] );
		}

		if ( ! empty( $tool ) ) {
			$payload['tools'] = array(
				array(
					'name'         => $tool['name'],
					'description'  => isset( $tool['description'] ) ? $tool['description'] : '',
					'input_schema' => $tool['parameters'],
				),
			);
			// Force the tool call — structured output, never free text.
			$payload['tool_choice'] = array( 'type' => 'tool', 'name' => $tool['name'] );
		}

		$client   = new \GuzzleHttp\Client();
		$response = $client->post(
			self::API_URL,
			array(
				'headers' => array(
					'x-api-key'         => $this->api_key,
					'anthropic-version' => self::API_VERSION,
					'Content-Type'      => 'application/json',
				),
				'json'    => $payload,
				'timeout' => ! empty( $options['timeout'] ) ? intval( $options['timeout'] ) : 15,
			)
		);

		$body = json_decode( (string) $response->getBody(), true );

		$tool_args = null;
		$text      = null;
		if ( ! empty( $body['content'] ) && is_array( $body['content'] ) ) {
			foreach ( $body['content'] as $block ) {
				if ( isset( $block['type'] ) && 'tool_use' === $block['type'] && isset( $block['input'] ) ) {
					$tool_args = $block['input'];
				}
				if ( isset( $block['type'] ) && 'text' === $block['type'] && isset( $block['text'] ) ) {
					$text = $block['text'];
				}
			}
		}

		return array(
			'tool_args' => is_array( $tool_args ) ? $tool_args : null,
			'text'      => $text,
			'usage'     => array(
				'input_tokens'  => isset( $body['usage']['input_tokens'] ) ? intval( $body['usage']['input_tokens'] ) : 0,
				'output_tokens' => isset( $body['usage']['output_tokens'] ) ? intval( $body['usage']['output_tokens'] ) : 0,
			),
		);
	}
}
