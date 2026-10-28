<?php
/**
 * WordPress.org Plugin API Handler
 *
 * Fetches and caches changelog data from WordPress.org Plugin API.
 *
 * @package Changelog_Service
 */

declare(strict_types=1);

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Changelog_Service_WPOrg_API
 *
 * Handles fetching plugin information from WordPress.org API with caching.
 *
 * @since 1.1.0
 */
class Changelog_Service_WPOrg_API {

	/**
	 * WordPress.org API base URL.
	 *
	 * @var string
	 */
	const API_URL = 'https://api.wordpress.org/plugins/info/1.2/';

	/**
	 * Transient expiration time (24 hours in seconds).
	 *
	 * @var int
	 */
	const CACHE_EXPIRATION = DAY_IN_SECONDS;

	/**
	 * Transient key prefix.
	 *
	 * @var string
	 */
	const TRANSIENT_PREFIX = 'caas_wporg_';

	/**
	 * Get changelog as a pseudo WP_Post object.
	 *
	 * @param string $slug WordPress.org plugin slug.
	 * @return WP_Post|null Post object with changelog content or null on failure.
	 */
	public static function get_changelog( string $slug ): ?WP_Post {
		$slug = sanitize_title( $slug );

		if ( empty( $slug ) ) {
			return null;
		}

		$plugin_info = self::get_plugin_info( $slug );

		if ( ! $plugin_info ) {
			return null;
		}

		// Get the changelog from sections.
		$changelog_html = $plugin_info['sections']['changelog'] ?? '';

		if ( empty( $changelog_html ) ) {
			return null;
		}

		// Convert HTML changelog to readme.txt format for compatibility with existing parser.
		$changelog_content = self::convert_html_to_readme_format( $changelog_html );

		// Create a pseudo WP_Post object.
		$post                 = new stdClass();
		$post->ID             = self::generate_pseudo_id( $slug );
		$post->post_type      = 'changelog-service';
		$post->post_title     = $plugin_info['name'] ?? $slug;
		$post->post_content   = $changelog_content;
		$post->post_status    = 'publish';
		$post->post_name      = $slug;
		$post->comment_status = 'closed';
		$post->ping_status    = 'closed';

		// Store meta data in a way that can be retrieved.
		$post->wporg_meta = array(
			'version'      => $plugin_info['version'] ?? '',
			'tested'       => $plugin_info['tested'] ?? '',
			'requires'     => $plugin_info['requires'] ?? '',
			'requires_php' => $plugin_info['requires_php'] ?? '',
			'homepage'     => $plugin_info['homepage'] ?? '',
		);

		return new WP_Post( $post );
	}

	/**
	 * Get plugin information from WordPress.org API with caching.
	 *
	 * @param string $slug WordPress.org plugin slug.
	 * @return array|null Plugin info array or null on failure.
	 */
	public static function get_plugin_info( string $slug ): ?array {
		$slug          = sanitize_title( $slug );
		$transient_key = self::get_transient_key( $slug );

		// Try to get cached data.
		$cached = get_transient( $transient_key );

		if ( false !== $cached ) {
			return $cached;
		}

		// Fetch fresh data from API.
		$plugin_info = self::fetch_from_api( $slug );

		if ( $plugin_info ) {
			// Cache the result.
			set_transient( $transient_key, $plugin_info, self::CACHE_EXPIRATION );
		}

		return $plugin_info;
	}

	/**
	 * Fetch plugin information from WordPress.org API.
	 *
	 * @param string $slug WordPress.org plugin slug.
	 * @return array|null Plugin info array or null on failure.
	 */
	private static function fetch_from_api( string $slug ): ?array {
		$url = add_query_arg(
			array(
				'action'  => 'plugin_information',
				'request' => array(
					'slug'   => $slug,
					'fields' => array(
						'sections'     => true,
						'tested'       => true,
						'requires'     => true,
						'requires_php' => true,
						'homepage'     => true,
					),
				),
			),
			self::API_URL
		);

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$body = wp_remote_retrieve_body( $response );

		if ( empty( $body ) ) {
			return null;
		}

		$data = json_decode( $body, true );

		// Check for API error response.
		if ( ! is_array( $data ) || isset( $data['error'] ) ) {
			return null;
		}

		return $data;
	}

	/**
	 * Convert HTML changelog to readme.txt format.
	 *
	 * WordPress.org API returns HTML formatted changelog. This converts it
	 * to the readme.txt format that the existing parser expects.
	 *
	 * @param string $html HTML changelog content.
	 * @return string Changelog in readme.txt format.
	 */
	private static function convert_html_to_readme_format( string $html ): string {
		$output = '';

		// Use DOMDocument to parse HTML.
		$dom = new DOMDocument();

		// Suppress warnings for malformed HTML and use UTF-8.
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();

		$xpath = new DOMXPath( $dom );

		// Find all version headers (h4 tags typically).
		$headers = $xpath->query( '//h4' );

		if ( ! $headers || 0 === $headers->length ) {
			// Try h3 as fallback.
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
	 * Generate a pseudo post ID from slug.
	 *
	 * Uses a negative hash to avoid conflicts with real post IDs.
	 *
	 * @param string $slug Plugin slug.
	 * @return int Negative pseudo ID.
	 */
	private static function generate_pseudo_id( string $slug ): int {
		// Use CRC32 to generate a consistent numeric hash, make it negative.
		return -abs( crc32( 'wporg_' . $slug ) );
	}

	/**
	 * Get the transient key for a plugin slug.
	 *
	 * @param string $slug Plugin slug.
	 * @return string Transient key.
	 */
	private static function get_transient_key( string $slug ): string {
		// WordPress transient keys have a 172 character limit.
		// Using md5 hash to ensure consistent length for long slugs.
		return self::TRANSIENT_PREFIX . md5( $slug );
	}

	/**
	 * Clear cached data for a specific plugin.
	 *
	 * @param string $slug Plugin slug.
	 * @return bool True if deleted, false otherwise.
	 */
	public static function clear_cache( string $slug ): bool {
		$transient_key = self::get_transient_key( sanitize_title( $slug ) );
		return delete_transient( $transient_key );
	}
}
