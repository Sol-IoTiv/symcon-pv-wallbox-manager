# Mitarbeit und Pflege

## Wo gehört ein Beitrag hin?

| Inhalt | Ort |
|---|---|
| Ideen, Fragen, Setups, Erfahrungsberichte | GitHub Discussions |
| Reproduzierbare Fehler, konkret beschlossene Umsetzung | GitHub Issues |
| Code und versionsgebundene Anleitung | Pull Request auf dev, README und docs/ |
| Tatsächlich implementierte Änderungen | CHANGELOG.md; bis Veröffentlichung unter Unreleased |
| Versionspakete und Freigabestatus | GitHub Releases |
| Orientierung und Links | Wiki, sofern initialisiert |

## Ideen bearbeiten

Discussion-Kategorien: [Ideen](https://github.com/Sol-IoTiv/symcon-pv-wallbox-manager/discussions/categories/ideen), [Hilfe](https://github.com/Sol-IoTiv/symcon-pv-wallbox-manager/discussions/categories/hilfe), [Setups](https://github.com/Sol-IoTiv/symcon-pv-wallbox-manager/discussions/categories/setups) und [Ankündigungen](https://github.com/Sol-IoTiv/symcon-pv-wallbox-manager/discussions/categories/ank%C3%BCndigungen). Der [angeheftete Einstieg](https://github.com/Sol-IoTiv/symcon-pv-wallbox-manager/discussions/20) erklärt die Zuordnung.

1. Vorhandene Discussions und Issues durchsuchen.
2. Problem, Nutzen und gewünschtes Verhalten beschreiben; technische Umsetzung optional.
3. Bei angenommener Umsetzung ein Issue anlegen und beide Einträge verknüpfen.
4. Implementierung und Tests per PR nach dev; Dokumentation und Unreleased aktualisieren.
5. Nach Integration die Discussion mit PR/Commit und „Auf dev umgesetzt“ ergänzen und als erledigt schließen. Nach Veröffentlichung Version und Release verlinken. Geschlossene Discussions erscheinen nicht mehr in der offenen Ideenansicht.

Erledigte Discussions nicht löschen: Sie enthalten Begründungen und verhindern doppelte Vorschläge. Die 13 bisherigen Ideen sind übertragen. FEATURES.md bleibt als kurzer Verweis für bestehende Links erhalten und wird nicht mehr als Aufgabenliste gepflegt.

## Branches und Releases

Freigabeweg: `dev` → `beta-staging` → `main`. Entwicklung und Tests erfolgen auf `dev`. Geprüfte Beta-Kandidaten werden per PR nach `beta-staging` übernommen und von dort separat für den Beta-Kanal im Symcon Store eingereicht. Erst ein erfolgreich abgenommener Stand wird per PR nach `main` übernommen und für Stable eingereicht.

`main` bleibt aktuell GitHub-Standardbranch, hat aber noch keine Stable-Freigabe. Alle bisherigen GitHub Releases sind Pre-releases. Branchübernahmen, GitHub Releases und Store-Einreichungen sind getrennte Schritte; keine automatische Veröffentlichung durch einen Push.

Draft-PR #22 wurde ohne Merge geschlossen; der alte Stabilisationsbranch wurde nach Prüfung der vollständig in dev enthaltenen Commits gelöscht.

Neue Arbeiten und Dokumentationsänderungen auf dev aufbauen. Für Freigaben gelten [RELEASING.md](docs/RELEASING.md) und [TESTING.md](docs/TESTING.md). Historische Testberichte bleiben datierte Nachweise; aktuelle Nachträge müssen ihren geprüften Commit benennen.

## Wiki

README und docs/ sind die maßgebliche, gemeinsam mit dem Code geprüfte Anleitung. Im Wiki nur Einstieg, Navigationslinks und gegebenenfalls Community-Beispiele pflegen. Konfiguration, Property-Referenz und Lademodi nicht nochmals abschreiben. Eine Wiki-Startseite sollte auf dev, beta-staging, main und die Releases verweisen und deren unterschiedlichen Status erklären.

## Geschützte Freigabebranches

main und beta-staging verlangen Pull Requests, einen aktuellen PR-Stand mit erfolgreicher CI unter PHP 8.2/8.3 und aufgelöste Review-Kommentare. Force-Push und Branchlöschung sind gesperrt; die Regeln gelten auch für Administratoren. Eine zusätzliche Freigabe durch eine zweite Person ist derzeit nicht vorgeschrieben, damit das Projekt als Einzelmaintainer bedienbar bleibt.

Die historischen main-/beta-staging-Stände haben noch keine Regressionstests. Ihre CI prüft ausdrücklich nur die PHP-Syntax. Sobald die Tests mit dem Entwicklungscode übernommen sind, führt derselbe Workflow zusätzlich die vollständige Suite aus. Ein grüner Syntaxlauf ist keine Hardware- oder Stable-Freigabe.
