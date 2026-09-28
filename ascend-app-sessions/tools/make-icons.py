#!/usr/bin/env python3
"""
Builds the app's web-manifest icons from the 1024x1024 logo (branding/logo-source-1024.png):

  icon-192.png, icon-512.png   the logo as is (rounded square, transparent corners)
  icon-maskable-512.png        full-bleed version, artwork inside the maskable safe zone
  shortcut-<name>.png          96x96 shortcut icons cut from the logo's symbols, white on the
  shortcut-<name>-maskable.png   logo's blue or green: a round badge, and a full-bleed maskable one

  python3 ascend-app-sessions/tools/make-icons.py branding/logo-source-1024.png ascend-app-sessions/assets/icons

Needs Pillow, numpy and scipy.
"""
import os
import sys

import numpy as np
from PIL import Image
from scipy import ndimage as ndi

SRC, OUT = sys.argv[1], sys.argv[2]
os.makedirs(OUT, exist_ok=True)

logo = Image.open(SRC).convert('RGBA')
assert logo.size == (1024, 1024), 'expected the 1024x1024 master logo'
px = np.asarray(logo).astype(float)
rgb, alpha = px[..., :3], px[..., 3] / 255.0
lo = rgb.min(axis=2)


def soft(v, a, b):
    return np.clip((v - a) / (b - a), 0, 1)


white = soft(lo, 60, 235) * alpha          # the logo's white artwork
ink = soft(235 - lo, 0, 175) * alpha        # anything darker than white (the blue cap)


def colour(mask):
    return tuple(int(round(c)) for c in rgb[mask].mean(axis=0))


BLUE = colour((rgb[..., 2] > 180) & (rgb[..., 0] < 40) & (alpha > 0.98) & (np.arange(1024)[:, None] > 700))
GREEN = colour((rgb[..., 1] > 180) & (rgb[..., 2] < 60) & (rgb[..., 0] > 60) & (alpha > 0.98))


def cut(layer, box, keep):
    """Soft mask of the connected shapes inside box that keep(props) accepts, cropped tight."""
    x0, y0, x1, y1 = box
    sub = layer[y0:y1, x0:x1]
    lab, n = ndi.label(sub > 0.5)
    chosen = []
    for i in range(1, n + 1):
        ys, xs = np.nonzero(lab == i)
        props = {
            'area': len(xs), 'cx': xs.mean() + x0, 'cy': ys.mean() + y0,
            'edge': xs.min() == 0 or ys.min() == 0 or xs.max() == sub.shape[1] - 1 or ys.max() == sub.shape[0] - 1,
        }
        if keep(props):
            chosen.append(i)
    grown = ndi.binary_dilation(np.isin(lab, chosen), iterations=3)
    m = sub * grown
    ys, xs = np.nonzero(m > 0.02)
    return m[ys.min():ys.max() + 1, xs.min():xs.max() + 1]


def disk_of(layer, point):
    lab, _ = ndi.label(layer > 0.5)
    ys, xs = np.nonzero(lab == lab[point[1], point[0]])
    return (xs.min() + xs.max()) / 2, (ys.min() + ys.max()) / 2, (xs.max() - xs.min()) / 2


# The four symbols.
GEAR_C = (171, 497)   # the complete inner gear inside the logo's left half-gear
cx, cy, r = disk_of(white, (512, 355))   # the white disc behind the graduation cap (above the cap)
symbols = {
    'gear': cut(white, (60, 386, 283, 609), lambda p: p['area'] > 200 and np.hypot(p['cx'] - GEAR_C[0], p['cy'] - GEAR_C[1]) < 15),
    'atom': cut(white, (712, 300, 1024, 700), lambda p: p['area'] > 30 and not p['edge']),
    'math': cut(white, (360, 712, 664, 972), lambda p: p['area'] > 30 and not p['edge']),
    'cap': cut(ink * (np.hypot(*np.meshgrid(np.arange(1024) - cx, np.arange(1024) - cy)) < r * 0.9), (int(cx - r), int(cy - r), int(cx + r), int(cy + r)), lambda p: p['area'] > 30),
}

# Shortcut name -> symbol, background.
SHORTCUTS = {
    'time-card': ('math', BLUE),
    'students': ('cap', BLUE),
    'parents': ('gear', GREEN),
    'games': ('atom', GREEN),
}


def place(mask, size, diagonal):
    """The symbol as an L image of size x size, scaled so its bounding-box diagonal is `diagonal` px."""
    h, w = mask.shape
    s = diagonal / np.hypot(w, h)
    img = Image.fromarray((mask * 255).astype('uint8'), 'L').resize((max(1, round(w * s)), max(1, round(h * s))), Image.LANCZOS)
    canvas = Image.new('L', (size, size), 0)
    canvas.paste(img, ((size - img.width) // 2, (size - img.height) // 2))
    return canvas


def shortcut(mask, bg, maskable, size=96, ss=4):
    big = size * ss
    if maskable:
        base = Image.new('RGBA', (big, big), bg + (255,))
        glyph = place(mask, big, 0.74 * big)          # inside the 80% safe-zone circle
    else:
        base = Image.new('RGBA', (big, big), (0, 0, 0, 0))
        disc = Image.new('L', (big, big), 0)
        from PIL import ImageDraw
        ImageDraw.Draw(disc).ellipse([0, 0, big - 1, big - 1], fill=255)
        base.paste(Image.new('RGBA', (big, big), bg + (255,)), (0, 0), disc)
        glyph = place(mask, big, 0.78 * big)
    base.paste(Image.new('RGBA', (big, big), (255, 255, 255, 255)), (0, 0), glyph)
    return base.resize((size, size), Image.LANCZOS)


for name, (sym, bg) in SHORTCUTS.items():
    shortcut(symbols[sym], bg, False).save(os.path.join(OUT, f'shortcut-{name}.png'), optimize=True)
    shortcut(symbols[sym], bg, True).save(os.path.join(OUT, f'shortcut-{name}-maskable.png'), optimize=True)

# Manifest icons.
for size in (192, 512):
    logo.resize((size, size), Image.LANCZOS).save(os.path.join(OUT, f'icon-{size}.png'), optimize=True)


def extend(arr, known):
    """Fill every pixel outside `known` by repeatedly averaging already-filled neighbours."""
    arr = arr.copy()
    known = known.copy()
    while not known.all():
        acc = np.zeros_like(arr)
        cnt = np.zeros(known.shape)
        for dy, dx in ((-1, 0), (1, 0), (0, -1), (0, 1), (-1, -1), (-1, 1), (1, -1), (1, 1)):
            k = np.roll(np.roll(known, dy, 0), dx, 1)
            acc += np.roll(np.roll(arr, dy, 0), dx, 1) * k[..., None]
            cnt += k
        grow = ~known & (cnt > 0)
        arr[grow] = acc[grow] / cnt[grow][:, None]
        known |= grow
    return arr


# Maskable: the logo shrunk so its symbols sit inside the safe zone, on a full-bleed continuation of
# its own background: the blue and green are extended outwards (never the white artwork, which would
# smear into bars), and the four white diagonal stripes are continued out to the corners.
N, SCALE = 1024, 0.80
wmask = white > 0.5


def stripe_line(corner):
    """Fit the logo's stripe toward `corner`: returns (point, unit direction, half width) in logo pixels."""
    c = np.array(corner, float)
    u = (np.array([511.5, 511.5]) - c) / np.hypot(511.5, 511.5)
    v = np.array([-u[1], u[0]])
    ts, offs, widths = [], [], []
    for t in range(120, 490, 10):
        samples = [(k, wmask[int(round(c[1] + t * u[1] + k * v[1])), int(round(c[0] + t * u[0] + k * v[0]))]) for k in range(-60, 61)]
        run = [k for k, w in samples if w]
        # one clean stripe crossing, not cut off by the sampling window
        if run and max(run) - min(run) + 1 == len(run) and len(run) < 22 and -60 < min(run) and max(run) < 60:
            ts.append(t)
            offs.append((min(run) + max(run)) / 2)
            widths.append(len(run))
    k, b = np.polyfit(ts, offs, 1)
    p0 = c + b * v
    d = u + k * v
    return p0, d / np.hypot(*d), np.median(widths) / 2


CORNERS = ((0, 0), (1023, 0), (0, 1023), (1023, 1023))
stripes = [stripe_line(c) for c in CORNERS]
size = round(N * SCALE)
off = (N - size) // 2
small = logo.resize((size, size), Image.LANCZOS)
canvas = np.zeros((N, N, 4))
canvas[off:off + size, off:off + size] = np.asarray(small).astype(float)
c_rgb, c_a = canvas[..., :3], canvas[..., 3] / 255
solid = c_a > 0.98
background = solid & (c_rgb.min(axis=2) < 40)    # pure blue/green only, no anti-aliased white edges
filled = extend(c_rgb, background)
yy, xx = np.mgrid[0:N, 0:N] + 0.5
lx, ly = (xx - off) / SCALE, (yy - off) / SCALE   # canvas -> logo coordinates
cover = np.zeros((N, N))
for (p0, d, hw), (kx, ky) in zip(stripes, CORNERS):
    dist = np.abs((lx - p0[0]) * d[1] - (ly - p0[1]) * d[0]) * SCALE
    own = ((xx < N / 2) == (kx == 0)) & ((yy < N / 2) == (ky == 0))   # only toward its own corner
    cover = np.maximum(cover, np.clip(hw * SCALE + 0.5 - dist, 0, 1) * own)
filled = filled * (1 - cover[..., None]) + 255 * cover[..., None]
out = np.where(solid[..., None], c_rgb, filled)
edge = (c_a > 0.02) & ~solid                     # blend the logo's anti-aliased rim
out[edge] = (c_rgb[edge] * c_a[edge][:, None] + filled[edge] * (1 - c_a[edge][:, None]))
Image.fromarray(out.round().clip(0, 255).astype('uint8'), 'RGB').resize((512, 512), Image.LANCZOS).save(os.path.join(OUT, 'icon-maskable-512.png'), optimize=True)

print('blue', BLUE, 'green', GREEN, '->', sorted(os.listdir(OUT)))
