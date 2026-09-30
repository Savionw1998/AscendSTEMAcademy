# ascendstemacademy.com site audit (29 Sep 2026)

Scope: every published page, payment gateways, email routing, the withdrawal letter
generator, the family dashboard, and the plugin list. The site was inspected through
the Royal MCP connector plus the school Gmail inbox; the site's HTTP front end and plugin
source files are not reachable from this environment, so anything marked "could not
verify" needs a quick look in wp-admin.

## 1. Changes made during the audit

| Where | What changed | Why |
|---|---|---|
| Enrollment Agreement page (ID 4448) | Removed the stale Elementor "builder" flag | The page had been rebuilt in the block editor on 23 Sep, but Elementor was still serving the 2024 centred-text version. The rebuilt page (with the h1 "Ascend STEM Academy Enrollment Agreement") is now live. This is also the WooCommerce terms page linked from checkout. |
| Shop page (ID 3477) | "Secure checkout with WooPayments or PayPal" → "Secure checkout with WooPayments: all major cards, Apple Pay, Google Pay, Klarna and Afterpay" | PayPal is not installed (see §3). |
| Privacy Policy (ID 4897) | "Payments are handled by our payment processors (WooPayments and PayPal)" → "(WooPayments)" | Same reason. |
| Checkout page (ID 3517) | Removed the orphaned `woocommerce-paypal-payments/checkout-paylater-messages` block | The PayPal Payments plugin was uninstalled; the block was unregistered. |

Undo tokens (valid 72 h, use `mcp_undo_last_operation`): privacy policy data `4e555de5f7fb1facfcdf36872a20949c`.

## 2. Email routing

Everything the site sends goes through WP Mail SMTP (Gmail SMTP, account
ascendstemacademy@gmail.com, "force from" on), so every outbound message lands in the
school's Sent folder. Checked in Gmail: time cards, time-card parent copies, Ultimate
Member account emails, WordPress login/password emails, living-transcript nudges,
re-enrollment reminders and lead notifications all go out and arrive.

| Setting | Current value | Status |
|---|---|---|
| WordPress admin email | ascendstemacademy@gmail.com | OK |
| WP Mail SMTP from / SMTP user | ascendstemacademy@gmail.com | OK |
| WooCommerce "From" address | ascendstemacademy@gmail.com | OK |
| WooCommerce new-order recipient | not overridden → admin email | OK |
| Jetpack contact form (Contact page) | AscendStemAcademy@gmail.com | OK |
| Lead Capture notify email | ascendstemacademy@gmail.com | OK |
| Ultimate Member admin email | ascendstemacademy@gmail.com | OK |
| Discovery Wheel admin email, PWA push contact, Sentinel notify | ascendstemacademy@gmail.com | OK |
| **WooCommerce low-stock / out-of-stock recipient** | **winstonsavion@gmail.com** | **Fix: WooCommerce → Settings → Emails → Recipient (stock notifications)** |
| **WooPayments "communications email"** | **winstonsavion@gmail.com** (account email is ascendstemacademy@gmail.com) | **Fix: Payments → Settings → Account details / Stripe Express dashboard → contact email** |
| **Legacy "PayPal Standard" gateway (disabled)** | email + receiver_email = winstonsavion@gmail.com | Clear or delete: WooCommerce → Settings → Payments → PayPal (legacy). Disabled, so no money flows there, but it should not carry a personal address. |
| Re-enrollment roster entry "Banger Aston Winston" | guardian email winstonsavion@gmail.com | Test data in the roster; remove if not a real student. |

The WooCommerce options above are not in the connector's write allowlist, so they must
be changed in wp-admin.

## 3. Payments

* **WooPayments (live)**: account `acct_1QVZ1cCQ6JALpkSO`, status complete, payments
  enabled, daily payouts to Wells Fargo ••••6124, statement descriptor "ASCEND STEM
  ACADEMY", lifetime volume $2,395. Enabled methods: cards, Apple Pay, Google Pay,
  Klarna, Afterpay, Amazon Pay, Link. Affirm was rejected by the processor (leave it off).
* **PayPal is not available at checkout.** The PayPal Payments plugin was uninstalled
  (its settings show onboarding never completed), and the legacy PayPal Standard gateway
  is disabled. Three pages still promised PayPal; two are fixed (§1). Two remain because
  they live inside Elementor HTML widgets that include inline scripts/styles the
  connector would strip:
  * Enrollment page (ID 2781): "Payment method (PayPal, Visa, Mastercard, Discover, or
    American Express)" → suggest "(Visa, Mastercard, Discover, American Express, Apple Pay
    or Google Pay)".
  * Why Choose page (ID 5436): "Payments are handled by WooPayments and PayPal." →
    "Payments are handled by WooPayments."
  If you want PayPal back instead, install and connect "WooCommerce PayPal Payments" and
  the copy can stay.
* **Coupons are globally disabled** (`woocommerce_enable_coupons = no`, WooCommerce →
  Settings → General → "Enable the use of coupon codes"). WooCommerce refuses to apply
  any coupon while this is off, so the WELCOMEBACK10 re-enrollment coupon, FRIEND-xxxx
  referral codes, THANKS credits, the Discovery Wheel "$5/$10 off" prizes, and the
  FES-UA and military discount codes you email out cannot work at checkout unless the
  Ascend Re-Enrollment plugin forces them on. Turn coupons on and test one code. (The
  10 % sibling discount is separate and applied by the enrollment plugin.)
* **Point-of-sale store address** reads "Ocala, CA 34479" (WooCommerce → Settings →
  Point of Sale). Should be FL.
* **Refund / returns page** is set to page ID 11, which no longer exists. Either create a
  short "Store policies" page (shipping, returns, no tuition refunds after withdrawal)
  or clear the setting.
* Orphaned settings from uninstalled gateways (Stripe in test mode, PayPal Payments)
  are harmless but can be deleted with a database cleaner later.
* Product prices match the page copy everywhere: K-8 $185/$165, 9-12 $200/$175,
  transcript $15, progress report $20, ID $25, resume reviews $30/$75/$125, graduation
  packet $125, sticker pack $15, Pond Key $1, Full Pond Pass $19.99.

## 4. Withdrawal letter generator (Enrollment page)

Root cause found: the generator is a purely client-side tool. Its code (inside the
Enrollment page's Elementor HTML widget) builds the letter in the browser and offers
Print and Copy. It has no email field, no request to the server, and no hook into the
Ascend Lead Capture plugin. The Compliance Wizard works differently: Lead Capture
injects its own "email me a copy" step there, which is why the school gets
"[Lead] Compliance Wizard …" notifications and parents get "Your copy from Ascend STEM
Academy" (both confirmed in Gmail on 25 Sep).

Evidence: in 30 days of mail (including Trash) there is not one lead email with the
source "Withdrawal Letter Generator", and the four stored leads are all Compliance
Wizard or Resource Hub Gate. So nothing is "failing" in transit; the generator never
sends.

Fix options (needs the plugin code, which this environment cannot read):

1. In `ascend-lead-capture`, make the generator hook target the current markup:
   after `#generateBtn` runs, show the same email form used by the wizard, then send
   `source = "Withdrawal Letter Generator"` with the letter text from `#letterPaper` so the
   plugin emails the school (notify_email) and the parent (email_parent is on).
2. Or add an "Email me this letter" field directly in the generator HTML and post to
   the Lead Capture endpoint.

Until then, tell families to use Print / Copy.

## 5. Page-by-page check

All 30 published pages and the product/category pages return HTTP 200 with the expected
title and h1. Login-gated pages (/user/, /account/, /time-card-tracker/, the six games,
/enrollment-form/) correctly show the login page to visitors. Findings:

* Enrollment Agreement was serving stale Elementor content (fixed, §1).
* /posts/ is the blog index (page_for_posts), so the leftover `[bdp_post]` shortcode in
  its page content is never shown. Harmless; the Elementor "Blog intro" data on that page
  is also unused.
* /shop/ renders its block-editor content as the shop description; the Elementor data on
  that page (intro + `[products]` shortcodes) is unused.
* Resource Explorer is gated by Ascend Resource Gate (email required), as intended.
* Old page content copies (`post_content`) on Elementor pages are stale search copies;
  Elementor renders the widget data, so they can be ignored.
* Stale leftovers I could not trash (permission): draft page "Tickets Checkout" (ID 4124,
  from The Events Calendar), Elementor template "The Events Calendar – Starter" (ID
  4093), and two unused Elementor "Default Kit" posts (IDs 5 and 2629; the active kit is
  2730). Also ~20 inactive placeholder widgets ("Demo St, Brooklyn", "mail@example.com")
  under Appearance → Widgets → Inactive.

## 6. Family dashboard (/user/) and logged-in menu

The dashboard page is an Elementor shortcode widget:
`[ascend_family_dashboard um_form="4300"] … announcements … [/ascend_family_dashboard]`.
The announcements list is all Florida testing / financial-aid dates; none of Ascend's own
requirements appear. Parents need three things the public site states elsewhere:

* Attendance is collected at the end of September, December, March and June
  (Time Card page and FAQ).
* 180 days of instruction per enrollment year (FAQ, Enrollment Agreement).
* Re-enrollment falls on each child's enrollment anniversary; reminders go out 60/30/7
  days before (Re-Enrollment plugin).

I prepared the three items but the write was blocked by the session's permission
classifier. To add them, edit the page in Elementor, open the shortcode widget, change
the count `15` to `18`, and paste this right after `<div class="ascend-ann-body">`:

```html
<div class="ascend-ann-item"><p class="ascend-ann-title">Attendance Collection</p><div class="ascend-ann-meta"><span class="ascend-ann-when">End of September, December, March and June</span><span class="ascend-ann-tag">Ascend Requirement</span></div><p class="ascend-ann-text">Ascend collects attendance four times a year. Log your school hours as you go on your <a href="https://ascendstemacademy.com/time-card-tracker/">Time Card</a> so it is current before each collection date.</p></div>
<div class="ascend-ann-item"><p class="ascend-ann-title">180 Days of Instruction</p><div class="ascend-ann-meta"><span class="ascend-ann-when">Every enrollment year</span><span class="ascend-ann-tag">Ascend Requirement</span></div><p class="ascend-ann-text">Ascend students complete 180 days of instruction per school year. That means 180 school days, not calendar days. Your time card keeps the count for you.</p></div>
<div class="ascend-ann-item"><p class="ascend-ann-title">Re-Enrollment</p><div class="ascend-ann-meta"><span class="ascend-ann-when">Your enrollment anniversary</span><span class="ascend-ann-tag">Ascend Requirement</span></div><p class="ascend-ann-text">Each child&rsquo;s school year runs 12 months from the day you enrolled. Re-enrollment reminders are emailed 60, 30 and 7 days before the anniversary. Re-enroll by that date to keep records and private-school status continuous.</p></div>
```

Other observations:

* Logged-in menu: Home, Dashboard, My Account (→ /account/), Time Card, Resources, Games
  (6), Log Out. There is no direct link to Enhancements (reports, IDs, transcripts) or to
  Contact. If the dashboard's quick actions do not cover them, add them to the menu.
* Two pages are called "My Account": the Account Hub (/account/) and the WooCommerce
  orders page (/my-account/). Renaming the WooCommerce page to "Orders" would remove the
  confusion.
* A parent (28 Sep) reported the dashboard showing enrollment documents as missing even
  though they were on file. Worth checking the enrollment status panel's document logic.
* Cron shows hourly "ase_drive_retry" and "ase_tc_retry" jobs from the enrollment
  plugin; if the Google Drive bridge is failing, its retry queue may be growing.

## 7. Plugins: what is needed, what is not

45 active plugins, 47 installed. Jetpack Boost's own speed score fell from 79 / 95
(mobile / desktop, 2024) to 24 / 34 (Sep 2026); pages ship 117–194 scripts and ~50
stylesheets. Trimming the list below is the biggest performance win available.

### Remove now (unused or redundant)

| Plugin | Reason |
|---|---|
| bbPress (inactive) | Forums are gone; delete. Users still carry a harmless `bbp_participant` role. |
| Cloudflare (inactive) | Not in use; delete. |
| Unlimited Elements for Elementor | No widgets from it exist on any page. |
| EmbedPress | No embeds or blocks from it anywhere; the About video is a plain iframe. |
| Image Optimization (Elementor) | Never configured. |
| WPvivid Backup | Duplicate of UpdraftPlus (which backs up daily to Google Drive, 7 kept). WPvivid keeps 3 × ~1 GB local copies in wp-content/wpvividbackups. Delete the plugin and that folder. Also delete `uploads/wp-migrate-db/ascendstemacademy-20260910212923-wezpj.zip` (500 MB), which UpdraftPlus flags every night. |
| WooCommerce.com Update Manager | Only needed for paid Woo.com extensions; none installed. |
| Hide/Remove Metadata | Hides author/date on posts by CSS; Astra does this natively (Customizer → Blog → Single Post → Meta). |
| The HostGator Plugin | Host upsell/telemetry; runs a cron every minute. Safe to deactivate. |
| Advanced Database Cleaner, WP Crontrol | Admin utilities; keep installed but deactivate until needed. |

### Consolidate

| Plugin | Note |
|---|---|
| GTM4WP **and** Site Kit Tag Manager | Both inject container GTM-M5HLBZC4, so the container loads twice. Keep one (GTM4WP is simpler). Site Kit also injects GA4 (G-7E29475PLK) directly; if the GTM container already fires GA4, page views are double-counted. |
| Social Media and Share Icons | Only used for share buttons under posts and a pop-up on the blog page. Jetpack's Sharing module does the same with no extra plugin. |
| Side Cart WooCommerce | "Redirect to cart after add" is also on, which fights the fly-out cart. Pick one behaviour. |
| Akismet | Comments are effectively unused (none on the site). If comments stay closed, Akismet can go. |

### Keep

Ascend plugins (10), WooCommerce + WooPayments + Shipping + Tax, Ultimate Member,
Jetpack (contact form, image CDN, WooPayments connection), WP Mail SMTP, Wordfence,
UpdraftPlus, ThinkRank, Site Kit (Search Console / AdSense / Ads), Ad Inserter (5 AdSense
blocks), Widgets for Google Reviews (home page), Redirection, GTranslate, WPFront
Notification Bar, W3 Total Cache, Code Snippets (review its snippets: this environment
cannot read them), Royal MCP.

### Do we still need Astra and Elementor?

* **Astra**: yes, for now. It is the theme, its header/footer builder provides the
  header (logo, menu, account icon) and footer, and the Astra Child theme (v1.1.0,
  "Ascend STEM Academy") holds custom code. Replacing it means a new theme plus rebuilding
  header/footer and re-checking ~110 KB of custom CSS. Low payoff.
* **Elementor**: can be retired, but it is a project, not a toggle. Sixteen pages carry
  the Elementor flag; the ones actually rendered by Elementor are Home, Enrollment,
  About, Enhancements, Graduation, FAQ, Privacy Policy, Resources, Resource Explorer,
  Why Choose, User, Account, Login, Registration and Password Reset. Almost all of their
  content is hand-written HTML in single Elementor HTML widgets (the "design v2"
  pages), so each converts to a block-editor Custom HTML block by copying the widget
  contents. Only three real Elementor dependencies remain: the Home hero (native
  image/heading/button widgets + the Trustindex reviews shortcode), the Essential Addons
  post grid at the bottom of the Enrollment page, and the EA "vertical text" extension
  settings sprinkled on widgets (cosmetic). Once those pages are converted, Elementor,
  Essential Addons and Unlimited Elements (three plugins plus their CSS/JS) can be
  removed, and the ~127 Elementor-specific rules in the custom CSS cleaned up.

## 8. Security items to check in wp-admin

The Ascend Sentinel scan from 7 Sep still lists two items that look like an injected
loader rather than a plugin drop-in. They were both written at 2026-09-07 15:10:19 UTC:

* `wp-content/db.php` (211 KB, base64-heavy, no vendor header). A real W3 Total Cache
  db.php drop-in is about 2 KB and names the plugin at the top.
* `wp-content/.sc_2372c1d3/core_d9b7c04b.php` (438 KB) plus an `index.php` in a hidden
  directory.

Open them in the HostGator file manager (or run a fresh Wordfence scan and read its
results). If they are not from a plugin you recognise, remove them and rotate passwords.
`advanced-cache.php2` and `object-cache.php2` are old W3TC drop-ins renamed on
2025-01-07 and can simply be deleted. `mu-plugins-/` is a renamed backup of the old
mu-plugins folder and can go too.

## 9. Manual checklist (things this connector could not change)

1. WooCommerce → Settings → Emails: set stock-notification recipient to
   ascendstemacademy@gmail.com.
2. Payments → Settings: change the WooPayments communications email from
   winstonsavion@gmail.com to ascendstemacademy@gmail.com.
3. WooCommerce → Settings → Payments → PayPal (legacy): remove
   winstonsavion@gmail.com or delete the gateway settings.
4. WooCommerce → Settings → General: enable coupon codes, then test WELCOMEBACK10 in a
   cart.
5. WooCommerce → Settings → Point of Sale: fix "Ocala, CA".
6. WooCommerce → Settings → Advanced: set or clear the Refund/Returns page (ID 11 is gone).
7. Elementor edits: PayPal wording on the Enrollment and Why Choose pages; announcements
   block on /user/ (HTML in §6).
8. Trash: page 4124, template 4093, Elementor kits 5 and 2629; inactive widgets.
9. Plugin clean-up per §7; then re-run Jetpack Boost's speed score.
10. Check §8 files.
11. Ascend Lead Capture: wire the withdrawal letter generator (§4).

## 10. Status after fixes (30 Sep 2026)

The fix plan (`docs/fix-plan-2026-09-29.md`) was carried out by a Claude Code session on
30 Sep 2026, 08:05–08:50 EDT, through the Royal MCP connector and the owner's logged-in
wp-admin. **No shell was available.** Claude Code's permission classifier blocked three things:
opening the cPanel Terminal and reading plugin source, adding one of the two menu items, and
deleting the plugins. Every step that needed them is listed under "Left for the owner" with
the exact commands or clicks.

**Backup first:** UpdraftPlus "Before fix plan 2026-09-29" (nonce `7f639ee53391`) completed
before any change. It holds the database plus plugins, themes, uploads, others and mu-plugins,
and is stored in Google Drive.

### Task by task

| Task | Result | Notes |
|---|---|---|
| 1.1 Stock-notification recipient | **Done** | Was winstonsavion@gmail.com, now ascendstemacademy@gmail.com (WooCommerce → Settings → Products → Inventory). Saved and re-read. |
| 1.2 Legacy PayPal gateway email | **Left for owner** | WooCommerce no longer shows the PayPal Standard settings screen. The connector can't read or write the option either. Needs WP-CLI (below). The gateway stays disabled. |
| 1.3 WooPayments communications email | **Left for owner** | Only the account owner can change it. |
| 1.4 Coupons | **Done, tested** | Was off, now on. The coupon list has welcomeback10 (no expiry), friend-euvf, friend-hjtg, fesua-7k2m, salute-4m8q and spin-9xr1kv. As a logged-out guest, K-8 Re-Enrollment (4463) with WELCOMEBACK10 went from $165.00 to $155.00. |
| 1.5 POS store address | **Done** | Was "3200 NE 29th CT / Ocala, CA 34479", now "3200 NE 29th CT / Ocala, FL 34479". Re-read after reload. |
| 1.6 Store Policies page | **Partly done** | Draft page **7619 "Store Policies"** holds the FAQ no-refund sentence and the "5–7 business days" line. Merch returns now read "All merchandise sales are final. We do not accept returns." (owner's wording, 30 Sep). Published 30 Sep at the owner's request: https://ascendstemacademy.com/store-policies/ (HTTP 200). Clearing `woocommerce_refund_returns_page_id` needs WP-CLI. The field isn't in WooCommerce → Settings → Advanced. |
| 1.7 PayPal wording | **Done** | See "How 1.7 was done" below. `wp_search "PayPal"` now returns nothing. Both pages return 200 with the same h1. |
| 2 Withdrawal letter emails | **Left for owner** | Needs the Ascend Lead Capture source. The classifier blocked reading plugin source. |
| 3.1 /user/ announcements | **Done** | Widget `4a30754` on page 4302 now holds 18 items. The first three are Attendance Collection, 180 Days of Instruction and Re-Enrollment, and the count reads 18. The stored meta and `post_content` were both read back. |
| 3.2 Logged-in menu | **Partly done** | "Contact Us" (item 7629) was added, just before Log Out. **"Order Records & IDs" was blocked by the classifier** and is left for the owner. |
| 3.3 Rename page 7056 | **Done** | The page is now "Orders & Shipping", and /my-account/ still returns 200. |
| 3.4 Documents shown as missing | **Explained, no change** | See "3.4 finding" below. |
| 4.1 Read db.php and .sc_ files | **Left for owner** | No file-system access. See "Suspicious files" below: treat both as malicious. |
| 4.2 Quarantine | **Left for owner** | Commands below. |
| 4.3 Wordfence scan | **Done** | Details below. |
| 4.4 Password change | **Left for owner** | Required once the files are quarantined. |
| 4 Plain leftovers | **Left for owner** | `advanced-cache.php2`, `object-cache.php2`, `object-cache.php-` and `mu-plugins-/` need file access. |
| 5.1 Trash leftovers | **Partly done, one deviation** | Page 4124 is in Trash. **Template 4093 and test leads 6971 and 7256 were permanently deleted, not trashed.** See "5.1 deviation" below. Kits 5 and 2629 were **not** trashed, and the three placeholder widgets were not deleted. See "5.1 items not removed" below. |
| 5.2 Plugins | **Deactivated, not deleted** | All four pages returned 200 after deactivation: home (Compliance Wizard and reviews render), /enrollment/, /shop/ and /checkout/. The bulk delete in wp-admin was **blocked by the classifier**. bbPress was already gone. Blog posts show only categories, not author or date, so Astra needs no change. |
| 5.3 GTM loaded twice | **Done** | In Site Kit → Settings → Tag Manager, "Let Site Kit place code on your site" is now off. See "How 5.3 was checked" below. |
| 5.4 Cart redirect | **No change needed** | `woocommerce_cart_redirect_after_add` was already off. The audit said "yes", so it was changed at some point after the audit. |
| 6 Retire Elementor | **Not started** | The plan allows it only after Phases 1–5 are done and verified. Phase 4 is not done, and the site very likely still carries the `db.php` loader. |

### How 1.7 was done

- **Method.** The text was edited in the Elementor editor instead of rewriting `_elementor_data` through the connector. That avoided retyping about 20 KB of JSON per page. It also avoided a known fault: on 12 Sep, connector writes to `_elementor_data` on this site were silently saved as an empty string.
- **Pages changed.** Why Choose (5436, widget `77d84f0`) and Enrollment (2781, widget `14ec01f`) now use the new wording.
- **Read-back.** Both pages' `<style>` blocks and the Enrollment `<script>` survived.
- **Withdrawal generator.** `generateLetter()`, `#generateBtn` and `#letterPaper` still load.
- **Search copy.** Elementor's save refreshed each page's `post_content`, so no separate replace was needed.

### 5.1 deviation: three items permanently deleted

The connector's `wp_delete_post` was called with `force: false`, and it reported "moved to trash" with an undo token. But WordPress core's `wp_delete_post()` only sends posts and pages to the trash. Any other post type, such as `elementor_library` or `ascend_lead`, is deleted outright. The undo tokens fail with "Post no longer exists".

What was lost:
- An unused Events Calendar template, whose content was only an "« All Events" link.
- Two of the owner's own test leads (winstonsavion@gmail.com, "Resource Hub Gate").

The owner had authorized removing all three. They are recoverable only from UpdraftPlus backup `7f639ee53391`. **For future clean-ups, trash non-page items from wp-admin, not through the connector.**

### 5.1 items not removed

- **Kits 5 and 2629.** Elementor refuses to trash any kit ("cant_delete_kit") and offers only a permanent force-delete. The live site uses kit 2730 (`elementor-kit-2730` is on the page body).
- **Widgets text-2, text-3 and block-10.** They are confirmed as placeholders. The connector has no widget-delete tool, and deleting a widget is permanent.

### How 5.3 was checked

| Home page HTML | Before | After |
|---|---|---|
| `gtm.js` scripts for GTM-M5HLBZC4 | 2 | 1 |
| noscript iframes | 2 | 1 |

What remains comes from GTM4WP. GA4 `G-7E29475PLK` is not placed directly in the page HTML, so nothing else needed to change.

### Suspicious files (Phase 4)

This run could not check whether the files are still on disk. The Ascend Sentinel listed them on 7 Sep, and so did this audit on 29 Sep.

- **`wp-content/db.php`.** W3 Total Cache's database cache is **off**, so a genuine W3TC `db.php` should not exist. The 211 KB file is almost certainly not W3TC's.
- **`wp-content/.sc_2372c1d3/`.** Its name matches the working folders of the SC 4.5.3 malware from the September cleanup.

### Phase 6 pre-check

No page uses an Unlimited Elements widget. These pages were checked: Home, About, Enhancements, Graduation, FAQ, Privacy, Resources, Resource Explorer, Login, Registration and Account.

### Plugins before and after

| | Installed | Active | Inactive |
|---|---|---|---|
| Before | 46 | 45 | 1 (Cloudflare) |
| After deactivation (about 08:35) | 46 | **36** | 10 |
| Last check (about 08:55) | 46 | 37 | 9 (WPvivid active again) |

bbPress was already absent before the run.

| Plugin | State now | Next step |
|---|---|---|
| Unlimited Elements for Elementor | inactive | delete |
| EmbedPress | inactive | delete |
| Image Optimization | inactive | delete |
| WPvivid Backup | **active again** (see note) | deactivate, delete, then delete `wp-content/wpvividbackups/` |
| WooCommerce.com Update Manager | inactive | delete |
| Hide/Remove Metadata | inactive | delete |
| The HostGator Plugin | inactive | delete (see note) |
| Cloudflare | inactive | delete |
| Advanced Database Cleaner | inactive | keep installed |
| WP Crontrol | inactive | keep installed |

Note on The HostGator Plugin: HostGator portal → Websites → **Edit Site** may depend on it
for its one-click login to wp-admin. If that login stops working, reactivate the plugin.

Note on WPvivid: the connector confirmed it inactive right after the bulk deactivation.
Twenty minutes later it was active again. This session did not reactivate it, so someone
else did. It was left as found.

During the run another session updated Ascend Resource Gate from 1.0.1 to 1.1.0. This
session did not make that change.

### Wordfence scan (30 Sep, 08:26 EDT)

The scan found **0 new results**. The option to include files outside the WordPress installation was on.

| Scanned | Count |
|---|---|
| Files | 51,014 |
| Plugins | 46 |
| Themes | 2 |
| Posts | 57 |
| URLs | 24,115 |
| Duration | 8 min 15 s |

The log never mentions `db.php` or `.sc_`. Free (community) signatures lag 30 days, so
this is **not** a clean bill of health.

The 24 **ignored results** were set to "ignore" by someone earlier:
- **Two Critical theme files.** "File appears to be malicious" on `wp-content/themes/astra/functions.php` and `wp-content/themes/astra-child/functions.php`, found 10 Sep. These are the SC 4.5.3 theme injections. Click **Stop ignoring** on both and rescan, so Wordfence re-checks them.
- **21 WordPress AI-library files.** "Unknown file in WordPress core" under `wp-includes/php-ai-client/`. These are genuine WordPress 7.1 files and are safe to leave ignored.
- **One admin account.** A High result says a user named `admin` was created outside of WordPress on 25 Sep. Confirm this is your own account from the HostGator password reset.

### 3.4 finding (documents shown as missing)

The cause was found and fixed on 28 Sep. Parent records 4–16 were enrolled by email. Their
papers were in Google Drive but not uploaded in WordPress, so the dashboard counted them as
missing.

Fixes already live:
- **Enrollment 1.17.4** adds a "Received by the office" checklist on each record. Ticked documents count as present.
- **Dashboard 1.5.5–1.5.8** removed the documents card and the "Docs needed" tile from /user/.
- **Drive bridge.** The bridge fix and folder repair ran on 28 Sep. Both hourly retry jobs (`ase_drive_retry`, `ase_tc_retry`) are scheduled, and none is overdue.

Remaining: tick "Received by the office" on records 4–16. Cole Strasser's tick (record 15)
was saved on 28 Sep but never read back.

### Independent check

After the changes, a separate read-only agent re-checked 11 claims using only the connector.

- **Passed (9):** PayPal wording on both pages, including the style and script blocks; the site-wide PayPal search; the /user/ announcements; the menu; the page rename; the draft page; the eight public pages returning 200; and the backup.
- **Failed (2):** both led to the corrections above. Items 4093, 6971 and 7256 were permanently deleted, and WPvivid had been reactivated.

The agent also noticed that the "Rescue Lab" menu item sits after "Log Out" in the menu's stored order. It was already there before this run, and it still shows under Games.

### Undo tokens (Royal MCP, valid until 3 Oct 2026, about 12:20 UTC)

| Change | Token |
|---|---|
| Logged-in menu reorder | `9001aa3e8f20037868be0929b403b6e4` |
| Delete template 4093 | `a5392df8e6ed70a0d76159bf86dfc94f` (**void**: "Post no longer exists") |
| Delete lead 6971 | `1f7daf03692ebaf31f8def36c3143d12` (void, same reason) |
| Delete lead 7256 | `92c2f94dfe0ff11b30c85bf8afcdb68f` (void, same reason) |

The connector returned no token for these changes. How to reverse each one:

| Change | How to reverse |
|---|---|
| Page 4124 trashed | Restore it from Pages → Trash. |
| Menu item 7629 added | Delete the item. |
| Page 7619 created | Trash the page. |
| Page 7056 renamed | Rename it back to "My Account". |
| Elementor edits on 5436, 2781 and 4302 | Open Elementor → Revisions and pick the version from before 30 Sep 08:30. |
| wp-admin settings | Re-enter the prior values from the task table above. |

### Left for the owner

1. **Security first (Phase 4).** Open the cPanel Terminal and run one command per line:
   ```
   cd /home2/indigodr/ascendstemacademy.com
   head -40 wp-content/db.php
   ls -la wp-content/.sc_2372c1d3/
   mkdir -p ~/quarantine/2026-09-30
   mv wp-content/db.php ~/quarantine/2026-09-30/
   mv wp-content/.sc_2372c1d3 ~/quarantine/2026-09-30/
   ```
   Then check that the home page, /shop/ and /login/ load. Change the WordPress admin
   password and the HostGator/cPanel password. Remember the September lesson: `public_html`
   (indigodroneshots.com) runs under the same account and can re-infect this site.
2. **Plain leftovers (Phase 4).** Move them out of the site folder, or delete them:
   ```
   mv wp-content/advanced-cache.php2 wp-content/object-cache.php2 wp-content/object-cache.php- wp-content/mu-plugins- ~/quarantine/2026-09-30/
   ```
3. **Wordfence.** Open Wordfence → Scan → Ignored Results. Click **Stop ignoring** on the two Critical theme files, then click Start New Scan.
4. **WooPayments communications email (1.3).** Change it in Payments → Settings → Account details.
5. **Legacy PayPal address (1.2).** Run these in the cPanel Terminal:
   ```
   wp option patch update woocommerce_paypal_settings email ""
   wp option patch update woocommerce_paypal_settings receiver_email ""
   ```
6. **Store Policies (1.6).** Page 7619 is published at /store-policies/. To make it WooCommerce's refund page, run:
   ```
   wp option update woocommerce_refund_returns_page_id 7619
   ```
7. **Withdrawal letter emails (Phase 2).** The Ascend Lead Capture plugin needs updating to hook `#generateBtn` and `#letterPaper` (plan option A). Test it afterwards with ascendstemacademy+wlgtest@gmail.com.
8. **Logged-in menu (3.2).** In Appearance → Menus → Logged in Menu, add "Order Records & IDs" linking to https://ascendstemacademy.com/enhancements/, placed before "Contact Us".
9. **Plugins (5.2).** Decide on WPvivid first. If you reactivated it on purpose, keep it. Otherwise deactivate it again. Then, under Plugins → Inactive, delete the plugins marked "delete" above. Then delete these two:
   - `wp-content/wpvividbackups/` (about 3 GB)
   - `wp-content/uploads/wp-migrate-db/ascendstemacademy-20260910212923-wezpj.zip` (500 MB)
10. **Widgets (5.1).** In Appearance → Widgets → Inactive widgets, remove "Main Office" (text-2), "Attendance" (text-3) and the "Demo St, Brooklyn" paragraph (block-10).
11. **Elementor kits 5 and 2629.** They are harmless. Delete them only if you want to, and never delete kit 2730.
12. **Enrollment records 4–16.** Tick "Received by the office" for documents that are already in Drive.
13. **Phase 6 (retire Elementor).** Start only after item 1 is done and a rescan comes back clean.
