# Continuation prompt — Ascend STEM Academy video #3 ("The Platform")

Paste everything below the rule into a fresh Claude Code session to pick the project up.
It is self-contained: it assumes no memory of the conversation that built the video.

---

Continue work on Ascend STEM Academy's third marketing video — a SaaS-style product
explainer. Everything you need is already in the repo; read it before you touch anything.

**Repo:** `Savionw1998/AscendSTEMAcademy`
**Branch:** `claude/ascend-stem-marketing-video-4x0bv0` (develop and push here only)
**Folder:** `saas-video/`

## Read these first, in this order

1. `saas-video/PLAN.md` — the spine. Storyboard, VO script, a verified-fact table citing the
   live site for every product claim, the look spec, the pipeline, and a status log running
   §8 → §13 that records what changed in each render and why.
2. `saas-video/manifest.json` — the per-scene render spec: 13 scenes, each with its clip URL,
   VO file, duration, caption text/position/size, and any per-scene flags
   (`build`, `min`, `play_badge`, `shirt_badge`). The `assets` block holds the logo,
   wordmark, diploma, music, fonts and the Play Store badge/URL.
3. `saas-video/jobs.json` — every Higgsfield job ID already spent: 13 style frames, 13 Helena
   VO takes, 13 Seedance clips, and the media IDs + download URLs of every render v1–v6.
   **Check this before generating anything.** Most of what you might reach for already exists.
4. `saas-video/scripts/` — `assemble.py` (the renderer), `scenes.py` (code-built scenes: the
   scrolling time card and the end card), `qa.py` (the numerical QA harness).

## Where it stands

| | |
|---|---|
| **v5 — the publish cut** | https://d2ol7oe51mr4n9.cloudfront.net/user_3IlwAF1RJeeZloQfJKUEXDdLRVb/01f6afe4-c215-4110-b0c7-0d159b177206.mp4 · 103.6 s · 1080p24 · 37 MB · commit `c0577dc` |
| **v6 — not the publish cut** | same length, adds the Ascend badge printed on the welcome-packet T-shirt. Exists on request; the user chose v5, where the shirt is deliberately blank. |

Six renders, each a single targeted change: v2 added the music bed and Play badge, v3 was a
QA polish pass, v4 moved every caption to the bottom third, v5 enlarged the end-card logo
into a horizontal lockup, v6 tracked the badge onto the moving shirt. The user has approved
the animation — **do not regenerate any AI clip** unless they ask.

## Open work

1. **YouTube upload of v5 as Public** — blocked in the cloud container (no YouTube connector,
   and the egress proxy 403s youtube.com). `saas-video/HANDOFF-youtube-upload.md` is a
   ready-to-paste prompt for a browser-capable session, carrying the title, the verbatim
   description with YouTube-legal chapter marks, tags, category, audience and the Public step.
2. **Site embed** — once the watch URL exists, embed it on ascendstemacademy.com through the
   WordPress connector (`wp_get_page` → `wp_update_page`, plus `wp_update_seo_meta`). The user
   hasn't yet said which page: home, enrollment, or about. Ask.
3. **9:16 Shorts cut (~35 s)** — scenes 0, 2, 8, 9, 10, 12. Captions must be re-laid out for
   the taller frame, not just cropped.
4. **15 s bumper** — scenes 0, 2, 10, 12.
5. **Thumbnail** — none exists. The video opens on a tower of paperwork, so YouTube's
   auto-pick is a weak first frame.
6. **The two YouTube reference links** the user sent (`M8tXsQ34f30`, `aSte18D2_YE`) were never
   viewable from the container. The look is an interpretation of "SaaS marketing video," not a
   match to them. If the user can describe or transcribe those videos, the look may want a
   re-cut.

## How this project renders — read before you run anything

The container's egress proxy blocks `*.cloudfront.net`, `youtube.com` and
`ascendstemacademy.com`. Three consequences that shape everything:

- **You cannot watch the video.** No generated frame or clip can be pulled into the
  container. Every quality judgement so far was made numerically, by `qa.py`, or by the user
  looking at the Higgsfield gallery. Don't claim you've reviewed a cut. If you need to know
  whether something looks right, measure it or ask.
- **Rendering happens in the Higgsfield sandbox**, not locally, via
  `mcp__Higgsfield__sandbox_exec`. It has ffmpeg 5.1, Pillow 12, ImageMagick, faster-whisper
  and Montserrat ExtraBold, and it can reach the CDN. It is ephemeral — it dies ~10 s after a
  call returns — so a render must be one command chained with `&&`, or use the tool's own
  `background: true` (15-minute lease; do not nest `nohup ... &` inside it, that returns an
  empty log). Each render ends by uploading its output through `media_upload` → `curl PUT` →
  `media_confirm`.
- **Ship scripts to the sandbox by commit SHA** from raw.githubusercontent.com. Branch URLs
  are cached ~5 minutes and will serve you a stale manifest exactly once, at the worst moment.

Facts about the product come from the WordPress MCP (`wp_get_page`, `wp_search`), not from
memory and not from the live site — the site is unreachable by HTTP from here. PLAN.md §1
cites the page behind every claim; keep that table honest if you add a claim.

## Gotchas already paid for

- `-shortest` truncates the master (picture outruns audio); use `apad=whole_dur=<total>`.
- `sidechaincompress` consumes the `[vo]` label — `asplit` it first.
- `scenes.render()` prints to stdout, so QA writes its report to a file, not to a pipe.
- Object isolation in a frame needs ImageMagick connected components, not a dark-pixel
  bounding box — the first shirt measurement swallowed the shirt's own drop shadow.
- The caption bottom margin is 7.5 % of frame **height**. It was briefly 10 % of frame
  *width*, which pushed every caption into the middle of the animation; that was the single
  worst bug in the project.

## Working style the user has asked for

Captions bottom-third only — left, right or centre, never top. Lucas is a cameo, twice, and
never speaks. One change per render, and record it in a new numbered section of PLAN.md with
the measurements behind it. Commit and push to the branch above; do not open a pull request
unless asked.

Start by reading PLAN.md and telling me which of the open items you want to take.

---
