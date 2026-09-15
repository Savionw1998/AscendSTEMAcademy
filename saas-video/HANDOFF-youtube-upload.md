# Handoff prompt — publish SaaS Explainer v5 to YouTube

Paste everything between the `---` rules into a **Claude Code session that has browser
control** (the Chrome extension / a local session with a browser tool). The cloud session
that built the video cannot reach youtube.com — its egress proxy blocks it — so this step
has to run somewhere with a real browser.

---

You have browser control and I need you to publish a finished marketing video to YouTube,
then report back the watch URL. Work in the open tab / a new tab as needed. Do not skip the
visibility step — this one must end up **Public**.

## 1. Get the file

Download this MP4 to disk first (curl/wget is fine, or the browser's own download):

https://d2ol7oe51mr4n9.cloudfront.net/user_3IlwAF1RJeeZloQfJKUEXDdLRVb/01f6afe4-c215-4110-b0c7-0d159b177206.mp4

Expect: 103.6 s · 1920x1080 · 24 fps · H.264 + AAC · ~37 MB. Save it as
`Ascend-STEM-Academy-SaaS-Explainer-v5.mp4`. If the download comes back much smaller than
37 MB or won't play, stop and tell me — don't upload a truncated file.

## 2. Upload it

Go to https://studio.youtube.com on the **Ascend STEM Academy** channel (if more than one
channel/brand account is available, confirm with me before picking). Create → Upload videos →
select the file.

## 3. Fill in the details exactly

**Title** — use this one:

```
Ascend STEM Academy — Florida Homeschooling, All in One Place
```

**Description** — paste verbatim, including the blank lines and the bullet characters:

```
Homeschooling in Florida comes with a filing cabinet attached. Ascend STEM Academy puts the whole thing in one place.

We're a Florida private umbrella school built for homeschooling families. Enrollment takes about five minutes — and from there we handle the notices of intent, withdrawal letters, health forms and immunization records, so you don't have to.

Inside your dashboard:
• Time Card Tracker — log school hours by subject and watch progress toward Florida's requirements fill in on its own
• The Living Transcript — type one sentence about today, or just send a photo, and we turn it into a standards-mapped transcript your student keeps as a portfolio at graduation
• Four daily STEM-a-lotl puzzles, streaks and badges
• 60+ curated homeschool resources, quick actions and enrollment status at a glance

And it's all in your pocket — Ascend STEM Academy is now on the Google Play Store:
https://play.google.com/store/apps/details?id=com.ascendstemacademy.twa

Take FLVS Flex courses. Earn college credit in high school through our dual enrollment partnership with the College of Central Florida. And graduate with a real diploma — our transcripts and diplomas are no different than a traditional private school's.

Enroll in about five minutes: https://ascendstemacademy.com

CHAPTERS
0:00 The paperwork problem
0:10 Enroll in about five minutes
0:25 Real support & your welcome packet
0:42 Inside your dashboard
0:58 The Living Transcript
1:18 Google Play, dual enrollment & your diploma

—
Ascend STEM Academy, LLC · Florida private umbrella school for homeschooling families
Website: https://ascendstemacademy.com
Android app: https://play.google.com/store/apps/details?id=com.ascendstemacademy.twa
```

The chapter timestamps are real and YouTube-legal (first at 0:00, each ≥10 s). Don't
reorder or reword them.

**Audience:** No, it's not made for kids. (The video is addressed to parents.)

**Show more →**
- Tags: `homeschool florida, florida umbrella school, umbrella school, homeschooling, florida homeschool laws, notice of intent florida, homeschool transcript, dual enrollment florida, FLVS, College of Central Florida, homeschool records, STEM homeschool, ascend stem academy`
- Category: **Education**
- Language: English
- Comments: leave at the channel default
- Playlist: if a playlist named "Ascend STEM Academy" exists, add it. If not, create one with
  that name and add this video.

**Thumbnail:** there is no custom thumbnail yet. Let YouTube auto-pick for now and tell me —
the video opens on a tower of paperwork, which is a weak first frame, so we'll likely want a
custom one later.

## 4. Visibility

Set it to **Public** and publish. Do not leave it Unlisted or scheduled.

## 5. Report back

Send me:
- the watch URL (`https://www.youtube.com/watch?v=...`)
- the video ID on its own
- confirmation that visibility reads Public in Studio after publishing
- which channel it went up on

## 6. If you also have the WordPress connector for ascendstemacademy.com

Optional — only if the tools are there. Otherwise just hand me the URL and I'll do it.

Embed the video near the top of the **home page** of ascendstemacademy.com using a
`core/embed` block:

```html
<!-- wp:embed {"url":"https://www.youtube.com/watch?v=VIDEO_ID","type":"video","providerNameSlug":"youtube","responsive":true,"className":"wp-embed-aspect-16-9 wp-has-aspect-ratio"} -->
<figure class="wp-block-embed is-type-video is-provider-youtube wp-block-embed-youtube wp-embed-aspect-16-9 wp-has-aspect-ratio"><div class="wp-block-embed__wrapper">
https://www.youtube.com/watch?v=VIDEO_ID
</div></figure>
<!-- /wp:embed -->
```

Read the page's current content first, insert the block rather than replacing anything, and
show me the diff before saving.

## Context you may need

- This is video #3 for Ascend STEM Academy, a SaaS-style product explainer. It covers
  five-minute enrollment, document handling, the dashboard (time card tracker, Living
  Transcript, games), the Google Play app, FLVS + College of Central Florida dual
  enrollment, and the diploma.
- **v5 is the right cut.** A v6 exists with the Ascend badge printed on the welcome-packet
  T-shirt; it is *not* the one to publish. Don't substitute it.
- Source project: `Savionw1998/AscendSTEMAcademy`, branch
  `claude/ascend-stem-marketing-video-4x0bv0`, folder `saas-video/` — `PLAN.md` there has the
  full storyboard, the verified-fact table behind every claim in the description, and the
  render history.

---
