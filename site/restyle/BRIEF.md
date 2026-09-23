# Brief: convert one page to the new design (read-only on the live site)

Never call any write/update/delete tool on the site.

1. Load tools with ToolSearch: "select:mcp__Thinkrank__get-post-content,mcp__Ascend_STEM_Academy_MCP__wp_get_post_meta".
   Fetch get-post-content(post_id) AND wp_get_post_meta(post_id, key="_elementor_data"). _elementor_data is the source of truth
   (ucaddon_* / eael-* widgets do not render in get-post-content). Collect all visible text, link URLs, image URLs + alt, buttons,
   lists, accordion items, prices, and any shortcodes/forms/embeds. Skip hidden/empty widgets and editor placeholders.
2. Study the design: /home/user/AscendSTEMAcademy/site/why-choose/page-content.html (finished page in this style) and
   /home/user/AscendSTEMAcademy/ascend-site-updates/assets/site.css (available .asa-* classes).
3. Write /home/user/AscendSTEMAcademy/site/restyle/<slug>.html as Gutenberg blocks
   "<!-- wp:html -->\n<div class='asa-why'>...</div>\n<!-- /wp:html -->", one per section, like the reference.
   - Keep wording essentially the same (fix obvious typos only). Keep ALL info, prices, links, images. Invent nothing. No emoji.
   - New structure: asa-hero first (eyebrow, the one h1, short sub, the page's existing main buttons), then sections alternating
     plain / asa-alt, cards/grids for lists, closing asa-cta if the page had a call to action.
   - Single-quoted attributes; absolute https://ascendstemacademy.com/ URLs.
   - Images: <img src='...' alt='...' loading='lazy' style='width:100%;height:auto;border-radius:20px'>.
   - Accordions: <details class='asa-faq'><summary>Q</summary><div><p>A</p></div></details>.
   - Shortcodes/forms preserved: close the html block, add "<!-- wp:shortcode -->\n[...]\n<!-- /wp:shortcode -->", reopen.
     An Elementor form widget (not a shortcode): report it; use a mailto/phone button in its place.
   - Mobile-first; no inline grid-template-columns.
   - New classes go in /home/user/AscendSTEMAcademy/site/restyle/<slug>.css, every selector prefixed ".asa-why ", using the
     same variables. Keep it small. Only the faq page defines .asa-faq.
4. Validate with python3: parses (html.parser), block comments balanced, exactly one <h1>.

Report (under 25 lines per page): files written, sections in order, anything not carried over, any dropped text and why.
