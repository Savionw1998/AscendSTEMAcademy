# Bringing local closer to production

The default `make up` gives you a clean WordPress install with the Astra
parent and our `astra-child` theme. That is enough for most theme work.

Production runs **39 active plugins** and real content, so some bugs only
appear with more of that context. This document covers how to add it.

## What production runs (recorded 2026-09-10)

Captured through the site's WordPress MCP connection so the next person does
not have to re-derive it.

- WordPress 7.1, PHP 8.3.33, MySQL 5.7.44
- Astra 4.13.11 parent, Astra Child 1.1.0
- 38 active plugins, 28 published pages, 26 published posts
- Permalinks `/%postname%/`, timezone America/New_York
- Customizer "Additional CSS" is empty — it was migrated into the child
  theme's `style.css` on 2026-09-09

### First-party plugins (your code — not on wordpress.org)

| Plugin | Version |
|---|---|
| Ascend Enrollment & Time Cards | 1.16.2 |
| Ascend Lead Capture | 1.6.0 |
| Ascend Living Transcript | 1.3.1 |
| Ascend Student Dashboard | 1.2.2 |
| Ascend Re-Enrollment & Referrals | 1.3.0 |
| Ascend Axolotl Games | 2.0.0 |
| Ascend PWA | 1.0.0 |

These must be copied from the server into `wp-content/plugins/` and committed.
See `wp-content/plugins/README.md`.

## Installing third-party plugins

`make plugins` installs the twelve the site needs to render, pinned to the
versions production runs. It is idempotent, and `make up` calls it for you.

Anything else from wordpress.org:

```bash
make wp CMD="plugin install wp-crontrol --activate"
```

Commercial plugins (Essential Addons Pro, Unlimited Elements Pro, Astra Pro)
are not on wordpress.org. Install them from a zip:

```bash
docker compose cp ~/Downloads/addon.zip wordpress:/tmp/addon.zip
make wp CMD="plugin install /tmp/addon.zip --activate"
```

Third-party plugins land in `wp-content/plugins/` on your machine but are
git-ignored, so they survive `make down` and are removed by `make destroy`.

### Deliberately skipped locally

W3 Total Cache and Cloudflare cache your changes away; Wordfence and Akismet
want API keys; Jetpack and Site Kit want connected accounts; UpdraftPlus has
nothing to back up; WooPayments, WooCommerce Shipping and Tax need real
merchant accounts; Royal MCP connects the *live* site to AI assistants;
Advanced Database Cleaner is destructive. `scripts/plugins.sh` has the full
list. Install any of them by hand if you are specifically debugging it.

## Working with a copy of production content

Only do this when you actually need real content, and treat the dump as
sensitive: it contains student and family data. `db/` and `*.sql` are
git-ignored precisely so a dump cannot be committed by accident.

1. Export from production (via the host's phpMyAdmin, a backup plugin, or
   `wp db export` over SSH) and save it as `db/local.sql`.
2. Import it:

   ```bash
   make db-import
   ```

3. Rewrite the URLs so links and assets resolve locally. Elementor stores page
   layouts as serialized JSON in post meta, so this **must** run through
   `search-replace`, which rewrites serialized data safely — a SQL find/replace
   will corrupt every Elementor page:

   ```bash
   make wp CMD="search-replace 'https://ascendstemacademy.com' 'http://localhost:8080' --all-tables --dry-run"
   # review the counts, then run it for real without --dry-run
   ```

   Then flush Elementor's compiled CSS so it regenerates against the new URLs:

   ```bash
   make wp CMD="elementor flush-css"
   ```

4. Scrub anything you do not need locally, at minimum resetting user
   passwords and dropping any stored payment or contact submissions.

5. Re-assert the local safety settings the import overwrote:

   ```bash
   make wp CMD="option update blog_public 0"
   ```

To go back to a clean slate: `make destroy && make up`.

## Uploads

Media lives in the `wp_core` volume, not in git. If a page you are debugging
needs real images, copy just those files in:

```bash
docker compose cp ./some-image.jpg wordpress:/var/www/html/wp-content/uploads/2026/09/
```

Copying the entire production uploads directory is rarely worth the disk space.
