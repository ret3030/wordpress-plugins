# Epoxis Hardening

Jednosouborový plugin pro WordPress a WooCommerce (PHP 8.0+). Bez nastavení, vše se zapne aktivací.

## Co dělá

| Oblast | Řešení |
|---|---|
| Výpis uživatelů | Zablokuje `/wp-json/wp/v2/users` pro nepřihlášené, `?author=N` a `/author/...` přesměruje na úvodní stránku, z oEmbed odstraní autora |
| Přihlášení | Skrytá adresa přihlášení (`wp-login.php` vrací 404), zámek na 15 minut po 5 chybných pokusech z jedné IP, generická chybová hláška (nevyzradí, zda je špatné jméno, nebo heslo) |
| Povrch útoku | Vypne XML-RPC a hlavičku `X-Pingback`, odstraní generator, RSD a WLW odkazy, vypne editor souborů v administraci |
| Info soubory | Smaže `readme.html` a `license.txt` při aktivaci a po každé aktualizaci jádra |
| Hlavičky | HSTS (1 rok), `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, bezpečná podmnožina CSP (`base-uri`, `object-src`, `frame-ancestors`), odstraní `X-Powered-By` |
| Sitemap | Odebere sitemapu uživatelů, vyloučí košík, pokladnu a Můj účet |
| robots.txt | Doplní blokaci vyhledávání, řazení, filtrů a stránek obchodu. Zachová pravidla Woo a WPForms |
| security.txt | Servíruje `/.well-known/security.txt` (RFC 9116). Kontakt je e-mail správce, nebo konstanta `EPOXIS_SECURITY_CONTACT` ve `wp-config.php` |

## Instalace

1. **Nejdřív deaktivuj SiteGuard** (v druhém okně prohlížeče zůstaň přihlášený, kdyby se něco pokazilo). Oba pluginy nesmí běžet současně, přejmenovávaly by přihlášení proti sobě. Po deaktivaci je přihlášení na chvíli na `/wp-login.php`.
   Pak nahraj `epoxis-hardening.zip` v **Pluginy → Přidat nový → Nahrát plugin**. Starší verze se nahradí a plugin zůstane aktivní, přihlášení se přesune na skrytou adresu. SiteGuard pak můžeš smazat, Hardening přebírá všechno, co dělal (skrytá adresa, zámek, jednotná chyba, vypnutý XML-RPC).
2. Přihlášení zůstává na **stejné adrese jako dosud** (`/login_55023`). Zkus se přihlásit, odhlásit a obnovit heslo.
3. Ověř: `curl -sI https://www.epoxis.cz/` (hlavičky), `/wp-json/wp/v2/users` (404), `/wp-login.php` (404), `/robots.txt`, `/.well-known/security.txt`.
4. Pokud používáš cache plugin nebo CDN, vyprázdni cache.

### Nastavení ve `wp-config.php` (nepovinné)

| Konstanta | Účel |
|---|---|
| `EPOXIS_LOGIN_SLUG` | jiná adresa přihlášení (výchozí `login_55023`) |
| `EPOXIS_SECURITY_CONTACT` | kontakt v `security.txt` (výchozí e-mail správce webu) |
| `EPOXIS_LOGIN_PROTECTION_OFF` | `define( 'EPOXIS_LOGIN_PROTECTION_OFF', true );` je **nouzový vypínač**: vrátí běžný `wp-login.php` a vypne zámek. Použij, kdyby ses nemohl přihlásit. Řádek jde přidat přes FTP nebo správce souborů hostingu. |

Zámek počítá IP z `REMOTE_ADDR`. Kdyby hosting posílal všechny návštěvy přes jednu proxy, zamkl by se po pěti chybách všem. Nouzový vypínač to řeší.

## Co plugin záměrně nedělá

- **Plná CSP s `script-src`.** Rozbila by Elementor a platební bránu. Zavádějte ji zvlášť v režimu `Content-Security-Policy-Report-Only`.
- **Captcha a dvoufázové ověření (2FA).** Captcha zhoršuje použitelnost a 2FA je citlivá na chyby, proto je lepší osvědčený plugin (WP 2FA nebo Two Factor). 2FA pro administrátory je největší zbývající krok k bezpečnosti.
- **Odstranění `?ver=` z odkazů na skripty.** Zlepšení je zanedbatelné a po aktualizaci by prohlížeče držely staré soubory.
- **Změna loginu `root` a slugu autora `david`.** To je změna dat. Udělejte ji ručně v administraci (nový správce, starého smazat s převodem obsahu).
- **Souhlas s cookies (`_fbp`) a DMARC/DKIM.** Cookie lišta a DNS záznamy jsou mimo WordPress.

## Pravidla na úrovni serveru (OpenResty/nginx, doporučeno)

```nginx
location ~ ^/wp-admin/install\.php$ { return 404; }
location ~ /\.(?!well-known) { return 404; }
location ~* \.(sql|bak|old|zip|log|env)$ { return 404; }
location ~* ^/wp-content/uploads/.*\.php$ { return 404; }
```

Hosting už část blokuje sám (`.env`, `.git`, `xmlrpc.php`), ale `install.php` je dostupný (jen hlásí "Instalace už proběhla").

## Testy

```
php tests/run.php
```
