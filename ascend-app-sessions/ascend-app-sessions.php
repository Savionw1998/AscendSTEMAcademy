<?php
/**
 * Plugin Name:       Ascend App Sessions
 * Plugin URI:        https://ascendstemacademy.com/
 * Description:       Keeps families signed in to the Ascend STEM Academy Android app. Detects app sessions, gives family accounts 90-day logins that renew while they keep using the app, and makes the Ultimate Member login form remember them.
 * Version:           1.0.0
 * Requires at least: 6.3
 * Requires PHP:      8.0
 * Author:            Ascend STEM Academy
 * License:           GPL-2.0-or-later
 * Text Domain:       ascend-app-sessions
 *
 * ---------------------------------------------------------------------------
 * HOW THE SITE KNOWS IT IS INSIDE THE APP
 *
 *   The Android app is a Trusted Web Activity. It opens the site with an
 *   android-app://<package> referrer, and inside it the page matches
 *   (display-mode: standalone). When either is true, a tiny script at the top
 *   of <head> sets the first-party cookie asa_app=1 (1 year, SameSite=Lax,
 *   Secure). PHP also sets it when it sees the referrer on an uncached request.
 *   The script is the same for every visitor, so page caching cannot break it.
 *
 *   Set ASCEND_APP_PACKAGE in wp-config.php to the app's package name to only
 *   trust that app's referrer; left undefined, any android-app:// referrer counts.
 *
 * WHAT APP SESSIONS GET
 *
 *   - Family accounts (role um_student) signed in from the app get a 90-day
 *     login instead of 2 or 14 days. Admins, editors, authors, shop managers
 *     and um_faculty keep WordPress's default.
 *   - Sliding renewal: once the login has under 30 days left, the next page the
 *     family opens in the app extends it to 90 days again (at most once a day).
 *   - The Ultimate Member login form ticks and hides "Keep me signed in" and
 *     tells password managers which fields are the username and password.
 *
 * CACHING (W3 Total Cache)
 *
 *   App requests and logged-in requests define DONOTCACHEPAGE (app requests
 *   also send no-cache headers), so no cached copy is stored for them. W3TC serves
 *   cached pages before WordPress loads, so it must also be told to skip them:
 *   Performance > Page Cache > Advanced > "Rejected cookies" must contain
 *   asa_app, and "Don't cache pages for logged in users" must stay on.
 *   This plugin shows an admin notice until both are true.
 * ---------------------------------------------------------------------------
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ASCEND_APP_VERSION', '1.0.0' );
define( 'ASCEND_APP_URL', plugin_dir_url( __FILE__ ) );
define( 'ASCEND_APP_COOKIE', 'asa_app' );

require_once __DIR__ . '/includes/app-mode.php';
require_once __DIR__ . '/includes/sessions.php';
require_once __DIR__ . '/includes/login-form.php';
