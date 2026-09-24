# Bereinigter Statusauszug

go-e-v4-60.6-sanitized.json enthält ausschließlich freigegebene technische Felder aus der Nutzerantwort vom 24.09.2026: alw, car, psm, frc, amp, err, var, ama, nrg und fwv. Keine Seriennummern, Netzwerkkennungen, Zugangsdaten oder Ladehistorie wurden übernommen.

Der eingefügte Gesamttext war beim Feld eto syntaktisch kein gültiges JSON. Die zehn übernommenen Top-Level-Werte wurden daher einzeln dekodiert. Die Fixture ist ein minimierter Statusauszug, keine unveränderte HTTP-Antwort. Der produktive JSON-Parser bleibt strikt.

Aufnahmezustand: kein Fahrzeug, keine Ladeleistung, 3P eingestellt, frc=0 (Neutral). Dies bestätigt keine erfolgreiche Ladestop-Anforderung und keinen ausgeführten Phasenwechsel.
