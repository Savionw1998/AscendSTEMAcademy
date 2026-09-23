<?php
/**
 * Speed check: finds plugins that overlap or look unused, and lets the admin
 * deactivate them one at a time (reversible from the Plugins screen).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Map plugin Name => plugin file for everything installed. */
function asu_plugin_index() {
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$out = array();
	foreach ( get_plugins() as $file => $info ) {
		$out[ $info['Name'] ] = array( 'file' => $file, 'active' => is_plugin_active( $file ) );
	}
	return $out;
}

function asu_find_plugin( array $index, $needle ) {
	foreach ( $index as $name => $info ) {
		if ( false !== stripos( $name, $needle ) ) {
			return array_merge( array( 'name' => $name ), $info );
		}
	}
	return null;
}

/** Which published pages use widgets whose type starts with $prefix. */
function asu_pages_using_widget_prefix( $prefix ) {
	global $wpdb;
	$like = '%' . $wpdb->esc_like( '"widgetType":"' . $prefix ) . '%';
	$ids  = $wpdb->get_col( $wpdb->prepare(
		"SELECT pm.post_id FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id
		 WHERE pm.meta_key = '_elementor_data' AND pm.meta_value LIKE %s AND p.post_status = 'publish'",
		$like
	) );
	return array_map( 'intval', $ids );
}

function asu_page_links( array $ids ) {
	if ( ! $ids ) {
		return '<em>none</em>';
	}
	return implode( ', ', array_map( function ( $id ) {
		return '<a href="' . esc_url( get_permalink( $id ) ) . '" target="_blank" rel="noopener">' . esc_html( get_the_title( $id ) ) . '</a>';
	}, $ids ) );
}

function asu_speed_findings() {
	$idx      = asu_plugin_index();
	$findings = array();

	$updraft = asu_find_plugin( $idx, 'UpdraftPlus' );
	$wpvivid = asu_find_plugin( $idx, 'WPvivid' );
	if ( $updraft && $wpvivid && $updraft['active'] && $wpvivid['active'] ) {
		$findings[] = array( 'plugin' => $wpvivid, 'impact' => 'Medium', 'why' => 'Two backup plugins run side by side (UpdraftPlus and WPvivid). One is enough; keep UpdraftPlus and deactivate WPvivid after confirming UpdraftPlus has a recent backup.' );
	}

	$ue = asu_find_plugin( $idx, 'Unlimited Elements' );
	if ( $ue && $ue['active'] ) {
		$pages      = asu_pages_using_widget_prefix( 'ucaddon_' );
		$findings[] = array( 'plugin' => $ue, 'impact' => 'High', 'why' => 'Unlimited Elements widgets are used on: ' . asu_page_links( $pages ) . '. ' . ( $pages ? 'Replace these widgets before deactivating, or keep it.' : 'No published page uses it, so it is safe to deactivate.' ), 'html' => true, 'safe' => ! $pages );
	}

	$ea = asu_find_plugin( $idx, 'Essential Addons' );
	if ( $ea && $ea['active'] ) {
		$pages      = asu_pages_using_widget_prefix( 'eael-' );
		$findings[] = array( 'plugin' => $ea, 'impact' => 'High', 'why' => 'Essential Addons widgets are used on: ' . asu_page_links( $pages ) . '. ' . ( $pages ? 'Replace these widgets before deactivating, or keep it.' : 'No published page uses it, so it is safe to deactivate.' ), 'html' => true, 'safe' => ! $pages );
	}

	$gtm  = asu_find_plugin( $idx, 'GTM4WP' );
	$kit  = asu_find_plugin( $idx, 'Site Kit' );
	if ( $gtm && $kit && $gtm['active'] && $kit['active'] ) {
		$findings[] = array( 'plugin' => $gtm, 'impact' => 'Medium', 'why' => 'Google Tag Manager (GTM4WP) and Site Kit can both load Google Analytics, which doubles tracking scripts and can double-count visits. Keep Site Kit unless your Tag Manager container does more than analytics.' );
	}

	$ads = asu_find_plugin( $idx, 'Ad Inserter' );
	if ( $ads && $ads['active'] ) {
		$findings[] = array( 'plugin' => $ads, 'impact' => 'Low', 'why' => 'Ad Inserter is active. If the site shows no ads or injected code, it can go.' );
	}

	$bbp = asu_find_plugin( $idx, 'bbPress' );
	if ( $bbp && $bbp['active'] ) {
		$forums = (int) wp_count_posts( 'forum' )->publish;
		$topics = (int) wp_count_posts( 'topic' )->publish;
		$findings[] = array( 'plugin' => $bbp, 'impact' => 'Low', 'why' => sprintf( 'bbPress forums: %d published forum(s), %d topic(s).', $forums, $topics ) . ( ( $forums + $topics ) ? ' Keep it if families use the forums.' : ' Nothing is published, so it is safe to deactivate.' ), 'safe' => ! ( $forums + $topics ) );
	}

	$adc = asu_find_plugin( $idx, 'Advanced Database Cleaner' );
	if ( $adc && $adc['active'] ) {
		$findings[] = array( 'plugin' => $adc, 'impact' => 'Low', 'why' => 'Run one cleanup, then deactivate it. It is only needed occasionally.' );
	}

	$cf = asu_find_plugin( $idx, 'Cloudflare' );
	if ( $cf && ! $cf['active'] ) {
		$findings[] = array( 'plugin' => $cf, 'impact' => 'High', 'why' => 'The Cloudflare plugin is installed but not active. A free Cloudflare account puts a fast cache in front of the whole site. This one needs activating, not deactivating: do it from the Plugins screen and connect your account.', 'activate' => true );
	}

	return $findings;
}

add_action( 'admin_post_asu_deactivate', function () {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'asu_deactivate' );
	$file = isset( $_POST['plugin'] ) ? sanitize_text_field( wp_unslash( $_POST['plugin'] ) ) : '';
	$idx  = asu_plugin_index();
	$ok   = false;
	foreach ( $idx as $info ) {
		if ( $info['file'] === $file ) {
			$ok = true;
		}
	}
	if ( $ok && plugin_basename( ASU_DIR . 'ascend-site-updates.php' ) !== $file ) {
		deactivate_plugins( $file );
	}
	wp_safe_redirect( asu_admin_url( array( 'tab' => 'speed', 'asu_msg' => 'deactivated' ) ) );
	exit;
} );

function asu_render_speed_tab() {
	echo '<p class="asu-intro">Your mobile speed score is 36/100 (Google PageSpeed). Every active plugin adds code to every page, so the quickest wins are plugins that duplicate each other or are not used. Deactivating is reversible from the Plugins screen. Check the site after each one.</p>';
	echo '<table class="widefat striped asu-table"><thead><tr><th>Plugin</th><th>Impact</th><th>What I found</th><th></th></tr></thead><tbody>';
	foreach ( asu_speed_findings() as $f ) {
		echo '<tr><td><strong>' . esc_html( $f['plugin']['name'] ) . '</strong></td><td>' . esc_html( $f['impact'] ) . '</td><td>';
		echo empty( $f['html'] ) ? esc_html( $f['why'] ) : wp_kses_post( $f['why'] );
		echo '</td><td>';
		if ( ! empty( $f['activate'] ) ) {
			echo '<a class="button" href="' . esc_url( admin_url( 'plugins.php' ) ) . '">Go to Plugins</a>';
		} elseif ( $f['plugin']['active'] ) {
			$confirm = empty( $f['safe'] ) ? 'return confirm(\'This plugin may still be in use. Deactivate anyway?\');' : '';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="' . esc_attr( $confirm ) . '">';
			wp_nonce_field( 'asu_deactivate' );
			echo '<input type="hidden" name="action" value="asu_deactivate"><input type="hidden" name="plugin" value="' . esc_attr( $f['plugin']['file'] ) . '">';
			submit_button( 'Deactivate', 'secondary small', 'submit', false );
			echo '</form>';
		}
		echo '</td></tr>';
	}
	echo '</tbody></table>';
	echo '<h2>Also worth doing</h2><ul class="asu-list"><li>In W3 Total Cache, turn on Page Cache, Browser Cache and Minify (CSS and JS), then clear all caches.</li><li>Re-test at <a href="https://pagespeed.web.dev/analysis?url=https%3A%2F%2Fascendstemacademy.com%2F" target="_blank" rel="noopener">PageSpeed Insights</a> after each change.</li></ul>';
}
