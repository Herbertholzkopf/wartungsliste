<?php
// Einbinden der Konfiguration
$config = require_once 'config.php';

// Datenbankverbindung herstellen
try {
    $pdo = new PDO(
        "mysql:host={$config['server']};dbname={$config['database']};charset=utf8",
        $config['user'],
        $config['password']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Parameter prüfen
$customer_id = $_GET['customer_id'] ?? null;
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');

if (!$customer_id) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'Customer ID is required']);
    exit;
}

// Erst den calculation_time_span des Kunden abfragen
try {
    $stmt = $pdo->prepare("SELECT calculation_time_span FROM customers WHERE id = ?");
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer) {
        header('HTTP/1.1 404 Not Found');
        echo json_encode(['error' => 'Customer not found']);
        exit;
    }
    
    // Notfalltickets abrufen basierend auf calculation_time_span
    if ($customer['calculation_time_span'] === 'monthly') {
        // Für monatliche Kunden: Berechne nur den ausgewählten Monat
        $stmt = $pdo->prepare("
            SELECT 
                e.*
            FROM emergency_tickets e
            WHERE e.customer_id = ?
            AND MONTH(e.datetime) = ?
            AND YEAR(e.datetime) = ?
            ORDER BY e.datetime DESC
        ");
        $stmt->execute([$customer_id, $month, $year]);
    } else {
        // Für Quartalskunden: Berechne das gesamte Quartal
        $quarter = ceil($month / 3);
        $startMonth = ($quarter - 1) * 3 + 1;
        $endMonth = $quarter * 3;
        
        $stmt = $pdo->prepare("
            SELECT 
                e.*
            FROM emergency_tickets e
            WHERE e.customer_id = ?
            AND MONTH(e.datetime) BETWEEN ? AND ?
            AND YEAR(e.datetime) = ?
            ORDER BY e.datetime DESC
        ");
        $stmt->execute([$customer_id, $startMonth, $endMonth, $year]);
    }
    
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($tickets);
} catch(PDOException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Error fetching emergency tickets: ' . $e->getMessage()]);
    exit;
}