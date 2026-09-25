# Lademodi

LademodusAuswahl: 0 Nur PV, 1 PV-Anteil, 2 Manuell, 5 Hybrid. 3/4 sind keine implementierten Zielzeitmodi.

## Gemeinsame Regeln

Aktivzustand, Fahrzeug, gültige Statusdaten und Ziel-SoC prüfen; Modusbedarf berechnen; Stromgrenzen und Netzbudget anwenden; nötigen Phasenwechsel bestätigen; Strom setzen; erst danach freigeben. Ein Moduswunsch ist kein direkter HTTP-Befehl. Moduswechsel verwerfen alte Überschussglättung, Hysterese-/Phasenzähler und Hybrid-Endladungszeit. Ungültige konfigurierte Fahrzeug-/Ziel-SoC-Werte sperren die Ladung in allen Modi. Ein ungültiger konfigurierter Hausakku-SoC sperrt PV und Hybrid; ohne zugeordnete Hausakkuvariable besteht diese Bedingung nicht.

## Nur PV

Optionale Hausakku-SoC-Bedingung und Start-Hysterese müssen erfüllt sein. Überschuss: PV minus gefilterter Hausverbrauch ohne Wallbox minus gegebenenfalls positive Speicherladung. Gegenüber 1.4.9b entfällt der unbedingte Schnellstart. Die Stop-Hysterese hält eine bestehende Ladung bis zum Ablauf der konfigurierten Zyklen mindestens auf Mindeststrom, auch bei null Überschuss. Netzlimit, Deaktivierung und Ziel-SoC haben weiterhin Vorrang. Mindeststrom, Rundung, Glättung und Messverzögerungen können Netzanteile verursachen.

## PV-Anteil

PVAnteil (0–100 %) wird auf PV minus Hausverbrauch ohne Wallbox angewendet. Keine separate Hausakku-SoC-Startsperre und kein zusätzlicher Speicherleistungsabzug. Phasenwahl und Stromberechnung richten sich nach dem gewählten Anteil des Überschusses. 0 % fordert sofort Stop an. Bei einem Anteil größer 0 gelten Start-/Stop-Hysterese und Netzlimit. Beispiel: 6.000 W Überschuss, 50 % → ungefähr 3.000 W Bedarf.

Ab der bestehenden HausakkuSOCVollSchwelle wird bei gültigem Hausakku-SoC automatisch mit 100 % gerechnet. Unterhalb der Schwelle gilt wieder der gespeicherte PVAnteil. Dieser Regler wird nicht überschrieben; die Statusanzeige erklärt die wirksamen 100 %. Ohne zugeordneten oder gültigen Hausakku-SoC bleibt der eingestellte Anteil wirksam. 0 % fordert weiterhin Stop an und wird nicht automatisch angehoben. Es gibt keine zusätzliche Einstellung oder separate SoC-Hysterese; bestehende Leistungs-Hysterese und Stromrampe gelten weiterhin.

Beispiel: 1500 W Überschuss ergeben bei 70 % nur 1050 W, unter der Standard-Stoppschwelle von 1100 W. Mit erreichtem Hausakku-Ziel stehen 1500 W zur Verfügung und die Standard-Startschwelle von 1400 W kann erfüllt werden. Netzlimit, Phasenlogik und Fahrzeug-Ziel-SoC bleiben vorrangig.

Das Modul steuert nur die Wallbox. Ob der übrige Überschuss gespeichert oder eingespeist wird, entscheidet das Speichersystem; eine feste 70/30-Aufteilung wird nicht garantiert.

## Manuell

ManuellAmpere und ManuellPhasen geben den Wunsch vor. **ManuellPhasen=1 bedeutet 1P; 2 bedeutet 3P.** Keine PV-Startbedingung, aber weiterhin Ziel-SoC, Netzbudget und Phasenbestätigung.

## Hybrid

Start wie Nur PV. Nach Beginn hält Hybrid bei abnehmendem Überschuss die Mindestladung. Optional folgt verzögert die feste Endladung. Bei erneutem Überschuss kehrt PV-Regelung zurück. Eine absichtliche Umschaltpause wird als fortbestehender Ladebedarf gespeichert, bis ein neuer Zyklus freigibt oder den Bedarf verwirft.

## Ladeende

Ziel-SoC fordert Stop an. Zusätzlich besteht eine Ladeende-Erkennung über mehrere Zyklen: Nach der letzten Freigabe muss zunächst Status „Fahrzeug lädt“ mit mindestens 300 W beobachtet worden sein. Erst danach zählen Messungen unter 300 W bei Wallboxstatus „Ladung beendet“ (car=4) als Ladeende. Eine Freigabe ohne Leistungsaufnahme ist ein Wartezustand und löst keinen Wechsel auf den Standardmodus aus. Eigene Stopbefehle, Abstecken und zurückgenommene Freigabe verwerfen den Nachweis. Während Phasenübergängen ist diese Erkennung unterdrückt; im manuellen Modus wird sie nicht verwendet. ModeAfterUnplug gilt nach Abstecken und Ladeende. Stop erzwingt keinen unnötigen Wechsel auf 1P.
