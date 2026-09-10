# Plugins

Only **first-party** plugins — the ones authored by Ascend STEM Academy — are
tracked in git. They match the `ascend-*` directory prefix; everything else in
this directory is git-ignored.

Production runs these seven first-party plugins. None of them are in this
repository yet — copy each one from `wp-content/plugins/` on the live server
and commit it:

| Directory (expected) | Plugin | Version in production |
|---|---|---|
| `ascend-enrollment-time-cards/` | Ascend Enrollment & Time Cards | 1.16.2 |
| `ascend-lead-capture/` | Ascend Lead Capture | 1.6.0 |
| `ascend-living-transcript/` | Ascend Living Transcript | 1.3.1 |
| `ascend-student-dashboard/` | Ascend Student Dashboard | 1.2.2 |
| `ascend-re-enrollment-referrals/` | Ascend Re-Enrollment & Referrals | 1.3.0 |
| `ascend-axolotl-games/` | Ascend Axolotl Games | 2.0.0 |
| `ascend-pwa/` | Ascend PWA | 1.0.0 |

Directory names are a guess based on the plugin titles — use whatever the real
directories are called on the server. As long as they start with `ascend-`,
they will be tracked and mounted into the container automatically.

Several styles in the child theme's `style.css` target markup these plugins
render, so the site will not look right locally until they are present:

- `.spf-*` — STEM Path Finder widget
- `#ascend-compliance-wizard` — Florida Homeschool Compliance Wizard
- `.wlg-wrap` — Withdrawal Letter Generator
- `.ascend-ann` — Dashboard Announcements panel

Third-party plugins are installed into this directory by `make plugins` and are
not committed. See `docs/parity.md`.
