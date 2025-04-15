-- Wahl der Datenbank
USE wartungsliste;


CREATE TABLE customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_number VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    contingent_hours INT NOT NULL,
    contingent_minutes INT NOT NULL,
    contingent_emergency_tickets INT NOT NULL,
    calculation_time_span VARCHAR(10) NOT NULL DEFAULT 'monthly' CHECK (calculation_time_span IN ('monthly', 'quarterly')),
    notes TEXT
);

CREATE TABLE employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    notes TEXT
);

CREATE TABLE work_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    employee_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    datetime DATETIME NOT NULL,
    duration_minutes INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (employee_id) REFERENCES employees(id)
);

CREATE TABLE emergency_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    datetime DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id)
);


-- Tabelle für die Verwaltung der verbrauchten Minuten
-- Die Tabelle enthält die verbrauchten Minuten pro Kunde im aktuellen Abrechungszeitraum
-- Die Tabelle enthält den Status des verbrauchten Minutenkontingents im Vergleich mit dem verfügbaren Minutenkontingent
-- Der Status kann die Werte 'ok', 'warning' oder 'danger' annehmen
CREATE TABLE customer_contingent_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    used_minutes INT NOT NULL DEFAULT 0,
    status VARCHAR(10) NOT NULL DEFAULT 'ok' CHECK (status IN ('ok', 'warning', 'danger')),
    FOREIGN KEY (customer_id) REFERENCES customers(id)
);