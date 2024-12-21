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
                // Erst prüfen ob Arbeitseinträge existieren
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM work_entries WHERE customer_id = ?");
                $stmt->execute([$_POST['customer_id']]);
                if ($stmt->fetchColumn() > 0) {
                    $_SESSION['error'] = "Kunde kann nicht gelöscht werden, da bereits Arbeitseinträge existieren.";
                } else {
                    $stmt = $pdo->prepare("DELETE FROM customers WHERE id = ?");
                    $stmt->execute([$_POST['customer_id']]);
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
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
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
            <h2 class="text-xl font-bold text-gray-800 mb-4">Kunden</h2>
            
            <!-- Neuer Kunde Form -->
            <div x-data="{ showForm: false }" class="mb-6">
                <button @click="showForm = !showForm"
                        class="bg-blue-500 text-white px-4 py-2 rounded-lg mb-4">
                    Neuen Kunden anlegen
                </button>

                <form x-show="showForm" method="POST" class="space-y-4 bg-gray-50 p-4 rounded-lg">
                    <input type="hidden" name="action" value="add_customer">
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Name</label>
                            <input type="text" name="name" required
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Kundennummer</label>
                            <input type="text" name="customer_number" required
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Kontingent Stunden</label>
                            <input type="number" name="contingent_hours" required min="0"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Kontingent Minuten</label>
                            <input type="number" name="contingent_minutes" required min="0" max="59"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Notizen</label>
                        <textarea name="notes" rows="3"
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"></textarea>
                    </div>

                    <div class="flex justify-end space-x-2">
                        <button type="button" @click="showForm = false"
                                class="bg-gray-500 text-white px-4 py-2 rounded-lg">
                            Abbrechen
                        </button>
                        <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-lg">
                            Speichern
                        </button>
                    </div>
                </form>
            </div>

            <!-- Kundenliste -->
            <div class="space-y-4">
                <?php
                $customers = $pdo->query("SELECT * FROM customers ORDER BY name")->fetchAll();
                foreach ($customers as $customer) {
                ?>
                    <div class="border rounded-lg p-4" x-data="{ editing: false }">
                        <div x-show="!editing" class="flex justify-between items-start">
                            <div>
                                <h3 class="font-bold"><?= htmlspecialchars($customer['name']) ?></h3>
                                <p class="text-sm text-gray-600">
                                    Kundennummer: <?= htmlspecialchars($customer['customer_number']) ?>
                                </p>
                                <p class="text-sm text-gray-600">
                                    Kontingent: <?= $customer['contingent_hours'] ?>h <?= $customer['contingent_minutes'] ?>min
                                </p>
                                <?php if ($customer['notes']): ?>
                                    <p class="mt-2 text-sm"><?= nl2br(htmlspecialchars($customer['notes'])) ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="space-x-2">
                                <button @click="editing = true"
                                        class="bg-blue-500 text-white px-3 py-1 rounded">
                                    Bearbeiten
                                </button>
                                <form method="POST" class="inline" onsubmit="return confirm('Wirklich löschen?')">
                                    <input type="hidden" name="action" value="delete_customer">
                                    <input type="hidden" name="customer_id" value="<?= $customer['id'] ?>">
                                    <button type="submit" class="bg-red-500 text-white px-3 py-1 rounded">
                                        Löschen
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Bearbeitungsformular -->
                        <form x-show="editing" method="POST" class="space-y-4 mt-4">
                            <input type="hidden" name="action" value="edit_customer">
                            <input type="hidden" name="customer_id" value="<?= $customer['id'] ?>">
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Name</label>
                                    <input type="text" name="name" required
                                           value="<?= htmlspecialchars($customer['name']) ?>"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Kundennummer</label>
                                    <input type="text" name="customer_number" required
                                           value="<?= htmlspecialchars($customer['customer_number']) ?>"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Kontingent Stunden</label>
                                    <input type="number" name="contingent_hours" required min="0"
                                           value="<?= $customer['contingent_hours'] ?>"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Kontingent Minuten</label>
                                    <input type="number" name="contingent_minutes" required min="0" max="59"
                                           value="<?= $customer['contingent_minutes'] ?>"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Notizen</label>
                                <textarea name="notes" rows="3"
                                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"><?= htmlspecialchars($customer['notes']) ?></textarea>
                            </div>

                            <div class="flex justify-end space-x-2">
                                <button type="button" @click="editing = false"
                                        class="bg-gray-500 text-white px-4 py-2 rounded-lg">
                                    Abbrechen
                                </button>
                                <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-lg">
                                    Speichern
                                </button>
                            </div>
                        </form>
                    </div>
                <?php
                }
                ?>
            </div>
        </div>

        <!-- Mitarbeiter Verwaltung -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Mitarbeiter</h2>
            
            <!-- Neuer Mitarbeiter Form -->
            <div x-data="{ showForm: false }" class="mb-6">
                <button @click="showForm = !showForm"
                        class="bg-blue-500 text-white px-4 py-2 rounded-lg mb-4">
                    Neuen Mitarbeiter anlegen
                </button>

                <form x-show="showForm" method="POST" class="space-y-4 bg-gray-50 p-4 rounded-lg">
                    <input type="hidden" name="action" value="add_employee">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Name</label>
                        <input type="text" name="name" required
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Notizen</label>
                        <textarea name="notes" rows="3"
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"></textarea>
                    </div>

                    <div class="flex justify-end space-x-2">
                        <button type="button" @click="showForm = false"
                                class="bg-gray-500 text-white px-4 py-2 rounded-lg">
                            Abbrechen
                        </button>
                        <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-lg">
                            Speichern
                        </button>
                    </div>
                </form>
            </div>

            <!-- Mitarbeiterliste -->
            <div class="space-y-4">
                <?php
                $employees = $pdo->query("SELECT * FROM employees ORDER BY name")->fetchAll();
                foreach ($employees as $employee) {
                ?>
                    <div class="border rounded-lg p-4" x-data="{ editing: false }">
                        <div x-show="!editing" class="flex justify-between items-start">
                            <div>
                                <h3 class="font-bold"><?= htmlspecialchars($employee['name']) ?></h3>
                                <?php if ($employee['notes']): ?>
                                    <p class="mt-2 text-sm"><?= nl2br(htmlspecialchars($employee['notes'])) ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="space-x-2">
                                <button @click="editing = true"
                                        class="bg-blue-500 text-white px-3 py-1 rounded">
                                    Bearbeiten
                                </button>
                                <form method="POST" class="inline" onsubmit="return confirm('Wirklich löschen?')">
                                    <input type="hidden" name="action" value="delete_employee">
                                    <input type="hidden" name="employee_id" value="<?= $employee['id'] ?>">
                                    <button type="submit" class="bg-red-500 text-white px-3 py-1 rounded">
                                        Löschen
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Bearbeitungsformular -->
                        <form x-show="editing" method="POST" class="space-y-4 mt-4">
                            <input type="hidden" name="action" value="edit_employee">
                            <input type="hidden" name="employee_id" value="<?= $employee['id'] ?>">
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Name</label>
                                <input type="text" name="name" required
                                       value="<?= htmlspecialchars($employee['name']) ?>"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Notizen</label>
                                <textarea name="notes" rows="3"
                                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"><?= htmlspecialchars($employee['notes']) ?></textarea>
                            </div>

                            <div class="flex justify-end space-x-2">
                                <button type="button" @click="editing = false"
                                        class="bg-gray-500 text-white px-4 py-2 rounded-lg">
                                    Abbrechen
                                </button>
                                <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-lg">
                                    Speichern
                                </button>
                            </div>
                        </form>
                    </div>
                <?php
                }
                ?>
            </div>
        </div>
    </div>

    <script>
        // Alpine.js Initialization (falls benötigt)
        document.addEventListener('alpine:init', () => {
            // Hier können wir Alpine.js Komponenten definieren
        });
    </script>
</body>
</html>