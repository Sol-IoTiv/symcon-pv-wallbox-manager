# Fehlerdiagnose

| Zustand | Prüfung |
|---|---|
| Netzvariable fehlt | Zuweisung und Existenz der NetzleistungID |
| Netzmesswert ungültig/veraltet | Numerischer Typ, VariableUpdated, Aktualisierungsrate, Maximalalter |
| Kein Ladebudget | Bezug inklusive Wallbox, Vorzeichen, Einheit und Limit |
| Phasensteuerung gesperrt | Befehlsergebnis, Stillstand, psm, Firmware und Timeout |
| Cooldown | Letzter Wechsel; bei unzureichendem Budget bleibt die Ladung gestoppt |
| Wallbox nicht erreichbar | IP, Netzwerk, lokale API v2; tatsächlichen Ladezustand an Wallbox prüfen |
| Strompreise ungültig | Antwortschema, Zeitintervall, Anbieter und Erreichbarkeit |

Phasenfehler erst nach Ursachenbehebung quittieren. Bei fehlendem Stillstand wird Reset abgelehnt. Ein Reset startet nicht unmittelbar.

Eine nicht bestätigte Ladefreigabe (`frc=2`) wird getrennt vom Phasenfehler behandelt. Nach einem bestätigten Stopbefehl sind maximal drei Freigabeversuche insgesamt erlaubt, jeweils frühestens nach 30 Sekunden und mit neu geprüftem Status, Ladeplan, SoC und Netzbudget. Danach ist der Formularbutton „Phasen-/Ladefreigabefehler nach Stillstand zurücksetzen“ erforderlich. Der Zähler und die Wartefrist bleiben über Neustarts erhalten. Unbestätigte Stop- oder Phasenbefehle sperren weiterhin dauerhaft. Ein Reset protokolliert Erfolg oder Ablehnung; er setzt auch den Freigabezähler zurück und verlangt bestätigten Stillstand.

„Wallbox-Befehl nicht bestätigt“ enthält HTTP-Code, Transportfehler, JSON-Fehler und das Bestätigungsfeld des angeforderten Befehls. Eine fehlende Bestätigung beweist keine Ablehnung durch die Wallbox. Vollständige Antworten und Zugangsdaten werden nicht protokolliert.

Fehlerberichte: Commit/Version, Symcon-Version, go-e-Modell/Firmware, Fahrzeug, Modus, erwartetes/tatsächliches Verhalten und frc/psm/alw/car/amp/nrg mit Zeitstempeln nennen. DebugLogging gezielt aktivieren. Persönliche Daten und Zugangsdaten vor Weitergabe entfernen.

Statusanzeigen beruhen auf Rückmeldungen. Eine angenommene Anforderung ist keine bestätigte physische Ladung.
