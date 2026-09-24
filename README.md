# PVWallboxManager

IP-Symcon-Modul zur Regelung eines go-eChargers über die lokale HTTP-API v2: PV-Überschussladen, PV-Anteil, Manuell, Hybrid und Begrenzung des gesamten Netzbezugs.

**Arbeitsstand 1.4.10b – unveröffentlicht.** Die Hardwareabnahme mit konkretem Modell, Firmware und Fahrzeug steht aus. 1.5.0 Stable folgt erst nach erfolgreicher Abnahme.

## Voraussetzungen

- Bibliotheksmetadaten deklarieren weiterhin Symcon ab 7.1. Diese Mindestversion benötigt Integrationstests; lokale Prüfungen verwenden PHP 8.3 mit simuliertem Symcon.
- go-eCharger mit lokaler HTTP-API v2 und Unterstützung für psm, frc, amp, alw und nrg. API v2 in der App aktivieren.
- Laut bisheriger Projektdokumentation wurde V4 verwendet, V3 war theoretisch kompatibel. Für diesen Entwurf liegt noch keine neue Hardwarefreigabe vor.
- Erreichbare Wallbox-IP über HTTP/Port 80; kein zusätzliches IPSCoyote-Modul erforderlich.
- Numerische PV-Erzeugung und gesamter Hausverbrauch **inklusive Wallbox**, optional Speicherleistung/SoC.
- Für aktives Netzlimit: regelmäßig aktualisierte Gesamt-Netzleistung inklusive Wallbox.

## Einstieg

1. Repository über die Symcon-Modulverwaltung einbinden: https://github.com/Sol-IoTiv/symcon-pv-wallbox-manager.
2. Für diesen Entwurf den Testbranch `codex/1.4.10b-stabilization` wählen. Er ist noch kein freigegebenes Beta- oder Stable-Release.
3. Instanz PVWallboxManager anlegen; Wallbox-IP, Energiewerte, Einheiten und Vorzeichen konfigurieren.
4. Hausverbrauch inklusive Wallbox eintragen. Die Wallboxleistung zieht das Modul selbst ab.
5. Stromgrenzen prüfen und Lademodus auswählen. Bei Netzlimit Messwertalter und Vorzeichen prüfen.
6. Bestehende Instanzen gemäß [Migration](docs/MIGRATION-1.4.10b.md) aktualisieren, nicht neu anlegen.

## Funktionen

| Modus | Verhalten |
|---|---|
| Nur PV | Überschussladen mit optionaler Hausakku-Startbedingung und Hysterese |
| PV-Anteil | Eingestellter Prozentsatz des Überschusses aus PV minus Hausverbrauch ohne Wallbox |
| Manuell | Benutzerdefinierte Ampere und Phasen innerhalb gemeinsamer Grenzen |
| Hybrid | PV-Regelung, Mindestladung bei abnehmendem Überschuss, optionale Endladung |

Netzbudget, Ziel-SoC, Aktivzustand und Fehlerzustände haben Vorrang. Details: [Lademodi](docs/CHARGING_MODES.md).

Ein zentraler Phasenablauf bestätigt erst den Ladestop, dann den gesetzten Phasenmodus. Standard: 180 s Mindestabstand, 60 s Timeout pro Schritt. Unbestätigte Wechsel sperren die Wiederfreigabe. Reset ist nur bei zurückgelesenem Stillstand möglich. Gemessene Fahrzeugphasen und eingestellter Modus bleiben getrennt; im Stillstand werden 0 genutzte Phasen angezeigt.

Strompreise von aWATTar AT/DE oder kompatibler Custom-API werden angezeigt. **Preisoptimierte Ladeplanung und Zielzeitladung sind nicht implementiert.** TargetTime bleibt ein Legacy-Platzhalter. Eine Restzeitschätzung anhand von SoC, Kapazität und aktueller Leistung ist vorhanden.

Die Netzbegrenzung verwendet Gesamtleistung und 230 V Rechenspannung je Phase. Sie ersetzt keine Sicherungen und keine Überwachung einzelner Außenleiter; Mess-/Kommunikationsverzögerungen bleiben möglich.

## Dokumentation

- [Konfiguration](docs/CONFIGURATION.md) und [vollständige Property-Referenz](docs/PROPERTY-REFERENCE.md)
- [Lademodi](docs/CHARGING_MODES.md), [Phasensteuerung](docs/PHASE_SWITCHING.md)
- [Fehlerdiagnose](docs/TROUBLESHOOTING.md), [Migration](docs/MIGRATION-1.4.10b.md)
- [Tests und Hardwareabnahme](docs/TESTING.md), [Versionierung](docs/RELEASING.md)
- [Changelog](CHANGELOG.md), [Ideen](FEATURES.md)

Tests: `php tests/regression.php`, `php tests/charging.php`, `php tests/metadata.php`. Keine reale Wallbox wird angesprochen.

Projekt von [Sol-IoTiv](https://github.com/Sol-IoTiv), [MIT-Lizenz](LICENSE.md).
