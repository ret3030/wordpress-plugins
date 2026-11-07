# Velrion Snippets

Lehká správa vlastního kódu na jednom místě: PHP, CSS, JS a HTML snippety.

## Typy a umístění

| Typ  | Kde                                          |
|------|----------------------------------------------|
| PHP  | všude, jen web, jen administrace             |
| CSS  | web, administrace (vloží se do `<style>`)    |
| JS   | hlavička webu, patička webu, administrace (vloží se do `<script>`) |
| HTML | hlavička webu, začátek stránky, patička webu |

Priorita určuje pořadí: nižší číslo dřív, standard je 10.

## Ochrana proti chybám

- Při uložení nebo zapnutí PHP snippetu se zkontroluje syntaxe. Snippet s chybou zůstane vypnutý.
- Když snippet při spuštění vyhodí chybu nebo skončí fatální chybou, sám se vypne a v administraci se ukáže upozornění.
- Kdyby web přesto nešel načíst, do `wp-config.php` přidej `define( 'VELRION_SAFE_MODE', true );`. Tím se vypnou všechny PHP snippety.

Snippety jsou uložené ve volbě `velrion_snippets` v tabulce `wp_options`.
