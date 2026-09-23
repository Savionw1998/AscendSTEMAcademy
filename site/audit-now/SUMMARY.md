# Where the site stands (checked 2026-09-23, read-only)

Details per page: pages-1.md, pages-2.md, content-seo.md, shop.md.

## What you reverted
- Home: the two sections I added (Games band, Latest guides) are gone. Everything else from me on Home is still live
  (tagline is the H1, "Who We Are" is H2, wizard title H2, empty HTML block removed, Notice of Intent wording x2, statute link).

## Still live (nothing else was reverted)
- About: field trips wording removed; drone video removed; "What you get with Ascend" comparison added; closing
  "Ready to join" box added; old lone Contact button removed. (Padding of the two added blocks changed to 25px - your edit.)
- Enrollment: steps wording; "free FLVS Flex courses"; field trips bullet removed; test-site background image removed;
  tuition table (K-8 $185/$165, 9-12 $200/$175, siblings 10%) added; old hover price cards removed.
- Enhancements: Joann Fabrics removed; Teacher Verification Letter wording; 3 "Delivered in 5-7 days" lines removed;
  Field Trips section removed; "Official transcripts and enrollment letters" section added.
- Graduation: 3 typo fixes; "Geometry"; PE removed from Science; Electives list replaced; blank lines removed;
  "Celebrate your graduate" sticker callout added.
- FAQ: Lorem ipsum removed; withdraw answer cleaned; superintendent, FLVS, 180 days, dual enrollment answers rewritten;
  "Why pay" link fixed; 2 new questions (scholarships, mid-year switch).
- Contact: phone link fixed.
- Resources: FLVS link, /registration/ link, Explorer link fixed; "hands-on STEM programs and in-person support" claim
  reworded; "This is a sub title" placeholder removed.
- Enrollment Agreement: 6 wording fixes.
- Shop: page replaced with intro + Merch / Student services / Game passes product lists.
- Blog: intro heading added; broken [bdp_post] replaced with [ascend_latest_guides] (needs the snippet).
- Time Card: "How attendance works" note added. Still logged-in-only (Ultimate Member).
- My Account: Student Account link -> /user/.
- Privacy Policy: fully rewritten (Sept 23, 2026).
- Why Choose: my redesign (Elementor off) incl. "filing on your own" comparison and "What it costs".
- Additional CSS: 23.8 KB stylesheet for my sections (.asa-why).
- SEO titles/descriptions on 12 pages, game page titles, My Account noindex, schema (phone, $165-$200), llms.txt.
- Blog posts: closing paragraph on 16 guides, typo fixes (5791, 5160, 4000), 5334 and 3917 refreshed.
- Tickets Checkout page is a draft; alt text on 5 images.
- Shop products: enrollment descriptions + cross-sells; "campus" wording removed; duplicate kids tee draft; resume,
  graduation packet, progress report rewritten; Official Transcript $15 (published); Launch Pack and Sticker Bundle (drafts);
  Enhancements category moved out of Merch; Game Passes category created.

## Loose ends found
- Pond Key / Full Pond Pass are probably not in the Game Passes category, so that Shop section may be empty.
- Tees 3903 and 3899 still contain leftover ChatGPT markup.
- Enhancements, Graduation, FAQ, Resources, Contact, Agreement and Shop still carry my restyle HTML in the page's
  hidden text copy (post_content). Visitors don't see it (Elementor renders), but search/SEO tools read it.
  Opening each page in Elementor and clicking Update replaces it.
- The page-fix snippet was never run (no changes from it anywhere).

## Code that is only there because of me
- Code Snippets "Ascend site features": blog grid shortcode, sibling discount, /blog and /register redirects,
  Shop page display, Enrollment-form login redirect. Deleting it: the Blog page shows raw [ascend_latest_guides] text
  and the sibling discount stops.
- Code Snippets "Ascend Page Fix" (if you added it): never ran; safe to delete.
- Additional CSS block (.asa-why): needed by Why Choose, the Shop/Privacy/Blog/Time Card sections and the About,
  Enrollment, Enhancements, Graduation added sections.

## Resource Explorer (page 5477)
- I never edited it. It was last saved 07:50-07:51 today (Sept 23) from your admin account.
- It has no Ultimate Member restriction. The connector cannot see the page password or the Ascend Resource Gate settings.
