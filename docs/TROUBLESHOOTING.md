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

Fehlerberichte: Commit/Version, Symcon-Version, go-e-Modell/Firmware, Fahrzeug, Modus, erwartetes/tatsächliches Verhalten und frc/psm/alw/car/amp/nrg mit Zeitstempeln nennen. DebugLogging gezielt aktivieren. Persönliche Daten und Zugangsdaten vor Weitergabe entfernen.

Statusanzeigen beruhen auf Rückmeldungen. Eine angenommene Anforderung ist keine bestätigte physische Ladung.
