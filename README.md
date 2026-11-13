# Epoxis odznaky

Odznaky na produktech WooCommerce: buď hotové ikony (ekologický výrobek, vyměnitelná žárovka, vlastní gravírování a další), nebo vlastní text. Samostatný plugin, nahrazuje FX Badge.

## Instalace

Nahraj `epoxis-odznaky.zip` v **Pluginy → Přidat nový → Nahrát plugin** a aktivuj. Vyžaduje WooCommerce. Je to samostatný plugin, s FX Badge nesdílí data ani kód. FX Badge před aktivací deaktivuj, jinak se odznaky zobrazí dvakrát.

## Použití

Menu **Odznaky**. Po první návštěvě administrace tam najdeš hotové ikony jako **koncepty** (vypnuté):

| Ikona | Výchozí roh |
|---|---|
| Ekologický výrobek | vpravo nahoře |
| Vyměnitelná žárovka | vpravo nahoře |
| Vlastní gravírování | vpravo nahoře |
| Ruční výroba, Záruka, Expedice do 72 hodin, Super dárek, Nejprodávanější | vlevo nahoře |

U každé otevři **Upravit**, zvol vzhled a umístění, v části **Kde se odznak zobrazí** vyber kategorie nebo konkrétní produkty a **zveřejni**. Odznak se zobrazuje jen u zveřejněných.

| Pole | Význam |
|---|---|
| Typ | Ikona, nebo Text |
| Ikona | výběr z hotových, výběrem se doplní její barva a popisek (vlastní popisek se nepřepíše) |
| Popisek | u ikony se ukáže při najetí myší, u textu je to samotný text odznaku |
| Barvy | pozadí a ikona či text |
| Umístění | čtyři rohy, více odznaků ve stejném rohu se řadí pod sebe |
| Velikost, tvar | malý až velký, kruh, zaoblený, obdélník |
| Zobrazit | ve výpisu produktů, na detailu produktu |
| Kategorie | odznak platí i pro podkategorie (např. „Všechny Lampy“ pokryje všechny typy) |
| Konkrétní produkty | doplněk ke kategoriím, hledání podle názvu |

Seznam odznaků ukazuje náhled a kde se který zobrazuje.

## Co se oproti FX Badge změnilo

- Odznaky podle kategorií, ne jen podle ručně vybraných produktů.
- Ikony a popisek při najetí myší.
- Odznaky ve stejném rohu se nepřekrývají.
- Odznaky se načtou z cache, výpis produktů nedělá dotazy na odznaky.
- Hodnoty (barvy, volby) se ověřují. FX Badge je vypisoval do CSS bez kontroly.
- Nepřevzato: ruční odsazení v pixelech, vlastní CSS pole, PHP funkce `fxb_display()` a spol., stránka Nastavení s příručkou, přeložitelné řetězce.

## Přidání nové ikony

V `includes/badges.php` do pole `epxb_presets()` přidat klíč s popiskem, barvou, rohem a SVG tvary (čárová ikona 24x24). Po další návštěvě administrace se založí jako koncept.

## Testy

```
php tests/run.php
```
