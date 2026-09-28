# Ascend App Sessions

Site-side support for the Ascend STEM Academy Android app, which is a Trusted Web Activity (TWA)
around ascendstemacademy.com. Standalone plugin; nothing here edits Ultimate Member, the Ascend PWA
plugin or the theme.

## Keeping families signed in

WordPress logins last 2 days, or 14 with "Keep me signed in". Families open the app less often than
that, so shortcuts kept landing on /login/.

- **App detection.** The TWA opens the site with an `android-app://<package>` referrer, and inside
  the app the page matches `(display-mode: standalone)`. When either is true, a small script at the
  top of `<head>` sets the first-party cookie `asa_app=1` (1 year, `SameSite=Lax`, `Secure` on https,
  renewed on every app page load). PHP also sets it when it sees the referrer on an uncached request.
  The script is identical for every visitor, so it works on cached pages too.
- **90-day logins.** `auth_cookie_expiration` returns 90 days when `asa_app` is set and the account
  is a family account (`um_student`, the role every family account on the site has; UM's registration
  role is also `um_student`). Admins, editors, authors, contributors, shop managers and `um_faculty`
  keep WordPress's default, even when the account also has a family role.
- **Sliding renewal.** On `init`, when a family's app login has under 30 days left, the next page
  they open in the app re-issues it for 90 days. At most once a day (user meta
  `ascend_app_session_renewed`). The session token is kept and its expiry extended, so nonces
  already printed in open pages stay valid. Never on AJAX, REST, cron, admin or form posts.
- **Login form.** In the app, the Ultimate Member login form replaces "Keep me signed in" with an
  always-on hidden field, and sets `autocomplete="username"` / `autocomplete="current-password"`
  (UM prints `autocomplete="off"` on the username and nothing on the password). `assets/app.js`
  does the same in the browser for login pages served from the page cache.

Optional: define the app's package name in `wp-config.php` so only the app's own referrer counts
(otherwise any `android-app://` referrer does, e.g. a link opened from the Gmail app):

```php
define( 'ASCEND_APP_PACKAGE', 'com.example.package' ); // applicationId from the Android project
```

## W3 Total Cache: required settings

The plugin defines `DONOTCACHEPAGE` for app requests and logged-in requests (and sends no-cache
headers for app requests), so W3TC never stores those pages. W3TC serves pages it already stored *before* WordPress
loads, though, so it must also be told to skip them. In **Performance → Page Cache**:

1. General: **Don't cache pages for logged in users** stays ticked (W3TC's default).
2. Advanced → **Rejected cookies**: add a line `asa_app`.
3. Save, then **Performance → Purge All Caches**.

The plugin shows an admin notice until both settings are right. Purge again after deploying, so no
page stored before the plugin was active is served to the app.

## Install

Upload the `ascend-app-sessions` folder to `wp-content/plugins/` and activate it. Deactivating it
restores WordPress's default login lengths (existing 90-day logins run until they expire).

## Tests

```bash
php ascend-app-sessions/tests/plugin-test.php     # rules, with stand-ins for WordPress and W3TC
ascend-app-sessions/tests/browser/setup-site.sh    # throwaway WP 7.1.2 + UM 2.13.1 site on :8080
node ascend-app-sessions/tests/browser/step1.js    # needs Playwright (npm i -g playwright)
```

The browser tests use a real `display-mode: standalone` window (Chromium's `--app` mode) as the
stand-in for the TWA, and set `asa_app` by hand for the cookie cases.
