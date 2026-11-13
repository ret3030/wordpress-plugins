# Epoxis Hardening

Jednosouborový plugin pro WordPress a WooCommerce (PHP 8.0+). Bez nastavení, vše se zapne aktivací.

## Co dělá

| Oblast | Řešení |
|---|---|
| Výpis uživatelů | Zablokuje `/wp-json/wp/v2/users` pro nepřihlášené, `?author=N` a `/author/...` přesměruje na úvodní stránku, z oEmbed odstraní autora |
| Přihlášení | Generická chybová hláška (nevyzradí, zda je špatné jméno, nebo heslo) |
| Povrch útoku | Vypne XML-RPC a hlavičku `X-Pingback`, odstraní generator, RSD a WLW odkazy, vypne editor souborů v administraci |
| Info soubory | Smaže `readme.html` a `license.txt` při aktivaci a po každé aktualizaci jádra |
| Hlavičky | HSTS (1 rok), `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, bezpečná podmnožina CSP (`base-uri`, `object-src`, `frame-ancestors`), odstraní `X-Powered-By` |
| Sitemap | Odebere sitemapu uživatelů, vyloučí košík, pokladnu a Můj účet |
| robots.txt | Doplní blokaci vyhledávání, řazení, filtrů a stránek obchodu. Zachová pravidla Woo a WPForms |
| security.txt | Servíruje `/.well-known/security.txt` (RFC 9116). Kontakt je e-mail správce, nebo konstanta `EPOXIS_SECURITY_CONTACT` ve `wp-config.php` |

## Instalace

1. Nahrajte `epoxis-hardening.zip` v administraci (Pluginy, Instalace pluginů, Nahrát plugin) a aktivujte.
2. Ověřte: `curl -sI https://www.epoxis.cz/` (hlavičky), `/wp-json/wp/v2/users` (404), `/robots.txt`, `/.well-known/security.txt`.
3. Pokud používáte cache plugin nebo CDN, vyprázdněte cache.

## Co plugin záměrně nedělá

- **Plná CSP s `script-src`.** Rozbila by Elementor a platební bránu. Zavádějte ji zvlášť v režimu `Content-Security-Policy-Report-Only`.
- **Omezení počtu pokusů o přihlášení.** Za reverzní proxy hostingu není z PHP spolehlivě znát IP, takže by hrozilo zablokování všech včetně vás. Řešte WAF hostingu, Wordfence nebo 2FA.
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
