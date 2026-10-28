<?php
/**
 * Changelog Meta Fields
 *
 * Handles custom meta fields for changelog posts.
 *
 * @package Changelog_Service
 */

declare(strict_types=1);

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Changelog_Service_Meta_Fields
 *
 * Manages custom meta fields for changelogs.
 *
 * @since 1.0.0
 */
class Changelog_Service_Meta_Fields {

	/**
	 * Post type slug.
	 *
	 * @var string
	 */
	const POST_TYPE = 'changelog-service';

	/**
	 * Meta field keys.
	 *
	 * @var array
	 */
	const META_FIELDS = array(
		'type',
		'version',
		'tested',
		'requires',
		'requires_php',
		'homepage',
	);

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_meta' ), 10, 2 );
		add_action( 'init', array( $this, 'register_meta' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_meta_box_scripts' ) );
	}

	/**
	 * Enqueue scripts for meta box.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_meta_box_scripts( string $hook ): void {
		global $post_type;

		// Only load on changelog post type edit screens.
		if ( self::POST_TYPE !== $post_type ) {
			return;
		}

		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}

		// Register a dummy script handle to attach inline script to.
		wp_register_script(
			'caas-meta-fields',
			'',
			array( 'jquery' ),
			CAAS_VERSION,
			true
		);
		wp_enqueue_script( 'caas-meta-fields' );

		$inline_script = "
			jQuery(document).ready(function($) {
				function toggleFields() {
					var type = $('input[name=\"caas_type\"]:checked').val();
					if (type === 'host') {
						$('.caas-plugin-theme-fields').hide();
					} else {
						$('.caas-plugin-theme-fields').show();
					}
				}
				$('input[name=\"caas_type\"]').on('change', toggleFields);
				toggleFields();
			});
		";

		wp_add_inline_script( 'caas-meta-fields', $inline_script );
	}

	/**
	 * Register meta fields for REST API.
	 */
	public function register_meta(): void {
		foreach ( self::META_FIELDS as $field ) {
			register_post_meta(
				self::POST_TYPE,
				$field,
				array(
					'show_in_rest'  => true,
					'single'        => true,
					'type'          => 'string',
					'auth_callback' => function() {
						return current_user_can( 'edit_posts' );
					},
				)
			);
		}
	}

	/**
	 * Add the meta box.
	 */
	public function add_meta_box(): void {
		add_meta_box(
			'caas_meta',
			__( 'Changelog Details', 'changelog-service' ),
			array( $this, 'render_meta_box' ),
			self::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Render the meta box.
	 *
	 * @param WP_Post $post The post object.
	 */
	public function render_meta_box( WP_Post $post ): void {
		wp_nonce_field( 'caas_meta_nonce', 'caas_meta_nonce' );

		$type        = get_post_meta( $post->ID, 'type', true ) ?: 'plugin';
		$version     = get_post_meta( $post->ID, 'version', true );
		$tested      = get_post_meta( $post->ID, 'tested', true );
		$requires    = get_post_meta( $post->ID, 'requires', true );
		$requires_php = get_post_meta( $post->ID, 'requires_php', true );
		$homepage    = get_post_meta( $post->ID, 'homepage', true );

		?>
		<div class="caas-meta-fields">
			<div class="field-group full-width">
				<label for="caas_type_plugin">
					<?php esc_html_e( 'Type', 'changelog-service' ); ?>
				</label>
				<fieldset style="margin-top: 8px; border: none; padding: 0;">
					<label style="display: inline-block; margin-right: 20px;">
						<input
							type="radio"
							id="caas_type_plugin"
							name="caas_type"
							value="plugin"
							<?php checked( $type, 'plugin' ); ?>
						/>
						<?php esc_html_e( 'Plugin', 'changelog-service' ); ?>
					</label>
					<label style="display: inline-block; margin-right: 20px;">
						<input
							type="radio"
							id="caas_type_theme"
							name="caas_type"
							value="theme"
							<?php checked( $type, 'theme' ); ?>
						/>
						<?php esc_html_e( 'Theme', 'changelog-service' ); ?>
					</label>
					<label style="display: inline-block;">
						<input
							type="radio"
							id="caas_type_host"
							name="caas_type"
							value="host"
							<?php checked( $type, 'host' ); ?>
						/>
						<?php esc_html_e( 'Web Host', 'changelog-service' ); ?>
					</label>
				</fieldset>
				<span class="description"><?php esc_html_e( 'Select the type of changelog', 'changelog-service' ); ?></span>
			</div>

			<div class="field-group caas-plugin-theme-fields" style="<?php echo 'host' === $type ? 'display: none;' : ''; ?>">
				<label for="caas_version">
					<?php esc_html_e( 'Version', 'changelog-service' ); ?>
				</label>
				<input
					type="text"
					id="caas_version"
					name="caas_version"
					value="<?php echo esc_attr( $version ); ?>"
					placeholder="1.0.0"
				/>
				<span class="description"><?php esc_html_e( 'Current stable version', 'changelog-service' ); ?></span>
			</div>

			<div class="field-group caas-plugin-theme-fields" style="<?php echo 'host' === $type ? 'display: none;' : ''; ?>">
				<label for="caas_tested">
					<?php esc_html_e( 'Tested Up To', 'changelog-service' ); ?>
				</label>
				<input
					type="text"
					id="caas_tested"
					name="caas_tested"
					value="<?php echo esc_attr( $tested ); ?>"
					placeholder="6.4"
				/>
				<span class="description"><?php esc_html_e( 'WordPress version tested up to', 'changelog-service' ); ?></span>
			</div>

			<div class="field-group caas-plugin-theme-fields" style="<?php echo 'host' === $type ? 'display: none;' : ''; ?>">
				<label for="caas_requires">
					<?php esc_html_e( 'Requires WordPress', 'changelog-service' ); ?>
				</label>
				<input
					type="text"
					id="caas_requires"
					name="caas_requires"
					value="<?php echo esc_attr( $requires ); ?>"
					placeholder="5.8"
				/>
				<span class="description"><?php esc_html_e( 'Minimum WordPress version required', 'changelog-service' ); ?></span>
			</div>

			<div class="field-group caas-plugin-theme-fields" style="<?php echo 'host' === $type ? 'display: none;' : ''; ?>">
				<label for="caas_requires_php">
					<?php esc_html_e( 'Requires PHP', 'changelog-service' ); ?>
				</label>
				<input
					type="text"
					id="caas_requires_php"
					name="caas_requires_php"
					value="<?php echo esc_attr( $requires_php ); ?>"
					placeholder="7.4"
				/>
				<span class="description"><?php esc_html_e( 'Minimum PHP version required', 'changelog-service' ); ?></span>
			</div>

			<div class="field-group full-width">
				<label for="caas_homepage">
					<?php esc_html_e( 'Homepage URL', 'changelog-service' ); ?>
				</label>
				<input
					type="url"
					id="caas_homepage"
					name="caas_homepage"
					value="<?php echo esc_url( $homepage ); ?>"
					placeholder="https://example.com/my-plugin"
				/>
				<span class="description">
					<?php
					if ( 'host' === $type ) {
						esc_html_e( 'Web host homepage URL', 'changelog-service' );
					} else {
						esc_html_e( 'Plugin or theme homepage URL', 'changelog-service' );
					}
					?>
				</span>
			</div>
		</div>
		<?php
	}

	/**
	 * Save meta fields.
	 *
	 * @param int     $post_id The post ID.
	 * @param WP_Post $post    The post object.
	 */
	public function save_meta( int $post_id, WP_Post $post ): void {
		// Verify nonce.
		if ( ! isset( $_POST['caas_meta_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['caas_meta_nonce'] ) ), 'caas_meta_nonce' ) ) {
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

		// Save each field.
		$fields = array(
			'type'         => 'caas_type',
			'version'      => 'caas_version',
			'tested'       => 'caas_tested',
			'requires'     => 'caas_requires',
			'requires_php' => 'caas_requires_php',
			'homepage'     => 'caas_homepage',
		);

		foreach ( $fields as $meta_key => $post_key ) {
			if ( isset( $_POST[ $post_key ] ) ) {
				$value = 'homepage' === $meta_key
					? esc_url_raw( wp_unslash( $_POST[ $post_key ] ) )
					: sanitize_text_field( wp_unslash( $_POST[ $post_key ] ) );

				// Validate type field.
				if ( 'type' === $meta_key && ! in_array( $value, array( 'plugin', 'theme', 'host' ), true ) ) {
					$value = 'plugin'; // Default to plugin if invalid.
				}

				update_post_meta( $post_id, $meta_key, $value );
			}
		}

		// Clear fields that shouldn't exist for host type.
		$type = get_post_meta( $post_id, 'type', true );
		if ( 'host' === $type ) {
			delete_post_meta( $post_id, 'version' );
			delete_post_meta( $post_id, 'tested' );
			delete_post_meta( $post_id, 'requires' );
			delete_post_meta( $post_id, 'requires_php' );
		}
	}
}

