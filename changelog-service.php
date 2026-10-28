<?php
/**
 * Plugin Name: Changelog as a Service - Publish, Display, and Communicate Beautiful Changelogs
 * Plugin URI: https://changelogwp.com/caas/
 * Description: Publish and display beautiful changelogs for your WordPress plugins and themes. Provides REST API endpoints for changelog syndication.
 * Version: 1.3.3
 * Author: ChangelogWP
 * Author URI: https://changelogwp.com/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: changelog-service
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package Changelog_Service
 */

declare(strict_types=1);

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'CAAS_VERSION', '1.3.3' );
define( 'CAAS_PLUGIN_FILE', __FILE__ );
define( 'CAAS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CAAS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main Changelog_Service class.
 *
 * @since 1.0.0
 */
final class Changelog_Service {

	/**
	 * Single instance of the class.
	 *
	 * @var Changelog_Service|null
	 */
	private static ?Changelog_Service $instance = null;

	/**
	 * Get the single instance of the class.
	 *
	 * @return Changelog_Service
	 */
	public static function instance(): Changelog_Service {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Include required files.
	 */
	private function includes(): void {
		require_once CAAS_PLUGIN_DIR . 'includes/class-settings.php';
		require_once CAAS_PLUGIN_DIR . 'includes/class-post-type.php';
		require_once CAAS_PLUGIN_DIR . 'includes/class-meta-fields.php';
		require_once CAAS_PLUGIN_DIR . 'includes/class-wporg-api.php';
		require_once CAAS_PLUGIN_DIR . 'includes/class-changelog-service-shortcode.php';
		require_once CAAS_PLUGIN_DIR . 'includes/class-block.php';
		require_once CAAS_PLUGIN_DIR . 'includes/class-rest-api.php';
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Flush rewrite rules if needed (backup flush after activation).
		add_action( 'init', array( $this, 'maybe_flush_rewrite_rules' ), 999 );

		// Initialize components.
		new Changelog_Service_Settings();
		new Changelog_Service_Post_Type();
		new Changelog_Service_Meta_Fields();
		new Changelog_Service_Shortcode();
		new Changelog_Service_Block();
		new Changelog_Service_REST_API();
	}

	/**
	 * Flush rewrite rules if transient is set (backup flush after activation).
	 */
	public function maybe_flush_rewrite_rules(): void {
		if ( get_transient( 'caas_flush_rewrite_rules' ) ) {
			flush_rewrite_rules( true );
			delete_transient( 'caas_flush_rewrite_rules' );
		}
	}

	/**
	 * Enqueue frontend assets.
	 */
	public function enqueue_frontend_assets(): void {
		wp_register_style(
			'caas-changelog',
			CAAS_PLUGIN_URL . 'assets/css/changelog.css',
			array(),
			CAAS_VERSION
		);

		wp_register_script(
			'caas-frontend',
			CAAS_PLUGIN_URL . 'assets/js/frontend.js',
			array(),
			CAAS_VERSION,
			true
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( string $hook ): void {
		global $post_type;

		if ( 'changelog-service' !== $post_type ) {
			return;
		}

		wp_enqueue_style(
			'caas-admin',
			CAAS_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			CAAS_VERSION
		);

		wp_enqueue_script(
			'caas-admin',
			CAAS_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			CAAS_VERSION,
			true
		);
	}
}

/**
 * Returns the main instance of Changelog_Service.
 *
 * @return Changelog_Service
 */
function caas(): Changelog_Service {
	return Changelog_Service::instance();
}

// Initialize the plugin.
add_action( 'plugins_loaded', 'caas' );

// Activation hook.
register_activation_hook( __FILE__, 'caas_activate' );

/**
 * Plugin activation.
 */
function caas_activate(): void {
	// Trigger post type registration.
	require_once CAAS_PLUGIN_DIR . 'includes/class-post-type.php';
	$post_type = new Changelog_Service_Post_Type();
	$post_type->register_post_type();

	// Hard flush rewrite rules so permalinks work immediately.
	// Using true for hard flush to ensure .htaccess/rewrite rules are regenerated.
	flush_rewrite_rules( true );

	// Set a flag to flush again on next page load (backup mechanism).
	set_transient( 'caas_flush_rewrite_rules', true, 60 );
}

// Deactivation hook.
register_deactivation_hook( __FILE__, 'caas_deactivate' );

/**
 * Plugin deactivation.
 */
function caas_deactivate(): void {
	flush_rewrite_rules();
}
