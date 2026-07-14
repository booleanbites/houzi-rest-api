<?php
/**
 * Google Gemini provider adapter (generateContent + forced function calls) over Guzzle.
 *
 * @package Houzi Mobile Api
 * @since Houzi 1.5.0
 * @author Adil Soomro
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Houzi_AI_Provider_Gemini implements Houzi_AI_Provider_Interface {

	const API_BASE      = 'https://generativelanguage.googleapis.com/v1beta/models/';
	const DEFAULT_MODEL = 'gemini-3-flash-preview';

	private $api_key;

	public function __construct( $api_key ) {
		$this->api_key = $api_key;
	}

	public function complete( $system, $messages, $tool = null, $options = array() ) {
		$model = ! empty( $options['model'] ) ? $options['model'] : self::DEFAULT_MODEL;

		$contents = array();
		foreach ( $messages as $message ) {
			$contents[] = array(
				'role'  => ( 'assistant' === $message['role'] ) ? 'model' : 'user',
				'parts' => array( array( 'text' => $message['content'] ) ),
			);
		}

		$payload = array(
			'systemInstruction' => array( 'parts' => array( array( 'text' => $system ) ) ),
			'contents'          => $contents,
			'generationConfig'  => array(
				'maxOutputTokens' => ! empty( $options['max_tokens'] ) ? intval( $options['max_tokens'] ) : 1024,
				'temperature'     => isset( $options['temperature'] ) ? floatval( $options['temperature'] ) : 0.2,
			),
		);

		if ( ! empty( $tool ) ) {
			$payload['tools'] = array(
				array(
					'functionDeclarations' => array(
						array(
							'name'        => $tool['name'],
							'description' => isset( $tool['description'] ) ? $tool['description'] : '',
							'parameters'  => $tool['parameters'],
						),
					),
				),
			);
			// Force the function call — structured output, never free text.
			$payload['toolConfig'] = array(
				'functionCallingConfig' => array(
					'mode'                 => 'ANY',
					'allowedFunctionNames' => array( $tool['name'] ),
				),
			);
		}

		$client   = new \GuzzleHttp\Client();
		$response = $client->post(
			self::API_BASE . rawurlencode( $model ) . ':generateContent',
			array(
				'headers' => array(
					'x-goog-api-key' => $this->api_key,
					'Content-Type'   => 'application/json',
				),
				'json'    => $payload,
				'timeout' => ! empty( $options['timeout'] ) ? intval( $options['timeout'] ) : 15,
			)
		);

		$body = json_decode( (string) $response->getBody(), true );

		$tool_args = null;
		$text      = null;
		if ( ! empty( $body['candidates'][0]['content']['parts'] ) ) {
			foreach ( $body['candidates'][0]['content']['parts'] as $part ) {
				if ( isset( $part['functionCall']['args'] ) && is_array( $part['functionCall']['args'] ) ) {
					$tool_args = $part['functionCall']['args'];
				}
				if ( isset( $part['text'] ) ) {
					$text = $part['text'];
				}
			}
		}

		return array(
			'tool_args' => is_array( $tool_args ) ? $tool_args : null,
			'text'      => $text,
			'usage'     => array(
				'input_tokens'  => isset( $body['usageMetadata']['promptTokenCount'] ) ? intval( $body['usageMetadata']['promptTokenCount'] ) : 0,
				'output_tokens' => isset( $body['usageMetadata']['candidatesTokenCount'] ) ? intval( $body['usageMetadata']['candidatesTokenCount'] ) : 0,
			),
		);
	}
}
