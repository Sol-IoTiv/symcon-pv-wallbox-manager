# Repository-Prüfung vom 10.10.2026

Geprüfter Codestand: 5d9755383d70285ffc242b3d47aba17e8fed055f (04.10.2026), Arbeitsversion 1.4.10b. Remote-Abgleich am 10.10.2026: identischer Stand. Dokumentationsprüfung mit gezielter Durchsicht von Ladeausführer, Tests, Versionsmetadaten, README, docs/, Changelog und Vorlagen; keine vollständige erneute Zeilenprüfung des gesamten Moduls.

## Verifikation

- PHP-Syntax für module.php und ChargingControl.php erfolgreich.
- 84 Ladeverhaltenstests und drei Basisregressionen erfolgreich.
- Metadaten, Formular-Properties und Property-Referenz erfolgreich.
- GitHub-CI für den geprüften Codestand erfolgreich, unter anderem Lauf 37200589346.
- Vollständige Hardwareabnahme und Integration mit realem Symcon weiterhin offen. Version und Build/Datum bleiben unverändert; keine Stable-Freigabe.

## Befunde und Änderungen

| Befund | Ergebnis |
|---|---|
| README beschreibt PV-Anteil ohne automatische 100-%-Anhebung | Tabelle präzisiert; ausführliche Modusbeschreibung war bereits vorhanden |
| FEATURES vermischt Historie und offene Wünsche | Erledigte Punkte entfernt, offene Ideen zusammengeführt |
| Changelog spricht noch von fester Anteilssemantik | Zeitliche Entwicklung zur automatischen Anhebung präzisiert; veröffentlichte Historie erhalten |
| Issue-Vorlagen fragen nach nicht implementierter Zielzeitladung | Durch Versions-/Branchangabe ersetzt; Buglabel bereinigt |
| Phasendokumentation erklärt neue Freigabeversuche nicht | Drei Versuche und 30-s-Abstand ergänzt; Stop-/Phasenfehler getrennt erklärt |
| Unklare Pflegeverantwortung für Ideen und Anleitung | CONTRIBUTING.md beschreibt Discussions → Issue → PR → Changelog |
| Entwicklungsbranch mit einmaligem Versionsnamen | dev als dauerhafter Branch; alter Remote-Branch zunächst für Draft-PR #22 erhalten |

## Remote-Struktur und nächste Entscheidungen

- main ist Standardbranch, beta-staging liegt auf e9337aa, bisheriger Stabilisationsbranch auf 5d97553. main und beta-staging sind ältere Entwicklungsstände. Die neue README erscheint deshalb zunächst auf dev; vor einer Übernahme nach main muss der gesamte Codeunterschied geprüft werden.
- Draft-PR #22 zielt von codex/1.4.10b-stabilization nach beta-staging. Alten Remote-Branch erst nach Abschluss oder bewusster Ablösung dieses PR entfernen. Ein lokaler Branchwechsel ersetzt dessen Head nicht.
- Zuletzt veröffentlichtes Release laut Releaseliste: 1.4.7b vom 19.05.2026. 1.4.9b ist ein vorhandener Code-/Changelogstand, kein hier nachgewiesenes Release. Das b-Release 1.4.7b ist als regulär statt Prerelease markiert. Zukünftige Beta/RC-Releases konsistent kennzeichnen; historische Tags unverändert lassen.
- GitHub-Kurzbeschreibung „Modularer Energie-Manager für PV, Speicher, Wallbox etc.“ ist zu breit. GitHub-Kurzbeschreibung aktualisiert auf: „IP-Symcon-Modul für go-eCharger: PV-Überschussladen, PV-Anteil, manuelles und hybrides Laden mit Netzbezugsbegrenzung.“
- Discussions sind aktiviert. Bestehende Inhalte und Kategorien konnten in diesem Durchlauf nicht zuverlässig gelesen werden; keine Übertragung oder Veröffentlichung einzelner Ideen behauptet. FEATURES.md bleibt bis zum verlinkten Transfer erhalten.
- Wiki ist aktiviert; Abruf des Wiki-Git-Repositories liefert „Repository not found“. Das belegt kein bereits gepflegtes Wiki und kann auf fehlende Initialisierung oder Zugriff hindeuten. Keine bestehenden Seiten verändert.

## Vorschlag für das Wiki

Eine Startseite „PVWallboxManager – Dokumentation“ mit Links zu README auf main/dev, Releases, Konfiguration, Lademodi, Fehlerdiagnose, Migration und Discussions. Deutlich erklären: dev ist unveröffentlicht. Keine Kopie der Property-Referenz oder Betriebsanleitung pflegen. Nach Initialisierung die Links anhand real existierender Seiten prüfen.

## Vorschlag für Discussions

Kategorien Ideen, Hilfe, Setups und Ankündigungen. Offene Themen aus FEATURES.md einzeln übernehmen und dort ihre URLs hinterlegen. Erst nach vollständigem Transfer die Datei durch einen kurzen Verweis ersetzen oder entfernen. Umgesetzte Ideen aus der offenen Liste entfernen, Discussion mit Commit/PR und Veröffentlichungsstatus erhalten. Bereits implementiert bedeutet noch nicht veröffentlicht.
