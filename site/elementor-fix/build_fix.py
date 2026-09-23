"""Rebuild the sections added on 2026-09-23 as native Elementor widgets styled like the owner's pages.

Output: fix.json = {page_id: {old_element_id: [new elements...]}}.
Styles are copied from style-reference.json (settings taken from the owner's own widgets):
centered headings/text/buttons, blue #009CDE headings, Roboto, 8px-radius buttons, white cards with
14px radius, light-green (#BFE0B4) and blue (#009CDE) bands.
"""
import copy
import itertools
import json
import os
import random

HERE = os.path.dirname(os.path.abspath(__file__))
REF = json.load(open(os.path.join(HERE, "style-reference.json")))
SITE = "https://ascendstemacademy.com"
REG = SITE + "/registration/"
CALL = "https://calendar.app.google/Zrt44eK4625dPaz66"
BLUE, GREEN, LIGHT, PINK = "#009CDE", "#1D4010", "#BFE0B4", "#F2A6C6"

random.seed(20260923)
_used = set()


def eid():
    while True:
        v = "a5%05x" % random.randrange(16 ** 5)
        if v not in _used:
            _used.add(v)
            return v


def clean(settings):
    """Drop the owner's per-widget content/leftovers (title, text, links, EA gradient repeaters)."""
    s = copy.deepcopy(settings)
    for k in ("title", "editor", "text", "link", "image", "icon_list", "eael_vto_writing_gradient_color_repeater"):
        s.pop(k, None)
    return s


def px(t, r, b, l):
    return {"unit": "px", "top": str(t), "right": str(r), "bottom": str(b), "left": str(l), "isLinked": False}


def container(children, settings=None, inner=False):
    s = {"content_width": "boxed", "flex_direction": "column", "flex_align_items": "center",
         "flex_justify_content": "center", "flex_gap": {"size": 16, "column": "16", "row": "16", "unit": "px"}}
    s.update(settings or {})
    return {"id": eid(), "elType": "container", "isInner": inner, "settings": s, "elements": children}


def section(children, band=None):
    if band == "green":
        s = clean(REF["section_container_band_light_green"])
    elif band == "blue":
        s = clean(REF["section_container_band_blue_cta"])
    else:
        s = clean(REF["section_container"])
    return container(children, s)


def row(children):
    s = clean(REF["inner_container"])
    s.update({"content_width": "full", "flex_justify_content": "center", "flex_align_items": "stretch"})
    return container(children, s, inner=True)


def card(children, featured=False, width=32):
    s = clean(REF["card_container_featured" if featured else "card_container"])
    s["width"] = {"size": width, "sizes": [], "unit": "%"}
    s.update({"flex_direction": "column", "flex_align_items": "center", "flex_justify_content": "flex-start",
              "flex_gap": {"size": 10, "column": "10", "row": "10", "unit": "px"}})
    return container(children, s, inner=True)


def widget(wtype, settings):
    return {"id": eid(), "elType": "widget", "widgetType": wtype, "isInner": False, "settings": settings, "elements": []}


def h(title, size="h2", color=BLUE, font_size=None):
    base = clean(REF["heading_h3"] if size == "h3" else REF["heading_h2"])
    base.update({"title": title, "header_size": size, "title_color": color, "align": "center",
                 "typography_typography": "custom", "typography_font_family": "Roboto", "typography_font_weight": "600"})
    if size == "h3":
        base["typography_font_size"] = {"size": 22, "sizes": [], "unit": "px"}
    if font_size:
        base["typography_font_size"] = {"size": font_size, "sizes": [], "unit": "px"}
    return widget("heading", base)


def h1(title):
    s = clean(REF["heading_h1_page_title_roboto_about"])
    s.update({"title": title, "header_size": "h1", "align": "center",
              "_margin": px(100, 0, 16, 0), "_margin_mobile": px(90, 0, 12, 0),
              "typography_font_size": {"unit": "px", "size": 52, "sizes": []},
              "typography_font_size_mobile": {"unit": "px", "size": 38, "sizes": []}})
    return widget("heading", s)


def text(html, color="#000000", size=None, align="center"):
    s = clean(REF["text_editor_black_about_graduation"])
    s.update({"editor": html, "text_color": color, "align": align})
    if size:
        s.update({"typography_typography": "custom", "typography_font_size": {"size": size, "sizes": [], "unit": "px"}})
    return widget("text-editor", s)


def price(value):
    s = clean(REF["text_editor_price_big"])
    s.update({"editor": "<p>%s</p>" % value, "typography_font_weight": "700", "_margin": px(0, 0, 4, 0)})
    return widget("text-editor", s)


def button(label, url, style="primary", external=False):
    s = clean(REF["button_primary" if style == "primary" else "button_secondary"])
    s.update({"text": label, "align": "center",
              "link": {"url": url, "is_external": "on" if external else "", "nofollow": "", "custom_attributes": ""}})
    if style == "outline":
        s.update({"background_color": "#FFFFFF", "button_text_color": BLUE, "border_border": "solid",
                  "border_width": {"unit": "px", "top": "2", "right": "2", "bottom": "2", "left": "2", "isLinked": True},
                  "border_color": BLUE})
    return widget("button", s)


def buttons(*btns):
    s = {"content_width": "full", "flex_direction": "row", "flex_direction_mobile": "column", "flex_wrap": "wrap",
         "flex_justify_content": "center", "flex_align_items": "center",
         "flex_gap": {"size": 12, "column": "12", "row": "12", "unit": "px"}}
    return container(list(btns), s, inner=True)


def icon_list(items, icon="fas fa-check", color=BLUE):
    s = clean(REF["icon_list"])
    s.pop("__globals__", None)
    s.update({"icon_list": [{"_id": eid(), "text": t, "selected_icon": {"value": icon, "library": "fa-solid"}} for t in items],
              "icon_color": color, "text_color": "#000000", "icon_align": "center",
              "icon_size": {"size": 14, "sizes": [], "unit": "px"},
              "icon_typography_font_size": {"size": 16, "sizes": [], "unit": "px"},
              "space_between": {"size": 12, "sizes": [], "unit": "px"}, "_padding": px(0, 0, 0, 0)})
    return widget("icon-list", s)


def shortcode(code):
    return widget("shortcode", {"shortcode": code})


fix = {}

# ------------------------------------------------------------ About 2780
fix[2780] = {
    "asuac01": [section([
        h("What you get with Ascend"),
        text("<p>Every umbrella school keeps your child&rsquo;s records. Here&rsquo;s what Ascend adds on top.</p>", size=19),
        row([
            card([h("A basic umbrella school", "h3", GREEN),
                  icon_list(["Records filed through an automated portal",
                             "Questions wait in a support-ticket queue",
                             "A generic diploma and a basic record",
                             "You find curriculum and resources on your own",
                             "No particular focus"], icon="fas fa-times", color="#9AA7B0")], width=48),
            card([h("Support that goes further", "h3", GREEN),
                  icon_list(["A real person reviews your child’s file and files the district notice for you",
                             "Same-day or next-day answers from someone who knows your family",
                             "A 24-credit diploma and transcript aligned to Florida standards",
                             "Grade-by-grade guides and 60+ curated resources included",
                             "A STEM focus, with free axolotl learning games for kids",
                             "Optional progress reports, IDs, resume reviews and college and trade consulting"])],
                 featured=True, width=48),
        ]),
    ], band="green")],
    "asuat01": [section([
        h("Ready to join the Ascend STEM family?", color="#FFFFFF"),
        text("<p>Enrollment is open year-round for grades K&ndash;12.</p>", color="#FFFFFF", size=19),
        buttons(button("Start enrollment", REG, "secondary"),
                button("Book a free 15-minute call", CALL, "secondary", external=True)),
    ], band="blue")],
}

# ------------------------------------------------------------ Enrollment 2781
def tuition_card(title, k8, hs):
    return card([h(title, "h3", GREEN),
                 text("<p>Grades K&ndash;8</p>", size=16), price(k8 + '<span style="font-size:16px;font-weight:400"> / year</span>'),
                 text("<p>Grades 9&ndash;12</p>", size=16), price(hs + '<span style="font-size:16px;font-weight:400"> / year</span>')])


fix[2781] = {
    "asuet01": [section([
        h("Tuition", font_size=48),
        row([tuition_card("First-time enrollment", "$185", "$200"),
             tuition_card("Re-enrollment", "$165", "$175"),
             card([h("Siblings save 10%", "h3", GREEN),
                   text("<p>Enrolling more than one child? Every additional child in the same order is 10% off. "
                        "It&rsquo;s applied automatically at checkout.</p>", size=16)], featured=True)]),
        text("<p>Your child&rsquo;s school year runs for 12 months from the day you enroll. Enrollment is open year-round.</p>", size=16),
    ], band="green")],
}

# ------------------------------------------------------------ Enhancements 2782
fix[2782] = {
    "asuer01": [section([
        h("Official Transcripts and Enrollment Letters"),
        row([card([h("Official Transcript", "h3", GREEN), price("$15"),
                   text("<p>A signed official transcript of your student&rsquo;s Ascend coursework and grades, "
                        "for colleges, scholarships, employers or a new school.</p>", color=GREEN, size=16),
                   button("Order a Transcript", SITE + "/product/official-transcript/")], width=48),
             card([h("Enrollment Verification Letter", "h3", GREEN), price("Free"),
                   text("<p>Proof that your child is enrolled with Ascend, for your school district, Step Up For Students, "
                        "sports leagues or discounts. Free for enrolled families.</p>", color=GREEN, size=16),
                   button("Request a Letter", SITE + "/contact-us/", "outline")], width=48)]),
    ], band="green")],
}

# ------------------------------------------------------------ Graduation 2783 (a widget inside an existing container)
fix[2783] = {
    "asugm01": [container([
        text("<p><strong>Celebrate your graduate.</strong> Add the STEM-a-lotl Graduate sticker, "
             "a cap-and-tassel axolotl for water bottles and laptops.</p>", size=17),
        button("See the Graduate Sticker", SITE + "/product/stem-a-lotl-graduate-sticker/", "outline"),
    ], dict(clean(REF["section_container_band_light_green"]), margin=px(24, 0, 24, 0), margin_mobile=px(16, 0, 16, 0),
            padding=px(28, 24, 28, 24)), inner=True)],
}

# ------------------------------------------------------------ Shop 3477 (whole page)
fix[3477] = {
    "asush01": [
        section([h1("Ascend STEM Academy Shop"),
                 text("<p>Show your school pride, order records and reports, or unlock every axolotl game.</p>", size=19)]),
        section([h("Merch"), text("<p>T-shirts, hoodies, stickers and more.</p>", size=18),
                 shortcode('[products category="merch" limit="24" columns="4" orderby="menu_order"]')]),
        section([h("Student Services"), text("<p>Progress reports, IDs, transcripts, resume reviews and graduation.</p>", size=18),
                 shortcode('[products category="enhancements" limit="12" columns="4"]')], band="green"),
        section([h("Axolotl Game Passes"), text("<p>Unlock more puzzles in our free learning games.</p>", size=18),
                 shortcode('[products category="game-passes" limit="4" columns="2"]')]),
    ],
}

# ------------------------------------------------------------ Blog 3521 (widget before the grid)
fix[3521] = {
    "asubl01": [h1("Guides for Florida Homeschool Families"),
                text("<p>What to teach at every grade, homeschooling kids with ADHD, autism or anxiety, "
                     "scholarships and how umbrella schools work.</p>", size=19)],
}

# ------------------------------------------------------------ Time Card 6097 (widget before the time card)
fix[6097] = {
    "asutc01": [text("<p><strong>How attendance works:</strong> log hours here as you go. Ascend collects attendance four "
                     "times a year, at the end of March, June, September and December. Questions? Call "
                     "<a href=\"tel:+13863857653\" style=\"color:#037CAF\">(386) 385-7653</a>.</p>", size=16)],
}

# ------------------------------------------------------------ Privacy 4897 (whole page)
import re
policy = open(os.path.join(HERE, "..", "..", "ascend-site-updates", "tools", "privacy-policy.html"), encoding="utf-8").read()
body = re.search(r'<p class="asa-meta">.*</article>', policy, re.S).group(0).replace("</article>", "")
body = body.replace(' class="asa-meta"', "")
body = re.sub(r"<h2>", '<h2 style="color:#009CDE;font-size:26px;margin:28px 0 10px">', body)
policy_text = text(body, align="left", size=17)
policy_text["settings"]["align"] = "left"
fix[4897] = {
    "asupp01": [section([h1("Privacy Policy"),
                         container([policy_text], {"content_width": "boxed", "boxed_width": {"size": 820, "sizes": [], "unit": "px"},
                                                   "flex_align_items": "stretch"}, inner=True)])],
}

out = {str(k): v for k, v in fix.items()}
json.dump(out, open(os.path.join(HERE, "fix.json"), "w"), ensure_ascii=False, separators=(",", ":"))
print({k: list(v) for k, v in out.items()}, os.path.getsize(os.path.join(HERE, "fix.json")), "bytes")
