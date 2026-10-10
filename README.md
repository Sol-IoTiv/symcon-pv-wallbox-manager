# PVWallboxManager

IP-Symcon-Modul für go-eCharger mit PV-Überschussladen, PV-Anteil, manuellem und hybridem Laden sowie Netzbezugsbegrenzung.

**Noch keine Stable-Version freigegeben.** `main` ist für spätere Produktionsversionen vorgesehen. Sein vorhandener Modulcode ist ein historischer Entwicklungsstand und wird durch diese Einstiegsseite nicht als produktionsreif freigegeben.

## Welcher Stand ist der richtige?

| Branch | Zweck |
|---|---|
| [dev](https://github.com/Sol-IoTiv/symcon-pv-wallbox-manager/tree/dev) | Laufende Entwicklung und Tests; Arbeitsstand 1.4.10b, unveröffentlicht |
| [beta-staging](https://github.com/Sol-IoTiv/symcon-pv-wallbox-manager/tree/beta-staging) | Vorbereitung der Beta-Veröffentlichung im Symcon Store |
| main | Für abgenommene Produktionsversionen vorgesehen; derzeit keine Stable-Freigabe |

Der Freigabeweg ist **dev → beta-staging → main**. Alle bisherigen [GitHub Releases](https://github.com/Sol-IoTiv/symcon-pv-wallbox-manager/releases) sind Pre-releases. Store-Einreichungen erfolgen separat im Symcon-Entwicklerbereich; Branchname und GitHub Release allein veröffentlichen kein Store-Update.

## Aktuelle Dokumentation

Die folgenden Links zeigen ausdrücklich die Anleitung des unveröffentlichten Entwicklungsstands auf `dev`. Sie beschreiben nicht den historischen Modulcode auf `main`:

- [README und Einstieg](https://github.com/Sol-IoTiv/symcon-pv-wallbox-manager/blob/dev/README.md)
- [Konfiguration](https://github.com/Sol-IoTiv/symcon-pv-wallbox-manager/blob/dev/docs/CONFIGURATION.md) und [Property-Referenz](https://github.com/Sol-IoTiv/symcon-pv-wallbox-manager/blob/dev/docs/PROPERTY-REFERENCE.md)
- [Lademodi](https://github.com/Sol-IoTiv/symcon-pv-wallbox-manager/blob/dev/docs/CHARGING_MODES.md), [Phasensteuerung](https://github.com/Sol-IoTiv/symcon-pv-wallbox-manager/blob/dev/docs/PHASE_SWITCHING.md) und [Fehlerdiagnose](https://github.com/Sol-IoTiv/symcon-pv-wallbox-manager/blob/dev/docs/TROUBLESHOOTING.md)
- [Migration und Upgrade](https://github.com/Sol-IoTiv/symcon-pv-wallbox-manager/blob/dev/docs/MIGRATION-1.4.10b.md)
- [Changelog](https://github.com/Sol-IoTiv/symcon-pv-wallbox-manager/blob/dev/CHANGELOG.md), [Tests und Hardwareabnahme](https://github.com/Sol-IoTiv/symcon-pv-wallbox-manager/blob/dev/docs/TESTING.md) und [Freigabeablauf](https://github.com/Sol-IoTiv/symcon-pv-wallbox-manager/blob/dev/docs/RELEASING.md)

Preisoptimierte Ladeplanung und Zielzeitladung sind im aktuellen Entwicklungsstand nicht implementiert. Strompreise werden angezeigt. Die vollständige Hardware- und Symcon-Abnahme steht aus.

## Mitarbeit und Austausch

- [Discussions](https://github.com/Sol-IoTiv/symcon-pv-wallbox-manager/discussions): Ideen, Hilfe, Setups und Ankündigungen.
- [Issues](https://github.com/Sol-IoTiv/symcon-pv-wallbox-manager/issues): reproduzierbare Fehler und konkret beschlossene Umsetzung.
- [Wiki](https://github.com/Sol-IoTiv/symcon-pv-wallbox-manager/wiki): Einstieg und Navigation zur versionsgebundenen Dokumentation.
- [Mitarbeit und Pflege](https://github.com/Sol-IoTiv/symcon-pv-wallbox-manager/blob/dev/CONTRIBUTING.md): Ablauf von der Idee bis zur Veröffentlichung.

Projekt von [Sol-IoTiv](https://github.com/Sol-IoTiv), [MIT-Lizenz](LICENSE.md).
