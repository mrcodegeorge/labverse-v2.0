#!/usr/bin/env bash
# ==============================================================================
# LABVERSE v2.0 Enterprise LIMS — 1-Command Ubuntu / Debian Server Installer
# Usage: sudo bash install-server.sh
# ==============================================================================
set -e

echo "=============================================================================="
echo "  LABVERSE v2.0 — Automated Linux Server Installer (Ubuntu / Debian)"
echo "=============================================================================="

if [ "$EUID" -ne 0 ]; then
  echo "Please run this installer with sudo: sudo bash install-server.sh"
  exit 1
fi

APP_DIR=$(pwd)
SERVER_IP=$(hostname -I | awk '{print $1}')

echo "[1/6] Updating system packages and installing PHP, Composer, SQLite, MySQL & Nginx..."
apt-get update -y
apt-get install -y software-properties-common curl git unzip nginx sqlite3 \
    php-fpm php-cli php-common php-mysql php-sqlite3 php-mbstring php-xml php-curl php-zip php-bcmath php-intl

if ! command -v composer &> /dev/null; then
    echo "[2/6] Installing Composer package manager..."
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

echo "[3/6] Installing LABVERSE application dependencies..."
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction

echo "[4/6] Preparing environment & database permissions..."
if [ ! -f "$APP_DIR/.env" ]; then
    cp "$APP_DIR/.env.example" "$APP_DIR/.env"
    php artisan key:generate --force
fi

touch "$APP_DIR/database/database.sqlite"
chown -R www-data:www-data "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" "$APP_DIR/database" "$APP_DIR/.env"
chmod -R 775 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" "$APP_DIR/database"

PHP_SOCK=$(find /run/php/ -name "php*-fpm.sock" | head -n 1)
if [ -z "$PHP_SOCK" ]; then
    PHP_SOCK="/run/php/php-fpm.sock"
fi

echo "[5/6] Configuring Nginx web server..."
cat > /etc/nginx/sites-available/labverse <<EOF
server {
    listen 80 default_server;
    server_name _;
    root $APP_DIR/public;

    index index.php index.html;
    charset utf-8;
    client_max_body_size 50M;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:$PHP_SOCK;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF

ln -sf /etc/nginx/sites-available/labverse /etc/nginx/sites-enabled/labverse
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl restart nginx

echo "=============================================================================="
echo "  SUCCESS! LABVERSE v2.0 Server Components Installed!"
echo "  Open your web browser and visit the Visual Setup Wizard to finish setup:"
echo ""
echo "      http://$SERVER_IP/install"
echo "=============================================================================="

