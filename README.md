# WordPress plugins

Vlastní WordPress pluginy. Každý plugin má svou větev, soubory jsou v kořeni větve, takže se větev dá rovnou zabalit do zipu a nainstalovat.

| Větev | Plugin | Popis |
|-------|--------|-------|
| `velrion-backup` | Velrion Backup | Záloha celého webu s týdenním cronem, kontrolou integrity a obnovou na jiný web. |
| `velrion-snippets` | Velrion Snippets | Správa PHP, CSS, JS a HTML snippetů na jednom místě. |
| `velrion-dpd-pickup` | Velrion DPD Pickup | Výběr výdejního místa nebo boxu DPD (včetně AlzaBoxů) v pokladně. |
| `velrion-shop-manager` | Velrion Shop Manager | Zjednodušená administrace pro správce obchodu, nahrazuje Adminimize a User Role Editor. |
| `epoxis-faq` | Epoxis FAQ | FAQ pro epoxis.cz. |
| `epoxis-gravirovani` | Epoxis gravírování | Volitelné gravírování u vybraných produktů na epoxis.cz. |

## Zip k instalaci

```sh
git archive --format=zip --prefix=velrion-snippets/ -o velrion-snippets.zip velrion-snippets
```
