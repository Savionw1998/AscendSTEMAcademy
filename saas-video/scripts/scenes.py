#!/usr/bin/env python3
"""Code-built scenes for the Ascend STEM Academy SaaS video (#3).

Runs inside the Higgsfield sandbox (ffmpeg + Pillow). Every scene renders PNG
frames to a work dir, then ffmpeg packs them into an H.264 mp4 at 1080p24.

    python3 scenes.py timecard OUT.mp4 --dur 9.0
    python3 scenes.py endcard OUT.mp4 --dur 8.0 --lucas lucas_clip.mp4 --badge badge.png --wordmark wordmark.png

UI facts for the time card come from the real /time-card-tracker/ page
(screenshots 2026-09-15): the subject chip list, the 180-day bar, the copy
"Lucas keeps the tally", the blue Send button. Student name is fictional.
"""
import argparse, glob, math, os, shutil, subprocess, sys
from PIL import Image, ImageDraw, ImageFilter, ImageFont

W, H, FPS = 1920, 1080, 24

# Brand palette (PLAN.md §2) + the real app's UI tones from the screenshots.
BLUE = (0, 156, 222)
DEEP = (29, 64, 16)
LIGHT = (191, 224, 180)
INK = (23, 62, 99)
PAPER = (247, 250, 252)
MINT = (238, 248, 230)
CHIP = (232, 245, 224)
CHIP_EDGE = (184, 220, 170)
GREY = (110, 118, 112)
WHITE = (255, 255, 255)
CARD_EDGE = (206, 230, 196)

FONT_DIRS = ["/usr/share/fonts/truetype/higgsfield", "/usr/share/fonts/truetype",
             "/usr/share/fonts", os.path.expanduser("~/.fonts")]


def find_font(names):
    for d in FONT_DIRS:
        for n in names:
            hits = glob.glob(os.path.join(d, "**", n), recursive=True)
            if hits:
                return hits[0]
    return None


BOLD_PATH = find_font(["Montserrat-ExtraBold.ttf", "Poppins-ExtraBold.ttf", "Metropolis-ExtraBold.ttf"])
SEMI_PATH = find_font(["Montserrat-SemiBold.ttf", "Montserrat-Bold.ttf", "Poppins-SemiBold.ttf",
                       "DejaVuSans-Bold.ttf"]) or BOLD_PATH
BODY_PATH = find_font(["Montserrat-Medium.ttf", "Montserrat-Regular.ttf", "Poppins-Regular.ttf",
                       "DejaVuSans.ttf", "LiberationSans-Regular.ttf"]) or SEMI_PATH

_font_cache = {}


def font(kind, size):
    path = {"bold": BOLD_PATH, "semi": SEMI_PATH, "body": BODY_PATH}[kind]
    key = (path, size)
    if key not in _font_cache:
        _font_cache[key] = ImageFont.truetype(path, size)
    return _font_cache[key]


# ---------------------------------------------------------------- easing
def ease_out(t):
    """Confident SaaS easing (close to cubic-bezier(.22,1,.36,1))."""
    t = max(0.0, min(1.0, t))
    return 1 - (1 - t) ** 5


def seg(t, start, end):
    """0→1 progress of t inside [start, end]."""
    if end <= start:
        return 1.0 if t >= end else 0.0
    return max(0.0, min(1.0, (t - start) / (end - start)))


# ---------------------------------------------------------------- drawing
def background():
    """Near-white ground with a soft blue→green wash and a faint grid."""
    img = Image.new("RGB", (W, H), PAPER)
    wash = Image.new("RGB", (W, H), PAPER)
    d = ImageDraw.Draw(wash)
    d.ellipse((-400, -500, 1100, 700), fill=(222, 240, 250))
    d.ellipse((1100, 500, 2500, 1700), fill=(226, 244, 220))
    wash = wash.filter(ImageFilter.GaussianBlur(220))
    img = Image.blend(img, wash, 0.9)
    d = ImageDraw.Draw(img)
    for x in range(0, W, 96):
        d.line((x, 0, x, H), fill=(240, 244, 246), width=1)
    for y in range(0, H, 96):
        d.line((0, y, W, y), fill=(240, 244, 246), width=1)
    return img


def shadow_card(img, box, radius=28, fill=WHITE, edge=CARD_EDGE, alpha=255, lift=18):
    """Floating white card with a long soft shadow; alpha fades the whole card in.
    Works on any surface, not just full frames (the time-card page is a tall canvas)."""
    x0, y0, x1, y1 = box
    if alpha <= 0:
        return
    size = img.size
    sh = Image.new("RGBA", size, (0, 0, 0, 0))
    sd = ImageDraw.Draw(sh)
    sd.rounded_rectangle((x0, y0 + lift, x1, y1 + lift), radius, fill=(23, 62, 99, int(38 * alpha / 255)))
    sh = sh.filter(ImageFilter.GaussianBlur(26))
    img.paste(sh, (0, 0), sh)
    card = Image.new("RGBA", size, (0, 0, 0, 0))
    cd = ImageDraw.Draw(card)
    cd.rounded_rectangle(box, radius, fill=fill + (alpha,), outline=edge + (alpha,), width=2)
    img.paste(card, (0, 0), card)


def text(d, xy, s, kind, size, color, anchor="la"):
    d.text(xy, s, font=font(kind, size), fill=color, anchor=anchor)


def pill(d, box, label, size=30, fill=CHIP, edge=CHIP_EDGE, color=DEEP, kind="semi", lit=0.0):
    """Subject chip. lit 0→1 blends the chip toward brand blue (the 'tapped' state)."""
    f = tuple(int(a + (b - a) * lit) for a, b in zip(fill, BLUE))
    e = tuple(int(a + (b - a) * lit) for a, b in zip(edge, BLUE))
    c = tuple(int(a + (b - a) * lit) for a, b in zip(color, WHITE))
    d.rounded_rectangle(box, (box[3] - box[1]) // 2, fill=f, outline=e, width=2)
    cx, cy = (box[0] + box[2]) // 2, (box[1] + box[3]) // 2
    text(d, (cx, cy), label, kind, size, c, anchor="mm")


def pack(frames_dir, out, dur):
    n = int(round(dur * FPS))
    subprocess.check_call([
        "ffmpeg", "-y", "-loglevel", "error", "-framerate", str(FPS),
        "-i", os.path.join(frames_dir, "f%05d.png"), "-frames:v", str(n),
        "-c:v", "libx264", "-preset", "medium", "-crf", "17", "-pix_fmt", "yuv420p",
        "-movflags", "+faststart", out])


# ================================================================ TIME CARD
SUBJECTS = ["Math", "Science", "Engineering & Tech", "Reading & Language Arts", "Social Studies",
            "Art & Music", "Physical Education", "Elective", "Field Trip", "Independent Study"]
# (chip index, day, hours, time the chip is tapped)
TAPS = [(0, "Mon", 1.5, 1.2), (1, "Mon", 1.0, 2.4), (3, "Tue", 2.0, 3.6), (5, "Tue", 1.0, 4.8)]
STUDENT = "Avery Johnson — Grade 5"
DAYS_START = 41


def scroll_at(t, dur):
    """Piecewise eased scroll of the left-hand page, in page pixels.

    The page moves while rows land so each new row arrives into clear space
    instead of cramming the card, then settles on the confirm + send block.
    """
    keys = [(0.0, 0), (1.4, 0), (3.0, 150), (4.6, 300), (6.0, 430), (7.4, 560), (dur, 560)]
    for (t0, v0), (t1, v1) in zip(keys, keys[1:]):
        if t <= t1:
            return v0 + (v1 - v0) * ease_out(seg(t, t0, t1))
    return keys[-1][1]


def timecard_page(t, dur, pw):
    """The scrolling left-hand column drawn on a tall canvas of width `pw`."""
    ph = 1560
    page = Image.new("RGBA", (pw, ph), (0, 0, 0, 0))
    d = ImageDraw.Draw(page)
    a_head = ease_out(seg(t, 0.0, 0.7))
    a_hours = ease_out(seg(t, 0.45, 1.15))
    pad = 50

    # --- header card
    hy = 20 + int((1 - a_head) * 60)
    shadow_card(page, (0, hy, pw, hy + 300), alpha=int(255 * a_head))
    d = ImageDraw.Draw(page)
    if a_head > 0.05:
        c = tuple(int(255 - (255 - v) * a_head) for v in DEEP)
        g = tuple(int(255 - (255 - v) * a_head) for v in GREY)
        text(d, (pad, hy + 46), "Weekly time card", "bold", 58, c)
        text(d, (pad, hy + 126), "Log your student's instructional hours for the week.", "body", 27, g)
        text(d, (pad, hy + 164), "Add a row for each subject and day. Lucas keeps the tally.", "body", 27, g)
        text(d, (pad, hy + 216), "STUDENT", "semi", 20, g)
        d.rounded_rectangle((pad, hy + 244, pad + 420, hy + 288), 10, fill=CHIP, outline=CHIP_EDGE, width=2)
        text(d, (pad + 18, hy + 266), STUDENT, "body", 24, c, anchor="lm")
        text(d, (pad + 470, hy + 216), "WEEK ENDING", "semi", 20, g)
        d.rounded_rectangle((pad + 470, hy + 244, pad + 750, hy + 288), 10, fill=CHIP, outline=CHIP_EDGE, width=2)
        text(d, (pad + 488, hy + 266), "09 / 18 / 2026", "body", 24, c, anchor="lm")

    # --- hours card: chips, then logged rows with room to breathe
    rows = [(ci, day, hrs, tt) for (ci, day, hrs, tt) in TAPS if ease_out(seg(t, tt + 0.15, tt + 0.55)) > 0]
    card_h = 470 + 66 * len(rows)
    cy = 360 + int((1 - a_hours) * 60)
    shadow_card(page, (0, cy, pw, cy + card_h), alpha=int(255 * a_hours))
    d = ImageDraw.Draw(page)
    logged = []
    if a_hours > 0.05:
        c = tuple(int(255 - (255 - v) * a_hours) for v in DEEP)
        g = tuple(int(255 - (255 - v) * a_hours) for v in GREY)
        text(d, (pad, cy + 40), "Hours logged", "bold", 42, c)
        text(d, (pad, cy + 96), "Tap a subject to start a row.", "body", 25, g)
        x, y, chip_h = pad, cy + 150, 52
        f = font("semi", 24)
        for i, s in enumerate(SUBJECTS):
            w = int(d.textlength(s, font=f)) + 52
            if x + w > pw - pad:
                x, y = pad, y + chip_h + 16
            lit = 0.0
            for (ci, _day, _h, tt) in TAPS:
                if ci == i:
                    lit = ease_out(seg(t, tt, tt + 0.18)) * (1.0 if t < tt + 0.6 else 0.28)
            pill(d, (x, y, x + w, y + chip_h), s, size=24, lit=lit * a_hours)
            x += w + 14
        # logged rows, generously spaced
        ry = y + chip_h + 36
        for (ci, day, hrs, tt) in TAPS:
            k = ease_out(seg(t, tt + 0.15, tt + 0.55))
            if k <= 0:
                continue
            logged.append((ci, hrs, day))
            ox, alpha = int((1 - k) * 44), int(255 * k)
            row = Image.new("RGBA", (pw, ph), (0, 0, 0, 0))
            rd = ImageDraw.Draw(row)
            rd.rounded_rectangle((pad + ox, ry, pw - pad + ox, ry + 54), 12, fill=MINT + (alpha,),
                                 outline=CHIP_EDGE + (alpha,), width=2)
            rd.text((pad + 22 + ox, ry + 27), SUBJECTS[ci], font=font("semi", 25), fill=DEEP + (alpha,), anchor="lm")
            rd.text((pad + 520 + ox, ry + 27), day, font=font("body", 24), fill=GREY + (alpha,), anchor="lm")
            rd.text((pw - pad - 22 + ox, ry + 27), f"{hrs:.1f} h", font=font("bold", 25), fill=BLUE + (alpha,), anchor="rm")
            page.alpha_composite(row)
            d = ImageDraw.Draw(page)
            ry += 66

    # --- confirm + send block, revealed by the scroll
    by = cy + card_h + 40
    a_conf = ease_out(seg(t, 6.0, 6.8))
    if a_conf > 0.02:
        shadow_card(page, (0, by, pw, by + 190), alpha=int(255 * a_conf))
        d = ImageDraw.Draw(page)
        c = tuple(int(255 - (255 - v) * a_conf) for v in DEEP)
        g = tuple(int(255 - (255 - v) * a_conf) for v in GREY)
        checked = ease_out(seg(t, 7.0, 7.3)) > 0.5
        d.rounded_rectangle((pad, by + 40, pad + 34, by + 74), 7,
                            fill=BLUE if checked else WHITE, outline=BLUE if checked else CHIP_EDGE, width=2)
        if checked:
            d.line((pad + 8, by + 57, pad + 15, by + 65, pad + 26, by + 49), fill=WHITE, width=4, joint="curve")
        text(d, (pad + 52, by + 42), "I confirm these hours are an accurate record", "body", 25, c)
        text(d, (pad + 52, by + 74), "of my student's instruction for this week.", "body", 25, c)
        text(d, (pad, by + 122), "SIGNED", "semi", 20, g)
        d.rounded_rectangle((pad, by + 146, pad + 420, by + 176), 8, fill=CHIP, outline=CHIP_EDGE, width=2)
        text(d, (pad + 16, by + 161), "Ascend Academy", "body", 22, c, anchor="lm")
    return page, ph


def timecard_frame(t, dur):
    img = background()
    a_right = ease_out(seg(t, 0.25, 0.95))

    # --- left column: the scrolling page, clipped to a viewport
    vx0, vx1, vy0, vy1 = 150, 1100, 40, 1040
    page, ph = timecard_page(t, dur, vx1 - vx0)
    off = int(scroll_at(t, dur))
    win = page.crop((0, off, page.width, min(ph, off + (vy1 - vy0))))
    img.paste(win, (vx0, vy0), win)
    d = ImageDraw.Draw(img)

    # --- right column: the tally card, fixed while the page scrolls
    rx0, rx1 = 1170, 1770
    ry0 = 70 + int((1 - a_right) * 60)
    shadow_card(img, (rx0, ry0, rx1, ry0 + 560), alpha=int(255 * a_right))
    d = ImageDraw.Draw(img)
    rows = [(ci, hrs) for (ci, day, hrs, tt) in TAPS if ease_out(seg(t, tt + 0.15, tt + 0.55)) > 0]
    if a_right > 0.05:
        c = tuple(int(255 - (255 - v) * a_right) for v in DEEP)
        g = tuple(int(255 - (255 - v) * a_right) for v in GREY)
        b = tuple(int(255 - (255 - v) * a_right) for v in BLUE)
        # Hours + days react to the taps.
        hours = sum(h for (_ci, h) in rows)
        days_logged = len({day for (ci, day, hrs, tt) in TAPS if seg(t, tt + 0.15, tt + 0.55) > 0})
        days_total = DAYS_START + days_logged
        text(d, (rx0 + 44, ry0 + 46), "Avery Johnson", "bold", 34, c)
        text(d, (rx1 - 44, ry0 + 58), f"{days_total} of 180 days", "body", 25, g, anchor="rm")
        # progress bar
        bx0, bx1, by = rx0 + 44, rx1 - 44, ry0 + 112
        d.rounded_rectangle((bx0, by, bx1, by + 30), 15, fill=MINT, outline=CHIP_EDGE, width=2)
        frac = days_total / 180.0
        fw = int((bx1 - bx0) * frac)
        d.rounded_rectangle((bx0, by, bx0 + max(30, fw), by + 30), 15, fill=BLUE)
        text(d, (bx0, by + 52), f"{180 - days_total} days left this period", "semi", 24, c)
        text(d, (bx1, by + 52), "Jun 30 – Jun 29", "body", 24, g, anchor="ra")
        # Big number: tween toward the current total.
        text(d, ((rx0 + rx1) // 2, ry0 + 300), f"{hours:.1f}".rstrip("0").rstrip("."), "bold", 150, b, anchor="mm")
        text(d, ((rx0 + rx1) // 2, ry0 + 395), "hours on this card", "semi", 28, c, anchor="mm")
        text(d, ((rx0 + rx1) // 2, ry0 + 450), f"{days_logged} day{'s' if days_logged != 1 else ''} logged", "body", 26, g, anchor="mm")
    return img


# ================================================================ END CARD
def endcard_frame(t, dur, badge, wordmark):
    """Badge + wordmark + URL settle onto a white card. The Lucas peek is composited
    by assemble.py from the AI clip (keyed by card edge), so this draws the card only."""
    img = background()
    d = ImageDraw.Draw(img)
    a = ease_out(seg(t, 0.0, 0.8))
    cy = 80 + int((1 - a) * 50)
    shadow_card(img, (240, cy, 1680, cy + 820), radius=36, alpha=int(255 * a))
    # Badge drops in, then wordmark, then URL, then the enroll line.
    kb = ease_out(seg(t, 0.5, 1.2))
    if kb > 0 and badge is not None:
        s = int(300 * (0.85 + 0.15 * kb))
        b = badge.resize((s, s), Image.LANCZOS)
        img.paste(b, (960 - s // 2, cy + 90 + int((1 - kb) * 30)), b)
    kw = ease_out(seg(t, 1.0, 1.8))
    if kw > 0 and wordmark is not None:
        ww = 760
        wh = int(wordmark.height * ww / wordmark.width)
        wm = wordmark.resize((ww, wh), Image.LANCZOS)
        layer = Image.new("RGBA", (W, H), (0, 0, 0, 0))
        layer.paste(wm, (960 - ww // 2, cy + 410 + int((1 - kw) * 30)), wm)
        layer.putalpha(layer.getchannel("A").point(lambda v: int(v * kw)))
        img.paste(layer, (0, 0), layer)
    d = ImageDraw.Draw(img)
    ku = ease_out(seg(t, 1.7, 2.4))
    if ku > 0:
        c = tuple(int(255 - (255 - v) * ku) for v in INK)
        text(d, (960, cy + 660 + int((1 - ku) * 20)), "ascendstemacademy.com", "bold", 60, c, anchor="mm")
    ke = ease_out(seg(t, 2.3, 3.0))
    if ke > 0:
        c = tuple(int(255 - (255 - v) * ke) for v in DEEP)
        text(d, (960, cy + 740 + int((1 - ke) * 20)), "Enroll in about five minutes.", "semi", 36, c, anchor="mm")
    return img


# ================================================================ main
def render(fn, out, dur, **kw):
    work = out + "_frames"
    shutil.rmtree(work, ignore_errors=True)
    os.makedirs(work)
    n = int(round(dur * FPS))
    for i in range(n):
        fn(i / FPS, dur, **kw).save(os.path.join(work, f"f{i:05d}.png"))
        if i % 24 == 0:
            print(f"  {os.path.basename(out)}: {i}/{n}", flush=True)
    pack(work, out, dur)
    shutil.rmtree(work, ignore_errors=True)
    print("wrote", out)


if __name__ == "__main__":
    ap = argparse.ArgumentParser()
    ap.add_argument("scene", choices=["timecard", "endcard", "still"])
    ap.add_argument("out")
    ap.add_argument("--dur", type=float, default=9.0)
    ap.add_argument("--badge")
    ap.add_argument("--wordmark")
    ap.add_argument("--t", type=float, default=None, help="still: render one frame at t")
    a = ap.parse_args()
    print("fonts:", BOLD_PATH, SEMI_PATH, BODY_PATH)
    if a.scene == "timecard":
        render(timecard_frame, a.out, a.dur)
    elif a.scene == "endcard":
        badge = Image.open(a.badge).convert("RGBA") if a.badge else None
        wm = Image.open(a.wordmark).convert("RGBA") if a.wordmark else None
        render(endcard_frame, a.out, a.dur, badge=badge, wordmark=wm)
    else:  # a quick single PNG for eyeballing layout
        timecard_frame(a.t if a.t is not None else 6.0, a.dur).save(a.out)
        print("wrote", a.out)
