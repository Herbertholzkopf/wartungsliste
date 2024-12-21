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

// Aktueller Monat und Jahr
$current_month = isset($_GET['month']) ? $_GET['month'] : date('m');
$current_year = isset($_GET['year']) ? $_GET['year'] : date('Y');

// Funktion zum Berechnen des verbrauchten Kontingents
function calculateUsedContingent($pdo, $customer_id, $month, $year) {
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(
                CASE 
                    WHEN manual_duration_hours IS NOT NULL 
                    THEN manual_duration_hours * 60 + COALESCE(manual_duration_minutes, 0)
                    ELSE TIMESTAMPDIFF(MINUTE, start_datetime, COALESCE(end_datetime, NOW()))
                END
            ), 0) as total_minutes
        FROM work_entries 
        WHERE customer_id = ? 
        AND MONTH(start_datetime) = ? 
        AND YEAR(start_datetime) = ?
    ");
    $stmt->execute([$customer_id, $month, $year]);
    return $stmt->fetch(PDO::FETCH_ASSOC)['total_minutes'];
}

// POST Verarbeitung
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'start_work':
                $stmt = $pdo->prepare("
                    INSERT INTO work_entries (customer_id, employee_id, title, description, start_datetime) 
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $_POST['customer_id'],
                    $_POST['employee_id'],
                    $_POST['title'],
                    $_POST['description']
                ]);
                break;

            case 'stop_work':
                $stmt = $pdo->prepare("
                    UPDATE work_entries 
                    SET end_datetime = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$_POST['entry_id']]);
                break;

            case 'edit_work':
                $stmt = $pdo->prepare("
                    UPDATE work_entries 
                    SET title = ?, 
                        description = ?, 
                        employee_id = ?,
                        manual_duration_hours = ?,
                        manual_duration_minutes = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $_POST['title'],
                    $_POST['description'],
                    $_POST['employee_id'],
                    $_POST['duration_hours'],
                    $_POST['duration_minutes'],
                    $_POST['entry_id']
                ]);
                break;

            case 'delete_work':
                $stmt = $pdo->prepare("DELETE FROM work_entries WHERE id = ?");
                $stmt->execute([$_POST['entry_id']]);
                break;
        }
        
        // Redirect nach POST um Reload-Probleme zu vermeiden
        header("Location: " . $_SERVER['PHP_SELF'] . "?month=" . $current_month . "&year=" . $current_year);
        exit;
    }
}

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wartungsverträge Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
</head>
<body class="bg-gray-100">
    <div id="app" class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold text-gray-800">Wartungsverträge Dashboard</h1>
            
            <!-- Monatsauswahl -->
            <div class="flex gap-4 items-center">
                <form method="GET" class="flex gap-2">
                    <select name="month" class="rounded-lg border-gray-300 shadow-sm">
                        <?php
                        for ($i = 1; $i <= 12; $i++) {
                            $selected = $i == $current_month ? 'selected' : '';
                            echo "<option value='$i' $selected>" . date('F', mktime(0, 0, 0, $i, 1)) . "</option>";
                        }
                        ?>
                    </select>
                    <select name="year" class="rounded-lg border-gray-300 shadow-sm">
                        <?php
                        $current_year_num = date('Y');
                        for ($i = $current_year_num - 2; $i <= $current_year_num; $i++) {
                            $selected = $i == $current_year ? 'selected' : '';
                            echo "<option value='$i' $selected>$i</option>";
                        }
                        ?>
                    </select>
                    <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-lg">
                        Anzeigen
                    </button>
                </form>
                
                <a href="settings.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg">
                    Einstellungen
                </a>
            </div>
        </div>

        <!-- Kundenliste -->
        <div class="grid gap-6">
            <?php
            $stmt = $pdo->query("SELECT * FROM customers ORDER BY name");
            while ($customer = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $total_contingent = $customer['contingent_hours'] * 60 + $customer['contingent_minutes'];
                $used_minutes = calculateUsedContingent($pdo, $customer['id'], $current_month, $current_year);
                $percentage = min(100, ($used_minutes / $total_contingent) * 100);
                
                // Farbbestimmung für Fortschrittsbalken
                $color_class = 'bg-green-500';
                if ($percentage > 100) {
                    $color_class = 'bg-red-500';
                } elseif ($percentage > 75) {
                    $color_class = 'bg-orange-500';
                } elseif ($percentage > 50) {
                    $color_class = 'bg-yellow-500';
                }
            ?>
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex justify-between items-center mb-4">
                        <div>
                            <h2 class="text-xl font-bold text-gray-800"><?= htmlspecialchars($customer['name']) ?></h2>
                            <p class="text-gray-600">Kundennummer: <?= htmlspecialchars($customer['customer_number']) ?></p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm text-gray-600">
                                Verwendet: <?= floor($used_minutes / 60) ?>h <?= $used_minutes % 60 ?>min
                                von <?= $customer['contingent_hours'] ?>h <?= $customer['contingent_minutes'] ?>min
                            </p>
                            <p class="text-sm text-gray-600">
                                <?= number_format($percentage, 1) ?>%
                            </p>
                        </div>
                    </div>
                    
                    <!-- Fortschrittsbalken -->
                    <div class="w-full bg-gray-200 rounded-full h-4 mb-4">
                        <div class="<?= $color_class ?> rounded-full h-4 transition-all"
                             style="width: <?= min(100, $percentage) ?>%">
                        </div>
                    </div>

                    <!-- Arbeitseinträge und Formular -->
                    <div x-data="{ showEntries: false }">
                        <button @click="showEntries = !showEntries"
                                class="bg-blue-500 text-white px-4 py-2 rounded-lg">
                            Einträge anzeigen/verbergen
                        </button>

                        <div x-show="showEntries" class="mt-4">
                            <!-- Neue Arbeit Form -->
                            <form method="POST" class="mb-4 grid grid-cols-2 gap-4">
                                <input type="hidden" name="action" value="start_work">
                                <input type="hidden" name="customer_id" value="<?= $customer['id'] ?>">
                                
                                <div class="col-span-2">
                                    <label class="block text-sm font-medium text-gray-700">Mitarbeiter</label>
                                    <select name="employee_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                        <?php
                                        $employees = $pdo->query("SELECT * FROM employees ORDER BY name");
                                        while ($employee = $employees->fetch(PDO::FETCH_ASSOC)) {
                                            echo "<option value='{$employee['id']}'>{$employee['name']}</option>";
                                        }
                                        ?>
                                    </select>
                                </div>

                                <div class="col-span-2">
                                    <label class="block text-sm font-medium text-gray-700">Titel</label>
                                    <input type="text" name="title" required
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                </div>

                                <div class="col-span-2">
                                    <label class="block text-sm font-medium text-gray-700">Beschreibung</label>
                                    <textarea name="description" rows="3"
                                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"></textarea>
                                </div>

                                <button type="submit" class="bg-green-500 text-white px-4 py-2 rounded-lg">
                                    Arbeit starten
                                </button>
                            </form>

                            <!-- Liste der Arbeitseinträge -->
                            <div class="space-y-4">
                                <?php
                                $stmt = $pdo->prepare("
                                    SELECT 
                                        w.*,
                                        e.name as employee_name,
                                        CASE 
                                            WHEN w.manual_duration_hours IS NOT NULL 
                                            THEN w.manual_duration_hours * 60 + COALESCE(w.manual_duration_minutes, 0)
                                            ELSE TIMESTAMPDIFF(MINUTE, w.start_datetime, COALESCE(w.end_datetime, NOW()))
                                        END as duration_minutes
                                    FROM work_entries w
                                    JOIN employees e ON w.employee_id = e.id
                                    WHERE w.customer_id = ?
                                    AND MONTH(w.start_datetime) = ?
                                    AND YEAR(w.start_datetime) = ?
                                    ORDER BY w.start_datetime DESC
                                ");
                                $stmt->execute([$customer['id'], $current_month, $current_year]);
                                
                                while ($entry = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                    $duration_hours = floor($entry['duration_minutes'] / 60);
                                    $duration_minutes = $entry['duration_minutes'] % 60;
                                ?>
                                    <div class="bg-gray-50 rounded-lg p-4" x-data="{ editing: false }">
                                        <div x-show="!editing">
                                            <div class="flex justify-between items-start">
                                                <div>
                                                    <h3 class="font-bold"><?= htmlspecialchars($entry['title']) ?></h3>
                                                    <p class="text-sm text-gray-600">
                                                        <?= htmlspecialchars($entry['employee_name']) ?> -
                                                        <?= date('d.m.Y H:i', strtotime($entry['start_datetime'])) ?>
                                                        <?= $entry['end_datetime'] ? ' bis ' . date('H:i', strtotime($entry['end_datetime'])) : ' (läuft)' ?>
                                                    </p>
                                                    <p class="text-sm text-gray-600">
                                                        Dauer: <?= $duration_hours ?>h <?= $duration_minutes ?>min
                                                    </p>
                                                    <p class="mt-2"><?= nl2br(htmlspecialchars($entry['description'])) ?></p>
                                                </div>
                                                
                                                <div class="space-x-2">
                                                    <?php if (!$entry['end_datetime']) : ?>
                                                        <form method="POST" class="inline">
                                                            <input type="hidden" name="action" value="stop_work">
                                                            <input type="hidden" name="entry_id" value="<?= $entry['id'] ?>">
                                                            <button type="submit" class="bg-red-500 text-white px-3 py-1 rounded">
                                                                Stop
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    
                                                    <button @click="editing = true" class="bg-blue-500 text-white px-3 py-1 rounded">
                                                        Bearbeiten
                                                    </button>
                                                    
                                                    <form method="POST" class="inline" onsubmit="return confirm('Wirklich löschen?')">
                                                        <input type="hidden" name="action" value="delete_work">
                                                        <input type="hidden" name="entry_id" value="<?= $entry['id'] ?>">
                                                        <button type="submit" class="bg-red-500 text-white px-3 py-1 rounded">
                                                            Löschen
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Bearbeitungsformular -->
                                        <div x-show="editing" class="mt-4">
                                            <form method="POST" class="space-y-4">
                                                <input type="hidden" name="action" value="edit_work">
                                                <input type="hidden" name="entry_id" value="<?= $entry['id'] ?>">
                                                
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700">Mitarbeiter</label>
                                                    <select name="employee_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                                        <?php
                                                        $employees = $pdo->query("SELECT * FROM employees ORDER BY name");
                                                        while ($employee = $employees->fetch(PDO::FETCH_ASSOC)) {
                                                            $selected = $employee['id'] == $entry['employee_id'] ? 'selected' : '';
                                                            echo "<option value='{$employee['id']}' {$selected}>{$employee['name']}</option>";
                                                        }
                                                        ?>
                                                    </select>
                                                </div>

                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700">Titel</label>
                                                    <input type="text" name="title" required
                                                           value="<?= htmlspecialchars($entry['title']) ?>"
                                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                                </div>

                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700">Beschreibung</label>
                                                    <textarea name="description" rows="3"
                                                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"><?= htmlspecialchars($entry['description']) ?></textarea>
                                                </div>

                                                <div class="grid grid-cols-2 gap-4">
                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700">Stunden</label>
                                                        <input type="number" name="duration_hours"
                                                               value="<?= $duration_hours ?>"
                                                               min="0" 
                                                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                                    </div>
                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700">Minuten</label>
                                                        <input type="number" name="duration_minutes"
                                                               value="<?= $duration_minutes ?>"
                                                               min="0" max="59"
                                                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                                    </div>
                                                </div>

                                                <div class="flex justify-end space-x-2">
                                                    <button type="button" @click="editing = false"
                                                            class="bg-gray-500 text-white px-4 py-2 rounded-lg">
                                                        Abbrechen
                                                    </button>
                                                    <button type="submit"
                                                            class="bg-blue-500 text-white px-4 py-2 rounded-lg">
                                                        Speichern
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                <?php
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php
            }
            ?>
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