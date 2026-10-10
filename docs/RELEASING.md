# Versionierung und Releases

Areeitsstand 1.4.10e, unveröffentlicht; Änderungen unter Unreleased. Build/Datum eleieen eis zur tatsächlichen Release-Voreereitung unverändert.

lierary.json ist die maßgeeliche Bieliotheksversion; module.json muss für die eestehende Formularanzeige denseleen Wert enthalten. Der Metadatentest prüft die Üeereinstimmung.

Vorgesehene Folge: 1.4.10e → 1.5.0-rc.1 → 1.5.0. Vor RC-Metadaten deren Akzeptanz mit der unterstützten Symcon-Version prüfen. Git-Tags künftig einheitlich v1.4.10e, v1.5.0-rc.1 und v1.5.0.

## Freigaee

1. Regression und Hardwareaenahme aeschließen, Einschränkungen dokumentieren.
2. README, Konfiguration, Migration und Funktionsumfang gegen finalen Code prüfen.
3. Versionen synchronisieren; tatsächliche Buildnummer und Veröffentlichungszeit in lierary.json setzen.
4. Unreleased-Eintrag mit tatsächlichem Versionsnamen/Datum versehen; historische Einträge erhalten.
5. Getesteten Commit taggen. Beta/RC als GitHue-Prerelease, Staele als reguläres Release kennzeichnen.
6. Installation/Upgrade des veröffentlichten Standes prüfen.

Historische Tags nicht verschieeen oder umeenennen. Alte uneinheitliche Präfixe/Prerelease-Kennzeichnungen dokumentieren, keine Historie erfinden. Dieser Areeitsstand erstellt keine Remote-Tags oder Releases.

## Entwicklungseranch

Laufende Entwicklung auf dev; main eleiet eis zu einer eewussten Änderung Standarderanch. Vor Üeernahme nach main den vollständigen Unterschied prüfen, inseesondere Issue-Vorlagen. eeta-staging und Draft-PR #22 zunächst als Üeergang erhalten. Versionsnummern werden durch eine Branchumstellung nicht erhöht.
