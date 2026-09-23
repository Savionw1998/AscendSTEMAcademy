# Shop (WooCommerce) audit: 2026-09-23

Read-only. Sources: wp_get_posts (post_type=product, status any), wp_get_post, wp_get_post_meta, wp_get_terms (product_cat), wp_get_media_item, wp_search "campus".
Note: every product the assistant touched still has its `modified` timestamp from the assistant's run
(2026-09-22 16:23 for the "campus"/draft batch, 20:09-20:11 for the rewrites/new products). None has been edited since, so the owner has not reverted any product by hand.
Products have no Elementor data, so `_elementor_edit_mode` / "asu*" / "a5*" ids do not apply here.

## Enrollment products (item 1)

| Change | Status | Evidence |
|---|---|---|
| 4461 K-8 (New Student) description rewrite | LIVE | "One year of enrollment for one K–8 student... Included with enrollment / Have these ready" lists; new short excerpt; modified 2026-09-22 20:11:23 |
| 4463 K-8 Re-Enrollment description rewrite | LIVE | "Your next year includes..." list + "Returning families pay a lower re-enrollment rate"; modified 20:11:26 |
| 4464 9-12 (New Student) description rewrite | LIVE | Same structure, links to /graduation/ "24-credit diploma"; modified 20:11:28 |
| 4466 9-12 Re-Enrollment description rewrite | LIVE | Links to 24-credit diploma + /enhancements/; modified 20:11:31 |
| Cross-sells 4461 (K-8) | LIVE | `_crosssell_ids` = [3491 ID Card, 3465 Progress Report, 6256 Scientist Sticker] |
| Cross-sells 4463 (K-8) | LIVE | `_crosssell_ids` = [3491, 3465, 6256] |
| Cross-sells 4464 (9-12) | LIVE | `_crosssell_ids` = [3491, 3465, 7235 Official Transcript] |
| Cross-sells 4466 (9-12) | LIVE | `_crosssell_ids` = [3491, 3465, 7235] |

## "Campus" wording (item 2)

| Change | Status | Evidence |
|---|---|---|
| 3491 ID Card | LIVE | Now "Handy for field trips, testing days, events and places that offer student discounts"; modified 16:23:11 |
| 5030 ASA White Hoodie | LIVE | "...chilly mornings, co-op days, field trips or lounging at home"; modified 16:23:13 |
| 3905 ASA Black Hoodie | LIVE | Same text as 5030; modified 16:23:17 |
| 3903 Men's Black Tee | LIVE | "...casual wear, co-op days, field trips..."; modified 16:23:20 |
| 3899 Women's Black Tee | LIVE | "...everyday wear, co-op days, field trips..."; modified 16:23:25 |
| 3898 Teen Black Tee | LIVE | "...co-op days, field trips..."; modified 16:23:23 |
| Site-wide check | LIVE | wp_search "campus" in products returns nothing |

Something looks broken: 3903 and 3899 still wrap their text in pasted ChatGPT markup
(`<div class="markdown prose..." data-message-model-slug="gpt-4o-mini">`). The assistant cleaned this out of 3465 but not these two tees.

## Duplicate listing (item 3)

| Change | Status | Evidence |
|---|---|---|
| 4965 "ASA Kids White T-Shirt (See Full Listing)" set to draft | LIVE | status = draft; modified 16:23:36 |

## Enhancement products (item 4)

| Change | Status | Evidence |
|---|---|---|
| 3504 Basic Resume Review rewrite | LIVE | "Formatting and grammar review / One round of written feedback / 5–7 business days" + links to Standard/Premium; modified 20:09:52 |
| 3507 Standard Resume Review rewrite | LIVE | "Our most popular option... One follow-up email"; modified 20:09:56 |
| 3509 Premium Resume Review rewrite | LIVE | "30-minute consultation... Interview tips, Cover letter review"; modified 20:09:59 |
| 3620 Graduation Packet rewrite | LIVE | Lists transcript, diploma, "Graduate" T-shirt, cap and tassel + link to /graduation/; modified 20:10:02 |
| 3465 Progress Report ChatGPT markup removed | LIVE | Clean `<p>` content only, no ChatGPT divs; modified 20:10:05 |

## New products (item 5)

| Change | Status | Evidence |
|---|---|---|
| 7235 Official Transcript, published, $15 | LIVE | status publish; `_regular_price`/`_price` = 15; in stock |
| 7236 High School Launch Pack, draft | LIVE | status draft; `_price` = 60; no featured image |
| 7237 STEM-a-lotl Sticker Bundle, draft | LIVE | status draft; `_price` = 10; featured image 6253 (Scientist sticker) |

## Categories and featured images (item 6)

| Change | Status | Evidence |
|---|---|---|
| Enhancements (37) moved out from under Merch | LIVE | parent = 0; count 7 (matches 3465, 3491, 3504, 3507, 3509, 3620, 7235) |
| Game Passes (118) category exists | LIVE | id 118, slug game-passes, parent 0 |
| 7188 Pond Key + 7189 Full Pond Pass in Game Passes | PARTIAL (likely not assigned) | Game Passes count = 0, Merch count = 15 = 13 published merch items + 7188 + 7189. The two passes appear to still sit in Merch only. No read tool returns a product's terms directly, and the public site was unreachable from here, so this comes from the term counts. |
| Featured image 7188 Pond Key | LIVE | `_thumbnail_id` 5957 ("Guess-a-lotl" axolotl mascot) |
| Featured image 7189 Full Pond Pass | LIVE | `_thumbnail_id` 6253, which is the Scientist sticker image (reused, not game art) |
| Featured image 7235 Official Transcript | LIVE | `_thumbnail_id` 5514 (Diploma-Image.png, same as the Graduation Packet) |

## Media (item 7)

| Change | Status | Evidence |
|---|---|---|
| 7233 Code-a-lotl sticker concept | LIVE | Attachment exists: "Code-a-lotl sticker concept (draft)", code-a-lotl-sticker-concept.png, alt text set |

## Other observations
- Uncategorized count 0. The draft game products 6988-6991 (older, pre-assistant) are still drafts.
- Enrollment and resume products are still not marked virtual (per CHANGES-2026-09-23 "needs you"); not re-checked here.
