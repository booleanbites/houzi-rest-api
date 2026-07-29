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
			'lite_model',
			'Lite Model',
			array( $this, 'lite_model_callback' ),
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
		if ( isset( $input['lite_model'] ) ) {
			$sanitary_values['lite_model'] = sanitize_text_field( $input['lite_model'] );
		}

		// Marker so unchecked boxes are honored only after the first save
		// (before that, all features default on when AI is enabled).
		$sanitary_values['features_saved'] = 1;
		foreach ( array( 'search', 'describe', 'ask_listing', 'crm', 'suggestions' ) as $feature ) {
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
			if ( ! preg_match( '/^(property_city|property_area|property_state|property_country):\S+$/u', $right ) ) {
				continue;
			}
			foreach ( explode( ',', $left ) as $alias ) {
				$alias = class_exists( 'Houzi_AI_Location_Resolver' )
					? Houzi_AI_Location_Resolver::normalize( $alias )
					: ( function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( $alias ), 'UTF-8' ) : strtolower( trim( $alias ) ) );
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
			<label for="model"><br>Leave empty for the provider default (OpenAI: gpt-5-mini, Claude: claude-haiku-4-5, Gemini: gemini-3-flash-preview).</label>',
			isset( $this->houzi_ai_options['model'] ) ? esc_attr( $this->houzi_ai_options['model'] ) : ''
		);
	}

	public function lite_model_callback() {
		printf(
			'<input class="regular-text" type="text" name="houzi_ai_options[lite_model]" id="lite_model" value="%s" placeholder="Provider default">
			<label for="lite_model"><br>A cheaper / faster model for lightweight generations such as the home "Tailored for You" suggestions. Prefer a low-cost model here to save on cost (e.g. gpt-5-nano, claude-haiku-4-5, gemini-3.1-flash-lite). Leave empty to reuse the Model above / the provider default.</label>',
			isset( $this->houzi_ai_options['lite_model'] ) ? esc_attr( $this->houzi_ai_options['lite_model'] ) : ''
		);
	}

	public function features_callback() {
		$features = array(
			'search'      => 'AI Property Search (natural language)',
			// Hidden on the ai-feature-most-wanted branch (not shipped in this app):
			// 'describe'    => 'AI Description Writer',
			'ask_listing' => 'Ask About This Listing (buyer Q&amp;A)',
			// 'crm'         => 'CRM Copilot (lead summary, match ranking, email drafts)',
			'suggestions' => 'Tailored for You (home taxonomy suggestions)',
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
		$initial_mappings = array();

		if ( is_array( $aliases ) ) {
			// Group aliases that point at the same term onto one line.
			$grouped = array();
			foreach ( $aliases as $alias => $target ) {
				$grouped[ $target ][] = $alias;
			}
			foreach ( $grouped as $target => $alias_list ) {
				$lines[] = implode( ', ', $alias_list ) . ' => ' . $target;
				list( $tax, $slug ) = array_pad( explode( ':', $target, 2 ), 2, '' );
				$initial_mappings[] = array(
					'taxonomy' => $tax,
					'slug'     => $slug,
					'aliases'  => array_values( array_unique( $alias_list ) ),
				);
			}
		}

		// Fetch site taxonomy terms for term selector in modal.
		$site_terms = array(
			'property_city'    => array(),
			'property_area'    => array(),
			'property_state'   => array(),
			'property_country' => array(),
		);

		$tax_labels = array(
			'property_city'    => 'City',
			'property_area'    => 'Area',
			'property_state'   => 'State',
			'property_country' => 'Country',
		);

		foreach ( array_keys( $site_terms ) as $tax ) {
			$terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) );
			if ( ! is_wp_error( $terms ) && is_array( $terms ) ) {
				foreach ( $terms as $t ) {
					$site_terms[ $tax ][] = array(
						'slug' => $t->slug,
						'name' => $t->name,
					);
				}
			}
		}
		?>
		<!-- Hidden textarea for backward compatibility with WordPress form POST -->
		<textarea name="houzi_ai_options[location_aliases]" id="location_aliases_hidden" style="display:none;"><?php echo esc_textarea( implode( "\n", $lines ) ); ?></textarea>

		<div class="houzi-alias-wrapper" style="max-width: 840px;">
			<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
				<span class="description">
					Map search variations &amp; abbreviations to canonical taxonomy terms (e.g. <em>ny, nyc &rarr; City: new-york-city</em> or <em>کرانچی, کراچی &rarr; City: کراچی</em>).
				</span>
				<button type="button" class="button button-primary" id="houzi-open-add-alias-modal">
					<span class="dashicons dashicons-plus-alt2" style="vertical-align: middle; margin-right: 4px; font-size: 16px; line-height: 1.3;"></span> Add Location Alias
				</button>
			</div>

			<table class="widefat striped" id="houzi-alias-table" style="box-shadow: 0 1px 3px rgba(0,0,0,0.05); border-radius: 6px; overflow: hidden;">
				<thead>
					<tr>
						<th style="width: 15%;">Taxonomy</th>
						<th style="width: 30%;">Target Term / Slug</th>
						<th style="width: 40%;">Aliases</th>
						<th style="width: 15%; text-align: right;">Actions</th>
					</tr>
				</thead>
				<tbody id="houzi-alias-table-body">
					<!-- Rendered dynamically by JS -->
				</tbody>
			</table>
		</div>

		<!-- Modal Dialog HTML -->
		<div id="houzi-alias-modal-overlay" class="houzi-alias-modal-overlay">
			<div class="houzi-alias-modal-card">
				<div class="houzi-alias-modal-header">
					<h3 id="houzi-modal-title" style="margin: 0; font-size: 16px; font-weight: 600;">Add Location Alias</h3>
					<button type="button" id="houzi-modal-close-btn" class="houzi-modal-close">&times;</button>
				</div>
				<div class="houzi-alias-modal-body">
					<div id="houzi-modal-error" style="display:none; background:#fcf0f1; border-left:4px solid #d94f4f; padding:8px 12px; margin-bottom:14px; font-size:13px; color:#b32d2d;"></div>

					<div class="houzi-form-group" style="margin-bottom: 14px;">
						<label for="houzi-modal-tax-select" style="display:block; font-weight:600; margin-bottom:4px; font-size:13px;">Taxonomy</label>
						<select id="houzi-modal-tax-select" class="widefat" style="max-width:100%;">
							<option value="property_city">City (property_city)</option>
							<option value="property_area">Area (property_area)</option>
							<option value="property_state">State (property_state)</option>
							<option value="property_country">Country (property_country)</option>
						</select>
					</div>

					<div class="houzi-form-group" style="margin-bottom: 14px;">
						<label for="houzi-modal-term-select" style="display:block; font-weight:600; margin-bottom:4px; font-size:13px;">Target Term / Slug</label>
						<select id="houzi-modal-term-select" class="widefat" style="max-width:100%;">
							<!-- Populated based on selected taxonomy -->
						</select>
						<input type="text" id="houzi-modal-custom-slug" class="widefat" placeholder="Enter custom slug (e.g. کراچی or new-york-city)" style="margin-top: 6px; display: none;">
					</div>

					<div class="houzi-form-group" style="margin-bottom: 14px;">
						<label style="display:block; font-weight:600; margin-bottom:4px; font-size:13px;">Aliases for this target</label>
						<div id="houzi-modal-tag-container" class="houzi-tag-container">
							<!-- Tag chips rendered here -->
						</div>
						<div style="display: flex; gap: 6px; margin-top: 8px;">
							<input type="text" id="houzi-modal-new-alias-input" class="regular-text" placeholder="Type alias (e.g. ny or کرانچی) and press Enter" style="flex: 1;">
							<button type="button" id="houzi-modal-add-alias-tag-btn" class="button"><span class="dashicons dashicons-plus-alt" style="vertical-align:middle; margin-right:2px;"></span> Add</button>
						</div>
						<span class="description" style="font-size:12px; color:#646970;">You can add multiple aliases. Commas (,) separate multiple values.</span>
					</div>
				</div>
				<div class="houzi-alias-modal-footer">
					<button type="button" id="houzi-modal-cancel-btn" class="button">Cancel</button>
					<button type="button" id="houzi-modal-save-btn" class="button button-primary">Save Mapping</button>
				</div>
			</div>
		</div>

		<style>
			.houzi-alias-modal-overlay {
				position: fixed;
				top: 0; left: 0; right: 0; bottom: 0;
				background: rgba(0, 0, 0, 0.55);
				z-index: 100000;
				display: flex;
				align-items: center;
				justify-content: center;
				opacity: 0;
				pointer-events: none;
				transition: opacity 0.2s ease;
			}
			.houzi-alias-modal-overlay.active {
				opacity: 1;
				pointer-events: auto;
			}
			.houzi-alias-modal-card {
				background: #fff;
				width: 100%;
				max-width: 520px;
				border-radius: 8px;
				box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
				overflow: hidden;
			}
			.houzi-alias-modal-header {
				padding: 14px 20px;
				background: #f6f7f7;
				border-bottom: 1px solid #dcdcde;
				display: flex;
				justify-content: space-between;
				align-items: center;
			}
			.houzi-modal-close {
				background: none;
				border: none;
				font-size: 22px;
				cursor: pointer;
				color: #646970;
				line-height: 1;
				padding: 0;
			}
			.houzi-modal-close:hover {
				color: #d63638;
			}
			.houzi-alias-modal-body {
				padding: 20px;
			}
			.houzi-alias-modal-footer {
				padding: 12px 20px;
				background: #f6f7f7;
				border-top: 1px solid #dcdcde;
				display: flex;
				justify-content: flex-end;
				gap: 8px;
			}
			.houzi-tax-badge {
				display: inline-block;
				padding: 2px 8px;
				border-radius: 12px;
				font-size: 11px;
				font-weight: 600;
				text-transform: uppercase;
				letter-spacing: 0.3px;
			}
			.houzi-tax-property_city { background: #e7f1ff; color: #0c63e4; }
			.houzi-tax-property_area { background: #e6f4ea; color: #137333; }
			.houzi-tax-property_state { background: #feefc3; color: #b06000; }
			.houzi-tax-property_country { background: #fce8e6; color: #c5221f; }

			.houzi-alias-chip {
				display: inline-flex;
				align-items: center;
				background: #f0f0f1;
				border: 1px solid #c3c4c7;
				border-radius: 4px;
				padding: 2px 8px;
				font-size: 12px;
				margin: 2px 4px 2px 0;
				color: #1d2327;
			}
			.houzi-tag-chip {
				display: inline-flex;
				align-items: center;
				background: #2271b1;
				color: #fff;
				border-radius: 4px;
				padding: 3px 8px;
				font-size: 12px;
				margin: 3px;
			}
			.houzi-tag-chip-remove {
				margin-left: 6px;
				cursor: pointer;
				font-size: 14px;
				font-weight: bold;
				line-height: 1;
				opacity: 0.8;
			}
			.houzi-tag-chip-remove:hover {
				opacity: 1;
				color: #ffcdd2;
			}
			.houzi-tag-container {
				min-height: 38px;
				border: 1px solid #8c8f94;
				border-radius: 4px;
				padding: 4px 6px;
				background: #fff;
				display: flex;
				flex-wrap: wrap;
				align-items: center;
			}
		</style>

		<script>
		(function() {
			var initialMappings = <?php echo wp_json_encode( $initial_mappings ); ?>;
			var siteTerms = <?php echo wp_json_encode( $site_terms ); ?>;
			var taxLabels = <?php echo wp_json_encode( $tax_labels ); ?>;

			var mappings = Array.isArray(initialMappings) ? initialMappings : [];
			var currentEditIndex = null;
			var modalTags = [];

			var tableBody = document.getElementById('houzi-alias-table-body');
			var hiddenTextarea = document.getElementById('location_aliases_hidden');
			var modalOverlay = document.getElementById('houzi-alias-modal-overlay');
			var modalTitle = document.getElementById('houzi-modal-title');
			var modalError = document.getElementById('houzi-modal-error');
			var taxSelect = document.getElementById('houzi-modal-tax-select');
			var termSelect = document.getElementById('houzi-modal-term-select');
			var customSlugInput = document.getElementById('houzi-modal-custom-slug');
			var tagContainer = document.getElementById('houzi-modal-tag-container');
			var newAliasInput = document.getElementById('houzi-modal-new-alias-input');
			var addAliasTagBtn = document.getElementById('houzi-modal-add-alias-tag-btn');
			var openAddBtn = document.getElementById('houzi-open-add-alias-modal');
			var closeBtn = document.getElementById('houzi-modal-close-btn');
			var cancelBtn = document.getElementById('houzi-modal-cancel-btn');
			var saveBtn = document.getElementById('houzi-modal-save-btn');

			function syncHiddenTextarea() {
				var lines = [];
				mappings.forEach(function(item) {
					if (item.aliases && item.aliases.length > 0 && item.taxonomy && item.slug) {
						lines.push(item.aliases.join(', ') + ' => ' + item.taxonomy + ':' + item.slug);
					}
				});
				hiddenTextarea.value = lines.join('\n');
			}

			function renderTable() {
				tableBody.innerHTML = '';
				if (mappings.length === 0) {
					var tr = document.createElement('tr');
					tr.innerHTML = '<td colspan="4" style="text-align:center; padding: 24px; color: #646970;">No location aliases configured yet. Click "Add Location Alias" to add one.</td>';
					tableBody.appendChild(tr);
					return;
				}

				mappings.forEach(function(item, index) {
					var tr = document.createElement('tr');

					// Taxonomy badge
					var tdTax = document.createElement('td');
					var badge = document.createElement('span');
					badge.className = 'houzi-tax-badge houzi-tax-' + item.taxonomy;
					badge.textContent = taxLabels[item.taxonomy] || item.taxonomy;
					tdTax.appendChild(badge);

					// Target Slug
					var tdTarget = document.createElement('td');
					var displayName = item.slug;
					// Try to find term name in siteTerms
					if (siteTerms[item.taxonomy]) {
						for (var i = 0; i < siteTerms[item.taxonomy].length; i++) {
							if (siteTerms[item.taxonomy][i].slug === item.slug) {
								displayName = siteTerms[item.taxonomy][i].name + ' (' + item.slug + ')';
								break;
							}
						}
					}
					tdTarget.innerHTML = '<strong>' + escapeHtml(displayName) + '</strong><br><code style="font-size:11px;">' + escapeHtml(item.taxonomy + ':' + item.slug) + '</code>';

					// Aliases chips
					var tdAliases = document.createElement('td');
					if (item.aliases && item.aliases.length > 0) {
						item.aliases.forEach(function(alias) {
							var chip = document.createElement('span');
							chip.className = 'houzi-alias-chip';
							chip.textContent = alias;
							tdAliases.appendChild(chip);
						});
					} else {
						tdAliases.innerHTML = '<em style="color:#8c8f94;">None</em>';
					}

					// Actions
					var tdActions = document.createElement('td');
					tdActions.style.textAlign = 'right';
					tdActions.innerHTML = '<button type="button" class="button button-small houzi-btn-edit" data-index="' + index + '">Edit</button> ' +
						'<button type="button" class="button button-small button-link-delete houzi-btn-delete" data-index="' + index + '" style="margin-left:4px;">Delete</button>';

					tr.appendChild(tdTax);
					tr.appendChild(tdTarget);
					tr.appendChild(tdAliases);
					tr.appendChild(tdActions);
					tableBody.appendChild(tr);
				});

				syncHiddenTextarea();
			}

			function escapeHtml(text) {
				return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
			}

			function updateTermSelectOptions(selectedTax, selectedSlug) {
				termSelect.innerHTML = '';
				var terms = siteTerms[selectedTax] || [];

				var defaultOption = document.createElement('option');
				defaultOption.value = '';
				defaultOption.textContent = '-- Select ' + (taxLabels[selectedTax] || 'Term') + ' --';
				termSelect.appendChild(defaultOption);

				var slugFound = false;
				terms.forEach(function(t) {
					var opt = document.createElement('option');
					opt.value = t.slug;
					opt.textContent = t.name + ' (' + t.slug + ')';
					if (selectedSlug && t.slug === selectedSlug) {
						opt.selected = true;
						slugFound = true;
					}
					termSelect.appendChild(opt);
				});

				var customOpt = document.createElement('option');
				customOpt.value = '__custom__';
				customOpt.textContent = '+ Enter custom slug manually...';
				termSelect.appendChild(customOpt);

				if (selectedSlug && !slugFound) {
					customOpt.selected = true;
					customSlugInput.style.display = 'block';
					customSlugInput.value = selectedSlug;
				} else {
					customSlugInput.style.display = 'none';
					customSlugInput.value = '';
				}
			}

			function renderModalTags() {
				tagContainer.innerHTML = '';
				if (modalTags.length === 0) {
					tagContainer.innerHTML = '<span style="color:#a7aaad; font-style:italic; font-size:12px;">No aliases added yet. Type below to add.</span>';
					return;
				}
				modalTags.forEach(function(alias, i) {
					var chip = document.createElement('span');
					chip.className = 'houzi-tag-chip';
					chip.appendChild(document.createTextNode(alias));

					var removeBtn = document.createElement('span');
					removeBtn.className = 'houzi-tag-chip-remove';
					removeBtn.innerHTML = '&times;';
					removeBtn.onclick = function() {
						modalTags.splice(i, 1);
						renderModalTags();
					};
					chip.appendChild(removeBtn);
					tagContainer.appendChild(chip);
				});
			}

			function addAliasInputToTags() {
				var val = newAliasInput.value.trim();
				if (!val) return;
				var parts = val.split(',');
				parts.forEach(function(p) {
					var clean = p.trim();
					if (clean && modalTags.indexOf(clean) === -1) {
						modalTags.push(clean);
					}
				});
				newAliasInput.value = '';
				renderModalTags();
			}

			function openModal(editIndex, prefillAlias) {
				currentEditIndex = editIndex;
				modalError.style.display = 'none';
				modalError.textContent = '';

				if (editIndex !== null && mappings[editIndex]) {
					var item = mappings[editIndex];
					modalTitle.textContent = 'Edit Location Alias';
					taxSelect.value = item.taxonomy || 'property_city';
					updateTermSelectOptions(taxSelect.value, item.slug);
					modalTags = item.aliases ? item.aliases.slice() : [];
				} else {
					modalTitle.textContent = 'Add Location Alias';
					taxSelect.value = 'property_city';
					updateTermSelectOptions('property_city', '');
					modalTags = prefillAlias ? [prefillAlias] : [];
				}

				renderModalTags();
				modalOverlay.classList.add('active');
			}

			function closeModal() {
				modalOverlay.classList.remove('active');
			}

			// Event listeners
			taxSelect.addEventListener('change', function() {
				updateTermSelectOptions(taxSelect.value, '');
			});

			termSelect.addEventListener('change', function() {
				if (termSelect.value === '__custom__') {
					customSlugInput.style.display = 'block';
					customSlugInput.focus();
				} else {
					customSlugInput.style.display = 'none';
				}
			});

			addAliasTagBtn.addEventListener('click', addAliasInputToTags);
			newAliasInput.addEventListener('keydown', function(e) {
				if (e.key === 'Enter') {
					e.preventDefault();
					addAliasInputToTags();
				}
			});

			openAddBtn.addEventListener('click', function() {
				openModal(null);
			});

			closeBtn.addEventListener('click', closeModal);
			cancelBtn.addEventListener('click', closeModal);

			modalOverlay.addEventListener('click', function(e) {
				if (e.target === modalOverlay) {
					closeModal();
				}
			});

			saveBtn.addEventListener('click', function() {
				var tax = taxSelect.value;
				var slug = '';
				if (termSelect.value === '__custom__') {
					slug = customSlugInput.value.trim();
				} else {
					slug = termSelect.value;
				}

				if (!tax) {
					modalError.textContent = 'Please select a taxonomy.';
					modalError.style.display = 'block';
					return;
				}
				if (!slug) {
					modalError.textContent = 'Please select or enter a target term slug.';
					modalError.style.display = 'block';
					return;
				}
				if (modalTags.length === 0) {
					modalError.textContent = 'Please add at least one alias.';
					modalError.style.display = 'block';
					return;
				}

				var newMapping = {
					taxonomy: tax,
					slug: slug,
					aliases: modalTags
				};

				if (currentEditIndex !== null && mappings[currentEditIndex]) {
					mappings[currentEditIndex] = newMapping;
				} else {
					mappings.push(newMapping);
				}

				renderTable();
				closeModal();
			});

			// Table Edit & Delete delegation
			tableBody.addEventListener('click', function(e) {
				var editBtn = e.target.closest('.houzi-btn-edit');
				if (editBtn) {
					var idx = parseInt(editBtn.getAttribute('data-index'), 10);
					openModal(idx);
					return;
				}
				var delBtn = e.target.closest('.houzi-btn-delete');
				if (delBtn) {
					var idx = parseInt(delBtn.getAttribute('data-index'), 10);
					if (confirm('Are you sure you want to delete this alias mapping?')) {
						mappings.splice(idx, 1);
						renderTable();
					}
					return;
				}
			});

			// Misses table "+ Add as Alias" buttons
			document.addEventListener('click', function(e) {
				var missBtn = e.target.closest('.houzi-add-miss-alias-btn');
				if (missBtn) {
					e.preventDefault();
					var phrase = missBtn.getAttribute('data-phrase');
					openModal(null, phrase);
				}
			});

			// Initial render
			renderTable();
		})();
		</script>
		<?php
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
		<table class="widefat striped" style="max-width:680px">
			<thead><tr><th>Phrase</th><th>Times</th><th>Last Seen</th><th>Action</th></tr></thead>
			<tbody>
			<?php foreach ( $misses as $phrase => $stats ) : ?>
				<tr>
					<td><strong><?php echo esc_html( $phrase ); ?></strong></td>
					<td><?php echo intval( $stats['count'] ); ?></td>
					<td><?php echo esc_html( $stats['last'] ); ?></td>
					<td>
						<button type="button" class="button button-small houzi-add-miss-alias-btn" data-phrase="<?php echo esc_attr( $phrase ); ?>">
							<span class="dashicons dashicons-plus-alt2" style="vertical-align:middle; font-size:14px; margin-right:2px;"></span> Add as Alias
						</button>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
}
