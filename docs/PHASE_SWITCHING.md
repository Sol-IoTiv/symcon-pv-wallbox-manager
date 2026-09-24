# Kontrollierte Phasensteuerung

ChargingControl::executeChargingPlan() ist der gemeinsame Ausführer. Attribute speichern den Übergang über PHP-Ausführungen hinweg. Eine instanzbezogene Semaphore schützt Steuerung und Bedienaktionen. Überlappende Timer werden übersprungen; Bedienaktionen warten bis zu 5 s und melden danach einen Fehler.

| Zustand | Verhalten |
|---|---|
| idle | Zulässigen Plan anwenden oder Übergang beginnen |
| stopping | Stop anfordern; zwei Stillstandsmessungen mit mindestens 2 s Abstand |
| confirming | Phasenbefehl akzeptiert; zwei Statusbestätigungen des Zielmodus bei Stillstand |
| fault | Keine Wiederfreigabe; explizite Quittierung erforderlich |

Stillstand: frc=1, alw=false, car nicht ladend, Gesamtleistung höchstens 30 W, Betrag jedes Außenleiterstroms höchstens 0,2 A. Pflichtfelder müssen vorhanden und plausibel typisiert sein. Fehlende Daten bedeuten keinen Stillstand.

HTTP 200 allein genügt nicht: Die JSON-Antwort muss für den Parameter true enthalten. Danach muss psm den Zielmodus bestätigen. psm beschreibt die Einstellung, nicht die vom Fahrzeug genutzten Phasen. Erst ein neuer Regelzyklus entscheidet über den Wiederanlauf.

Während des Cooldowns bleibt der bestätigte Modus erhalten, soweit ein zulässiger Strom möglich ist. Andernfalls Stop. Reduzieren/Stoppen wartet nie auf den Cooldown.

Timeout, Ablehnung, Kommunikationsverlust im Übergang oder Abbruch nach unbestätigtem Phasenbefehl sperren die Wiederfreigabe. ApplyChanges während eines Übergangs erfordert Quittierung. Eine neue PHP-Ausführung allein verliert den Auftrag nicht.

Reset über Formularbutton oder PVWM_ResetPhaseFault: nur bei zurückgelesenem Stillstand; gibt selbst nicht frei. Bei Kommunikationsverlust kann das Modul einen tatsächlichen Stop nicht garantieren.

180 s Cooldown, 60 s Timeout und Stillstandsschwellen sind vorläufige Beta-Defaults; Hardware-/Firmware-/Fahrzeugprüfung erforderlich.
