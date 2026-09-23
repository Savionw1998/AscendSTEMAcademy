# Audit: pages group 2 (fetched live 2026-09-23)

Read-only. Source: fresh `_elementor_data`, `_elementor_edit_mode` and `post_content` (wp_get_post_meta / wp_get_page).
Compared against `ascend-site-updates/data/changes.json` (27 changes on these 9 pages) and `site/elementor-fix/fix.json`.

**Headline: none of the 27 changes on these pages has been reverted. All are LIVE, word for word.** No page has any `a5*` (fix.json) ids, so the fix snippet was not applied to Shop, Blog, Time Card or Privacy.

| Page | ID | _elementor_edit_mode | asu* ids | a5* ids | post_content restyle HTML (`asa-why`) |
|---|---|---|---|---|---|
| FAQ | 2784 | builder | asufq01, asufq02 | none | YES, stale (full restyled FAQ) |
| Contact | 2785 | builder | none | none | YES, stale |
| Resources | 5025 | builder | none | none | YES, stale |
| Enrollment Agreement | 4448 | builder | none | none | YES, stale |
| Shop | 3477 | builder | asush01-08 | none | YES, stale |
| Blog / Posts | 3521 | builder | asubl01 | none | not checked (not requested) |
| Time Card | 6097 | builder | asutc01 | none | not checked (not requested) |
| My Account | 7056 | builder | none | none | not checked (not requested) |
| Privacy Policy | 4897 | builder | asupp01, asupp02 | none | not checked (not requested) |

## FAQ (2784)

| Change | Status | Evidence |
|---|---|---|
| faq-lorem: remove "Lorem ipsum" heading | LIVE | Diploma item (521f61b) `heading: ""`, no Lorem anywhere |
| faq-withdraw: real "Can we withdraw?" answer | LIVE | eb34409 content = "Yes. Simply contact us via email to terminate enrollment..." |
| faq-noi: superintendent answer | LIVE | 35673b3 = "No, you don't file anything with the superintendent yourself..." + umbrella-schools link |
| faq-whypay: fix "Why pay" link | LIVE | 2e24204 link.url = /why-choose-ascend-stem-academy/ |
| faq-flvs: rewrite FLVS answer | LIVE | fba782c = "Yes! Ascend STEM Academy students can take free FLVS Flex courses..." |
| faq-180: "180 calendar days" fix | LIVE | ec7fbac = "...180 school days, not calendar days..." |
| faq-dual: tidy dual enrollment | LIVE | 03550ae = "Yes, they can. The College of Central Florida is the main option..." |
| faq-scholar: add scholarship FAQ | LIVE | item asufq01 present, same text |
| faq-midyear: add mid-year FAQ | LIVE | item asufq02 present, same text |

Other notes: the "Why pay" (2e24204) and "Diploma" (521f61b) items have no image, button or template keys, unlike the other items. They render with the widget defaults, so this is cosmetic. The Unlimited Elements accordion still carries demo `multisource_*` settings (gallery images, an Instagram handle, and a BigBuckBunny video). These are unused widget defaults and do not render. post_content still holds the full restyle FAQ HTML (class `asa-why`) while the page renders from Elementor.

## Contact (2785)

| Change | Status | Evidence |
|---|---|---|
| contact-tel: fix phone link | LIVE | 93d7129 link.url = `tel:+13863857653` |

Other notes: nothing looks broken. post_content still holds the restyle HTML (`asa-why` hero, cards and CTA).

## Resources (5025)

| Change | Status | Evidence |
|---|---|---|
| res-22: strip %22 from FLVS link | LIVE | 53a3d09 btn url ends `?source=counselor-resources` |
| res-register: /register/ to /registration/ | LIVE | 3256f41 secondary url = /registration/ |
| res-explorer: add https:// to Explorer link | LIVE | 54e7ac5 url = https://ascendstemacademy.com/ascend-stem-academy-resources-and-information/homeschool-resource-explorer/ |
| res-claim: drop "hands-on STEM / in-person" claim | LIVE | 53a3d09 = "...while Ascend STEM Academy keeps their records and provides guidance and one-on-one support." |
| res-subtitle: remove "This is a sub title" | LIVE | bbf51b5 `eael_infobox_sub_title: ""` |

Other notes: the FLVS PDFs are still the 24-25 editions (already known). On 54e7ac5 and f4b30e3 the secondary link points to the FLVS brochure, but the secondary button is not enabled there, so it is hidden. The info box has an unused "Click Me!" button text; no button shows. post_content still holds the restyle HTML.

## Enrollment Agreement (4448)

| Change | Status | Evidence |
|---|---|---|
| agr-spaces: stray &nbsp; spaces | LIVE | "enroll my child at Ascend STEM Academy:" |
| agr-to: "permitted to use" | LIVE | "is permitted to use and store this information" |
| agr-number: fold Welcome Packet sentence into item 2 | LIVE | "2. ...administration, and to fulfill all requirements..." |
| agr-statute: Section 1002.42 | LIVE | "as related to private schools (Section 1002.42, Florida Statutes)." |
| agr-voice: "I release and hold harmless" | LIVE | text present; "We release" gone |
| agr-sign: checkbox wording | LIVE | "By checking the agreement box and submitting my enrollment, I agree..." |

Other notes: the heading widget 5ebb24c has an inline `<span style=...>` pasted into its title. This looks pre-existing and renders fine. post_content still holds the restyle HTML.

## Shop (3477)

| Change | Status | Evidence |
|---|---|---|
| shop-rebuild: replace_all with Merch / Services / Game passes | LIVE | `_elementor_data` is exactly asush01-asush08 (3 intro HTML blocks, plus `[products category="merch"/"enhancements"/"game-passes"]`) |

Other notes: the fix.json replacement (a53d0ab and others) was not applied. The page depends on the `asa-why`/`asa-embed` CSS; if that CSS is removed, the headings render unstyled. The products render only if those WooCommerce categories exist (not checked). post_content also holds the restyle Shop HTML with the same three shortcodes.

## Blog / Posts (3521)

| Change | Status | Evidence |
|---|---|---|
| blog-intro: insert heading/intro before 5cbf73c | LIVE | asubl01 HTML ("Guides for Florida homeschool families") is first in container f1fb486 |
| blog-grid: [bdp_post] to [ascend_latest_guides] | LIVE | 5cbf73c shortcode = `[ascend_latest_guides count="50" filters="1"]` |

Other notes: the page renders only if the `[ascend_latest_guides]` snippet or plugin is active. If it is not, the raw shortcode shows (not verified from meta). The fix.json heading (a56c1ce, a5f2fb8) was not applied.

## Time Card (6097)

| Change | Status | Evidence |
|---|---|---|
| tc-note: attendance note + phone | LIVE | asutc01 HTML ("How attendance works... Call (386) 385-7653") before 7939a138 |

**um_content_restriction** (live):
`_um_custom_access_settings: true`, `_um_accessible: 2` (logged-in users only), `_um_access_roles: administrator, editor, author, um_student, um_faculty`, `_um_access_hide_from_queries: false`, `_um_noaccess_action: 0` (show message), `_um_restrict_by_custom_message: 0`, `_um_restrict_custom_message: ""`, `_um_access_redirect: 0`, `_um_access_redirect_url: ""`.
The page is still restricted to logged-in users with those roles. It has **not** been set to "Everyone".

Other notes: 7939a138 is a text-editor holding Gutenberg comments around `[ascend_time_card]` plus an empty `<p></p>`. It works, but the markup is messy. The fix.json replacement (a5b03f2) was not applied.

## My Account (7056)

| Change | Status | Evidence |
|---|---|---|
| myacct-link: /account/ to /user/ | LIVE | 557b4894 has `href="https://ascendstemacademy.com/user/"` (Student Account) |

Other notes: the text-editor holds raw Gutenberg block comments, 100px spacers and `[woocommerce_my_account]`. The spacers stack on top of the container's 100px top margin, so there may be a large gap. Otherwise it is fine.

## Privacy Policy (4897)

| Change | Status | Evidence |
|---|---|---|
| privacy-rewrite: replace_all with new policy | LIVE | Data is exactly asupp01 > asupp02 HTML (9 sections, "Effective date: September 23, 2026") |

Other notes: the whole policy is a single HTML widget that depends on the `asa-why` CSS. The owner had been asked to read it before applying, and it is live. The fix.json version (a5236b3 and others) was not applied.
