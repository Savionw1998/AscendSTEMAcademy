#!/usr/bin/env python3
"""Numerical QA for the SaaS explainer — runs in the Higgsfield sandbox.

    python3 qa.py manifest.json > qa_report.json

Per scene it reports:
  clip      native duration / fps, and how much freeze-pad or trim the fit needs
  vo        real speech end (silencedetect) vs the scene length → effective tail
  captions  for each caption, an obstruction score for every candidate position
            (content fraction + motion under the caption card during its window)
            and the best position
  endcard   Lucas's bbox over every frame (max extent) and the card's stability
  playbadge the placeholder pill's bbox per frame → drift
"""
import json, os, re, subprocess, sys
from PIL import Image, ImageChops, ImageStat

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import assemble  # noqa: E402
from assemble import W, H, VO_LEAD, TAIL, WORK, fetch, probe_dur, caption_box, CAPTION_POSITIONS  # noqa: E402
from assemble import pink_bbox, white_card_bbox, dark_pill_bbox  # noqa: E402


def probe(path):
    out = subprocess.check_output(["ffprobe", "-v", "error", "-select_streams", "v:0", "-show_entries",
                                   "stream=r_frame_rate,nb_frames,width,height:format=duration",
                                   "-of", "json", path]).decode()
    j = json.loads(out)
    st = j["streams"][0]
    num, den = st["r_frame_rate"].split("/")
    return {"dur": float(j["format"]["duration"]), "fps": round(float(num) / float(den), 3),
            "w": st["width"], "h": st["height"]}


def speech_bounds(wav):
    """(first, last) non-silent second via silencedetect; None if no speech found."""
    p = subprocess.run(["ffmpeg", "-hide_banner", "-i", wav, "-af", "silencedetect=n=-38dB:d=0.25",
                        "-f", "null", "-"], capture_output=True, text=True)
    dur = probe_dur(wav)
    ends = [float(x) for x in re.findall(r"silence_end: ([\d.]+)", p.stderr)]
    starts = [float(x) for x in re.findall(r"silence_start: ([\d.]+)", p.stderr)]
    first = ends[0] if starts and starts[0] < 0.05 and ends else 0.0
    last = starts[-1] if starts and (not ends or starts[-1] > ends[-1]) else dur
    return first, last, dur


def frames_at(path, times, size=(480, 270)):
    """Small RGB frames at the given times (seconds)."""
    out = []
    for t in times:
        png = os.path.join(WORK, "_qa.png")
        subprocess.run(["ffmpeg", "-y", "-loglevel", "error", "-ss", f"{t:.3f}", "-i", path, "-frames:v", "1",
                        "-vf", f"scale={size[0]}:{size[1]}", png], check=True)
        out.append(Image.open(png).convert("RGB"))
    return out


def content_fraction(img, box):
    """Share of pixels in box that are not near-white ground."""
    x0, y0, x1, y1 = box
    crop = img.crop((x0, y0, x1, y1))
    hsv = crop.convert("HSV")
    lum = crop.convert("L").load()
    sat = hsv.getchannel("S").load()
    n = tot = 0
    for y in range(crop.height):
        for x in range(crop.width):
            tot += 1
            if lum[x, y] < 222 or sat[x, y] > 48:
                n += 1
    return n / max(1, tot)


def motion_fraction(a, b, box):
    x0, y0, x1, y1 = box
    d = ImageChops.difference(a.crop((x0, y0, x1, y1)), b.crop((x0, y0, x1, y1))).convert("L")
    px = d.load()
    n = tot = 0
    for y in range(d.height):
        for x in range(d.width):
            tot += 1
            if px[x, y] > 18:
                n += 1
    return n / max(1, tot)


def scaled_box(text, pos, size, maxw, sx, sy):
    x0, y0, cw, ch = caption_box(text, pos, size, maxw)
    return int(x0 * sx), int(y0 * sy), int((x0 + cw) * sx), int((y0 + ch) * sy)


def qa_scene(s, m, assets):
    sid = s["id"]
    rep = {"id": sid}
    target = max(s["vo_dur"] + VO_LEAD + TAIL, s.get("min", 0))
    rep["target"] = round(target, 3)
    # --- VO
    vo_url = s["vo"] if s["vo"].startswith("http") else m["vo_base"] + s["vo"]
    vo = fetch(vo_url, os.path.join(WORK, f"{sid}_vo.wav"))
    first, last, vdur = speech_bounds(vo)
    rep["vo"] = {"wav_dur": round(vdur, 3), "speech_start": round(first, 3), "speech_end": round(last, 3),
                 "ends_at_scene_t": round(VO_LEAD + last, 3), "tail_after_speech": round(target - VO_LEAD - last, 3)}
    # --- clip
    if s.get("clip"):
        raw = fetch(s["clip"], os.path.join(WORK, f"{sid}_raw.mp4"))
        pr = probe(raw)
        rep["clip"] = dict(pr, freeze_pad=round(max(0, target - pr["dur"]), 3), trim=round(max(0, pr["dur"] - target), 3))
        src = raw
    else:
        src = os.path.join(WORK, f"{sid}_raw.mp4")
        if not os.path.exists(src):
            import scenes
            scenes.render(scenes.timecard_frame, src, target)
        rep["clip"] = dict(probe(src), built=True)
    # --- captions: sample the clip over each caption's window
    sx, sy = 480 / W, 270 / H
    caps = s.get("captions", [])
    rep["captions"] = []
    for i, c in enumerate(caps):
        st = c["at"]
        en = c.get("end") or (caps[i + 1]["at"] if i + 1 < len(caps) else target)
        en = min(en, rep["clip"]["dur"] if not rep["clip"].get("built") else target)
        times = [st + k * (en - st) / 6 for k in range(7)]
        fr = frames_at(src, [min(t, rep["clip"]["dur"] - 0.05) for t in times])
        scores = {}
        for pos in CAPTION_POSITIONS:
            box = scaled_box(c["text"], pos, c.get("size", 54), c.get("maxw", 980), sx, sy)
            cf = sum(content_fraction(f, box) for f in fr) / len(fr)
            mf = sum(motion_fraction(fr[k], fr[k + 1], box) for k in range(len(fr) - 1)) / (len(fr) - 1)
            scores[pos] = {"content": round(cf, 3), "motion": round(mf, 3), "score": round(cf + 2 * mf, 3)}
        cur = c.get("pos", "bl")
        best = min(scores, key=lambda p: scores[p]["score"])
        rep["captions"].append({"text": c["text"], "window": [round(st, 2), round(en, 2)], "current": cur,
                                "best": best, "scores": scores})
    # --- end card: Lucas extent over all frames
    if s.get("build") == "endcard":
        n = int(rep["clip"]["dur"] * 4)
        fr = frames_at(src, [k / 4 for k in range(n)], size=(960, 540))
        boxes = [pink_bbox(f) for f in fr]
        boxes = [b for b in boxes if b]
        if boxes:
            ext = (min(b[0] for b in boxes) * 2, min(b[1] for b in boxes) * 2,
                   max(b[2] for b in boxes) * 2, max(b[3] for b in boxes) * 2)
        else:
            ext = None
        cards = [white_card_bbox(f) for f in fr[:: max(1, len(fr) // 6)]]
        rep["endcard"] = {"lucas_extent": ext, "lucas_frame0": tuple(x * 2 for x in boxes[0]) if boxes else None,
                          "card_samples": [tuple(x * 2 for x in cb) for cb in cards]}
    # --- play badge pill drift
    if s.get("play_badge"):
        n = int(rep["clip"]["dur"] * 4)
        fr = frames_at(src, [k / 4 for k in range(n)], size=(960, 540))
        pills = [dark_pill_bbox(f) for f in fr]
        pills = [tuple(x * 2 for x in p) if p else None for p in pills]
        found = [p for p in pills if p]
        drift = None
        if found:
            cx = [(p[0] + p[2]) / 2 for p in found]; cy = [(p[1] + p[3]) / 2 for p in found]
            drift = {"dx": round(max(cx) - min(cx), 1), "dy": round(max(cy) - min(cy), 1),
                     "missing_frames": pills.count(None), "first": found[0], "last": found[-1]}
        rep["playbadge"] = {"pill_per_quarter_second": pills, "drift": drift}
    return rep


def main():
    m = json.load(open(sys.argv[1]))
    os.makedirs(WORK, exist_ok=True)
    assets = {}
    report = {"scenes": [qa_scene(s, m, assets) for s in m["scenes"]]}
    json.dump(report, sys.stdout, indent=1)


if __name__ == "__main__":
    main()
