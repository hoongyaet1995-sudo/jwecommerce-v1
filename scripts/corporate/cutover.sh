#!/bin/sh
set -eu
app=/home/u762348670/apps/jw-corporate
domain=/home/u762348670/domains/jwaistudio.online
expected=${1:?Expected approved Git SHA required}
test "$(git -C "$app" rev-parse HEAD)" = "$expected"
test "$(realpath "$domain/public_html")" = "$domain/public_html"
test -f "$app/public/build-admin/manifest.json"
test -f "$app/docs/content/jw-homepage.json"
stamp=$(date +%Y%m%d-%H%M%S)
backup=/home/u762348670/backups/jw-full-cutover-$stamp
next=$domain/jw-public-next-$stamp
mkdir -m 700 "$backup"
mkdir -m 755 "$next"
cp "$app/.env" "$backup/corporate.env"
# Publish only public static files. The application, .env and database stay private.
for dir in build build-admin css js images fonts marketing-studio; do
  if test -d "$app/public/$dir"; then cp -R "$app/public/$dir" "$next/$dir"; fi
done
cp "$app/public/.htaccess" "$next/.htaccess"
cp "$app/scripts/corporate/website-index.php" "$next/index.php"
for file in favicon.ico robots.txt; do
  if test -f "$app/public/$file"; then cp "$app/public/$file" "$next/$file"; fi
done
find "$next" -type d -exec chmod 755 {} \;
find "$next" -type f -exec chmod 644 {} \;
# Exact legal aliases point to editable CMS pages. No old application is called.
sed -i '/RewriteEngine On/a\    RewriteRule ^privacy/?$ /pages/privacy-policy [R=302,L]\n    RewriteRule ^privacy-policy/?$ /pages/privacy-policy [R=302,L]\n    RewriteRule ^terms/?$ /pages/terms-and-conditions [R=302,L]\n    RewriteRule ^terms-and-conditions/?$ /pages/terms-and-conditions [R=302,L]' "$next/.htaccess"
touch "$next/.jw-corporate-site"
# ASSET_URL is intentionally cleared: this instance now owns the whole public root.
sed -i 's|^ASSET_URL=.*|ASSET_URL=|' "$app/.env"
cd "$app"
/usr/bin/php artisan optimize:clear
# Keep an offline rollback copy only; the old project is no longer web accessible.
mv "$domain/public_html" "$backup/retired-public_html"
mv "$next" "$domain/public_html"
printf 'Corporate website active. Offline backup: %s\n' "$backup"
git rev-parse HEAD
