# 🌟 Feature- und Ideenliste für PVWallboxManager

Hier werden geplante Features gesammelt, Community-Wünsche, Ideen und größere ToDos für die Weiterentwicklung des Moduls.

**Pull Requests, Kommentare und Vorschläge sind willkommen!**

---

## 🚗 Lademodi

- [x] **🌤️Hybrid-Laden-Modus**
      Immer Mindestleistung laden (z.B. 6A), PV-Überschuss wird aufaddiert (wie bei evcc).
      **Status:** Implementiert; Verhalten siehe docs/CHARGING_MODES.md

- [x] **Begrenzung des gesamten Netzbezugs (W)**
      Die Wallbox regelt die Leistung dynamisch herunter, falls das Haus (inkl. aller Verbraucher) den maximalen Netzanschluss (z.B. 35A/7kW) zu überschreiten droht.
      **Status:** Implementiert; Messwertprüfung und gemeinsamer Phasenablauf in 1.4.10b vorbereitet
      **Hintergrund:** Die Software begrenzt den gemessenen Gesamtbezug. Sie ersetzt keine Sicherungen oder Überwachung einzelner Außenleiter.
      **Beispiel:** Haus braucht 6kW, dann bleiben nur noch 1kW (1-phasig) für die Wallbox übrig.

- [ ] **Weitere Lademodi und Features**
    - [ ] Zeitgesteuertes Laden (z.B. Zielzeit, günstige Börsenzeiten) Errechnete Zeitfenster in der Anzeige-Info anzeigen
    - [x] PV2Car mit Prozentsteuerung -> **Umgesetzt in: v1.1b**
    - [x] neuer Lademodi (Laden Manuell steuern) -> Start/Stop, Amperevorgabe, Phasenvorgabe

---

## 💡 Ideen

- [ ] Ladestatistik als HTMLBox
- [ ] Chart-Darstellung für Ladevorgänge
- [ ] Testladung um max Ladeleistung zu bestimmen
- [ ] Forecast Sonnenstunden
- [ ] Forecast Kalender Ort Tremin
- [ ] Pushbenachrichtigung am Handy beim Fahrzeug Connect -> Auswahl Lademodi
- [ ] Sommer- / Wintermodus notwendig? wie verhält sich das Modul im Sommer vs Übergangszeit vs Winter
- [ ] Strompreis Tibber erweitern
- [x] Strompreis: Grundpreis, Aufschlag, Steuersatz -> **Umgesetzt in: v1.4b**
- [x] Ladezeit Berechnen anhand Auto Batteriekapazität, IST SOC, Ziel SOC und aktueller Ladeleistung -> **Umgesetzt in: v1.4b**
- [x] Uhrzeit Ladeende errechnen und in WF ausgeben -> **Umgesetzt in: v1.4b**
- [ ] Überlegung Modul für mehrere Fahrzeug erweitern

---
## 🛠️ Technische Verbesserungen

- [x] Konfigurierbare Hysterese und Phasenumschaltung -> **Umgesetzt in: v1.0b**
- [x] Mehr Visualisierung/Logging im WebFront -> **Umgesetzt in: v1.3b**
- [ ] Automatisches Reset nach Stromausfall
- [x] PV-Überschuss immer berechnen -> **Umgesetzt in: v1.3b**
- [x] Modul über die Visu abschalten (Button) -> **Umgesetzt in: v1.2b**
- [x] Bug 🏠 Hausverbrauch abzügl. Wallbox (W) wird beim Modulintervall 0 W berechnet -> **Umgesetzt in: v1.2b**
- [x] Börsenpreise sollen zur vollen Stunde aktualisiert werden -> **Umgesetzt in: v1.1b**
- [x] Wenn Auto SOC erreicht hat soll der Ladenodus auch beendet werden. Derzeit Versucht das Modul verzweifelt zu laden. -> **Umgesetzt in: v1.1b**
- [x] Status-Ino Anzige im Webfront (Lademodi, Phasensstatus, Status, Modus) -> **Umgesetzt in: v1.1b**
- [x] Status-Ino Anzige im Webfront erweitern um PV-Antiel in (%) -> **Umgesetzt in: v1.1b**
- [x] Statusanzeige "Warte auf Fahrzeug" umbenannt in "Fahrzeug verbunden / Bereit zum Laden" -> **Umgesetzt in: v1.3b**
