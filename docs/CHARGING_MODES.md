# Lademodi

LademodusAuswahl: 0 Nur PV, 1 PV-Anteil, 2 Manuell, 5 Hybrid. 3/4 sind keine implementierten Zielzeitmodi.

## Gemeinsame Regeln

Aktivzustand, Fahrzeug, gültige Statusdaten und Ziel-SoC prüfen; Modusbedarf berechnen; Stromgrenzen und Netzbudget anwenden; nötigen Phasenwechsel bestätigen; Strom setzen; erst danach freigeben. Ein Moduswunsch ist kein direkter HTTP-Befehl. Moduswechsel verwerfen alte Überschussglättung, Hysterese-/Phasenzähler und Hybrid-Endladungszeit. Ungültige konfigurierte Fahrzeug-/Ziel-SoC-Werte sperren die Ladung in allen Modi. Ein ungültiger konfigurierter Hausakku-SoC sperrt PV und Hybrid; ohne zugeordnete Hausakkuvariable besteht diese Bedingung nicht.

## Nur PV

Optionale Hausakku-SoC-Bedingung und Start-Hysterese müssen erfüllt sein. Überschuss: PV minus gefilterter Hausverbrauch ohne Wallbox minus gegebenenfalls positive Speicherladung. Gegenüber 1.4.9b entfällt der unbedingte Schnellstart. Die Stop-Hysterese hält eine bestehende Ladung bis zum Ablauf der konfigurierten Zyklen mindestens auf Mindeststrom, auch bei null Überschuss. Netzlimit, Deaktivierung und Ziel-SoC haben weiterhin Vorrang. Mindeststrom, Rundung, Glättung und Messverzögerungen können Netzanteile verursachen.

## PV-Anteil

PVAnteil (0–100 %) wird auf PV minus Hausverbrauch ohne Wallbox angewendet. Keine separate Hausakku-SoC-Startsperre und kein zusätzlicher Speicherleistungsabzug. Phasenwahl und Stromberechnung richten sich nach dem gewählten Anteil des Überschusses. 0 % fordert sofort Stop an. Bei einem Anteil größer 0 gelten Start-/Stop-Hysterese und Netzlimit. Beispiel: 6.000 W Überschuss, 50 % → ungefähr 3.000 W Bedarf.

## Manuell

ManuellAmpere und ManuellPhasen geben den Wunsch vor. **ManuellPhasen=1 bedeutet 1P; 2 bedeutet 3P.** Keine PV-Startbedingung, aber weiterhin Ziel-SoC, Netzbudget und Phasenbestätigung.

## Hybrid

Start wie Nur PV. Nach Beginn hält Hybrid bei abnehmendem Überschuss die Mindestladung. Optional folgt verzögert die feste Endladung. Bei erneutem Überschuss kehrt PV-Regelung zurück. Eine absichtliche Umschaltpause wird als fortbestehender Ladebedarf gespeichert, bis ein neuer Zyklus freigibt oder den Bedarf verwirft.

## Ladeende

Ziel-SoC fordert Stop an. Zusätzlich besteht eine Leistungs-Fallbackerkennung über mehrere Zyklen; während Übergängen unterdrückt, im manuellen Modus nicht verwendet. ModeAfterUnplug gilt nach Abstecken und Ladeende. Stop erzwingt keinen unnötigen Wechsel auf 1P.
