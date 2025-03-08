# wartungsliste


# Installationsschritte:

## Webserver
Projekt von GitHub herunterladen und in einen Pfad für einen Webserver legen (in diesem Fall Windows IIS)
für dieses Projekt wird PHP benötigt, mit folgenden Modulen: **pdo_mysql**, **mysqli**

## Python
Für das Ausführen der Skripts, muss auch Python installiert werden.
Bitte beachte beim Installationsassistenten unter Windows, dass du den Haken bei "PATH" setzt!

## MySQL Datenbank
Richte eine MySQL Datenbank ein. 
Hier sollte ein extra Benutzer z.B. **wartungsliste_user** und eine Datenbank **wartungsliste** erstellt werden und die Rechte richtig vergeben werden.
(siehe dir hierfür die install.sh aus dem install-Verzeichnis an!)
Für die Einrichtung der eigentlichen Datenbank-Tabellen, sieh die die **database.sql** im install-Verzeichnis an.

## Konfigurationen anpassen
Die Zugangsdaten der Datenbank müssen noch in 2 Dateien hinterlegt werden (1x für .py-Skripte und 1x für .php-Skripte)
im root-Verzeichnis in der **config.php** und in der **/processing/config/database.py**
im **/processing/config**-Verzeichnis muss auch noch die **mail-py** für den Empfang und Versand von Mails konfiguriert werden.
im **/processing/mail-report/mail-report.py** muss relativ weit unten noch die Empfängeradressen geändert werden

## Aufgabenplanung / Cron-Jobs
nahezu alle .py-Skripte müssen über eine Aufgabenplanung oder ähnliches für ein automatisches Ausführen konfiguriert werden.
| Skript | Dauer | Beschreibung |
| --- | --- | --- |
| processing/**mail-report.py** | immer am letzten Tag des Monats | schickt eine Übersicht über die Kunden und ob das Kontigent "gereicht" hat |
| processing/**set-customer_contingent_status.py** | z.B. alle 2 Minuten | Berechnet die verbrauchten Minuten eines Kunden im aktuellen Monat/Quartal |

Für die Skripts wird Python benutzt. Dieses solltest du schon installiert haben.
Python (python.exe) sollte unter C:\Users\Administrator.PHD\AppData\Local\Programs\Python\Python313 zu finden sein. (beachte, dass 313 die Version ist und bei dir anders sein kann)

Für die automatische Erstellung der "Aufgaben" unter Windows, können die .ps1 (PowerShell-Skripts) unter **/install/processing-tasks** genutzt werden.
Ändere in diesen Skripts bitte einen Pfad zu den Python-Skripts, falls dieser anders ist und den Pfad zu den .py-Skripten, falls dieser bei dir anders ist!
!! Achtung !! das send-mail-report-task.ps1-Skript erstellt die Aufgabe für eine tägliche Ausführung. Das muss noch manuell geändert werden (siehe für genauere Infos in der send-mail-report-task.ps1)



## Datenbankstruktur
![napkin-selection](https://github.com/user-attachments/assets/d2760339-87aa-4b3f-bb7f-85397d8f0f40)
