import os
import smtplib
import configparser
from email.mime.multipart import MIMEMultipart
from email.mime.text import MIMEText
from email.mime.application import MIMEApplication
from datetime import datetime

def load_config(config_file='mail_config.ini'):
    """Lädt die Mail-Konfiguration aus der ini-Datei."""
    config = configparser.ConfigParser(interpolation=None)  # Hier wird die Interpolation deaktiviert
    config.read(config_file)
    
    # Empfängerliste aufteilen und Leerzeichen entfernen
    recipients = [email.strip() for email in config['EMAIL']['recipient'].split(';')]
    
    return {
        'smtp_server': config['EMAIL']['smtp_server'],
        'smtp_port': config['EMAIL']['smtp_port'],
        'username': config['EMAIL']['username'],
        'password': config['EMAIL']['password'],
        'sender': config['EMAIL']['sender'],
        'recipients': recipients
    }

def send_html_reports(folder_path='.'):
    """Versendet alle HTML-Reports aus dem angegebenen Ordner."""
    # Konfiguration laden
    config = load_config()
    
    # HTML-Dateien im Ordner finden
    html_files = [f for f in os.listdir(folder_path) 
                 if f.endswith('.html') and 'wartungsvertraege' in f]
    
    if not html_files:
        print("Keine HTML-Reports gefunden.")
        return
    
    # E-Mail vorbereiten
    msg = MIMEMultipart()
    msg['From'] = config['sender']
    msg['To'] = ', '.join(config['recipients'])  # Empfänger mit Komma getrennt
    msg['Subject'] = "Wartungsverträge Report"
    
    # Haupttext der E-Mail
    body = f"Wartungsverträge Reports vom {datetime.now().strftime('%d.%m.%Y')}\n\n"
    body += "Folgende Reports sind angehängt:\n"
    body += "\n".join([f"- {f}" for f in html_files])
    msg.attach(MIMEText(body, 'plain'))
    
    # Anhänge hinzufügen
    for html_file in html_files:
        file_path = os.path.join(folder_path, html_file)
        with open(file_path, 'r', encoding='utf-8') as f:
            attachment = MIMEText(f.read(), 'html')
            attachment.add_header('Content-Disposition', 'attachment', 
                                filename=html_file)
            msg.attach(attachment)
    
    try:
        # Verbindung zum SMTP-Server aufbauen
        server = smtplib.SMTP(config['smtp_server'], int(config['smtp_port']))
        server.starttls()  # TLS-Verschlüsselung aktivieren
        server.login(config['username'], config['password'])
        
        # E-Mail an alle Empfänger senden
        server.send_message(msg)
        server.quit()
        
        print(f"E-Mail erfolgreich versendet an {len(config['recipients'])} Empfänger mit {len(html_files)} Anhängen.")
        
        # Nach erfolgreichem Versand Dateien löschen
        for html_file in html_files:
            file_path = os.path.join(folder_path, html_file)
            try:
                os.remove(file_path)
                print(f"Datei {html_file} wurde gelöscht.")
            except Exception as e:
                print(f"Fehler beim Löschen von {html_file}: {str(e)}")
                
    except Exception as e:
        print(f"Fehler beim Senden der E-Mail: {str(e)}")
        print("Dateien wurden nicht gelöscht, da die E-Mail nicht versendet wurde.")

if __name__ == "__main__":
    send_html_reports()