#!/usr/bin/env bash
# Builds a throwaway local site for the browser tests: WordPress 7.1.2 on SQLite, Ultimate Member
# 2.13.1, this plugin, a stand-in for Astra's header, and the live pages, login form and header CSS
# the app work touches (see fixture.php). Then serves it on http://localhost:8080.
#
#   ascend-app-sessions/tests/browser/setup-site.sh
#
# Needs git, PHP 8.x with pdo_sqlite. Idempotent: re-running reuses what is already there.
set -euo pipefail

HERE=$(cd "$(dirname "$0")" && pwd)
PLUGIN=$(cd "$HERE/../.." && pwd)
SRC=${ASA_SRC:-$HERE/.src}
SITE=${ASA_SITE:-$HERE/.site}
PORT=${ASA_PORT:-8080}

clone() { # repo tag dir
	[ -d "$3" ] || git -c advice.detachedHead=false clone -q --depth 1 --branch "$2" "https://github.com/$1" "$3"
}
mkdir -p "$SRC"
clone WordPress/WordPress 7.1.2 "$SRC/wp"
clone ultimatemember/ultimatemember 2.13.1 "$SRC/um"
clone WordPress/sqlite-database-integration v3.0.2 "$SRC/sqlite"

if [ ! -f "$SITE/wp-load.php" ]; then
	mkdir -p "$SITE"
	cp -r "$SRC/wp/." "$SITE/"
	rm -rf "$SITE/.git"
	# The SQLite plugin package links its engine in from a sibling package; copy it in for real.
	cp -r "$SRC/sqlite/packages/plugin-sqlite-database-integration" "$SITE/wp-content/plugins/sqlite-database-integration"
	rm "$SITE/wp-content/plugins/sqlite-database-integration/wp-includes/database"
	cp -r "$SRC/sqlite/packages/mysql-on-sqlite/src" "$SITE/wp-content/plugins/sqlite-database-integration/wp-includes/database"
	sed "s#{SQLITE_IMPLEMENTATION_FOLDER_PATH}#$SITE/wp-content/plugins/sqlite-database-integration#" \
		"$SITE/wp-content/plugins/sqlite-database-integration/db.copy" > "$SITE/wp-content/db.php"
	cp -r "$SRC/um" "$SITE/wp-content/plugins/ultimate-member"
	rm -rf "$SITE/wp-content/plugins/ultimate-member/.git"
fi

ln -sfn "$PLUGIN" "$SITE/wp-content/plugins/ascend-app-sessions"
mkdir -p "$SITE/wp-content/themes/astra" "$SITE/wp-content/mu-plugins" "$SITE/wp-content/database"
cp "$HERE"/mock-astra/* "$SITE/wp-content/themes/astra/"
cp "$HERE"/mu-plugins/* "$SITE/wp-content/mu-plugins/"
cp "$HERE/router.php" "$HERE/fixture.php" "$HERE/sessions.php" "$HERE/restrict.php" "$HERE/live-header-offsets.css" "$SITE/"

cat > "$SITE/wp-config.php" <<EOF
<?php
define( 'DB_NAME', 'wp' ); define( 'DB_USER', '' ); define( 'DB_PASSWORD', '' ); define( 'DB_HOST', '' );
define( 'DB_CHARSET', 'utf8mb4' ); define( 'DB_COLLATE', '' );
define( 'DB_DIR', __DIR__ . '/wp-content/database/' ); define( 'DB_FILE', 'wp.sqlite' );
foreach ( array( 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT' ) as \$k ) { define( \$k, 'local-test-' . \$k ); }
\$table_prefix = 'wp_';
define( 'WP_DEBUG', true ); define( 'WP_DEBUG_LOG', __DIR__ . '/wp-content/debug.log' ); define( 'WP_DEBUG_DISPLAY', false );
define( 'WP_HOME', 'http://localhost:$PORT' ); define( 'WP_SITEURL', 'http://localhost:$PORT' );
define( 'DISABLE_WP_CRON', true ); define( 'AUTOMATIC_UPDATER_DISABLED', true ); define( 'WP_HTTP_BLOCK_EXTERNAL', true );
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ . '/' ); }
require_once ABSPATH . 'wp-settings.php';
EOF

( cd "$SITE" && ASA_PORT=$PORT php fixture.php install && ASA_PORT=$PORT php fixture.php )

if ! curl -s -o /dev/null "http://localhost:$PORT/"; then
	nohup php -S "localhost:$PORT" -t "$SITE" "$SITE/router.php" > "$SITE/server.log" 2>&1 < /dev/null &
	sleep 1
fi
echo "Local site: http://localhost:$PORT  (admin / admin-pass-123, family1 / teacher1 / editor1 : Test-pass-12345)"
