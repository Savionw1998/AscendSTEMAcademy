# Bringing local closer to production

The default `make up` gives you a clean WordPress install with the Astra
parent and our `astra-child` theme. That is enough for most theme work.

Production runs **39 active plugins** and real content, so some bugs only
appear with more of that context. This document covers how to add it.

## Seeing what production actually runs

The repository is connected to the live site through its WordPress MCP
connection, so you can inspect production without shell access. Useful
starting points: the active plugin list, site status (PHP/MySQL versions,
memory limits), and the active theme.

Record any findings that matter here, so the next person does not have to
re-derive them.

## Installing the plugins production uses

Free plugins from the wordpress.org directory install directly:

```bash
make wp CMD="plugin install astra-sites contact-form-7 --activate"
```

Commercial plugins (Astra Pro, LMS or SIS add-ons, form add-ons) are **not**
on wordpress.org and cannot be fetched this way. Install them by dropping the
zip into the running container:

```bash
docker compose cp ~/Downloads/astra-addon.zip wordpress:/tmp/addon.zip
make wp CMD="plugin install /tmp/addon.zip --activate"
```

Installed plugins live in the Docker volume, not in git, so they survive
`make down` but are removed by `make destroy`.

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

3. Rewrite the URLs so links and assets resolve locally:

   ```bash
   make wp CMD="search-replace 'https://ascendstemacademy.com' 'http://localhost:8080' --all-tables --dry-run"
   # review the counts, then run it for real without --dry-run
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
