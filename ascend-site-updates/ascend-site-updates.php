<?php
/**
 * Plugin Name:       Ascend Site Updates
 * Description:       Reviews and applies a packaged set of content updates to Ascend STEM Academy's Elementor pages (with before/after preview and one-click restore), plus redirects, a sibling discount, a latest-guides shortcode and a plugin speed check. Tools &rarr; Ascend Site Updates.
 * Version:           1.0.0
 * Author:            Ascend STEM Academy
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * License:           GPL-2.0-or-later
 * Text Domain:       ascend-site-updates
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ASU_VERSION', '1.0.0' );
define( 'ASU_DIR', plugin_dir_path( __FILE__ ) );
define( 'ASU_URL', plugin_dir_url( __FILE__ ) );

require_once ASU_DIR . 'includes/engine.php';
require_once ASU_DIR . 'includes/features.php';
require_once ASU_DIR . 'includes/speed.php';

/* ------------------------------------------------------------------ */
/* Data                                                                */
/* ------------------------------------------------------------------ */

function asu_changes() {
	static $data = null;
	if ( null === $data ) {
		$json = file_get_contents( ASU_DIR . 'data/changes.json' );
		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			$data = array( 'pages' => array(), 'changes' => array() );
		}
	}
	return $data;
}

function asu_changes_for_page( $page_id ) {
	return array_values( array_filter( asu_changes()['changes'], function ( $c ) use ( $page_id ) {
		return (int) $c['page'] === (int) $page_id;
	} ) );
}

/** Elementor data as an array, whether it is stored as a JSON string or an array. */
function asu_get_elements( $page_id ) {
	$raw = get_post_meta( $page_id, '_elementor_data', true );
	if ( is_string( $raw ) && '' !== $raw ) {
		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) ? $decoded : null;
	}
	return is_array( $raw ) ? $raw : null;
}

function asu_save_elements( $page_id, array $elements ) {
	// Same storage format Elementor itself uses (a slashed JSON string).
	update_post_meta( $page_id, '_elementor_data', wp_slash( wp_json_encode( $elements ) ) );
	asu_flush_page( $page_id );
}

function asu_flush_page( $page_id ) {
	delete_post_meta( $page_id, '_elementor_element_cache' );
	delete_post_meta( $page_id, '_elementor_css' );
	if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
		\Elementor\Plugin::$instance->files_manager->clear_cache();
	}
	clean_post_cache( $page_id );
	if ( function_exists( 'w3tc_flush_post' ) ) {
		w3tc_flush_post( $page_id );
	}
}

/** Keep the untouched original of each page the first time we change it. */
function asu_backup_page( $page_id ) {
	$backups = get_option( 'asu_backups', array() );
	if ( isset( $backups[ $page_id ] ) ) {
		return;
	}
	$raw = get_post_meta( $page_id, '_elementor_data', true );
	$backups[ $page_id ] = array(
		'raw'  => is_string( $raw ) ? $raw : wp_json_encode( $raw ),
		'time' => current_time( 'mysql' ),
	);
	update_option( 'asu_backups', $backups, false );
}

function asu_restore_page( $page_id ) {
	$backups = get_option( 'asu_backups', array() );
	if ( empty( $backups[ $page_id ]['raw'] ) ) {
		return false;
	}
	update_post_meta( $page_id, '_elementor_data', wp_slash( $backups[ $page_id ]['raw'] ) );
	asu_flush_page( $page_id );
	unset( $backups[ $page_id ] );
	update_option( 'asu_backups', $backups, false );
	return true;
}

/**
 * Run a page's changes in order. With $only (list of change ids) only those are applied;
 * the rest are still simulated so later changes see the right tree.
 * Returns [ statuses(id => [status, detail]), changed(bool) ].
 */
function asu_run_page( $page_id, $write = false, $only = null ) {
	$elements = asu_get_elements( $page_id );
	$statuses = array();
	if ( null === $elements ) {
		foreach ( asu_changes_for_page( $page_id ) as $c ) {
			$statuses[ $c['id'] ] = array( 'missing', 'This page has no Elementor data.' );
		}
		return array( $statuses, false );
	}
	$working = $elements;
	$changed = false;
	foreach ( asu_changes_for_page( $page_id ) as $c ) {
		list( $next, $status, $detail ) = asu_apply_change( $working, $c );
		$selected = null === $only || in_array( $c['id'], $only, true );
		if ( 'applied' === $status && ! $selected ) {
			$statuses[ $c['id'] ] = array( 'pending', 'Ready to apply.' );
			continue;
		}
		if ( 'applied' === $status ) {
			$working = $next;
			$changed = true;
			if ( ! $write ) {
				$status = 'pending';
				$detail = 'Ready to apply.';
			}
		}
		$statuses[ $c['id'] ] = array( $status, $detail );
	}
	if ( $write && $changed ) {
		asu_backup_page( $page_id );
		asu_save_elements( $page_id, $working );
		$log = get_option( 'asu_log', array() );
		$log[] = array( 'time' => current_time( 'mysql' ), 'page' => $page_id, 'user' => get_current_user_id() );
		update_option( 'asu_log', array_slice( $log, -50 ), false );
	}
	return array( $statuses, $changed );
}

/* ------------------------------------------------------------------ */
/* Admin                                                               */
/* ------------------------------------------------------------------ */

add_action( 'admin_menu', function () {
	add_management_page( 'Ascend Site Updates', 'Ascend Site Updates', 'manage_options', 'ascend-site-updates', 'asu_render_admin' );
} );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( 'tools_page_ascend-site-updates' === $hook ) {
		wp_enqueue_style( 'asu-admin', ASU_URL . 'assets/admin.css', array(), ASU_VERSION );
	}
} );

function asu_admin_url( $args = array() ) {
	return add_query_arg( array_merge( array( 'page' => 'ascend-site-updates' ), $args ), admin_url( 'tools.php' ) );
}

add_action( 'admin_post_asu_apply', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'asu_apply' );
	$target = isset( $_POST['page_id'] ) ? sanitize_text_field( wp_unslash( $_POST['page_id'] ) ) : '';
	$only   = isset( $_POST['change'] ) ? array( sanitize_text_field( wp_unslash( $_POST['change'] ) ) ) : null;
	$pages  = 'all' === $target ? array_keys( asu_changes()['pages'] ) : array( (int) $target );
	$count  = 0;
	foreach ( $pages as $pid ) {
		list( , $changed ) = asu_run_page( (int) $pid, true, $only );
		$count += $changed ? 1 : 0;
	}
	wp_safe_redirect( asu_admin_url( array( 'asu_msg' => 'applied', 'n' => $count ) ) );
	exit;
} );

add_action( 'admin_post_asu_restore', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'asu_restore' );
	$pid = isset( $_POST['page_id'] ) ? (int) $_POST['page_id'] : 0;
	$ok  = asu_restore_page( $pid );
	wp_safe_redirect( asu_admin_url( array( 'asu_msg' => $ok ? 'restored' : 'norestore' ) ) );
	exit;
} );

add_action( 'admin_post_asu_settings', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'asu_settings' );
	update_option( 'asu_sibling_enabled', empty( $_POST['sibling'] ) ? 0 : 1 );
	update_option( 'asu_redirects_enabled', empty( $_POST['redirects'] ) ? 0 : 1 );
	wp_safe_redirect( asu_admin_url( array( 'tab' => 'settings', 'asu_msg' => 'saved' ) ) );
	exit;
} );

function asu_status_badge( $status ) {
	$labels = array(
		'pending' => 'Ready',
		'applied' => 'Applied',
		'done'    => 'Done',
		'missing' => 'Needs a look',
	);
	$label = isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	return '<span class="asu-badge asu-' . esc_attr( $status ) . '">' . esc_html( $label ) . '</span>';
}

function asu_render_admin() {
	$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'pages';
	echo '<div class="wrap asu-wrap"><h1>Ascend Site Updates</h1>';

	if ( isset( $_GET['asu_msg'] ) ) {
		$msgs = array(
			'applied'   => sprintf( 'Updates applied to %d page(s). A backup of each changed page was kept.', isset( $_GET['n'] ) ? (int) $_GET['n'] : 0 ),
			'restored'  => 'Page restored to its original version.',
			'norestore' => 'No backup found for that page.',
			'saved'     => 'Settings saved.',
			'deactivated' => 'Plugin deactivated. You can reactivate it any time from the Plugins screen.',
		);
		$key = sanitize_key( $_GET['asu_msg'] );
		if ( isset( $msgs[ $key ] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $msgs[ $key ] ) . '</p></div>';
		}
	}

	$tabs = array( 'pages' => 'Page updates', 'speed' => 'Speed check', 'settings' => 'Settings' );
	echo '<nav class="nav-tab-wrapper">';
	foreach ( $tabs as $k => $label ) {
		printf( '<a href="%s" class="nav-tab %s">%s</a>', esc_url( asu_admin_url( array( 'tab' => $k ) ) ), $tab === $k ? 'nav-tab-active' : '', esc_html( $label ) );
	}
	echo '</nav>';

	if ( 'speed' === $tab ) {
		asu_render_speed_tab();
	} elseif ( 'settings' === $tab ) {
		asu_render_settings_tab();
	} else {
		asu_render_pages_tab();
	}
	echo '</div>';
}

function asu_render_pages_tab() {
	$data    = asu_changes();
	$backups = get_option( 'asu_backups', array() );
	echo '<p class="asu-intro">Each row is one change. <strong>Ready</strong> means it will apply cleanly; <strong>Done</strong> means the page already has it; <strong>Needs a look</strong> means the text on the page has changed since these updates were written, so that row is skipped. Applying keeps a backup of the page, and <em>Restore original</em> puts it back exactly as it was.</p>';

	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="asu-applyall">';
	wp_nonce_field( 'asu_apply' );
	echo '<input type="hidden" name="action" value="asu_apply"><input type="hidden" name="page_id" value="all">';
	submit_button( 'Apply all ready updates', 'primary', 'submit', false );
	echo '</form>';

	foreach ( $data['pages'] as $pid => $title ) {
		$pid = (int) $pid;
		list( $statuses ) = asu_run_page( $pid, false );
		$ready = count( array_filter( $statuses, function ( $s ) { return 'pending' === $s[0]; } ) );
		printf(
			'<section class="asu-page"><h2>%s <a href="%s" target="_blank" rel="noopener">view page</a> <a href="%s">edit in Elementor</a></h2>',
			esc_html( $title ),
			esc_url( get_permalink( $pid ) ),
			esc_url( admin_url( 'post.php?post=' . $pid . '&action=elementor' ) )
		);
		echo '<table class="widefat striped asu-table"><thead><tr><th>Change</th><th>Before &rarr; after</th><th>Status</th><th></th></tr></thead><tbody>';
		foreach ( asu_changes_for_page( $pid ) as $c ) {
			$st = isset( $statuses[ $c['id'] ] ) ? $statuses[ $c['id'] ] : array( 'missing', '' );
			echo '<tr><td><strong>' . esc_html( $c['label'] ) . '</strong>';
			if ( ! empty( $c['why'] ) ) {
				echo '<br><span class="asu-why">' . esc_html( $c['why'] ) . '</span>';
			}
			echo '</td><td class="asu-diff">' . asu_describe_change( $c ) . '</td>';
			echo '<td>' . asu_status_badge( $st[0] ) . '<br><span class="asu-detail">' . esc_html( $st[1] ) . '</span></td><td>';
			if ( 'pending' === $st[0] ) {
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
				wp_nonce_field( 'asu_apply' );
				echo '<input type="hidden" name="action" value="asu_apply"><input type="hidden" name="page_id" value="' . esc_attr( $pid ) . '"><input type="hidden" name="change" value="' . esc_attr( $c['id'] ) . '">';
				submit_button( 'Apply', 'secondary small', 'submit', false );
				echo '</form>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table><div class="asu-page-actions">';
		if ( $ready ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( 'asu_apply' );
			echo '<input type="hidden" name="action" value="asu_apply"><input type="hidden" name="page_id" value="' . esc_attr( $pid ) . '">';
			submit_button( sprintf( 'Apply %d ready update(s) on this page', $ready ), 'secondary', 'submit', false );
			echo '</form>';
		}
		if ( isset( $backups[ $pid ] ) ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'Restore this page to how it was before these updates?\');">';
			wp_nonce_field( 'asu_restore' );
			echo '<input type="hidden" name="action" value="asu_restore"><input type="hidden" name="page_id" value="' . esc_attr( $pid ) . '">';
			submit_button( 'Restore original (backup from ' . $backups[ $pid ]['time'] . ')', 'delete', 'submit', false );
			echo '</form>';
		}
		echo '</div></section>';
	}
}

/** Human-readable before/after for a change row. */
function asu_describe_change( array $c ) {
	$short = function ( $s, $n = 220 ) {
		$s = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( preg_replace( '/<[^>]+>/', ' ', (string) $s ) ) ) );
		return mb_strlen( $s ) > $n ? mb_substr( $s, 0, $n ) . '&hellip;' : esc_html( $s );
	};
	switch ( $c['type'] ) {
		case 'replace':
			$before = '' === trim( wp_strip_all_tags( $c['find'] ) ) ? esc_html( $c['find'] ) : $short( $c['find'] );
			$after  = '' === $c['replace'] ? '<em>(removed)</em>' : ( '' === trim( wp_strip_all_tags( $c['replace'] ) ) ? esc_html( $c['replace'] ) : $short( $c['replace'] ) );
			return '<del>' . $before . '</del> &rarr; <ins>' . $after . '</ins>';
		case 'set':
			return 'Set <code>' . esc_html( $c['setting'] ) . '</code> to <code>' . esc_html( is_scalar( $c['value'] ) ? (string) $c['value'] : ( null === $c['value'] ? '(none)' : wp_json_encode( $c['value'] ) ) ) . '</code>';
		case 'remove':
			return 'Remove this block from the page.';
		case 'remove_item':
			return 'Remove list item containing <del>' . esc_html( $c['match'] ) . '</del>';
		case 'add_item':
			return 'Add item: <ins>' . $short( isset( $c['item'][ $c['match_key'] ] ) ? $c['item'][ $c['match_key'] ] : '' ) . '</ins>';
		case 'insert':
			$html = asu_first_html( $c['new'] );
			return 'Add a new section: <ins>' . $short( $html ) . '</ins>';
		case 'replace_all':
			return 'Replace the page content: <ins>' . $short( asu_first_html( array( 'elements' => $c['elements'] ) ), 300 ) . '</ins>';
	}
	return '';
}

function asu_first_html( array $el ) {
	if ( isset( $el['settings'] ) ) {
		foreach ( array( 'html', 'editor', 'title', 'shortcode' ) as $k ) {
			if ( ! empty( $el['settings'][ $k ] ) && is_string( $el['settings'][ $k ] ) ) {
				return $el['settings'][ $k ];
			}
		}
	}
	$out = '';
	if ( ! empty( $el['elements'] ) ) {
		foreach ( $el['elements'] as $child ) {
			$out .= ' ' . asu_first_html( $child );
		}
	}
	return $out;
}

function asu_render_settings_tab() {
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="asu-settings">';
	wp_nonce_field( 'asu_settings' );
	echo '<input type="hidden" name="action" value="asu_settings">';
	printf(
		'<p><label><input type="checkbox" name="sibling" value="1" %s> <strong>Sibling discount</strong>: 10%% off every enrollment after the first in the same order (the highest-priced child pays full price).</label></p>',
		checked( asu_sibling_enabled(), true, false )
	);
	printf(
		'<p><label><input type="checkbox" name="redirects" value="1" %s> <strong>Redirects</strong>: send the broken links <code>/blog/</code> to <code>/posts/</code> and <code>/register/</code> to <code>/registration/</code>.</label></p>',
		checked( asu_redirects_enabled(), true, false )
	);
	submit_button( 'Save settings' );
	echo '</form>';
	echo '<h2>Shortcode</h2><p><code>[ascend_latest_guides count="3"]</code> shows the newest blog guides as cards. The Home page update uses it.</p>';
}
