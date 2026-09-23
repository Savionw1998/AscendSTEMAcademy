# Read-only audit brief (ascendstemacademy.com)

STRICTLY READ-ONLY: never call any tool that writes, updates, deletes, publishes or undoes anything on the site.
Load tools with ToolSearch, e.g. "select:mcp__Ascend_STEM_Academy_MCP__wp_get_post_meta,mcp__Ascend_STEM_Academy_MCP__wp_get_post,mcp__Ascend_STEM_Academy_MCP__wp_get_page,mcp__Ascend_STEM_Academy_MCP__wp_get_post_revisions,mcp__Thinkrank__get-post-content,mcp__Thinkrank__get-post-seo".

Background: between 2026-09-22 and 2026-09-23 an assistant changed the site. The owner has since reverted some of it by hand
(in Elementor / WordPress). We need to know, item by item, what is STILL LIVE vs REVERTED (back to the original) vs
PARTIAL/OTHER (neither the original nor the assistant's version: the owner edited it differently).

Reference files in /home/user/AscendSTEMAcademy:
- ascend-site-updates/data/changes.json : the 63 Elementor edits (each has page, id, label, type, element, and find/replace or value).
  For "replace": original text = find, assistant text = replace. For insert/add_item: new elements have ids starting "asu".
  For remove: element id that was removed. For set: setting/value.
- site/CHANGES-2026-09-22.md, site/CHANGES-2026-09-23.md, site/CHANGES-2026-09-23-restyle.md : what else was changed.
- site/seo/pages.py (new SEO values) and site/seo/before-2026-09-22.json (original SEO values).
- site/restyle/<page>.html : HTML the assistant put into post_content of 8 pages (later switched back to Elementor).
- site/elementor-fix/fix.json : replacement Elementor sections ("a5xxxxx" ids) from a page-fix snippet that may or may not have been run.

For each item record: status (LIVE / REVERTED / PARTIAL / UNKNOWN), and one short line of evidence.
Also for each page: _elementor_edit_mode value, whether any "asu*" or "a5*" element ids exist, and anything that looks broken
(raw shortcodes, empty sections, placeholder text, duplicate sections). Also note if post_content still holds the assistant's
restyle HTML (class 'asa-why') while the page renders from Elementor.

Write your findings to the file named in your task (markdown, grouped by page, a table per page:
| Change | Status | Evidence |). Keep evidence short. Then reply with a 10-line summary.
