<?php
/**
 * Changelog Block
 *
 * Registers a Gutenberg block that wraps the changelog shortcode.
 *
 * @package Changelog_Service
 */

declare(strict_types=1);

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Changelog_Service_Block
 *
 * Handles Gutenberg block registration.
 *
 * @since 1.0.0
 */
class Changelog_Service_Block {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_block' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
	}

	/**
	 * Register the block.
	 */
	public function register_block(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		// Get settings defaults.
		$settings              = Changelog_Service_Settings::get_settings();
		$show_meta_default     = ! empty( $settings['show_meta_default'] );
		$limit_default         = (int) ( $settings['default_version_limit'] ?? 0 );
		$heading_level_default = absint( $settings['default_heading_level'] ?? 4 );
		if ( $heading_level_default < 1 || $heading_level_default > 6 ) {
			$heading_level_default = 4;
		}
		$order_default          = isset( $settings['default_order'] ) && in_array( $settings['default_order'], array( 'newest', 'oldest' ), true ) ? $settings['default_order'] : 'newest';
		$filter_badges_default  = ! empty( $settings['default_filter_badges'] );
		$search_default         = ! empty( $settings['default_search'] );
		$copy_link_default      = ! empty( $settings['default_copy_link'] );
		$show_emojis_default    = ! empty( $settings['show_emojis'] );
		$badge_style_default    = isset( $settings['badge_style'] ) && in_array( $settings['badge_style'], array( 'rounded', 'square', 'pill' ), true ) ? $settings['badge_style'] : 'rounded';
		$badge_position_default = isset( $settings['badge_position'] ) && in_array( $settings['badge_position'], array( 'left', 'inline' ), true ) ? $settings['badge_position'] : 'left';
		$signup_default         = ! empty( $settings['show_signup_link'] );
		$template_default       = isset( $settings['default_template'] ) && in_array( $settings['default_template'], array( 'default', 'timeline' ), true ) ? $settings['default_template'] : 'default';

		register_block_type(
			'changelogwp/changelog',
			array(
				'editor_script'   => 'caas-block-editor',
				'editor_style'    => 'caas-block-editor-style',
				'render_callback' => array( $this, 'render_block' ),
				'attributes'      => array(
					'changelogId'   => array(
						'type'    => 'number',
						'default' => 0,
					),
					'eddId'         => array(
						'type'    => 'number',
						'default' => 0,
					),
					'pluginSlug'    => array(
						'type'    => 'string',
						'default' => '',
					),
					'wporgSelected' => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'showMeta'      => array(
						'type'    => 'boolean',
						'default' => $show_meta_default,
					),
					'limit'         => array(
						'type'    => 'number',
						'default' => $limit_default,
					),
					'headingLevel'  => array(
						'type'    => 'number',
						'default' => $heading_level_default,
					),
					'order'         => array(
						'type'    => 'string',
						'default' => $order_default,
					),
					'filterBadges'  => array(
						'type'    => 'boolean',
						'default' => $filter_badges_default,
					),
					'search'        => array(
						'type'    => 'boolean',
						'default' => $search_default,
					),
					'copyLink'      => array(
						'type'    => 'boolean',
						'default' => $copy_link_default, // Use global default, same as showMeta.
					),
					'showEmojis'    => array(
						'type'    => 'boolean',
						'default' => $show_emojis_default,
					),
					'badgeStyle'    => array(
						'type'    => 'string',
						'default' => $badge_style_default,
					),
					'badgePosition' => array(
						'type'    => 'string',
						'default' => $badge_position_default,
					),
					'signupEnabled' => array(
						'type'    => 'boolean',
						'default' => $signup_default,
					),
					'signupUrl'     => array(
						'type'    => 'string',
						'default' => '',
					),
					'template'      => array(
						'type'    => 'string',
						'default' => $template_default,
					),
				),
			)
		);
	}

	/**
	 * Check if EDD and Software Licensing are active.
	 *
	 * @return bool
	 */
	private function is_edd_sl_active(): bool {
		return class_exists( 'Easy_Digital_Downloads' ) && class_exists( 'EDD_Software_Licensing' );
	}

	/**
	 * Enqueue editor assets.
	 */
	public function enqueue_editor_assets(): void {
		// Get all changelogs for the selector.
		$changelogs = get_posts(
			array(
				'post_type'      => 'changelog-service',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$changelog_options = array();

		// Add default placeholder option.
		$changelog_options[] = array(
			'value' => 0,
			'label' => __( 'Select a Changelog', 'changelog-service' ),
		);

		foreach ( $changelogs as $changelog ) {
			// Only add if post has a valid ID and non-empty title.
			if ( ! empty( $changelog->ID ) && ! empty( $changelog->post_title ) && trim( $changelog->post_title ) !== '' ) {
				$changelog_options[] = array(
					'value' => (int) $changelog->ID,
					'label' => sanitize_text_field( trim( $changelog->post_title ) ),
				);
			}
		}

		// Add EDD products if EDD and Software Licensing are active.
		$edd_products = array();
		if ( $this->is_edd_sl_active() ) {
			$edd_downloads = get_posts(
				array(
					'post_type'      => 'download',
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'orderby'        => 'title',
					'order'          => 'ASC',
				)
			);

			foreach ( $edd_downloads as $download ) {
				// Only add if download has valid ID, non-empty title, and changelog content.
				if ( ! empty( $download->ID ) && ! empty( $download->post_title ) && trim( $download->post_title ) !== '' ) {
					$changelog_content = get_post_meta( $download->ID, '_edd_sl_changelog', true );
					if ( ! empty( $changelog_content ) && trim( $changelog_content ) !== '' ) {
						$edd_products[] = array(
							'value' => (int) $download->ID,
							'label' => sanitize_text_field( trim( $download->post_title ) ) . ' (EDD Product)',
						);
					}
				}
			}
		}

		wp_enqueue_script(
			'caas-block-editor',
			CAAS_PLUGIN_URL . 'assets/js/block.js',
			array( 'wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render' ),
			CAAS_VERSION,
			true
		);

		// Get settings defaults to pass to JavaScript.
		$settings              = Changelog_Service_Settings::get_settings();
		$show_meta_default     = ! empty( $settings['show_meta_default'] );
		$limit_default         = (int) ( $settings['default_version_limit'] ?? 0 );
		$heading_level_default = absint( $settings['default_heading_level'] ?? 4 );
		if ( $heading_level_default < 1 || $heading_level_default > 6 ) {
			$heading_level_default = 4;
		}
		$order_default          = isset( $settings['default_order'] ) && in_array( $settings['default_order'], array( 'newest', 'oldest' ), true ) ? $settings['default_order'] : 'newest';
		$filter_badges_default  = ! empty( $settings['default_filter_badges'] );
		$search_default         = ! empty( $settings['default_search'] );
		$copy_link_default      = ! empty( $settings['default_copy_link'] );
		$show_emojis_default    = ! empty( $settings['show_emojis'] );
		$badge_style_default    = isset( $settings['badge_style'] ) && in_array( $settings['badge_style'], array( 'rounded', 'square', 'pill' ), true ) ? $settings['badge_style'] : 'rounded';
		$badge_position_default = isset( $settings['badge_position'] ) && in_array( $settings['badge_position'], array( 'left', 'inline' ), true ) ? $settings['badge_position'] : 'left';
		$signup_default         = ! empty( $settings['show_signup_link'] );
		$template_default       = isset( $settings['default_template'] ) && in_array( $settings['default_template'], array( 'default', 'timeline' ), true ) ? $settings['default_template'] : 'default';

		wp_localize_script(
			'caas-block-editor',
			'caasBlock',
			array(
				'changelogs'   => $changelog_options,
				'eddProducts'  => $edd_products,
				'eddSlActive'  => $this->is_edd_sl_active(),
				'wporgEnabled' => true,
				'defaults'     => array(
					'showMeta'      => $show_meta_default,
					'limit'         => $limit_default,
					'headingLevel'  => $heading_level_default,
					'order'         => $order_default,
					'filterBadges'  => $filter_badges_default,
					'search'        => $search_default,
					'copyLink'      => $copy_link_default,
					'showEmojis'    => $show_emojis_default,
					'badgeStyle'    => $badge_style_default,
					'badgePosition' => $badge_position_default,
					'signup'        => $signup_default,
					'template'      => $template_default,
				),
			)
		);

		wp_enqueue_style(
			'caas-block-editor-style',
			CAAS_PLUGIN_URL . 'assets/css/changelog.css',
			array(),
			CAAS_VERSION
		);
	}

	/**
	 * Render the block.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public function render_block( array $attributes ): string {
		// Get settings defaults for attributes that use global defaults in block registration.
		$settings              = Changelog_Service_Settings::get_settings();
		$show_meta_default     = ! empty( $settings['show_meta_default'] );
		$limit_default         = (int) ( $settings['default_version_limit'] ?? 0 );
		$heading_level_default = absint( $settings['default_heading_level'] ?? 4 );
		if ( $heading_level_default < 1 || $heading_level_default > 6 ) {
			$heading_level_default = 4;
		}
		$order_default          = isset( $settings['default_order'] ) && in_array( $settings['default_order'], array( 'newest', 'oldest' ), true ) ? $settings['default_order'] : 'newest';
		$filter_badges_default  = ! empty( $settings['default_filter_badges'] );
		$search_default         = ! empty( $settings['default_search'] );
		$copy_link_default      = ! empty( $settings['default_copy_link'] );
		$show_emojis_default    = ! empty( $settings['show_emojis'] );
		$badge_style_default    = isset( $settings['badge_style'] ) && in_array( $settings['badge_style'], array( 'rounded', 'square', 'pill' ), true ) ? $settings['badge_style'] : 'rounded';
		$badge_position_default = isset( $settings['badge_position'] ) && in_array( $settings['badge_position'], array( 'left', 'inline' ), true ) ? $settings['badge_position'] : 'left';
		$signup_default         = ! empty( $settings['show_signup_link'] ) ? 'true' : '';
		$template_default       = isset( $settings['default_template'] ) && in_array( $settings['default_template'], array( 'default', 'timeline' ), true ) ? $settings['default_template'] : 'default';

		$id            = isset( $attributes['changelogId'] ) ? (int) $attributes['changelogId'] : 0;
		$edd_id        = isset( $attributes['eddId'] ) ? (int) $attributes['eddId'] : 0;
		$plugin_slug   = isset( $attributes['pluginSlug'] ) ? sanitize_title( $attributes['pluginSlug'] ) : '';
		$limit         = isset( $attributes['limit'] ) ? (int) $attributes['limit'] : $limit_default;
		$heading_level = isset( $attributes['headingLevel'] ) ? absint( $attributes['headingLevel'] ) : $heading_level_default;
		$order         = isset( $attributes['order'] ) && in_array( $attributes['order'], array( 'newest', 'oldest' ), true ) ? $attributes['order'] : $order_default;

		// Handle boolean attributes: if explicitly set (even to false), use it. Otherwise use global settings default.
		// Gutenberg may omit false boolean values, so we check if the key exists.
		// All use the same pattern: array_key_exists check, filter_var conversion, fallback to global default.
		$show_meta_bool     = array_key_exists( 'showMeta', $attributes ) ? filter_var( $attributes['showMeta'], FILTER_VALIDATE_BOOLEAN ) : $show_meta_default;
		$filter_badges_bool = array_key_exists( 'filterBadges', $attributes ) ? filter_var( $attributes['filterBadges'], FILTER_VALIDATE_BOOLEAN ) : $filter_badges_default;
		$search_bool        = array_key_exists( 'search', $attributes ) ? filter_var( $attributes['search'], FILTER_VALIDATE_BOOLEAN ) : $search_default;
		$copy_link_bool     = array_key_exists( 'copyLink', $attributes ) ? filter_var( $attributes['copyLink'], FILTER_VALIDATE_BOOLEAN ) : $copy_link_default;
		$show_emojis_bool   = array_key_exists( 'showEmojis', $attributes ) ? filter_var( $attributes['showEmojis'], FILTER_VALIDATE_BOOLEAN ) : $show_emojis_default;

		// Convert to string for shortcode.
		$show_meta     = $show_meta_bool ? 'true' : 'false';
		$filter_badges = $filter_badges_bool ? 'true' : 'false';
		$search        = $search_bool ? 'true' : 'false';
		$copy_link     = $copy_link_bool ? 'true' : 'false';
		$show_emojis   = $show_emojis_bool ? 'true' : 'false';

		$badge_style    = isset( $attributes['badgeStyle'] ) && in_array( $attributes['badgeStyle'], array( 'rounded', 'square', 'pill' ), true ) ? $attributes['badgeStyle'] : $badge_style_default;
		$badge_position = isset( $attributes['badgePosition'] ) && in_array( $attributes['badgePosition'], array( 'left', 'inline' ), true ) ? $attributes['badgePosition'] : $badge_position_default;

		// Validate heading level (1-6).
		if ( $heading_level < 1 || $heading_level > 6 ) {
			$heading_level = 4;
		}

		$shortcode_atts = array();

		if ( ! empty( $plugin_slug ) ) {
			$shortcode_atts[] = sprintf( 'plugin_slug="%s"', esc_attr( $plugin_slug ) );
		} elseif ( $edd_id > 0 ) {
			$shortcode_atts[] = sprintf( 'edd_id="%d"', $edd_id );
		} elseif ( $id > 0 ) {
			$shortcode_atts[] = sprintf( 'id="%d"', $id );
		}

		// Always pass all block attributes so shortcode knows they're explicitly set.
		// This ensures block settings override general settings defaults.
		$shortcode_atts[] = sprintf( 'show_meta="%s"', $show_meta );
		$shortcode_atts[] = sprintf( 'limit="%d"', $limit );
		$shortcode_atts[] = sprintf( 'heading_level="%d"', $heading_level );
		$shortcode_atts[] = sprintf( 'order="%s"', $order );
		$shortcode_atts[] = sprintf( 'filter_badges="%s"', $filter_badges );
		$shortcode_atts[] = sprintf( 'search="%s"', $search );
		$shortcode_atts[] = sprintf( 'copy_link="%s"', $copy_link );
		$shortcode_atts[] = sprintf( 'show_emojis="%s"', $show_emojis );
		$shortcode_atts[] = sprintf( 'badge_style="%s"', $badge_style );
		$shortcode_atts[] = sprintf( 'badge_position="%s"', $badge_position );

		// Template attribute.
		$template = isset( $attributes['template'] ) && in_array( $attributes['template'], array( 'default', 'timeline' ), true ) ? $attributes['template'] : $template_default;
		$shortcode_atts[] = sprintf( 'template="%s"', $template );

		// Handle signup attributes.
		$signup_enabled = array_key_exists( 'signupEnabled', $attributes ) ? filter_var( $attributes['signupEnabled'], FILTER_VALIDATE_BOOLEAN ) : ! empty( $signup_default );
		$signup_url     = isset( $attributes['signupUrl'] ) ? $attributes['signupUrl'] : '';

		if ( $signup_enabled ) {
			// If URL is provided, use it; otherwise use "true" to auto-generate from plugin_slug
			if ( ! empty( $signup_url ) ) {
				$shortcode_atts[] = sprintf( 'signup="%s"', esc_url( $signup_url ) );
			} else {
				$shortcode_atts[] = 'signup="true"';
			}
		}

		$shortcode_string = '[changelog_service ' . implode( ' ', $shortcode_atts ) . ']';

		return do_shortcode( $shortcode_string );
	}
}
