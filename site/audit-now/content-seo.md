# Content / SEO audit (non-Elementor items), 2026-09-23

Read-only audit. Nothing on the site was changed. Sources: Royal MCP (wp_get_page/post/posts/media_item/custom_css/post_meta/search) and ThinkRank (get-post-seo, get-post-content, get-schema-settings, get-site-identity-settings, get-llms-txt-status/settings).

## 1. Why Choose Ascend (page 5436)

- `_elementor_edit_mode` = **off** (the page renders post_content, not Elementor).
- post_content = 10,496 bytes, 7 Custom HTML blocks, all wrapped in `class='asa-why'`.
- ThinkRank get-post-content renders the redesign text ("Why families choose Ascend STEM Academy..."), so the redesign is what visitors see.
- No `asu*` / `a5*` ids in post_content (it is not Elementor data). Not checked: whether the old `_elementor_data` is still stored (large meta, not fetched). Per the 09-22 notes it was kept as a backup.
- Nothing looks broken: no raw shortcodes, no empty sections, no placeholder text, no duplicate sections.

| Change | Status | Evidence |
|---|---|---|
| Elementor switched off (`_elementor_edit_mode`=off) | LIVE | meta value "off" |
| Redesign in post_content (why-choose/page-content.html) | LIVE | hero, "More than a name on your documents", "Six things you get", steps, guides and CTA all present |
| "Umbrella school vs. filing on your own" section (09-23) | LIVE | section `asa-why-vs` with the heading "Less paperwork, more teaching" is present. It says "Ascend files the required notice with your district" |
| "What it costs" box (09-23) | LIVE | "$185 a year for grades K-8, about $15 a month, and $200 a year for grades 9-12" |

## 2. Additional CSS (theme astra-child, custom_css post 7210)

| Change | Status | Evidence |
|---|---|---|
| `.asa-why` stylesheet in Additional CSS | LIVE | The header reads "Ascend STEM Academy page design (Why Choose, About, Enhancements, Graduation, FAQ, Resources, Contact, Agreement, Shop). Scoped to .asa-why." It is about 23.8 KB, the full restyle version (it ends with the same `.asa-shop-head` rule as site/restyle/additional-css.css, 23,766 bytes), not the 11.9 KB why-choose.css |

## 3. SEO titles and descriptions (ThinkRank get-post-seo), pages in site/seo/pages.py

All 12 match the new values in pages.py exactly. The OG titles and descriptions match too.

| Page | Status | Evidence (live title) |
|---|---|---|
| 2732 Home | LIVE | "Florida Umbrella School for K-12 Homeschool \| Ascend STEM" + new desc |
| 2780 About | LIVE | "About Ascend STEM Academy \| Florida Umbrella School" + new desc |
| 5436 Why Choose | LIVE | "Why Choose Ascend STEM Academy \| Florida Umbrella School" + new desc |
| 2781 Enrollment | LIVE | "Enrollment & Tuition \| Ascend STEM Florida Umbrella School" + new desc |
| 2782 Enhancements | LIVE | "Progress Reports, Student IDs & Resume Reviews \| Ascend STEM" + new desc (focus kw is "homeschool progress reports and student IDs", pages.py has "...florida") |
| 2783 Graduation | LIVE | "Florida Homeschool Graduation & Diploma \| Ascend STEM" + new desc (focus kw is "florida homeschool graduation", pages.py has "...diploma") |
| 2784 FAQ | LIVE | "Florida Homeschool & Umbrella School FAQ \| Ascend STEM" + new desc |
| 2785 Contact | LIVE | "Contact Ascend STEM Academy \| Book a Free 15-Minute Call" + new desc |
| 5025 Resources | LIVE | "Florida Homeschool Resources: FLVS, Dual Enrollment & More" + new desc |
| 3477 Shop | LIVE | "Shop Ascend STEM Academy Merch & Student Services" + new desc |
| 3521 Blog | LIVE | "Florida Homeschool Blog & Grade Guides \| Ascend STEM" + new desc |
| 4897 Privacy | LIVE | "Privacy Policy \| Ascend STEM Academy" + new desc (OG/Twitter title "Ascend STEM Academy Privacy Policy") |

Extra checks. These pages are not in pages.py, so there is no exact "new" value to compare against:

| Page | Status | Evidence |
|---|---|---|
| Game titles 5952 / 6011 / 6017 / 7174 | LIVE (probable) | Titles are no longer the "before" values. They are now shorter "Name: ... \| Ascend STEM" titles, e.g. "Guess-a-lotl: Daily Axolotl STEM Word Game \| Ascend STEM" |
| 7056 My Account noindex | LIVE | robots_meta_enabled=1, noindex=true |

## 4. Schema, site identity and llms.txt

| Change | Status | Evidence |
|---|---|---|
| Schema phone +1-386-385-7653 | LIVE | organization_contact_phone "+1-386-385-7653" |
| Schema price range $165-$200 | LIVE | schema business_price_range "$165-$200". Note: site identity business_price_range is still "$" (not changed, separate field) |
| Site search on | LIVE | website_enable_search true, search URL set |
| Person works for Ascend | LIVE | person_works_for "Ascend STEM Academy" (Emily Llerena, Director of Education) |
| Refreshed org description | LIVE | "...Florida private umbrella (cover) school for K-12 homeschool families... Enrollment is open year-round." |
| llms.txt says Ascend files the district notice (09-23) | LIVE (settings) | key_features includes "Ascend files the required notice with the school district; families keep no portfolio and need no annual evaluation". Transcripts ($15) and free verification letters are listed too |
| llms.txt published file | LIVE (probable) | Static file exists at /llms.txt, 3,763 bytes, last modified 2026-09-23 00:13 UTC. Only a short preview is available (the public URL is blocked from this sandbox), so the full published text was not read |

Inconsistency to flag. The llms.txt website_description still says Ascend removes "the need for a homeschool letter of intent", which is fine. The FAQ/Elementor pages were not checked here.

## 5. Blog posts

| Change | Status | Evidence |
|---|---|---|
| Closing paragraph in 16 guides (5702-5783, 5791, 5799, 5805) | LIVE (sampled 6 of 16) | Fetched 5702, 5738, 5764, 5783, 5791 and 5805. Each has the new paragraph linking to /why-choose-ascend-stem-academy/ and /enrollment/: games link for K-8, graduation link for 9-12, "book a free 15-minute call" for the special-needs guides. All 6 fetched show modified 2026-09-22 16:18-16:20, so they have not been edited since. Not fetched: 5719, 5727, 5733, 5743, 5749, 5754, 5759, 5770, 5775, 5799 |
| 5791 sentence fix | LIVE | "some families find that a hybrid of homeschooling and outside support works best." |
| 5160 typo fix | LIVE | search snippet: "unique opportunity to enroll their children" |
| 4000 typo fix | LIVE | search snippet: "unique opportunity to enroll their children" |
| 5334 title without "NOW OPEN" | LIVE | title "Understanding Florida's Step Up For Students Scholarships and Umbrella Schools". Slug is still now-open-... (intended) |
| 5334 2025-26 amounts note | LIVE | "Updated September 2026: ... published for the 2025–26 school year, so check Step Up For Students..." (modified 2026-09-22 20:12) |
| 3917 drone offer removed | LIVE | Title is "How Drones Can Transform Your Child's Homeschooling" (no "Special Offer"). A "Getting Started with Drones at Home" section is present, and searches for the offer/tutoring wording return no match in 3917 |

Minor observation: several blog images have empty alt text (5705 in 5702; 5335, 5337, 5339 in 5334). These were not part of the change set.

## 6. Tickets Checkout (page 4124)

| Change | Status | Evidence |
|---|---|---|
| Set to draft | LIVE | status "draft". The content is still `[tec_tickets_checkout]` but is not public |

## 7. Image alt text

| Attachment | Status | Live alt |
|---|---|---|
| 5747 | LIVE | "Graphic for the Ascend STEM Academy fifth grade Florida homeschool guide" |
| 4948 | LIVE | "Learning styles and practical study tools for homeschool students" |
| 4946 | LIVE | "Illustration of different learning styles for homeschooled children" |
| 4940 | LIVE | "Illustration of different learning styles for homeschooled children" |
| 4934 | LIVE | "At-home workout scene for active homeschool learning" |

(There is no "before" record for alt text. The 09-22 notes say these images had none, so non-empty text means the change is still live.)

## Side observation (outside my items, relevant to the restyle pages)

wp_get_pages content_length for the restyle pages equals the size of site/restyle/<page>.html minus 1 byte:

| Page | content_length | restyle file |
|---|---|---|
| Enhancements 2782 | 9174 | enhancements.html 9175 |
| FAQ 2784 | 9801 | faq.html 9802 |
| Graduation 2783 | 7366 | graduation.html 7367 |
| Resources 5025 | 7756 | resources.html 7757 |
| Contact 2785 | 2686 | contact.html 2687 |
| Agreement 4448 | 4500 | agreement.html 4501 |
| Shop 3477 | 2036 | shop.html 2037 |

So post_content on those pages very likely still holds the assistant's `asa-why` restyle HTML, even though they may render from Elementor. About 2780 (2851 vs about.html 7238) does not match, so its content was probably replaced.
