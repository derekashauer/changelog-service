<?php
/**
 * Changelog Post Type
 *
 * Registers the changelog custom post type with plain text editor support.
 *
 * @package Changelog_Service
 */

declare(strict_types=1);

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Changelog_Service_Post_Type
 *
 * Handles changelog post type registration and editor configuration.
 *
 * @since 1.0.0
 */
class Changelog_Service_Post_Type {

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
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_filter( 'use_block_editor_for_post_type', array( $this, 'disable_gutenberg' ), 10, 2 );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_content' ), 10, 2 );
		add_filter( 'wp_insert_post_data', array( $this, 'filter_post_data' ), 10, 2 );

		// List table columns.
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'add_columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'render_columns' ), 10, 2 );

		// Admin scripts.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		// Frontend display.
		add_filter( 'the_content', array( $this, 'render_changelog_content' ), 10, 1 );
	}

	/**
	 * Register the changelog post type.
	 */
	public function register_post_type(): void {
		$labels = array(
			'name'                  => _x( 'Changelogs', 'Post type general name', 'changelog-service' ),
			'singular_name'         => _x( 'Changelog', 'Post type singular name', 'changelog-service' ),
			'menu_name'             => _x( 'Changelogs', 'Admin Menu text', 'changelog-service' ),
			'name_admin_bar'        => _x( 'Changelog', 'Add New on Toolbar', 'changelog-service' ),
			'add_new'               => __( 'Add New', 'changelog-service' ),
			'add_new_item'          => __( 'Add New Changelog', 'changelog-service' ),
			'new_item'              => __( 'New Changelog', 'changelog-service' ),
			'edit_item'             => __( 'Edit Changelog', 'changelog-service' ),
			'view_item'             => __( 'View Changelog', 'changelog-service' ),
			'all_items'             => __( 'All Changelogs', 'changelog-service' ),
			'search_items'          => __( 'Search Changelogs', 'changelog-service' ),
			'parent_item_colon'     => __( 'Parent Changelogs:', 'changelog-service' ),
			'not_found'             => __( 'No changelogs found.', 'changelog-service' ),
			'not_found_in_trash'    => __( 'No changelogs found in Trash.', 'changelog-service' ),
			'archives'              => _x( 'Changelog archives', 'The post type archive label', 'changelog-service' ),
			'insert_into_item'      => _x( 'Insert into changelog', 'Overrides the "Insert into post" phrase', 'changelog-service' ),
			'uploaded_to_this_item' => _x( 'Uploaded to this changelog', 'Overrides the "Uploaded to this post" phrase', 'changelog-service' ),
			'filter_items_list'     => _x( 'Filter changelogs list', 'Screen reader text', 'changelog-service' ),
			'items_list_navigation' => _x( 'Changelogs list navigation', 'Screen reader text', 'changelog-service' ),
			'items_list'            => _x( 'Changelogs list', 'Screen reader text', 'changelog-service' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_nav_menus'  => true,
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'changelog-details' ),
			'capability_type'    => 'post',
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => 25,
			'menu_icon'          => 'dashicons-list-view',
			'show_in_rest'       => true, // Enable REST API but we'll disable Gutenberg separately.
			'supports'           => array( 'title' ), // No 'editor' - we use custom plain text.
		);

		$args = apply_filters( 'caas_post_type_args', $args );

		register_post_type( self::POST_TYPE, $args );
	}

	/**
	 * Disable Gutenberg for the changelog post type.
	 *
	 * @param bool   $use_block_editor Whether to use the block editor.
	 * @param string $post_type        The post type.
	 * @return bool
	 */
	public function disable_gutenberg( bool $use_block_editor, string $post_type ): bool {
		if ( self::POST_TYPE === $post_type ) {
			return false;
		}
		return $use_block_editor;
	}

	/**
	 * Add meta boxes.
	 */
	public function add_meta_boxes(): void {
		// Content meta box.
		add_meta_box(
			'caas_content',
			__( 'Changelog Content', 'changelog-service' ),
			array( $this, 'render_content_meta_box' ),
			self::POST_TYPE,
			'normal',
			'high'
		);

		// API Endpoint meta box (sidebar).
		add_meta_box(
			'caas_api_endpoint',
			__( 'API Endpoint', 'changelog-service' ),
			array( $this, 'render_api_endpoint_meta_box' ),
			self::POST_TYPE,
			'side',
			'default'
		);

		// How to Use meta box (sidebar).
		add_meta_box(
			'caas_how_to_use',
			__( 'How to Use', 'changelog-service' ),
			array( $this, 'render_how_to_use_meta_box' ),
			self::POST_TYPE,
			'side',
			'default'
		);
	}

	/**
	 * Render the content meta box.
	 *
	 * @param WP_Post $post The post object.
	 */
	public function render_content_meta_box( WP_Post $post ): void {
		wp_nonce_field( 'caas_content_nonce', 'caas_content_nonce' );

		$content = $post->post_content;
		?>
		<div id="caas-content-wrap">
			<label for="caas-content-editor">
				<?php esc_html_e( 'Enter your changelog in WordPress readme.txt format:', 'changelog-service' ); ?>
			</label>
			<textarea
				id="caas-content-editor"
				name="caas_content"
				placeholder="= 1.0.0 =
* Added - New export feature
* Fixed: Bug with widget display
* Improved - Loading performance"
			><?php echo esc_textarea( $content ); ?></textarea>
			<p class="description">
				<?php esc_html_e( 'Use = version = for version headers and * for list items. Use "Prefix - ", "Prefix: ", or "Prefix : " format for badges (e.g., "Fixed - bug", "Fixed: bug", or "Fix : bug").', 'changelog-service' ); ?>
			</p>

			<div class="caas-format-help">
				<h4><?php esc_html_e( 'Format Example:', 'changelog-service' ); ?></h4>
				<pre>= 1.2.0 =
This release focuses on performance and security.
* Added - New export feature
* Improved: Loading speed by 50%
* Fixed - Issue with date formatting
* Security: Patch for user input
* Fix : Resolved login issue
* Dev: Added filter for customization

= 1.1.0 =
* Added - Widget support
* Fixed: Compatibility with PHP 8.1</pre>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the How to Use meta box.
	 *
	 * @param WP_Post $post The post object.
	 */
	public function render_how_to_use_meta_box( WP_Post $post ): void {
		$post_id   = $post->ID;
		$shortcode = '[changelog_service id="' . absint( $post_id ) . '"]';
		?>
		<div class="caas-how-to-use">
			<h4 style="margin-top: 0;"><?php esc_html_e( 'Using the Block', 'changelog-service' ); ?></h4>
			<ol style="margin-left: 20px;">
				<li><?php esc_html_e( 'Edit any post or page', 'changelog-service' ); ?></li>
				<li><?php esc_html_e( 'Add the "Changelog" block', 'changelog-service' ); ?></li>
				<li><?php esc_html_e( 'Select this changelog from the dropdown', 'changelog-service' ); ?></li>
			</ol>

			<h4><?php esc_html_e( 'Using the Shortcode', 'changelog-service' ); ?></h4>
			<div style="margin-bottom: 10px;">
				<input
					type="text"
					value="<?php echo esc_attr( $shortcode ); ?>"
					class="widefat caas-copy-input"
					readonly
					onclick="this.select();"
					style="font-family: monospace; font-size: 12px;"
				/>
			</div>
			<button type="button" class="button button-primary caas-copy-btn" data-copy="<?php echo esc_attr( $shortcode ); ?>" style="width: 100%;">
				<?php esc_html_e( 'Copy Shortcode', 'changelog-service' ); ?>
			</button>
			<p class="description" style="margin-top: 8px; margin-bottom: 0;">
				<?php esc_html_e( 'Paste this shortcode anywhere to display the changelog.', 'changelog-service' ); ?>
			</p>
			<p style="margin-top: 12px; margin-bottom: 0; padding-top: 12px; border-top: 1px solid #ddd;">
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=changelog-service&page=caas-settings#howto' ) ); ?>" target="_blank">
					<?php esc_html_e( 'View advanced usage &rarr;', 'changelog-service' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Render the API endpoint meta box.
	 *
	 * @param WP_Post $post The post object.
	 */
	public function render_api_endpoint_meta_box( WP_Post $post ): void {
		if ( 'publish' !== $post->post_status ) {
			echo '<p class="description">' . esc_html__( 'Publish this changelog to get the API endpoint URL.', 'changelog-service' ) . '</p>';
			return;
		}

		$endpoint_url = $this->get_api_endpoint_url( $post );
		?>
		<div class="caas-api-endpoint">
			<input
				type="text"
				value="<?php echo esc_url( $endpoint_url ); ?>"
				class="widefat caas-copy-input"
				readonly
				onclick="this.select();"
			/>
			<button type="button" class="button caas-copy-btn" data-copy="<?php echo esc_url( $endpoint_url ); ?>">
				<?php esc_html_e( 'Copy', 'changelog-service' ); ?>
			</button>
			<p class="description" style="margin-top: 8px;">
				<?php esc_html_e( 'Use this URL to access the changelog data via REST API.', 'changelog-service' ); ?>
			</p>
			<p style="margin-top: 12px; margin-bottom: 0; padding-top: 12px; border-top: 1px solid #ddd;">
				<strong><?php esc_html_e( 'Want more visibility?', 'changelog-service' ); ?></strong><br>
				<?php esc_html_e( 'Submit your changelog to ChangelogWP.com and let users subscribe to your updates.', 'changelog-service' ); ?>
			</p>
			<p style="margin-top: 8px; margin-bottom: 0;">
				<a href="https://changelogwp.com/changelog-submission/" target="_blank" class="button button-secondary" style="width: 100%; text-align: center;">
					<?php esc_html_e( 'Submit to ChangelogWP.com &rarr;', 'changelog-service' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Get the API endpoint URL for a changelog.
	 *
	 * @param WP_Post $post The post object.
	 * @return string
	 */
	public function get_api_endpoint_url( WP_Post $post ): string {
		return rest_url( 'changelogwp/v1/changelog/' . $post->post_name );
	}

	/**
	 * Add custom columns to the list table.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function add_columns( array $columns ): array {
		$new_columns = array();

		foreach ( $columns as $key => $label ) {
			$new_columns[ $key ] = $label;

			// Add Shortcode and API Endpoint columns after title.
			if ( 'title' === $key ) {
				$new_columns['shortcode']    = __( 'Shortcode', 'changelog-service' );
				$new_columns['api_endpoint'] = __( 'API Endpoint', 'changelog-service' );
			}
		}

		return $new_columns;
	}

	/**
	 * Render custom column content.
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id Post ID.
	 */
	public function render_columns( string $column, int $post_id ): void {
		$post = get_post( $post_id );

		if ( 'shortcode' === $column ) {
			if ( 'publish' !== $post->post_status ) {
				echo '<span class="description">' . esc_html__( 'Not published', 'changelog-service' ) . '</span>';
				return;
			}

			$shortcode_id = '[changelog_service id="' . absint( $post_id ) . '"]';
			?>
			<div class="caas-shortcode-cell">
				<code class="caas-shortcode-text" style="font-size: 11px; display: block; margin-bottom: 5px;"><?php echo esc_html( $shortcode_id ); ?></code>
				<button type="button" class="button button-small caas-copy-btn" data-copy="<?php echo esc_attr( $shortcode_id ); ?>">
					<?php esc_html_e( 'Copy', 'changelog-service' ); ?>
				</button>
			</div>
			<?php
			return;
		}

		if ( 'api_endpoint' === $column ) {
			if ( 'publish' !== $post->post_status ) {
				echo '<span class="description">' . esc_html__( 'Not published', 'changelog-service' ) . '</span>';
				return;
			}

			$endpoint_url = $this->get_api_endpoint_url( $post );
			?>
			<div class="caas-endpoint-cell">
				<code class="caas-endpoint-url"><?php echo esc_html( $endpoint_url ); ?></code>
				<button type="button" class="button button-small caas-copy-btn" data-copy="<?php echo esc_url( $endpoint_url ); ?>">
					<?php esc_html_e( 'Copy', 'changelog-service' ); ?>
				</button>
			</div>
			<?php
		}
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_scripts( string $hook ): void {
		global $post_type;

		if ( self::POST_TYPE !== $post_type ) {
			return;
		}

		// Inline script for copy functionality.
		wp_add_inline_script(
			'jquery',
			"
			jQuery(document).ready(function($) {
				$(document).on('click', '.caas-copy-btn', function(e) {
					e.preventDefault();
					var url = $(this).data('copy');
					var btn = $(this);
					var originalText = btn.text();
					
					navigator.clipboard.writeText(url).then(function() {
						btn.text('" . esc_js( __( 'Copied!', 'changelog-service' ) ) . "');
						setTimeout(function() {
							btn.text(originalText);
						}, 2000);
					}).catch(function() {
						// Fallback for older browsers.
						var input = $('<input type=\"text\" value=\"' + url + '\" style=\"position:absolute;left:-9999px;\">');
						$('body').append(input);
						input[0].select();
						document.execCommand('copy');
						input.remove();
						btn.text('" . esc_js( __( 'Copied!', 'changelog-service' ) ) . "');
						setTimeout(function() {
							btn.text(originalText);
						}, 2000);
					});
				});
			});
			"
		);
	}

	/**
	 * Save the changelog content.
	 *
	 * @param int     $post_id The post ID.
	 * @param WP_Post $post    The post object.
	 */
	public function save_content( int $post_id, WP_Post $post ): void {
		// Verify nonce.
		if ( ! isset( $_POST['caas_content_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['caas_content_nonce'] ) ), 'caas_content_nonce' ) ) {
			return;
		}

		// Check autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check permissions.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Save content.
		if ( isset( $_POST['caas_content'] ) ) {
			$content = sanitize_textarea_field( wp_unslash( $_POST['caas_content'] ) );

			// Remove the action to prevent infinite loop.
			remove_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_content' ), 10 );

			wp_update_post(
				array(
					'ID'           => $post_id,
					'post_content' => $content,
				)
			);

			// Re-add the action.
			add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_content' ), 10, 2 );
		}
	}

	/**
	 * Filter post data to use our custom content field.
	 *
	 * @param array $data    Post data.
	 * @param array $postarr Raw post data.
	 * @return array
	 */
	public function filter_post_data( array $data, array $postarr ): array {
		if ( isset( $postarr['post_type'] ) && self::POST_TYPE === $postarr['post_type'] ) {
			if ( isset( $_POST['caas_content'] ) &&
				isset( $_POST['caas_content_nonce'] ) &&
				wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['caas_content_nonce'] ) ), 'caas_content_nonce' ) ) {
				$data['post_content'] = sanitize_textarea_field( wp_unslash( $_POST['caas_content'] ) );
			}
		}
		return $data;
	}

	/**
	 * Render changelog content on single post view.
	 *
	 * @param string $content The post content.
	 * @return string
	 */
	public function render_changelog_content( string $content ): string {
		// Only on frontend, single changelog posts.
		if ( is_admin() || ! is_singular( self::POST_TYPE ) ) {
			return $content;
		}

		global $post;

		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return $content;
		}

		// Use the shortcode to render the changelog with default settings.
		$shortcode = '[changelog_service id="' . absint( $post->ID ) . '"]';

		// Render using do_shortcode to get the same formatting as the shortcode.
		// The shortcode output is already escaped by the shortcode handler, but we ensure it's safe.
		$formatted_changelog = do_shortcode( $shortcode );

		// Return the formatted changelog (already escaped by shortcode handler).
		return wp_kses_post( $formatted_changelog );
	}
}
