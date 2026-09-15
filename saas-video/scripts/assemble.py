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


def render_caption(text, pos, path, size=54, maxw=980):
    lines, f, lh, cw, ch, pad_x, pad_y = caption_png(text, path, size, maxw)
    layer = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    m = int(W * 0.10)  # 10 % title-safe
    if pos == "bl":
        x0, y0 = m, H - m - ch
    elif pos == "br":
        x0, y0 = W - m - cw, H - m - ch
    elif pos == "bc":
        x0, y0 = (W - cw) // 2, H - m - ch
    elif pos == "tl":
        x0, y0 = m, m
    elif pos == "tc":
        x0, y0 = (W - cw) // 2, m
    elif pos == "cc":
        x0, y0 = (W - cw) // 2, (H - ch) // 2
    else:
        x0, y0 = m, H - m - ch
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


def endcard_overlays(first_frame_png, badge_path, wordmark_path, diploma_path, outdir):
    """Lay the diploma beat, then the official badge, wordmark, URL and enroll line on the
    AI end-card clip, inside its white card and above Lucas. Returns [(png, start, end)]."""
    base = Image.open(first_frame_png).convert("RGB")
    cx0, cy0, cx1, cy1 = white_card_bbox(base)
    if cx1 - cx0 < 600 or cy1 - cy0 < 300:   # detection failed → assume 70 % card
        cx0, cy0, cx1, cy1 = int(W * 0.15), int(H * 0.15), int(W * 0.85), int(H * 0.85)
    lb = pink_bbox(base)
    floor = (lb[1] - 30) if lb else cy1 - 40
    top = cy0 + 40
    avail = max(420, floor - top)
    print(f"  endcard: card=({cx0},{cy0},{cx1},{cy1}) lucas={lb} content band {top}..{floor}")
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
    # Beat 2: badge → wordmark → URL → enroll line, stacked and scaled to fit `avail`.
    badge = Image.open(badge_path).convert("RGBA")
    wm = Image.open(wordmark_path).convert("RGBA")
    bs = int(min(260, avail * 0.36))
    ww = int(min(700, (cx1 - cx0) * 0.55))
    wh = int(wm.height * ww / wm.width)
    url_s, en_s = 56, 34
    total = bs + 26 + wh + 30 + url_s + 18 + en_s
    scale = min(1.0, avail / total)
    bs, ww, wh = int(bs * scale), int(ww * scale), int(wh * scale)
    url_s, en_s = int(url_s * scale), int(en_s * scale)
    y = top + (avail - int(total * scale)) // 2
    l = layer(); b = badge.resize((bs, bs), Image.LANCZOS); l.paste(b, (mid - bs // 2, y), b)
    p = os.path.join(outdir, "ov_badge.png"); l.save(p); items.append((p, t0, None)); y += bs + int(26 * scale)
    l = layer(); w = wm.resize((ww, wh), Image.LANCZOS); l.paste(w, (mid - ww // 2, y), w)
    p = os.path.join(outdir, "ov_wordmark.png"); l.save(p); items.append((p, t0 + 0.6, None)); y += wh + int(30 * scale)
    l = layer(); ImageDraw.Draw(l).text((mid, y + url_s // 2), "ascendstemacademy.com",
                                        font=scenes.font("bold", url_s), fill=scenes.INK + (255,), anchor="mm")
    p = os.path.join(outdir, "ov_url.png"); l.save(p); items.append((p, t0 + 1.3, None)); y += url_s + int(18 * scale)
    l = layer(); ImageDraw.Draw(l).text((mid, y + en_s // 2), "Enroll in about five minutes.",
                                        font=scenes.font("semi", en_s), fill=scenes.DEEP + (255,), anchor="mm")
    p = os.path.join(outdir, "ov_enroll.png"); l.save(p); items.append((p, t0 + 2.0, None))
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


def play_badge_overlay(first_frame_png, badge_path, outdir):
    """Fit the official Google Play badge over the placeholder pill in scene 10."""
    base = Image.open(first_frame_png).convert("RGB")
    bb = dark_pill_bbox(base)
    if bb is None:
        bb = (760, 850, 1160, 970)          # fallback: centred under a centred phone
    x0, y0, x1, y1 = bb
    print(f"  play badge: pill={bb}")
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
    return p


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
    """items = [(png, start, end|None, fade)] → one ffmpeg pass with fades."""
    if not items:
        shutil.copy(src, dst)
        return dst
    cmd = ["ffmpeg", "-y", "-loglevel", "error", "-i", src]
    for png, *_ in items:
        cmd += ["-loop", "1", "-i", png]
    fc, last = [], "[0:v]"
    for i, (png, st, en, fade) in enumerate(items, start=1):
        en = target if en is None else en
        fc.append(f"[{i}:v]format=rgba,fade=t=in:st={st:.3f}:d={fade:.3f}:alpha=1,"
                  f"fade=t=out:st={max(st, en - 0.25):.3f}:d=0.25:alpha=1[o{i}]")
        fc.append(f"{last}[o{i}]overlay=0:0:enable='between(t,{st:.3f},{en:.3f})'[v{i}]")
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

    items = []
    if build == "endcard":
        ff = os.path.join(WORK, f"{sid}_f0.png")
        sh("ffmpeg", "-y", "-loglevel", "error", "-i", fit, "-frames:v", "1", ff)
        for png, st, en in endcard_overlays(ff, assets["badge"], assets["wordmark"], assets.get("diploma"), WORK):
            items.append((png, st, en, 0.5))
    if s.get("play_badge") and assets.get("play_badge"):
        ff = os.path.join(WORK, f"{sid}_f0.png")
        sh("ffmpeg", "-y", "-loglevel", "error", "-i", fit, "-frames:v", "1", ff)
        items.append((play_badge_overlay(ff, assets["play_badge"], WORK), s["play_badge"], None, 0.4))
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
        fc.append(f"[{i + 1}:a]aresample=48000,adelay={ms}|{ms}[v{i}]")
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
