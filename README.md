# WordPress plugins

Vlastní WordPress pluginy. Každý plugin má svou větev se soubory v kořeni, hotové zipy k nahrání do WordPressu jsou ve složce `dist/` v této větvi.

| Větev | Plugin | Verze | Zip | Popis |
|-------|--------|-------|-----|-------|
| `velrion-backup` | Velrion Backup | 2.1.0 | [stáhnout](https://github.com/ret3030/wordpress-plugins/raw/main/dist/velrion-backup.zip) | Záloha celého webu s týdenním cronem, kontrolou integrity a obnovou na jiný web. Volitelný počet uchovávaných záloh (1 až 5) a záloha jen médií. |
| `velrion-images` | Velrion Images | 1.0.0 | [stáhnout](https://github.com/ret3030/wordpress-plugins/raw/main/dist/velrion-images.zip) | Optimalizace fotek při nahrání: zmenšení, JPEG/WebP, bez originálu v plné velikosti. |
| `velrion-images-bulk` | Velrion Images Bulk | 1.0.1 | [stáhnout](https://github.com/ret3030/wordpress-plugins/raw/main/dist/velrion-images-bulk.zip) | Jednorázová optimalizace stávající knihovny médií se zálohou a návratem, po použití smazat. |
| `velrion-review-request` | Velrion Review Request | 1.0.0 | [stáhnout](https://github.com/ret3030/wordpress-plugins/raw/main/dist/velrion-review-request.zip) | E-mail se žádostí o recenzi X dní po dokončení objednávky. |
| `velrion-snippets` | Velrion Snippets | 1.1.0 | [stáhnout](https://github.com/ret3030/wordpress-plugins/raw/main/dist/velrion-snippets.zip) | Správa PHP, CSS, JS a HTML snippetů na jednom místě. |
| `velrion-dpd-pickup` | Velrion DPD Pickup | 1.2.0 | [stáhnout](https://github.com/ret3030/wordpress-plugins/raw/main/dist/velrion-dpd-pickup.zip) | Výběr výdejního místa nebo boxu DPD (včetně AlzaBoxů) v pokladně. |
| `velrion-admin` | Velrion Admin | 1.0.0 | [stáhnout](https://github.com/ret3030/wordpress-plugins/raw/main/dist/velrion-admin.zip) | Jednoduché menu pro administrátory bez nástěnky, s přepínačem na celé menu. |
| `velrion-shop-manager` | Velrion Shop Manager | 1.1.0 | [stáhnout](https://github.com/ret3030/wordpress-plugins/raw/main/dist/velrion-shop-manager.zip) | Zjednodušená administrace pro správce obchodu, nahrazuje Adminimize a User Role Editor. |
| `epoxis-faq` | Epoxis FAQ | 1.0.1 | [stáhnout](https://github.com/ret3030/wordpress-plugins/raw/main/dist/epoxis-faq.zip) | FAQ pro epoxis.cz. |
| `epoxis-gravirovani` | Epoxis gravírování | 1.2.0 | [stáhnout](https://github.com/ret3030/wordpress-plugins/raw/main/dist/epoxis-gravirovani.zip) | Volitelné gravírování u vybraných produktů na epoxis.cz. |
| `epoxis-odznaky` | Epoxis odznaky | 2.0.0 | [stáhnout](https://github.com/ret3030/wordpress-plugins/raw/main/dist/epoxis-odznaky.zip) | Odznaky na produktech: hotové ikony (ekologický výrobek, vyměnitelná žárovka, gravírování) nebo text, podle kategorií či produktů. |
| `epoxis-hardening` | Epoxis Hardening | 1.0.0 | [stáhnout](https://github.com/ret3030/wordpress-plugins/raw/main/dist/epoxis-hardening.zip) | Zabezpečení WordPressu a WooCommerce: skrytí uživatelů, bezpečnostní hlavičky, XML-RPC, sitemap, robots.txt, security.txt. |
| `custom-gallery` | Redakční galerie | 1.0.3 | [stáhnout](https://github.com/ret3030/wordpress-plugins/raw/main/dist/custom-gallery.zip) | Widget galerie pro Elementor, obsah se spravuje v Média → Galerie. |

## Instalace

Stáhnout zip z tabulky a nahrát ho v **Pluginy → Přidat nový → Nahrát plugin**. Při nahrání novější verze WordPress nabídne nahrazení stávající.

## Nová verze zipů

Po změně v některé pluginové větvi spustit v `main`:

```sh
./build-zips.sh
git add dist && git commit -m "Rebuild plugin zips"
```

Skript zabalí všechny pluginové větve do `dist/<větev>.zip` se složkou pluginu uvnitř. Při změně verze upravit i sloupec Verze v tabulce.
