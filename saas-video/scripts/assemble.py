#!/usr/bin/env python3
"""Assemble the Ascend STEM Academy SaaS video (#3) inside the Higgsfield sandbox.

    python3 assemble.py manifest.json --master master.mp4 --preview preview.mp4 \
        [--put-master URL] [--put-preview URL] [--scenes a,b,c]

manifest.json:
{
  "assets":  {"badge": URL, "wordmark": URL, "music": URL|null,
              "font_medium": URL, "font_semibold": URL},
  "scenes": [
    {"id": "sc00", "clip": URL,             "vo": URL, "vo_dur": 3.76,
     "captions": [{"text": "…", "at": 0.3, "pos": "bl"}], "min": 4.5},
    {"id": "sc07", "build": "timecard",     "vo": URL, "vo_dur": 8.18, ...},
    {"id": "sc12", "build": "endcard", "clip": URL, ...}
  ]
}

Per scene the clip is trimmed, or freeze-padded on its last frame, to
max(vo_dur + TAIL, min). Captions are PIL cards overlaid with ffmpeg and fade
in at their `at` time. VO lines are placed on the global timeline; the music
bed loops underneath with sidechain ducking. Nothing here needs a display.
"""
import argparse, json, os, shutil, subprocess, sys
from PIL import Image, ImageDraw, ImageFilter, ImageFont

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import scenes  # noqa: E402  (fonts, palette, code-built scenes)

W, H, FPS = 1920, 1080, 24
TAIL = 0.9          # seconds of picture after the VO line ends
VO_LEAD = 0.35      # VO starts this long after the cut
MUSIC_GAIN = 0.16
WORK = "work"


def sh(*cmd, quiet=True):
    if not quiet:
        print("+", " ".join(cmd), flush=True)
    subprocess.check_call(cmd)


def fetch(url, dest):
    if os.path.exists(dest) and os.path.getsize(dest) > 0:
        return dest
    sh("curl", "-sfL", "--retry", "3", "-o", dest, url)
    return dest


def probe_dur(path):
    out = subprocess.check_output(["ffprobe", "-v", "error", "-show_entries", "format=duration",
                                   "-of", "csv=p=0", path]).decode().strip()
    return float(out)


# ---------------------------------------------------------------- captions
def wrap(text, f, maxw, d):
    words, lines, cur = text.split(), [], ""
    for w in words:
        trial = (cur + " " + w).strip()
        if d.textlength(trial, font=f) <= maxw or not cur:
            cur = trial
        else:
            lines.append(cur)
            cur = w
    if cur:
        lines.append(cur)
    return lines


def caption_png(text, path, size=54, maxw=980):
    """White card, soft shadow, ink text, brand-blue accent bar. Alpha PNG at full frame size
    so ffmpeg can overlay it at (0,0); returns the card's box for positioning."""
    layer = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    d = ImageDraw.Draw(layer)
    f = scenes.font("bold", size)
    lines = wrap(text, f, maxw, d)
    lh = int(size * 1.22)
    tw = max(int(d.textlength(l, font=f)) for l in lines)
    pad_x, pad_y = 44, 30
    cw, ch = tw + pad_x * 2 + 14, lh * len(lines) + pad_y * 2
    return lines, f, lh, cw, ch, pad_x, pad_y


# Captions live in the bottom band only — a card over the middle of the frame
# hides the animation it is describing.
CAPTION_POSITIONS = ("bl", "br", "bc")
MX = int(W * 0.10)          # 10 % side margin (title-safe)
MY = int(H * 0.075)         # lower than the sides, so the card hugs the bottom edge


def caption_box(text, pos, size=54, maxw=980):
    """(x0, y0, cw, ch) of the caption card for a position code."""
    lines, f, lh, cw, ch, pad_x, pad_y = caption_png(text, None, size, maxw)
    y0 = H - MY - ch
    if pos == "br":
        return W - MX - cw, y0, cw, ch
    if pos == "bc":
        return (W - cw) // 2, y0, cw, ch
    return MX, y0, cw, ch       # bl, and the default for anything else


def render_caption(text, pos, path, size=54, maxw=980):
    lines, f, lh, _cw, _ch, pad_x, pad_y = caption_png(text, path, size, maxw)
    layer = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    x0, y0, cw, ch = caption_box(text, pos, size, maxw)
    sh_ = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    ImageDraw.Draw(sh_).rounded_rectangle((x0, y0 + 14, x0 + cw, y0 + ch + 14), 22, fill=(23, 62, 99, 60))
    sh_ = sh_.filter(ImageFilter.GaussianBlur(18))
    layer.alpha_composite(sh_)
    d = ImageDraw.Draw(layer)
    d.rounded_rectangle((x0, y0, x0 + cw, y0 + ch), 22, fill=(255, 255, 255, 246))
    d.rounded_rectangle((x0, y0 + 18, x0 + 10, y0 + ch - 18), 5, fill=scenes.BLUE + (255,))
    y = y0 + pad_y
    for l in lines:
        d.text((x0 + pad_x + 14, y), l, font=f, fill=scenes.INK + (255,))
        y += lh
    layer.save(path)
    return path


# ---------------------------------------------------------------- end card overlay
def pink_bbox(img):
    """Bounding box of Lucas's pink pixels in an RGB frame (None if absent)."""
    px = img.load()
    xs, ys = [], []
    for y in range(0, img.height, 4):
        for x in range(0, img.width, 4):
            r, g, b = px[x, y]
            if r > 200 and 120 < g < 200 and b > 150 and r - g > 40:
                xs.append(x); ys.append(y)
    if len(xs) < 40:
        return None
    return min(xs), min(ys), max(xs), max(ys)


def white_card_bbox(img):
    """Largest central run of near-white rows/cols = the end card's interior."""
    g = img.convert("L")
    px = g.load()
    cx, cy = img.width // 2, img.height // 2
    def run(axis):
        lo, hi = (cx, cx) if axis == 0 else (cy, cy)
        while lo > 0 and (px[lo, cy] if axis == 0 else px[cx, lo]) > 244:
            lo -= 1
        n = img.width if axis == 0 else img.height
        while hi < n - 1 and (px[hi, cy] if axis == 0 else px[cx, hi]) > 244:
            hi += 1
        return lo, hi
    x0, x1 = run(0)
    y0, y1 = run(1)
    return x0, y0, x1, y1


def sample_frames(clip, n, size=(960, 540)):
    """n evenly spaced RGB frames from a clip, in one ffmpeg pass."""
    d = os.path.join(WORK, "_scan")
    shutil.rmtree(d, ignore_errors=True)
    os.makedirs(d)
    dur = probe_dur(clip)
    times = [min(dur - 0.05, k * dur / max(1, n - 1)) for k in range(n)]
    sel = "+".join(f"lt(abs(t-{t:.3f}),0.021)" for t in times)
    sh("ffmpeg", "-y", "-loglevel", "error", "-i", clip, "-vf",
       f"select='{sel}',scale={size[0]}:{size[1]}", "-vsync", "0", os.path.join(d, "f%04d.png"))
    return [Image.open(os.path.join(d, f)).convert("RGB") for f in sorted(os.listdir(d))]


def lucas_extent(clip, n=40):
    """Lucas's bounding box across the WHOLE clip, in full-frame coordinates.

    Frame 0 is not enough: he rises as he waves, so anything placed using only
    his starting position gets overlapped later in the shot.
    """
    boxes = [pink_bbox(f) for f in sample_frames(clip, n)]
    boxes = [b for b in boxes if b]
    if not boxes:
        return None
    return (min(b[0] for b in boxes) * 2, min(b[1] for b in boxes) * 2,
            max(b[2] for b in boxes) * 2, max(b[3] for b in boxes) * 2)


def endcard_overlays(clip, first_frame_png, badge_path, wordmark_path, diploma_path, outdir):
    """Lay the diploma beat, then the official badge, wordmark, URL and enroll line on the
    AI end-card clip, inside its white card and clear of Lucas's full wave.
    Returns [(png, start, end)]."""
    base = Image.open(first_frame_png).convert("RGB")
    cx0, cy0, cx1, cy1 = white_card_bbox(base)
    if cx1 - cx0 < 600 or cy1 - cy0 < 300:   # detection failed → assume 70 % card
        cx0, cy0, cx1, cy1 = int(W * 0.15), int(H * 0.15), int(W * 0.85), int(H * 0.85)
    lb = lucas_extent(clip) or pink_bbox(base)
    floor = (lb[1] - 40) if lb else cy1 - 40
    top = cy0 + 40
    avail = max(360, floor - top)
    print(f"  endcard: card=({cx0},{cy0},{cx1},{cy1}) lucas_full={lb} content band {top}..{floor}")
    items = []
    def layer():
        return Image.new("RGBA", (W, H), (0, 0, 0, 0))
    mid = (cx0 + cx1) // 2
    # Beat 1 (0.2–3.0 s): the real diploma, fitted into the band.
    if diploma_path:
        dip = Image.open(diploma_path).convert("RGBA")
        dw = min(cx1 - cx0 - 120, int(avail * dip.width / dip.height))
        dh = int(dip.height * dw / dip.width)
        dip = dip.resize((dw, dh), Image.LANCZOS)
        l = layer()
        sh_ = layer()
        ImageDraw.Draw(sh_).rectangle((mid - dw // 2, top + (avail - dh) // 2 + 16, mid + dw // 2,
                                       top + (avail - dh) // 2 + dh + 16), fill=(23, 62, 99, 70))
        l.alpha_composite(sh_.filter(ImageFilter.GaussianBlur(20)))
        l.paste(dip, (mid - dw // 2, top + (avail - dh) // 2), dip)
        p = os.path.join(outdir, "ov_diploma.png"); l.save(p); items.append((p, 0.2, 3.0))
    t0 = 3.2 if diploma_path else 0.5
    # Beat 2: the logo as a HORIZONTAL lockup — badge beside wordmark — then URL and the
    # enroll line under it.
    #
    # Stacking badge / wordmark / URL / enroll vertically forced everything through the
    # narrow band above Lucas (~416 px) and scaled the badge down to ~114 px. Setting the
    # mark across the card's width instead spends the axis we have plenty of, so the badge
    # renders around 280 px — roughly 2.5x — with the wordmark at full strength beside it.
    badge = Image.open(badge_path).convert("RGBA")
    wm = Image.open(wordmark_path).convert("RGBA")
    if badge.getbbox():
        badge = badge.crop(badge.getbbox())     # trim transparent margin so the mark fills its box
    if wm.getbbox():
        wm = wm.crop(wm.getbbox())
    url_s, en_s = 50, 30
    gap_lock, gap_url = 24, 12
    # Height budget: the lockup row plus the two text lines must fit the band above Lucas,
    # so every pixel not spent on type goes to the mark.
    lock_h = avail - (url_s + en_s + gap_lock + gap_url)
    lock_h = max(150, min(lock_h, int(avail * 0.78)))
    bs = lock_h                                  # badge is square and sets the row height
    wm_h = int(lock_h * 0.60)                    # wordmark reads at ~60 % of the badge's height
    wm_w = int(wm.width * wm_h / wm.height)
    inner = 44                                   # space between badge and wordmark
    lock_w = bs + inner + wm_w
    max_w = cx1 - cx0 - 120
    if lock_w > max_w:                           # too wide for the card → scale the row down
        k = max_w / lock_w
        bs, wm_w, wm_h, lock_w = int(bs * k), int(wm_w * k), int(wm_h * k), max_w
    total = bs + gap_lock + url_s + gap_url + en_s
    y = top + max(0, (avail - total) // 2)
    lx = mid - lock_w // 2
    print(f"  endcard logo: badge {bs}px  wordmark {wm_w}x{wm_h}  lockup {lock_w}px @ x{lx} y{y}")
    # Badge lands first, wordmark joins it, then the two text lines.
    l = layer()
    b = badge.resize((bs, bs), Image.LANCZOS)
    l.paste(b, (lx, y), b)
    p = os.path.join(outdir, "ov_badge.png"); l.save(p); items.append((p, t0, None))
    l = layer()
    w = wm.resize((wm_w, wm_h), Image.LANCZOS)
    l.paste(w, (lx + bs + inner, y + (bs - wm_h) // 2), w)      # optically centred on the badge
    p = os.path.join(outdir, "ov_wordmark.png"); l.save(p); items.append((p, t0 + 0.5, None))
    y += bs + gap_lock
    l = layer(); ImageDraw.Draw(l).text((mid, y + url_s // 2), "ascendstemacademy.com",
                                        font=scenes.font("bold", url_s), fill=scenes.INK + (255,), anchor="mm")
    p = os.path.join(outdir, "ov_url.png"); l.save(p); items.append((p, t0 + 1.1, None)); y += url_s + gap_url
    l = layer(); ImageDraw.Draw(l).text((mid, y + en_s // 2), "Enroll in about five minutes.",
                                        font=scenes.font("semi", en_s), fill=scenes.DEEP + (255,), anchor="mm")
    p = os.path.join(outdir, "ov_enroll.png"); l.save(p); items.append((p, t0 + 1.7, None))
    return items


def dark_pill_bbox(img, y_min_frac=0.5):
    """Bounding box of the dark app-store placeholder pill in the lower half of a frame."""
    g = img.convert("L")
    px = g.load()
    W_, H_ = img.size
    rows = [y for y in range(int(H_ * y_min_frac), H_, 2)
            if sum(1 for x in range(0, W_, 4) if px[x, y] < 70) > 25]
    if not rows:
        return None
    y0, y1 = min(rows), max(rows)
    cols = [x for x in range(0, W_, 2)
            if sum(1 for y in range(y0, y1 + 1, 4) if px[x, y] < 70) > max(3, (y1 - y0) // 12)]
    if not cols or (max(cols) - min(cols)) < 120 or (y1 - y0) < 40:
        return None
    return min(cols), y0, max(cols), y1


def pill_track(clip, n=24):
    """The placeholder pill's bbox at the clip's start and end, in full-frame coords.

    The phone drifts, so a badge pinned to frame 0 lets the dark pill peek out
    from under it later in the shot. Returns (first, last) or None.
    """
    fr = sample_frames(clip, n)
    found = [(k, dark_pill_bbox(f)) for k, f in enumerate(fr)]
    found = [(k, tuple(v * 2 for v in b)) for k, b in found if b]
    if not found:
        return None
    return found[0][1], found[-1][1]


def play_badge_overlay(clip, first_frame_png, badge_path, outdir):
    """Fit the official Google Play badge over the placeholder pill in scene 10.
    Returns (png, dx, dy) — the drift the overlay must follow across the clip."""
    base = Image.open(first_frame_png).convert("RGB")
    track = pill_track(clip)
    bb = dark_pill_bbox(base)
    if track:
        bb = track[0]
        dx, dy = track[1][0] - track[0][0], track[1][1] - track[0][1]
    else:
        dx = dy = 0
    if bb is None:
        bb = (760, 850, 1160, 970)          # fallback: centred under a centred phone
        dx = dy = 0
    x0, y0, x1, y1 = bb
    print(f"  play badge: pill={bb} drift=({dx},{dy})")
    badge = Image.open(badge_path).convert("RGBA")
    bbox = badge.getbbox()
    badge = badge.crop(bbox) if bbox else badge
    ph = int((y1 - y0) * 1.12)
    pw = int(badge.width * ph / badge.height)
    if pw > (x1 - x0) * 1.15:
        pw = int((x1 - x0) * 1.15); ph = int(badge.height * pw / badge.width)
    badge = badge.resize((pw, ph), Image.LANCZOS)
    layer = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    cx, cy = (x0 + x1) // 2, (y0 + y1) // 2
    # Soft mask hides the pill's own edges around the badge.
    ImageDraw.Draw(layer).rounded_rectangle((cx - pw // 2 - 8, cy - ph // 2 - 8, cx + pw // 2 + 8, cy + ph // 2 + 8),
                                            18, fill=(247, 250, 252, 255))
    layer.paste(badge, (cx - pw // 2, cy - ph // 2), badge)
    p = os.path.join(outdir, "ov_playbadge.png")
    layer.save(p)
    return p, dx, dy


def find_garment(frame, window, lum_max=150, sat_max=60):
    """Bbox of the dark, low-saturation garment inside `window` (full-frame coords).

    The welcome-packet shirt is the only neutral-dark object in that shot — the door
    and the mat are both strongly coloured — so thresholding on luminance AND
    saturation isolates it without touching anything else.
    """
    x0, y0, x1, y1 = window
    crop = frame.crop(window)
    lum = crop.convert("L").point(lambda v: 255 if v < lum_max else 0)
    sat = crop.convert("HSV").getchannel("S").point(lambda v: 255 if v < sat_max else 0)
    bb = ImageChops.darker(lum, sat).getbbox()
    if not bb:
        return None
    w, h = bb[2] - bb[0], bb[3] - bb[1]
    if not (140 <= w <= 420 and 140 <= h <= 420):
        return None
    return x0 + bb[0], y0 + bb[1], x0 + bb[2], y0 + bb[3]


def apply_tracked_badge(src, dst, badge_path, cfg):
    """Print the official badge onto a moving garment, frame by frame.

    The shirt floats up out of the box before settling, so a fixed overlay would
    slide off it. Frames stream through rawvideo pipes — no PNG sequence on disk.
    """
    window = tuple(cfg["window"])
    size_frac = cfg.get("size_frac", 0.42)
    cy_frac = cfg.get("cy_frac", 0.42)
    fade = cfg.get("fade", 0.4)
    badge = Image.open(badge_path).convert("RGBA")
    if badge.getbbox():
        badge = badge.crop(badge.getbbox())
    dur = probe_dur(src)
    dec = subprocess.Popen(["ffmpeg", "-v", "error", "-i", src, "-f", "rawvideo",
                            "-pix_fmt", "rgb24", "-"], stdout=subprocess.PIPE)
    enc = subprocess.Popen(["ffmpeg", "-y", "-v", "error", "-f", "rawvideo", "-pix_fmt", "rgb24",
                            "-s", f"{W}x{H}", "-r", str(FPS), "-i", "-", "-c:v", "libx264",
                            "-crf", "16", "-preset", "fast", "-pix_fmt", "yuv420p", dst],
                           stdin=subprocess.PIPE)
    nbytes = W * H * 3
    last, first_seen, n, hits = None, None, 0, 0
    cache = {}
    while True:
        buf = dec.stdout.read(nbytes)
        if len(buf) < nbytes:
            break
        fr = Image.frombytes("RGB", (W, H), buf)
        bb = find_garment(fr, window) or last
        if bb:
            last = bb
            if first_seen is None:
                first_seen = n
            hits += 1
            gw, gh = bb[2] - bb[0], bb[3] - bb[1]
            bs = max(24, int(gw * size_frac))
            a = min(1.0, (n - first_seen) / max(1e-6, fade * FPS))
            if bs not in cache:
                cache[bs] = badge.resize((bs, bs), Image.LANCZOS)
            b = cache[bs]
            if a < 1.0:
                b = b.copy()
                b.putalpha(b.getchannel("A").point(lambda v: int(v * a)))
            fr.paste(b, (bb[0] + gw // 2 - bs // 2, bb[1] + int(gh * cy_frac) - bs // 2), b)
        enc.stdin.write(fr.tobytes())
        n += 1
    enc.stdin.close()
    dec.wait(); enc.wait()
    print(f"  shirt badge: {hits}/{n} frames printed, first at "
          f"{(first_seen or 0) / FPS:.2f}s, last box {last}")
    return dst


# ---------------------------------------------------------------- per-scene build
def fit_clip(src, dst, target):
    """Scale/pad to 1920x1080@24, then trim or freeze-pad to `target` seconds."""
    d = probe_dur(src)
    vf = "scale=1920:1080:force_original_aspect_ratio=decrease,pad=1920:1080:(ow-iw)/2:(oh-ih)/2,fps=24,setsar=1"
    if d >= target:
        sh("ffmpeg", "-y", "-loglevel", "error", "-i", src, "-t", f"{target:.3f}", "-vf", vf,
           "-an", "-c:v", "libx264", "-crf", "16", "-preset", "fast", "-pix_fmt", "yuv420p", dst)
    else:
        sh("ffmpeg", "-y", "-loglevel", "error", "-i", src, "-vf",
           f"{vf},tpad=stop_mode=clone:stop_duration={target - d + 0.05:.3f}", "-t", f"{target:.3f}",
           "-an", "-c:v", "libx264", "-crf", "16", "-preset", "fast", "-pix_fmt", "yuv420p", dst)
    return dst


def overlay_pngs(src, dst, items, target):
    """items = [(png, start, end|None, fade)] or [(png, start, end, fade, dx, dy)] for an
    overlay that tracks a drifting element → one ffmpeg pass with fades."""
    if not items:
        shutil.copy(src, dst)
        return dst
    cmd = ["ffmpeg", "-y", "-loglevel", "error", "-i", src]
    for it in items:
        cmd += ["-loop", "1", "-i", it[0]]
    fc, last = [], "[0:v]"
    for i, it in enumerate(items, start=1):
        png, st, en, fade = it[:4]
        dx, dy = (it[4], it[5]) if len(it) > 5 else (0, 0)
        en = target if en is None else en
        fc.append(f"[{i}:v]format=rgba,fade=t=in:st={st:.3f}:d={fade:.3f}:alpha=1,"
                  f"fade=t=out:st={max(st, en - 0.25):.3f}:d=0.25:alpha=1[o{i}]")
        # Linear drift so the overlay stays locked to what it covers.
        ox = "0" if not dx else f"'{dx}*min(1,t/{max(0.001, target):.3f})'"
        oy = "0" if not dy else f"'{dy}*min(1,t/{max(0.001, target):.3f})'"
        fc.append(f"{last}[o{i}]overlay={ox}:{oy}:enable='between(t,{st:.3f},{en:.3f})'[v{i}]")
        last = f"[v{i}]"
    cmd += ["-filter_complex", ";".join(fc), "-map", last, "-t", f"{target:.3f}",
            "-c:v", "libx264", "-crf", "16", "-preset", "fast", "-pix_fmt", "yuv420p", dst]
    sh(*cmd)
    return dst


def build_scene(s, assets, only):
    sid = s["id"]
    target = max(s["vo_dur"] + VO_LEAD + TAIL, s.get("min", 0))
    raw = os.path.join(WORK, f"{sid}_raw.mp4")
    fit = os.path.join(WORK, f"{sid}_fit.mp4")
    out = os.path.join(WORK, f"{sid}.mp4")
    if only and sid not in only and os.path.exists(out):
        return out, probe_dur(out)
    print(f"[{sid}] target {target:.2f}s", flush=True)
    build = s.get("build")
    if build == "timecard":
        if not (os.path.exists(raw) and abs(probe_dur(raw) - target) < 0.2):
            scenes.render(scenes.timecard_frame, raw, target)
    elif s.get("clip"):
        fetch(s["clip"], raw)
    else:
        raise SystemExit(f"{sid}: no clip and no build")
    fit_clip(raw, fit, target)
    if s.get("shirt_badge") and assets.get("badge"):
        printed = os.path.join(WORK, f"{sid}_shirt.mp4")
        apply_tracked_badge(fit, printed, assets["badge"], s["shirt_badge"])
        fit = printed

    items = []
    if build == "endcard":
        ff = os.path.join(WORK, f"{sid}_f0.png")
        sh("ffmpeg", "-y", "-loglevel", "error", "-i", fit, "-frames:v", "1", ff)
        for png, st, en in endcard_overlays(fit, ff, assets["badge"], assets["wordmark"],
                                            assets.get("diploma"), WORK):
            items.append((png, st, en, 0.5))
    if s.get("play_badge") and assets.get("play_badge"):
        ff = os.path.join(WORK, f"{sid}_f0.png")
        sh("ffmpeg", "-y", "-loglevel", "error", "-i", fit, "-frames:v", "1", ff)
        png, dx, dy = play_badge_overlay(fit, ff, assets["play_badge"], WORK)
        items.append((png, s["play_badge"], None, 0.4, dx, dy))
    caps = s.get("captions", [])
    for i, c in enumerate(caps):
        png = render_caption(c["text"], c.get("pos", "bl"), os.path.join(WORK, f"{sid}_cap{i}.png"),
                             size=c.get("size", 54), maxw=c.get("maxw", 980))
        st = c["at"]
        if c.get("end") is not None:
            en = c["end"]
        elif i + 1 < len(caps) and caps[i + 1].get("replaces", True):
            en = caps[i + 1]["at"]
        else:
            en = None
        items.append((png, st, en, 0.3))
    overlay_pngs(fit, out, items, target)
    return out, target


# ---------------------------------------------------------------- main
def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("manifest")
    ap.add_argument("--master", default="master.mp4")
    ap.add_argument("--preview", default="preview.mp4")
    ap.add_argument("--put-master")
    ap.add_argument("--put-preview")
    ap.add_argument("--scenes", help="comma list of scene ids to (re)build; others reused from work/")
    a = ap.parse_args()
    only = set(a.scenes.split(",")) if a.scenes else None
    m = json.load(open(a.manifest))
    os.makedirs(WORK, exist_ok=True)

    # Fonts: Montserrat Medium/SemiBold are not in the sandbox; fetch if the manifest says where.
    for key, name in (("font_medium", "Montserrat-Medium.ttf"), ("font_semibold", "Montserrat-SemiBold.ttf")):
        if m["assets"].get(key):
            p = fetch(m["assets"][key], os.path.join(WORK, name))
            if key == "font_medium":
                scenes.BODY_PATH = p
            else:
                scenes.SEMI_PATH = p
    scenes._font_cache.clear()
    assets = {"badge": fetch(m["assets"]["badge"], os.path.join(WORK, "badge.png")),
              "wordmark": fetch(m["assets"]["wordmark"], os.path.join(WORK, "wordmark.png"))}
    if m["assets"].get("diploma"):
        assets["diploma"] = fetch(m["assets"]["diploma"], os.path.join(WORK, "diploma.png"))
    if m["assets"].get("play_badge"):
        assets["play_badge"] = fetch(m["assets"]["play_badge"], os.path.join(WORK, "play_badge.png"))
    music = fetch(m["assets"]["music"], os.path.join(WORK, "music.bin")) if m["assets"].get("music") else None

    # Video: build scenes, concat.
    parts, starts, t = [], [], 0.0
    for s in m["scenes"]:
        p, d = build_scene(s, assets, only)
        parts.append(p); starts.append(t); t += d
    total = t
    with open(os.path.join(WORK, "concat.txt"), "w") as f:
        for p in parts:
            f.write(f"file '{os.path.abspath(p)}'\n")
    video = os.path.join(WORK, "video.mp4")
    sh("ffmpeg", "-y", "-loglevel", "error", "-f", "concat", "-safe", "0", "-i", os.path.join(WORK, "concat.txt"),
       "-c", "copy", video)
    print(f"picture: {total:.1f}s", flush=True)

    # Audio: VO lines on the timeline + optional ducked music bed.
    cmd = ["ffmpeg", "-y", "-loglevel", "error", "-i", video]
    fc, mix_in = [], []
    for i, s in enumerate(m["scenes"]):
        vo_url = s["vo"] if s["vo"].startswith("http") else m.get("vo_base", "") + s["vo"]
        vo = fetch(vo_url, os.path.join(WORK, f"{s['id']}_vo.wav"))
        cmd += ["-i", vo]
        ms = int((starts[i] + VO_LEAD) * 1000)
        vd = probe_dur(vo)
        # 60 ms tail fade so a line never stops on a hard edge against the music.
        fc.append(f"[{i + 1}:a]aresample=48000,afade=t=out:st={max(0, vd - 0.06):.3f}:d=0.06,"
                  f"adelay={ms}|{ms}[v{i}]")
        mix_in.append(f"[v{i}]")
    n = len(m["scenes"])
    fc.append("".join(mix_in) + f"amix=inputs={n}:normalize=0,alimiter=limit=0.95[vo]")
    if music:
        cmd += ["-stream_loop", "-1", "-i", music]
        fc.append(f"[{n + 1}:a]aresample=48000,atrim=0:{total:.3f},volume={MUSIC_GAIN},"
                  f"afade=t=in:d=1,afade=t=out:st={max(0, total - 3):.3f}:d=3[mus]")
        fc.append("[vo]asplit[vo1][vo2]")
        fc.append("[mus][vo1]sidechaincompress=threshold=0.05:ratio=4:attack=40:release=500[musd]")
        fc.append("[musd][vo2]amix=inputs=2:normalize=0[mix]")
        amap = "[mix]"
    else:
        amap = "[vo]"
    fc.append(f"{amap}apad=whole_dur={total:.3f}[aout]")   # picture length wins, never -shortest
    cmd += ["-filter_complex", ";".join(fc), "-map", "0:v", "-map", "[aout]", "-t", f"{total:.3f}",
            "-c:v", "copy", "-c:a", "aac", "-b:a", "192k", a.master]
    sh(*cmd)
    sh("ffmpeg", "-y", "-loglevel", "error", "-i", a.master, "-vf", "scale=1280:720", "-c:v", "libx264",
       "-crf", "26", "-preset", "fast", "-c:a", "aac", "-b:a", "128k", a.preview)
    print("master", probe_dur(a.master), "s", os.path.getsize(a.master) // 1024, "KB", flush=True)
    for path, url in ((a.master, a.put_master), (a.preview, a.put_preview)):
        if url:
            sh("curl", "-sf", "-o", "/dev/null", "-w", f"put {os.path.basename(path)} %{{http_code}}\\n",
               "-X", "PUT", "-H", "Content-Type: video/mp4", "--data-binary", f"@{path}", url)
    json.dump({"scenes": [{"id": s["id"], "start": round(st, 3)} for s, st in zip(m["scenes"], starts)],
               "total": round(total, 3)}, open("timeline.json", "w"), indent=1)


if __name__ == "__main__":
    main()
