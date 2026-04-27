# 🕘 Changelog – PVWallboxManager

Alle Änderungen, Features & Fixes des Moduls werden hier dokumentiert.  
**Repository:** https://github.com/Sol-IoTiv/symcon-pv-wallbox-manager

## [1.4.5b] - 2026-04-27
### 🚀 Neu
- Schnellstart für `PVonly` und `PV2Car`
  - sofortiger Ladebeginn bei ausreichendem Überschuss
  - Start-Hysterese wird beim ersten Start übersprungen
  - automatische initiale Phasenwahl

### 🛠️ Änderungen
- `PV2Car`: direkter Start ohne Ramp-Up → schnellere Reaktion auf PV-Überschuss

### ⚙️ Optimierung
- API-Schreibzugriffe reduziert
  - Ladestrom nur bei Änderung
  - Tracking über `LastSentChargingCurrent`

### 🧾 Logging
- Einheitliches Format (`Kurztext | Detail`)
- Neue Log-Typen: `start`, `stop`
- Weniger Log-Spam durch konsequentes `debug`

### 🧹 Refactoring
- Ladeende-Logik zentralisiert und vereinfacht
- Hysterese- und Fallback-Handling gebündelt

### 🎨 UI
- Status-Info übersichtlicher strukturiert (Icons, Gruppierung, kompakter)

## [1.4.4b] - 2026-04-24
### 🧹 Refactoring
- Methodenstruktur in `module.php` neu sortiert und thematisch gruppiert
- Bereichs-Kommentare zur besseren Übersicht eingeführt
- Zentralen Konstantenblock für Lademodi, Phasenmodi, Cooldowns und Ladeende-Schwellwerte ergänzt
- Magic Numbers in zentralen Logikbereichen durch Konstanten ersetzt
- Mapping-Funktionen für Lademodi auf Konstanten umgestellt

### 🛠️ Fixes
- Spike-Filter verbessert: starke Lastabfälle (z. B. nach Abschalten der Wärmepumpe) werden nicht mehr blockiert → verhindert „festhängenden“ Hausverbrauch und ermöglicht korrektes Wiederanlaufen der PV-Ladung

### 🔧 Wartbarkeit
- Lesbarkeit und Orientierung im Code verbessert
- Grundlage für weitere Code-Verschlankung geschaffen
- Keine funktionalen Änderungen an der Lade- oder Phasenlogik

## [1.4.3b] - 2026-04-22
### ✨ Neu
- Konfigurierbarer Lademodus nach Fahrzeug-Abstecken (`ModeAfterUnplug`)

### 🔧 Verbesserung
- Einheitliches Verhalten bei Fahrzeug-Abstecken und Ladeende
- Lademodus wird nur noch bei tatsächlichem Statuswechsel geändert (kein zyklisches Zurücksetzen mehr)

### 🧠 Logik
- Zentrale Fallback-Logik für Lademodus eingeführt (`ApplyModeAfterDisconnectOrChargeEnd`)
- Ladeende verwendet nun die gleiche Logik wie Fahrzeug-Abstecken

### 🧹 Refactoring
- Entferntes hartes Zurücksetzen auf „Nur PV“
- Bereinigung der Lademodus-Logik in den Modus-Funktionen


## [1.4.2b] - 2026-04-21
### Update
- Clamp charging current to wallbox limits (16/32A)

### 🛠️ Fixes
- clampAmpere(): verhindert, dass MinAmpere über dem effektiven Wallbox-Maximalstrom liegt

## [1.4.1b] - 2026-04-20
### Refactor
- Legacy-Code und veraltete Hilfsfunktionen entfernt (`FahrzeugVerbunden`, `GetFrcText`, ungenutzte Methoden)
- Ladelogik überarbeitet und Lademodi vereinheitlicht (`pvonly`, `pv2car`, `manuell`)
- Saubere Trennung von Zustandsprüfung und Aktionen:
  - `isCarConnected()` → Statusprüfung
  - `handleNoCarConnected()` → Handling / Fallback
- `routeChargingMode()` neu aufgebaut → keine doppelte Logik mehr, klarer Ablauf
- Timer-Handling und Statusfluss vereinfacht und konsolidiert
- Kommentare bereinigt und Code insgesamt lesbarer gemacht

### Fixes
- Börsenpreis-Vorschau korrigiert → aktuelle Stunde wird jetzt korrekt angezeigt
- Fixed Phasenumschaltung (`Phasenmodus`) korrigiert (thx @brownson) https://github.com/brownson/symcon-pv-wallbox-manager/commit/b4c744119a09afd9a6d508878c35d885efda791c#diff-30c80afda9db4a50bf1f908b20eca6a8c7242c6db1b302cd1fcb9b8839035ca4L76
- Verwendung von `HausakkuSOCID` in der Überschussberechnung korrigiert (thx @brownson) https://github.com/brownson/symcon-pv-wallbox-manager/commit/602f479dc5597e4eef5def64677816b5ce254c29


## [1.4b] - 2025-08-06
- Börsenpreis: Grundpreis, Aufschlag und Steuersatz in den Properties hinzugefügt
- Börsenpreis wird anhand der Werte berechnet
- Börsenpreis-Vorscha (HTML) angepasst
- Ladezeit und Uhrzeit Ladeende wird berechnen und im WF in der Variable "⏳ Ladezeit" angezeigen (z.b.: 04h 22min -> 16:24 Uhr)
- Exponentialle Glättung: Der berechnete PV-Überschuss wird nun mit einem einstellbaren α-Wert (SmoothingAlpha) geglättet, um plötzliche Schwankungen zu dämpfen und eine stabilere Lade­strom­steuerung zu ermöglichen.
- Ramp-Rate-Limiting: Die Änderung des Lade­stroms pro Zyklus wird auf einen konfigurierbaren Maximalwert (MaxRampDeltaAmp derzeit 2A fix) begrenzt, sodass der Strom sanft von Minimum bis Maximum ansteigt und abrupte Sprünge vermieden werden.

## [1.3b] - 2025-07-29
### Hinzugefügt
- SOC-Werte (IST/ZIEL) werden jetzt in allen Lademodi berücksichtigt  
- Manuelles Vollladen startet sofort mit konfigurierter Stromstärke und Phasen (Hysterese nicht angewendet)

### Geändert
- Status-Anzeige komplett neu gestaltet  
- „Warte auf Fahrzeug“ umbenannt in „Fahrzeug verbunden / Bereit zum Laden“  
- Modul-deaktiviert-Zustand wird jetzt angezeigt  
- SOC IST/ZIEL vom Fahrzeug in der Status-Info angezeigt

### Behoben
- Anzeige- und Berechnungsfehler in der Status-Info korrigiert  
- Doppelte Berechnungen und redundante Logs entfernt  
- API-Befehle werden nur gesendet, wenn sich ein Wert tatsächlich ändert  
- Hysterese für Phasenumschaltung und Start/Stop vollständig implementiert
- PV-Überschuss < 250 W wird als 0 W angezeigt
- NoPowerCounter nach 3 aufeinanderfolgenden fehlenden Leistungswerten (unter 100 W) wir Ladeende angenommen

### Bereinigt
- Modulstruktur bereinigt und neu organisiert

## [1.2b] - 2025-07-28
- Bugfix "🏠 Hausverbrauch abzügl. Wallbox (W)" 0 Werte
- Modul über Visiu aktivieren / deaktivieren neuer Boolean Variable
- PV-Überschuss (W) und (A) wird jetzt immer berechnen
- Lademodi "Manuell Vollladen": Die Ladeleistung richtet sich nun nach den manuell eingestellten Phasen- und Ampere-Werten. 🔀 Phasen (manuell) & 🔌 Ampere (manuell)

## [1.1b] - 2025-07-25
- Börsenpreise wird zur vollen Stunde aktualisiert
- Börsenpreis-Vorschau +24h erweitert
- Hausansschluss (W) aktueller Wert wird im WF immer aktualisert angezeigt
- Wenn Auto SOC erreicht hat wird nach 6 Intervallen der Ladenodus auch beendet. Ist in der Instanzkonfig das Property Aktueller SOC und Ziel SOC gesetzt wird der Lademodus anhand der Werte beendet.
- Phasenmodus wird immer aktualisiert
- Stauts-Info Anzeige ~HTML Box für Webfront hinzugefügt (Lademodi, Phasensstatus, Status, Modus, PV2Car (%) werden angezeigt)
- 🏠 Hausverbrauch (W) und 🏠 Hausverbrauch abzügl. Wallbox (W) werden per Ereignis immer aktualisiert
- NEU: ☀️ und ⚡️ Icons für „PV-Überschuss (W/A)“ im WebFront
- NEU: 0A-Logik für Ladestrom (zeigt 0A, solange kein Überschuss)
- FIX: Hausverbrauch abzüglich Wallbox kann nicht mehr negativ werden
- OPTIMIERUNG: Glättung & Buffer für Hausverbrauch abzüglich Wallbox verbessert
- OPTIMIERUNG: Alle Werte im WebFront jetzt gerundet (keine Nachkommastellen-Flut)
- Diverse Berechnungen überarbeitet und WebFront-Anzeige bereinigt

## [1.0b] – 2025-07-13

### 🚀 Wichtige Neuerungen

- **KEIN IPSCoyote/GO-eCharger Modul mehr erforderlich!**
  - Direkte, native Anbindung an die GO-eCharger API (V3 & V4).
- **Komplette PV-Bilanz- und Hausverbrauchsberechnung jetzt direkt im Modul** (keine Hilfsskripte mehr nötig).
- **Intelligente Phasenerkennung:**  
  - Automatische Erkennung der tatsächlich genutzten Phasen (1/2/3), z. B. für Fahrzeuge, die nur zweiphasig laden können.
- **Vorbereitung Strompreis-Forecast-HTML-Box:**  
  - Moderne, vorbereitete Visualisierung für zukünftige Strompreisprognosen im WebFront integriert.
- **Exklusive Lademodi-Schaltung:**  
  - Es kann immer nur ein Modus gleichzeitig aktiv sein (Manuell, PV2Car, Nur PV).  
  - Alle Modi werden automatisch deaktiviert, wenn das Fahrzeug abgesteckt wird.
- **Status- und Diagnosevariablen für WebFront:**  
  - Bessere Übersicht, Logging und Fehlerdiagnose.
- **Logging & Robustheit verbessert:**  
  - Fehlerhandling, Initialisierung von Attributen, Self-Healing und präzise Protokollierung.

### ⚠️ Noch nicht enthalten/geplant (Roadmap):

- ⏰ Intelligente Zielzeitladung (PV-optimiert)
- 💶 Preisoptimiertes Laden (Beta)
- 🖼️ Strompreis-Forecast-HTML-Box als aktive Preissteuerung
- Automatische Testladung zur Erkennung der maximalen Fahrzeug-Ladeleistung
- Erweiterte Auswertung von externen Fahrzeugdaten (z. B. via MQTT/WeConnect)
- Geplantes Ladefenster-Logging
- Weitere Wallbox-Unterstützung

---

## 🐞 Bugfix und Update seit Version 0.9b

- **Update:**
  - alte Variablen „Zielzeitladung PV-optimiert“ und „Strompreis-Modus aktiv“ löschen !!!
  - Zielzeitladung komplett überarbeitet:
    - Anbindung der Awattar-API (AT/DE) zur automatischen Abfrage der aktuellen und zukünftigen Marktstrompreise.
    - Strompreis-basierte Ladeplanung und Visualisierung im WebFront integriert.
    - Unterstützung für Awattar Österreich (`api.awattar.at`) und Awattar Deutschland (`api.awattar.de`).
  - logging noch weiter ausgebaut
    - Im PV2Car-Modus wird jetzt im Log immer der eingestellte Prozentanteil und die daraus berechnete Ladeleistung fürs Auto angezeigt.
  - Beim Aktivieren des Moduls erfolgt jetzt sofort ein Initialdurchlauf der Ladelogik – das System reagiert damit sofort und wartet nicht mehr auf das nächste Intervall.
  - Bei Deaktivierung alles sauber stoppen, zurücksetzen, Timer aus.
  - Manueller Volllademodus nutzt jetzt konsequent die Property MaxAutoWatt (falls gesetzt). Ist kein Wert hinterlegt, wird die Ladeleistung automatisch anhand Phasen und Ampere berechnet.
  - Hausverbrach wird ab der Version 0.9.1b im Modul berechnet
  - Beim Modewechsel zu Fahrzeug Verbunden soll auch initial das Modul durchlaufen
  - KEINE Berechnung PV-Überschuss bei getrenntem Fahrzeug

- **Bugfix:**
  - StrompreisModus Boolean wurde nicht angelegt
  - Der aktuelle Lademodus („standard“, „manuell“, „pv2car“ oder „zielzeit“) wird nun als Variable gespeichert und bei jedem Moduswechsel korrekt gesetzt bzw. zurückgesetzt und berechnet.
  - Die Property für den PV2Car-Anteil (PVAnteilAuto) wird nun durchgehend verwendet, Namenskonflikte behoben.
  - Unnötige Fallback-Werte entfernt, konsistente Verwendung der Hilfsfunktion GetMaxLadeleistung() für maximale Ladeleistung implementiert.
  - Variablen-Initialisierung in der Hystereselogik. Alle Zustände sind jetzt robust gegen „Undefined variable“-Fehler, insbesondere beim Batterie-Prioritäts-Return.
  - Die Prio-Logik für PV-Batterie im Standardmodus setzt die Ladeleistung jetzt immer auf 0, ohne die Hystereselogik zu verlassen. Dadurch bleiben alle Status- und Lademodusmeldungen konsistent und Fehler werden vermieden.
  - Codebereinigung: Doppelten Prüf- und Abbruch-Block für „Kein Fahrzeug verbunden“ entfernt, damit Status und Steuerung immer eindeutig sind.
  - Wallbox wird nun bei fehlendem Fahrzeug immer zuverlässig auf Modus „Bereit“ (Standby) gestellt.
  - Statusanzeige: Lademodus-Status wird auch bei abgestecktem Fahrzeug korrekt aktualisiert.

---

## [0.9] – 2025-06-30

### 🚀 Neue Funktionen & Verbesserungen

- **PV-Batterieentladung:**
  -  Über die Instanzkonfiguration steuerbar (Boolean, Standard: aktiviert).
  -  Neu: Statusvariable **PV-Batterieentladung erlaubt**
  -  Im WebFront als Status sichtbar (nur lesbar, nicht schaltbar).
  - Synchronisation:  
    - Die Variable spiegelt stets den aktuellen Property-Status wider.
  - Hinweis:  
    → Die Freigabe der Batterieentladung kann so z. B. per Skript für einen Passivmodus automatisiert werden, bleibt aber ausschließlich über die Konfiguration änderbar.

- **Start- und Stop-Hysterese:**  
  Einstellbare Hysterese-Zyklen für das Starten und Stoppen der PV-Überschussladung. Erhöht die Stabilität bei schwankender PV-Leistung (z. B. Wolkendurchzug, Hausverbrauch).
  - Einstellungen komfortabel im WebFront mit Icons, kurzen Erklärungen, RowLayout.
  - Hysterese-Zähler und -Zustände werden für Nachvollziehbarkeit ins Debug-Log geschrieben.

- **Wallbox-Konfig-Panel:**  
  Komplettes Redesign des Konfigurationsbereichs für die Wallbox im WebFront:  
  - Klar strukturierte Darstellung per RowLayout, einheitliche Icons, praxisnahe Erklärungen.
  - Start-/Stop-Hysterese mit deutlicher Trennung und Kurzbeschreibungen.

- **Ladelogik & Statushandling:**  
  - Wallbox wird jetzt immer explizit auf „Bereit“ gesetzt, wenn kein PV-Überschuss vorhanden ist (verhindert Fehlermeldungen im Fahrzeug).
  - Lademodus, Fahrzeugstatus und Wallbox-Status werden nur noch bei Änderungen neu geschrieben.
  - Alle Aktionen (Modus/Leistung) werden nur bei echten Änderungen ausgeführt (keine unnötigen Schreibzugriffe, weniger Log-Spam).
 
- **Preisoptimiertes Laden (in Vorbereitung)**
  - Vorbereitung zur Integration mit dem Symcon-Strompreis-Modul ([Awattar, Tibber, …](https://github.com/symcon/Strompreis)) für automatisierte, zeit- und preisbasierte Ladeplanung (z.B. Laden bei günstigen Börsenstrompreisen).  
  - Interne Platzhalter und Properties für die kommende Preislogik angelegt.

- **Logging & Debug:**
  - Debug-Logging in der Instanzkonfig Modulsteuerung eingebaut
  - Modul aktivieren/deaktivieren in der Instanzkonfig Modulsteuerung eingebaut
  - PV-Überschussberechnung mit detailreichem Logging (PV, Hausverbrauch, Batterie, Netz, Ladeleistung, Puffer).
  - Hysterese-Zustände, Phasenumschaltung und Ladestatus werden jetzt nachvollziehbar mitprotokolliert.
  - Reduktion unnötiger/wiederholter Logeinträge.

- **Diverse Bugfixes & Cleanups:**  
  - Optimierte Fehlerbehandlung, robusteres Status- und Hysterese-Handling.
  - Properties, die nicht mehr benötigt werden entfernt.

---

**Hinweis:**  
Nach dem Update sollten die Modul-Properties (insbesondere IDs und Schwellenwerte) sowie die Wallbox-Konfiguration überprüft werden!

---

## [0.8] – 2025-06-25

🛠️ **Großes Refactoring & Aufräumen**
- Entfernen von alten und doppelten Funktionen ("Altlasten"), komplette Konsolidierung des Codes.
- Klare Trennung und Vereinfachung der Hauptfunktionen: PV-Überschussberechnung, Modus-Weiche, Zielzeitladung, Phasenumschaltung, Button-Logik, etc.
- Code vollständig modularisiert und für künftige Feature-Erweiterungen vorbereitet.

✨ **Verbesserte Logik & UX**
- Buttons im WebFront ("Manuell Vollladen", "PV2Car", "Zielzeitladung PV-optimiert") schließen sich jetzt zuverlässig gegenseitig aus.
- Reset-Logik der Buttons bei Trennung des Fahrzeugs optimiert.
- Buttons funktionieren nur, wenn ein Fahrzeug angeschlossen ist **oder** die Option "Nur laden, wenn Fahrzeug verbunden" deaktiviert ist (sichtbarer Hinweis empfohlen).
- Meldungen zu allen Status- und Umschaltaktionen verbessert.

📈 **PV-Überschuss-Formel überarbeitet**
- Formel im Modul und in der README vereinheitlicht:  
  `PV-Überschuss = PV-Erzeugung – Hausverbrauch – Batterieladung`
- Logging und Debug-Ausgaben bei Anwendung des dynamischen Puffers deutlich verbessert (inkl. Puffer-Faktor und berechnetem Wert).

🐞 **Bugfixes**
- Fehlerbehebung: "Modus 1/2 springt hin und her", wenn kein Fahrzeug angeschlossen ist.
- Diverse kleinere Korrekturen an Statusmeldungen und der Steuerlogik.

---

**Hinweis:**  
Nach Update bitte einmal alle Modul-Properties kontrollieren (vor allem Variable-IDs) und die Werte im WebFront prüfen!

---

## [0.7] – 2025-06-24
### 🚀 Highlights
- Zielzeitladung (PV-optimiert) ist jetzt verfügbar (Beta): Tagsüber PV-Überschuss, 4h vor Zielzeit Umschalten auf Vollladung.
- Vollständige Überarbeitung der PV-Überschussberechnung:  
  - Es werden keine negativen Werte mehr als PV-Überschuss geschrieben.
  - Logik: PV + Wallbox-Leistung – Hausverbrauch – (nur positive) Batterie-Leistung ± Netzeinspeisung.
- Phasenumschaltung über stabile Umschaltzähler (Hysterese) verfeinert.
- Dynamischer Pufferfaktor ersetzt statischen Puffer. Staffelung:  
  - <2000 W → 80 %  
  - <4000 W → 85 %  
  - <6000 W → 90 %  
  - ab 6000 W → 93 %
- Neu: Statusvariable und WebFront-Anzeige für aktuellen Lademodus.
- Alle Buttons (Manuell, PV2Car, Zielzeitladung) schließen sich jetzt gegenseitig aus.
- Modus-Status und PV-Überschuss werden bei Inaktivität zurückgesetzt.
- Unterstützung für PV2Car: Prozentsatz des Überschusses als Ladeleistung konfigurierbar.
- Automatische Deaktivierung aller Modi, wenn Fahrzeug getrennt.

### 🛠️ Fixes & interne Änderungen
- **Bugfix:** Negative Überschusswerte werden nicht mehr als Ladeleistung verwendet.
- **Bugfix:** PV-Überschuss-Variable zeigt immer >= 0 W.
- Fehlerhafte/unnötige Properties entfernt (z. B. MinAktivierungsWatt).
- PV-Überschuss wird jetzt ausschließlich über den aktuellen Betriebsmodus berechnet (keine doppelten Berechnungen).
- Modul-URL und Doku-Links auf `github.com/Sol-IoTiv` aktualisiert.
- Verbesserte Loggingausgaben für Debug & Nachvollziehbarkeit.
- Code-Optimierung und Cleanups (u. a. bessere Trennung von Modus/Status).
- Default-Werte und form.json-Beschreibungen für Start/Stop und Phasenumschaltung überarbeitet.

---

## [0.6] – 2025-06-18
### 🚗 Zielzeitladung (Beta)
- Einführung Zielzeitladung (SoC-basiert, Vorlaufzeit 4 h, nur PV oder mit Netz).
- Fahrzeug-SOC-Integration.
- Archiv-Variablen und Zielzeit-Vergleich.
- Fehlerbehandlung, wenn keine Zielwerte verfügbar.

---

## [0.5] – 2025-06-14
### 🧠 Fahrzeugdaten, Modus-Buttons & Logging
- SOC-basierte Ladeentscheidung (aktiver vs. Ziel-SoC).
- Buttons: Manuell, PV2Car, Zielzeitladung – gegenseitig exklusiv, mit Modus-Statusanzeige.
- Erweiterung Logging (Phasenumschaltung, Lademodus, SoC).
- Fehlerhafte Timer-Registrierung gefixt.

---

## [0.4] – 2025-06-10
### 🔁 Phasenumschaltung & Pufferlogik
- Dynamische Phasenumschaltung (Hysterese 3x unter/über Schwelle).
- Neuer „Dynamischer Puffer“ für stabilere Ladeleistungsregelung.
- Neue Properties für Phasenschwellen und Limit.
- Verbesserte Fehler- und Statuslogs.

---

## [0.3] – 2025-06-07
### 🏁 Start/Stop Schwellen, Logging
- Separate Properties für Start/Stopp-Leistung (Watt).
- Überschussberechnung mit Wallbox-Eigenverbrauch.
- Erweiterte Logik für Batterie (nur positive Werte).
- Erste Beta-Version an Tester verteilt.

---

## [0.2] – 2025-06-01
- Basisskript für PV-Überschussladung auf GO-eCharger portiert.
- Basis-Berechnung für Überschuss, Start/Stopp, Logging, Ladeleistungsregelung.
- WebFront-Integration, Variablen & Actions angelegt.

---

## [0.1] – 2025-05-27
- Initialer Import und Start der Entwicklung.
- Grundfunktionen für PV-Überschussberechnung und Ladeleistungssteuerung.
- Dokumentation und Roadmap angelegt.

---

© 2025 [Siegfried Pesendorfer](https://github.com/Sol-IoTiv) – Open Source für die Symcon-Community
