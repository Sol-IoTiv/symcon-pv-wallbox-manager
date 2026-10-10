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

Empfohlene Discussion-Kategorien: Ideen, Hilfe, Setups und Ankündigungen. Kategorien sind ein Vorschlag und werden nicht durch diese Datei angelegt.

1. Vorhandene Discussions und Issues durchsuchen.
2. Problem, Nutzen und gewünschtes Verhalten beschreiben; technische Umsetzung optional.
3. Bei angenommener Umsetzung ein Issue anlegen und beide Einträge verknüpfen.
4. Implementierung und Tests per PR nach dev; Dokumentation und Unreleased aktualisieren.
5. Nach Integration aus FEATURES.md entfernen. Discussion mit PR und „Auf dev umgesetzt“ ergänzen; nach Veröffentlichung Version und Release verlinken.

Erledigte Discussions nicht löschen: Sie enthalten Begründungen und verhindern doppelte Vorschläge. FEATURES.md erst entfernen, wenn alle verbleibenden Ideen übertragen und die Verweise ersetzt sind.

## Branches und Releases

Freigabeweg: `dev` → `beta-staging` → `main`. Entwicklung und Tests erfolgen auf `dev`. Geprüfte Beta-Kandidaten werden per PR nach `beta-staging` übernommen und von dort separat für den Beta-Kanal im Symcon Store eingereicht. Erst ein erfolgreich abgenommener Stand wird per PR nach `main` übernommen und für Stable eingereicht.

`main` bleibt aktuell GitHub-Standardbranch, hat aber noch keine Stable-Freigabe. Alle bisherigen GitHub Releases sind Pre-releases. Branchübernahmen, GitHub Releases und Store-Einreichungen sind getrennte Schritte; keine automatische Veröffentlichung durch einen Push.

Draft-PR #22 wurde ohne Merge geschlossen; der alte Stabilisationsbranch wurde nach Prüfung der vollständig in dev enthaltenen Commits gelöscht.

Neue Arbeiten und Dokumentationsänderungen auf dev aufbauen. Für Freigaben gelten [RELEASING.md](docs/RELEASING.md) und [TESTING.md](docs/TESTING.md). Historische Testberichte bleiben datierte Nachweise; aktuelle Nachträge müssen ihren geprüften Commit benennen.

## Wiki

README und docs/ sind die maßgebliche, gemeinsam mit dem Code geprüfte Anleitung. Im Wiki nur Einstieg, Navigationslinks und gegebenenfalls Community-Beispiele pflegen. Konfiguration, Property-Referenz und Lademodi nicht nochmals abschreiben. Eine Wiki-Startseite sollte auf dev, beta-staging, main und die Releases verweisen und deren unterschiedlichen Status erklären.
