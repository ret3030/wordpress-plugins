# Velrion Images Bulk

Jednorázová optimalizace stávající knihovny médií se zálohou a možností úplného návratu. Po potvrzení se plugin smaže a nic po sobě nenechá. Doplňuje Velrion Images, které optimalizuje nově nahrávané fotky.

## Co udělá s každou fotkou (JPEG, PNG, WebP)

1. Odebere originál v plné velikosti, který WordPress drží vedle verze `-scaled` a web ho nezobrazuje.
2. Hlavní soubor zmenší na nejdelší stranu 2400 px (nebo podle nastavení Velrion Images) a překóduje v kvalitě 82. Natočení z EXIF se před tím zapeče do pixelů.
3. Překóduje zmenšeniny.
4. Odebere kopie `foto.jpg.webp` po dřívějších optimalizačních pluginech.

**Názvy, adresy a formáty souborů zůstávají**, odkazy v obsahu, Elementoru i katalogu Meta platí dál. Soubor se nahradí jen tehdy, když je nový menší.

## Bezpečnost

Nic se nemaže natrvalo. Každý změněný nebo odebraný soubor se **přesune do `wp-content/velrion-images-backup`** (mimo `uploads`, se stejnou strukturou složek) a původní záznam fotky se uloží do databáze. Do úklidu je proto na disku potřeba zhruba tolik místa, kolik dnes zabírají média.

## Postup (Média → Optimalizovat knihovnu)

1. **Spustit / pokračovat**: běží po 5 fotkách, stránka musí zůstat otevřená. Přerušený běh jde spustit znovu.
2. **Kontrola webu**: úvodní stránka, galerie, produkty, košík. Při problému **Vrátit vše**: soubory i záznamy se vrátí přesně do původního stavu.
3. **Potvrdit a uklidit**: smaže zálohu a záznamy pluginu. Potom už návrat nejde a další optimalizace je zablokovaná, aby se fotky nekomprimovaly podruhé.
4. Plugin deaktivovat a **smazat**. Při smazání odstraní i poslední příznak v databázi (a případnou zálohu, pokud se krok 3 vynechal).

## Test

Kopie skutečné složky epoxis.cz `2025/03` (27 fotek): 128 MB → 48 MB. Po „Vrátit vše“ všechny soubory bajt po bajtu shodné s originálem a záznamy beze změny. Po úklidu žádná záloha ani záznamy.
