# Epoxis FAQ pro Elementor

Časté dotazy se spravují v administraci v menu **FAQ**, na stránku se vkládají Elementor widgetem **FAQ**. Funguje s Elementorem free.

## Instalace

Nahraj `epoxis-faq.zip` v **Pluginy → Přidat nový → Nahrát plugin**, nebo zkopíruj složku `epoxis-faq/` do `wp-content/plugins/`. Pak plugin aktivuj.

## Správa obsahu (klient)

**FAQ → Přidat otázku**

| Pole | Co do něj patří |
|---|---|
| Název | Otázka |
| Obsah | Odpověď (tučné písmo, odkazy i seznamy fungují) |
| Skupiny (vpravo) | Zařazení, např. Lampy, Na míru, Doprava. Otázka může být ve více skupinách |
| Pořadí (vpravo) | Číslo; menší je výš. Doporučuju číslovat po deseti (10, 20, 30…), aby šlo později vložit otázku mezi |

Otázka se na webu zobrazí, jen když je **publikovaná**. Koncept nebo otázka v koši se neukáže.

Skupiny se zakládají v **FAQ → Skupiny**.

## Vložení na stránku

V Elementoru vyhledej widget **FAQ** (kategorie General).

- **Skupiny:** nic nevybráno = všechny otázky. Když vybereš skupiny, zobrazí se v pořadí, v jakém je vybereš.
- **Nadpisy skupin:** nad každou skupinou nadpis (H2 až H4).
- **Otevřená jen jedna odpověď:** otevřením otázky se předchozí zavře.
- **Strukturovaná data (FAQPage):** popis otázek pro Google a AI vyhledávače. Na jedné stránce zapnout jen u jednoho widgetu.
- Vzhled (barvy, písmo, odsazení, šipka, linka) je v záložce Styl. Výchozí barvy se dědí ze šablony.

Nová skupina se v seznamu widgetu objeví **po obnovení Elementor editoru**.

## Jak to funguje

| Vrstva | Kde |
|---|---|
| Otázky | vlastní typ obsahu `epx_faq` (název = otázka, obsah = odpověď, pořadí = `menu_order`) |
| Skupiny | taxonomie `epx_faq_group` |
| Widget | `includes/class-epx-faq-widget.php` |
| Styl | `assets/frontend.css`, načítá se jen na stránkách s widgetem |

- Rozbalování stojí na nativním HTML `<details>`, **žádný JavaScript**. Odpovědi jsou v HTML i zavřené, takže je vidí vyhledávače i TranslatePress.
- „Jen jedna otevřená“ používá atribut `name` na `<details>`. Starší prohlížeče ho ignorují a otevřou víc odpovědí najednou, nic se nerozbije.
- Otázky nemají vlastní URL ani se neobjevují v sitemapě.
- Přístup má každý, kdo smí upravovat příspěvky (administrátor, správce obchodu, editor).
- Deaktivace ani smazání pluginu otázky z databáze neodstraní. Po opětovné aktivaci jsou zpět.
- Google od roku 2023 zobrazuje rozbalovací FAQ přímo ve výsledcích hledání jen u úřadů a zdravotnických webů. Strukturovaná data přesto pomáhají vyhledávačům a AI asistentům porozumět obsahu.
