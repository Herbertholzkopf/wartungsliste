#!/bin/bash
# install.sh

# Farben für Ausgaben
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo "Wartungsliste Installations Skript"
echo "================================"

# Root-Rechte prüfen
if [ "$EUID" -ne 0 ]; then 
    echo -e "${RED}Bitte als root ausführen${NC}"
    exit 1
fi

# Systemaktualisierung
echo -e "${YELLOW}Systemkomponenten werden aktualisiert...${NC}"
apt-get update
apt-get upgrade -y

# Installation von Python & Python-Paketen
echo -e "${YELLOW}Web- & Datenbankserver werden installiert...${NC}"
apt-get install -y nginx mysql-server php-fpm php-mysql

# Installation von weiteren Paketen
echo -e "${YELLOW}git & unzip werden installiert...${NC}"
apt-get install -y git unzip


# Konfiguration von MySQL
"${GREEN}Konfiguration der Datenbank wird gestartet...${NC}"
echo -e "${YELLOW}MySQL Root Passwort setzen...${NC}"
read -s -p "Gewünschtes MySQL Root Passwort: " mysqlpass
echo ""

mysql --user=root <<EOF
ALTER USER 'root'@'localhost' IDENTIFIED WITH auth_socket;
DELETE FROM mysql.user WHERE User='';
DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');
DROP DATABASE IF EXISTS test;
DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';
FLUSH PRIVILEGES;
EOF

sudo mysql <<EOF
ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '${mysqlpass}';
FLUSH PRIVILEGES;
EOF

if ! mysql --user=root --password="${mysqlpass}" -e "SELECT 1;" >/dev/null 2>&1; then
    echo -e "${RED}Fehler beim Setzen des MySQL Root-Passworts.${NC}"
    exit 1
fi

echo -e "${GREEN}MySQL Root-Passwort erfolgreich gesetzt.${NC}"




# Einrichtung der MySQL Datenbank
echo -e "${YELLOW}Erstelle Datenbank "wartungsliste" und Benutzer "wartungsliste_user"...${NC}"
read -s -p "Wartungsliste Datenbank-Benutzer Passwort: " dbpass
echo ""

if ! mysql --user=root --password="${mysqlpass}" <<EOF
CREATE DATABASE wartungsliste DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'wartungsliste_user'@'localhost' IDENTIFIED BY '${dbpass}';
GRANT ALL PRIVILEGES ON wartungsliste.* TO 'wartungsliste_user'@'localhost';
FLUSH PRIVILEGES;
EOF
then
    echo -e "${RED}Fehler beim Erstellen der Datenbank und des Benutzers.${NC}"
    exit 1
fi


# Verzeichnis für das Projekt erstellen
echo -e "${YELLOW}Erstelle Projekt-Verzeichnis...${NC}"
mkdir -p /var/www/wartungsliste

# Projekt von GitHub klonen
echo -e "${YELLOW}Klone Git Repository...${NC}"
if git clone https://github.com/PHD-IT-Systeme/wartungsliste.git /var/www/wartungsliste; then
    echo -e "${GREEN}Repository erfolgreich geklont${NC}"
else
    echo -e "${RED}Fehler beim Klonen des Repositories${NC}"
    exit 1
fi


# Datenbank konfigurieren
echo -e "${YELLOW}Erstelle Tabellen und Test-Daten...${NC}"
mysql --user=wartungsliste_user --password="${dbpass}" < /var/www/wartungsliste/install/database.sql

# Rechte des Verzeichnis anpassen
chown -R www-data:www-data /var/www/wartungsliste
chmod -R 755 /var/www/wartungsliste
chown -R www-data:adm /var/www/wartungsliste/log
chmod 750 /var/www/wartungsliste/log
chmod 640 /var/www/wartungsliste/log/*

# Nginx Konfiguration erstellen
echo -e "${YELLOW}Konfiguriere Nginx...${NC}"
cat > /etc/nginx/sites-available/wartungsliste <<'EOF'
server {
    listen 80;
    server_name _;
    root /var/www/wartungsliste;
    index index.php index.html;

    # Hauptlocation
    location / {
        try_files $uri $uri/ /index.php?$args;
    }

    # PHP-Verarbeitung
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php-fpm.sock;
        fastcgi_intercept_errors on;
    }

    # Verhindern des Zugriffs auf versteckte Dateien
    location ~ /\.(ht|git|py|env|config) {
        deny all;
        return 404;
    }

    # Logging
    error_log /var/www/wartungsliste/log/error.log;
    access_log /var/www/wartungsliste/log/access.log;
}
EOF

# Nginx Site verknüpfen und aktivieren
ln -s /etc/nginx/sites-available/wartungsliste /etc/nginx/sites-enabled/
rm /etc/nginx/sites-enabled/default
nginx -t && systemctl restart nginx

# Dienste neu starten
echo -e "${YELLOW}Dienste werden neu gestartet...${NC}"
systemctl restart nginx

echo -e "${GREEN}Installation abgeschlossen!${NC}"
echo -e "${RED}Korrigiere das gerade eingegebene Passwort des Datenbank-Benutzers mit dem Befehl: nano /var/www/wartungsliste/config.php${NC}"