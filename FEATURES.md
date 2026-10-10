# Offene Ideen

Diese Liste enthält ausschließlich offene Vorschläge, keine Zusagen für ein Release. Bereits umgesetzte Funktionen stehen im [Changelog](CHANGELOG.md) und in der [Betriebsdokumentation](docs/CHARGING_MODES.md).

Ziel ist die Verwaltung in [GitHub Discussions](https://github.com/Sol-IoTiv/symcon-pv-wallbox-manager/discussions). Bis jede Idee dort einen verlinkten Eintrag hat, bleibt diese Datei die Übergangsliste. Vorgehen: [Mitarbeit und Pflege](CONTRIBUTING.md).

## Laden und Integration

- Zielzeitladung mit berechneten Ladefenstern.
- Preisoptimierte Ladeplanung; derzeit werden Preise nur angezeigt.
- Tibber als zusätzliche Strompreisquelle.
- Automatische Testladung zur Bestimmung der Fahrzeug-Ladeleistung.
- Unterstützung mehrerer Fahrzeuge.
- Weitere Wallboxmodelle und Schnittstellen.
- Erweiterte externe Fahrzeugdaten, beispielsweise über MQTT.

## Auswertung und Komfort

- Ladestatistik als HTMLBox und Verlaufsgrafiken für Ladevorgänge.
- PV-/Sonnenstundenprognose zur Ladeplanung.
- Kalenderintegration für Abfahrtszeiten und Termine.
- Pushbenachrichtigung beim Anstecken mit Auswahl des Lademodus.
- Saisonales Verhalten prüfen: Ist eine zusätzliche Sommer-/Wintereinstellung nötig?
- Ladefenster und Entscheidungen nachvollziehbar protokollieren.

## Noch zu prüfen

- Wiederanlauf nach Stromausfall mit realem Symcon und Wallbox testen. Persistente Phasenaufträge und Neustartfehlerbehandlung sind bereits implementiert; ein pauschaler automatischer Reset ist daher kein offenes Feature. Eine Fehlerquittierung darf die Stillstandsprüfung nicht umgehen.

## Abschluss einer Idee

Nach Implementierung: zugehöriges Issue und PR verlinken, Änderung im Changelog dokumentieren, Idee aus dieser offenen Liste entfernen. Eine Discussion bleibt als nachvollziehbare Entscheidung erhalten und erhält den Status „Umgesetzt“ samt Version oder Commit. „Auf dev implementiert“ und „veröffentlicht“ getrennt angeben.
