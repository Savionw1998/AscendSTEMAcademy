# Ascend App Sessions

Site-side support for the Ascend STEM Academy Android app, which is a Trusted Web Activity (TWA)
around ascendstemacademy.com. Standalone plugin; nothing here edits Ultimate Member, the Ascend PWA
plugin or the theme. The app itself is rebuilt from `android/` (see `android/README.md`).

## Keeping families signed in

WordPress logins last 2 days, or 14 with "Keep me signed in". Families open the app less often than
that, so shortcuts kept landing on /login/.

- **App detection.** The TWA opens the site with an `android-app://com.ascendstemacademy.twa`
  referrer (the published app's package ID; other apps' referrers do not count), and inside
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

If the package ID ever changes, set it in `wp-config.php` (`''` accepts any `android-app://` referrer):

```php
define( 'ASCEND_APP_PACKAGE', 'com.ascendstemacademy.twa' );
```

## Return to where you tapped

- **Restricted pages → login → back.** A logged-out visitor opening a page that Ultimate Member
  restricts with "Show access restricted message" (the "Restricted content" page) is sent to
  `/login/?redirect_to=<that page>` instead. Pages set to "Redirect user" are left to Ultimate
  Member, which already does this. Logged-in visitors without the right role still get the message,
  since sending them to the login page would loop.
- **After login.** Ultimate Member's login form carries the destination in a hidden `redirect_to`
  field and follows it before it looks at the form's "after login" setting or the
  `um_login_redirect_url` filter (`um_user_login()` in UM 2.13.1), so the plugin sets that field
  (`um_browser_url_redirect_to__filter`): the page the visitor was sent from if it is on this site
  and is not the login, logout, registration or password-reset page, otherwise the dashboard (`/user/`).
- **Dashboard while logged out.** `/user/` sends logged-out visitors to the login page and back,
  instead of to the home page.
- **Start URL.** In the app, a family that is already signed in and opens `/` goes to `/user/`.

Two settings on the live site:

- **Time Card (page 6097)** had its restriction switched off (public). On 2026-09-28 it was switched
  back on, as agreed: logged-in Student, Faculty and staff accounts only, and "What happens when users
  without access try to view the post?" = **Redirect user → Login page**. So Ultimate Member itself
  sends logged-out visitors to `/login/?redirect_to=…/time-card-tracker/`, even without this plugin,
  and the login form (above) brings them back. To make it public again: Pages → Time Card →
  Ultimate Member: Content Restriction → untick "Restrict access to this post?".
- **Login form 4414** has "Redirection after Login" = "Redirect to profile". The plugin decides the
  destination, so this setting no longer matters; set it to "Redirect to URL" with
  `https://ascendstemacademy.com/user/` if you want the admin screen to match what happens:
  `wp post meta update 4414 _um_login_after_login redirect_url && wp post meta update 4414 _um_login_redirect_url https://ascendstemacademy.com/user/`

## App shortcuts and icons

The icons are in `assets/icons/`, cut from the new logo (`branding/logo-source-1024.png`) by
`tools/make-icons.py`:

| File | Use |
|---|---|
| `icon-192.png`, `icon-512.png` | App icon (`purpose: any`); Bubblewrap also makes the splash screen from the 512 |
| `icon-maskable-512.png` | Full-bleed app icon (`purpose: maskable`); becomes the Android adaptive launcher icon |
| `shortcut-time-card[-maskable].png` | Time Card → `/time-card-tracker/` (maths symbols, blue) |
| `shortcut-students[-maskable].png` | Students → `/user/` (graduation cap, blue) |
| `shortcut-parents[-maskable].png` | Parents → `/account/` (gear, green) |
| `shortcut-games[-maskable].png` | Games → `/guess-a-lotl/` (atom, green) |

Each shortcut has a 96×96 round icon (`any`) and a 96×96 full-bleed one (`maskable`): Bubblewrap
uses the maskable one for the launcher shortcut and needs the `any` one for older Android versions.

**Nothing to change in the Ascend PWA plugin.** It provides the manifest at
`https://ascendstemacademy.com/manifest.json` (the URL the published app was built from; Chrome only
updates installed copies of the app from the same URL). Its source is not in this repository, so this
plugin handles both ways that URL can be answered, and keeps every other field of the manifest as
the PWA plugin set it:

- **By WordPress** (the PWA plugin answers the request, from a hook or straight from its own main
  file): from the moment this plugin loads, which is before `ascend-pwa` since plugins load in
  alphabetical order, the response is buffered, and its `icons` and `shortcuts` are replaced on the
  way out. The old `Content-Length`/`ETag`/`Last-Modified` are dropped, a browser's old ETag never
  gets a "304 Not Modified", and the response is never page-cached.
- **As a real `manifest.json` file** in the site's root folder (the web server sends it without
  running WordPress): on every admin page, the file's `icons` and `shortcuts` are updated if they
  differ, so this also catches the PWA plugin writing its own version again later. The first time,
  the original is saved as `manifest.json.before-ascend-app`. If the file cannot be written, an admin
  notice says so.

On ascendstemacademy.com it is the second case: a real file, first updated on 2026-09-28 by version 1.0.1.

What happened is recorded in the option `ascend_app_manifest_status`: `served_by_wordpress` (time of
the last such request, at most hourly) and `static_file` (`none`, `updated`, `up to date`,
`not writable` or `not a web manifest`). The first admin page after installing or updating the plugin
also empties W3 Total Cache's page cache once (`ascend_app_flushed_version`), so no copy stored before
is served again. The same arrays are available to any code as `apply_filters( 'ascend_app_manifest', $manifest )`.

To check the live manifest, add any query string, e.g. `https://ascendstemacademy.com/manifest.json?check=1`:
the PWA plugin's service worker and page caches key on the exact URL, so this shows what the server
sends now.

The Android app is rebuilt from `android/twa-manifest.json`; see `android/README.md`.

## App mode UI

`assets/app.css` and `assets/app.js`. Every rule is inside `@media (display-mode: standalone)` and
the tab bar is only added in a standalone window, so the website looks exactly as before, including
in a browser tab that carries the `asa_app` cookie.

- **Bottom tab bar:** Dashboard (`/user/`), Time Card (`/time-card-tracker/`), Games
  (`/guess-a-lotl/`, current on every game page) and Account (`/account/`). Change it with the
  `ascend_app_tabs` filter.
- **Header:** Astra's transparent header floats over the page (`position: absolute`). In the app it
  sits in the page flow instead, solid white and 56px tall, with a 40px-tall logo, and the Header
  menu (desktop and mobile) and its menu button are hidden.
- **Floating-header workarounds:** with the header in the flow, the Customizer CSS rules that push
  content below it (`#content` top padding on posts, games, Time Card and the login/account pages;
  `.asa-hero` / `.asa-store-hero` top margin; the Time Card container's 80px Elementor margin) are
  reset to a 16px gap in the app. On the website they still apply, because there the header still
  floats.
- **Login page:** the Register button is hidden and Login takes the full width.
- Astra's scroll-to-top button and the side cart's basket button are lifted above the tab bar.

## W3 Total Cache: required settings

The plugin defines `DONOTCACHEPAGE` for app requests and logged-in requests (and sends no-cache
headers for app requests), so W3TC never stores those pages. W3TC serves pages it already stored
*before* WordPress loads, though, so it must also be told to skip them. In **Performance → Page Cache**:

1. General: **Don't cache pages for logged in users** stays ticked (W3TC's default).
2. Advanced → **Rejected cookies**: add a line `asa_app`.
3. Save, then **Performance → Purge All Caches**.

The plugin shows an admin notice until both settings are right. Purge again after deploying, so no
page stored before the plugin was active is served to the app.

## Install

Upload the `ascend-app-sessions` folder to `wp-content/plugins/` and activate it (to make a zip
without the tests: `zip -r ascend-app-sessions.zip ascend-app-sessions -x 'ascend-app-sessions/tests/*' 'ascend-app-sessions/tools/*'`).
Deactivating it restores WordPress's default login lengths (existing 90-day logins run until they
expire) and removes the app UI; the PWA plugin's filter line then changes nothing.

## Deploy and check on the live site

1. Activate the plugin. Set the two W3 Total Cache settings (above), save, purge all caches.
2. Optional: the form 4414 setting (above). (The Time Card restriction is already on.)
3. Check in desktop Chrome, before touching the Android build:
   - DevTools console on ascendstemacademy.com:
     `document.cookie = "asa_app=1; Max-Age=31536000; Path=/; SameSite=Lax; Secure"`, then open
     `/login/`: no "Keep me signed in"; log in with a family (Student) test account; DevTools →
     Application → Cookies: `wordpress_logged_in_…` expires in about 90 days. Open `/`: you land on
     `/user/`. Log out, open `/time-card-tracker/`: login, then back.
   - App look: open the site in a standalone window, e.g. `google-chrome --app=https://ascendstemacademy.com/guess-a-lotl/`
     (or Chrome menu → Cast, save and share → Install page as app): tab bar, solid header, no menu,
     no Register button. A normal tab must look exactly as before.
4. Open any admin page once (it updates a static `manifest.json` and empties the page cache), then
   `https://ascendstemacademy.com/manifest.json?check=1`: four shortcuts (DevTools → Application →
   Manifest: no errors).
5. Rebuild the Android app and upload it to Play: `android/README.md`.

## Tests

```bash
php ascend-app-sessions/tests/plugin-test.php     # rules, with stand-ins for WordPress and W3TC
ascend-app-sessions/tests/browser/setup-site.sh    # throwaway WP 7.1.2 + UM 2.13.1 site on :8080
node ascend-app-sessions/tests/browser/step1.js    # needs Playwright (npm i -g playwright)
node ascend-app-sessions/tests/browser/step2.js
node ascend-app-sessions/tests/browser/step3.js    # /manifest.json: a stand-in Ascend PWA plugin, served 3 ways
node ascend-app-sessions/tests/browser/step4.js    # app-mode UI vs the website
```

The browser tests use a real `display-mode: standalone` window (Chromium's `--app` mode) as the
stand-in for the TWA, and set `asa_app` by hand for the cookie cases.
