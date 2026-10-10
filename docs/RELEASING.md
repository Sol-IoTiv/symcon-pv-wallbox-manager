# Versionierung und Releases

Arbeitsstand 1.4.10b auf dev, unveröffentlicht; Änderungen unter Unreleased. Build/Datum bleiben bis zur tatsächlichen Release-Vorbereitung unverändert. Eine Stable-Version ist noch nicht freigegeben.

library.json ist die maßgebliche Bibliotheksversion; module.json muss für die bestehende Formularanzeige denselben Wert enthalten. Der Metadatentest prüft die Übereinstimmung.

## Branches und Veröffentlichungskanäle

| Branch | Aufgabe | Veröffentlichung |
|---|---|---|
| dev | Laufende Entwicklung und Tests | Keine automatische Veröffentlichung |
| beta-staging | Geprüfter Beta-Kandidat aus dev | Separate Einreichung im Symcon Store auf Beta; GitHub Release als Pre-release |
| main | Abgenommener Produktionsstand aus beta-staging | Separate Stable-Einreichung mit Symcon-Review; reguläres GitHub Release erst nach Freigabe |

Freigabeweg: dev → beta-staging → main. Übernahmen erfolgen per geprüftem Pull Request. main ist aktuell GitHub-Standardbranch; diese Einstellung bedeutet keine Stable-Freigabe. dev und beta-staging werden durch diese Dokumentationsänderung nicht zusammengeführt.

Die Store-Kanäle werden im Symcon-Entwicklerbereich verwaltet. Bei einer GitHub-Einreichung ist der passende getestete Stand auszuwählen und vor dem Einreichen mit dem Release-Commit abzugleichen. Beta/Testing und Stable haben unterschiedliche Einreichungsabläufe; Stable durchläuft Symcons Review. Ein GitHub-Pre-release setzt nicht automatisch den Store-Kanal auf Beta. Die aktuelle Konto-Konfiguration wurde hier nicht geprüft.

Quelle: [Symcon – Einreichen](https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/store/einreichen/).

## GitHub Releases und Tags

Ein Tag kennzeichnet einen bestimmten Codestand. Das zugehörige GitHub Release beschreibt diese Version und bietet Downloads an. Branches entwickeln sich weiter; veröffentlichte Tags werden nicht verschoben oder umbenannt.

Alle elf bisherigen Beta-Releases sind seit der Bereinigung am 10.10.2026 als Pre-release gekennzeichnet. Korrigiert wurden 1.4.7b, v1.4.6b, v1.4.3b und v1.4.2b; bei v1.4.3b wurde der fehlende Titel ergänzt. Tags, Release-Texte, Zielstände und Veröffentlichungsdaten blieben unverändert. Die unterschiedlichen historischen Tag-Präfixe bleiben bestehen.

Vorgesehene Folge: 1.4.10b → 1.5.0-rc.1 → 1.5.0. Vor RC-Metadaten deren Akzeptanz mit der unterstützten Symcon-Version prüfen. Neue Git-Tags einheitlich v1.4.10b, v1.5.0-rc.1 und v1.5.0. Beta/RC bleiben Pre-releases; das erste reguläre Release folgt erst mit einer freigegebenen Produktionsversion.

## Beta-Freigabe

1. Regression und die für den Beta-Kandidaten erforderlichen Praxisprüfungen abschließen; offene Einschränkungen dokumentieren.
2. README, Konfiguration, Migration und Funktionsumfang gegen den finalen Code prüfen.
3. Versionen synchronisieren; tatsächliche Buildnummer und Veröffentlichungszeit in library.json setzen.
4. Unreleased-Eintrag mit tatsächlichem Versionsnamen/Datum versehen; historische Einträge erhalten.
5. Geprüften Kandidaten per PR von dev nach beta-staging übernehmen; den übernommenen Stand erneut prüfen.
6. Den getesteten Release-Commit auf beta-staging taggen und als GitHub-Pre-release veröffentlichen.
7. Denselben Stand separat im Symcon-Entwicklerbereich auf Beta einreichen; Installation und Upgrade aus dem Store prüfen.

## Stable-Freigabe

1. Vollständige Hardware- und Symcon-Abnahme gemäß TESTING.md abschließen; offene Blocker beheben.
2. Abgenommenen Stand per PR von beta-staging nach main übernehmen. Finale Stable-Metadaten, Dokumentation und Changelog prüfen.
3. Den geprüften Produktionsstand im Symcon-Entwicklerbereich auf Stable einreichen und den Review abschließen.
4. Nach erfolgreicher Freigabe den zugehörigen Commit taggen und als reguläres GitHub Release veröffentlichen.
5. Installation und Upgrade des veröffentlichten Standes prüfen; Versionshinweise auch auf dev und beta-staging konsistent halten.

Historische Tags nicht verschieben, Versionslücken nicht nachträglich erfinden. Draft-PR #22 ist ohne Merge geschlossen; der alte Stabilisationsbranch wurde zugunsten von dev gelöscht. Diese Bereinigung erstellt keine neue Beta-/Stable-Version und reicht nichts im Symcon Store ein.
