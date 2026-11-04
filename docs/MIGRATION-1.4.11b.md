# Update 1.4.10b → 1.4.11b

Bestehende Instanz aktualisieren, nicht neu anlegen. Vor dem Update Symcon-Konfiguration und Modulstand sichern. Bestehende Variablen, IDs und Historien bleiben erhalten; eine manuelle Datenkonvertierung ist nicht erforderlich. Für ältere Versionen gelten zusätzlich die [Migrationshinweise zu 1.4.10b](MIGRATION-1.4.10b.md).

## Änderungen

- PV-Erzeugung, Hausverbrauch, Batterieladung und Netzleistung erlauben Integer und Float. Die konfigurierte Einheit W/kW muss zur Quelle passen.
- Hausakku-SoC, Fahrzeug-SoC und Ziel-SoC erlauben ausschließlich Integer und Float; Werte müssen zwischen 0 und 100 Prozent liegen. Bisher irrtümlich verwendete Strings oder Boolean-Quellen durch numerische Variablen ersetzen.
- Nach verlorener Ladefreigabe-Antwort und Transportausfall beim anschließenden Stop wird die Stop-Bestätigung begrenzt erneut geprüft. Währenddessen bleibt die Ladefreigabe gesperrt. Erst zwei frische Stillstandsbestätigungen und eine spätere vollständige Neubewertung erlauben einen weiteren Freigabeversuch.
- Echte Wallbox-/Phasenfehler, Befehlsablehnungen und ausgeschöpfte Versuche bleiben quittierungspflichtig. Bestehende Fehlersperren werden nicht automatisch gelöscht.

Nach dem Update Variablenzuordnungen, Einheiten und SoC-Werte kontrollieren. Die Wiederherstellung ersetzt keine zuverlässige Verbindung zur Wallbox. Hardware-, Kernel- und Store-Upgrade-Abnahme stehen weiterhin aus; dies ist eine Beta-Version.
