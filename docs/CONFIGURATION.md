# Konfiguration

Alle registrierten Eigenschaften mit Typ und Default: [PROPERTY-REFERENCE.md](PROPERTY-REFERENCE.md). Defaults überschreiben keine vorhandenen Werte.

## Verbindung und Betrieb

WallboxIP ist eine lokale IP-Adresse; der Client verwendet API v2 über HTTP/Port 80. WallboxAPIKey bleibt als Legacy-Einstellung erhalten, wird von der aktuellen Steuerung nicht verwendet.

ModulAktiv=false fordert Stop an und beendet die normalen Timer. Es ist keine Übergabe an eine autonome Wallboxregelung. Ohne Kommunikation ist ein tatsächlicher Stop nicht bestätigt.

RefreshInterval: 15–600 s, Standard 30 s. InitialCheckInterval: 0 deaktiviert Polling ohne Fahrzeug, sonst 5–60 s. Bei 0 benötigt eine neue Fahrzeugverbindung einen extern angestoßenen UpdateStatus-Aufruf. Während einer Umschaltung läuft zusätzlich ein 2-s-Timer.

DebugLogging aktiviert zusätzliche Protokolle. ModeAfterUnplug: -1 beibehalten, 0 Nur PV, 1 PV-Anteil, 2 Manuell. Gilt auch nach erkanntem Ladeende; Hybrid kann mit -1 erhalten bleiben.

## Energiebilanz

PVErzeugungID: positive PV-Erzeugung. HausverbrauchID: positiver Gesamtverbrauch inklusive Wallbox. BatterieladungID: positive Speicherladung. Einheiten jeweils W/kW. InvertHausverbrauch und InvertBatterieladung korrigieren abweichende Quellen. Nicht zugeordnete Werte (ID 0) werden als 0 behandelt; ungültige zugeordnete Werte sperren den Zyklus.

Die Wallboxleistung wird vom Hausverbrauch abgezogen. Nur PV/Hybrid berücksichtigen zusätzlich positive Batterieladung, solange der Hausakku nicht als voll erkannt wird. PV-Anteil verwendet keinen separaten Speicherleistungsabzug.

HausakkuSOCID/HausakkuSOCVollSchwelle bilden eine optionale Startbedingung für PV/Hybrid. Ohne zugeordneten SoC keine SoC-Sperre; eine bereits laufende Ladung wird nicht allein wegen Unterschreiten dieser Startschwelle beendet.

SmoothingAlpha steuert exponentielle Glättung (0–1). MaxRampDeltaAmp begrenzt Stromerhöhungen; Reduzierungen erfolgen sofort. Nach drei auffälligen Hauslastanstiegen übernimmt der Spikefilter den neuen Wert.

## Ladegrenzen

MinAmpere/MaxAmpere gelten zusammen mit den aus var/ama erkannten Wallboxgrenzen. Untergrenze 6 A; widersprüchliche Mindest-/Maximalwerte sperren die Freigabe.

MinLadeWatt/MinStopWatt: Start-/Stopschwellen. StartLadeHysterese/StopLadeHysterese zählen Regelaufrufe. Phasen3Schwelle/Phasen1Schwelle und Phasen3Limit/Phasen1Limit bilden den Phasenwunsch. Netzbudget und Cooldown können diesen zurückstellen.

Im Formular bündelt „Phasenumschaltung“ die Schaltschwellen, Zählerlimits, Wartezeiten und das Zurücksetzen eines Phasenfehlers.

PhaseSwitchCooldown: Mindestabstand zwischen Phasenwechseln, Standard 180 s, 30–1800 s. Währenddessen kann mit der bisherigen Phasenanzahl weitergeladen werden, sofern genug Leistung verfügbar ist. PhaseSwitchTimeout: maximale Wartezeit je Bestätigung (Stillstand bzw. übernommener Phasenmodus), Standard 60 s, im Formular 15–300 s. Sobald die erforderlichen Rückmeldungen vorliegen, geht es weiter; dies ist keine feste Ladepause. Ohne Bestätigung bleibt die Wiederfreigabe gesperrt. Das sind Beta-Defaults für die Hardwareabnahme.

## Netzlimit

Im Formular bündelt „Netzanschluss / Netzbezug“ Messvariable, Einheit, Vorzeichen, maximales Messwertalter sowie den Hinweis zur Bedienung der Begrenzung.

NetzleistungID: Gesamt-Netzleistung inklusive Wallbox. Intern positiv=Bezug, negativ=Einspeisung; InvertNetzleistung kehrt das Vorzeichen um. NetzleistungEinheit: W/kW.

GridMeasurementMaxAge: Standard 120 s, Bezug auf Symcons VariableUpdated. Der Zähler muss auch bei gleichem Wert regelmäßig aktualisieren. Fehlende, falsch typisierte, nicht numerische oder zu alte Werte sperren die Ladung bei aktivem Limit.

Die Begrenzung ausschließlich über die bedienbaren Instanzvariablen „Netzbegrenzung aktiv“ (NetzlimitAktiv) und „Maximale Netzbelastung“ (MaxNetzbezugWatt) einstellen, in der Visualisierung oder im Objektbaum unter der PVWallboxManager-Instanz. Der Schieberegler reicht von 0 bis 22.000 W in 100-W-Schritten. Zuerst den gewünschten Grenzwert in Watt setzen, dann einschalten. **0 W bedeutet deaktivierte Begrenzung.**

Die früheren Formular-Startwerte NetzlimitStartAktiv und MaxGridLoadWatt bleiben als Legacy-Eigenschaften für die Kompatibilität registriert, sind aber nicht mehr im Formular sichtbar. Sie werden nur bei der erstmaligen Initialisierung übernommen; vorhandene Laufzeitwerte bleiben beim Update erhalten. Neue Instanzen starten standardmäßig mit ausgeschalteter Begrenzung und 0 W.

Budget = aktuelle Wallboxleistung + Bezugslimit − Netzbezug. Gilt auch bei Einspeisung. Reicht das Budget nicht für 3P-Mindeststrom, wird 1P erwogen; reicht auch das nicht, wird Stop angefordert. Cooldown verzögert keinen nötigen Stop.

## Hybrid und Fahrzeug

CarMaxPhases: Maximale AC-Ladephasen des angeschlossenen Fahrzeugs (1, 2 oder 3; Standard 3). Im 3P-Wallboxmodus wird mit dieser Anzahl gerechnet, im 1P-Modus mit einer Phase. Gilt für PV, PV-Anteil, Hybrid, Netzlimit und Leistungsumrechnung während des Cooldowns. Bei Fahrzeugwechsel passend einstellen. Die gemessene Phasenanzeige bleibt unabhängig davon; der Wallboxmodus bleibt 1P/3P.

HybridEndMode: 0 deaktiviert, 1 feste 1P-Endladung, 2 feste 3P-Endladung. HybridEndDelaySeconds: Wartezeit bei dauerhaft geringer PV; 0 deaktiviert die Endladung. HybridEndAmpere: Stromwunsch innerhalb der gemeinsamen Grenzen.

CarSOCID/CarTargetSOCID: Ziel-SoC beendet die Ladung in allen Modi. CarBatteryCapacity (kWh) dient der Restzeitschätzung, nicht der Ladeplanung.

## Strompreise

UseMarketPrices, MarketPriceProvider (awattar_at/awattar_de/custom) und MarketPriceAPI steuern den Abruf. Custom benötigt data-Einträge mit start_timestamp/end_timestamp in Millisekunden und marketprice in EUR/MWh.

Preis ct/kWh = (marketprice/10 + MarketPriceBasePrice) × (1 + MarketPriceSurcharge/100) × (1 + MarketPriceTaxRate/100). Negative Preise sind zulässig. Der Steuer-Default im Modul ist 0; tatsächlichen Satz bewusst einstellen.

MarketPricesValid zeigt einen gültigen aktuellen letzten Abruf. Bei Fehler bleibt der letzte Preis erhalten und wird als ungültig markiert. Der Abruf erfolgt stündlich und beim Anwenden der aktiven Konfiguration.
