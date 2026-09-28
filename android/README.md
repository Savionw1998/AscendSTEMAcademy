# Ascend STEM Academy Android app

The Play Store app is a Trusted Web Activity (a full-screen Chrome window on ascendstemacademy.com)
built with [Bubblewrap](https://github.com/GoogleChromeLabs/bubblewrap). `twa-manifest.json` is
Bubblewrap's whole configuration; Bubblewrap generates the Android project from it.

> **The app on Google Play is newer than this folder.** Versions 4–7 were built on the family's own
> computer from a customised copy of the Bubblewrap project (Claude Code session "Ascend app
> improvements"). Version 7 ("1.1.3") has been on the Closed testing → Alpha track since 2026-09-22.
> It adds an offline screen, a "Share app" shortcut and in-app updates, and its shortcuts are
> Dashboard, Time Card, Enroll and Share app. **Prefer updating that project:** change its shortcuts
> to the four below, give it versionCode 8 or higher, and build it there. That project contains
> hand-written Android code, so running `bubblewrap update` there would erase those additions.
> This folder is the plain Bubblewrap fallback without those extras. Its versionCode is 8, so Play
> accepts it after version 7.

This file was rebuilt from the published build `AscendSTEM-v3.apk` (Google Drive → "Ascend STEM
App"). It keeps everything that build had and changes only what was asked for:

| | AscendSTEM-v3 | This version |
|---|---|---|
| Package ID | `com.ascendstemacademy.twa` | same |
| Version | versionCode 3, "1.0.1" | **versionCode 8, "1.2.0"** (7 is already on Play) |
| Name / launcher name | Ascend STEM Academy / Ascend STEM | same |
| Opens | `https://ascendstemacademy.com/`, portrait | same |
| Colours | status and navigation bar `#009CDE`, splash `#FFFFFF` | same |
| Other | notifications on, Custom Tabs fallback, site-settings shortcut | same |
| Icon and splash screen | old logo | **new logo** |
| Long-press shortcuts | none | **Time Card, Students, Parents, Games** |

## Before you build

1. The website must already serve the new icons and shortcuts: install `ascend-app-sessions` 1.0.1 or
   newer, open any admin page once, then open <https://ascendstemacademy.com/manifest.json?check=1>.
   It should list the four shortcuts. (Bubblewrap downloads the icons from the website while it builds.)
2. **The signing key.** Google Play only accepts an update signed with the same upload key as the
   version it already has. Version 3 was signed on 2026-09-09 with a key whose SHA-256 fingerprint is
   `94:51:38:F0:F3:F5:D4:F9:BE:89:9B:59:5F:BE:21:FB:F4:76:B8:A6:83:D7:6E:EA:D0:11:1C:B9:25:7D:FB:E5`.
   Version 7 was signed with that same key on the family's computer, so it exists there, usually as a
   file called `android.keystore` in the Android project folder, with two passwords. To build from this
   folder, copy it here as `android.keystore`. If its alias is not `android`, change `"alias"` in
   `twa-manifest.json`.

   Keep the keystore and its passwords somewhere safe (not in this repository; `.gitignore` blocks it).

## Build

On any computer with Node.js 18 or newer:

```bash
npm install -g @bubblewrap/cli
cd android
bubblewrap update --skipVersionUpgrade   # first run offers to install Java and the Android SDK: answer yes
bubblewrap build                         # asks for the keystore password and the key password
```

`update` generates the Android project here from `twa-manifest.json` (launcher icon, splash screen,
shortcuts); it leaves this README, `twa-manifest.json` and your keystore alone. `--skipVersionUpgrade`
because the version is already set (8). `build` writes:

- `app-release-bundle.aab` → upload this to Google Play
- `app-release-signed.apk` → install this on a phone to try it first

Don't edit the generated files by hand: change `twa-manifest.json` and run `bubblewrap update` again.

## Upload to Google Play

Google Play takes the `.aab` file, not the `.apk`.

1. Play Console → the app → **Test and release → Testing → Closed testing → Alpha** (where version 7
   is) → **Create new release**. Check the **Testers** tab has an email list with the testers in it.
   Without one the release reaches nobody.
2. Upload `app-release-bundle.aab`. Release name: `1.2.0 (8)`. Release notes, for example:
   *Stay signed in, app shortcuts for Time Card, Students, Parents and Games, and a new icon.*
3. **Next → Save and publish** (or Start rollout). Install it on a phone from the testers' opt-in
   link and check: new icon; long-press the icon shows the four shortcuts; the app opens without a
   browser address bar.
4. Then **Production → Create new release → Add from library** (version 8) → **Next → Save and
   publish**, and send it for review.
5. Optional: the store listing icon `branding/play-store-icon-512.png` (branch
   `claude/google-play-console-logo-b3k8ln`) under **Grow users → Store presence → Main store listing**.

If the app opens with an address bar at the top, the site's `/.well-known/assetlinks.json` does not
list the key the installed app is signed with: add the **App signing key certificate** SHA-256 from
Play Console → App integrity → App signing (and, for a side-loaded test APK, the upload key's
fingerprint).

## Next time icons or shortcuts change

Change them on the website, then:

```bash
bubblewrap merge --appVersionName=<new version name> \
  --ignore name --ignore short_name --ignore display --ignore displayOverride \
  --ignore fullScopeUrl --ignore startUrl --ignore themeColor --ignore backgroundColor \
  --ignore monochromeIcons --ignore protocol_handlers --ignore file_handlers \
  --ignore launchHandlerClientMode
bubblewrap update --skipVersionUpgrade
bubblewrap build
```

`merge` copies the icons and shortcuts from the live web manifest into `twa-manifest.json` and raises
versionCode by 1; the `--ignore` flags keep every other setting as it is here.
