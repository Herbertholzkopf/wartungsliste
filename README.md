## automatisierte Installation unter Ubuntu
bitte stelle sicher, dass das Skript mit Adminrechten (root-Rechte) ausgeführt wird. Entweder mit dem Befehl `sudo` vor den eigentlichen Befehlen oder als root Benutzer: `sudo -i`
```
wget https://raw.githubusercontent.com/PHD-IT-Systeme/wartungsliste/refs/heads/main/install/install.sh
chmod +x install.sh
sudo ./install.sh
```


## wichtige Schritte nach der Installation
Konfigurationsdateien bearbeiten:
das kann mit dem Editor nano folgendermaßen gemacht werden:
`nano /var/www/wartungsliste/config.php` (Konfiguration für den Zugriff auf die Datenbank über Website)
`nano /var/www/wartungsliste/report/database_config.ini` (Konfiguration für den Zugriff auf die Datenbank für die Reports [siehe weiter unten])
`nano /var/www/wartungsliste/report/mail_config.ini` (Konfiguration der Zugangsdaten des Mailanbieters)
```


## Report-Funktion
Unter `/var/www/wartungsliste/report` liegen Python-Skripte, die für das Quartal und Monat einen Bericht erstellen können über das verbrauchte Kontingent.
Zusätzlich gibt es auch ein Skript zum Verschicken der Berichte als Mail.

Für beide Skripte muss in der jeweiligen .ini Datei die Benutzer und Passwörter usw. abgeändert werden.
Diese Skripts können dann z.B. als CronJob automatisch am Ende des Monats und Quartals laufen.


## Datenbankstruktur
![napkin-selection](https://github.com/user-attachments/assets/d2760339-87aa-4b3f-bb7f-85397d8f0f40)