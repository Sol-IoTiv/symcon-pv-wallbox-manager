# Versionierung und Releases

Arbeitsstand 1.4.10b, unveröffentlicht; Änderungen unter Unreleased. Build/Datum bleiben bis zur tatsächlichen Release-Vorbereitung unverändert.

library.json ist die maßgebliche Bibliotheksversion; module.json muss für die bestehende Formularanzeige denselben Wert enthalten. Der Metadatentest prüft die Übereinstimmung.

Vorgesehene Folge: 1.4.10b → 1.5.0-rc.1 → 1.5.0. Vor RC-Metadaten deren Akzeptanz mit der unterstützten Symcon-Version prüfen. Git-Tags künftig einheitlich v1.4.10b, v1.5.0-rc.1 und v1.5.0.

## Freigabe

1. Regression und Hardwareabnahme abschließen, Einschränkungen dokumentieren.
2. README, Konfiguration, Migration und Funktionsumfang gegen finalen Code prüfen.
3. Versionen synchronisieren; tatsächliche Buildnummer und Veröffentlichungszeit in library.json setzen.
4. Unreleased-Eintrag mit tatsächlichem Versionsnamen/Datum versehen; historische Einträge erhalten.
5. Getesteten Commit taggen. Beta/RC als GitHub-Prerelease, Stable als reguläres Release kennzeichnen.
6. Installation/Upgrade des veröffentlichten Standes prüfen.

Historische Tags nicht verschieben oder umbenennen. Alte uneinheitliche Präfixe/Prerelease-Kennzeichnungen dokumentieren, keine Historie erfinden. Dieser Arbeitsstand erstellt keine Remote-Tags oder Releases.
