<?php

/**
 * Admin tab for AI (BYOK) settings.
 *
 * Stores everything in the `houzi_ai_options` option. The provider API key is
 * write-only: it is never echoed back into the form; leaving the field empty
 * on save keeps the stored key.
 *
 * @link       https://booleanbites.com
 * @since      1.5.0
 *
 * @package    Houzi_Rest_Api
 * @subpackage Houzi_Rest_Api/admin/partials
 * @author Adil Soomro
 */
class RestApiAISettings {

	private $houzi_ai_options;

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.5.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.5.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

		add_action( 'admin_init', array( $this, 'houzi_ai_page_init' ) );
		$this->houzi_ai_options = get_option( 'houzi_ai_options' );
	}

	public function ai_settings() {
		?>

		<form method="post" action="options.php">
				<?php
					settings_fields( 'houzi_ai_option_group' );
					do_settings_sections( 'houzi-rest-api-ai-admin' );
					submit_button();
				?>
		</form>
		<?php
		$this->render_usage_summary();
		$this->render_location_misses();
	}

	public function houzi_ai_page_init() {

		register_setting(
			'houzi_ai_option_group', // option_group
			'houzi_ai_options', // option_name
			array( $this, 'houzi_ai_sanitize' ) // sanitize_callback
		);
		add_settings_section(
			'houzi_ai_setting_section', // id
			'AI Settings (BYOK)', // title
			array( $this, 'houzi_ai_section_info' ), // callback
			'houzi-rest-api-ai-admin' // page
		);
		add_settings_field(
			'ai_enabled',
			'Enable AI',
			array( $this, 'ai_enabled_callback' ),
			'houzi-rest-api-ai-admin',
			'houzi_ai_setting_section'
		);
		add_settings_field(
			'provider',
			'AI Provider',
			array( $this, 'provider_callback' ),
			'houzi-rest-api-ai-admin',
			'houzi_ai_setting_section'
		);
		add_settings_field(
			'api_key',
			'API Key',
			array( $this, 'api_key_callback' ),
			'houzi-rest-api-ai-admin',
			'houzi_ai_setting_section'
		);
		add_settings_field(
			'model',
			'Model',
			array( $this, 'model_callback' ),
			'houzi-rest-api-ai-admin',
			'houzi_ai_setting_section'
		);
		add_settings_field(
			'features',
			'Features',
			array( $this, 'features_callback' ),
			'houzi-rest-api-ai-admin',
			'houzi_ai_setting_section'
		);
		add_settings_field(
			'rate_limits',
			'Rate Limits',
			array( $this, 'rate_limits_callback' ),
			'houzi-rest-api-ai-admin',
			'houzi_ai_setting_section'
		);
		add_settings_field(
			'output_language',
			'Output Language',
			array( $this, 'output_language_callback' ),
			'houzi-rest-api-ai-admin',
			'houzi_ai_setting_section'
		);
		add_settings_field(
			'location_aliases',
			'Location Aliases',
			array( $this, 'location_aliases_callback' ),
			'houzi-rest-api-ai-admin',
			'houzi_ai_setting_section'
		);
	}

	public function houzi_ai_sanitize( $input ) {
		$existing         = get_option( 'houzi_ai_options' );
		$existing         = is_array( $existing ) ? $existing : array();
		$sanitary_values  = array();

		if ( isset( $input['ai_enabled'] ) ) {
			$sanitary_values['ai_enabled'] = 'ai_enabled';
		}
		if ( isset( $input['provider'] ) && in_array( $input['provider'], array( 'openai', 'anthropic', 'gemini' ), true ) ) {
			$sanitary_values['provider'] = $input['provider'];
		}

		// Write-only key: empty input keeps the previously stored key.
		if ( isset( $input['api_key'] ) && '' !== trim( $input['api_key'] ) ) {
			$sanitary_values['api_key'] = sanitize_text_field( trim( $input['api_key'] ) );
		} elseif ( isset( $existing['api_key'] ) ) {
			$sanitary_values['api_key'] = $existing['api_key'];
		}

		if ( isset( $input['model'] ) ) {
			$sanitary_values['model'] = sanitize_text_field( $input['model'] );
		}

		// Marker so unchecked boxes are honored only after the first save
		// (before that, all features default on when AI is enabled).
		$sanitary_values['features_saved'] = 1;
		foreach ( array( 'search', 'describe', 'ask_listing', 'crm' ) as $feature ) {
			if ( isset( $input[ 'feature_' . $feature ] ) ) {
				$sanitary_values[ 'feature_' . $feature ] = 1;
			}
		}

		if ( isset( $input['rate_per_user_hour'] ) ) {
			$sanitary_values['rate_per_user_hour'] = absint( $input['rate_per_user_hour'] );
		}
		if ( isset( $input['rate_per_site_day'] ) ) {
			$sanitary_values['rate_per_site_day'] = absint( $input['rate_per_site_day'] );
		}
		if ( isset( $input['output_language'] ) ) {
			$sanitary_values['output_language'] = sanitize_text_field( $input['output_language'] );
		}

		// Alias lines live in their own option, consumed by the location resolver.
		if ( isset( $input['location_aliases'] ) ) {
			update_option( 'houzi_ai_location_aliases', $this->parse_alias_lines( $input['location_aliases'] ), false );
		}

		return $sanitary_values;
	}

	/**
	 * Parse alias lines like:
	 *   ny, nyc => property_city:new-york-city
	 * into a normalized alias => "taxonomy:slug" map.
	 */
	private function parse_alias_lines( $raw ) {
		$aliases = array();
		$lines   = preg_split( '/\r\n|\r|\n/', (string) $raw );
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line || false === strpos( $line, '=>' ) ) {
				continue;
			}
			list( $left, $right ) = array_map( 'trim', explode( '=>', $line, 2 ) );
			if ( ! preg_match( '/^(property_city|property_area|property_state|property_country):[a-z0-9\-]+$/', $right ) ) {
				continue;
			}
			foreach ( explode( ',', $left ) as $alias ) {
				$alias = class_exists( 'Houzi_AI_Location_Resolver' )
					? Houzi_AI_Location_Resolver::normalize( $alias )
					: strtolower( trim( $alias ) );
				if ( '' !== $alias ) {
					$aliases[ $alias ] = $right;
				}
			}
		}
		return $aliases;
	}

	public function houzi_ai_section_info() {
		echo 'Bring-your-own-key AI features for the Houzi app. The API key stays on this server; the app never sees it. '
			. 'AI calls are made with <em>your</em> provider account, so keep the rate limits sensible.';
	}

	public function ai_enabled_callback() {
		printf(
			'<input type="checkbox" name="houzi_ai_options[ai_enabled]" id="ai_enabled" value="ai_enabled" %s>
			<label for="ai_enabled">Master switch for all AI features.</label>',
			( isset( $this->houzi_ai_options['ai_enabled'] ) && 'ai_enabled' === $this->houzi_ai_options['ai_enabled'] ) ? 'checked' : ''
		);
	}

	public function provider_callback() {
		$provider = isset( $this->houzi_ai_options['provider'] ) ? $this->houzi_ai_options['provider'] : 'openai';
		?>
		<select name="houzi_ai_options[provider]" id="provider">
			<option value="openai" <?php selected( $provider, 'openai' ); ?>>OpenAI</option>
			<option value="anthropic" <?php selected( $provider, 'anthropic' ); ?>>Anthropic (Claude)</option>
			<option value="gemini" <?php selected( $provider, 'gemini' ); ?>>Google (Gemini)</option>
		</select>
		<?php
	}

	public function api_key_callback() {
		$has_key = ! empty( $this->houzi_ai_options['api_key'] );
		printf(
			'<input class="regular-text" type="password" name="houzi_ai_options[api_key]" id="api_key" value="" placeholder="%s" autocomplete="new-password">
			<label for="api_key"><br>%s</label>',
			$has_key ? '•••••••••••••••• (saved)' : 'Enter your provider API key',
			$has_key
				? 'A key is saved. Leave empty to keep it, or paste a new key to replace it.'
				: 'The key is stored on this server only and is never sent to the app.'
		);
	}

	public function model_callback() {
		printf(
			'<input class="regular-text" type="text" name="houzi_ai_options[model]" id="model" value="%s" placeholder="Provider default">
			<label for="model"><br>Leave empty for the provider default (OpenAI: gpt-4.1-mini, Claude: claude-haiku-4-5, Gemini: gemini-2.0-flash).</label>',
			isset( $this->houzi_ai_options['model'] ) ? esc_attr( $this->houzi_ai_options['model'] ) : ''
		);
	}

	public function features_callback() {
		$features = array(
			'search'      => 'AI Property Search (natural language)',
			'describe'    => 'AI Description Writer',
			'ask_listing' => 'Ask About This Listing (buyer Q&amp;A)',
			'crm'         => 'CRM Copilot (lead summary, match ranking, email drafts)',
		);
		$saved = isset( $this->houzi_ai_options['features_saved'] );
		foreach ( $features as $key => $label ) {
			$checked = $saved
				? ! empty( $this->houzi_ai_options[ 'feature_' . $key ] )
				: true;
			printf(
				'<label><input type="checkbox" name="houzi_ai_options[feature_%1$s]" value="1" %2$s> %3$s</label><br>',
				esc_attr( $key ),
				$checked ? 'checked' : '',
				$label
			);
		}
	}

	public function rate_limits_callback() {
		printf(
			'<input type="number" min="1" name="houzi_ai_options[rate_per_user_hour]" value="%s" style="width:90px"> AI calls per user per hour (default 30)<br>
			<input type="number" min="1" name="houzi_ai_options[rate_per_site_day]" value="%s" style="width:90px"> AI calls per site per day — cost guard (default 500)',
			isset( $this->houzi_ai_options['rate_per_user_hour'] ) ? esc_attr( $this->houzi_ai_options['rate_per_user_hour'] ) : '',
			isset( $this->houzi_ai_options['rate_per_site_day'] ) ? esc_attr( $this->houzi_ai_options['rate_per_site_day'] ) : ''
		);
	}

	public function output_language_callback() {
		printf(
			'<input class="regular-text" type="text" name="houzi_ai_options[output_language]" id="output_language" value="%s" placeholder="auto">
			<label for="output_language"><br>Language code for generated text (e.g. en, es, ar). Use "auto" to follow the app user\'s language.</label>',
			isset( $this->houzi_ai_options['output_language'] ) ? esc_attr( $this->houzi_ai_options['output_language'] ) : ''
		);
	}

	public function location_aliases_callback() {
		$aliases = get_option( 'houzi_ai_location_aliases' );
		$lines   = array();
		if ( is_array( $aliases ) ) {
			// Group aliases that point at the same term onto one line.
			$grouped = array();
			foreach ( $aliases as $alias => $target ) {
				$grouped[ $target ][] = $alias;
			}
			foreach ( $grouped as $target => $alias_list ) {
				$lines[] = implode( ', ', $alias_list ) . ' => ' . $target;
			}
		}
		printf(
			'<textarea class="large-text" rows="5" name="houzi_ai_options[location_aliases]" id="location_aliases" placeholder="ny, nyc => property_city:new-york-city">%s</textarea>
			<label for="location_aliases"><br>One mapping per line: <code>alias1, alias2 =&gt; taxonomy:slug</code>. Taxonomy is one of property_city, property_area, property_state, property_country. Grow this list from the misses below.</label>',
			esc_textarea( implode( "\n", $lines ) )
		);
	}

	private function render_usage_summary() {
		$log = get_option( 'houzi_ai_usage_log' );
		if ( ! is_array( $log ) || empty( $log ) ) {
			return;
		}
		krsort( $log );
		$log = array_slice( $log, 0, 7, true );
		?>
		<h3>AI Usage (last 7 days)</h3>
		<table class="widefat striped" style="max-width:760px">
			<thead><tr><th>Day</th><th>Feature</th><th>Calls</th><th>Errors</th><th>Input Tokens</th><th>Output Tokens</th><th>Avg ms</th></tr></thead>
			<tbody>
			<?php foreach ( $log as $day => $features ) : ?>
				<?php foreach ( $features as $feature => $stats ) : ?>
				<tr>
					<td><?php echo esc_html( $day ); ?></td>
					<td><?php echo esc_html( $feature ); ?></td>
					<td><?php echo intval( $stats['calls'] ); ?></td>
					<td><?php echo intval( $stats['errors'] ); ?></td>
					<td><?php echo intval( $stats['input_tokens'] ); ?></td>
					<td><?php echo intval( $stats['output_tokens'] ); ?></td>
					<td><?php echo $stats['calls'] > 0 ? intval( $stats['total_ms'] / $stats['calls'] ) : 0; ?></td>
				</tr>
				<?php endforeach; ?>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private function render_location_misses() {
		$misses = get_option( 'houzi_ai_location_misses' );
		if ( ! is_array( $misses ) || empty( $misses ) ) {
			return;
		}
		uasort( $misses, function ( $a, $b ) {
			return $b['count'] - $a['count'];
		} );
		$misses = array_slice( $misses, 0, 20, true );
		?>
		<h3>Unresolved Location Phrases</h3>
		<p>Users searched for these places but no taxonomy term matched. Add the real ones as aliases above.</p>
		<table class="widefat striped" style="max-width:560px">
			<thead><tr><th>Phrase</th><th>Times</th><th>Last Seen</th></tr></thead>
			<tbody>
			<?php foreach ( $misses as $phrase => $stats ) : ?>
				<tr>
					<td><?php echo esc_html( $phrase ); ?></td>
					<td><?php echo intval( $stats['count'] ); ?></td>
					<td><?php echo esc_html( $stats['last'] ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
}
