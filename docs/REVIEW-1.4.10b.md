# Nachprüfung 1.4.10b – 24.09.2026

Geprüft: module.php, ChargingControl.php, Konfigurationsformular, Timer/Bedienaktionen, Ladeende/SoC, Statusanzeige, Messwertvalidierung, Wallboxtransport, Netzbudget, automatische Phasenerkennung, Strompreise und vorhandene Regressionstests. Prüfung durch Quelltextdurchsicht und lokale Simulation mit PHP 8.3.31; keine Stable-Freigabe.

## Behobene Befunde

| Bereich | Reproduzierbarer Fehler | Korrektur |
|---|---|---|
| PV-Anteil | 8000 W Überschuss bei 25 % konnten eine 3P-Anforderung auslösen, obwohl nur 2000 W zugeteilt sind. | Phasenwahl verwendet den Anteil. |
| PV-Anteil | 0 % konnte während langer Stop-Hysterese weiterladen. | Explizite 0 % stoppen unmittelbar. |
| Nur PV | Null Überschuss umging die Stop-Hysterese durch einen Stromwunsch von 0 A. | Mindeststrom bis zum regulären Stop; Netz-/SoC-Sperren haben Vorrang. |
| Hybrid | Der Text „Hybrid-Laden aktiv“ konnte einen fehlenden Netzzähler als Sperrgrund verdecken. | Konkreten Sperrgrund erhalten. |
| Moduswechsel | Glättung und Hybrid-Endladezeit des vorherigen Modus blieben bestehen. | Regelhistorie zurücksetzen. |
| Hausakku | Eine konfigurierte, nicht mehr existente SoC-Variable galt als erfüllte Startbedingung. | PV/Hybrid sperren und Grund anzeigen. |
| Fahrzeug-SoC | Text wie „offline“ konnte die Restzeitberechnung mit einem PHP-Fehler abbrechen. | Typ, Endlichkeit und Bereich prüfen; bei ungültiger zugeordneter Quelle Stop, Restzeit n/a. |
| Energiequellen | Numerische Strings bestanden die Prüfung vor einem typisierten Float-Zugriff. | Nur Integer-/Float-Variablen zulassen, numerischen Wert generisch lesen. |
| Strompreise | Numerische Strings mit unendlichem Wert wurden akzeptiert. | Endlichkeit prüfen. |

## Modusübergreifende Prüfungen

Für Nur PV, PV-Anteil, Manuell und Hybrid: Stop bei ausgeschöpftem Netzbudget, erreichtem Ziel-SoC, Deaktivierung und Kommunikationsausfall. Automatische Zweiphasenerkennung und Strombudget in allen vier Modi. Zusätzlich: PV-Start-/Stop-Hysterese, Hausakku-Startbedingung, Hybrid-Mindestladung und verzögerte Endladung, 1P/3P-Übergänge, Cooldown, Rücklesebestätigung, API-Ablehnungen und simulierte Sperren.

63 Verhaltenstests bestanden. Syntax- und Metadatenprüfungen bestanden. Mocks führen keine echten parallelen Symcon-Prozesse aus und beweisen weder reale Schützstellungen noch das Zeitverhalten von Firmware oder Fahrzeug.

## Verbleibende Grenzen vor Stable

- Hardwarematrix für Nur PV, PV-Anteil und Hybrid einschließlich Wechsel unter schwankender PV und Hauslast protokollieren. Das positive Nutzerfeedback betrifft bisher keine vollständige Abnahme.
- Automatische Phasenerkennung beobachtet tatsächliche Nutzung, nicht eine garantierte Fahrzeug-Maximalfähigkeit. Änderung erst beim nächsten Statusabruf erkennbar; drei belastbare Messungen sind zum Absenken der Rechenphasen erforderlich. Pausen/Fehler verwerfen die Erkennung.
- Solange das Fahrzeug noch nicht im 3P-Wallboxmodus stabil lädt, wird konservativ mit drei Phasen gerechnet. Bei knappem Budget kann es deshalb zunächst bei 1P bleiben. Kein ungesicherter Teststart oberhalb des Budgets.
- Leistungsumrechnung verwendet nominal 230 V; Zählermessungen und Wallboxstatus werden zeitlich versetzt gelesen. Das Netzlimit ist eine zyklische Regelung und garantiert keine überschreitungsfreie Momentanleistung.
- PV-Modi erlauben durch Mindeststrom, Hysterese, Rundung und Glättung zeitweise Netzbezug. PV-Anteil bezeichnet einen Anteil des Überschusses, keinen garantierten prozentualen PV-Anteil an der Fahrzeugenergie.
- Konfigurierter Fahrzeug-SoC muss zum angeschlossenen Fahrzeug passen. Die automatische Phasenerkennung ordnet keine fremden Fahrzeug-SoC-Quellen zu.
- 0 W Netzlimit bedeutet weiterhin deaktivierte Begrenzung. Zielzeit-/Strompreis-Ladeplanung ist nicht implementiert.
- GitHub-CI, echte Parallelaufrufe unter Symcon 9 und Wiederanlauf nach Host-Neustart zusätzlich prüfen. Tests lokal unter PHP 8.3.31 sind kein Nachweis für alle unterstützten Laufzeitversionen.
