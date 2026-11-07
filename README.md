# Redakční galerie pro Elementor

Elementor widget, jehož obsah se spravuje mimo Elementor editor — v **Média → Galerie**.
Funguje s **Elementor free**.

## Instalace

1. Nahraj `custom-gallery.zip` v **Pluginy → Přidat nový → Nahrát plugin**, nebo rozbal složku `custom-gallery/` do `wp-content/plugins/`.
2. Aktivuj plugin.

## Použití

**Správa obsahu (shop manager):** Média → Galerie. Založ galerii, klikni na *Přidat obrázky*, vyber je v knihovně médií, pořadí uprav přetažením, ulož.

**Popisky kusů:** berou se z nativních polí přílohy, žádné druhé úložiště nevzniká.

| Pole přílohy | Zobrazí se jako | Příklad |
|---|---|---|
| Název | Jméno kusu | `Lampa Aurora` |
| Popisek | Specifikace pod jménem | `mosaz · dub · v. 42 cm · 2025` |

Pozor: WordPress vyplní Název automaticky z názvu souboru, takže neupravená fotka se ukáže jako `IMG_2043`. Název je potřeba přepsat v knihovně médií.

**Vložení na stránku:** v Elementoru vyhledej widget *Redakční galerie* (kategorie General) a v poli *Která galerie* vyber zdroj.

## Designové rozhodnutí

Výchozí nastavení je **zděné (masonry)** rozvržení, ne mřížka. U kusové výroby má každý kus jinou proporci — závěsná lampa je vysoká, stolní podsaditá — a jednotný ořez na čtverec obojí komolí. Mřížka je k dispozici v *Rozvržení → Typ*, pokud máš fotky nafocené jednotně.

Signature prvek je **rozsvícení**: fotky jsou v klidu ztlumené na `brightness(.88)`, takže mřížka čte jako místnost s nerozsvícenými lampami. Po najetí se kus prosvětlí a zezadu prosákne teplá záře v barevné teplotě 2700 K. Vypíná se v *Světlo → Rozsvítit při najetí*, barva se dá přenastavit tamtéž.

Na dotykových zařízeních (`@media (hover: none)`) se ztlumení i skrývání štítku ruší — bez myši by se nedalo rozsvítit.

Plugin **nenačítá žádná externí písma** (Google Fonts jsou v EU kvůli GDPR ošemetné). Jméno kusu dědí typografii šablony, specifikace používá systémový monospace prostrkaný do podoby raženého štítku. Obojí se dá přenastavit v sekci *Štítek*.

## Jak to funguje

| Vrstva | Kde |
|---|---|
| Uložení dat | `wp_options` → `cg_galleries` (pole attachment ID) |
| Admin UI | `includes/class-cg-admin.php` + `assets/admin.js` (wp.media + jQuery UI sortable) |
| Widget | `includes/class-cg-widget.php` |

Obrázky se **nekopírují** — ukládají se jen ID příloh. Smazání galerie ani odinstalace pluginu se knihovny médií nedotkne.

## Poznámky

- **Oprávnění**: výchozí capability je `manage_woocommerce`, tedy role **Správce obchodu (shop_manager)** a **Administrátor**. Redaktor přístup nemá. Pokud je WooCommerce vypnuté, plugin se automaticky vrátí k `edit_pages`, aby stránka nezmizela i adminovi.

  Změnit to jde filtrem, bez zásahu do pluginu:

  ```php
  add_filter( 'cg_capability', function () {
      return 'edit_pages';
  } );
  ```
- **Lightbox** používá vestavěný Elementor lightbox. Musí být zapnutý v *Elementor → Nastavení → Lightbox*.
- Nově založená galerie se v rozbalovacím seznamu widgetu objeví **po obnovení Elementor editoru** — seznam se načítá při registraci controls.
- **Pořadí v zděném rozvržení** jde po sloupcích shora dolů (CSS `column-count`), ne zleva doprava po řádcích. U reference kusů to nevadí; kdyby ano, přepni na mřížku nebo se doplní JS masonry.
- Pokud budeš chtít galerií hodně (desítky), přesuň úložiště z `wp_options` do vlastního post typu; jedna option s velkým polem se u malého počtu galerií chová dobře, u velkého už ne.

## Rozšíření, která dávají smysl

- Popisky u obrázků → uložit vedle ID i `caption` a vypsat pod obrázek.
- Masonry rozvržení → `column-count` místo gridu, nebo napojit Elementorem bundlovaný `imagesloaded`.
- Lazy loading je řešený nativně přes `wp_get_attachment_image()`.
