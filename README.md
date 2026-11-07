# Velrion Images

Optimalizace fotek přímo při nahrání do knihovny médií. Obecný plugin pro všechny weby, bez externí služby (používá editor obrázků WordPressu, Imagick nebo GD na serveru).

## Co dělá

- **Plná velikost:** fotka se zmenší na nejdelší stranu max. 2400 px, otočí podle EXIF a uloží v kvalitě 82 jako JPEG (výchozí) nebo WebP. **Originál se neukládá**, převod je nevratný.
- **JPEG místo WebP u plné velikosti** kvůli katalogu Meta (Facebook for WooCommerce) a Google Merchant, které podle dostupných zdrojů přijímají jen JPEG a PNG.
- **Zmenšeniny**, které web zobrazuje (náhledy WooCommerce, Blocksy, Elementor), vznikají ve **WebP**.
- **PNG** (grafika, průhlednost) se převádí na WebP. GIF a SVG zůstávají beze změny.
- Už optimalizovaná fotka, kterou není třeba zmenšovat, se nepřekódovává, pokud by tím nezmenšila.
- WordPress nevytváří kopii `-scaled`.
- EXIF (poloha, fotoaparát) odstraňuje Imagick při zmenšení, tedy u všech fotek z telefonu. U fotek menších než limit zůstane.

Platí jen pro nově nahrané fotky. Stávající knihovna se nemění.

## Nastavení

**Nastavení → Média → Optimalizace fotek:** nejdelší strana (800 až 6000 px), kvalita (40 až 100) a formát plné velikosti (JPEG / WebP).

## Náhrada CompressX

1. Aktivovat Velrion Images.
2. **Deaktivovat CompressX**: při deaktivaci sám odstraní svá pravidla z `.htaccess` (bloky `# BEGIN CompressX`), přes která posílal AVIF kopie.
3. **Smazat CompressX** v Pluginech: smaže i svá nastavení.
4. Ve Správci souborů smazat složky `wp-content/compressx-nextgen` (AVIF kopie, na epoxis.cz cca 194 MB) a `wp-content/compressx`.
5. Zkontrolovat, že v `.htaccess` (kořen webu, `wp-content`, `wp-content/uploads`) nezůstal blok `CompressX`, a vymazat cache.
6. Zkušebně nahrát fotku z telefonu a ověřit velikost souboru a zmenšeniny `.webp` v `wp-content/uploads`.
