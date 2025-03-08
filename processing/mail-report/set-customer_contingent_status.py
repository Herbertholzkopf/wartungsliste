# Berechnet die verbrauchten Minuten eines Kunden im aktuellen Monat bzw. Quartal und setzt den Status des Kundenkontingents
# Status 'ok': Verbrauch unter 75% des Kontingents
# Status 'warning': Verbrauch über 75% des Kontingents
# Status 'danger': Verbrauch über 100% des Kontingents

import pymysql
import os
import sys
from datetime import datetime

# Datenbankverbindung
sys.path.append(os.path.join(os.path.dirname(__file__), '../config'))
import database

def connect_to_database():
    try:
        connection = pymysql.connect(
            host=database.DB_HOST,
            user=database.DB_USER,
            password=database.DB_PASSWORD,
            database=database.DB_NAME,
            charset='utf8mb4',
            cursorclass=pymysql.cursors.DictCursor
        )
        return connection
    except Exception as e:
        print(f"Fehler beim Verbinden mit der Datenbank: {e}")
        sys.exit(1)

def get_current_quarter_months(current_month):
    """Bestimmt die Monate des aktuellen Quartals basierend auf dem aktuellen Monat"""
    if 1 <= current_month <= 3:
        return [1, 2, 3]  # Q1
    elif 4 <= current_month <= 6:
        return [4, 5, 6]  # Q2
    elif 7 <= current_month <= 9:
        return [7, 8, 9]  # Q3
    else:
        return [10, 11, 12]  # Q4

def update_customer_contingent_status():
    # Aktuelle Zeit für Berechnung
    current_date = datetime.now()
    current_month = current_date.month
    current_year = current_date.year
    current_quarter_months = get_current_quarter_months(current_month)
    
    try:
        # Verbindung zur Datenbank herstellen
        connection = connect_to_database()
        cursor = connection.cursor()
        
        # 1. Kunden mit monatlichem Abrechnungszeitraum verarbeiten
        process_monthly_customers(cursor, connection, current_month, current_year)
        
        # 2. Kunden mit quartalsweisem Abrechnungszeitraum verarbeiten
        process_quarterly_customers(cursor, connection, current_quarter_months, current_year)
        
        cursor.close()
        connection.close()
        print("Aktualisierung der Kundenkontingent-Status abgeschlossen.")
        
    except Exception as e:
        print(f"Fehler bei der Verarbeitung: {e}")

def process_monthly_customers(cursor, connection, current_month, current_year):
    """Verarbeitet Kunden mit monatlichem Abrechnungszeitraum"""
    cursor.execute("""
        SELECT id, customer_number, name, contingent_hours, contingent_minutes 
        FROM customers
        WHERE calculation_time_span = 'monthly'
    """)
    
    monthly_customers = cursor.fetchall()
    print(f"Verarbeite {len(monthly_customers)} Kunden mit monatlichem Abrechnungszeitraum...")
    
    for customer in monthly_customers:
        customer_id = customer['id']
        
        # Kontingent in Minuten umrechnen
        total_contingent_minutes = (customer['contingent_hours'] * 60) + customer['contingent_minutes']
        
        # Verbrauchte Minuten im aktuellen Monat summieren
        cursor.execute("""
            SELECT SUM(duration_minutes) as total_used_minutes
            FROM work_entries
            WHERE customer_id = %s
            AND MONTH(datetime) = %s
            AND YEAR(datetime) = %s
        """, (customer_id, current_month, current_year))
        
        result = cursor.fetchone()
        total_used_minutes = result['total_used_minutes'] if result['total_used_minutes'] else 0
        
        # Status aktualisieren
        update_customer_status(cursor, connection, customer, customer_id, total_used_minutes, total_contingent_minutes)

def process_quarterly_customers(cursor, connection, quarter_months, current_year):
    """Verarbeitet Kunden mit quartalsweisem Abrechnungszeitraum"""
    cursor.execute("""
        SELECT id, customer_number, name, contingent_hours, contingent_minutes 
        FROM customers
        WHERE calculation_time_span = 'quarterly'
    """)
    
    quarterly_customers = cursor.fetchall()
    print(f"Verarbeite {len(quarterly_customers)} Kunden mit quartalsweisem Abrechnungszeitraum...")
    
    for customer in quarterly_customers:
        customer_id = customer['id']
        
        # Kontingent in Minuten umrechnen
        total_contingent_minutes = (customer['contingent_hours'] * 60) + customer['contingent_minutes']
        
        # Verbrauchte Minuten im aktuellen Quartal summieren
        # Da wir mehrere Monate haben, müssen wir eine IN-Klausel verwenden
        months_str = ','.join(map(str, quarter_months))
        cursor.execute(f"""
            SELECT SUM(duration_minutes) as total_used_minutes
            FROM work_entries
            WHERE customer_id = %s
            AND MONTH(datetime) IN ({months_str})
            AND YEAR(datetime) = %s
        """, (customer_id, current_year))
        
        result = cursor.fetchone()
        total_used_minutes = result['total_used_minutes'] if result['total_used_minutes'] else 0
        
        # Status aktualisieren
        update_customer_status(cursor, connection, customer, customer_id, total_used_minutes, total_contingent_minutes)

def update_customer_status(cursor, connection, customer, customer_id, total_used_minutes, total_contingent_minutes):
    """Aktualisiert den Status eines Kunden basierend auf den verbrauchten Minuten"""
    # Status berechnen
    if total_contingent_minutes > 0:
        usage_percentage = (total_used_minutes / total_contingent_minutes) * 100
        if usage_percentage > 100:
            status = 'danger'
        elif usage_percentage > 75:
            status = 'warning'
        else:
            status = 'ok'
    else:
        # Falls kein Kontingent definiert ist
        status = 'danger'
    
    # Prüfen, ob bereits ein Eintrag für diesen Kunden existiert
    cursor.execute("""
        SELECT id FROM customer_contingent_status
        WHERE customer_id = %s
    """, (customer_id,))
    
    existing_entry = cursor.fetchone()
    
    if existing_entry:
        # Bestehenden Eintrag aktualisieren
        cursor.execute("""
            UPDATE customer_contingent_status
            SET used_minutes = %s, status = %s
            WHERE customer_id = %s
        """, (total_used_minutes, status, customer_id))
    else:
        # Neuen Eintrag erstellen
        cursor.execute("""
            INSERT INTO customer_contingent_status (customer_id, used_minutes, status)
            VALUES (%s, %s, %s)
        """, (customer_id, total_used_minutes, status))
    
    connection.commit()
    
    print(f"Kunde {customer['name']} (ID: {customer_id}): {total_used_minutes} von {total_contingent_minutes} Minuten verbraucht, Status: {status}")

if __name__ == "__main__":
    update_customer_contingent_status()