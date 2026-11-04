# Stop-Wiederherstellung nach Freigabe-Timeout

Entwicklungsstand auf dev, nicht veröffentlicht. Anlass: Nutzerlogs vom 17.10. und 28.10.2026 zeigen Freigabe-Timeout (5 s), anschließenden Verbindungs-Timeout beim Stop (3 s) und sofortige Dauersperre bereits bei Versuch 1/3. Der Grund des Netzwerkausfalls ist damit nicht ermittelt.

## Ablauf

Nach fehlender Stop-Bestätigung wird ein neuer Status abgerufen; der vor dem Freigabebefehl gelesene Status genügt nicht. Bestätigt er Stillstand, bleiben die bisherigen begrenzten Freigabeversuche erhalten. Bei Transportausfall des Stops und unbestätigtem Stillstand beginnt ein persistent gespeicherter Wiederherstellungszustand. Explizite Stop-Ablehnungen werden nicht so behandelt.

Währenddessen sind ausschließlich Stop-Befehle erlaubt, keine Stromvorgaben, Phasenwechsel oder Freigaben. Maximal drei Stop-/Statusprüfungen im Abstand von mindestens 30 Sekunden, feste Frist 120 Sekunden. Zwei aufeinanderfolgende frische Statusabfragen müssen frc=1, alw=false, keinen Ladezustand, höchstens 30 W und je Phase höchstens 0,2 A zeigen. Wallboxfehler verhindern die Wiederherstellung. Unterbrochene Bestätigungen zählen nicht zusammen.

Nach Erfolg erfolgt keine Freigabe im selben Zyklus. Weitere 30 Sekunden Wartezeit, danach vollständige Neubewertung von Betriebsmodus, Verbindung, SoC, Aktivierung, Netzbudget und Phasenwahl. Der Zähler von maximal drei unbestätigten Freigabeversuchen bleibt erhalten. Nach Frist-/Versuchsablauf oder echter Ablehnung bleibt eine quittierungspflichtige Sperre. Bestehende Phasenfehler werden niemals automatisch gelöscht.

Zustand, Frist, nächste Prüfung und Versuchszähler sind Instanzattribute. Auch der normale UpdateStatus-Pfad bearbeitet die Wiederherstellung vor Modusauswahl und den frühen Rücksprüngen bei Nichterreichbarkeit oder Deaktivierung. Die Wiederherstellung nutzt die vorhandenen Statusaufrufe; sie startet keinen Hintergrundprozess und garantiert keinen erfolgreichen Stop bei ausgefallener Verbindung.

## Prüfung am 03.11.2026

99 Ladeverhaltenstests und 3 Basisregressionen bestanden (102 insgesamt), zusätzlich Metadaten/Formular/Property-Referenz, PHP-Syntax der geänderten PHP-Dateien und Whitespaceprüfung. Neue Tests simulieren den dokumentierten Transportausfall, verzögerte Wiederkehr, tatsächliche Ladung trotz verlorener Antwort, ungültige/fehlende Statusdaten, explizite Ablehnung, Wallboxfehler, Frist-/Versuchsablauf, erhaltenen Zustand bei Objektrekonstruktion, getrennte Stillstandsbestätigungen, drei erschöpfte Freigabeversuche sowie Aktivierung/SoC/Netzbudget vor Wiederfreigabe.

Testbefehle: php tests/charging.php; php tests/regression.php; php tests/metadata.php. Tests simulieren Zeit und HTTP/Symcon; keine realen Hardwarebefehle, keine echte Kernel-/Neustartabnahme. Keine neue Versionsnummer, kein Tag, kein Release und keine Store-Einreichung.
