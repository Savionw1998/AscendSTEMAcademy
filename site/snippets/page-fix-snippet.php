<?php
/**
 * Ascend Page Fix (Code Snippets: "Only run in administration area").
 *
 * Adds Tools > Ascend Page Fix. Clicking "Apply" there:
 *  1. puts the Home page back to its original design (from the backup the old Ascend Site Updates plugin kept);
 *  2. replaces the sections added on Sept 23 with regular Elementor widgets styled like the rest of the site.
 * The new sections are downloaded from the Ascend GitHub repository and checked against a fixed checksum,
 * so nothing is applied unless the file is exactly the reviewed one. "Undo" puts every page back.
 * The snippet can be deleted once you are happy with the pages.
 */

define( 'ASA_PF_URL', 'https://raw.githubusercontent.com/Savionw1998/AscendSTEMAcademy/c7022d0ca299f976e2f13edd4f68e63427174d9d/site/elementor-fix/fix.json' );
define( 'ASA_PF_SHA256', 'b273c9e8b020693506de7c778381c61a032174d90a51ae5c101585a74af22598' );

function asa_pf_flush( $pid ) {
	delete_post_meta( $pid, '_elementor_css' );
	delete_post_meta( $pid, '_elementor_element_cache' );
	delete_post_meta( $pid, '_elementor_page_assets' );
	clean_post_cache( $pid );
	if ( function_exists( 'w3tc_flush_post' ) ) {
		w3tc_flush_post( $pid );
	}
}

function asa_pf_replace( array &$elements, $old_id, array $new ) {
	foreach ( $elements as $i => $el ) {
		if ( isset( $el['id'] ) && $el['id'] === $old_id ) {
			array_splice( $elements, $i, 1, $new );
			return true;
		}
		if ( ! empty( $el['elements'] ) && is_array( $el['elements'] ) ) {
			$children = $el['elements'];
			if ( asa_pf_replace( $children, $old_id, $new ) ) {
				$elements[ $i ]['elements'] = $children;
				return true;
			}
		}
	}
	return false;
}

function asa_pf_apply() {
	$res = wp_remote_get( ASA_PF_URL, array( 'timeout' => 30 ) );
	if ( is_wp_error( $res ) ) {
		return array( 'Could not download the page designs: ' . $res->get_error_message() );
	}
	$body = wp_remote_retrieve_body( $res );
	if ( hash( 'sha256', $body ) !== ASA_PF_SHA256 ) {
		return array( 'The downloaded page designs did not match the expected file, so nothing was changed.' );
	}
	$fix = json_decode( $body, true );
	if ( ! is_array( $fix ) ) {
		return array( 'The page designs could not be read, so nothing was changed.' );
	}

	$log    = array();
	$backup = array();
	$old    = get_option( 'asu_backups', array() );
	if ( ! empty( $old[2732]['raw'] ) ) {
		$backup[2732] = get_post_meta( 2732, '_elementor_data', true );
		update_post_meta( 2732, '_elementor_data', wp_slash( $old[2732]['raw'] ) );
		asa_pf_flush( 2732 );
		$log[] = 'Home page: restored to its original design.';
	} else {
		$log[] = 'Home page: no saved original found, so it was not changed.';
	}
	foreach ( $fix as $pid => $swaps ) {
		$pid = (int) $pid;
		$raw = get_post_meta( $pid, '_elementor_data', true );
		$els = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
		if ( ! is_array( $els ) ) {
			$log[] = get_the_title( $pid ) . ': no Elementor design found, skipped.';
			continue;
		}
		$done = 0;
		foreach ( $swaps as $old_id => $new ) {
			if ( asa_pf_replace( $els, $old_id, $new ) ) {
				$done++;
			}
		}
		if ( $done ) {
			$backup[ $pid ] = is_string( $raw ) ? $raw : wp_json_encode( $raw );
			update_post_meta( $pid, '_elementor_data', wp_slash( wp_json_encode( $els ) ) );
			update_post_meta( $pid, '_elementor_edit_mode', 'builder' );
			asa_pf_flush( $pid );
		}
		$log[] = sprintf( '%s: %d of %d sections rebuilt in the site style.', get_the_title( $pid ), $done, count( $swaps ) );
	}
	if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
		\Elementor\Plugin::$instance->files_manager->clear_cache();
	}
	if ( function_exists( 'w3tc_flush_all' ) ) {
		w3tc_flush_all();
	}
	update_option( 'asa_pf_backup', $backup, false );
	update_option( 'asa_pf_done', array( 'time' => current_time( 'mysql' ), 'log' => $log ), false );
	return $log;
}

function asa_pf_undo() {
	$backup = get_option( 'asa_pf_backup', array() );
	foreach ( (array) $backup as $pid => $raw ) {
		update_post_meta( (int) $pid, '_elementor_data', wp_slash( $raw ) );
		asa_pf_flush( (int) $pid );
	}
	if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
		\Elementor\Plugin::$instance->files_manager->clear_cache();
	}
	if ( function_exists( 'w3tc_flush_all' ) ) {
		w3tc_flush_all();
	}
	delete_option( 'asa_pf_done' );
	return array( sprintf( 'Undone: %d page(s) put back the way they were before the fix.', count( (array) $backup ) ) );
}

add_action( 'admin_menu', function () {
	add_management_page( 'Ascend Page Fix', 'Ascend Page Fix', 'manage_options', 'asa-page-fix', function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$log = null;
		if ( isset( $_POST['asa_pf'] ) && check_admin_referer( 'asa_pf' ) ) {
			$log = ( 'undo' === $_POST['asa_pf'] ) ? asa_pf_undo() : ( get_option( 'asa_pf_done' ) ? null : asa_pf_apply() );
		}
		$done = get_option( 'asa_pf_done' );
		echo '<div class="wrap"><h1>Ascend Page Fix</h1>';
		if ( $log ) {
			echo '<div class="notice notice-success"><ul style="list-style:disc;margin-left:20px">';
			foreach ( $log as $line ) {
				echo '<li>' . esc_html( $line ) . '</li>';
			}
			echo '</ul><p>Now clear the cache (Performance &rarr; Purge All Caches) and look at the pages.</p></div>';
		}
		if ( $done ) {
			echo '<p>The fix was applied on ' . esc_html( $done['time'] ) . '. If you don&rsquo;t like the result, undo it:</p>';
		} else {
			echo '<p>This puts the Home page back to its original design and rebuilds the sections added on Sept 23 '
				. '(About, Enrollment, Enhancements, Graduation, Shop, Blog, Time Card, Privacy Policy) as regular Elementor '
				. 'widgets that match the rest of the site: centered, blue headings, rounded buttons, white cards and light-green bands. '
				. 'You can undo it afterwards.</p>';
		}
		echo '<form method="post">';
		wp_nonce_field( 'asa_pf' );
		echo '<input type="hidden" name="asa_pf" value="' . ( $done ? 'undo' : 'apply' ) . '">';
		submit_button( $done ? 'Undo the page fix' : 'Apply the page fix', $done ? 'secondary' : 'primary' );
		echo '</form></div>';
	} );
} );
