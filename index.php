<?php
session_start();

date_default_timezone_set('Europe/Berlin');

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
    // Hole zuerst den calculation_time_span des Kunden
    $stmt = $pdo->prepare("SELECT calculation_time_span FROM customers WHERE id = ?");
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($customer['calculation_time_span'] === 'monthly') {
        // Für monatliche Kunden: Berechne nur den ausgewählten Monat
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(duration_minutes), 0) as total_minutes
            FROM work_entries 
            WHERE customer_id = ? 
            AND MONTH(datetime) = ? 
            AND YEAR(datetime) = ?
        ");
        $stmt->execute([$customer_id, $month, $year]);
    } else {
        // Für Quartalskunden: Berechne das gesamte Quartal
        $quarter = ceil($month / 3);
        $startMonth = ($quarter - 1) * 3 + 1;
        $endMonth = $quarter * 3;
        
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(duration_minutes), 0) as total_minutes
            FROM work_entries 
            WHERE customer_id = ? 
            AND MONTH(datetime) BETWEEN ? AND ?
            AND YEAR(datetime) = ?
        ");
        $stmt->execute([$customer_id, $startMonth, $endMonth, $year]);
    }
    
    return $stmt->fetch(PDO::FETCH_ASSOC)['total_minutes'];
}

// Validierungsfunktion für Datumsformat
function validateDateTime($date, $time) {
    $dateTime = DateTime::createFromFormat('Y-m-d H:i', "$date $time");
    return $dateTime && $dateTime->format('Y-m-d H:i') === "$date $time";
}

// Verbesserte Fehlerbehandlung und Validierung für POST-Anfragen
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['action'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Keine Aktion angegeben']);
        exit;
    }

    try {
        switch ($_POST['action']) {
            case 'add_work':
                if (!isset($_POST['work_date']) || !isset($_POST['duration_minutes'])) {
                    throw new Exception('Alle Felder müssen ausgefüllt sein');
                }

                $stmt = $pdo->prepare("
                    INSERT INTO work_entries (
                        customer_id, 
                        employee_id, 
                        title, 
                        description, 
                        datetime,
                        duration_minutes
                    ) VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $_POST['customer_id'],
                    $_POST['employee_id'],
                    $_POST['title'],
                    $_POST['description'],
                    $_POST['work_date'] . ' 00:00:00',
                    $_POST['duration_minutes']
                ]);
                break;

            case 'edit_work':
                if (!isset($_POST['work_date']) || !isset($_POST['duration_minutes'])) {
                    throw new Exception('Alle Felder müssen ausgefüllt sein');
                }

                $stmt = $pdo->prepare("
                    UPDATE work_entries 
                    SET title = ?, 
                        description = ?, 
                        employee_id = ?,
                        datetime = ?,
                        duration_minutes = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $_POST['title'],
                    $_POST['description'],
                    $_POST['employee_id'],
                    $_POST['work_date'] . ' 00:00:00',
                    $_POST['duration_minutes'],
                    $_POST['entry_id']
                ]);
                break;

            case 'delete_work':
                $stmt = $pdo->prepare("DELETE FROM work_entries WHERE id = ?");
                $stmt->execute([$_POST['entry_id']]);
                break;
        }

        // Sende eine Erfolgsantwort zurück
        http_response_code(200);
        echo json_encode(['success' => true]);
        exit;

    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
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
        .container {
            margin-bottom: 4rem;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold text-gray-800">Wartungsverträge Dashboard</h1>
            
            <!-- Monatsauswahl -->
            <div class="flex gap-4 items-center">
                <form method="GET" class="flex gap-2">
                    <select name="month" class="px-4 py-2 border rounded-lg">
                        <?php
                        $monate = array(
                            1 => 'Januar',
                            2 => 'Februar',
                            3 => 'März',
                            4 => 'April',
                            5 => 'Mai',
                            6 => 'Juni',
                            7 => 'Juli',
                            8 => 'August',
                            9 => 'September',
                            10 => 'Oktober',
                            11 => 'November',
                            12 => 'Dezember'
                        );
                        
                        for ($i = 1; $i <= 12; $i++) {
                            $selected = $i == $current_month ? 'selected' : '';
                            echo "<option value='$i' $selected>" . $monate[$i] . "</option>";
                        }
                        ?>
                    </select>
                    <select name="year" class="px-4 py-2 border rounded-lg">
                        <?php
                        $current_year_num = date('Y');
                        // Zeige 2 Jahre zurück und 1 Jahr in die Zukunft an
                        for ($i = $current_year_num - 2; $i <= $current_year_num + 1; $i++) {
                            $selected = $i == $current_year ? 'selected' : '';
                            echo "<option value='$i' $selected>$i</option>";
                        }
                        ?>
                    </select>
                    <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                        Anzeigen
                    </button>
                </form>
                
                <a href="settings.php" class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600">
                    Einstellungen
                </a>
            </div>
        </div>

        <!-- Kunden Table -->
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
                    <?php
                    $stmt = $pdo->query("SELECT * FROM customers ORDER BY name");
                    while ($customer = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $total_contingent = $customer['contingent_hours'] * 60 + $customer['contingent_minutes'];
                        $used_minutes = calculateUsedContingent($pdo, $customer['id'], $current_month, $current_year);
                        $remaining_minutes = $total_contingent - $used_minutes;
                        
                        // Neue Prozentberechnung
                        $usage_percentage = ($remaining_minutes / $total_contingent) * 100;
                        
                        // Farbbestimmung für Fortschrittsbalken
                        $color_class = 'bg-red-500';     // Default für überzogenes Kontingent (< 0%)
                        if ($usage_percentage > 25) {
                            $color_class = 'bg-green-500';  // Über 25% übrig
                        } elseif ($usage_percentage > 0) {
                            $color_class = 'bg-yellow-500'; // Zwischen 0% und 25% übrig
                        } elseif ($usage_percentage == 0) {
                            $color_class = 'bg-orange-500'; // Genau 0% übrig
                        }
                        
                        // Formatierung der Stunden und Minuten
                        $total_hours = floor($total_contingent / 60);
                        $total_mins = $total_contingent % 60;
                        $remaining_hours = floor(abs($remaining_minutes) / 60);
                        $remaining_mins = abs($remaining_minutes) % 60;
                        $prefix = $remaining_minutes < 0 ? '-' : '';
                    ?>
                    <tr class="hover:bg-gray-50 cursor-pointer" onclick='showCustomerModal(<?php echo json_encode([
                        "id" => $customer["id"],
                        "name" => $customer["name"],
                        "customer_number" => $customer["customer_number"],
                        "contingent_hours" => $customer["contingent_hours"],
                        "contingent_minutes" => $customer["contingent_minutes"],
                        "used_minutes" => $used_minutes,
                        "calculation_time_span" => $customer["calculation_time_span"]
                    ]); ?>)'>
                        <td class="px-6 py-4 font-medium"><?php echo htmlspecialchars($customer['name']); ?></td>
                        <td class="px-6 py-4"><?php echo htmlspecialchars($customer['customer_number']); ?></td>
                        <td class="px-6 py-4">
                            <?php 
                            $timespan_text = $customer['calculation_time_span'] === 'monthly' ? 'pro Monat' : 'pro Quartal';
                            $output = [];
                            
                            if ($total_hours > 0) {
                                $output[] = "{$total_hours}h";
                            }
                            if ($total_mins > 0) {
                                $output[] = "{$total_mins}min";
                            }
                            
                            echo implode(' ', $output) . " {$timespan_text}"; 
                            ?>
                        </td>
                        <td class="px-6 py-4">
                            <?php 
                            $output = [];
                            
                            if ($remaining_minutes >= 0) {
                                // Positive verbleibende Zeit
                                if ($remaining_hours > 0) {
                                    $output[] = "{$remaining_hours}h";
                                }
                                if ($remaining_mins > 0) {
                                    $output[] = "{$remaining_mins}min";
                                }
                            } else {
                                // Negative verbleibende Zeit (Überziehung)
                                if ($remaining_hours > 0) {
                                    $output[] = "-{$remaining_hours}h";
                                }
                                if ($remaining_mins > 0) {
                                    $output[] = "-{$remaining_mins}min";
                                }
                            }
                            
                            echo implode(' ', $output);
                            echo " (" . number_format($usage_percentage, 1) . "%)";
                            ?>
                        </td>
                        <td class="px-6 py-4">
                            <div class="w-full bg-gray-200 rounded-full h-2.5">
                                <div class="<?= $color_class ?> h-2.5 rounded-full" 
                                    style="width: <?= $usage_percentage <= 0 ? '100' : min(100, max(0, $usage_percentage)) ?>%">
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Customer Modal -->
    <div id="customerModal" class="modal">
        <div class="modal-content bg-white w-full max-w-4xl mx-auto mt-20 rounded-lg shadow-lg p-6 overflow-y-auto" style="max-height: 80vh;">
        <div class="flex justify-between items-start mb-6">
            <div>
                <h2 id="modalCustomerName" class="text-2xl font-bold mb-2"></h2>
                <div id="modalCustomerDetails" class="text-gray-600 space-y-1"></div>
            </div>
            <button onclick="hideModal()" class="text-gray-500 hover:text-gray-700">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

            <!-- Arbeitseinträge Form -->
            <form id="workEntryForm" method="POST" class="mb-6 bg-gray-50 p-4 rounded-lg">
                <input type="hidden" name="action" value="add_work">
                <input type="hidden" id="modalCustomerId" name="customer_id">
                
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
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
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Ticketnummer</label>
                        <input type="text" name="title" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Datum</label>
                        <input type="date" name="work_date" required
                            value="<?php echo date('Y-m-d'); ?>"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Dauer (in Minuten)</label>
                        <input type="number" name="duration_minutes" required min="1"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Beschreibung</label>
                    <textarea name="description" rows="3"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"></textarea>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600">
                        Hinzufügen
                    </button>
                </div>
            </form>

            <!-- Arbeitseinträge Liste -->
            <div id="workEntriesList" class="space-y-4">
                <!-- Wird dynamisch gefüllt -->
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content bg-white w-full max-w-2xl mx-auto mt-20 rounded-lg shadow-lg p-6">
            <div class="flex justify-between items-start mb-6">
                <h2 class="text-2xl font-bold">Eintrag bearbeiten</h2>
                <button onclick="hideEditModal()" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form id="editForm" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="edit_work">
                <input type="hidden" name="entry_id" id="editEntryId">

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Mitarbeiter</label>
                        <select name="employee_id" id="editEmployeeId" required 
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            <?php
                            $employees = $pdo->query("SELECT * FROM employees ORDER BY name");
                            while ($employee = $employees->fetch(PDO::FETCH_ASSOC)) {
                                echo "<option value='{$employee['id']}'>{$employee['name']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Ticketnummer</label>
                        <input type="text" name="title" id="editTitle" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Datum</label>
                        <input type="date" name="work_date" id="editDate" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Dauer (in Minuten)</label>
                        <input type="number" name="duration_minutes" id="editDuration" required min="1"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Beschreibung</label>
                    <textarea name="description" id="editDescription" rows="3"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"></textarea>
                </div>

                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="hideEditModal()"
                            class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600">
                        Abbrechen
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                        Speichern
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentCustomerId = null;

        async function showCustomerModal(customer) {
            currentCustomerId = customer.id;
            document.getElementById('modalCustomerId').value = customer.id;
            document.getElementById('modalCustomerName').textContent = customer.name;

            // Formatierung des Kontingents
            let contingentText = [];
            if (customer.contingent_hours > 0) {
                contingentText.push(`${customer.contingent_hours}h`);
            }
            if (customer.contingent_minutes > 0) {
                contingentText.push(`${customer.contingent_minutes}min`);
            }
            
            // Erstelle zwei separate Zeilen
            const customerNumberDiv = document.createElement('div');
            customerNumberDiv.textContent = `Kundennummer: ${customer.customer_number}`;
            
            const contingentDiv = document.createElement('div');
            const timespan = customer.calculation_time_span === 'monthly' ? 'pro Monat' : 'pro Quartal';
            contingentDiv.textContent = `Kontingent: ${contingentText.join(' ')} ${timespan}`;
            
            // Leere den modalCustomerDetails Container und füge die neuen Elemente hinzu
            const modalCustomerDetails = document.getElementById('modalCustomerDetails');
            modalCustomerDetails.innerHTML = '';
            modalCustomerDetails.appendChild(customerNumberDiv);
            modalCustomerDetails.appendChild(contingentDiv);

            await loadWorkEntries(customer.id);
            document.getElementById('customerModal').classList.add('active');
        }

        async function loadWorkEntries(customerId) {
            try {
                const response = await fetch(`api_work_entries.php?customer_id=${customerId}&month=<?= $current_month ?>&year=<?= $current_year ?>`);
                const entries = await response.json();
                
                const entriesHtml = entries.map(entry => {
                    // Konvertiere Minuten in Stunden und Minuten für die Anzeige
                    const hours = Math.floor(entry.duration_minutes / 60);
                    const minutes = entry.duration_minutes % 60;
                    
                    return `
                        <div class="bg-white p-4 rounded-lg shadow">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h3 class="font-bold">${entry.title}</h3>
                                    <p class="text-sm text-gray-600">
                                        ${entry.employee_name} - 
                                        ${formatDate(entry.datetime)}
                                    </p>
                                    <p class="text-sm text-gray-600">
                                        Dauer: ${hours}h ${minutes}min
                                    </p>
                                    <p class="mt-2">${entry.description || ''}</p>
                                </div>
                                <div class="flex space-x-2">
                                    <button onclick='showEditModal(${JSON.stringify(entry).replace(/'/g, "&#39;").replace(/"/g, "&quot;")})'
                                            type="button"
                                            class="px-3 py-1 bg-blue-500 text-white rounded hover:bg-blue-600">
                                        Bearbeiten
                                    </button>
                                    <button onclick='deleteEntry(${entry.id})'
                                            type="button"
                                            class="px-3 py-1 bg-red-500 text-white rounded hover:bg-red-600">
                                        Löschen
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                }).join('');
                
                document.getElementById('workEntriesList').innerHTML = entriesHtml;
            } catch (error) {
                console.error('Error loading work entries:', error);
                document.getElementById('workEntriesList').innerHTML = 
                    '<div class="text-red-500">Fehler beim Laden der Arbeitseinträge</div>';
            }
        }

        function showEditModal(entry) {
            try {
                // Konvertiere zu String und zurück, falls entry bereits ein String ist
                if (typeof entry === 'string') {
                    entry = JSON.parse(entry);
                }

                // Datum aus dem Datetime extrahieren
                const date = new Date(entry.datetime);

                // Formularfelder füllen
                document.getElementById('editEntryId').value = entry.id;
                document.getElementById('editEmployeeId').value = entry.employee_id;
                document.getElementById('editTitle').value = entry.title;
                document.getElementById('editDescription').value = entry.description || '';
                document.getElementById('editDate').value = date.toISOString().split('T')[0];
                document.getElementById('editDuration').value = entry.duration_minutes;

                document.getElementById('editModal').classList.add('active');
            } catch (error) {
                console.error('Error in showEditModal:', error);
                alert('Fehler beim Öffnen des Bearbeiten-Dialogs');
            }
        }

        function hideModal() {
            document.getElementById('customerModal').classList.remove('active');
        }

        function hideEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }

        async function deleteEntry(entryId) {
            if (!confirm('Möchten Sie diesen Eintrag wirklich löschen?')) {
                return;
            }

            try {
                const formData = new FormData();
                formData.append('action', 'delete_work');
                formData.append('entry_id', entryId);
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    await loadWorkEntries(currentCustomerId);
                } else {
                    throw new Error(result.error || 'Fehler beim Löschen des Eintrags');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Fehler beim Löschen des Eintrags');
            }
        }

        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleString('de-DE', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric'
            });
        }

        function formatTime(dateString) {
            const date = new Date(dateString);  // UTC entfernt
            return date.toLocaleString('de-DE', {
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        // Schließe Modals beim Klick außerhalb
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(event) {
                if (event.target === this) {
                    hideModal();
                    hideEditModal();
                }
            });
        });

        document.getElementById('editForm').addEventListener('submit', async function(event) {
            event.preventDefault();
            const formData = new FormData(this);
            // Füge aktuelle URL-Parameter hinzu
            const urlParams = new URLSearchParams(window.location.search);
            formData.append('month', urlParams.get('month') || <?= $current_month ?>);
            formData.append('year', urlParams.get('year') || <?= $current_year ?>);
            
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json(); // Parse die JSON-Antwort
                
                if (result.success) {
                    hideEditModal();
                    await loadWorkEntries(currentCustomerId);
                } else {
                    throw new Error(result.error || 'Network response was not ok.');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Fehler beim Speichern der Änderungen');
            }
        });

        // Event-Handler für das Arbeitseinträge-Formular
        document.getElementById('workEntryForm').addEventListener('submit', async function(event) {
            event.preventDefault();
            const formData = new FormData(this);
            
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                if (response.ok) {
                    this.reset();
                    await loadWorkEntries(currentCustomerId);
                } else {
                    throw new Error('Network response was not ok.');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Fehler beim Speichern des Arbeitseintrags');
            }
        });
    </script>

    <footer class="fixed bottom-0 w-full text-center py-4 text-gray-600 bg-white border-t border-gray-200 z-10">
        Made with ❤️ by Andreas Koller
    </footer>

</body>
</html>