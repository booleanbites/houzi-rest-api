<?php
/**
 * Common interface for BYOK AI providers.
 *
 * Every provider receives the same normalized inputs and returns the same
 * normalized output shape, so the gateway and feature handlers never need
 * to know which provider is configured.
 *
 * @package Houzi Mobile Api
 * @since Houzi 1.5.0
 * @author Adil Soomro
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

interface Houzi_AI_Provider_Interface {

	/**
	 * Run a single completion, optionally forcing a tool (structured output) call.
	 *
	 * @param string $system   System prompt.
	 * @param array  $messages List of ['role' => 'user'|'assistant', 'content' => string].
	 * @param array  $tool     Optional tool spec: ['name', 'description', 'parameters' => JSON schema].
	 *                         When given, the call MUST return tool args (forced tool choice).
	 * @param array  $options  ['model' => string, 'max_tokens' => int, 'temperature' => float, 'timeout' => int].
	 *
	 * @return array ['tool_args' => array|null, 'text' => string|null, 'usage' => array]
	 * @throws Exception On transport or provider errors (message safe to log, not to echo verbatim).
	 */
	public function complete( $system, $messages, $tool = null, $options = array() );
}
