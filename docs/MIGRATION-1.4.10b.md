# Migration 1.4.9b → Entwurf 1.4.10b

Vor Betatest Symcon-Konfiguration und bisherigen Modulstand sichern. Bestehende Instanz aktualisieren, nicht neu anlegen. GUIDs, bestehende Properties und Variablen-IDs bleiben erhalten. Keine Archive werden gelöscht.

Neue Defaults: PhaseSwitchCooldown=180, PhaseSwitchTimeout=60, InvertNetzleistung=false, GridMeasurementMaxAge=120. Bestehende Netzlimit-Laufzeitvariablen werden nicht durch Formular-Startwerte überschrieben.

## Verhaltensänderungen

- Start-Hysterese gilt für jeden PV-/PV-Anteil-Start.
- Phasenwechsel beinhalten bestätigte Ladepausen und mehrere Statuszyklen.
- Ladeende erzwingt keinen Wechsel auf 1P.
- Ungültige/veraltete Netzmessung sperrt bei aktivem Limit.
- Deaktivieren fordert Stop an; Bedienaktionen können dann nicht freigeben.
- Gemessene Fahrzeugphasen sind bei Stillstand 0. Externe Berechnungen gegen Division durch null absichern.
- InitialCheckInterval=0 deaktiviert tatsächlich Polling ohne Fahrzeug.
- Neue Variable MarketPricesValid: ein erhaltener alter Preis kann ungültig sein.

## Öffentliche Funktionen

Namen bleiben erhalten, zulässige Aktionen sind enger gefasst:

- SetChargingCurrent: 6–32 A nur im manuellen Modus als Wunsch übernehmen; gemeinsame Regelung ausführen.
- SetPhaseMode: 1/2 nur im manuellen Modus als Wunsch übernehmen. Annahme ist keine synchrone Umschaltbestätigung; Auto=0 wird abgelehnt.
- SetForceState: 1 fordert Stop an, 2 bewertet den aktuellen Modus neu; Neutral=0 wird abgelehnt. Ein einmaliger Stop deaktiviert die automatische Regelung nicht dauerhaft.
- SetChargingEnabled delegiert an diese Regelung; keine direkte alw-Schreiboperation.
- ResetPhaseFault quittiert ausschließlich bei bestätigt stillstehender Wallbox.

In Symcon tragen diese Funktionen wie bisher das Präfix PVWM_. Skripte mit bisherigen Rohbefehlen unabhängig vom Modus anpassen. TargetTime und WallboxAPIKey bleiben als Legacy-Konfiguration erhalten, ohne aktive Zielzeit-/API-Key-Funktion.

## Rückweg und Historisierung

Vor Downgrade Ladung stoppen und ausstehenden Phasenwechsel klären. 1.4.9b besitzt die neuen Absicherungen nicht. Neu hinzugefügte Diagnosevariablen müssen nicht gelöscht werden.

Vorhandene Archive bleiben erhalten. Zusätzliche Betriebsanalyse (Leistung, Netzbezug, Modus, gemessene Phasen) kann über Symcons Archiv eingerichtet werden. Dieser Entwurf aktiviert keine automatische Archivierung und ändert keine Aufbewahrungsregeln.
