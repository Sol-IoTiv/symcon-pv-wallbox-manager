# Tests und Abnahme

## Automatisierte Tests

Im Repository-Verzeichnis:

```sh
php -l PVWallboxManager/module.php
php -l PVWallboxManager/ChargingControl.php
php tests/regression.php
php tests/charging.php
php tests/metadata.php
```

In-Memory-Symcon und kontrollierte HTTP-Antworten; keine reale Wallbox. PHP-Warnungen werden zu Fehlern. Geprüft werden Befehlsreihenfolge, Rücklesebestätigung, Timeouts, Semaphorfreigabe, deaktiviertes Modul, vier Modi, Netzbudget/Vorzeichen/Messwertalter, Hardwaregrenzen, Preisantworten und persistente Übergänge.

Der Adapter ersetzt weder Symcon-Kernel noch echte gleichzeitige Prozesse. Reale Semaphore, Timer, Installation und Upgrade sind separat zu prüfen. CI ist für PHP 8.2/8.3 vorbereitet; lokale Erfolge bedeuten keinen bereits ausgeführten GitHub-Workflow.

## Offene Hardwareabnahme

| Feld / Versuch | Ergebnis |
|---|---|
| Commit/Version | einzutragen |
| Symcon-Version/Plattform | IP-Symcon 9.0 (Nutzerangabe 24.09.2026); Plattform offen |
| go-e-Modell/Firmware | go-eCharger V4, 11 kW; Firmware Beta 60.6 (Nutzerangabe 24.09.2026) |
| Fahrzeug/Software | VW ID.3 Pure (Nutzerangabe 24.09.2026); Modelljahr und Software offen |
| Akkukapazität | 55 kWh vom Nutzer bestätigt; Brutto-/Nettobezug noch offen |
| Messquelle, Einheit, Vorzeichen, Aktualisierung | einzutragen |
| 1P → 3P / 3P → 1P mit Zeitverlauf | offen |
| Vier Lademodi und Hybrid-Endladung | offen |
| Netzlimit, Lastsprung, Zählerausfall | offen |
| Netzwerkverlust während Stop/Bestätigung | offen |
| Abstecken, Ziel-SoC, Deaktivierung im Übergang | offen |
| Neustart/ApplyChanges im Übergang | offen |
| Zwei echte überlappende Symcon-Aufrufe | offen |
| Upgrade mit erhaltenen IDs/Einstellungen/Archiven | offen |
| Dauerbetrieb bei wechselndem Überschuss | offen |

Je Versuch Zeitstempel, Soll-/Ist-Modus, frc/alw, Leistung und Ströme protokollieren. Veröffentlichung/Freigabe erfordert dokumentierte Abnahme.

Die Hardwareangaben identifizieren den vorgesehenen Testaufbau; sie sind noch kein erfolgreicher Praxistest. CarBatteryCapacity erst mit bestätigter nutzbarer Kapazität in kWh setzen. Aus der Modellbezeichnung wird keine unterstützte Fahrzeug-Phasenanzahl abgeleitet; diese ist anhand der Statuswerte im Test festzustellen.
