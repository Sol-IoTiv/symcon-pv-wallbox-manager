# 🕘 Changelog – PVWallboxManager

Alle Änderungen, Features & Fixes des Moduls werden hier dokumentiert.  
**Repository:** https://github.com/Sol-IoTiv/symcon-pv-wallbox-manager

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
  - >6000 W → 93 %
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
