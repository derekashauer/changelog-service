<?php
/**
 * Changelog Settings
 *
 * Handles plugin settings page and options.
 *
 * @package Changelog_Service
 */

declare(strict_types=1);

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Changelog_Service_Settings
 *
 * Manages plugin settings and admin page.
 *
 * @since 1.0.0
 */
class Changelog_Service_Settings {

	/**
	 * Option name for storing settings.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'caas_settings';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_filter( 'wp_redirect', array( $this, 'preserve_settings_tab' ) );
	}

	/**
	 * Preserve the active tab when redirecting after settings save.
	 *
	 * @param string $location The redirect URL.
	 * @return string Modified redirect URL.
	 */
	public function preserve_settings_tab( string $location ): string {
		// Only modify redirects to our settings page after saving.
		if ( strpos( $location, 'page=caas-settings' ) === false ) {
			return $location;
		}

		// Check if we have a tab to preserve.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by WordPress settings API.
		if ( isset( $_POST['caas_active_tab'] ) && ! empty( $_POST['caas_active_tab'] ) ) {
			$tab      = sanitize_key( $_POST['caas_active_tab'] );
			$location = add_query_arg( 'tab', $tab, $location );
		}

		return $location;
	}

	/**
	 * Get default badge type definitions.
	 *
	 * @return array
	 */
	private static function get_default_badge_types(): array {
		return array(
			'added'      => array(
				'prefixes' => 'Added, Add, New',
				'label'    => 'New',
				'color'    => '#0e7a2a',
				'emoji'    => '✨',
				'builtin'  => true,
			),
			'improved'   => array(
				'prefixes' => 'Improved, Improve, Enhanced, Updated, Update',
				'label'    => 'Improved',
				'color'    => '#0a4c7f',
				'emoji'    => '⚡️',
				'builtin'  => true,
			),
			'fixed'      => array(
				'prefixes' => 'Fixed, Fix, Bugfix',
				'label'    => 'Fixed',
				'color'    => '#8b2e00', // Darkened from #a93800 for WCAG AA (4.5:1 contrast)
				'emoji'    => '🔧',
				'builtin'  => true,
			),
			'security'   => array(
				'prefixes' => 'Security',
				'label'    => 'Security',
				'color'    => '#8b0000', // Already passes WCAG AA
				'emoji'    => '🔒',
				'builtin'  => true,
			),
			'dev'        => array(
				'prefixes' => 'Dev, Developer, Developers',
				'label'    => 'Dev',
				'color'    => '#1f262b', // Already passes WCAG AA
				'emoji'    => '🛠️',
				'builtin'  => true,
			),
			'deprecated' => array(
				'prefixes' => 'Deprecated, Deprecate',
				'label'    => 'Deprecated',
				'color'    => '#4b5563', // Darkened from #6b7280 for WCAG AA (4.5:1 contrast)
				'emoji'    => '⚠️',
				'builtin'  => true,
			),
			'removed'    => array(
				'prefixes' => 'Removed, Remove',
				'label'    => 'Removed',
				'color'    => '#b91c1c', // Darkened from #dc2626 for WCAG AA (4.5:1 contrast)
				'emoji'    => '🗑️',
				'builtin'  => true,
			),
			'tweak'      => array(
				'prefixes' => 'Tweak, Tweaked',
				'label'    => 'Tweak',
				'color'    => '#6d28d9', // Darkened from #7c3aed for WCAG AA (4.5:1 contrast)
				'emoji'    => '🔨',
				'builtin'  => true,
			),
		);
	}

	/**
	 * Get default settings.
	 *
	 * @return array
	 */
	private static function get_defaults(): array {
		return array(
			// Display options.
			'show_emojis'           => true,
			'show_meta_default'     => true,
			'default_version_limit' => 0,
			'default_heading_level' => 4,
			'default_order'         => 'newest',
			'default_filter_badges' => false,
			'default_search'        => false,
			'default_copy_link'     => false,
			'badge_style'           => 'rounded',
			'badge_position'        => 'left',
			'show_signup_link'      => false,
			'default_template'      => 'default',

			// Timeline template colors.
			'timeline_line_color'          => '#d1d5db',
			'timeline_version_bg_color'    => '#374151',
			'timeline_version_text_color'  => '#ffffff',

			// Badges - unified array of all badges (built-in + custom).
			'badges'                => self::get_default_badge_types(),
		);
	}

	/**
	 * Cached settings instance.
	 *
	 * @var array|null
	 */
	private static ?array $cached_settings = null;

	/**
	 * Get all settings with defaults.
	 *
	 * @return array
	 */
	public static function get_settings(): array {
		// Return cached settings if available.
		if ( null !== self::$cached_settings ) {
			return self::$cached_settings;
		}

		$defaults       = self::get_defaults();
		$default_badges = self::get_default_badge_types();
		$saved          = get_option( self::OPTION_NAME, array() );

		// Merge defaults.
		$settings = wp_parse_args( $saved, $defaults );

		// Ensure all default badge types exist in badges array.
		if ( ! isset( $settings['badges'] ) || ! is_array( $settings['badges'] ) ) {
			$settings['badges'] = $default_badges;
		} else {
			// Merge with defaults to ensure all built-in types exist.
			foreach ( $default_badges as $key => $default ) {
				if ( ! isset( $settings['badges'][ $key ] ) ) {
					$settings['badges'][ $key ] = $default;
				}
			}
		}

		// Cache settings.
		self::$cached_settings = $settings;

		return $settings;
	}

	/**
	 * Clear cached settings (call after updating settings).
	 */
	public static function clear_cache(): void {
		self::$cached_settings = null;
	}

	/**
	 * Get a single setting.
	 *
	 * @param string $key Setting key.
	 * @return mixed
	 */
	public static function get( string $key ) {
		$settings = self::get_settings();
		return $settings[ $key ] ?? null;
	}

	/**
	 * Add settings page to admin menu.
	 */
	public function add_settings_page(): void {
		add_submenu_page(
			'edit.php?post_type=changelog-service',
			__( 'Changelog as a Service Settings', 'changelog-service' ),
			__( 'Settings', 'changelog-service' ),
			'manage_options',
			'caas-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings.
	 */
	public function register_settings(): void {
		register_setting(
			'caas_settings_group',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => self::get_defaults(),
			)
		);
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array $input Input settings.
	 * @return array
	 */
	public function sanitize_settings( $input ): array {
		$sanitized      = array();
		$defaults       = self::get_defaults();
		$default_badges = self::get_default_badge_types();

		// Boolean fields.
		$sanitized['show_emojis']           = ! empty( $input['show_emojis'] );
		$sanitized['show_meta_default']     = ! empty( $input['show_meta_default'] );
		$sanitized['default_filter_badges'] = ! empty( $input['default_filter_badges'] );
		$sanitized['default_search']        = ! empty( $input['default_search'] );
		$sanitized['default_copy_link']     = ! empty( $input['default_copy_link'] );
		$sanitized['show_signup_link']      = ! empty( $input['show_signup_link'] );

		// Integer fields.
		$sanitized['default_version_limit'] = absint( $input['default_version_limit'] ?? 0 );
		$sanitized['default_heading_level'] = absint( $input['default_heading_level'] ?? 4 );
		// Validate heading level (1-6).
		if ( $sanitized['default_heading_level'] < 1 || $sanitized['default_heading_level'] > 6 ) {
			$sanitized['default_heading_level'] = 4;
		}

		// Select fields.
		$valid_styles = array( 'rounded', 'square', 'pill' );
		if ( isset( $input['badge_style'] ) && in_array( $input['badge_style'], $valid_styles, true ) ) {
			$sanitized['badge_style'] = $input['badge_style'];
		} else {
			$sanitized['badge_style'] = 'rounded';
		}

		$valid_positions = array( 'left', 'inline' );
		if ( isset( $input['badge_position'] ) && in_array( $input['badge_position'], $valid_positions, true ) ) {
			$sanitized['badge_position'] = $input['badge_position'];
		} else {
			$sanitized['badge_position'] = 'left';
		}

		// Template field.
		$valid_templates = array( 'default', 'timeline' );
		if ( isset( $input['default_template'] ) && in_array( $input['default_template'], $valid_templates, true ) ) {
			$sanitized['default_template'] = $input['default_template'];
		} else {
			$sanitized['default_template'] = 'default';
		}

		// Timeline colors.
		$sanitized['timeline_line_color'] = sanitize_hex_color( $input['timeline_line_color'] ?? '' ) ?: '#d1d5db';
		$sanitized['timeline_version_bg_color'] = sanitize_hex_color( $input['timeline_version_bg_color'] ?? '' ) ?: '#374151';
		$sanitized['timeline_version_text_color'] = sanitize_hex_color( $input['timeline_version_text_color'] ?? '' ) ?: '#ffffff';

		// Order field.
		$valid_orders = array( 'newest', 'oldest' );
		if ( isset( $input['default_order'] ) && in_array( $input['default_order'], $valid_orders, true ) ) {
			$sanitized['default_order'] = $input['default_order'];
		} else {
			$sanitized['default_order'] = 'newest';
		}

		// Badges array.
		$sanitized['badges'] = array();
		if ( ! empty( $input['badges'] ) && is_array( $input['badges'] ) ) {
			foreach ( $input['badges'] as $key => $badge ) {
				// Skip empty badges (no prefixes).
				if ( empty( $badge['prefixes'] ) ) {
					continue;
				}

				$sanitized_key = sanitize_key( $key );

				$default_color = '#666666';
				if ( isset( $default_badges[ $key ]['color'] ) ) {
					$default_color = $default_badges[ $key ]['color'];
				}

				$badge_color = sanitize_hex_color( $badge['color'] ?? '' );
				if ( empty( $badge_color ) ) {
					$badge_color = $default_color;
				}

				$sanitized['badges'][ $sanitized_key ] = array(
					'prefixes' => sanitize_text_field( $badge['prefixes'] ?? '' ),
					'label'    => sanitize_text_field( $badge['label'] ?? '' ),
					'color'    => $badge_color,
					'emoji'    => sanitize_text_field( $badge['emoji'] ?? '' ),
					'builtin'  => ! empty( $default_badges[ $key ] ),
				);
			}
		}

		// Ensure all built-in badges exist.
		foreach ( $default_badges as $key => $default ) {
			if ( ! isset( $sanitized['badges'][ $key ] ) ) {
				$sanitized['badges'][ $key ] = $default;
			}
		}

		// Clear cache after sanitizing (settings will be saved).
		self::clear_cache();

		return $sanitized;
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( string $hook ): void {
		if ( 'changelog-service_page_caas-settings' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );

		wp_enqueue_style(
			'caas-settings',
			CAAS_PLUGIN_URL . 'assets/css/settings.css',
			array(),
			CAAS_VERSION
		);

		wp_enqueue_script(
			'caas-settings',
			CAAS_PLUGIN_URL . 'assets/js/settings.js',
			array( 'jquery', 'wp-color-picker' ),
			CAAS_VERSION,
			true
		);

		wp_localize_script(
			'caas-settings',
			'caasSettings',
			array(
				'confirmDelete'    => __( 'Are you sure you want to delete this badge?', 'changelog-service' ),
				'optionName'       => self::OPTION_NAME,
				'emojiPickerTitle' => __( 'Select Emoji', 'changelog-service' ),
			)
		);
	}

	/**
	 * Render the settings page.
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = self::get_settings();
		?>
		<div class="wrap caas-settings-wrap">
			<?php settings_errors(); ?>
			<div class="caas-header">
				<div class="caas-header-content">
					<?php esc_html_e( 'Changelog Management by', 'changelog-service' ); ?> <img src="<?php echo esc_url( CAAS_PLUGIN_URL . 'assets/images/changelogwp-logo.png' ); ?>" alt="ChangelogWP" class="caas-logo" />
					<p class="caas-tagline"><a href="https://changelogwp.com" target="_blank"><?php esc_html_e( 'Visit website', 'changelog-service' ); ?></a></p>
				</div>
			</div>

			<h2></h2>

			<form method="post" action="options.php">
				<?php settings_fields( 'caas_settings_group' ); ?>
				<input type="hidden" name="caas_active_tab" id="caas-active-tab" value="<?php echo esc_attr( isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'display' ); ?>">

				<div class="caas-settings-tabs">
					<nav class="nav-tab-wrapper">
						<a href="#display" class="nav-tab nav-tab-active" data-tab="display">
							<?php esc_html_e( 'Display', 'changelog-service' ); ?>
						</a>
						<a href="#badges" class="nav-tab" data-tab="badges">
							<?php esc_html_e( 'Badges', 'changelog-service' ); ?>
						</a>
						<a href="#howto" class="nav-tab" data-tab="howto">
							<?php esc_html_e( 'How To', 'changelog-service' ); ?>
						</a>
					</nav>

					<!-- Display Tab -->
					<div id="tab-display" class="caas-tab-content active">
						<h2><?php esc_html_e( 'Display Options', 'changelog-service' ); ?></h2>
						<table class="form-table">
							<!-- Show/Hide Toggles - grouped together first -->
							<tr>
								<th scope="row"><?php esc_html_e( 'Show Meta Information', 'changelog-service' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[show_meta_default]" value="1" <?php checked( $settings['show_meta_default'] ); ?> />
										<?php esc_html_e( 'Show version/requirements info by default', 'changelog-service' ); ?>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Filter Badges', 'changelog-service' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[default_filter_badges]" value="1" <?php checked( $settings['default_filter_badges'] ); ?> />
										<?php esc_html_e( 'Show filter buttons for badge types by default', 'changelog-service' ); ?>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Search', 'changelog-service' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[default_search]" value="1" <?php checked( $settings['default_search'] ); ?> />
										<?php esc_html_e( 'Enable search functionality by default', 'changelog-service' ); ?>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Copy Link Button', 'changelog-service' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[default_copy_link]" value="1" <?php checked( $settings['default_copy_link'] ); ?> />
										<?php esc_html_e( 'Show copy link button for each version by default', 'changelog-service' ); ?>
									</label>
									<p class="description"><?php esc_html_e( 'Note: Copy link buttons are always enabled and appear on hover. This setting controls default behavior for new blocks.', 'changelog-service' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Show Emojis', 'changelog-service' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[show_emojis]" value="1" <?php checked( $settings['show_emojis'] ); ?> />
										<?php esc_html_e( 'Display emojis in badge labels by default', 'changelog-service' ); ?>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Signup Link', 'changelog-service' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[show_signup_link]" value="1" <?php checked( $settings['show_signup_link'] ); ?> />
										<?php esc_html_e( 'Show "Get notified of updates" link to changelogwp.com by default', 'changelog-service' ); ?>
									</label>
									<p class="description"><?php esc_html_e( 'When enabled with a WordPress.org plugin slug, displays a link for users to subscribe to changelog updates.', 'changelog-service' ); ?></p>
								</td>
							</tr>
							<!-- Styling/Display Options - grouped together after toggles -->
							<tr>
								<th scope="row"><?php esc_html_e( 'Default Version Limit', 'changelog-service' ); ?></th>
								<td>
									<input type="number" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[default_version_limit]" value="<?php echo esc_attr( $settings['default_version_limit'] ); ?>" min="0" max="100" class="small-text" />
									<p class="description"><?php esc_html_e( '0 = show all versions', 'changelog-service' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Default Heading Level', 'changelog-service' ); ?></th>
								<td>
									<select name="<?php echo esc_attr( self::OPTION_NAME ); ?>[default_heading_level]">
										<option value="1" <?php selected( $settings['default_heading_level'], 1 ); ?>>H1</option>
										<option value="2" <?php selected( $settings['default_heading_level'], 2 ); ?>>H2</option>
										<option value="3" <?php selected( $settings['default_heading_level'], 3 ); ?>>H3</option>
										<option value="4" <?php selected( $settings['default_heading_level'], 4 ); ?>>H4</option>
										<option value="5" <?php selected( $settings['default_heading_level'], 5 ); ?>>H5</option>
										<option value="6" <?php selected( $settings['default_heading_level'], 6 ); ?>>H6</option>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Default Order', 'changelog-service' ); ?></th>
								<td>
									<select name="<?php echo esc_attr( self::OPTION_NAME ); ?>[default_order]">
										<option value="newest" <?php selected( $settings['default_order'], 'newest' ); ?>><?php esc_html_e( 'Newest First', 'changelog-service' ); ?></option>
										<option value="oldest" <?php selected( $settings['default_order'], 'oldest' ); ?>><?php esc_html_e( 'Oldest First', 'changelog-service' ); ?></option>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Badge Style', 'changelog-service' ); ?></th>
								<td>
									<select name="<?php echo esc_attr( self::OPTION_NAME ); ?>[badge_style]">
										<option value="rounded" <?php selected( $settings['badge_style'], 'rounded' ); ?>><?php esc_html_e( 'Rounded', 'changelog-service' ); ?></option>
										<option value="square" <?php selected( $settings['badge_style'], 'square' ); ?>><?php esc_html_e( 'Square', 'changelog-service' ); ?></option>
										<option value="pill" <?php selected( $settings['badge_style'], 'pill' ); ?>><?php esc_html_e( 'Pill', 'changelog-service' ); ?></option>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Badge Position', 'changelog-service' ); ?></th>
								<td>
									<select name="<?php echo esc_attr( self::OPTION_NAME ); ?>[badge_position]">
										<option value="left" <?php selected( $settings['badge_position'], 'left' ); ?>><?php esc_html_e( 'Left (fixed column)', 'changelog-service' ); ?></option>
										<option value="inline" <?php selected( $settings['badge_position'], 'inline' ); ?>><?php esc_html_e( 'Inline (before text)', 'changelog-service' ); ?></option>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Template', 'changelog-service' ); ?></th>
								<td>
									<select name="<?php echo esc_attr( self::OPTION_NAME ); ?>[default_template]">
										<option value="default" <?php selected( $settings['default_template'], 'default' ); ?>><?php esc_html_e( 'Default', 'changelog-service' ); ?></option>
										<option value="timeline" <?php selected( $settings['default_template'], 'timeline' ); ?>><?php esc_html_e( 'Timeline', 'changelog-service' ); ?></option>
									</select>
									<p class="description"><?php esc_html_e( 'Default visual layout for changelogs. Can be overridden per shortcode/block.', 'changelog-service' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Timeline Line Color', 'changelog-service' ); ?></th>
								<td>
									<input type="text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[timeline_line_color]" value="<?php echo esc_attr( $settings['timeline_line_color'] ); ?>" class="caas-color-picker" />
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Timeline Version Badge Background', 'changelog-service' ); ?></th>
								<td>
									<input type="text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[timeline_version_bg_color]" value="<?php echo esc_attr( $settings['timeline_version_bg_color'] ); ?>" class="caas-color-picker" />
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Timeline Version Text Color', 'changelog-service' ); ?></th>
								<td>
									<input type="text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[timeline_version_text_color]" value="<?php echo esc_attr( $settings['timeline_version_text_color'] ); ?>" class="caas-color-picker" />
								</td>
							</tr>
						</table>
					</div>

					<!-- Badges Tab -->
					<div id="tab-badges" class="caas-tab-content">
						<h2><?php esc_html_e( 'Badge Types', 'changelog-service' ); ?></h2>
						<p class="description"><?php esc_html_e( 'Customize badge appearance and add your own custom badge types.', 'changelog-service' ); ?></p>

						<table class="widefat caas-badges-table" id="badges-table">
							<thead>
								<tr>
									<th class="col-prefixes"><?php esc_html_e( 'Prefixes', 'changelog-service' ); ?></th>
									<th class="col-label"><?php esc_html_e( 'Display Label', 'changelog-service' ); ?></th>
									<th class="col-color"><?php esc_html_e( 'Color', 'changelog-service' ); ?></th>
									<th class="col-emoji"><?php esc_html_e( 'Emoji', 'changelog-service' ); ?></th>
									<th class="col-preview"><?php esc_html_e( 'Preview', 'changelog-service' ); ?></th>
									<th class="col-actions"></th>
								</tr>
							</thead>
							<tbody id="badges-tbody">
								<?php
								$badges = $settings['badges'] ?? array();
								foreach ( $badges as $key => $badge ) :
									$is_builtin = ! empty( $badge['builtin'] );
									$prefixes   = $badge['prefixes'] ?? '';
									$label      = $badge['label'] ?? '';
									$color      = $badge['color'] ?? '#666666';
									$emoji      = $badge['emoji'] ?? '';
									?>
									<tr class="badge-row <?php echo $is_builtin ? 'builtin' : 'custom'; ?>" data-key="<?php echo esc_attr( $key ); ?>">
										<td class="col-prefixes">
											<input type="text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[badges][<?php echo esc_attr( $key ); ?>][prefixes]" value="<?php echo esc_attr( $prefixes ); ?>" class="regular-text badge-prefixes-input" placeholder="<?php esc_attr_e( 'Prefix1, Prefix2', 'changelog-service' ); ?>" />
										</td>
										<td class="col-label">
											<input type="text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[badges][<?php echo esc_attr( $key ); ?>][label]" value="<?php echo esc_attr( $label ); ?>" class="badge-label-input" data-key="<?php echo esc_attr( $key ); ?>" />
										</td>
										<td class="col-color">
											<input type="text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[badges][<?php echo esc_attr( $key ); ?>][color]" value="<?php echo esc_attr( $color ); ?>" class="caas-color-picker badge-color-input" data-key="<?php echo esc_attr( $key ); ?>" />
										</td>
								<td class="col-emoji">
									<input type="text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[badges][<?php echo esc_attr( $key ); ?>][emoji]" value="<?php echo esc_attr( $emoji ); ?>" class="badge-emoji-input" data-key="<?php echo esc_attr( $key ); ?>" placeholder="🔧" readonly />
								</td>
										<td class="col-preview">
											<span class="caas-badge-preview" id="preview-<?php echo esc_attr( $key ); ?>" style="background-color: <?php echo esc_attr( $color ); ?>;">
												<?php echo esc_html( ( $emoji ? $emoji . ' ' : '' ) . $label ); ?>
											</span>
										</td>
										<td class="col-actions">
											<?php if ( ! $is_builtin ) : ?>
												<button type="button" class="button-link button-link-delete remove-badge" title="<?php esc_attr_e( 'Remove badge', 'changelog-service' ); ?>">
													<span class="dashicons dashicons-trash"></span>
												</button>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>

						<p class="badges-actions">
							<button type="button" class="button" id="add-badge">
								<span class="dashicons dashicons-plus-alt2"></span>
								<?php esc_html_e( 'Add Badge', 'changelog-service' ); ?>
							</button>
						</p>

						<template id="badge-row-template">
							<tr class="badge-row custom" data-key="{{KEY}}">
								<td class="col-prefixes">
									<input type="text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[badges][{{KEY}}][prefixes]" value="" class="regular-text badge-prefixes-input" placeholder="<?php esc_attr_e( 'Prefix1, Prefix2', 'changelog-service' ); ?>" />
								</td>
								<td class="col-label">
									<input type="text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[badges][{{KEY}}][label]" value="" class="badge-label-input" data-key="{{KEY}}" placeholder="<?php esc_attr_e( 'Label', 'changelog-service' ); ?>" />
								</td>
								<td class="col-color">
									<input type="text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[badges][{{KEY}}][color]" value="#666666" class="caas-color-picker badge-color-input" data-key="{{KEY}}" />
								</td>
							<td class="col-emoji">
								<input type="text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[badges][{{KEY}}][emoji]" value="" class="badge-emoji-input" data-key="{{KEY}}" placeholder="🔧" readonly />
							</td>
								<td class="col-preview">
									<span class="caas-badge-preview" id="preview-{{KEY}}" style="background-color: #666666;"></span>
								</td>
								<td class="col-actions">
									<button type="button" class="button-link button-link-delete remove-badge" title="<?php esc_attr_e( 'Remove badge', 'changelog-service' ); ?>">
										<span class="dashicons dashicons-trash"></span>
									</button>
								</td>
							</tr>
						</template>

						<div class="caas-help-box">
							<h4><?php esc_html_e( 'How badges work', 'changelog-service' ); ?></h4>
							<p><?php esc_html_e( 'Enter comma-separated prefixes that trigger each badge. Changelog items must use a separator after the prefix:', 'changelog-service' ); ?></p>
							<ul>
								<li><code>Fixed - resolved the bug</code></li>
								<li><code>Added: new feature</code></li>
								<li><code>Fix : resolved the bug</code></li>
							</ul>
							<p><em><?php esc_html_e( 'Note: "Fixed something" without " - ", ": ", or " : " will NOT show a badge.', 'changelog-service' ); ?></em></p>
						</div>
					</div>

					<!-- How To Tab -->
					<div id="tab-howto" class="caas-tab-content">
						<h2><?php esc_html_e( 'How To Use Changelog as a Service', 'changelog-service' ); ?></h2>

						<h3><?php esc_html_e( 'Using the Gutenberg Block', 'changelog-service' ); ?></h3>
						<div class="caas-help-box">
							<ol>
								<li><?php esc_html_e( 'Edit any post or page in the WordPress editor', 'changelog-service' ); ?></li>
								<li><?php esc_html_e( 'Click the block inserter (+) button or type "/changelog"', 'changelog-service' ); ?></li>
								<li><?php esc_html_e( 'Search for "Changelog" and select the Changelog block', 'changelog-service' ); ?></li>
								<li><?php esc_html_e( 'In the block settings panel, select a changelog from the dropdown', 'changelog-service' ); ?></li>
								<li><?php esc_html_e( 'Configure display options such as heading level, order, search, and filters', 'changelog-service' ); ?></li>
								<li><?php esc_html_e( 'The block will automatically use your global settings defaults, which you can override per block', 'changelog-service' ); ?></li>
							</ol>
							<p><strong><?php esc_html_e( 'Note:', 'changelog-service' ); ?></strong> <?php esc_html_e( 'If you have Easy Digital Downloads with Software Licensing active, you can also select EDD products that have changelog data.', 'changelog-service' ); ?></p>
							<h4><?php esc_html_e( 'WordPress.org Plugin Option', 'changelog-service' ); ?></h4>
							<p><?php esc_html_e( 'You can also display changelogs from any plugin hosted on WordPress.org:', 'changelog-service' ); ?></p>
							<ol>
								<li><?php esc_html_e( 'Select "WordPress.org Plugin" from the changelog dropdown', 'changelog-service' ); ?></li>
								<li><?php esc_html_e( 'Enter the plugin slug in the text field (e.g., "akismet", "jetpack", "woocommerce")', 'changelog-service' ); ?></li>
								<li><?php esc_html_e( 'The changelog will be fetched from WordPress.org and cached for 24 hours', 'changelog-service' ); ?></li>
							</ol>
						</div>

						<h3><?php esc_html_e( 'Using the Shortcode', 'changelog-service' ); ?></h3>
						<div class="caas-help-box">
							<p><?php esc_html_e( 'You can display changelogs anywhere using the shortcode. All attributes are optional and will use your global settings defaults if not specified.', 'changelog-service' ); ?></p>

							<h4><?php esc_html_e( 'Basic Usage', 'changelog-service' ); ?></h4>
							<pre><code>[changelog_service id="123"]</code></pre>
							<p class="description"><?php esc_html_e( 'Display changelog by post ID', 'changelog-service' ); ?></p>

							<pre><code>[changelog_service slug="my-plugin"]</code></pre>
							<p class="description"><?php esc_html_e( 'Display changelog by post slug', 'changelog-service' ); ?></p>

							<pre><code>[changelog_service edd_id="456"]</code></pre>
							<p class="description"><?php esc_html_e( 'Display changelog from EDD product (requires EDD Software Licensing)', 'changelog-service' ); ?></p>

							<pre><code>[changelog_service plugin_slug="akismet"]</code></pre>
							<p class="description"><?php esc_html_e( 'Display changelog from a WordPress.org plugin (cached for 24 hours)', 'changelog-service' ); ?></p>

							<h4><?php esc_html_e( 'Shortcode Attributes', 'changelog-service' ); ?></h4>
							<table class="widefat" style="margin-top: 10px;">
								<thead>
									<tr>
										<th style="width: 200px;"><?php esc_html_e( 'Attribute', 'changelog-service' ); ?></th>
										<th><?php esc_html_e( 'Description', 'changelog-service' ); ?></th>
										<th style="width: 150px;"><?php esc_html_e( 'Default', 'changelog-service' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<tr>
										<td><code>id</code></td>
										<td><?php esc_html_e( 'Changelog post ID', 'changelog-service' ); ?></td>
										<td>-</td>
									</tr>
									<tr>
										<td><code>slug</code></td>
										<td><?php esc_html_e( 'Changelog post slug', 'changelog-service' ); ?></td>
										<td>-</td>
									</tr>
									<tr>
										<td><code>edd_id</code></td>
										<td><?php esc_html_e( 'EDD product ID (requires EDD Software Licensing)', 'changelog-service' ); ?></td>
										<td>-</td>
									</tr>
									<tr>
										<td><code>plugin_slug</code></td>
										<td><?php esc_html_e( 'WordPress.org plugin slug (e.g., "akismet", "jetpack"). Data is cached for 24 hours.', 'changelog-service' ); ?></td>
										<td>-</td>
									</tr>
									<tr>
										<td><code>show_meta</code></td>
										<td><?php esc_html_e( 'Show version/requirements meta information', 'changelog-service' ); ?></td>
										<td><?php echo esc_html( $settings['show_meta_default'] ? 'true' : 'false' ); ?></td>
									</tr>
									<tr>
										<td><code>limit</code></td>
										<td><?php esc_html_e( 'Maximum number of versions to display (0 = all)', 'changelog-service' ); ?></td>
										<td><?php echo esc_html( $settings['default_version_limit'] ); ?></td>
									</tr>
									<tr>
										<td><code>heading_level</code></td>
										<td><?php esc_html_e( 'Heading level for version numbers (1-6)', 'changelog-service' ); ?></td>
										<td>H<?php echo esc_html( $settings['default_heading_level'] ); ?></td>
									</tr>
									<tr>
										<td><code>order</code></td>
										<td><?php esc_html_e( 'Display order: newest or oldest', 'changelog-service' ); ?></td>
										<td><?php echo esc_html( $settings['default_order'] ); ?></td>
									</tr>
									<tr>
										<td><code>filter_badges</code></td>
										<td><?php esc_html_e( 'Show filter buttons for badge types', 'changelog-service' ); ?></td>
										<td><?php echo esc_html( $settings['default_filter_badges'] ? 'true' : 'false' ); ?></td>
									</tr>
									<tr>
										<td><code>search</code></td>
										<td><?php esc_html_e( 'Enable search functionality', 'changelog-service' ); ?></td>
										<td><?php echo esc_html( $settings['default_search'] ? 'true' : 'false' ); ?></td>
									</tr>
									<tr>
										<td><code>copy_link</code></td>
										<td><?php esc_html_e( 'Show copy link button for each version', 'changelog-service' ); ?></td>
										<td><?php echo esc_html( $settings['default_copy_link'] ? 'true' : 'false' ); ?></td>
									</tr>
									<tr>
										<td><code>show_emojis</code></td>
										<td><?php esc_html_e( 'Display emojis in badge labels', 'changelog-service' ); ?></td>
										<td><?php echo esc_html( $settings['show_emojis'] ? 'true' : 'false' ); ?></td>
									</tr>
									<tr>
										<td><code>badge_style</code></td>
										<td><?php esc_html_e( 'Badge style: rounded, square, or pill', 'changelog-service' ); ?></td>
										<td><?php echo esc_html( $settings['badge_style'] ); ?></td>
									</tr>
									<tr>
										<td><code>badge_position</code></td>
										<td><?php esc_html_e( 'Badge position: left (fixed column) or inline (before text)', 'changelog-service' ); ?></td>
										<td><?php echo esc_html( $settings['badge_position'] ); ?></td>
									</tr>
									<tr>
										<td><code>signup</code></td>
										<td><?php esc_html_e( 'Show signup link. Set to "true" to auto-generate URL from plugin_slug, or provide a custom URL.', 'changelog-service' ); ?></td>
										<td><?php echo esc_html( $settings['show_signup_link'] ? 'true' : 'false' ); ?></td>
									</tr>
									<tr>
										<td><code>template</code></td>
										<td><?php esc_html_e( 'Visual layout template: default or timeline', 'changelog-service' ); ?></td>
										<td><?php echo esc_html( $settings['default_template'] ); ?></td>
									</tr>
								</tbody>
							</table>

							<h4><?php esc_html_e( 'Examples', 'changelog-service' ); ?></h4>
							<pre><code>[changelog_service slug="my-plugin" show_meta="false" limit="5"]</code></pre>
							<p class="description"><?php esc_html_e( 'Show only the 5 most recent versions without meta information', 'changelog-service' ); ?></p>

							<pre><code>[changelog_service id="123" order="oldest" search="true"]</code></pre>
							<p class="description"><?php esc_html_e( 'Display oldest versions first with search enabled', 'changelog-service' ); ?></p>

							<pre><code>[changelog_service edd_id="456" heading_level="2" filter_badges="true"]</code></pre>
							<p class="description"><?php esc_html_e( 'Display EDD product changelog with H2 headings and badge filters', 'changelog-service' ); ?></p>

							<pre><code>[changelog_service plugin_slug="woocommerce" show_meta="true" limit="10"]</code></pre>
							<p class="description"><?php esc_html_e( 'Display WooCommerce changelog from WordPress.org with meta info and 10 versions', 'changelog-service' ); ?></p>

							<pre><code>[changelog_service slug="my-plugin" limit="10" order="oldest" search="true" filter_badges="true"]</code></pre>
							<p class="description"><?php esc_html_e( 'Full example with multiple options enabled', 'changelog-service' ); ?></p>

							<pre><code>[changelog_service plugin_slug="woocommerce" signup="true"]</code></pre>
							<p class="description"><?php esc_html_e( 'Display changelog with auto-generated signup link to ChangelogWP.com (uses plugin_slug)', 'changelog-service' ); ?></p>

							<pre><code>[changelog_service id="123" signup="https://changelogwp.com/plugin/my-plugin/"]</code></pre>
							<p class="description"><?php esc_html_e( 'Display changelog with custom signup URL', 'changelog-service' ); ?></p>

							<pre><code>[changelog_service slug="my-plugin" template="timeline"]</code></pre>
							<p class="description"><?php esc_html_e( 'Display changelog using the timeline template', 'changelog-service' ); ?></p>

							<pre><code>[changelog_service plugin_slug="woocommerce" template="timeline" search="true" filter_badges="true"]</code></pre>
							<p class="description"><?php esc_html_e( 'Timeline template with search and badge filters enabled', 'changelog-service' ); ?></p>
						</div>

						<h3><?php esc_html_e( 'Developer Filters', 'changelog-service' ); ?></h3>
						<div class="caas-help-box">
							<p><?php esc_html_e( 'Changelog as a Service provides several WordPress filters for developers to customize the output and behavior. Add these to your theme\'s functions.php file or a custom plugin.', 'changelog-service' ); ?></p>

							<h4><?php esc_html_e( 'Available Filters', 'changelog-service' ); ?></h4>

							<h5><code>caas_badge_configs</code></h5>
							<p><?php esc_html_e( 'Filter badge configurations (colors, labels, emojis).', 'changelog-service' ); ?></p>
							<pre><code>add_filter( 'caas_badge_configs', function( $configs ) {
	// Modify badge colors, labels, or emojis
	$configs['added']['color'] = '#00a32a';
	$configs['added']['label'] = 'New Feature';
	$configs['added']['emoji'] = '✨';
	return $configs;
} );</code></pre>
							<p class="description">
								<strong><?php esc_html_e( 'Parameters:', 'changelog-service' ); ?></strong><br>
								<?php esc_html_e( '$configs (array) - Array of badge configurations with keys like "added", "fixed", "improved", etc. Each contains "label", "color", and "emoji" keys.', 'changelog-service' ); ?>
							</p>

							<h5><code>caas_container_classes</code></h5>
							<p><?php esc_html_e( 'Filter CSS classes applied to the main changelog container.', 'changelog-service' ); ?></p>
							<pre><code>add_filter( 'caas_container_classes', function( $classes, $atts, $changelog ) {
	// Add custom classes based on conditions
	if ( $changelog->ID === 123 ) {
		$classes[] = 'my-custom-changelog';
	}
	return $classes;
}, 10, 3 );</code></pre>
							<p class="description">
								<strong><?php esc_html_e( 'Parameters:', 'changelog-service' ); ?></strong><br>
								<?php esc_html_e( '$classes (array) - Array of CSS class names', 'changelog-service' ); ?><br>
								<?php esc_html_e( '$atts (array) - Shortcode attributes', 'changelog-service' ); ?><br>
								<?php esc_html_e( '$changelog (WP_Post) - Changelog post object', 'changelog-service' ); ?>
							</p>

							<h5><code>caas_version_section_html</code></h5>
							<p><?php esc_html_e( 'Filter the HTML output for each version section.', 'changelog-service' ); ?></p>
							<pre><code>add_filter( 'caas_version_section_html', function( $html, $version, $items ) {
	// Modify the HTML for a version section
	// Example: Add custom wrapper
	$html = '<div class="custom-version-wrapper">' . $html . '</div>';
	return $html;
}, 10, 3 );</code></pre>
							<p class="description">
								<strong><?php esc_html_e( 'Parameters:', 'changelog-service' ); ?></strong><br>
								<?php esc_html_e( '$html (string) - The HTML output for the version section', 'changelog-service' ); ?><br>
								<?php esc_html_e( '$version (string) - Version string (e.g., "1.0.0")', 'changelog-service' ); ?><br>
								<?php esc_html_e( '$items (array) - Array of changelog items for this version', 'changelog-service' ); ?>
							</p>

							<h5><code>caas_badge_filters_html</code></h5>
							<p><?php esc_html_e( 'Filter the HTML output for badge filter buttons.', 'changelog-service' ); ?></p>
							<pre><code>add_filter( 'caas_badge_filters_html', function( $output, $badge_types ) {
	// Modify the badge filters HTML
	// Example: Add custom wrapper or modify button structure
	return $output;
}, 10, 2 );</code></pre>
							<p class="description">
								<strong><?php esc_html_e( 'Parameters:', 'changelog-service' ); ?></strong><br>
								<?php esc_html_e( '$output (string) - The HTML output for badge filters', 'changelog-service' ); ?><br>
								<?php esc_html_e( '$badge_types (array) - Array of badge type keys found in the changelog', 'changelog-service' ); ?>
							</p>

							<h5><code>caas_rest_changelog_data</code></h5>
							<p><?php esc_html_e( 'Filter changelog data returned by the REST API endpoint.', 'changelog-service' ); ?></p>
							<pre><code>add_filter( 'caas_rest_changelog_data', function( $data, $post ) {
	// Modify the REST API response data
	// Example: Add custom fields
	$data['custom_field'] = get_post_meta( $post->ID, 'custom_meta_key', true );
	return $data;
}, 10, 2 );</code></pre>
							<p class="description">
								<strong><?php esc_html_e( 'Parameters:', 'changelog-service' ); ?></strong><br>
								<?php esc_html_e( '$data (array) - Formatted changelog data array', 'changelog-service' ); ?><br>
								<?php esc_html_e( '$post (WP_Post) - Changelog post object', 'changelog-service' ); ?>
							</p>

							<h5><code>caas_post_type_args</code></h5>
							<p><?php esc_html_e( 'Filter the arguments used to register the changelog post type.', 'changelog-service' ); ?></p>
							<pre><code>add_filter( 'caas_post_type_args', function( $args ) {
	// Modify post type registration arguments
	// Example: Change the rewrite slug
	$args['rewrite']['slug'] = 'my-changelogs';
	// Example: Make the post type public
	$args['public'] = true;
	return $args;
} );</code></pre>
							<p class="description">
								<strong><?php esc_html_e( 'Parameters:', 'changelog-service' ); ?></strong><br>
								<?php esc_html_e( '$args (array) - Arguments array passed to register_post_type()', 'changelog-service' ); ?>
							</p>
						</div>

						<h3><?php esc_html_e( 'Badge Format', 'changelog-service' ); ?></h3>
						<div class="caas-help-box">
							<p><?php esc_html_e( 'To automatically display badges in your changelog entries, use the format "Prefix - ", "Prefix: ", or "Prefix : " at the start of each changelog item.', 'changelog-service' ); ?></p>
							<p><strong><?php esc_html_e( 'Important:', 'changelog-service' ); ?></strong> <?php esc_html_e( 'You must include a separator (dash or colon) after the prefix for the badge to appear. For example, "Fixed - ", "Added: ", or "Fix : " will work, but "Fixed something" without the separator will NOT show a badge.', 'changelog-service' ); ?></p>

							<h4><?php esc_html_e( 'Available Badge Types', 'changelog-service' ); ?></h4>
							<ul>
								<li><strong><?php esc_html_e( 'New', 'changelog-service' ); ?></strong> (<?php esc_html_e( 'green', 'changelog-service' ); ?>) - <code>Added</code>, <code>Add</code>, <code>New</code></li>
								<li><strong><?php esc_html_e( 'Improved', 'changelog-service' ); ?></strong> (<?php esc_html_e( 'blue', 'changelog-service' ); ?>) - <code>Improved</code>, <code>Improve</code>, <code>Enhanced</code>, <code>Updated</code>, <code>Update</code></li>
								<li><strong><?php esc_html_e( 'Fixed', 'changelog-service' ); ?></strong> (<?php esc_html_e( 'orange', 'changelog-service' ); ?>) - <code>Fixed</code>, <code>Fix</code>, <code>Bugfix</code></li>
								<li><strong><?php esc_html_e( 'Security', 'changelog-service' ); ?></strong> (<?php esc_html_e( 'red', 'changelog-service' ); ?>) - <code>Security</code></li>
								<li><strong><?php esc_html_e( 'Dev', 'changelog-service' ); ?></strong> (<?php esc_html_e( 'dark', 'changelog-service' ); ?>) - <code>Dev</code>, <code>Developer</code>, <code>Developers</code></li>
								<li><strong><?php esc_html_e( 'Deprecated', 'changelog-service' ); ?></strong> (<?php esc_html_e( 'gray', 'changelog-service' ); ?>) - <code>Deprecated</code>, <code>Deprecate</code></li>
								<li><strong><?php esc_html_e( 'Removed', 'changelog-service' ); ?></strong> (<?php esc_html_e( 'red', 'changelog-service' ); ?>) - <code>Removed</code>, <code>Remove</code></li>
								<li><strong><?php esc_html_e( 'Tweak', 'changelog-service' ); ?></strong> (<?php esc_html_e( 'purple', 'changelog-service' ); ?>) - <code>Tweak</code>, <code>Tweaked</code></li>
							</ul>

							<h4><?php esc_html_e( 'Examples', 'changelog-service' ); ?></h4>
							<ul>
								<li><code>* Fix - Resolved login issue</code></li>
								<li><code>* Fixed: Resolved login issue</code></li>
								<li><code>* Fix : Resolved login issue</code></li>
								<li><code>* Add - New feature added</code></li>
								<li><code>* Added: New feature added</code></li>
								<li><code>* Security - Patched vulnerability</code></li>
								<li><code>* Improved: Performance optimization</code></li>
							</ul>
						</div>

						<h3><?php esc_html_e( 'Search & Filter Behavior', 'changelog-service' ); ?></h3>
						<div class="caas-help-box">
							<p><?php esc_html_e( 'When search or filter features are enabled, the changelog displays version headers with only the matching changelog items. This makes it easy to see which version each entry belongs to while filtering results.', 'changelog-service' ); ?></p>
							<p><?php esc_html_e( 'Copy link buttons are always enabled and appear on hover next to each version number, allowing users to quickly copy a direct link to a specific version.', 'changelog-service' ); ?></p>
						</div>
					</div>
				</div>

				<?php submit_button(); ?>
			</form>

			<!-- Emoji Picker Container -->
			<div id="emoji-picker-container" style="display: none; position: absolute; z-index: 100100;">
				<emoji-picker></emoji-picker>
			</div>

			<?php
			// Load emoji-picker-element as ES module (Apache-2.0 license).
			// See: https://github.com/nolanlawson/emoji-picker-element
			wp_enqueue_script(
				'caas-emoji-picker',
				CAAS_PLUGIN_URL . 'assets/js/vendor/picker.js',
				array(),
				CAAS_VERSION,
				false
			);
			// Add type="module" attribute.
			add_filter(
				'script_loader_tag',
				function( $tag, $handle ) {
					if ( 'caas-emoji-picker' === $handle ) {
						$tag = str_replace( '<script ', '<script type="module" ', $tag );
					}
					return $tag;
				},
				10,
				2
			);
			?>
		</div>
		<?php
	}
}
