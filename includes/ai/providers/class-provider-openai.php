<?php
/**
 * OpenAI provider adapter (chat completions + forced tool calls) over Guzzle.
 *
 * @package Houzi Mobile Api
 * @since Houzi 1.5.0
 * @author Adil Soomro
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Houzi_AI_Provider_OpenAI implements Houzi_AI_Provider_Interface {

	const API_URL       = 'https://api.openai.com/v1/chat/completions';
	const DEFAULT_MODEL = 'gpt-4.1-mini';

	private $api_key;

	public function __construct( $api_key ) {
		$this->api_key = $api_key;
	}

	public function complete( $system, $messages, $tool = null, $options = array() ) {
		$payload = array(
			'model'       => ! empty( $options['model'] ) ? $options['model'] : self::DEFAULT_MODEL,
			'max_tokens'  => ! empty( $options['max_tokens'] ) ? intval( $options['max_tokens'] ) : 1024,
			'temperature' => isset( $options['temperature'] ) ? floatval( $options['temperature'] ) : 0.2,
			'messages'    => array_merge(
				array( array( 'role' => 'system', 'content' => $system ) ),
				$messages
			),
		);

		if ( ! empty( $tool ) ) {
			$payload['tools'] = array(
				array(
					'type'     => 'function',
					'function' => array(
						'name'        => $tool['name'],
						'description' => isset( $tool['description'] ) ? $tool['description'] : '',
						'parameters'  => $tool['parameters'],
					),
				),
			);
			// Force the tool call — structured output, never free text.
			$payload['tool_choice'] = array(
				'type'     => 'function',
				'function' => array( 'name' => $tool['name'] ),
			);
		}

		$client   = new \GuzzleHttp\Client();
		$response = $client->post(
			self::API_URL,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_key,
					'Content-Type'  => 'application/json',
				),
				'json'    => $payload,
				'timeout' => ! empty( $options['timeout'] ) ? intval( $options['timeout'] ) : 15,
			)
		);

		$body    = json_decode( (string) $response->getBody(), true );
		$message = isset( $body['choices'][0]['message'] ) ? $body['choices'][0]['message'] : array();

		$tool_args = null;
		if ( ! empty( $message['tool_calls'][0]['function']['arguments'] ) ) {
			$tool_args = json_decode( $message['tool_calls'][0]['function']['arguments'], true );
		}

		return array(
			'tool_args' => is_array( $tool_args ) ? $tool_args : null,
			'text'      => isset( $message['content'] ) ? $message['content'] : null,
			'usage'     => array(
				'input_tokens'  => isset( $body['usage']['prompt_tokens'] ) ? intval( $body['usage']['prompt_tokens'] ) : 0,
				'output_tokens' => isset( $body['usage']['completion_tokens'] ) ? intval( $body['usage']['completion_tokens'] ) : 0,
			),
		);
	}
}
