# Registrierte Eigenschaften

Stand: unveröffentlichter Entwurf 1.4.10b. Defaults gelten bei neuen Instanzen; vorhandene Werte werden nicht überschrieben.

| Property | Typ | Standard |
|---|---|---|
| WallboxIP | string | `'0.0.0.0'` |
| WallboxAPIKey | string | `''` |
| RefreshInterval | integer | `30` |
| ModulAktiv | boolean | `true` |
| ModeAfterUnplug | integer | `0` |
| DebugLogging | boolean | `false` |
| MinAmpere | integer | `6` |
| MaxAmpere | integer | `16` |
| Phasen1Schwelle | integer | `3680` |
| Phasen3Schwelle | integer | `4140` |
| HausakkuSOCID | integer | `0` |
| HausakkuSOCVollSchwelle | integer | `95` |
| CarSOCID | integer | `0` |
| CarTargetSOCID | integer | `0` |
| CarBatteryCapacity | float | `0` |
| Phasen1Limit | integer | `3` |
| Phasen3Limit | integer | `3` |
| MinLadeWatt | integer | `1400` |
| MinStopWatt | integer | `1100` |
| StartLadeHysterese | integer | `3` |
| StopLadeHysterese | integer | `3` |
| InitialCheckInterval | integer | `10` |
| PVErzeugungID | integer | `0` |
| PVErzeugungEinheit | string | `'W'` |
| HausverbrauchID | integer | `0` |
| HausverbrauchEinheit | string | `'W'` |
| InvertHausverbrauch | boolean | `false` |
| BatterieladungID | integer | `0` |
| BatterieladungEinheit | string | `'W'` |
| InvertBatterieladung | boolean | `false` |
| PhaseSwitchCooldown | integer | `180` |
| PhaseSwitchTimeout | integer | `60` |
| InvertNetzleistung | boolean | `false` |
| GridMeasurementMaxAge | integer | `120` |
| NetzleistungID | integer | `0` |
| NetzleistungEinheit | string | `'W'` |
| NetzlimitStartAktiv | boolean | `false` |
| MaxGridLoadWatt | integer | `0` |
| UseMarketPrices | boolean | `false` |
| MarketPriceProvider | string | `'awattar_at'` |
| MarketPriceAPI | string | `''` |
| MarketPriceBasePrice | float | `0.00` |
| MarketPriceSurcharge | float | `0.00` |
| MarketPriceTaxRate | float | `0.00` |
| SmoothingAlpha | float | `0.5` |
| MaxRampDeltaAmp | integer | `2` |
| HybridEndMode | integer | `0` |
| HybridEndAmpere | integer | `6` |
| HybridEndDelaySeconds | integer | `600` |

Die fachlichen Bedeutungen und Einschränkungen stehen in [CONFIGURATION.md](CONFIGURATION.md).

NetzlimitStartAktiv und MaxGridLoadWatt sind ausgeblendete Legacy-Eigenschaften zur erstmaligen Initialisierung. Für die laufende Bedienung gelten ausschließlich die Instanzvariablen NetzlimitAktiv und MaxNetzbezugWatt.
