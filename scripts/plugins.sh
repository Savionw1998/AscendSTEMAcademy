#!/usr/bin/env bash
# Installs the third-party plugins needed to render ascendstemacademy.com
# locally. Idempotent. Invoked by `make plugins`.
#
# Versions are pinned to what production runs so local reproduces production
# behaviour. Plugins that only phone home to external services (caching, CDN,
# security scanning, analytics, backups, payments) are deliberately skipped —
# see SKIPPED below.
set -euo pipefail

wp() { command wp --path=/var/www/html --allow-root "$@"; }

# Page building — the site is Elementor-based, so these are required for any
# page to render correctly.
BUILDER=(
  "elementor:4.2.4"
  "essential-addons-for-elementor-lite:6.8.3"
  "unlimited-elements-for-elementor:2.0.18"
  "embedpress:4.6.5"
)

# Membership, commerce and community features the site depends on.
FEATURES=(
  "ultimate-member:2.13.0"
  "woocommerce:11.1.0"
  "bbpress:2.6.15"
  "woo-cart-drawer:2.8.1"
  "code-snippets:3.10.2"
  "redirection:5.10.0"
  "wpfront-notification-bar:3.5.1"
  "gtranslate:3.1.2"
)

install_group() {
  local label="$1"; shift
  echo "==> ${label}"
  for entry in "$@"; do
    local slug="${entry%%:*}"
    local version="${entry##*:}"
    if wp plugin is-installed "$slug" 2>/dev/null; then
      echo "    already installed: $slug"
      continue
    fi
    echo "    installing $slug ($version)..."
    # A pinned version can 404 if it has aged out of the .org archive; fall
    # back to the current release rather than failing the whole run.
    if ! wp plugin install "$slug" --version="$version" --activate 2>/dev/null; then
      echo "    !! version $version unavailable for $slug — installing latest"
      wp plugin install "$slug" --activate || echo "    !! FAILED: $slug (install manually)"
    fi
  done
}

install_group "Page builder" "${BUILDER[@]}"
install_group "Site features" "${FEATURES[@]}"

echo
echo "==> Skipped on purpose (need external accounts or fight local dev):"
cat <<'SKIPPED'
    W3 Total Cache, Cloudflare     caching/CDN — hide your changes locally
    Wordfence, Akismet             security/spam scanning — need API keys
    Jetpack, Site Kit by Google    need a connected WordPress.com/Google account
    UpdraftPlus                    backups — nothing to back up locally
    WooPayments, WC Shipping/Tax   need real merchant accounts
    Royal MCP                      connects the live site to AI assistants
    Image Optimization, ThinkRank  external optimisation/SEO services
    Advanced Database Cleaner      destructive; not for a scratch database
SKIPPED
echo
echo "    Install any of them yourself with:  make wp CMD=\"plugin install <slug> --activate\""
echo
echo "==> First-party Ascend plugins are NOT installed by this script."
echo "    They are your own code — see wp-content/plugins/README.md."
echo
wp plugin list --status=active --field=name 2>/dev/null | sed 's/^/    active: /' || true
