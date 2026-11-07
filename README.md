# Epoxis gravírování

Volitelné gravírování u vybraných produktů. Zákazník na produktové stránce zaškrtne „Přidat gravírování“, napíše text a vybere styl písma. Cena gravírování se připočte k ceně lampy a text i styl se uloží k položce objednávky.

## Instalace

Nahraj `epoxis-gravirovani.zip` v **Pluginy → Přidat nový → Nahrát plugin** a aktivuj. Vyžaduje WooCommerce.

## Nastavení

**WooCommerce → Nastavení → Produkty → Gravírování**

| Pole | Výchozí |
|---|---|
| Kategorie s gravírováním | žádná (např. Svícnové) |
| Cena | 290 Kč |
| Maximální počet znaků | 30 |

Gravírování se nabízí u všech lamp z vybraných kategorií, i u nově přidaných.

**Výjimky:** lampu mimo vybrané kategorie zapneš v Produkty → Upravit produkt → Data produktu → Obecné → zaškrtnout **Gravírování**. Funguje u jednoduchých i variabilních produktů.

## Co vidí zákazník

1. Pod výběrem varianty rámeček „Přidat gravírování +290 Kč“.
2. Po zaškrtnutí pole pro text s počítadlem znaků a dlaždice se styly písma. V dlaždicích se rovnou ukazuje zadaný text daným písmem.
3. V košíku a pokladně je u položky „Gravírování: …“ a „Styl gravírování: …“, cena položky obsahuje příplatek.

Lampa s jiným textem gravírování je v košíku samostatný řádek.

## Co vidí dílna

V detailu objednávky, v e-mailech a na faktuře je u položky:

- **Gravírování:** text přesně tak, jak ho zákazník napsal
- **Styl gravírování:** název stylu a písma, např. `Psací jemný (Halimum)`

## Styly a písma

| Klíč | Název | Písmo | Soubor |
|---|---|---|---|
| `psaci` | Psací jemný | Halimum | `assets/fonts/halimum.woff2` nebo `.ttf`, **zatím chybí** |
| `pevny` | Tvrdší | Lora | `assets/fonts/lora-regular.ttf` (licence OFL, `Lora-OFL.txt`) |

Písma se načítají z webu, ne z Google Fonts. CSS a písma se načtou jen na produktech s gravírováním.

**Halimum:** před nahráním souboru ověřit, že licence povoluje komerční použití a vložení na web (webfont). Soubor stačí pojmenovat `halimum.woff2` (nebo `halimum.ttf`) a nahrát do `assets/fonts/`. Do té doby náhled použije systémové psací písmo.

**Změna nebo přidání stylu:** pole `epx_engr_styles()` v `epoxis-gravirovani.php` a odpovídající `@font-face` + třída `.epx-engr__sample--<klíč>` v `assets/engraving.css`. Starých objednávek se změna nedotkne, mají uložený název stylu jako text.

## Jak to funguje

| Krok | Hook |
|---|---|
| Kontrola formuláře (prázdný text, délka, styl) | `woocommerce_add_to_cart_validation` |
| Uložení do košíku | `woocommerce_add_cart_item_data` |
| Příplatek k ceně | `woocommerce_add_cart_item` + `woocommerce_get_cart_item_from_session` |
| Zobrazení v košíku | `woocommerce_get_item_data` |
| Uložení do objednávky | `woocommerce_checkout_create_order_line_item` |

Příplatek se přičítá jen v těchto dvou hookách, ne v `woocommerce_before_calculate_totals`, který se volá opakovaně a cenu by přičetl vícekrát.

## Před nasazením otestovat

- Jednoduchý i variabilní produkt, se zaškrtnutým i nezaškrtnutým gravírováním
- Prázdný text a text přes limit: musí se ukázat chyba a formulář si vyplněné hodnoty ponechat
- Přepnutí měny na EUR (WooCommerce Currency Switcher): příplatek se má přepočítat
- Kombinace s nahráním fotky (WooCommerce Upload Files) u stejného produktu
- Objednávka: text a styl v adminu, v e-mailu zákazníkovi i na PDF faktuře
