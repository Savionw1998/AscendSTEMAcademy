# Ascend STEM Academy Android app

The Play Store app is a Trusted Web Activity (a full-screen Chrome window on ascendstemacademy.com)
built with [Bubblewrap](https://github.com/GoogleChromeLabs/bubblewrap). `twa-manifest.json` is
Bubblewrap's whole configuration; Bubblewrap generates the Android project from it.

The original project folder was not saved anywhere I could find, so this file was rebuilt from the
published build `AscendSTEM-v3.apk` (Google Drive → "Ascend STEM App"). It keeps everything that
build had and changes only what was asked for:

| | AscendSTEM-v3 (published) | This version |
|---|---|---|
| Package ID | `com.ascendstemacademy.twa` | same |
| Version | versionCode 3, "1.0.1" | **versionCode 4, "1.1.0"** |
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
   - **You have it** (usually a file called `android.keystore`, plus its two passwords, on the computer
     that built version 3): copy it into this folder as `android.keystore`. If its alias is not
     `android`, change `"alias"` in `twa-manifest.json`.
   - **You don't:** in Play Console open the app → **App integrity** (in the left menu under *Test and
     release*) → **App signing** → **Request upload key reset**. Make a new key and its certificate:
     ```bash
     keytool -genkeypair -v -keystore android.keystore -alias android -keyalg RSA -keysize 2048 -validity 10000
     keytool -export -rfc -keystore android.keystore -alias android -file upload_certificate.pem
     ```
     upload `upload_certificate.pem` there, and build once Google confirms (usually within 2 days).
   - **Version 3 was never uploaded to Play:** any key works; make one with the first command above.

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
because the version is already 4. `build` writes:

- `app-release-bundle.aab` → upload this to Google Play
- `app-release-signed.apk` → install this on a phone to try it first

Don't edit the generated files by hand: change `twa-manifest.json` and run `bubblewrap update` again.

## Upload to Google Play

1. Play Console → the app → **Test and release → Testing → Internal testing → Create new release**.
2. Upload `app-release-bundle.aab`. Release name: `1.1.0 (4)`. Release notes, for example:
   *Stay signed in, app shortcuts for Time Card, Students, Parents and Games, and a new icon.*
3. **Next → Save and publish** (or Start rollout). Install it on a phone from the internal-testing
   link and check: new icon; long-press the icon shows the four shortcuts; the app opens without a
   browser address bar.
4. Then **Production → Create new release → Add from library** (version 4) → **Next → Save and
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
