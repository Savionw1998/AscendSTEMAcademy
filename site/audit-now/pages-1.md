# Audit: pages-1 (Home 2732, About 2780, Enrollment 2781, Enhancements 2782, Graduation 2783)

Checked 2026-09-23 against freshly fetched `_elementor_data` (read-only tools only: wp_get_post_meta, wp_get_post, wp_get_post_revisions, Thinkrank get-post-content).
All 36 changes.json items for these pages were checked.

## Page overview

| Page | _elementor_edit_mode | asu* ids | a5* ids (fix.json) | post_content holds restyle HTML (`asa-why`)? | Notes |
|---|---|---|---|---|---|
| Home 2732 | builder | none | none | n/a (Home was not restyled) | Last saved 2026-09-23 07:42 (3 revisions that minute). Games band and Latest-guides row are gone. |
| About 2780 | builder | asuac01/02, asuat01/02 | none | **No.** post_content is Elementor's plain sync of the builder content (no `asa-why` class). Restyle HTML is gone. | Modified 2026-09-23 07:47. |
| Enrollment 2781 | builder | asuet01/02 | none | not checked (not restyled) | |
| Enhancements 2782 | builder | asuer01/02 | none | **Yes.** post_content still holds the full restyle HTML: 7 `<div class='asa-why'>` blocks, the same as site/restyle/enhancements.html. The page renders from Elementor. | Thinkrank's "content" for SEO analysis is built from this stale HTML. |
| Graduation 2783 | builder | asugm01 | none | **Yes.** post_content still holds the full restyle HTML: 6 `asa-why` blocks, the same as site/restyle/graduation.html. The page renders from Elementor. | Same Thinkrank caveat. |

The fix.json sections (`a5xxxxx`) exist for 2780–2783, but none appear on these pages. The elementor-fix snippet has **not** been applied here.

## Home (2732)

| Change | Status | Evidence |
|---|---|---|
| home-h1: hero tagline as H1 | LIVE | 0c61f50 `header_size: "h1"` |
| home-h1-whoweare: "Who We Are" H1 to H2 | LIVE | a41b579 has no `header_size` key, so it uses Elementor's default h2. The explicit "h2" was probably dropped when the owner saved in the editor. |
| home-h1-wizard: wizard title H1 to H2 | LIVE | 45b45fbd contains `<h2>Florida Homeschool<br>Compliance Wizard</h2>` |
| home-h1-wizard-css | LIVE | `#ascend-compliance-wizard .acw-header h2{` is present. The old `h1{` rule is gone. |
| home-empty: remove empty HTML 9fe7454 | LIVE | 9fe7454 is absent |
| home-noi: Notice of Intent wording | LIVE | 4f7d1f5 = "Filing your own Notice of Intent (Ascend files the required notice for you)" |
| home-wizard-noi | LIVE | Wizard text has "you don’t file a Notice of Intent yourself (Ascend files…)". The old "no separate Notice of Intent" text is gone. |
| home-wizard-statute | LIVE | Disclaimer links to leg.state.fl.us 1002.41. The flsenate 2024 URL is gone. |
| home-games (asuhg01 after 102a02d) | REVERTED | No asuhg* ids. 102a02d is followed directly by 6b63a13c (wizard). |
| home-guides (asuhl01 at end) | REVERTED | No asuhl* ids. The page ends with the wizard container 6b63a13c. |

Broken or odd on Home: nothing broken. The top-level containers are 9923533, d849b5d (trustindex shortcode widget, which is normal), 65667d5, cafee74, 102a02d and 6b63a13c. No empty sections, raw shortcodes in text, or placeholders.

## About (2780)

| Change | Status | Evidence |
|---|---|---|
| about-trips: drop field trips/workshops | LIVE | 25cc1b3 description = "…including guides, project ideas, and college and career support…" |
| about-video: remove header drone video | LIVE | 235ba13 has no `background_video_link`. Only `background_play_on_mobile` and the overlay remain. |
| about-compare (asuac01) | LIVE (container edited) | asuac01/asuac02 are present. The HTML is identical to changes.json. The container padding is now 25px on all sides (the assistant set 0), and `content_width: boxed` was dropped. |
| about-cta (asuat01 at end) | LIVE (container edited) | asuat01/asuat02 are present and the HTML is identical. The container padding is now 25px (the assistant set 0). |
| about-contact-btn: remove 6249777 | LIVE | 6249777 is absent |

Broken or odd on About: nothing broken in Elementor. The synced post_content contains the text "skip render: ucaddon_circle_number_widget" ×6. This is only Elementor's text sync of Unlimited Elements widgets and does not render on the page. It does feed Thinkrank's content analysis.

## Enrollment (2781)

| Change | Status | Evidence |
|---|---|---|
| enr-steps | LIVE | e94544a: "Create your family account, then open the Enrollment Form in your account…" |
| enr-flvs | LIVE | 4ce4b27 list item = "Option to take free FLVS Flex courses" |
| enr-trips: remove Field Trips item | LIVE | c7a115c items: Progress Reports, Student and Educator IDs, Transcripts, Resume Reviews, College Preparation Consulting |
| enr-strattic: remove background image | LIVE | L8eKkSJ has no `background_image`. Only position and size remain. |
| enr-tuition (asuet01 after 7d42347) | LIVE | asuet01/02 are present right after 7d42347 ("Tuition" heading). The settings and HTML are identical to changes.json. |
| enr-hover: remove 3e4b691 | LIVE | 3e4b691 is absent |

Broken or odd on Enrollment: container 3bfe819 has `settings: []` and holds one eael-post-grid widget (768f2c6) with almost no settings. That is probably pre-existing and not broken. Container 7d42347 holds only the "Tuition" heading. This is intended, because the tuition table follows it.

## Enhancements (2782)

| Change | Status | Evidence |
|---|---|---|
| enh-joann: remove Joann Fabrics | LIVE | 1ee05e5: "Michael’s, Office Depot, and Barnes &amp; Noble." |
| enh-hpa: explain verification letter | LIVE | 1ee05e5: "a Teacher Verification Letter (a letter from Ascend confirming…)" |
| enh-delivery-1 (rrt0001) | LIVE | rrt0001 is absent |
| enh-delivery-2 (rrt0002) | LIVE | rrt0002 is absent |
| enh-delivery-3 (rrt0003) | LIVE | rrt0003 is absent |
| enh-trips: remove e7af7fd | LIVE | e7af7fd is absent |
| enh-records (asuer01 after rr00001) | LIVE | asuer01/02 are present at top level right after rr00001, identical to changes.json |

Broken or odd on Enhancements: the Elementor tree is clean, with no empty sections or placeholders. The "Delivered in 5–7 business days" line still appears in header tat0001 and in 3ed1c73 and 8a3c04a. Those were not part of the changes. **post_content still contains the restyle HTML** (see overview).

## Graduation (2783)

| Change | Status | Evidence |
|---|---|---|
| grad-typo1 | LIVE | efdf4e1: "please select the listed course" |
| grad-typo2 | LIVE | 85fb602: "no different from traditional private school transcripts and diplomas." |
| grad-typo3 | LIVE | 4f07a57: "Career and Technical Education (1 credit)" |
| grad-geometry | LIVE | 7402a87: "Geometry (1 credit)" |
| grad-pe: remove PE from Science card | LIVE | 9879983 content = only "Biology (1 credit)" |
| grad-electives | LIVE | 6be3f96 lists Computer Science and Coding … Extra math and science courses |
| grad-math-blank | LIVE | b2e3bc6 ends at "Probability and Statistics", with no empty `<p>&nbsp;</p>` |
| grad-merch (asugm01 after 1401f9c) | LIVE | asugm01 is directly after button 1401f9c, with identical HTML |

Broken or odd on Graduation: the Elementor tree is clean. Top-level containers 35db82e, 8aa71f0, e4ad766 and f1fb247 have `settings: []`, which is normal for Elementor. **post_content still contains the restyle HTML** (see overview).

## Caveat

The `asa-*` blocks (asuac/asuat/asuet/asuer/asugm) depend on the `asa-why`/`asa-embed` CSS from the restyle additional CSS. This audit did not check whether that CSS is still loaded. If it was removed, these blocks render unstyled.
