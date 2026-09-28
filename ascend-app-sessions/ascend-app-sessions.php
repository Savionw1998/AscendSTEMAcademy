<?php
/**
 * Plugin Name:       Ascend App Sessions
 * Plugin URI:        https://ascendstemacademy.com/
 * Description:       Site-side support for the Ascend STEM Academy Android app: 90-day renewing logins for families in the app, logins that return to the page that asked for them, the app's shortcut icons, and an app-only tab bar and header.
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
 * RETURN TO WHERE YOU TAPPED
 *
 *   - Logged-out visitors to a page Ultimate Member restricts go to
 *     /login/?redirect_to=<page> instead of the "Restricted content" message,
 *     and the login form sends them back there (else to the /user/ dashboard).
 *   - In the app, a signed-in family opening the start URL (/) goes to /user/.
 *
 * APP ICONS AND SHORTCUTS
 *
 *   assets/icons/ holds the manifest icons and the four shortcut icons, cut from
 *   the logo by tools/make-icons.py. The Ascend PWA plugin takes them with
 *   $manifest = apply_filters( 'ascend_app_manifest', $manifest );
 *
 * APP MODE UI (assets/app.css, assets/app.js)
 *
 *   Only inside the app, (display-mode: standalone): a bottom tab bar, a solid
 *   compact header in the page flow without the marketing menu, and no Register
 *   button on the login page. The website is unchanged.
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
require_once __DIR__ . '/includes/redirects.php';
require_once __DIR__ . '/includes/manifest.php';
