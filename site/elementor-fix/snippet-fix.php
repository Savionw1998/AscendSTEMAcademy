<?php
/*
 * One-time page fix, started by clicking the button in the notice at the top of wp-admin:
 *  1. Home page: put back the original design (from the backup the old Ascend Site Updates plugin kept).
 *  2. Replace the sections added on 2026-09-23 (About, Enrollment, Enhancements, Graduation, Shop, Blog,
 *     Time Card, Privacy Policy) with regular Elementor widgets styled like the rest of the site:
 *     centered, blue headings, rounded buttons, white cards and light-green bands.
 * Each page's previous design is kept in the option asa_elementor_fix_backup, and the notice offers an Undo.
 */
function asa_fix_data() {
	return json_decode( <<<'ASA_FIX_JSON'
__FIX_JSON__
ASA_FIX_JSON
		, true );
}

function asa_fix_flush( $pid ) {
	delete_post_meta( $pid, '_elementor_css' );
	delete_post_meta( $pid, '_elementor_element_cache' );
	delete_post_meta( $pid, '_elementor_page_assets' );
	clean_post_cache( $pid );
	if ( function_exists( 'w3tc_flush_post' ) ) {
		w3tc_flush_post( $pid );
	}
}

function asa_fix_flush_all() {
	if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
		\Elementor\Plugin::$instance->files_manager->clear_cache();
	}
	if ( function_exists( 'w3tc_flush_all' ) ) {
		w3tc_flush_all();
	}
}

/** Replace the element with id $old_id (anywhere in the tree) by the elements in $new. */
function asa_fix_replace( array &$elements, $old_id, array $new ) {
	foreach ( $elements as $i => $el ) {
		if ( isset( $el['id'] ) && $el['id'] === $old_id ) {
			array_splice( $elements, $i, 1, $new );
			return true;
		}
		if ( ! empty( $el['elements'] ) && is_array( $el['elements'] ) ) {
			$children = $el['elements'];
			if ( asa_fix_replace( $children, $old_id, $new ) ) {
				$elements[ $i ]['elements'] = $children;
				return true;
			}
		}
	}
	return false;
}

function asa_fix_run() {
	$log    = array();
	$backup = array();

	$old = get_option( 'asu_backups', array() );
	if ( ! empty( $old[2732]['raw'] ) ) {
		$backup[2732] = get_post_meta( 2732, '_elementor_data', true );
		update_post_meta( 2732, '_elementor_data', wp_slash( $old[2732]['raw'] ) );
		asa_fix_flush( 2732 );
		$log[] = 'Home page: restored to its original design.';
	} else {
		$log[] = 'Home page: no saved original found, so it was not changed.';
	}

	foreach ( (array) asa_fix_data() as $pid => $swaps ) {
		$pid = (int) $pid;
		$raw = get_post_meta( $pid, '_elementor_data', true );
		$els = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
		if ( ! is_array( $els ) ) {
			$log[] = get_the_title( $pid ) . ': no Elementor design found, skipped.';
			continue;
		}
		$done = 0;
		foreach ( $swaps as $old_id => $new ) {
			if ( asa_fix_replace( $els, $old_id, $new ) ) {
				$done++;
			}
		}
		if ( $done ) {
			$backup[ $pid ] = is_string( $raw ) ? $raw : wp_json_encode( $raw );
			update_post_meta( $pid, '_elementor_data', wp_slash( wp_json_encode( $els ) ) );
			update_post_meta( $pid, '_elementor_edit_mode', 'builder' );
			asa_fix_flush( $pid );
		}
		$log[] = sprintf( '%s: %d of %d sections rebuilt in the site style.', get_the_title( $pid ), $done, count( $swaps ) );
	}
	asa_fix_flush_all();
	update_option( 'asa_elementor_fix_backup', $backup, false );
	update_option( 'asa_elementor_fix_v1', array( 'time' => current_time( 'mysql' ), 'log' => $log ), false );
	return $log;
}

function asa_fix_undo() {
	$backup = get_option( 'asa_elementor_fix_backup', array() );
	foreach ( (array) $backup as $pid => $raw ) {
		update_post_meta( (int) $pid, '_elementor_data', wp_slash( $raw ) );
		asa_fix_flush( (int) $pid );
	}
	asa_fix_flush_all();
	delete_option( 'asa_elementor_fix_v1' );
	return count( (array) $backup );
}

add_action( 'admin_post_asa_fix', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'asa_fix' );
	if ( isset( $_POST['undo'] ) ) {
		$n = asa_fix_undo();
		set_transient( 'asa_fix_notice', array( sprintf( 'Undone: %d page(s) put back the way they were before the fix.', $n ) ), HOUR_IN_SECONDS );
	} elseif ( ! get_option( 'asa_elementor_fix_v1' ) ) {
		set_transient( 'asa_fix_notice', asa_fix_run(), HOUR_IN_SECONDS );
	}
	wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
	exit;
} );

add_action( 'admin_notices', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$form = function ( $label, $undo = false ) {
		$html  = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin:0 8px 8px 0">';
		$html .= wp_nonce_field( 'asa_fix', '_wpnonce', true, false ) . '<input type="hidden" name="action" value="asa_fix">';
		$html .= $undo ? '<input type="hidden" name="undo" value="1">' : '';
		$html .= '<button class="button ' . ( $undo ? '' : 'button-primary' ) . '">' . esc_html( $label ) . '</button></form>';
		return $html;
	};
	$log = get_transient( 'asa_fix_notice' );
	if ( $log ) {
		delete_transient( 'asa_fix_notice' );
		echo '<div class="notice notice-success"><p><strong>Ascend page fix:</strong></p><ul style="list-style:disc;margin-left:20px">';
		foreach ( (array) $log as $line ) {
			echo '<li>' . esc_html( $line ) . '</li>';
		}
		echo '</ul><p>Check the pages, then clear the cache (Performance &rarr; Purge All Caches).</p>';
		echo get_option( 'asa_elementor_fix_v1' ) ? $form( 'Undo the page fix', true ) : '';
		echo '</div>';
		return;
	}
	if ( get_option( 'asa_elementor_fix_v1' ) ) {
		return;
	}
	echo '<div class="notice notice-info"><p><strong>Ascend page fix is ready.</strong> It puts the Home page back to its original design and '
		. 'rebuilds the sections added on Sept 23 (About, Enrollment, Enhancements, Graduation, Shop, Blog, Time Card, Privacy Policy) '
		. 'as regular Elementor widgets that match the rest of the site. You can undo it afterwards.</p>';
	echo $form( 'Apply the page fix' ) . '</div>';
} );
