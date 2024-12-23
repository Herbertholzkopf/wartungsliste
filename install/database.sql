-- Wahl der Datenbank
USE wartungsliste;


CREATE TABLE customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_number VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    contingent_hours INT NOT NULL,
    contingent_minutes INT NOT NULL,
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