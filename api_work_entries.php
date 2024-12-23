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

// Arbeitseinträge abrufen
try {
    $stmt = $pdo->prepare("
        SELECT 
            w.*,
            e.name as employee_name
        FROM work_entries w
        JOIN employees e ON w.employee_id = e.id
        WHERE w.customer_id = ?
        AND MONTH(w.datetime) = ?
        AND YEAR(w.datetime) = ?
        ORDER BY w.datetime DESC
    ");

    $stmt->execute([$customer_id, $month, $year]);
    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($entries);
} catch(PDOException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Error fetching work entries']);
    exit;
}