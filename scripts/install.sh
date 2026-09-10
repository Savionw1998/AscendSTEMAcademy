#!/usr/bin/env bash
# Provisions the local WordPress install to match ascendstemacademy.com.
# Idempotent: safe to re-run. Invoked by `make install`.
set -euo pipefail

WP_URL="${WP_URL:-http://localhost:8080}"
WP_SITE_TITLE="${WP_SITE_TITLE:-Ascend STEM Academy (Local)}"
WP_ADMIN_USER="${WP_ADMIN_USER:-admin}"
WP_ADMIN_PASSWORD="${WP_ADMIN_PASSWORD:-admin}"
WP_ADMIN_EMAIL="${WP_ADMIN_EMAIL:-dev@ascendstemacademy.test}"

# Runs as root inside the container so it can write to the bind-mounted
# code directories; --allow-root keeps wp-cli from refusing.
wp() { command wp --path=/var/www/html --allow-root "$@"; }

# wp-cli reads the database credentials out of wp-config.php, which the web
# container generates on first boot. Wait for that, not just for core files.
echo "==> Waiting for WordPress core and wp-config.php..."
for _ in $(seq 1 60); do
  [ -f /var/www/html/wp-settings.php ] && [ -f /var/www/html/wp-config.php ] && break
  sleep 2
done
if [ ! -f /var/www/html/wp-config.php ]; then
  echo "!! wp-config.php never appeared. Is the 'wordpress' container running?" >&2
  echo "   Try: docker compose up -d wordpress && docker compose logs wordpress" >&2
  exit 1
fi

if wp core is-installed 2>/dev/null; then
  echo "==> WordPress is already installed; skipping core install."
else
  echo "==> Installing WordPress core..."
  wp core install \
    --url="$WP_URL" \
    --title="$WP_SITE_TITLE" \
    --admin_user="$WP_ADMIN_USER" \
    --admin_password="$WP_ADMIN_PASSWORD" \
    --admin_email="$WP_ADMIN_EMAIL" \
    --skip-email
fi

# Production uses /%postname%/ (confirmed via the site's MCP connection).
echo "==> Setting permalink structure to /%postname%/"
wp rewrite structure '/%postname%/' --hard
wp option update timezone_string 'America/New_York'
wp option update blogdescription 'Ascend STEM'

# Production runs Astra 4.13.11 (confirmed from the parent theme's
# functions.php). Pin it so local renders identically.
ASTRA_VERSION="${ASTRA_VERSION:-4.13.11}"

echo "==> Ensuring the Astra parent theme ($ASTRA_VERSION) is present..."
if ! wp theme is-installed astra; then
  if ! wp theme install astra --version="$ASTRA_VERSION"; then
    echo "!! Astra $ASTRA_VERSION unavailable; falling back to the latest release." >&2
    wp theme install astra
  fi
fi

echo "==> Activating the Astra Child theme..."
if wp theme is-installed astra-child; then
  wp theme activate astra-child
else
  echo "!! astra-child not found. Is wp-content/themes/astra-child mounted?" >&2
  exit 1
fi

# Activate any first-party Ascend plugins that have been added to the repo.
# They are the site's actual application code; without them the child theme's
# styles have nothing to attach to.
echo "==> Activating first-party Ascend plugins..."
ascend_found=0
for plugin_dir in /var/www/html/wp-content/plugins/ascend-*/; do
  [ -d "$plugin_dir" ] || continue
  slug="$(basename "$plugin_dir")"
  ascend_found=1
  if wp plugin activate "$slug" 2>/dev/null; then
    echo "    activated: $slug"
  else
    echo "    !! could not activate $slug (check its main plugin file)"
  fi
done
if [ "$ascend_found" -eq 0 ]; then
  echo "    none found — see wp-content/plugins/README.md for the seven"
  echo "    first-party plugins production runs and how to add them."
fi

# Keep the local site out of search results and off the real internet's radar.
wp option update blog_public 0

echo
echo "=========================================================="
echo " Local site ready:  $WP_URL"
echo " Admin:             $WP_URL/wp-admin"
echo " Username:          $WP_ADMIN_USER"
echo " Password:          $WP_ADMIN_PASSWORD"
echo "=========================================================="
