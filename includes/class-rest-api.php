<?php
/**
 * Changelog REST API
 *
 * Provides JSON endpoints for changelog data.
 *
 * @package Changelog_Service
 */

declare(strict_types=1);

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Changelog_Service_REST_API
 *
 * Handles REST API endpoint registration and responses.
 *
 * @since 1.0.0
 */
class Changelog_Service_REST_API {

	/**
	 * API namespace.
	 *
	 * @var string
	 */
	const NAMESPACE = 'changelogwp/v1';

	/**
	 * Post type slug.
	 *
	 * @var string
	 */
	const POST_TYPE = 'changelog-service';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes(): void {
		// Get single changelog by slug.
		register_rest_route(
			self::NAMESPACE,
			'/changelog/(?P<slug>[a-z0-9\-]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_changelog' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'slug' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_title',
					),
				),
			)
		);

		// Get all changelogs.
		register_rest_route(
			self::NAMESPACE,
			'/changelogs',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_changelogs' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'per_page' => array(
						'required'          => false,
						'type'              => 'integer',
						'default'           => 10,
						'sanitize_callback' => 'absint',
					),
					'page'     => array(
						'required'          => false,
						'type'              => 'integer',
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Get single changelog.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_changelog( WP_REST_Request $request ) {
		$slug = $request->get_param( 'slug' );

		$posts = get_posts(
			array(
				'name'        => $slug,
				'post_type'   => self::POST_TYPE,
				'post_status' => 'publish',
				'numberposts' => 1,
			)
		);

		if ( empty( $posts ) ) {
			return new WP_Error(
				'not_found',
				__( 'Changelog not found.', 'changelog-service' ),
				array( 'status' => 404 )
			);
		}

		$post = $posts[0];

		return new WP_REST_Response(
			self::format_changelog( $post ),
			200
		);
	}

	/**
	 * Get all changelogs.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_changelogs( WP_REST_Request $request ): WP_REST_Response {
		$per_page = $request->get_param( 'per_page' );
		$page     = $request->get_param( 'page' );

		$args = array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'modified',
			'order'          => 'DESC',
		);

		$query = new WP_Query( $args );

		$changelogs = array();
		foreach ( $query->posts as $post ) {
			$changelogs[] = self::format_changelog( $post, false );
		}

		$response = new WP_REST_Response(
			array(
				'changelogs'  => $changelogs,
				'total'       => (int) $query->found_posts,
				'total_pages' => (int) $query->max_num_pages,
				'page'        => $page,
				'per_page'    => $per_page,
			),
			200
		);

		// Add pagination headers.
		$response->header( 'X-WP-Total', $query->found_posts );
		$response->header( 'X-WP-TotalPages', $query->max_num_pages );

		return $response;
	}

	/**
	 * Format changelog for API response.
	 *
	 * @param WP_Post $post         Post object.
	 * @param bool    $include_full Include full changelog content.
	 * @return array
	 */
	public static function format_changelog( WP_Post $post, bool $include_full = true ): array {
		$type = get_post_meta( $post->ID, 'type', true ) ?: 'plugin';

		$data = array(
			'id'           => $post->ID,
			'slug'         => $post->post_name,
			'name'         => $post->post_title,
			'type'         => $type,
			'version'      => get_post_meta( $post->ID, 'version', true ) ?: '',
			'tested'       => get_post_meta( $post->ID, 'tested', true ) ?: '',
			'requires'     => get_post_meta( $post->ID, 'requires', true ) ?: '',
			'requires_php' => get_post_meta( $post->ID, 'requires_php', true ) ?: '',
			'homepage'     => get_post_meta( $post->ID, 'homepage', true ) ?: '',
			'last_updated' => get_post_modified_time( 'c', true, $post ),
			'link'         => get_permalink( $post ),
		);

		if ( $include_full ) {
			$data['changelog_raw'] = $post->post_content;

			// Parse changelog into structured format.
			// Create instance only when needed (REST API call).
			$shortcode         = new Changelog_Service_Shortcode();
			$data['changelog'] = $shortcode->get_parsed_changelog( $post->post_content );
		}

		/**
		 * Filter the formatted changelog data.
		 *
		 * @param array   $data Formatted changelog data.
		 * @param WP_Post $post Post object.
		 */
		return apply_filters( 'caas_rest_changelog_data', $data, $post );
	}
}
