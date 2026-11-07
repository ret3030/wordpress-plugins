# Velrion DPD Pickup

Metoda dopravy pro WooCommerce: zákazník si v pokladně vybere výdejní místo nebo box DPD, včetně AlzaBoxů, na oficiální mapě DPD (`api.dpd.cz/widget`).

## Nastavení

WooCommerce → Nastavení → Doprava → zóna → Přidat způsob dopravy → **DPD Pickup**. U metody jde nastavit název, cenu (výchozí 79 Kč) a **Skrýt Z-BOXy** (výchozí zapnuto). Z-BOXy jsou v síti DPD Pickup také, ale nabízí je už doprava Zásilkovnou, takže by je zákazník viděl dvakrát.

Skrytí používá parametr widgetu `excludeLockerProvider=ZBox`. V dokumentaci DPD není, ale widget ho čte a API podle něj Z-BOXy vyřadí (ověřeno 7. 11. 2026: 17 192 míst v ČR, bez Z-BOXů 13 082). Pokud ho DPD jednou zruší, Z-BOXy se v mapě zase objeví, nic jiného se nerozbije.

## Co dělá

- Pod sazbou DPD Pickup v pokladně ukáže tlačítko „Vybrat výdejní místo“, které otevře mapu DPD v jazyce webu a v zemi doručení.
- Bez vybraného místa nejde objednávku odeslat.
- Vybrané místo se uloží k objednávce (`_velrion_dpd_point_id`, `_velrion_dpd_point`) a ukáže se v administraci, v e-mailech, na děkovací stránce i v účtu zákazníka.

Zásilky se zakládají v portálu DPD jako dosud, plugin na API DPD napojený není. Funguje s klasickou pokladnou (shortcode `[woocommerce_checkout]`), ne s blokovou.
