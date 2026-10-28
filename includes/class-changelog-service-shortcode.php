<?php
/**
 * Changelog Shortcode
 *
 * Handles the [changelog] shortcode for displaying changelogs with badges.
 *
 * Badge system inspired by EDD Changelog Badges by Very Good Plugins.
 * https://verygoodplugins.com/
 *
 * @package Changelog_Service
 */

declare(strict_types=1);

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Changelog_Service_Shortcode
 *
 * Renders changelog content with color-coded badges.
 *
 * @since 1.0.0
 */
class Changelog_Service_Shortcode {

	/**
	 * Post type slug.
	 *
	 * @var string
	 */
	const POST_TYPE = 'changelog-service';

	/**
	 * Badge configurations (lazy-loaded).
	 *
	 * @var array|null
	 */
	private ?array $badge_configs = null;

	/**
	 * Plugin settings (lazy-loaded).
	 *
	 * @var array|null
	 */
	private ?array $settings = null;

	/**
	 * Show emojis setting for current shortcode instance.
	 *
	 * @var bool|null
	 */
	private ?bool $show_emojis_instance = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_shortcode( 'changelog_service', array( $this, 'render_shortcode' ) );
	}

	/**
	 * Get settings (lazy-loaded).
	 *
	 * @return array
	 */
	private function get_settings(): array {
		if ( ! isset( $this->settings ) ) {
			$this->settings = Changelog_Service_Settings::get_settings();
		}
		return $this->settings;
	}

	/**
	 * Get badge configurations (lazy-loaded).
	 *
	 * Badge system inspired by EDD Changelog Badges by Very Good Plugins.
	 * Each badge type supports multiple prefix variations (e.g., Fix/Fixed, Add/Added).
	 *
	 * @return array
	 */
	public function get_badge_configs(): array {
		if ( isset( $this->badge_configs ) ) {
			return $this->badge_configs;
		}

		$settings = $this->get_settings();
		$configs  = array();

		// Build configs from the unified badges array in settings.
		$badges = $settings['badges'] ?? array();

		foreach ( $badges as $key => $badge ) {
			if ( empty( $badge['prefixes'] ) ) {
				continue;
			}

			// Parse comma-separated prefixes into array.
			$prefixes = array_map( 'trim', explode( ',', $badge['prefixes'] ) );

			$configs[ $key ] = array(
				'prefixes'   => $prefixes,
				'color'      => $badge['color'] ?? '#666666',
				'badge_text' => $badge['label'] ?? '',
				'emoji'      => $badge['emoji'] ?? '',
				'class'      => sanitize_html_class( $key ),
			);
		}

		/**
		 * Filter badge configurations.
		 *
		 * @param array $configs Badge configurations.
		 */
		$this->badge_configs = apply_filters( 'caas_badge_configs', $configs );

		return $this->badge_configs;
	}

	/**
	 * Render the shortcode.
	 *
	 * @param array  $atts    Shortcode attributes.
	 * @param string $content Shortcode content.
	 * @return string
	 */
	public function render_shortcode( $atts, string $content = '' ): string {
		// Lazy-load settings.
		$settings = $this->get_settings();

		// Get defaults from settings.
		$show_meta_default     = $settings['show_meta_default'] ? 'true' : 'false';
		$limit_default         = (int) $settings['default_version_limit'];
		$heading_level_default = absint( $settings['default_heading_level'] ?? 4 );
		// Validate heading level (1-6).
		if ( $heading_level_default < 1 || $heading_level_default > 6 ) {
			$heading_level_default = 4;
		}
		$order_default          = isset( $settings['default_order'] ) && in_array( $settings['default_order'], array( 'newest', 'oldest' ), true ) ? $settings['default_order'] : 'newest';
		$filter_badges_default  = ! empty( $settings['default_filter_badges'] ) ? 'true' : 'false';
		$search_default         = ! empty( $settings['default_search'] ) ? 'true' : 'false';
		$copy_link_default      = ! empty( $settings['default_copy_link'] ) ? 'true' : 'false';
		$show_emojis_default    = ! empty( $settings['show_emojis'] ) ? 'true' : 'false';
		$badge_style_default    = isset( $settings['badge_style'] ) && in_array( $settings['badge_style'], array( 'rounded', 'square', 'pill' ), true ) ? $settings['badge_style'] : 'rounded';
		$badge_position_default = isset( $settings['badge_position'] ) && in_array( $settings['badge_position'], array( 'left', 'inline' ), true ) ? $settings['badge_position'] : 'left';

		$signup_default   = ! empty( $settings['show_signup_link'] ) ? 'true' : 'false';
		$template_default = isset( $settings['default_template'] ) && in_array( $settings['default_template'], array( 'default', 'timeline' ), true ) ? $settings['default_template'] : 'default';

		$atts = shortcode_atts(
			array(
				'id'             => 0,
				'slug'           => '',
				'edd_id'         => 0,
				'plugin_slug'    => '',
				'show_meta'      => $show_meta_default,
				'limit'          => $limit_default,
				'heading_level'  => $heading_level_default,
				'order'          => $order_default,
				'filter_badges'  => $filter_badges_default,
				'search'         => $search_default,
				'copy_link'      => $copy_link_default,
				'show_emojis'    => $show_emojis_default,
				'badge_style'    => $badge_style_default,
				'badge_position' => $badge_position_default,
				'signup'         => $signup_default,
				'template'       => $template_default,
			),
			$atts,
			'changelog'
		);

		// Validate and sanitize badge style and position.
		if ( ! in_array( $atts['badge_style'], array( 'rounded', 'square', 'pill' ), true ) ) {
			$atts['badge_style'] = $badge_style_default;
		}
		if ( ! in_array( $atts['badge_position'], array( 'left', 'inline' ), true ) ) {
			$atts['badge_position'] = $badge_position_default;
		}
		if ( ! in_array( $atts['template'], array( 'default', 'timeline' ), true ) ) {
			$atts['template'] = $template_default;
		}

		// Store show_emojis for this instance (used in render_item).
		$this->show_emojis_instance = filter_var( $atts['show_emojis'], FILTER_VALIDATE_BOOLEAN );

		// Get the changelog post.
		$changelog = $this->get_changelog( $atts );

		if ( ! $changelog ) {
			return '';
		}

		// Enqueue styles and scripts.
		wp_enqueue_style( 'caas-changelog' );

		// Always enqueue frontend JS for copy link functionality and interactive features.
		wp_enqueue_script( 'caas-frontend' );

		// Get changelog content.
		$changelog_content = $changelog->post_content;

		if ( empty( $changelog_content ) ) {
			return '';
		}

		// Parse and format the changelog.
		// Validate heading level (1-6) - already validated in shortcode_atts defaults, but ensure safety.
		$heading_level = absint( $atts['heading_level'] );
		if ( $heading_level < 1 || $heading_level > 6 ) {
			$heading_level = 4;
		}

		// Validate order - already validated in shortcode_atts defaults, but ensure safety.
		$order = in_array( $atts['order'], array( 'newest', 'oldest' ), true ) ? $atts['order'] : 'newest';

		// Check if copy link is enabled.
		// shortcode_atts returns strings, so we need to check for string 'true' or 'false'.
		$copy_link_value = isset( $atts['copy_link'] ) ? $atts['copy_link'] : $copy_link_default;
		// Convert string 'true'/'false' to boolean using filter_var which handles 'true', 'false', '1', '0', 1, 0, true, false.
		$copy_link_enabled = filter_var( $copy_link_value, FILTER_VALIDATE_BOOLEAN );

		// Check if filter badges is enabled - use same pattern as copy_link.
		$filter_badges_value   = isset( $atts['filter_badges'] ) ? $atts['filter_badges'] : $filter_badges_default;
		$filter_badges_enabled = filter_var( $filter_badges_value, FILTER_VALIDATE_BOOLEAN );

		// Check if search is enabled - use same pattern as copy_link.
		$search_value   = isset( $atts['search'] ) ? $atts['search'] : $search_default;
		$search_enabled = filter_var( $search_value, FILTER_VALIDATE_BOOLEAN );

		// Check if show meta is enabled - use same pattern as copy_link.
		$show_meta_value   = isset( $atts['show_meta'] ) ? $atts['show_meta'] : $show_meta_default;
		$show_meta_enabled = filter_var( $show_meta_value, FILTER_VALIDATE_BOOLEAN );

		// Parse changelog with options.
		$template    = $atts['template'];
		$parsed_data = $this->parse_changelog_data( $changelog_content, (int) $atts['limit'], $heading_level, $order, $copy_link_enabled, $template );
		$formatted   = $parsed_data['html'];

		// Build CSS classes.
		$classes = array( 'caas-changelog' );
		// Use badge style and position from shortcode attributes (already validated by shortcode_atts).
		$classes[] = 'badge-style-' . sanitize_html_class( $atts['badge_style'] );
		$classes[] = 'badge-position-' . sanitize_html_class( $atts['badge_position'] );
		$classes[] = 'caas-template-' . sanitize_html_class( $template );

		// Add feature classes.
		if ( $filter_badges_enabled ) {
			$classes[] = 'caas-filterable';
		}
		if ( $search_enabled ) {
			$classes[] = 'caas-searchable';
		}

		/**
		 * Filter the CSS classes for the changelog container.
		 *
		 * @param array  $classes Array of CSS classes.
		 * @param array  $atts    Shortcode attributes.
		 * @param object $changelog Changelog post object.
		 */
		$classes = apply_filters( 'caas_container_classes', $classes, $atts, $changelog );

		// Build inline style for timeline CSS custom properties.
		$style_attr = '';
		if ( 'timeline' === $template ) {
			$style_attr = sprintf(
				' style="--caas-timeline-line-color:%s;--caas-timeline-version-bg:%s;--caas-timeline-version-text:%s"',
				esc_attr( $settings['timeline_line_color'] ?? '#d1d5db' ),
				esc_attr( $settings['timeline_version_bg_color'] ?? '#374151' ),
				esc_attr( $settings['timeline_version_text_color'] ?? '#ffffff' )
			);
		}

		// Build output.
		$output = sprintf(
			'<div id="caas-%d" class="%s" data-changelog-id="%d"%s%s>',
			$changelog->ID,
			esc_attr( implode( ' ', $classes ) ),
			$changelog->ID,
			$this->get_data_attributes( $atts ),
			$style_attr
		);

		// Add search box if enabled.
		if ( $search_enabled ) {
			$search_id = 'caas-search-' . $changelog->ID;
			$output   .= '<div class="caas-search">';
			$output   .= '<label for="' . esc_attr( $search_id ) . '" class="screen-reader-text">' . esc_html__( 'Search changelog', 'changelog-service' ) . '</label>';
			$output   .= '<input type="search" id="' . esc_attr( $search_id ) . '" class="caas-search-input" placeholder="' . esc_attr__( 'Search changelog...', 'changelog-service' ) . '" aria-label="' . esc_attr__( 'Search changelog', 'changelog-service' ) . '" />';
			$output   .= '</div>';
		}

		// Add filter badges if enabled.
		if ( $filter_badges_enabled ) {
			$output .= $this->render_badge_filters( $parsed_data['badge_types'] );
		}

		// Add signup link if enabled.
		$signup_value   = isset( $atts['signup'] ) ? $atts['signup'] : $signup_default;
		$signup_enabled = false;
		$signup_url     = '';

		// Handle signup parameter - can be 'true', 'false', or a custom URL.
		if ( 'true' === $signup_value || true === $signup_value || '1' === $signup_value ) {
			// Auto-generate URL from plugin_slug if available.
			if ( ! empty( $atts['plugin_slug'] ) ) {
				$signup_url     = 'https://changelogwp.com/plugin/' . sanitize_title( $atts['plugin_slug'] ) . '/?watchlist';
				$signup_enabled = true;
			}
		} elseif ( 'false' !== $signup_value && false !== $signup_value && '0' !== $signup_value && ! empty( $signup_value ) ) {
			// Custom URL provided - append ?watchlist if not already present.
			$signup_url = esc_url_raw( $signup_value );
			if ( strpos( $signup_url, '?' ) === false ) {
				$signup_url .= '?watchlist';
			} elseif ( strpos( $signup_url, 'watchlist' ) === false ) {
				$signup_url .= '&watchlist';
			}
			$signup_enabled = true;
		}

		if ( $signup_enabled && ! empty( $signup_url ) ) {
			$output .= '<div class="caas-signup-link">';
			$output .= sprintf(
				'<a href="%s" class="button caas-signup-button" target="_blank" rel="noopener noreferrer">%s</a>',
				esc_url( $signup_url ),
				esc_html__( 'Get notified of updates', 'changelog-service' )
			);
			$output .= '</div>';
		}

		// Add meta info if requested.
		if ( $show_meta_enabled ) {
			$output .= $this->render_meta( $changelog );
		}

		// Wrap versions in a container for easier styling and reordering.
		$output .= '<div class="caas-versions">';
		$output .= $formatted;
		$output .= '</div>';

		$output .= '</div>';

		$output = apply_filters( 'caas_shortcode_output', $output, $atts, $changelog );

		return $output;
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
	 * Normalize changelog format to readme.txt style.
	 *
	 * Detects if content is HTML format (with h4, ul, li tags) and converts
	 * it to readme.txt format. If already in readme.txt format, returns as-is.
	 *
	 * @param string $content Changelog content (HTML or readme.txt format).
	 * @return string Changelog in readme.txt format.
	 */
	private function normalize_changelog_format( string $content ): string {
		// Check if content is already in readme.txt format (contains = version = headers).
		if ( preg_match( '/^=\s*.+?\s*=$/m', $content ) ) {
			return $content;
		}

		// Check if content contains HTML tags indicating HTML format.
		if ( ! preg_match( '/<h[34]>|<ul>|<li>|<p>/i', $content ) ) {
			// No readme.txt format and no HTML tags - return as-is.
			return $content;
		}

		// Convert HTML format to readme.txt format.
		return $this->convert_html_changelog_to_readme( $content );
	}

	/**
	 * Convert HTML changelog to readme.txt format.
	 *
	 * Handles changelogs in HTML format with h3/h4 version headers and ul/li items.
	 *
	 * @param string $html HTML changelog content.
	 * @return string Changelog in readme.txt format.
	 */
	private function convert_html_changelog_to_readme( string $html ): string {
		$output = '';

		// Use DOMDocument to parse HTML.
		$dom = new DOMDocument();

		// Suppress warnings for malformed HTML and use UTF-8.
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();

		$xpath = new DOMXPath( $dom );

		// Find all version headers (h4 tags typically, h3 as fallback).
		$headers = $xpath->query( '//h4' );

		if ( ! $headers || 0 === $headers->length ) {
			$headers = $xpath->query( '//h3' );
		}

		if ( $headers && $headers->length > 0 ) {
			foreach ( $headers as $header ) {
				// Get version text.
				$version = trim( $header->textContent );
				$output .= "= {$version} =\n";

				// Find the following ul element.
				$sibling = $header->nextSibling;

				while ( $sibling ) {
					// Skip text nodes and other non-element nodes.
					if ( XML_ELEMENT_NODE !== $sibling->nodeType ) {
						$sibling = $sibling->nextSibling;
						continue;
					}

					// If we hit another header, stop.
					if ( in_array( strtolower( $sibling->nodeName ), array( 'h3', 'h4' ), true ) ) {
						break;
					}

					// Process ul lists.
					if ( 'ul' === strtolower( $sibling->nodeName ) ) {
						$items = $xpath->query( './/li', $sibling );
						foreach ( $items as $item ) {
							$text    = trim( $item->textContent );
							$output .= "* {$text}\n";
						}
					}

					$sibling = $sibling->nextSibling;
				}

				$output .= "\n";
			}
		} else {
			// Fallback: strip tags and return as-is if no structure found.
			$output = wp_strip_all_tags( $html );
		}

		return trim( $output );
	}

	/**
	 * Get changelog post.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return WP_Post|null
	 */
	private function get_changelog( array $atts ): ?WP_Post {
		// Try WordPress.org plugin slug first if provided.
		if ( ! empty( $atts['plugin_slug'] ) ) {
			$wporg_changelog = Changelog_Service_WPOrg_API::get_changelog( $atts['plugin_slug'] );
			if ( $wporg_changelog ) {
				return $wporg_changelog;
			}
		}

		// Try EDD product if edd_id is provided and EDD SL is active.
		if ( ! empty( $atts['edd_id'] ) && $this->is_edd_sl_active() ) {
			$edd_product = get_post( (int) $atts['edd_id'] );
			if ( $edd_product && 'download' === $edd_product->post_type ) {
				// Get changelog from EDD Software Licensing meta.
				// EDD SL stores this meta with backslashes and strips them on read (see EDD_SL_Download::get_changelog()).
				$changelog_content = stripslashes( get_post_meta( $edd_product->ID, '_edd_sl_changelog', true ) );
				if ( ! empty( $changelog_content ) ) {
					// Convert HTML format to readme.txt format if needed.
					$changelog_content = $this->normalize_changelog_format( $changelog_content );

					// Clone the post object and modify it to work as a changelog.
					$changelog_post               = clone $edd_product;
					$changelog_post->post_content = $changelog_content;
					$changelog_post->post_type    = self::POST_TYPE;
					return $changelog_post;
				}
			}
		}

		// Try by ID first.
		if ( ! empty( $atts['id'] ) ) {
			$post = get_post( (int) $atts['id'] );
			if ( $post && self::POST_TYPE === $post->post_type ) {
				return $post;
			}
		}

		// Try by slug.
		if ( ! empty( $atts['slug'] ) ) {
			$posts = get_posts(
				array(
					'name'        => sanitize_title( $atts['slug'] ),
					'post_type'   => self::POST_TYPE,
					'post_status' => 'publish',
					'numberposts' => 1,
				)
			);
			if ( ! empty( $posts ) ) {
				return $posts[0];
			}
		}

		// If on a single changelog, use current post.
		if ( is_singular( self::POST_TYPE ) ) {
			return get_post();
		}

		return null;
	}

	/**
	 * Render meta information.
	 *
	 * @param WP_Post $changelog Changelog post object.
	 * @return string
	 */
	private function render_meta( WP_Post $changelog ): string {
		$post_id = $changelog->ID;

		// Check if this is a WordPress.org plugin (has wporg_meta property).
		if ( isset( $changelog->wporg_meta ) && is_array( $changelog->wporg_meta ) ) {
			$version      = $changelog->wporg_meta['version'] ?? '';
			$tested       = $changelog->wporg_meta['tested'] ?? '';
			$requires     = $changelog->wporg_meta['requires'] ?? '';
			$requires_php = $changelog->wporg_meta['requires_php'] ?? '';
		} else {
			// Check if this is an EDD product (has _edd_sl_changelog meta).
			$is_edd_product = false;
			if ( $this->is_edd_sl_active() ) {
				$edd_changelog = get_post_meta( $post_id, '_edd_sl_changelog', true );
				if ( ! empty( $edd_changelog ) ) {
					$is_edd_product = true;
				}
			}

			$version      = get_post_meta( $post_id, 'version', true );
			$tested       = get_post_meta( $post_id, 'tested', true );
			$requires     = get_post_meta( $post_id, 'requires', true );
			$requires_php = get_post_meta( $post_id, 'requires_php', true );

			// For EDD products, get required versions from EDD Software Licensing.
			if ( $is_edd_product ) {
				$edd_required_versions = get_post_meta( $post_id, '_edd_sl_required_versions', true );
				if ( is_array( $edd_required_versions ) ) {
					// Use EDD required versions if available, otherwise keep existing values.
					if ( ! empty( $edd_required_versions['wp'] ) && empty( $requires ) ) {
						$requires = $edd_required_versions['wp'];
					}
					if ( ! empty( $edd_required_versions['php'] ) && empty( $requires_php ) ) {
						$requires_php = $edd_required_versions['php'];
					}
				}
			}
		}

		// Only show if at least one field has a value.
		if ( ! $version && ! $tested && ! $requires && ! $requires_php ) {
			return '';
		}

		$output = '<div class="caas-meta">';

		if ( $version ) {
			$output .= sprintf(
				'<div class="caas-meta-item"><span class="caas-meta-label">%s</span> <span class="caas-meta-value">%s</span></div>',
				esc_html__( 'Version:', 'changelog-service' ),
				esc_html( $version )
			);
		}

		if ( $tested ) {
			$output .= sprintf(
				'<div class="caas-meta-item"><span class="caas-meta-label">%s</span> <span class="caas-meta-value">%s</span></div>',
				esc_html__( 'Tested Up To:', 'changelog-service' ),
				esc_html( $tested )
			);
		}

		if ( $requires ) {
			$output .= sprintf(
				'<div class="caas-meta-item"><span class="caas-meta-label">%s</span> <span class="caas-meta-value">%s</span></div>',
				esc_html__( 'Requires WP:', 'changelog-service' ),
				esc_html( $requires )
			);
		}

		if ( $requires_php ) {
			$output .= sprintf(
				'<div class="caas-meta-item"><span class="caas-meta-label">%s</span> <span class="caas-meta-value">%s</span></div>',
				esc_html__( 'Requires PHP:', 'changelog-service' ),
				esc_html( $requires_php )
			);
		}

		$output .= '</div>';

		return $output;
	}

	/**
	 * Parse changelog content into structured data with HTML.
	 *
	 * @param string $content      Changelog content in readme.txt format.
	 * @param int    $limit        Maximum number of versions to show.
	 * @param int    $heading_level Heading level for version numbers (1-6).
	 * @param string $order        Order: 'newest' or 'oldest'.
	 * @param bool   $copy_link    Whether to show copy link button.
	 * @param string $template     Template to use: 'default' or 'timeline'.
	 * @return array Array with 'html' and 'badge_types' keys.
	 */
	private function parse_changelog_data( string $content, int $limit = 0, int $heading_level = 4, string $order = 'newest', bool $copy_link = false, string $template = 'default' ): array {
		$versions        = array();
		$badge_types     = array();
		$lines           = explode( "\n", $content );
		$current_version = '';
		$items           = array();
		$notes           = array();
		$has_items       = false;

		foreach ( $lines as $line ) {
			$line = trim( $line );

			// Skip empty lines.
			if ( empty( $line ) ) {
				continue;
			}

			// Check for version header = 1.0.0 = or = 1.0.0 - 2024-01-15 =.
			if ( preg_match( '/^=\s*(.+?)\s*=$/', $line, $matches ) ) {
				// Save previous version.
				if ( ! empty( $current_version ) && ( ! empty( $items ) || ! empty( $notes ) ) ) {
					$versions[] = array(
						'version' => $current_version,
						'items'   => $items,
						'notes'   => $notes,
					);
				}

				$current_version = $matches[1];
				$items           = array();
				$notes           = array();
				$has_items       = false;
				continue;
			}

			// Check for list item (starts with * or -).
			if ( preg_match( '/^[\*\-]\s*(.+)$/', $line, $matches ) ) {
				$item      = $matches[1];
				$items[]   = $item;
				$has_items = true;

				// Extract badge type from item.
				$badge_type = $this->get_badge_type_from_item( $item );
				if ( $badge_type && ! in_array( $badge_type, $badge_types, true ) ) {
					$badge_types[] = $badge_type;
				}
				continue;
			}

			// Non-list text after a version header and before list items = release notes.
			if ( ! empty( $current_version ) && ! $has_items ) {
				$notes[] = $line;
			}
		}

		// Save last version.
		if ( ! empty( $current_version ) && ( ! empty( $items ) || ! empty( $notes ) ) ) {
			$versions[] = array(
				'version' => $current_version,
				'items'   => $items,
				'notes'   => $notes,
			);
		}

		// Apply ordering.
		if ( 'oldest' === $order ) {
			$versions = array_reverse( $versions );
		}

		// Apply limit.
		if ( $limit > 0 ) {
			$versions = array_slice( $versions, 0, $limit );
		}

		// Render HTML.
		$html = '';
		foreach ( $versions as $version_data ) {
			$notes = isset( $version_data['notes'] ) ? $version_data['notes'] : array();
			if ( 'timeline' === $template ) {
				$html .= $this->render_version_section_timeline( $version_data['version'], $version_data['items'], $heading_level, $copy_link, $notes );
			} else {
				$html .= $this->render_version_section( $version_data['version'], $version_data['items'], $heading_level, $copy_link, $notes );
			}
		}

		return array(
			'html'        => $html,
			'badge_types' => $badge_types,
		);
	}

	/**
	 * Render a version section.
	 *
	 * @param string $version       Version string.
	 * @param array  $items         List of changelog items.
	 * @param int    $heading_level Heading level (1-6).
	 * @param bool   $copy_link     Whether to show copy link button.
	 * @param array  $notes         Release notes text lines.
	 * @return string
	 */
	private function render_version_section( string $version, array $items, int $heading_level = 4, bool $copy_link = false, array $notes = array() ): string {
		$slug = sanitize_title( preg_replace( '/\s-\s\d+\/\d+\/\d+/', '', $version ) );

		// Validate and sanitize heading level.
		$heading_level = absint( $heading_level );
		if ( $heading_level < 1 || $heading_level > 6 ) {
			$heading_level = 4;
		}
		$heading_tag = 'h' . $heading_level;
		$heading_tag = tag_escape( $heading_tag );

		$html = '<div class="caas-version-section" data-version-slug="' . esc_attr( $slug ) . '">';

		// Get the current URL for the version link using WordPress functions.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- HTTP_HOST and REQUEST_URI are validated and sanitized below.
		$http_host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- REQUEST_URI is validated and sanitized below.
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$current_url = ( is_ssl() ? 'https://' : 'http://' ) . $http_host . $request_uri;
		$version_url = esc_url_raw( $current_url . '#' . $slug );

		// Version header with optional link icon.
		if ( $copy_link ) {
			$link_icon_svg = '<svg width="16" height="16" viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M12.707 7.091a2.685 2.685 0 0 0-3.798-3.798L7.763 4.44a.753.753 0 1 1-1.066-1.066l1.147-1.146a4.192 4.192 0 0 1 5.928 5.928l-1.146 1.147a.753.753 0 0 1-1.066-1.066l1.147-1.146Z"></path><path d="M5.26 10.76a.753.753 0 0 1 0-1.066l4.434-4.435a.753.753 0 0 1 1.065 1.065L6.325 10.76a.753.753 0 0 1-1.066 0Z"></path><path d="M3.293 12.707a2.685 2.685 0 0 1 0-3.798L4.44 7.763a.753.753 0 1 0-1.066-1.066L2.228 7.844a4.192 4.192 0 0 0 5.928 5.928l1.147-1.146a.753.753 0 0 0-1.066-1.066l-1.146 1.147a2.685 2.685 0 0 1-3.798 0Z"></path></svg>';

			$header_content = sprintf(
				'<%s class="anchoring" id="%s">%s <button class="caas-copy-link" data-url="%s" title="%s" aria-label="%s"><span class="caas-link-icon">%s</span></button></%s>',
				$heading_tag,
				esc_attr( $slug ),
				esc_html( $version ),
				esc_attr( $version_url ),
				esc_attr__( 'Copy link to this version', 'changelog-service' ),
				esc_attr__( 'Copy link to this version', 'changelog-service' ),
				$link_icon_svg,
				$heading_tag
			);
		} else {
			$header_content = sprintf(
				'<%s class="anchoring" id="%s">%s</%s>',
				$heading_tag,
				esc_attr( $slug ),
				esc_html( $version ),
				$heading_tag
			);
		}

		$html .= $header_content;

		// Release notes.
		if ( ! empty( $notes ) ) {
			$html .= '<div class="caas-release-notes">';
			foreach ( $notes as $note ) {
				$html .= '<p>' . $this->parse_inline_markdown( $note ) . '</p>';
			}
			$html .= '</div>';
		}

		if ( ! empty( $items ) ) {
			$html .= '<ul>';

			foreach ( $items as $item ) {
				$html .= $this->render_item( $item );
			}

			$html .= '</ul>';
		}

		$html .= '</div>';

		/**
		 * Filter the version section HTML.
		 *
		 * @param string $html    Version section HTML.
		 * @param string $version Version string.
		 * @param array  $items   List of changelog items.
		 */
		return apply_filters( 'caas_version_section_html', $html, $version, $items );
	}

	/**
	 * Parse a version string into version number and secondary info.
	 *
	 * Splits strings like "1.3 - Feb 12, 2026" or "1.3: 2026-02-12" into
	 * the version number and any secondary information (typically a date).
	 *
	 * @param string $version_text Full version text from the changelog header.
	 * @return array Array with 'version' and 'secondary' keys.
	 */
	public function parse_version_string( string $version_text ): array {
		// Match the version number first (e.g., 1.3.0, 1.0.0-beta1, 2.0).
		// Then strip any optional separator (dash, en-dash, em-dash, colon) from the remainder.
		if ( preg_match( '/^(\d+\.\d+(?:\.\d+)*(?:-[a-zA-Z0-9.]+)?)\s*(.*)$/', $version_text, $matches ) ) {
			$version   = trim( $matches[1] );
			$secondary = trim( $matches[2] );

			// Strip leading separator if present.
			$secondary = preg_replace( '/^[-–—:|]\s*/', '', $secondary );
			$secondary = trim( $secondary );

			return array(
				'version'   => $version,
				'secondary' => $secondary,
			);
		}

		// Fallback — version only.
		return array(
			'version'   => trim( $version_text ),
			'secondary' => '',
		);
	}

	/**
	 * Render a version section using the timeline template.
	 *
	 * @param string $version       Version string.
	 * @param array  $items         List of changelog items.
	 * @param int    $heading_level Heading level (1-6).
	 * @param bool   $copy_link     Whether to show copy link button.
	 * @param array  $notes         Release notes text lines.
	 * @return string
	 */
	private function render_version_section_timeline( string $version, array $items, int $heading_level = 4, bool $copy_link = false, array $notes = array() ): string {
		$slug = sanitize_title( preg_replace( '/\s-\s\d+\/\d+\/\d+/', '', $version ) );

		// Parse version string into version number and secondary info.
		$parsed = $this->parse_version_string( $version );

		// Validate and sanitize heading level.
		$heading_level = absint( $heading_level );
		if ( $heading_level < 1 || $heading_level > 6 ) {
			$heading_level = 4;
		}
		$heading_tag = 'h' . $heading_level;
		$heading_tag = tag_escape( $heading_tag );

		$html = '<div class="caas-version-section" data-version-slug="' . esc_attr( $slug ) . '">';

		// Timeline marker row.
		$html .= '<div class="caas-timeline-marker">';
		$html .= '<span class="caas-timeline-version-badge" id="' . esc_attr( $slug ) . '">' . esc_html( $parsed['version'] ) . '</span>';

		if ( ! empty( $parsed['secondary'] ) ) {
			$html .= '<span class="caas-timeline-secondary">' . esc_html( $parsed['secondary'] ) . '</span>';
		}

		// Copy link button in the marker row.
		if ( $copy_link ) {
			// Get the current URL for the version link.
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- HTTP_HOST and REQUEST_URI are validated and sanitized below.
			$http_host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- REQUEST_URI is validated and sanitized below.
			$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
			$current_url = ( is_ssl() ? 'https://' : 'http://' ) . $http_host . $request_uri;
			$version_url = esc_url_raw( $current_url . '#' . $slug );

			$link_icon_svg = '<svg width="16" height="16" viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M12.707 7.091a2.685 2.685 0 0 0-3.798-3.798L7.763 4.44a.753.753 0 1 1-1.066-1.066l1.147-1.146a4.192 4.192 0 0 1 5.928 5.928l-1.146 1.147a.753.753 0 0 1-1.066-1.066l1.147-1.146Z"></path><path d="M5.26 10.76a.753.753 0 0 1 0-1.066l4.434-4.435a.753.753 0 0 1 1.065 1.065L6.325 10.76a.753.753 0 0 1-1.066 0Z"></path><path d="M3.293 12.707a2.685 2.685 0 0 1 0-3.798L4.44 7.763a.753.753 0 1 0-1.066-1.066L2.228 7.844a4.192 4.192 0 0 0 5.928 5.928l1.147-1.146a.753.753 0 0 0-1.066-1.066l-1.146 1.147a2.685 2.685 0 0 1-3.798 0Z"></path></svg>';

			$html .= sprintf(
				'<button class="caas-copy-link" data-url="%s" title="%s" aria-label="%s"><span class="caas-link-icon">%s</span></button>',
				esc_attr( $version_url ),
				esc_attr__( 'Copy link to this version', 'changelog-service' ),
				esc_attr__( 'Copy link to this version', 'changelog-service' ),
				$link_icon_svg
			);
		}

		$html .= '</div>'; // .caas-timeline-marker

		// Screen-reader-only heading for accessibility/SEO.
		$html .= sprintf(
			'<%s class="screen-reader-text">%s</%s>',
			$heading_tag,
			esc_html( $version ),
			$heading_tag
		);

		// Timeline content with notes and items.
		$html .= '<div class="caas-timeline-content">';

		// Release notes.
		if ( ! empty( $notes ) ) {
			$html .= '<div class="caas-release-notes">';
			foreach ( $notes as $note ) {
				$html .= '<p>' . $this->parse_inline_markdown( $note ) . '</p>';
			}
			$html .= '</div>';
		}

		if ( ! empty( $items ) ) {
			$html .= '<ul>';

			foreach ( $items as $item ) {
				$html .= $this->render_item( $item );
			}

			$html .= '</ul>';
		}

		$html .= '</div>'; // .caas-timeline-content
		$html .= '</div>'; // .caas-version-section

		/** This filter is documented in class-changelog-service-shortcode.php */
		return apply_filters( 'caas_version_section_html', $html, $version, $items );
	}

	/**
	 * Convert basic inline markdown to HTML.
	 *
	 * Supports bold (**text** or __text__), italic (*text* or _text_),
	 * and links ([text](url)).
	 *
	 * @param string $text Plain text with optional inline markdown.
	 * @return string Sanitized HTML.
	 */
	private function parse_inline_markdown( string $text ): string {
		// Escape HTML first.
		$html = esc_html( $text );

		// Links: [text](url).
		$html = preg_replace(
			'/\[([^\]]+)\]\(([^)]+)\)/',
			'<a href="$2" rel="noopener noreferrer">$1</a>',
			$html
		);

		// Bold: **text** or __text__.
		$html = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html );
		$html = preg_replace( '/__(.+?)__/', '<strong>$1</strong>', $html );

		// Italic: *text* or _text_ (but not inside words for underscores).
		$html = preg_replace( '/\*(.+?)\*/', '<em>$1</em>', $html );
		$html = preg_replace( '/(?<!\w)_(.+?)_(?!\w)/', '<em>$1</em>', $html );

		// Inline code: `text`.
		$html = preg_replace( '/`(.+?)`/', '<code>$1</code>', $html );

		return wp_kses(
			$html,
			array(
				'a'      => array(
					'href' => array(),
					'rel'  => array(),
				),
				'strong' => array(),
				'em'     => array(),
				'code'   => array(),
			)
		);
	}

	/**
	 * Render a single changelog item with badge.
	 *
	 * Supports formats:
	 * - "Fix - Description" (prefix + space-dash-space, supports -, –, —)
	 * - "Fix: Description" (prefix + colon + optional space)
	 * - "Fix : Description" (prefix + space-colon-space)
	 *
	 * @param string $content Item content.
	 * @return string
	 */
	private function render_item( string $content ): string {
		$badge         = '';
		$display       = $content;
		$matched       = false;
		$badge_configs = $this->get_badge_configs();

		// Use instance-specific show_emojis if set, otherwise fall back to global settings.
		$show_emoji = $this->show_emojis_instance ?? ( $this->get_settings()['show_emojis'] ?? true );

		foreach ( $badge_configs as $config ) {
			if ( $matched ) {
				break;
			}

			foreach ( $config['prefixes'] as $prefix_variant ) {
				$prefix = preg_quote( $prefix_variant, '/' );

				// Match "Prefix - ", "Prefix: ", "Prefix:", or "Prefix : " formats.
				// Supports regular hyphen (-), en-dash (–), and em-dash (—).
				if ( preg_match( '/^' . $prefix . '(\s[-–—]\s|:\s?|\s:\s)/iu', $content, $matches ) ) {
					// Remove the prefix and separator from display.
					$display = preg_replace( '/^' . $prefix . '(\s[-–—]\s|:\s?|\s:\s)/iu', '', $content );

					// Build badge text with or without emoji.
					$badge_content = $show_emoji && ! empty( $config['emoji'] )
						? $config['emoji'] . ' ' . $config['badge_text']
						: $config['badge_text'];

					$badge = sprintf(
						'<span class="caas-badge %s" style="background-color: %s;">%s</span>',
						esc_attr( $config['class'] ),
						esc_attr( $config['color'] ),
						esc_html( $badge_content )
					);

					$matched = true;
					break;
				}
			}
		}

		return sprintf(
			'<li>%s<span class="caas-item-text">%s</span></li>',
			$badge,
			$this->parse_inline_markdown( $display )
		);
	}

	/**
	 * Get parsed changelog data as array.
	 *
	 * Useful for REST API and external use.
	 *
	 * @param string $content Changelog content.
	 * @return array
	 */
	public function get_parsed_changelog( string $content ): array {
		$lines    = explode( "\n", $content );
		$versions = array();
		$current  = null;

		foreach ( $lines as $line ) {
			$line = trim( $line );

			if ( empty( $line ) ) {
				continue;
			}

			// Version header.
			if ( preg_match( '/^=\s*(.+?)\s*=$/', $line, $matches ) ) {
				if ( null !== $current ) {
					$versions[] = $current;
				}
				$current = array(
					'version' => $matches[1],
					'items'   => array(),
				);
				continue;
			}

			// List item.
			if ( null !== $current && preg_match( '/^[\*\-]\s*(.+)$/', $line, $matches ) ) {
				$item_content  = $matches[1];
				$badge_type    = null;
				$type_found    = false;
				$badge_configs = $this->get_badge_configs();

				foreach ( $badge_configs as $type => $config ) {
					if ( $type_found ) {
						break;
					}

					foreach ( $config['prefixes'] as $prefix_variant ) {
						$prefix = preg_quote( $prefix_variant, '/' );
						// Match "Prefix - ", "Prefix: ", "Prefix:", or "Prefix : " formats.
						// Supports regular hyphen (-), en-dash (–), and em-dash (—).
						if ( preg_match( '/^' . $prefix . '(\s[-–—]\s|:\s?|\s:\s)/iu', $item_content ) ) {
							$badge_type = $type;
							$type_found = true;
							break;
						}
					}
				}

				$current['items'][] = array(
					'content' => $item_content,
					'type'    => $badge_type,
				);
			}
		}

		if ( null !== $current ) {
			$versions[] = $current;
		}

		return $versions;
	}

	/**
	 * Get data attributes for the container.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	private function get_data_attributes( array $atts ): string {
		$data_attrs = array();

		if ( isset( $atts['filter_badges'] ) ) {
			$data_attrs[] = sprintf( 'data-filter-badges="%s"', esc_attr( $atts['filter_badges'] ) );
		}
		if ( isset( $atts['search'] ) ) {
			$data_attrs[] = sprintf( 'data-search="%s"', esc_attr( $atts['search'] ) );
		}

		if ( empty( $data_attrs ) ) {
			return '';
		}

		return ' ' . implode( ' ', $data_attrs );
	}

	/**
	 * Render badge filter buttons.
	 *
	 * @param array $badge_types Array of badge type keys.
	 * @return string
	 */
	private function render_badge_filters( array $badge_types ): string {
		if ( empty( $badge_types ) ) {
			return '';
		}

		$output  = '<div class="caas-badge-filters showing-all" role="group" aria-label="' . esc_attr__( 'Filter changelog by badge type', 'changelog-service' ) . '">';
		$output .= '<button class="caas-filter-btn active" data-filter="all" style="background-color: #50575e;" aria-pressed="true" aria-label="' . esc_attr__( 'Show all changelog items', 'changelog-service' ) . '">' . esc_html__( 'All', 'changelog-service' ) . '</button>';

		$badge_configs = $this->get_badge_configs();
		foreach ( $badge_types as $badge_type ) {
			$config = $badge_configs[ $badge_type ] ?? null;
			if ( ! $config ) {
				continue;
			}

			$label   = ! empty( $config['badge_text'] ) ? $config['badge_text'] : ucfirst( $badge_type );
			$output .= sprintf(
				'<button class="caas-filter-btn" data-filter="%s" style="background-color: %s;" aria-pressed="false" aria-label="%s">%s</button>',
				esc_attr( $badge_type ),
				esc_attr( $config['color'] ),
				esc_attr__( 'Filter by', 'changelog-service' ) . ' ' . esc_attr( $label ),
				esc_html( $label )
			);
		}

		$output .= '</div>';

		/**
		 * Filter the badge filters HTML.
		 *
		 * @param string $output     Badge filters HTML.
		 * @param array  $badge_types Array of badge type keys.
		 */
		return apply_filters( 'caas_badge_filters_html', $output, $badge_types );
	}

	/**
	 * Get badge type from changelog item.
	 *
	 * @param string $item Changelog item text.
	 * @return string|null Badge type key or null.
	 */
	private function get_badge_type_from_item( string $item ): ?string {
		$badge_configs = $this->get_badge_configs();
		foreach ( $badge_configs as $type => $config ) {
			foreach ( $config['prefixes'] as $prefix ) {
				// Check if item starts with prefix followed by separator.
				// Supports regular hyphen (-), en-dash (–), and em-dash (—).
				if ( preg_match( '/^' . preg_quote( $prefix, '/' ) . '(\s[-–—]\s|\s*:\s*|\s:\s)/iu', $item ) ) {
					return $type;
				}
			}
		}

		return null;
	}
}
