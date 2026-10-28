<?php
/**
 * ChangelogWP Uninstall
 *
 * Fired when the plugin is uninstalled.
 *
 * @package ChangelogWP
 */

// Exit if not called from WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Optional: Delete all changelog posts and meta.
// Uncomment below if you want to remove all data on uninstall.
/*
$changelogs = get_posts( array(
	'post_type'      => 'changelog',
	'post_status'    => 'any',
	'posts_per_page' => -1,
	'fields'         => 'ids',
) );

foreach ( $changelogs as $changelog_id ) {
	wp_delete_post( $changelog_id, true );
}
*/

// Flush rewrite rules.
flush_rewrite_rules();

