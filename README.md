# Velrion Shop Manager

Zjednodušená administrace pro roli Správce obchodu (WooCommerce). Nahrazuje pluginy Adminimize a User Role Editor.

## Co dělá

- **Při aktivaci** upraví roli Správce obchodu: odebere oprávnění, která umožňovala měnit nastavení webu, instalovat šablony a upravovat jejich PHP soubory (`manage_options`, `edit_themes`, `edit_files` a další, viz `VELRION_SM_REMOVE_CAPS`), a přidá `translate_strings` pro překlad webu v TranslatePress.
- **Menu:** jen Produkty, Objednávky, Média (včetně Galerie), FAQ, Odznaky (plugin Epoxis odznaky), Kupóny a Překlad webu, v tomto pořadí (`VELRION_SM_MENU`).
- **Bez hlášky o nové verzi WordPressu** („Kontaktujte administrátora webu“). Aktualizace řeší administrátor, ten ji vidí dál.
- **Horní lišta:** jen název webu, překlad a účet s odhlášením.
- **Přihlášení:** místo nástěnky rovnou seznam produktů. Odkaz na konkrétní stránku (např. objednávku z e-mailu) zůstane.

Administrátor vidí administraci beze změny.

## Přechod z Adminimize a User Role Editor

1. Zálohovat web.
2. Aktivovat Velrion Shop Manager.
3. Deaktivovat a smazat Adminimize a User Role Editor.
4. Přihlásit se jako správce obchodu a projít produkty (úprava, nahrání fotek), objednávky a překlad.

Úprava oprávnění je trvalá a deaktivace pluginu ji nevrací. Menu a lišta se po deaktivaci vrátí do výchozího stavu.
