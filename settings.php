<?php
session_start();

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
    die("Verbindungsfehler: " . $e->getMessage());
}

// POST Verarbeitung
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_customer':
                $stmt = $pdo->prepare("
                    INSERT INTO customers (name, customer_number, contingent_hours, contingent_minutes, notes) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $_POST['name'],
                    $_POST['customer_number'],
                    $_POST['contingent_hours'],
                    $_POST['contingent_minutes'],
                    $_POST['notes']
                ]);
                break;

            case 'edit_customer':
                $stmt = $pdo->prepare("
                    UPDATE customers 
                    SET name = ?, 
                        customer_number = ?, 
                        contingent_hours = ?,
                        contingent_minutes = ?,
                        notes = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $_POST['name'],
                    $_POST['customer_number'],
                    $_POST['contingent_hours'],
                    $_POST['contingent_minutes'],
                    $_POST['notes'],
                    $_POST['customer_id']
                ]);
                break;

            case 'delete_customer':
                try {
                    // Transaktion starten
                    $pdo->beginTransaction();
                    
                    // Erst alle zugehörigen Arbeitseinträge löschen
                    $stmt = $pdo->prepare("DELETE FROM work_entries WHERE customer_id = ?");
                    $stmt->execute([$_POST['customer_id']]);
                    
                    // Dann den Kunden selbst löschen
                    $stmt = $pdo->prepare("DELETE FROM customers WHERE id = ?");
                    $stmt->execute([$_POST['customer_id']]);
                    
                    // Transaktion abschließen
                    $pdo->commit();
                } catch(Exception $e) {
                    // Bei einem Fehler Transaktion rückgängig machen
                    $pdo->rollBack();
                    $_SESSION['error'] = "Fehler beim Löschen des Kunden: " . $e->getMessage();
                }
                break;

            case 'add_employee':
                $stmt = $pdo->prepare("INSERT INTO employees (name, notes) VALUES (?, ?)");
                $stmt->execute([$_POST['name'], $_POST['notes']]);
                break;

            case 'edit_employee':
                $stmt = $pdo->prepare("UPDATE employees SET name = ?, notes = ? WHERE id = ?");
                $stmt->execute([$_POST['name'], $_POST['notes'], $_POST['employee_id']]);
                break;

            case 'delete_employee':
                // Erst prüfen ob Arbeitseinträge existieren
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM work_entries WHERE employee_id = ?");
                $stmt->execute([$_POST['employee_id']]);
                if ($stmt->fetchColumn() > 0) {
                    $_SESSION['error'] = "Mitarbeiter kann nicht gelöscht werden, da bereits Arbeitseinträge existieren.";
                } else {
                    $stmt = $pdo->prepare("DELETE FROM employees WHERE id = ?");
                    $stmt->execute([$_POST['employee_id']]);
                }
                break;
        }
        
        // Redirect nach POST
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Einstellungen - Wartungsverträge</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold text-gray-800">Einstellungen</h1>
            <a href="index.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg">
                Zurück zum Dashboard
            </a>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?= $_SESSION['error'] ?></span>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Kunden Verwaltung -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold text-gray-800">Kunden</h2>
                <button onclick="showCustomerModal()" class="bg-blue-500 text-white px-4 py-2 rounded-lg">
                    Neuen Kunden anlegen
                </button>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kundennummer</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kontingent</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Notizen</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php
                        $customers = $pdo->query("SELECT * FROM customers ORDER BY name")->fetchAll();
                        foreach ($customers as $customer) {
                        ?>
                        <tr>
                            <td class="px-6 py-4"><?= htmlspecialchars($customer['name']) ?></td>
                            <td class="px-6 py-4"><?= htmlspecialchars($customer['customer_number']) ?></td>
                            <td class="px-6 py-4"><?= $customer['contingent_hours'] ?>h <?= $customer['contingent_minutes'] ?>min</td>
                            <td class="px-6 py-4" title="<?= htmlspecialchars($customer['notes']) ?>">
                <?= nl2br(htmlspecialchars(strlen($customer['notes']) > 50 ? substr($customer['notes'], 0, 50) . '...' : $customer['notes'])) ?>
            </td>
                            <td class="px-6 py-4">
                                <button onclick='showEditCustomerModal(<?= json_encode($customer) ?>)' 
                                        class="bg-blue-500 text-white px-3 py-1 rounded mr-2">
                                    Bearbeiten
                                </button>
                                <form method="POST" class="inline" onsubmit="return confirm('Wirklich löschen? Dies wird auch alle Arbeitseinträge dieses Kunden löschen!')">
                                    <input type="hidden" name="action" value="delete_customer">
                                    <input type="hidden" name="customer_id" value="<?= $customer['id'] ?>">
                                    <button type="submit" class="bg-red-500 text-white px-3 py-1 rounded">
                                        Löschen
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Mitarbeiter Verwaltung -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold text-gray-800">Mitarbeiter</h2>
                <button onclick="showEmployeeModal()" class="bg-blue-500 text-white px-4 py-2 rounded-lg">
                    Neuen Mitarbeiter anlegen
                </button>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Notizen</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php
                        $employees = $pdo->query("SELECT * FROM employees ORDER BY name")->fetchAll();
                        foreach ($employees as $employee) {
                        ?>
                        <tr>
                            <td class="px-6 py-4"><?= htmlspecialchars($employee['name']) ?></td>
                            <td class="px-6 py-4" title="<?= htmlspecialchars($employee['notes']) ?>">
                <?= nl2br(htmlspecialchars(strlen($employee['notes']) > 50 ? substr($employee['notes'], 0, 50) . '...' : $employee['notes'])) ?>
            </td>
                            <td class="px-6 py-4">
                                <button onclick='showEditEmployeeModal(<?= json_encode($employee) ?>)' 
                                        class="bg-blue-500 text-white px-3 py-1 rounded mr-2">
                                    Bearbeiten
                                </button>
                                <form method="POST" class="inline" onsubmit="return confirm('Wirklich löschen?')">
                                    <input type="hidden" name="action" value="delete_employee">
                                    <input type="hidden" name="employee_id" value="<?= $employee['id'] ?>">
                                    <button type="submit" class="bg-red-500 text-white px-3 py-1 rounded">
                                        Löschen
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Customer Modal -->
    <div id="customerModal" class="modal">
        <div class="modal-content bg-white w-full max-w-2xl mx-auto mt-20 rounded-lg shadow-lg p-6">
            <div class="flex justify-between items-start mb-6">
                <h2 id="customerModalTitle" class="text-2xl font-bold">Neuen Kunden anlegen</h2>
                <button onclick="hideCustomerModal()" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form id="customerForm" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="add_customer">
                <input type="hidden" name="customer_id" id="customerFormId">

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Name</label>
                        <input type="text" name="name" id="customerFormName" required
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Kundennummer</label>
                        <input type="text" name="customer_number" id="customerFormNumber" required
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Kontingent Stunden</label>
                        <input type="number" name="contingent_hours" id="customerFormHours" required min="0"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Kontingent Minuten</label>
                        <input type="number" name="contingent_minutes" id="customerFormMinutes" required min="0" max="59"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Notizen</label>
                    <textarea name="notes" id="customerFormNotes" rows="10"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"></textarea>
                </div>

                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="hideCustomerModal()"
                            class="bg-gray-500 text-white px-4 py-2 rounded-lg">
                        Abbrechen
                    </button>
                    <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-lg">
                        Speichern
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Employee Modal -->
    <div id="employeeModal" class="modal">
        <div class="modal-content bg-white w-full max-w-2xl mx-auto mt-20 rounded-lg shadow-lg p-6">
            <div class="flex justify-between items-start mb-6">
                <h2 id="employeeModalTitle" class="text-2xl font-bold">Neuen Mitarbeiter anlegen</h2>
                <button onclick="hideEmployeeModal()" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form id="employeeForm" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="add_employee">
                <input type="hidden" name="employee_id" id="employeeFormId">

                <div>
                    <label class="block text-sm font-medium text-gray-700">Name</label>
                    <input type="text" name="name" id="employeeFormName" required
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Notizen</label>
                    <textarea name="notes" id="employeeFormNotes" rows="10"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"></textarea>
                </div>

                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="hideEmployeeModal()"
                            class="bg-gray-500 text-white px-4 py-2 rounded-lg">
                        Abbrechen
                    </button>
                    <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-lg">
                        Speichern
                    </button>
                </div>
            </form>
        </div>
    </div>

    <style>
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }
        .modal.active {
            display: flex;
        }
    </style>

    <script>
        // Modal Funktionen für Kunden
        function showCustomerModal() {
            document.getElementById('customerModalTitle').textContent = 'Neuen Kunden anlegen';
            document.getElementById('customerForm').reset();
            document.getElementById('customerForm').querySelector('input[name="action"]').value = 'add_customer';
            document.getElementById('customerFormId').value = '';
            document.getElementById('customerModal').classList.add('active');
        }

        function showEditCustomerModal(customer) {
            document.getElementById('customerModalTitle').textContent = 'Kunden bearbeiten';
            document.getElementById('customerForm').querySelector('input[name="action"]').value = 'edit_customer';
            document.getElementById('customerFormId').value = customer.id;
            document.getElementById('customerFormName').value = customer.name;
            document.getElementById('customerFormNumber').value = customer.customer_number;
            document.getElementById('customerFormHours').value = customer.contingent_hours;
            document.getElementById('customerFormMinutes').value = customer.contingent_minutes;
            document.getElementById('customerFormNotes').value = customer.notes || '';
            document.getElementById('customerModal').classList.add('active');
        }

        function hideCustomerModal() {
            document.getElementById('customerModal').classList.remove('active');
        }

        // Modal Funktionen für Mitarbeiter
        function showEmployeeModal() {
            document.getElementById('employeeModalTitle').textContent = 'Neuen Mitarbeiter anlegen';
            document.getElementById('employeeForm').reset();
            document.getElementById('employeeForm').querySelector('input[name="action"]').value = 'add_employee';
            document.getElementById('employeeFormId').value = '';
            document.getElementById('employeeModal').classList.add('active');
        }

        function showEditEmployeeModal(employee) {
            document.getElementById('employeeModalTitle').textContent = 'Mitarbeiter bearbeiten';
            document.getElementById('employeeForm').querySelector('input[name="action"]').value = 'edit_employee';
            document.getElementById('employeeFormId').value = employee.id;
            document.getElementById('employeeFormName').value = employee.name;
            document.getElementById('employeeFormNotes').value = employee.notes || '';
            document.getElementById('employeeModal').classList.add('active');
        }

        function hideEmployeeModal() {
            document.getElementById('employeeModal').classList.remove('active');
        }

        // Event-Listener für Modals
        document.addEventListener('DOMContentLoaded', function() {
            // Schließe Modals beim Klick außerhalb
            document.querySelectorAll('.modal').forEach(modal => {
                modal.addEventListener('click', function(event) {
                    if (event.target === this) {
                        hideCustomerModal();
                        hideEmployeeModal();
                    }
                });
            });

            // Form-Handler für Kunden
            document.getElementById('customerForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                }).then(async response => {
                    if (response.ok) {
                        window.location.reload();
                    } else {
                        const errorText = await response.text();
                        console.error('Server Error:', errorText);
                        throw new Error(`Fehler beim Speichern: ${errorText}`);
                    }
                }).catch(error => {
                    console.error('Error:', error);
                    alert(error.message || 'Fehler beim Speichern des Kunden');
                });
            });

            // Form-Handler für Mitarbeiter
            document.getElementById('employeeForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                }).then(response => {
                    if (response.ok) {
                        window.location.reload();
                    } else {
                        throw new Error('Fehler beim Speichern');
                    }
                }).catch(error => {
                    console.error('Error:', error);
                    alert('Fehler beim Speichern des Mitarbeiters');
                });
            });
        });
    </script>
</body>
</html>