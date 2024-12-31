import mysql.connector
from datetime import datetime
import os
import math
import configparser
from pathlib import Path

def connect_to_db():
    """Verbindung zur Datenbank herstellen"""
    config = configparser.ConfigParser(interpolation=None)  # Hier wird die Interpolation deaktiviert
    config_path = Path(__file__).parent / 'database_config.ini'
    config.read(config_path)
    
    return mysql.connector.connect(
        host=config['database']['host'],
        user=config['database']['user'],
        password=config['database']['password'],
        database=config['database']['database'],
        ssl_disabled=config['database'].getboolean('ssl_disabled')
    )

def get_quarter_range(month):
    """Ermittelt Start- und Endmonat des Quartals"""
    quarter = math.ceil(month / 3)
    start_month = (quarter - 1) * 3 + 1
    end_month = quarter * 3
    return start_month, end_month

def calculate_used_contingent(cursor, customer_id, current_month, current_year):
    """Berechnet das verbrauchte Kontingent eines Kunden im aktuellen Quartal"""
    start_month, end_month = get_quarter_range(current_month)
    
    query = """
        SELECT COALESCE(SUM(duration_minutes), 0) as total_minutes
        FROM work_entries 
        WHERE customer_id = %s 
        AND MONTH(datetime) BETWEEN %s AND %s
        AND YEAR(datetime) = %s
    """
    cursor.execute(query, (customer_id, start_month, end_month, current_year))
    result = cursor.fetchone()
    return result['total_minutes'] if result else 0

def get_quarter_name(month):
    """Gibt den Namen des Quartals zurück"""
    quarter = math.ceil(month / 3)
    return f"{quarter}. Quartal"

def generate_html_report():
    """Generiert den HTML-Report"""
    current_date = datetime.now()
    current_month = current_date.month
    current_year = current_date.year
    current_quarter = get_quarter_name(current_month)
    
    conn = connect_to_db()
    cursor = conn.cursor(dictionary=True)
    
    # Hole alle Quartalskunden
    cursor.execute("""
        SELECT * FROM customers 
        WHERE calculation_time_span = 'quarterly'
        ORDER BY name
    """)
    customers = cursor.fetchall()
    
    # HTML Template Start
    html_content = f"""
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Quartals Wartungsverträge Auswertung</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <style>
            @media print {{
                .bg-gray-100 {{ background-color: white !important; }}
                .shadow-sm {{ box-shadow: none !important; }}
            }}
        </style>
    </head>
    <body class="bg-gray-100">
        <div class="container mx-auto px-4 py-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-8">Wartungsverträge Auswertung - {current_quarter} {current_year}</h1>
            
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kunde</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kundennummer</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kontingent</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Verbleibend</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fortschritt</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
    """
    
    # Für jeden Kunden
    for customer in customers:
        total_contingent = customer['contingent_hours'] * 60 + customer['contingent_minutes']
        used_minutes = calculate_used_contingent(cursor, customer['id'], current_month, current_year)
        remaining_minutes = total_contingent - used_minutes
        
        # Berechne Prozente und Farbe
        usage_percentage = (used_minutes / total_contingent) * 100 if total_contingent > 0 else 0
        remaining_percentage = 100 - usage_percentage
        
        if usage_percentage > 100:
            color_class = 'bg-red-500'
        elif usage_percentage > 75:
            color_class = 'bg-yellow-500'
        else:
            color_class = 'bg-green-500'
        
        # Formatierung der Stunden und Minuten
        total_hours = customer['contingent_hours']
        total_mins = customer['contingent_minutes']
        remaining_hours = abs(remaining_minutes) // 60
        remaining_mins = abs(remaining_minutes) % 60
        
        # Formatiere die Kontingent-Anzeige
        contingent_display = ""
        if total_hours:
            contingent_display += str(total_hours) + "h "
        if total_mins:
            contingent_display += str(total_mins) + "min"
        
        # Formatiere die verbleibende Zeit
        remaining_display = ""
        if remaining_minutes < 0:
            remaining_display += "-"
        if remaining_hours:
            remaining_display += str(remaining_hours) + "h "
        if remaining_mins:
            remaining_display += str(remaining_mins) + "min"
        
        # HTML für den Kunden
        html_content += f"""
        <tr class="hover:bg-gray-50">
            <td class="px-6 py-4 font-medium">{customer['name']}</td>
            <td class="px-6 py-4">{customer['customer_number']}</td>
            <td class="px-6 py-4">{contingent_display} pro Quartal</td>
            <td class="px-6 py-4">{remaining_display} ({remaining_percentage:.1f}%)</td>
            <td class="px-6 py-4">
                <div class="w-full bg-gray-200 rounded-full h-2.5">
                    <div class="{color_class} h-2.5 rounded-full" 
                         style="width: {min(100, max(0, usage_percentage))}%">
                    </div>
                </div>
            </td>
        </tr>"""
    
    # HTML Template Ende
    html_content += f"""
                    </tbody>
                </table>
            </div>
            <div class="mt-8 text-center text-gray-600">
                Erstellt am {current_date.strftime("%d.%m.%Y %H:%M")}
            </div>
        </div>
    </body>
    </html>
    """
    
    # Speichere die HTML-Datei
    quarter = math.ceil(current_month / 3)
    filename = f"wartungsvertraege_quartal_{current_year}_Q{quarter}.html"
    with open(filename, 'w', encoding='utf-8') as f:
        f.write(html_content)
    
    cursor.close()
    conn.close()
    
    return filename

if __name__ == "__main__":
    try:
        filename = generate_html_report()
        print(f"Report wurde erfolgreich erstellt: {filename}")
    except Exception as e:
        print(f"Fehler beim Erstellen des Reports: {str(e)}")