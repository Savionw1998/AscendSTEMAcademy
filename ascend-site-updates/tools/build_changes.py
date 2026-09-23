"""Builds data/changes.json for the Ascend Site Updates plugin.

Every find string below was copied from the live page data (decoded), so the
plugin can match it exactly. NB = the non-breaking space some pages contain.
Run: python3 ascend-site-updates/tools/build_changes.py
"""
import json
import os

NB = " "
SITE = "https://ascendstemacademy.com"
REG = SITE + "/registration/"
CALL = "https://calendar.app.google/Zrt44eK4625dPaz66"
ZERO = {"unit": "px", "top": "0", "right": "0", "bottom": "0", "left": "0", "isLinked": True}

PAGES = {
    2732: "Home",
    2780: "About Us",
    2781: "Enrollment",
    2782: "Program Enhancements",
    2783: "Graduation",
    2784: "FAQ",
    2785: "Contact Us",
    5025: "Resources and Information",
    4448: "Enrollment Agreement",
    3477: "Shop",
    3521: "Blog (Posts)",
    6097: "Time Card",
    4897: "Privacy Policy",
    7056: "My Account",
}

changes = []


def add(page, cid, label, change, why=""):
    change = dict(change)
    change.update({"id": cid, "page": page, "label": label})
    if why:
        change["why"] = why
    changes.append(change)


def html_widget(wid, html, title=""):
    return {"id": wid, "elType": "widget", "widgetType": "html", "isInner": False,
            "settings": {"html": html, "_title": title}, "elements": []}


def shortcode_widget(wid, code):
    return {"id": wid, "elType": "widget", "widgetType": "shortcode", "isInner": False,
            "settings": {"shortcode": code}, "elements": []}


def section(cid, *widgets):
    return {"id": cid, "elType": "container", "isInner": False,
            "settings": {"content_width": "boxed", "flex_direction": "column",
                         "padding": ZERO, "padding_mobile": ZERO,
                         "flex_gap": {"size": 0, "column": "0", "row": "0", "unit": "px", "isLinked": True}},
            "elements": list(widgets)}


def cta_box(heading, text, fine=True):
    fine_html = ('<p class="asa-fine">Questions first? <a href="%s/contact-us/">Contact us</a> or '
                 '<a href="%s">book a free 15-minute call</a>.</p>' % (SITE, CALL)) if fine else ""
    return ('<div class="asa-why asa-embed"><section class="asa-sec"><div class="asa-wrap"><div class="asa-cta">'
            '<h2>%s</h2><p>%s</p><div class="asa-btns">'
            '<a class="asa-btn asa-btn-primary" href="%s">Start enrollment</a>'
            '<a class="asa-btn asa-btn-ghost" href="%s">Book a free 15-minute call</a></div>%s'
            '</div></div></section></div>') % (heading, text, REG, CALL, fine_html)


# ---------------------------------------------------------------- Home 2732
add(2732, "home-h1", "Make the hero tagline the page's main heading (H1)",
    {"type": "set", "element": "0c61f50", "setting": "header_size", "value": "h1"},
    "Search engines read the H1 as the page's topic. The tagline is currently an H5.")
add(2732, "home-h1-whoweare", "Change “Who We Are” from H1 to H2",
    {"type": "set", "element": "a41b579", "setting": "header_size", "value": "h2"},
    "A page should have one H1.")
add(2732, "home-h1-wizard", "Change the compliance wizard title from H1 to H2",
    {"type": "replace", "element": "45b45fbd", "setting": "html",
     "find": "<h1>Florida Homeschool<br>Compliance Wizard</h1>",
     "replace": "<h2>Florida Homeschool<br>Compliance Wizard</h2>", "count": 1})
add(2732, "home-h1-wizard-css", "Keep the wizard title's styling after the H2 change",
    {"type": "replace", "element": "45b45fbd", "setting": "html",
     "find": "#ascend-compliance-wizard h1{", "replace": "#ascend-compliance-wizard .acw-header h2{", "count": 1})
add(2732, "home-empty", "Remove the empty HTML block in the hero",
    {"type": "remove", "element": "9fe7454"})
add(2732, "home-noi", "Notice of Intent: say Ascend files it for you",
    {"type": "replace", "element": "4f7d1f5", "setting": "editor",
     "find": "Submitting a Notice of Intent" + NB,
     "replace": "Filing your own Notice of Intent (Ascend files the required notice for you)", "count": 1},
    "Matches your answer: Ascend files the notice with the district.")
add(2732, "home-wizard-noi", "Wizard: correct the Notice of Intent and evaluation wording",
    {"type": "replace", "element": "45b45fbd", "setting": "html",
     "find": "means no separate Notice of Intent to the district and no annual evaluation filing on your own — Ascend handles that as your school of record.",
     "replace": "means you don’t file a Notice of Intent yourself (Ascend files the required notice with your district as your school of record), and no annual evaluation is required.",
     "count": 1})
add(2732, "home-wizard-statute", "Wizard: link to the current Florida statute instead of the 2024 edition",
    {"type": "replace", "element": "45b45fbd", "setting": "html",
     "find": "https://www.flsenate.gov/Laws/Statutes/2024/1002.41",
     "replace": "http://www.leg.state.fl.us/statutes/index.cfm?App_mode=Display_Statute&amp;URL=1000-1099/1002/Sections/1002.41.html",
     "count": 1},
    "The Legislature's link always shows the statute as currently in force.")

GAMES = [
    ("Guess-a-lotl", "guess-a-lotl", "Daily STEM word game", "Capture-removebg-preview.png"),
    ("Connect-a-lotl", "connect-a-lotl", "Find the four hidden groups", "Capture-removebg-preview-2.png"),
    ("Cross-a-lotl", "cross-a-lotl", "Daily axolotl word search", "axolotl_vector_image-removebg-preview.png"),
    ("Hex-a-lotl", "hex-a-lotl", "Hexagon logic puzzle", "Capture-removebg-preview-1.png"),
    ("Grow-a-lotl", "grow-a-lotl", "Daily stem-cell growth puzzle", "stem-a-lotl-scientist-sticker.png"),
]
games_cards = "".join(
    '<a class="asa-game" href="%s/%s/"><img src="%s/wp-content/uploads/2026/09/%s" alt="" loading="lazy" width="88" height="88">'
    '<span class="asa-game-name">%s</span><span class="asa-game-desc">%s</span><span class="asa-more">Play &rarr;</span></a>'
    % (SITE, slug, SITE, img, name, desc) for name, slug, desc, img in GAMES)
games_html = (
    '<div class="asa-why asa-embed"><section class="asa-sec asa-games" aria-labelledby="asa-games-h"><div class="asa-wrap">'
    '<div class="asa-center"><span class="asa-eyebrow">Free axolotl learning games</span>'
    '<h2 id="asa-games-h">Play, puzzle and learn every day</h2>'
    '<p class="asa-lead asa-mt">Quick daily puzzles that build vocabulary, logic and STEM thinking, starring our axolotl mascot.</p></div>'
    '<div class="asa-game-grid">' + games_cards + '</div>'
    '<p class="asa-pass">Can’t wait for tomorrow’s puzzle? The <a href="' + SITE +
    '/product/axolotl-games-full-pond-pass/">Full Pond Pass</a> unlocks every game with no daily wait.</p>'
    '</div></section></div>')
add(2732, "home-games", "Add a “Play free axolotl games” band after Benefits of Enrollment",
    {"type": "insert", "after": "102a02d", "new": section("asuhg01", html_widget("asuhg02", games_html, "Games band"))})

guides_head = ('<div class="asa-why asa-embed"><section class="asa-sec" aria-labelledby="asa-guides-h"><div class="asa-wrap">'
               '<div class="asa-center"><span class="asa-eyebrow">From the blog</span>'
               '<h2 id="asa-guides-h">Latest guides for Florida homeschool families</h2></div></div></section></div>')
guides_foot = ('<div class="asa-why asa-embed"><p class="asa-center" style="margin:18px 0 48px">'
               '<a class="asa-btn asa-btn-outline" href="' + SITE + '/posts/">See all guides</a></p></div>')
add(2732, "home-guides", "Add a “Latest guides” row at the end of the page",
    {"type": "insert", "at_end": True,
     "new": section("asuhl01", html_widget("asuhl02", guides_head, "Latest guides heading"),
                    shortcode_widget("asuhl03", '[ascend_latest_guides count="4"]'),
                    html_widget("asuhl04", guides_foot, "See all guides"))},
    "Updates itself: always shows the four newest posts.")

# ---------------------------------------------------------------- About 2780
add(2780, "about-trips", "Remove field trips and workshops from Support and Guidance",
    {"type": "replace", "element": "25cc1b3", "setting": "description",
     "find": "including guides, project ideas, field trips, workshops, and college and career support",
     "replace": "including guides, project ideas, and college and career support", "count": 1},
    "Your answer: take field trips off the site until they launch.")
add(2780, "about-video", "Remove the stock drone video from the header",
    {"type": "set", "element": "235ba13", "setting": "background_video_link", "value": None},
    "It's a third-party clip (IndigoDrone) and wasn't displaying anyway.")
compare_html = (
    '<div class="asa-why asa-embed"><section class="asa-sec" aria-labelledby="asa-about-cmp"><div class="asa-wrap">'
    '<div class="asa-center"><span class="asa-eyebrow">How we compare</span>'
    '<h2 id="asa-about-cmp">What you get with Ascend</h2>'
    '<p class="asa-lead asa-mt">Every umbrella school keeps your child’s records. Here’s what Ascend adds on top.</p></div>'
    '<div class="asa-compare">'
    '<div class="asa-col is-them"><h3>A basic umbrella school</h3><ul>'
    '<li><span class="asa-mark asa-x" aria-hidden="true"></span>Records filed through an automated portal</li>'
    '<li><span class="asa-mark asa-x" aria-hidden="true"></span>Questions wait in a support-ticket queue</li>'
    '<li><span class="asa-mark asa-x" aria-hidden="true"></span>A generic diploma and a basic record</li>'
    '<li><span class="asa-mark asa-x" aria-hidden="true"></span>You find curriculum and resources on your own</li>'
    '<li><span class="asa-mark asa-x" aria-hidden="true"></span>No particular focus</li>'
    '</ul></div>'
    '<div class="asa-col is-us"><span class="asa-badge">Ascend STEM Academy</span><h3>Support that goes further</h3><ul>'
    '<li><span class="asa-mark asa-ck" aria-hidden="true"></span>A real person reviews your child’s file and files the district notice for you</li>'
    '<li><span class="asa-mark asa-ck" aria-hidden="true"></span>Same-day or next-day answers from someone who knows your family</li>'
    '<li><span class="asa-mark asa-ck" aria-hidden="true"></span>A 24-credit diploma and transcript aligned to Florida standards</li>'
    '<li><span class="asa-mark asa-ck" aria-hidden="true"></span>Grade-by-grade guides and 60+ curated resources included</li>'
    '<li><span class="asa-mark asa-ck" aria-hidden="true"></span>A STEM focus, with free axolotl learning games for kids</li>'
    '<li><span class="asa-mark asa-ck" aria-hidden="true"></span>Optional progress reports, IDs, resume reviews and college and trade consulting</li>'
    '</ul></div></div></div></section></div>')
add(2780, "about-compare", "Add a “What you get with Ascend” comparison",
    {"type": "insert", "before": "6249777", "new": section("asuac01", html_widget("asuac02", compare_html, "How we compare"))},
    "Your request: talk about the school and what it offers compared to others.")
add(2780, "about-cta", "End the page with Enroll and Book a call buttons",
    {"type": "insert", "at_end": True,
     "new": section("asuat01", html_widget("asuat02", cta_box(
         "Ready to join the Ascend STEM family?",
         "Enrollment is open year-round for grades K–12.", fine=True), "Closing call to action"))})
add(2780, "about-contact-btn", "Remove the old lone Contact Us button (the new closing section links to Contact)",
    {"type": "remove", "element": "6249777"})

# ---------------------------------------------------------------- Enrollment 2781
add(2781, "enr-steps", "Clarify the enrollment steps (registration first)",
    {"type": "replace", "element": "e94544a", "setting": "editor",
     "find": "Please scroll to the bottom of your account and complete the <strong>Enrollment Form</strong> for the child you wish to enroll.",
     "replace": "Create your family account, then open the <strong>Enrollment Form</strong> in your account and complete it for each child you are enrolling.",
     "count": 1},
    "Your answer: keep Registration first.")
add(2781, "enr-flvs", "Reword “Opportunity Participation in Florida Virtual School”",
    {"type": "replace", "element": "4ce4b27", "setting": "icon_list",
     "find": "Opportunity Participation in Florida Virtual School",
     "replace": "Option to take free FLVS Flex courses", "count": 1})
add(2781, "enr-trips", "Remove “Field Trips and Workshops (coming soon!)”",
    {"type": "remove_item", "element": "c7a115c", "setting": "icon_list", "match_key": "text", "match": "Field Trips and Workshops"})
add(2781, "enr-strattic", "Remove the background image that loads from a template site",
    {"type": "set", "element": "L8eKkSJ", "setting": "background_image", "value": None},
    "It pointed at gene-2697.live.strattic.io and wasn't displaying.")
tuition_html = (
    '<div class="asa-why asa-embed"><section class="asa-sec" aria-label="Tuition"><div class="asa-wrap">'
    '<div class="asa-tuition-grid">'
    '<div class="asa-tcard"><h3>First-time enrollment</h3><dl>'
    '<div><dt>Grades K–8</dt><dd>$185<span>/year</span></dd></div>'
    '<div><dt>Grades 9–12</dt><dd>$200<span>/year</span></dd></div></dl></div>'
    '<div class="asa-tcard"><h3>Re-enrollment</h3><dl>'
    '<div><dt>Grades K–8</dt><dd>$165<span>/year</span></dd></div>'
    '<div><dt>Grades 9–12</dt><dd>$175<span>/year</span></dd></div></dl></div>'
    '<div class="asa-tcard asa-hl"><h3>Siblings save 10%</h3>'
    '<p>Enrolling more than one child? Every additional child in the same order is 10% off. It’s applied automatically at checkout.</p></div>'
    '</div><p class="asa-tnote">Your child’s school year runs for 12 months from the day you enroll. Enrollment is open year-round.</p>'
    '</div></section></div>')
add(2781, "enr-tuition", "Show tuition as a plain table (with sibling pricing)",
    {"type": "insert", "after": "7d42347", "new": section("asuet01", html_widget("asuet02", tuition_html, "Tuition table"))},
    "Hover cards hide prices from Google and are awkward on phones.")
add(2781, "enr-hover", "Remove the old hover-reveal tuition cards",
    {"type": "remove", "element": "3e4b691"})

# ---------------------------------------------------------------- Enhancements 2782
add(2782, "enh-joann", "Remove Joann Fabrics (closed in 2025)",
    {"type": "replace", "element": "1ee05e5", "setting": "editor",
     "find": "Michael’s, Joann Fabrics, Office Depot, and Barnes &amp; Noble.",
     "replace": "Michael’s, Office Depot, and Barnes &amp; Noble.", "count": 1})
add(2782, "enh-hpa", "Explain the teacher verification letter",
    {"type": "replace", "element": "1ee05e5", "setting": "editor",
     "find": "a Home Physical Address (HPA) Teacher Verification Letter",
     "replace": "a Teacher Verification Letter (a letter from Ascend confirming you are your enrolled student’s home educator)",
     "count": 1})
for n in ("1", "2", "3"):
    add(2782, "enh-delivery-" + n, "Remove repeated “Delivered in 5–7 business days” line (resume card %s)" % n,
        {"type": "remove", "element": "rrt000" + n},
        "The page header already states delivery time.")
add(2782, "enh-trips", "Remove the Field Trips and Workshops section",
    {"type": "remove", "element": "e7af7fd"},
    "Also removes the broken /blog/ link that lived in it.")
records_html = (
    '<div class="asa-why asa-embed"><section class="asa-sec" aria-labelledby="asa-records-h"><div class="asa-wrap">'
    '<div class="asa-center"><span class="asa-eyebrow">Records on request</span>'
    '<h2 id="asa-records-h">Official transcripts and enrollment letters</h2></div>'
    '<div class="asa-grid asa-grid-2">'
    '<div class="asa-card"><h3>Official transcript</h3><p class="asa-price">$15</p>'
    '<p>A signed official transcript of your student’s Ascend coursework and grades, for colleges, scholarships, employers or a new school.</p>'
    '<p style="margin-top:14px"><a class="asa-btn asa-btn-blue" href="' + SITE + '/product/official-transcript/">Order a transcript</a></p></div>'
    '<div class="asa-card"><h3>Enrollment verification letter</h3><p class="asa-price">Free</p>'
    '<p>Proof that your child is enrolled with Ascend, for your school district, Step Up For Students, sports leagues or discounts. Free for enrolled families.</p>'
    '<p style="margin-top:14px"><a class="asa-btn asa-btn-outline" href="' + SITE + '/contact-us/">Request a letter</a></p></div>'
    '</div></div></section></div>')
add(2782, "enh-records", "Add official transcript ($15) and free enrollment verification letter",
    {"type": "insert", "after": "rr00001", "new": section("asuer01", html_widget("asuer02", records_html, "Transcripts and letters"))},
    "Your prices: transcript $15, letter free.")

# ---------------------------------------------------------------- Graduation 2783
add(2783, "grad-typo1", "Fix “Please selecting”",
    {"type": "replace", "element": "efdf4e1", "setting": "editor", "find": "Please selecting the listed course", "replace": "please select the listed course", "count": 1})
add(2783, "grad-typo2", "Fix “no different than … transcript and diploma”",
    {"type": "replace", "element": "85fb602", "setting": "editor",
     "find": "are no different than traditional private school transcript and diploma.",
     "replace": "are no different from traditional private school transcripts and diplomas.", "count": 1})
add(2783, "grad-typo3", "Fix “Career and Technical Educations”",
    {"type": "replace", "element": "4f07a57", "setting": "content", "find": "Career and Technical Educations", "replace": "Career and Technical Education", "count": 1})
add(2783, "grad-geometry", "Rename “Geometry 1” to “Geometry”",
    {"type": "replace", "element": "7402a87", "setting": "content", "find": "Geometry 1 (1 credit)", "replace": "Geometry (1 credit)", "count": 1})
add(2783, "grad-pe", "Remove Physical Education from the Science card (it's covered by H.O.P.E.)",
    {"type": "replace", "element": "9879983", "setting": "content",
     "find": '<p><span style="font-weight: 400;">Physical Education</span></p>', "replace": "", "count": 1, "done_if_absent": True})
old_electives = "<p>" + NB + "</p>" + "".join(
    '<p><span style="font-weight: 400;">%s</span></p>' % x for x in
    ["Agriscience", "Anatomy and Physiology", "Chemistry 1", "Earth-Space Science", "Environmental Science",
     "Forensic Science" + NB, "Marine Science 1", "Natural Resources and Conservation", "Physical Science", "Physics"])
new_electives = "".join("<p>%s</p>" % x for x in [
    "Computer Science and Coding", "Engineering and Robotics", "World Languages (Spanish, ASL and more)",
    "Psychology", "Economics", "Art, Music or Theatre", "Career and Technical Education courses",
    "Extra math and science courses (for example Chemistry or Physics)"])
add(2783, "grad-electives", "Replace the Electives sample list (it was a copy of the Science list)",
    {"type": "replace", "element": "6be3f96", "setting": "content", "find": old_electives, "replace": new_electives, "count": 1})
add(2783, "grad-math-blank", "Remove the five empty lines at the bottom of the Math card",
    {"type": "replace", "element": "b2e3bc6", "setting": "content",
     "find": ("<p>" + NB + "</p>") * 5, "replace": "", "count": 1, "done_if_absent": True})
grad_merch = ('<div class="asa-why asa-embed"><div class="asa-callout" style="margin:24px 0">'
              '<p><strong>Celebrate your graduate.</strong> Add the STEM-a-lotl Graduate sticker, a cap-and-tassel axolotl for water bottles and laptops.</p>'
              '<a class="asa-btn asa-btn-outline" href="' + SITE + '/product/stem-a-lotl-graduate-sticker/">See the Graduate sticker</a></div></div>')
add(2783, "grad-merch", "Add a graduation merch callout under the Order button",
    {"type": "insert", "after": "1401f9c", "new": html_widget("asugm01", grad_merch, "Graduation merch")})

# ---------------------------------------------------------------- FAQ 2784
ACC = "b973208"


def faq_set(cid, label, match, key, value, why=""):
    add(2784, cid, label, {"type": "set_item", "element": ACC, "setting": "uc_items",
                           "match_key": "title", "match": match, "key": key, "value": value}, why)


faq_set("faq-lorem", "Remove the “Lorem ipsum” placeholder", "Is an Ascend STEM Academy diploma different", "heading", "")
faq_set("faq-withdraw", "Replace the pasted page code in “Can we withdraw?” with the real answer", "Can we withdraw?", "content",
        "<p>Yes. Simply contact us via email to terminate enrollment. Your withdrawal will be recorded on the date that your request was sent. "
        "Ascend STEM Academy does not offer refunds if a student withdraws before his or her school year is completed. "
        "A student who withdraws from Ascend STEM Academy can enroll in public or private school or begin a course of homeschool.</p>")
faq_set("faq-noi", "Superintendent answer: Ascend files the notice; fix the dead blog reference", "must I notify the superintendent", "content",
        "<p>No, you don’t file anything with the superintendent yourself. Once your child is enrolled with Ascend, they are a private "
        "school student rather than a home education student, and Ascend files the required notice with your school district as their "
        "school of record. For more, read <a href=\"" + SITE + "/umbrella-schools-flexibility-support-and-legal-compliance/\">"
        "Is an Umbrella School the Right Choice for Your Family?</a></p>",
        "Matches your answer: Ascend files the notice.")
faq_set("faq-whypay", "Fix the broken “Why pay” link", "Why pay Ascend STEM Academy?", "link.url", SITE + "/why-choose-ascend-stem-academy/")
faq_set("faq-flvs", "Rewrite the garbled FLVS Flex answer", "enroll in Florida Virtual School", "content",
        "<p>Yes! Ascend STEM Academy students can take free FLVS Flex courses, with hundreds of options for grades K–12. "
        "We’ll send registration details in your Parent-Teacher Manual when you enroll.</p>")
faq_set("faq-180", "Correct “180 calendar days”", "How many days of school are required?", "content",
        "<p>Ascend students complete 180 days of instruction per school year. That means 180 school days, not calendar days. "
        "Track them as you go on your time card.</p>")
faq_set("faq-dual", "Tidy the dual enrollment answer", "dual enroll", "content",
        "<p>Yes, they can. The <strong>College of Central Florida</strong> is the main option for families in Marion, Citrus and Levy "
        "counties. Families in other areas can ask their local state college about dual enrollment for private school students. "
        "See our <a href=\"" + SITE + "/ascend-stem-academy-resources-and-information/\">Resources page</a> for details.</p>")
FAQ_ITEM = {"heading": "", "link": {"url": "#"}, "button_text": "Learn More", "template": {"template": ""},
            "image": {"url": SITE + "/wp-content/plugins/unlimited-elements-for-elementor/images/placeholder.png"},
            "additional_heading_content": "<i class=\"fa fa-star\"></i>", "_generated_id": "4fzv2",
            "item_repeater_class": "elementor-repeater-item-4fzv2"}
for cid, pos, _id, title, content in [
    ("faq-scholar", 16, "asufq01", "Can we use a Florida scholarship like Step Up For Students?",
     "<p>Scholarship rules differ by program and change from year to year. Read our guide, "
     "<a href=\"" + SITE + "/understanding-florida-school-choice-scholarships-and-florida-umbrella-schools/\">Understanding Florida School Choice "
     "Scholarships and Florida Umbrella Schools</a>, or <a href=\"" + SITE + "/contact-us/\">contact us</a> to talk through your situation. "
     "We can provide an enrollment verification letter if your program asks for one.</p>"),
    ("faq-midyear", 17, "asufq02", "Can we switch to Ascend in the middle of the school year?",
     "<p>Yes. Enrollment is open year-round, and your child’s Ascend school year starts the day you enroll. If your child is in "
     "public school now, the withdrawal letter generator on our <a href=\"" + SITE + "/enrollment/\">Enrollment page</a> writes the "
     "notice for your current school.</p>"),
]:
    item = dict(FAQ_ITEM, _id=_id, title=title, content=content)
    add(2784, cid, "Add FAQ: " + title,
        {"type": "add_item", "element": ACC, "setting": "uc_items", "item": item, "match_key": "title", "position": pos})

# ---------------------------------------------------------------- Contact 2785
add(2785, "contact-tel", "Fix the phone link so phones can dial it",
    {"type": "replace", "element": "93d7129", "setting": "link.url", "find": "tel:+1386385-7653", "replace": "tel:+13863857653", "count": 1})

# ---------------------------------------------------------------- Resources 5025
add(5025, "res-22", "Fix the FLVS registration link (stray %22 at the end)",
    {"type": "replace", "element": "53a3d09", "setting": "eael_cta_btn_link.url",
     "find": "?source=counselor-resources%22", "replace": "?source=counselor-resources", "count": 1})
add(5025, "res-register", "Fix “Log-In/Create Account” link (/register/ → /registration/)",
    {"type": "replace", "element": "3256f41", "setting": "eael_cta_secondary_btn_link.url",
     "find": SITE + "/register/", "replace": REG, "count": 1})
add(5025, "res-explorer", "Fix the Resource Explorer button link (it had no https://)",
    {"type": "replace", "element": "54e7ac5", "setting": "eael_cta_btn_link.url",
     "find": "ascendstemacademy.com/homeschool-resource-explorer",
     "replace": SITE + "/ascend-stem-academy-resources-and-information/homeschool-resource-explorer/", "count": 1})
add(5025, "res-claim", "Remove the “hands-on STEM programs and in-person support” claim",
    {"type": "replace", "element": "53a3d09", "setting": "eael_cta_content",
     "find": "while gaining access to Ascend STEM Academy’s hands-on STEM programs and in-person support.",
     "replace": "while Ascend STEM Academy keeps their records and provides guidance and one-on-one support.", "count": 1})
add(5025, "res-subtitle", "Remove the “This is a sub title” placeholder",
    {"type": "set", "element": "bbf51b5", "setting": "eael_infobox_sub_title", "value": ""})

# ---------------------------------------------------------------- Agreement 4448
AG = "6facabc"
for cid, label, find, rep in [
    ("agr-spaces", "Remove the stray spaces in “enroll my child at …”",
     "enroll my child at&nbsp; &nbsp; &nbsp; &nbsp;Ascend STEM Academy:", "enroll my child at Ascend STEM Academy:"),
    ("agr-to", "Add the missing “to” (permitted to use)", "is permitted use and store this information", "is permitted to use and store this information"),
    ("agr-number", "Fold the unnumbered Welcome Packet sentence into item 2",
     "2. To submit a quarterly report of daily attendance to the school administration.<br>To fulfill all requirements documented in the&nbsp;Policies section of your Welcome Packet&nbsp;or otherwise requested by Ascend STEM Academy.",
     "2. To submit a quarterly report of daily attendance to the school administration, and to fulfill all requirements documented in the Policies section of the Welcome Packet or otherwise requested by Ascend STEM Academy."),
    ("agr-statute", "Replace “the Private School Act” with the actual Florida statute",
     "as related to the Private School Act.", "as related to private schools (Section 1002.42, Florida Statutes)."),
    ("agr-voice", "Keep the agreement in the parent's voice (“I release”)", "We release and hold harmless", "I release and hold harmless"),
    ("agr-sign", "Match the signature wording to the checkbox",
     "By signing my name and dating this document, I agree", "By checking the agreement box and submitting my enrollment, I agree"),
]:
    add(4448, cid, label, {"type": "replace", "element": AG, "setting": "editor", "find": find, "replace": rep, "count": 1})

# ---------------------------------------------------------------- Shop 3477
shop_intro = ('<div class="asa-why asa-embed"><section class="asa-sec" style="padding-top:140px"><div class="asa-wrap"><div class="asa-center">'
              '<span class="asa-eyebrow">Ascend STEM Academy shop</span><h1 style="font-size:clamp(2rem,4.4vw,3rem);color:var(--asa-navy)">Merch, student services and game passes</h1>'
              '<p class="asa-lead asa-mt">Show your school pride, order records and reports, or unlock every axolotl game.</p></div></div></section></div>')


def shop_heading(text, sub):
    return ('<div class="asa-why asa-embed"><div class="asa-center" style="margin:36px 0 18px"><h2>%s</h2>'
            '<p class="asa-lead asa-mt">%s</p></div></div>') % (text, sub)


add(3477, "shop-rebuild", "Rebuild the shop page: Merch, Student services and Game passes (tuition stays off)",
    {"type": "replace_all", "elements": [
        section("asush01", html_widget("asush02", shop_intro, "Shop intro"),
                html_widget("asush03", shop_heading("Merch", "T-shirts, hoodies, stickers and more."), "Merch heading"),
                shortcode_widget("asush04", '[products category="merch" limit="24" columns="4" orderby="menu_order"]'),
                html_widget("asush05", shop_heading("Student services", "Progress reports, IDs, transcripts, resume reviews and graduation."), "Services heading"),
                shortcode_widget("asush06", '[products category="enhancements" limit="12" columns="4"]'),
                html_widget("asush07", shop_heading("Axolotl game passes", "Unlock more puzzles in our free learning games."), "Games heading"),
                shortcode_widget("asush08", '[products category="game-passes" limit="4" columns="2"]'))]},
    "The old layout was stored in a broken format, so the page showed a stale “Report Card $15” listing.")

# ---------------------------------------------------------------- Blog 3521
blog_intro = ('<div class="asa-why asa-embed"><section class="asa-sec" style="padding-top:140px"><div class="asa-wrap"><div class="asa-center">'
              '<span class="asa-eyebrow">The Ascend blog</span><h1 style="font-size:clamp(2rem,4.4vw,3rem);color:var(--asa-navy)">Guides for Florida homeschool families</h1>'
              '<p class="asa-lead asa-mt">What to teach at every grade, homeschooling kids with ADHD, autism or anxiety, scholarships and how umbrella schools work.</p>'
              '</div></div></section></div>')
add(3521, "blog-intro", "Add a heading and introduction to the blog",
    {"type": "insert", "before": "5cbf73c", "new": html_widget("asubl01", blog_intro, "Blog intro")})
add(3521, "blog-grid", "Replace the broken post-list shortcode with a working grid and topic filters",
    {"type": "replace", "element": "5cbf73c", "setting": "shortcode",
     "find": '[bdp_post design="design-2" grid="2"]', "replace": '[ascend_latest_guides count="50" filters="1"]', "count": 1},
    "The Blog Designer plugin it needed isn't installed, so visitors saw the raw [bdp_post] text.")

# ---------------------------------------------------------------- Time Card 6097
tc_note = ('<div class="asa-why asa-embed"><p class="asa-note" style="margin:0 0 18px">'
           '<strong>How attendance works:</strong> log hours here as you go. Ascend collects attendance four times a year, '
           'at the end of March, June, September and December. Questions? Call (386) 385-7653.</p></div>')
add(6097, "tc-note", "Explain quarterly attendance and add the office phone number",
    {"type": "insert", "before": "7939a138", "new": html_widget("asutc01", tc_note, "Attendance note")},
    "Your answer: attendance is collected quarterly.")

# ---------------------------------------------------------------- My Account 7056
add(7056, "myacct-link", "Point “Student Account” to the page where profiles and time cards live",
    {"type": "replace", "element": "557b4894", "setting": "editor",
     "find": 'href="' + SITE + '/account/"', "replace": 'href="' + SITE + '/user/"', "count": 1})

# ---------------------------------------------------------------- Privacy 4897
with open(os.path.join(os.path.dirname(__file__), "privacy-policy.html"), encoding="utf-8") as fh:
    privacy_html = fh.read().strip()
add(4897, "privacy-rewrite", "Replace the Privacy Policy with an updated version",
    {"type": "replace_all", "elements": [section("asupp01", html_widget("asupp02", privacy_html, "Privacy Policy"))]},
    "Covers student records, health and immunization forms, time cards, game progress, payments, cookies and analytics. Please read it before applying.")

out = {"version": "2026-09-23", "pages": {str(k): v for k, v in PAGES.items()}, "changes": changes}
path = os.path.join(os.path.dirname(__file__), "..", "data", "changes.json")
os.makedirs(os.path.dirname(path), exist_ok=True)
with open(path, "w", encoding="utf-8") as fh:
    json.dump(out, fh, ensure_ascii=False, indent=1)
ids = [c["id"] for c in changes]
assert len(ids) == len(set(ids)), "duplicate change ids"
print("%d changes across %d pages -> %s" % (len(changes), len(PAGES), os.path.normpath(path)))
