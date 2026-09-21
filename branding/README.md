# Ascend STEM Academy — app branding assets

## Files

| File | Use |
| --- | --- |
| `logo-source-1024.png` | Master logo, 1024×1024, transparent background. Regenerate other sizes from this. |
| `play-store-icon-512.png` | Google Play Console **App icon**. 512×512, 32-bit PNG, no alpha. |
| `android-launcher/ic_launcher-*.png` | Legacy launcher icons, one per density (mdpi 48 → xxxhdpi 192). |

The Play Store icon has its transparent corners flattened to white, because Play
rejects icons with an alpha channel. Play applies its own rounded mask, so the
white corners are not visible in the store.

## Uploading to Google Play Console

Uploading is a manual step in the Play Console web UI:

1. Play Console → select **Ascend STEM Academy**.
2. **Grow users → Store presence → Main store listing**.
3. Under **App icon**, replace the current image with `play-store-icon-512.png`.
4. **Save**, then **Send for review**. The new icon goes live once the listing
   update is approved (usually a few hours to a couple of days).

Changing the store listing icon does **not** change the icon on installed
devices — that comes from the launcher icons inside the app bundle.

## Using the launcher icons in the Android app

When the Android project lands in this repo, copy each density into its mipmap
folder and name it `ic_launcher.png`:

```
app/src/main/res/mipmap-mdpi/ic_launcher.png     <- ic_launcher-mdpi-48.png
app/src/main/res/mipmap-hdpi/ic_launcher.png     <- ic_launcher-hdpi-72.png
app/src/main/res/mipmap-xhdpi/ic_launcher.png    <- ic_launcher-xhdpi-96.png
app/src/main/res/mipmap-xxhdpi/ic_launcher.png   <- ic_launcher-xxhdpi-144.png
app/src/main/res/mipmap-xxxhdpi/ic_launcher.png  <- ic_launcher-xxxhdpi-192.png
```

Then bump `versionCode`, rebuild the app bundle and upload a new release — the
launcher icon only updates for users who install that build.
