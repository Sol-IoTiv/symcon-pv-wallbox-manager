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
| Entwicklungsbranch mit einmaligem Versionsnamen | dev als dauerhafter Branch; alter Remote-Branch nach Abschluss von Draft-PR #22 gelöscht |

## Remote-Struktur und nächste Entscheidungen

- main ist Standardbranch, beta-staging liegt auf e9337aa, bisheriger Stabilisationsbranch auf 5d97553. main und beta-staging sind ältere Entwicklungsstände. Die neue README erscheint deshalb zunächst auf dev; vor einer Übernahme nach main muss der gesamte Codeunterschied geprüft werden.
- Nachtrag 10.10.2026: Draft-PR #22 ohne Merge geschlossen. codex/1.4.10b-stabilization auf ausdrücklichen Nutzerwunsch gelöscht; vorher per merge-base geprüft, dass sämtliche Commits in origin/dev enthalten sind. main und beta-staging unverändert.
- Zuletzt veröffentlichtes Release laut Releaseliste: 1.4.7b vom 19.05.2026. 1.4.9b ist ein vorhandener Code-/Changelogstand, kein hier nachgewiesenes Release. Nachtrag 10.10.2026: Alle elf bisherigen Releases sind jetzt Pre-releases. Die vier zuvor regulären Beta-Releases wurden korrigiert; der fehlende Titel von v1.4.3b wurde ergänzt. Tags, Release-Texte, Zielstände und Veröffentlichungsdaten unverändert.
- GitHub-Kurzbeschreibung „Modularer Energie-Manager für PV, Speicher, Wallbox etc.“ ist zu breit. GitHub-Kurzbeschreibung aktualisiert auf: „IP-Symcon-Modul für go-eCharger: PV-Überschussladen, PV-Anteil, manuelles und hybrides Laden mit Netzbezugsbegrenzung.“
- Discussions sind aktiviert. Bestehende Inhalte und Kategorien konnten in diesem Durchlauf nicht zuverlässig gelesen werden; keine Übertragung oder Veröffentlichung einzelner Ideen behauptet. FEATURES.md bleibt bis zum verlinkten Transfer erhalten.
- Wiki ist aktiviert; Abruf des Wiki-Git-Repositories liefert „Repository not found“. Das belegt kein bereits gepflegtes Wiki und kann auf fehlende Initialisierung oder Zugriff hindeuten. Keine bestehenden Seiten verändert.

## Vorschlag für das Wiki

Eine Startseite „PVWallboxManager – Dokumentation“ mit Links zu README auf main/dev, Releases, Konfiguration, Lademodi, Fehlerdiagnose, Migration und Discussions. Deutlich erklären: dev ist unveröffentlicht. Keine Kopie der Property-Referenz oder Betriebsanleitung pflegen. Nach Initialisierung die Links anhand real existierender Seiten prüfen.

## Vorschlag für Discussions

Kategorien Ideen, Hilfe, Setups und Ankündigungen. Offene Themen aus FEATURES.md einzeln übernehmen und dort ihre URLs hinterlegen. Erst nach vollständigem Transfer die Datei durch einen kurzen Verweis ersetzen oder entfernen. Umgesetzte Ideen aus der offenen Liste entfernen, Discussion mit Commit/PR und Veröffentlichungsstatus erhalten. Bereits implementiert bedeutet noch nicht veröffentlicht.

## Festgelegter Freigabeweg

dev dient Entwicklung und Tests, beta-staging der Vorbereitung der Beta-Veröffentlichung im Symcon Store und main den später freigegebenen Produktionsständen. Noch keine Stable-Freigabe. README, CONTRIBUTING und RELEASING beschreiben jetzt denselben Ablauf. Store-Konto-Konfiguration nicht zugänglich und nicht geändert; keine neue Store-Einreichung und keine Branchzusammenführung durchgeführt.

## Abschluss der Repository-Bereinigung

- main-README durch einen klar gekennzeichneten Einstieg ersetzt; keine Stable-Freigabe und keine Übernahme des Entwicklungscodes. GitHub-Themen gesetzt.
- Discussion-Kategorien Ideen, Hilfe, Setups und Ankündigungen eingerichtet. Alle 13 offenen Ideen aus FEATURES.md sind als Discussions #23–#35 übertragen; konkreter Wiederanlauf-Test als Issue #36. Der angeheftete Einstieg #20 wurde aktualisiert. FEATURES.md ist nur noch ein Verweis; erledigte Discussions werden geschlossen und aus der offenen Ansicht ausgeblendet, nicht gelöscht.
- Wiki erstmals initialisiert und als Navigation zu README/docs eingerichtet. Keine zweite Betriebsanleitung gepflegt.
- main und beta-staging durch verpflichtende PRs, PHP-8.2/8.3-CI, aktuelle PR-Basis und aufgelöste Review-Kommentare geschützt; auch für Administratoren. Force-Push und Branchlöschung gesperrt. Historische Branchstände prüfen mangels Testsuite ausdrücklich nur Syntax; dev führt alle Regressionstests aus.
- Öffentliche Issue-Einstiege richten Fragen, Setups und offene Wünsche an Discussions. PR #37 nach erfolgreicher CI nach main übernommen; ausschließlich Vorlagen geändert.
- Symcon-Entwicklerbereich geprüft: Beta 1.4.9b veröffentlicht auf c3685a2e5fb2015a7756a5cf26a4f92219581181, Testing abgeschlossen, keine Stable-Version. Keine Store-Einreichung oder Änderung vorgenommen; Details in STORE-STATUS.md.
