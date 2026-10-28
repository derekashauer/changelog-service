<?php
/**
 * Changelog Abilities
 *
 * Registers WordPress Abilities (WordPress 6.9+) so AI agents can read and update changelogs.
 * They are offered over /wp-json/wp-abilities/v1/ and, with the MCP Adapter plugin, as MCP tools.
 *
 * @package Changelog_Service
 */

declare(strict_types=1);

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Changelog_Service_Abilities
 *
 * Registers the changelog abilities and adds them to the MCP Adapter's default server.
 *
 * @since 1.4.0
 */
class Changelog_Service_Abilities {

	/**
	 * Ability category and namespace.
	 *
	 * @var string
	 */
	const CATEGORY = 'changelog-service';

	/**
	 * Post type slug.
	 *
	 * @var string
	 */
	const POST_TYPE = 'changelog-service';

	/**
	 * Meta fields update-changelog can change.
	 *
	 * @var array
	 */
	const META_FIELDS = array( 'type', 'version', 'tested', 'requires', 'requires_php', 'homepage' );

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
		add_filter( 'mcp_adapter_default_server_config', array( $this, 'add_to_mcp_server' ) );
	}

	/**
	 * Register the ability category.
	 */
	public function register_category(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => __( 'Changelogs', 'changelog-service' ),
				'description' => __( 'Read and update the changelogs published on this site.', 'changelog-service' ),
			)
		);
	}

	/**
	 * Register the abilities.
	 */
	public function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		foreach ( $this->definitions() as $name => $definition ) {
			wp_register_ability( self::CATEGORY . '/' . $name, array( 'category' => self::CATEGORY ) + $definition );
		}
	}

	/**
	 * List our abilities as tools on the MCP Adapter's default server.
	 *
	 * @param mixed $config The default server's settings.
	 * @return mixed
	 */
	public function add_to_mcp_server( $config ) {
		if ( ! is_array( $config ) || ! isset( $config['tools'] ) || ! is_array( $config['tools'] ) ) {
			return $config;
		}

		foreach ( array_keys( $this->definitions() ) as $name ) {
			$name = self::CATEGORY . '/' . $name;
			if ( ! in_array( $name, $config['tools'], true ) ) {
				$config['tools'][] = $name;
			}
		}

		return $config;
	}

	/**
	 * Ability definitions, keyed by name without the namespace.
	 *
	 * @return array
	 */
	private function definitions(): array {
		$read_meta  = array(
			'public'      => true,
			'annotations' => array(
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			),
		);
		$write_meta = array(
			'public'      => true,
			'annotations' => array(
				'readonly'    => false,
				'destructive' => false,
				'idempotent'  => false,
			),
		);
		$changelog  = array(
			'id'   => array(
				'type'        => 'integer',
				'description' => __( 'Changelog ID. Use this or slug.', 'changelog-service' ),
			),
			'slug' => array(
				'type'        => 'string',
				'description' => __( 'Changelog slug. Use this or id.', 'changelog-service' ),
			),
		);
		$output     = array( 'type' => 'object' );

		return array(
			'list-changelogs'  => array(
				'label'               => __( 'List changelogs', 'changelog-service' ),
				'description'         => __( 'List the changelogs on this site, newest changed first, including drafts. Each has its id, slug, name, type (plugin, theme or host), current version and status. Use get-changelog for the full text.', 'changelog-service' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'default'              => array(),
					'additionalProperties' => false,
					'properties'           => array(
						'search'   => array(
							'type'        => 'string',
							'description' => __( 'Only changelogs whose name matches this text.', 'changelog-service' ),
						),
						'type'     => array(
							'type' => 'string',
							'enum' => array( 'plugin', 'theme', 'host' ),
						),
						'per_page' => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 100,
							'default' => 20,
						),
						'page'     => array(
							'type'    => 'integer',
							'minimum' => 1,
							'default' => 1,
						),
					),
				),
				'output_schema'       => $output,
				'execute_callback'    => array( $this, 'list_changelogs' ),
				'permission_callback' => array( $this, 'can_list' ),
				'meta'                => $read_meta,
			),
			'get-changelog'    => array(
				'label'               => __( 'Get a changelog', 'changelog-service' ),
				'description'         => __( 'Get one changelog by id or slug: its details, the raw text in readme.txt format (changelog_raw), each version parsed into items, and the badge words this site recognizes (badges). An item gets a badge when it starts with one of those words followed by " - " or ": ", like "Fixed - Login bug".', 'changelog-service' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'default'              => array(),
					'additionalProperties' => false,
					'properties'           => $changelog,
				),
				'output_schema'       => $output,
				'execute_callback'    => array( $this, 'get_changelog' ),
				'permission_callback' => array( $this, 'can_edit' ),
				'meta'                => $read_meta,
			),
			'add-release'      => array(
				'label'               => __( 'Add a release', 'changelog-service' ),
				'description'         => __( 'Add a new version to the top of a changelog. Each item is one line, starting with a badge word from get-changelog, like "Added - Export to CSV" or "Fixed: Crash on save". Also sets the changelog\'s current version to this one unless set_version is false. Fails if the version is already in the changelog; use update-changelog to change an existing release. dry_run shows the new text without saving.', 'changelog-service' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'default'              => array(),
					'additionalProperties' => false,
					'required'             => array( 'version', 'items' ),
					'properties'           => $changelog + array(
						'version'     => array(
							'type'        => 'string',
							'minLength'   => 1,
							'description' => __( 'Version number, like 1.4.0.', 'changelog-service' ),
						),
						'date'        => array(
							'type'        => 'string',
							'description' => __( 'Optional release date shown next to the version, like 2026-10-28.', 'changelog-service' ),
						),
						'notes'       => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'string' ),
							'description' => __( 'Optional lines of text shown above the items.', 'changelog-service' ),
						),
						'items'       => array(
							'type'     => 'array',
							'minItems' => 1,
							'items'    => array( 'type' => 'string' ),
						),
						'set_version' => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'dry_run'     => array(
							'type'    => 'boolean',
							'default' => false,
						),
					),
				),
				'output_schema'       => $output,
				'execute_callback'    => array( $this, 'add_release' ),
				'permission_callback' => array( $this, 'can_edit' ),
				'meta'                => $write_meta,
			),
			'update-changelog' => array(
				'label'               => __( 'Update a changelog', 'changelog-service' ),
				'description'         => __( 'Change a changelog\'s text or details. Only the fields you send change. changelog_raw replaces the whole text (readme.txt format: "= 1.2.0 =" headers, then "* Fixed - ..." items), so read it with get-changelog first and send it back edited. Setting type to host removes the version fields. dry_run shows the result without saving.', 'changelog-service' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'default'              => array(),
					'additionalProperties' => false,
					'properties'           => $changelog + array(
						'changelog_raw' => array(
							'type'        => 'string',
							'description' => __( 'The full changelog text.', 'changelog-service' ),
						),
						'name'          => array( 'type' => 'string' ),
						'type'          => array(
							'type' => 'string',
							'enum' => array( 'plugin', 'theme', 'host' ),
						),
						'version'       => array(
							'type'        => 'string',
							'description' => __( 'Current stable version.', 'changelog-service' ),
						),
						'tested'        => array(
							'type'        => 'string',
							'description' => __( 'WordPress version tested up to.', 'changelog-service' ),
						),
						'requires'      => array(
							'type'        => 'string',
							'description' => __( 'Minimum WordPress version.', 'changelog-service' ),
						),
						'requires_php'  => array(
							'type'        => 'string',
							'description' => __( 'Minimum PHP version.', 'changelog-service' ),
						),
						'homepage'      => array(
							'type'   => 'string',
							'format' => 'uri',
						),
						'dry_run'       => array(
							'type'    => 'boolean',
							'default' => false,
						),
					),
				),
				'output_schema'       => $output,
				'execute_callback'    => array( $this, 'update_changelog' ),
				'permission_callback' => array( $this, 'can_edit' ),
				'meta'                => $write_meta,
			),
		);
	}

	/**
	 * Permission to list changelogs.
	 *
	 * @return bool
	 */
	public function can_list(): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Permission to read or change one changelog. When it can't be found, the execute callback
	 * returns the not-found error.
	 *
	 * @param mixed $input Ability input.
	 * @return bool
	 */
	public function can_edit( $input = array() ): bool {
		$post = $this->find_changelog( (array) $input );
		if ( is_wp_error( $post ) ) {
			return current_user_can( 'edit_posts' );
		}
		return current_user_can( 'edit_post', $post->ID );
	}

	/**
	 * List changelogs.
	 *
	 * @param mixed $input Ability input.
	 * @return array
	 */
	public function list_changelogs( $input = array() ): array {
		$input    = (array) $input;
		$per_page = (int) ( $input['per_page'] ?? 20 );
		$page     = (int) ( $input['page'] ?? 1 );

		$args = array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'modified',
			'order'          => 'DESC',
		);
		if ( ! empty( $input['search'] ) ) {
			$args['s'] = sanitize_text_field( $input['search'] );
		}
		if ( ! empty( $input['type'] ) ) {
			$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => 'type',
					'value' => $input['type'],
				),
			);
		}

		$query      = new WP_Query( $args );
		$changelogs = array();
		foreach ( $query->posts as $post ) {
			$changelogs[] = $this->format( $post, false );
		}

		return array(
			'changelogs'  => $changelogs,
			'total'       => (int) $query->found_posts,
			'total_pages' => (int) $query->max_num_pages,
			'page'        => $page,
		);
	}

	/**
	 * Get one changelog.
	 *
	 * @param mixed $input Ability input.
	 * @return array|WP_Error
	 */
	public function get_changelog( $input = array() ) {
		$post = $this->find_changelog( (array) $input );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$data           = $this->format( $post );
		$data['badges'] = array();
		foreach ( ( new Changelog_Service_Shortcode() )->get_badge_configs() as $key => $config ) {
			$data['badges'][] = array(
				'type'     => (string) $key,
				'prefixes' => array_values( $config['prefixes'] ),
			);
		}

		return $data;
	}

	/**
	 * Add a release to the top of a changelog.
	 *
	 * @param mixed $input Ability input.
	 * @return array|WP_Error
	 */
	public function add_release( $input = array() ) {
		$input = (array) $input;
		$post  = $this->find_changelog( $input );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$shortcode = new Changelog_Service_Shortcode();
		$version   = sanitize_text_field( (string) $input['version'] );
		$number    = $shortcode->parse_version_string( $version )['version'];

		foreach ( $shortcode->get_parsed_changelog( $post->post_content ) as $existing ) {
			if ( $shortcode->parse_version_string( $existing['version'] )['version'] === $number ) {
				return new WP_Error(
					'version_exists',
					/* translators: %s: version number */
					sprintf( __( 'Version %s is already in this changelog. Use update-changelog to change it.', 'changelog-service' ), $number ),
					array( 'status' => 409 )
				);
			}
		}

		$date  = sanitize_text_field( (string) ( $input['date'] ?? '' ) );
		$lines = array( '' === $date ? '= ' . $version . ' =' : '= ' . $version . ' - ' . $date . ' =' );
		foreach ( (array) ( $input['notes'] ?? array() ) as $note ) {
			$note = sanitize_text_field( (string) $note );
			if ( '' !== $note ) {
				$lines[] = $note;
			}
		}
		foreach ( (array) $input['items'] as $item ) {
			// Drop a leading "* " or "- " the agent may have added itself.
			$item = sanitize_text_field( preg_replace( '/^[\*\-]\s+/', '', trim( (string) $item ) ) );
			if ( '' !== $item ) {
				$lines[] = '* ' . $item;
			}
		}

		$old     = trim( $post->post_content );
		$content = implode( "\n", $lines ) . ( '' === $old ? '' : "\n\n" . $old );
		$meta    = array();
		if ( ( $input['set_version'] ?? true ) && 'host' !== get_post_meta( $post->ID, 'type', true ) ) {
			$meta['version'] = $number;
		}

		return $this->save( $post, array( 'changelog_raw' => $content ) + $meta, ! empty( $input['dry_run'] ) );
	}

	/**
	 * Update a changelog's text or details.
	 *
	 * @param mixed $input Ability input.
	 * @return array|WP_Error
	 */
	public function update_changelog( $input = array() ) {
		$input = (array) $input;
		$post  = $this->find_changelog( $input );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$changes = array_intersect_key( $input, array_flip( array_merge( array( 'changelog_raw', 'name' ), self::META_FIELDS ) ) );
		if ( empty( $changes ) ) {
			return new WP_Error( 'no_changes', __( 'Send at least one field to change.', 'changelog-service' ), array( 'status' => 400 ) );
		}

		return $this->save( $post, $changes, ! empty( $input['dry_run'] ) );
	}

	/**
	 * Save changes to a changelog, cleaned the same way as the edit screen, and return it.
	 *
	 * @param WP_Post $post    The changelog.
	 * @param array   $changes Field => raw value (changelog_raw, name, or a meta field).
	 * @param bool    $dry_run Return the result without saving.
	 * @return array|WP_Error
	 */
	private function save( WP_Post $post, array $changes, bool $dry_run ) {
		$postarr = array();
		if ( isset( $changes['changelog_raw'] ) ) {
			$postarr['post_content'] = sanitize_textarea_field( (string) $changes['changelog_raw'] );
		}
		if ( isset( $changes['name'] ) ) {
			$postarr['post_title'] = sanitize_text_field( (string) $changes['name'] );
		}

		$meta = array();
		foreach ( self::META_FIELDS as $field ) {
			if ( isset( $changes[ $field ] ) ) {
				$meta[ $field ] = Changelog_Service_Meta_Fields::sanitize_field( $field, $changes[ $field ] );
			}
		}

		if ( $dry_run ) {
			$preview = clone $post;
			if ( isset( $postarr['post_content'] ) ) {
				$preview->post_content = $postarr['post_content'];
			}
			if ( isset( $postarr['post_title'] ) ) {
				$preview->post_title = $postarr['post_title'];
			}
			$data = $this->format( $preview );
			foreach ( $meta as $field => $value ) {
				$data[ $field ] = $value;
			}
			if ( 'host' === $data['type'] ) {
				$data['version']      = '';
				$data['tested']       = '';
				$data['requires']     = '';
				$data['requires_php'] = '';
			}
			$data['dry_run'] = true;
			return $data;
		}

		if ( ! empty( $postarr ) ) {
			// wp_update_post() expects slashed data.
			$result = wp_update_post( wp_slash( array( 'ID' => $post->ID ) + $postarr ), true );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}
		foreach ( $meta as $field => $value ) {
			update_post_meta( $post->ID, $field, $value );
		}
		Changelog_Service_Meta_Fields::clear_host_fields( $post->ID );

		return $this->format( get_post( $post->ID ) );
	}

	/**
	 * Find the changelog named by id or slug in the input.
	 *
	 * @param array $input Ability input.
	 * @return WP_Post|WP_Error
	 */
	private function find_changelog( array $input ) {
		$post = null;
		if ( ! empty( $input['id'] ) ) {
			$post = get_post( (int) $input['id'] );
		} elseif ( ! empty( $input['slug'] ) ) {
			$posts = get_posts(
				array(
					'name'        => sanitize_title( (string) $input['slug'] ),
					'post_type'   => self::POST_TYPE,
					'post_status' => 'any',
					'numberposts' => 1,
				)
			);
			$post  = $posts[0] ?? null;
		} else {
			return new WP_Error( 'missing_changelog', __( 'Send the changelog\'s id or slug.', 'changelog-service' ), array( 'status' => 400 ) );
		}

		if ( ! $post instanceof WP_Post || self::POST_TYPE !== $post->post_type ) {
			return new WP_Error( 'not_found', __( 'Changelog not found.', 'changelog-service' ), array( 'status' => 404 ) );
		}

		return $post;
	}

	/**
	 * Format a changelog like the REST API does, plus its status.
	 *
	 * @param WP_Post $post         The changelog.
	 * @param bool    $include_full Include the text and parsed versions.
	 * @return array
	 */
	private function format( WP_Post $post, bool $include_full = true ): array {
		return array( 'status' => $post->post_status ) + Changelog_Service_REST_API::format_changelog( $post, $include_full );
	}
}
