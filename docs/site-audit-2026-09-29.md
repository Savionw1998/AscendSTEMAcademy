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
