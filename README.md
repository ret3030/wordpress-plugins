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

Platí pro nově nahrané fotky. Stávající knihovnu zpracuje hromadná optimalizace níže.

## Hromadná optimalizace stávající knihovny

**Média → Optimalizovat knihovnu.** U každé fotky (JPEG, PNG, WebP):

1. smaže originál v plné velikosti, který WordPress drží vedle verze `-scaled` a web ho nezobrazuje, a odebere ho z metadat,
2. hlavní soubor zmenší na nastavený limit a překóduje (natočení z EXIF se před tím zapeče do pixelů),
3. překóduje zmenšeniny,
4. smaže kopie `foto.jpg.webp` po dřívějších optimalizačních pluginech.

**Názvy, adresy a formáty souborů zůstávají**, takže odkazy v obsahu, Elementoru i katalogu Meta platí dál. Soubor se nahradí jen tehdy, když je nový menší. Běží po 5 fotkách přes AJAX (stránka musí zůstat otevřená), hotové fotky se označí a při dalším spuštění přeskočí. **Nevratné: nejdřív zálohovat média.**

Test na kopii skutečné složky epoxis.cz (2025/03, 27 fotek): 128 MB → 48 MB za 4 s, kvalita při 100% zvětšení beze změny.

## Nastavení

**Nastavení → Média → Optimalizace fotek:** nejdelší strana (800 až 6000 px), kvalita (40 až 100) a formát plné velikosti (JPEG / WebP).

## Náhrada CompressX

1. Aktivovat Velrion Images.
2. **Deaktivovat CompressX**: při deaktivaci sám odstraní svá pravidla z `.htaccess` (bloky `# BEGIN CompressX`), přes která posílal AVIF kopie.
3. **Smazat CompressX** v Pluginech: smaže i svá nastavení.
4. Ve Správci souborů smazat složky `wp-content/compressx-nextgen` (AVIF kopie, na epoxis.cz cca 194 MB) a `wp-content/compressx`.
5. Zkontrolovat, že v `.htaccess` (kořen webu, `wp-content`, `wp-content/uploads`) nezůstal blok `CompressX`, a vymazat cache.
6. Zkušebně nahrát fotku z telefonu a ověřit velikost souboru a zmenšeniny `.webp` v `wp-content/uploads`.
