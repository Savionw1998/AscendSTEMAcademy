# Ascend STEM Academy — Video #3: "The Platform" (SaaS-style product marketing)

**Format:** Modern SaaS product-marketing explainer. Clean 2D vector motion design + animated
drawings + real UI snapshots of the website and mobile app. Voiceover narrator + kinetic
on-screen type + music bed.

**Length:** ~95 s, 16:9 1080p24 YouTube master. Cuts: 9:16 Shorts (~35 s), 15 s bumper, thumbnail.

**Lucas:** cameo only — two appearances (the games beat and the end card). He is NOT the
through-line of this video the way he was in video #2. He never speaks.

**Predecessor:** video #2 = `Drive › 04 Brand and Marketing › Videos › Lucas Explainer`
(paper-collage style, Lucas throughout, 94.6 s). This video is deliberately a different
register: that one sells the *feeling*, this one sells the *product*.

---

## 1. Source of truth

All feature claims below were pulled from the live site through the WordPress MCP on
2026-09-15, not from memory.

| Claim | Verified where |
|---|---|
| K–12 eligibility, year-round enrollment, recordkeeping, quarterly check-ins, satisfies FL attendance, 1-on-1 support on demand | `/enrollment/` "What's Included" |
| Parent-Teacher Manual, complimentary T-shirt, 60+ curated resources, family-selected curriculum | `/enrollment/` "What's Included" |
| FLVS Flex partnership (self-paced online courses; enroll as *private school* route, select Ascend) | `/ascend-stem-academy-resources-and-information/` + `/user/` announcements |
| College of Central Florida (CF) dual enrollment partnership | `/ascend-stem-academy-resources-and-information/` |
| Public School Withdrawal Letter Generator (Florida notification requirement) | `/enrollment/` embedded tool |
| Enrollment doc handling: birth certificate, DH 3040 health exam, DH 680 immunization / DH 681 exemption | `/enrollment/` "Required Information" |
| Welcome Packet (policies live in it) | `/enrollment-agreement/`, K-8 + 9-12 New Student Enrollment Packet products |
| Diploma: "no different than traditional private school transcript and diploma"; 2.5 GPA; standard 24-credit FL alignment; $125 graduation package = official transcript + diploma + Graduate tee + cap & tassel | `/graduation/` |
| Dashboard modules (exact shortcodes on `/user/`) | `ascend_enrollment_status`, `ascend_resources_row`, `ascend_quick_actions`, `ascend_my_time_cards`, `ascend_progress`, `ascend_badge_shelf`, `ascend_game_stats`, `ascend_project_upload`, `ascend_spin_wheel`, `alt_living_transcript`, `ascend_group_code` |
| Optional enhancements: progress reports, student/educator IDs, transcripts, résumé reviews, college prep consulting | `/enrollment/` |
| Tuition products | 9-12 New Student $200 · 9-12 Re-Enrollment $175 |

**Not verifiable from this container** (egress proxy blocks the open web — see §7):
- ~~The Google Play Store listing.~~ Confirmed 2026-09-15: https://play.google.com/store/apps/details?id=com.ascendstemacademy.twa — official badge composited over the placeholder pill in scene 10 by `assemble.py`.
- The two YouTube reference links you sent (`M8tXsQ34f30`, `aSte18D2_YE`) — youtube.com is
  blocked here. Style below is my read of "SaaS marketing video"; tell me what those two are
  and I'll re-cut the look to match.

**Reference screenshots:** `Drive › Ascend STEM Academy, LLC › New/` — 16 phone screenshots
(7 Chrome, 9 Samsung Browser, 2026-09-09) of the shop, Why Choose page, and app screens.
That is the "random new folder." Drive's OCR on them is unreliable and the live site is
blocked from here, so scene UI below is reconstructed from the shortcode list and page
source, which is more accurate anyway.

---

## 2. Look

**Not** the paper collage of video #2. This is the product register:

- **Ground:** near-white `#F7FAFC` with a very soft brand-blue→green gradient wash, subtle grid.
- **Surfaces:** floating UI cards, 16 px radius, long soft shadows, drifting on parallax.
- **Devices:** a clean laptop frame and a clean phone frame are recurring characters. The
  phone carries the mobile-app beats; the laptop carries the dashboard beats.
- **Motion:** confident easing (`cubic-bezier(.22,1,.36,1)`), things *assemble* rather than
  fly. Cards snap into place on the beat. No bounce, no wobble — that's video #2's language.
- **Drawings:** loose, friendly line-art vignettes (a parent at a kitchen table, a mail
  carrier, a stack of forms) sit *behind* the UI cards so the video reads warm, not corporate.
- **Palette:** Blue `#009CDE` · Deep green `#1D4010` · Light green `#BFE0B4` · Lucas pink
  `#F2A6C6` (accent only) · Ink `#173E63` · Paper `#F7FAFC`.
- **Type:** Poppins ExtraBold headline, Poppins Medium sub. One idea per card, ≤6 words.
  Rule-of-thirds layout with 10 % title-safe margins (carried over from video #2 v6).

---

## 3. Storyboard (95 s)

| # | Time | Scene | On-screen text |
|---|---|---|---|
| 0 | 0–7 | Cold open. A parent at a kitchen table behind a growing tower of forms: Notice of Intent, evaluation, portfolio, immunization. Paper stacks up past frame. | *Florida homeschooling comes with paperwork.* |
| 1 | 7–13 | The tower collapses into a single clean card that becomes the Ascend dashboard on a laptop. Logo badge assembles. | *Ascend STEM Academy* / *Your umbrella school, in one place.* |
| 2 | 13–22 | Enrollment form on screen. Fields fill themselves, a progress ring runs 0→100, timer in the corner reads **4:52**. Green check. | *Enroll in about five minutes.* |
| 3 | 22–31 | Documents (birth certificate, DH 3040, DH 680) glide in and file themselves into a labelled folder. A Notice of Intent stamps itself. Withdrawal-letter card slides by. | *Notice of intent. Withdrawal letters.* / *We handle the documents.* |
| 4 | 31–39 | Chat bubble pops on the phone, a reply lands almost immediately. Clock shows same-day. | *Questions? Real people answer.* / *Usually same day.* |
| 5 | 39–47 | Doorstep drawing. A branded box lands, opens: T-shirt, Parent-Teacher Manual, stickers, welcome letter. | *Days later: your welcome packet.* |
| 6 | 47–54 | Laptop dashboard scrolls: enrollment status → quick actions → 60+ resources → badge shelf. | *One dashboard. Everything in it.* |
| 7 | 54–61 | Time Card Tracker. Subject chips (Math, Science, Language Arts, Electives) drop into day cells; a **180-day** progress bar fills. | *Log hours by subject.* / *Florida requirements, tracked automatically.* |
| 8 | 61–71 | **Living Transcript.** Phone: parent thumb-types "Built a volcano, measured the pH." Sends. A photo of the project drops in too. Both resolve into a transcript row — subject, Florida standard, hours, credit. Pull back: a full transcript page. | *Type a sentence. Or send a photo.* / *It becomes a real transcript.* |
| 9 | 71–78 | Four game tiles fan out (5-letter grid, honeycomb, 4×4 groups, traced letter paths). Streak counter, badges. **Lucas cameo #1** — pokes up from behind the tiles, taps one, ducks back. | *Daily puzzles. Streaks. Badges.* |
| 10 | 78–85 | Phone rotates to front; the dashboard is running on it. Google Play badge lands underneath. | *Now on the Google Play Store.* |
| 11 | 85–91 | Two partner cards slide in: **FLVS Flex** (self-paced courses) and **College of Central Florida** (dual enrollment). A college-credit chip flies from CF card into the transcript. | *FLVS courses. CF dual enrollment.* / *College credit in high school.* |
| 12 | 91–95 | Diploma + official transcript + cap and tassel settle onto the card. Then clean end card: badge, wordmark, URL. **Lucas cameo #2** — peeks over the bottom edge of the card and waves. | *A real diploma.* → *AscendSTEMAcademy.com* / *Enroll in about five minutes.* |

9:16 cut = scenes 0, 2, 8, 9, 10, 12. 15 s bumper = 0, 2, 10, 12.

---

## 4. Voiceover (~170 words, warm confident product-marketing read)

| # | VO |
|---|---|
| 0 | Homeschooling in Florida comes with a filing cabinet attached. |
| 1 | Ascend STEM Academy puts the whole thing in one place. |
| 2 | Enrollment takes about five minutes. Not an afternoon — five minutes. |
| 3 | Notices of intent, withdrawal letters, health forms, immunization records. We handle the documents. |
| 4 | And when you have a question, a real person answers — usually the same day. |
| 5 | A few days later, your welcome packet shows up: your shirt, your parent-teacher manual, everything you need to start. |
| 6 | Inside your dashboard, it's all waiting. |
| 7 | Log school hours by subject, and watch progress toward Florida's requirements fill in on its own. |
| 8 | Type one sentence about today. Or just send a photo. We turn it into a standards-mapped transcript your student keeps — a portfolio at graduation. |
| 9 | Four daily puzzles keep them sharp. Streaks, badges, bragging rights. |
| 10 | And it's all in your pocket now. Ascend STEM Academy is on the Google Play Store. |
| 11 | Take FLVS courses. Earn college credit through our dual enrollment partnership with the College of Central Florida. |
| 12 | And graduate with a real diploma. Ascend STEM Academy — enroll in about five minutes. |

**Voice:** Helena, `seed_audio` preset `3c2b83c0-2e0a-5ae8-998a-a5fe71b7eccd` — the narrator
locked for video #2. Reusing her keeps the two videos sounding like one brand. Say the word
and I'll audition alternatives.

---

## 5. Production pipeline

Higgsfield account: `max` plan, 2368 credits at kickoff. Reference Elements already live from
video #2 and reused here:

| Element | ID |
|---|---|
| Lucas-2D | `3efa6ccf-924b-4f6b-a849-ab6a8ba76ee7` |
| Lucas-3D | `23d25af7-8f2c-44c8-9aac-d27de804dd36` |
| Ascend-Logo | `450b499b-03f2-48f8-9c25-95e0dd6e7d65` |

1. **Style frames** — one per scene (13), `gpt_image_2` / `nano_banana_pro`, 16:9, SaaS look
   from §2. UI text baked in at frame stage so it stays crisp and correct.
2. **Approval gate** — contact sheet to you before any animation.
3. **Animate** — Seedance 2.5 image-to-video, 4–8 s per scene, `generate_audio: false`.
   UI beats get deterministic code-built motion (scroll, fill, type-on) rather than AI motion
   wherever the UI must stay legible — AI video smears small text. Same lesson as video #2 v6.
4. **VO** — `generate_audio`, Helena, 13 lines.
5. **Assemble** — `assemble.py` (ffmpeg + PIL caption cards), ported from video #2.
6. **Package** — thumbnail, 9:16 cut, bumper; masters to
   `Drive › 04 Brand and Marketing › Videos › SaaS Explainer`.

Budget estimate: ~13 frames × 2 variants + ~14 clips + ~10 retakes ≈ 50 generations.

---

## 6. Open decisions for you

1. **The two YouTube references** — blocked from this container. What are they? If they set
   the visual target, §2 should follow them, not my default.
2. **Play Store link** — paste it and I'll put the real badge + QR on scene 10.
3. **Logged-in screenshots** — real captures of `/user/`, `/time-card-tracker/` and a game
   page would let scenes 6–9 show the *actual* UI instead of a faithful rebuild. Drop them in
   the `New/` folder and I'll swap them in.
4. **Length** — 95 s is YouTube-native. Say if you want a 60 s cut as the master instead.
5. **Music** — video #2 used a track you supplied. Same one again, or a different bed?

## 7. Environment notes

This session runs in a sandboxed container with a strict egress proxy: `youtube.com` and
`ascendstemacademy.com` both return 403 on CONNECT, so no live-site screenshots and no
reference-video analysis from here. The WordPress MCP, Google Drive MCP and Higgsfield MCP
all work, which is how the facts in §1 were verified.

Higgsfield's output CDN (`*.cloudfront.net`) is also blocked, so generated frames and clips
cannot be pulled into this container. Consequences for the pipeline in §5:
- I cannot look at a generation myself; you see it through the Higgsfield gallery widget and
  tell me what's wrong. Video #2's "generate, inspect, retake" loop was mine; here it is ours.
- Assembly (VO sync, caption cards, music, cuts) runs inside Higgsfield's `sandbox_exec`
  instead of local ffmpeg/PIL. **Probed 2026-09-15 and confirmed:** the sandbox pulls from
  the CDN, and has ffmpeg 5.1, Pillow 12, ImageMagick, faster-whisper and Montserrat
  ExtraBold (Poppins's stand-in). `assemble.py` from video #2 ports there almost unchanged;
  the sandbox is ephemeral, so each render is one chained command that ends by uploading
  its output through `media_upload` → `curl PUT` → `media_confirm`.
- If you'd rather keep the video #2 local pipeline, run this session from your Windows
  machine (H:\projects) instead of the cloud container and everything in §5 works as before.

## 8. Status log

- 2026-09-15 · Facts verified against the live site via WordPress MCP. Storyboard + VO v1
  written. Higgsfield Elements from video #2 confirmed still live.
- 2026-09-15 · Four style frames v1 submitted (sc00, sc02, sc08, sc10) with `gpt_image_2` at
  the model's default *low* quality as a first look at the SaaS register. Job IDs in
  `jobs.json`. Unreviewed by me — CDN blocked (§7). Awaiting your read before rendering the
  remaining nine at high quality.
- 2026-09-15 · Frames approved ("look good"), Helena locked, end-card URL = ascendstemacademy.com.
  Real time-card + app screenshots supplied → sc07 rebuilt in code from the real tracker UI
  (`scripts/scenes.py`); in-device UI in the AI frames matched to the app's mint/green look.
- 2026-09-15 · 13 Helena takes (87.2 s of VO). 8 remaining frames at high quality. 12 scenes
  animated with Seedance 2.5 (`omni_reference`, 1080p, no audio); sc03 took 25 min so a hedge
  job was submitted and left unused. `jobs.json` has every id.
- 2026-09-15 · **v1 master rendered in the Higgsfield sandbox**: `assemble.py` on
  `manifest.json` → 105.0 s, 1080p24, 37 MB, VO + caption cards, **no music yet**. End-card
  overlays auto-placed (card 134..1749 × 80..899, Lucas at 1160..1544 × 780..896, content
  band 120..750). URLs in `renders/timeline_v1.json`. Scripts fetched by commit SHA — the
  branch URL on raw.githubusercontent is cached ~5 min and served a stale manifest once.
- OPEN: music bed (the m4a you attached never made it to Higgsfield — the upload from this
  container was declined; drop it in the Higgsfield upload widget or re-approve the PUT and
  `assemble.py` mixes it with ducking); your review of the 12 AI clips; 9:16 cut; thumbnail.
- 2026-09-15 · Music bed uploaded through the Higgsfield widget (media `7240cb78`). Play Store link confirmed. → v2 render: music ducked under VO + official Google Play badge on sc10.
- 2026-09-15 · **v2 master rendered**: 105.0 s, 1080p24, 37 MB — music bed (gain 0.16, sidechain-ducked
  under Helena, 1 s in / 3 s out) + official Google Play badge fitted over the sc10 placeholder
  (pill detected at 732..1094 × 792..1042). URLs in `renders/timeline_v2.json`. Built from commit
  `8fd7bd2`. Unreviewed by me (CDN blocked) — awaiting your notes.
- OPEN: your review of the 12 AI clips → retakes; 9:16 Shorts cut; 15 s bumper; thumbnail.

## 9. QA pass (2026-09-15) and what it changed

`scripts/qa.py` measures the things a viewer notices, per scene: obstruction
(content + 2x motion) under every candidate caption position, where each VO line
actually stops speaking against the scene length, the mascot's extent across a
whole clip, and overlay drift. Findings and fixes, all in **v3**:

| Finding | Fix |
|---|---|
| **No VO line is cut off.** Every line has exactly 0.90 s of picture after its last word, and none bleeds into the next scene. | Kept; added a 60 ms tail fade so no line stops on a hard edge. |
| 6 captions sat on busy areas. Worst: sc02 enrollment 0.50, sc05 packet 0.55, sc06 dashboard 0.49, sc10 app 0.45, sc08 transcript 0.31 — where the caption covered the phone. | Moved: sc00→tl, sc02→tc (0.50→0.26), sc05→tl, sc06→br, sc08→br then bc (0.31→0.06), sc10→tl (the Play badge now owns the bottom). |
| **End card overlap.** Layout used Lucas's frame-0 box (y 784) but he rises to **y 576** as he waves, so the logo stack overlapped him. | `lucas_extent()` scans the whole clip; band is now 120..536. |
| **Play badge drift.** Badge pinned to frame 0 while the phone drifts 8–18 px, leaving the dark pill peeking out. | `pill_track()` + a drifting overlay. |
| End card held 1.46 s of frozen picture after the wave. | `min` 9.5 → 8.2 s. |
| Time card stacked rows into a fixed card until it read as a cramped list. | Left column rebuilt as a **scrolling page**: rows arrive into clear space, the scroll settles on the confirm-and-sign block. |

Clip fit is otherwise clean: every clip is 24 fps, and trims/pads are under 0.4 s
everywhere except the end card, now fixed.

- 2026-09-15 · **v3 master**: 103.6 s, 1080p24, 37 MB. Built from commit `5628e76`.
  URLs in `jobs.json › renders_v3`.
- OPEN: your review of v3; 9:16 Shorts cut; 15 s bumper; thumbnail.

## 10. v4 — captions in the bottom band (2026-09-15)

v3 moved six captions to the top of the frame. Wrong call: a caption card is
opaque, so at the top it still hid the animation, just in a different place.
v4 keeps every caption in the bottom band and lowers it — the card now sits
7.5 % of frame height off the bottom edge instead of 10 % of frame *width*
(192 px), which was riding it up into the action.

Re-measured all thirteen clips against bottom-left / bottom-centre /
bottom-right only. With the lower band, **eleven of thirteen scenes score 0.000
obstruction in all three slots** — the text no longer covers anything, so the
choice became editorial rather than forced:

| | position | why |
|---|---|---|
| sc00–sc06, sc08, sc09, sc11 | **bottom-centre** | bottom strip measures clear; centre is the strongest read, and it is what the games clip wanted |
| sc07 time card | bottom-right | the scrolling page fills the left and centre of the viewport |
| sc10 app | bottom-right | the Google Play badge owns bottom-centre (bc scored 0.961); caption wraps to two lines at 46 px to clear it |
| sc12 end card | bottom-left | Lucas waves in the bottom-right (measured 0.083 bl vs 0.224 br) |

AI clips unchanged — the animation itself was fine.

- 2026-09-15 · **v4 master**: 103.6 s, 1080p24, 37 MB, commit `42264e3`.
  URLs in `jobs.json › renders_v4`.
- OPEN: 9:16 Shorts cut; 15 s bumper; thumbnail.

## 11. v5 — end card logo (2026-09-15)

Only the last slide changed. The badge had been rendering at **114 px** because
badge, wordmark, URL and enroll line were stacked vertically and all four had to
fit the ~416 px band above Lucas's wave; the fit scaled everything to 0.76.

Fix: set the mark as a **horizontal lockup** — badge beside wordmark — which
spends the card's width (1615 px, mostly unused) instead of competing for its
height. Both PNGs are trimmed of transparent margin first so they fill their
boxes, and the URL/enroll type was tightened to hand the spare height to the mark.

| | v4 | v5 |
|---|---|---|
| badge | 114 px | **300 px** (2.6x) |
| wordmark | 532 x 177, below the badge | 377 x 180, beside it |
| lockup | — | 721 px wide, centred at x 581–1302, y 120–420 |

Still clear of Lucas (his extent starts at y 576) and still inside the white
card. Beats: badge lands, wordmark joins at +0.5 s, URL at +1.1 s, enroll line
at +1.7 s.

- 2026-09-15 · **v5 master**: 103.6 s, 1080p24, 37 MB, commit `c0577dc`.
  URLs in `jobs.json › renders_v5`.
- OPEN: 9:16 Shorts cut; 15 s bumper; thumbnail.

