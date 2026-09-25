# Lokales Prüfergebnis – Entwurf 1.4.10b

Datum: 23.09.2026. Ausgangscommit: e9337aa71d691a199bf68acf6a82634b0dfb0fae (beta-staging).
Arbeitsbranch: codex/1.4.10b-stabilization. Laufzeit: PHP 8.3.31 CLI, Windows.

| Prüfung | Ergebnis |
|---|---|
| PHP-Lint module.php | bestanden |
| PHP-Lint ChargingControl.php | bestanden |
| tests/regression.php | 3 Szenarien bestanden |
| tests/charging.php | 36 Szenarien bestanden |
| tests/metadata.php | Versionen, Formular-Properties und Property-Referenz bestanden |
| Git-Diff-Whitespaceprüfung mit Berücksichtigung bestehender CRLF-Dateien | bestanden |

Die drei anfänglichen Regressionen (Phasenanzeige im Stillstand, fehlender Netzzähler, festhängender Hauslastfilter) wurden zuerst am alten Stand ausgeführt und schlugen dort wie erwartet fehl. Nach Änderung bestehen sie.

Die simulierten Tests decken unter anderem alle vier Lademodi, Netzbezug/Einspeisung, API-Ablehnungen, Stop-/Phasenbestätigung, Timeout, persistente Übergänge, Semaphorfreigabe und Deaktivierung während eines Befehls ab. Details und reproduzierbare Befehle stehen in TESTING.md.

Nicht ausgeführt: tatsächlicher Symcon-Kernel, echte parallele Prozesse, Installation/Upgrade einer realen Instanz, GitHub-CI sowie Hardwaretests. Die Ergebnisse sind keine Stable-Freigabe. Modell, Firmware, Fahrzeug und Hardwareprotokoll fehlen noch.

Nachtrag 24.09.2026: Als Testaufbau wurden go-eCharger V4 mit 11 kW und VW ID.3 Pure genannt. Firmware und Hardwareprotokoll stehen weiterhin aus; die zusätzliche Angabe „55 kW“ ist hinsichtlich der Akkukapazität noch zu klären. Die obigen Testergebnisse bleiben reine Simulationsergebnisse.

Weitere Präzisierung am 24.09.2026: Nutzer bestätigt go-e-Firmware Beta 60.6, IP-Symcon 9.0 und 55 kWh Akkukapazität. Ob 55 kWh Brutto- oder Nutzkapazität sind, ist noch nicht bestätigt. Die Hardwareabnahme ist weiterhin offen; aus den Versionsangaben folgt keine geprüfte Kompatibilität.

Statusprüfung am 24.09.2026: Zehn nicht identifizierende Steuer-/Messfelder aus der Nutzerantwort als Fixture übernommen. Der Gesamttext enthielt einen JSON-Syntaxfehler beim ausgeschlossenen Feld eto. Die übernommenen Feldtypen werden vom Modul akzeptiert; var=11/ama=16 ergeben 16 A. car=1, Leistung/Ströme=0, psm=2 und frc=0 beschreiben einen Zustand ohne Fahrzeug mit neutraler Freigabe, keinen bestätigten Stop. Zusätzlicher Regressionstest bestanden; tests/charging.php enthält nun 37 Szenarien. Keine Hardwarebefehle wurden gesendet.

## Nachprüfung am 24.09.2026 nach den Nutzertests

60 Szenarien in tests/charging.php und 3 in tests/regression.php bestanden (63 insgesamt); einzelne Szenarien prüfen mehrere Modi und Fehlerbedingungen. PHP-Syntax aller sieben PHP-Dateien sowie Metadaten/Formularprüfung bestanden. Neue Fehler wurden vor der Korrektur mit fehlschlagenden Regressionstests reproduziert.

Prüfumfang und verbleibende Grenzen: [REVIEW-1.4.10b.md](REVIEW-1.4.10b.md). Nutzer meldet verbessertes Verhalten der automatischen Phasenerkennung; das ersetzt keine protokollierte Hardwareabnahme aller Modi. Historische Ergebnisse oben bleiben unverändert. GitHub-CI wurde in dieser Nachprüfung nicht verifiziert.

Forenbericht PV-Anteil geprüft: Weder der ursprüngliche beta-staging-Stand e9337aa noch der aktuelle Handler enthält eine Hausakku-SoC-Abschaltung oder automatische 100-%-Anhebung im PV-Anteil-Modus. Zwei zusätzliche Tests bestanden: 70 % bleiben über den Hausakku-SoC-Wechsel 92/93/100 unverändert; 1500 W Überschuss können bei 70 % unter der Stoppschwelle liegen, während Nur PV starten kann. Nun 62 Ladeverhaltenstests plus 3 Basisregressionen = 65. Die Ursache des konkreten Forenfalls bleibt ohne Versionsstand, Konfiguration und zeitgleiche Messwerte unbewiesen.

Umsetzung des gewünschten PV-Anteil-Verhaltens: Ab erreichtem Hausakku-Ziel automatisch 100 %, unterhalb Rückkehr zum gespeicherten Anteil. Die beiden vorherigen Forenfalltests wurden an das neue Sollverhalten angepasst; zwei Tests für 0 %, ungültigen/fehlenden Hausakku-SoC und Netzlimit ergänzt. Nun 64 Ladeverhaltenstests plus 3 Basisregressionen = 67; alle bestanden. Syntax, Formular und Metadaten ebenfalls geprüft. Hardwareabnahme der neuen Umschaltung offen.

Nachtrag 25.09.2026: Nutzerlog zeigt PV-Anteil-Freigabe um 09:53:16 und irrtümliches Ladeende nach drei 0-W-Abfragen um 09:54:04, anschließend Wechsel nach Manuell. Regression vor Korrektur reproduziert. Ladeende setzt nun gemessene Ladung seit letztem Stop und car=4 voraus. Vier zusätzliche Tests für Startwartezeit, echtes Ladeende, vorübergehend geringe Leistung und Stop-Reset bestanden. Gesamt: 68 Ladeverhaltenstests plus 3 Basisregressionen = 71. Die Ursache der ausbleibenden Fahrzeug-Leistungsaufnahme ist durch dieses Log nicht belegt; zusätzliche Debug-Rückmeldung dient der Diagnose.

Nachtrag Startplanung 25.09.2026: Dump zeigt 2599 W Ladung um 10:00:29, Stop um 10:00:31 und bestätigten Wechsel auf 1P um 10:00:38. Vorzeitige Freigabe bei noch ausstehender Phasenhysterese reproduziert und korrigiert. Fünf neue Tests: Start wartet in PV/PV-Anteil/Hybrid, bereits freigegebenes wartendes Fahrzeug, Cooldown, vollständige Startfolge mit Rücklesebestätigung in beiden Richtungen und unveränderte Hysterese bei laufender Ladung. 73 Ladeverhaltenstests plus 3 Basisregressionen = 76 bestanden; Syntax/Metadaten bestanden. Der konkrete Wechselgrund im alten Dump ist nicht eindeutig protokolliert. Hausverbrauch 2430 W bei Wallbox 2599 W deutet auf Zeitversatz oder unpassende Quelle; Messwertalter und Plausibilitätswarnung ergänzen die Diagnose. Keine automatische Korrektur der unbekannten Messquelle.
