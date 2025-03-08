# PowerShell-Skript zum Erstellen einer Aufgabe, die monatlich letzten Tag um 23:55 Uhr Uhr ausgeführt wird
# ##############################################################################################################
# # WICHTIG!: ändere manuell in der Aufgabenplanung den Trigger auf monatlich: Monate: "alle" & Tage: "Letzer" #
# # --> Das Cmdlet New-ScheduledTaskTrigger unterstützt keine monatliche Triggerkonfiguration....              #
# ##############################################################################################################

# Name der Aufgabe
$TaskName = "(wartungsliste) - Send monthly Status Mail"

# Pfad zum Python-Skript
$PythonScriptPath = "C:\inetpub\wwwroot\wartungsliste\processing\mail-report\mail-report.py"

# Arbeitsverzeichnis
$WorkingDirectory = "C:\inetpub\wwwroot\wartungsliste\processing\mail-report\"

# Befehl zum Ausführen (Python-Interpreter und Skript)
# Vollständiger Pfad zum Python-Interpreter
$PythonExe = "C:\Users\Administrator.PHD\AppData\Local\Programs\Python\Python313\python.exe"
$Action = New-ScheduledTaskAction -Execute $PythonExe -Argument $PythonScriptPath -WorkingDirectory $WorkingDirectory

# Trigger erstellen: täglich um 23:55 Uhr
$Trigger = New-ScheduledTaskTrigger -Daily -At "23:55"

# Einstellungen für den Aufgabenausführer
# SYSTEM-Konto verwenden, damit die Aufgabe ohne Benutzeranmeldung läuft
$Principal = New-ScheduledTaskPrincipal -UserId "SYSTEM" -LogonType ServiceAccount -RunLevel Highest

# Weitere Einstellungen
$Settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -ExecutionTimeLimit (New-TimeSpan -Minutes 5)

# Aufgabe registrieren
Register-ScheduledTask -TaskName $TaskName -Trigger $Trigger -Action $Action -Principal $Principal -Settings $Settings -Force

Write-Host "Die Aufgabe '$TaskName' wurde erfolgreich erstellt und konfiguriert für monatliche Ausführung am 31. Tag jeden Monats."