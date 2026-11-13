<?php
/**
 * Plugin Name: Epoxis Hardening
 * Description: Zabezpečení WordPressu a WooCommerce: skryté přihlášení se zámkem, výpis uživatelů, bezpečnostní hlavičky, XML-RPC, sitemap, robots.txt, security.txt.
 * Version: 1.1.0
 * Requires PHP: 8.0
 * Requires at least: 6.0
 * License: GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

// Úpravy souborů z administrace by po průniku do adminu umožnily spustit libovolný kód.
if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
	define( 'DISALLOW_FILE_EDIT', true );
}

// ---------------------------------------------------------------------------
// Únik uživatelských jmen
// ---------------------------------------------------------------------------

// REST seznam uživatelů ukazoval login "root" i bez přihlášení.
add_filter(
	'rest_endpoints',
	function ( array $endpoints ): array {
		if ( ! is_user_logged_in() ) {
			unset( $endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
		}
		return $endpoints;
	}
);

// ?author=1 a /author/jmeno/ prozrazují login přes přesměrování.
add_action(
	'template_redirect',
	function () {
		if ( ! is_user_logged_in() && ( isset( $_GET['author'] ) || is_author() ) ) {
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}
	},
	1
);

add_filter(
	'oembed_response_data',
	function ( array $data ): array {
		unset( $data['author_name'], $data['author_url'] );
		return $data;
	}
);

// ---------------------------------------------------------------------------
// Povrch útoku a verze
// ---------------------------------------------------------------------------

add_filter( 'xmlrpc_enabled', '__return_false' );
remove_action( 'wp_head', 'wp_generator' );
add_filter( 'the_generator', '__return_empty_string' );
remove_action( 'wp_head', 'rsd_link' );
remove_action( 'wp_head', 'wlwmanifest_link' );
add_filter( 'wp_headers', fn( array $h ) => array_diff_key( $h, [ 'X-Pingback' => 1 ] ) );

// readme.html a license.txt prozrazují verzi jádra; WordPress je při aktualizaci vrací.
function epoxis_delete_info_files() {
	foreach ( [ 'readme.html', 'license.txt' ] as $file ) {
		if ( file_exists( ABSPATH . $file ) ) {
			@unlink( ABSPATH . $file );
		}
	}
}
register_activation_hook( __FILE__, 'epoxis_delete_info_files' );
add_action(
	'upgrader_process_complete',
	function ( $upgrader, array $info ) {
		if ( 'core' === ( $info['type'] ?? '' ) ) {
			epoxis_delete_info_files();
		}
	},
	10,
	2
);

// ---------------------------------------------------------------------------
// Bezpečnostní hlavičky
// ---------------------------------------------------------------------------

// CSP je záměrně jen bezpečná podmnožina. Plná CSP s script-src rozbije Elementor a platební brány.
function epoxis_security_headers(): array {
	$headers = [
		'X-Content-Type-Options'  => 'nosniff',
		'X-Frame-Options'         => 'SAMEORIGIN',
		'Referrer-Policy'         => 'strict-origin-when-cross-origin',
		'Permissions-Policy'      => 'camera=(), microphone=(), geolocation=(), interest-cohort=()',
		'Content-Security-Policy' => "base-uri 'self'; object-src 'none'; frame-ancestors 'self'",
	];
	// HSTS se řídí adresou webu, ne is_ssl(), protože za reverzní proxy is_ssl() často lže.
	if ( str_starts_with( home_url(), 'https://' ) ) {
		$headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
	}
	return $headers;
}

add_action(
	'send_headers',
	function () {
		foreach ( epoxis_security_headers() as $name => $value ) {
			header( "$name: $value" );
		}
		header_remove( 'X-Powered-By' );
	}
);

// ---------------------------------------------------------------------------
// Sitemap
// ---------------------------------------------------------------------------

// Uživatelská sitemap by vypsala všechny autory a jejich slugy.
add_filter(
	'wp_sitemaps_add_provider',
	fn( $provider, string $name ) => 'users' === $name ? false : $provider,
	10,
	2
);

// Košík, pokladna a účet nemají být v indexu.
add_filter(
	'wp_sitemaps_posts_query_args',
	function ( array $args, string $post_type ): array {
		if ( 'page' === $post_type && function_exists( 'wc_get_page_id' ) ) {
			$private = array_filter( array_map( 'wc_get_page_id', [ 'cart', 'checkout', 'myaccount' ] ), fn( $id ) => $id > 0 );
			$args['post__not_in'] = array_merge( $args['post__not_in'] ?? [], $private );
		}
		return $args;
	},
	10,
	2
);

// ---------------------------------------------------------------------------
// robots.txt (doplňuje výstup WordPressu, Woo a WPForms, nepřepisuje ho)
// ---------------------------------------------------------------------------

function epoxis_robots_rules( array $private_paths ): string {
	$rules = [ 'Disallow: /?s=', 'Disallow: /search/', 'Disallow: /*?orderby=', 'Disallow: /*?*filter_', 'Disallow: /*?replytocom=' ];
	foreach ( $private_paths as $path ) {
		$rules[] = "Disallow: $path";
	}
	return "\n# Epoxis Hardening\nUser-agent: *\n" . implode( "\n", $rules ) . "\n";
}

add_filter(
	'robots_txt',
	function ( string $output, $public ): string {
		if ( ! $public ) {
			return $output;
		}
		$paths = [];
		if ( function_exists( 'wc_get_page_id' ) ) {
			foreach ( [ 'cart', 'checkout', 'myaccount' ] as $page ) {
				$id = wc_get_page_id( $page );
				if ( $id > 0 ) {
					$paths[] = wp_parse_url( get_permalink( $id ), PHP_URL_PATH );
				}
			}
		}
		return $output . epoxis_robots_rules( array_filter( $paths ) );
	},
	99,
	2
);

// ---------------------------------------------------------------------------
// /.well-known/security.txt (RFC 9116)
// ---------------------------------------------------------------------------

function epoxis_security_txt( string $contact, int $now ): string {
	return "Contact: mailto:$contact\nExpires: " . gmdate( 'Y-m-d\TH:i:s\Z', $now + YEAR_IN_SECONDS ) . "\nPreferred-Languages: cs, en\n";
}

// Kontakt se bere z EPOXIS_SECURITY_CONTACT, jinak z e-mailu správce webu.
add_action(
	'init',
	function () {
		$path = wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH );
		if ( '/.well-known/security.txt' !== $path ) {
			return;
		}
		$contact = defined( 'EPOXIS_SECURITY_CONTACT' ) ? EPOXIS_SECURITY_CONTACT : get_option( 'admin_email' );
		status_header( 200 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo epoxis_security_txt( $contact, time() ); // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}
);

// ---------------------------------------------------------------------------
// Přihlášení: skrytá adresa a zámek po chybných pokusech
// ---------------------------------------------------------------------------

// Nouzový vypínač: define( 'EPOXIS_LOGIN_PROTECTION_OFF', true ); ve wp-config.php vrátí běžný wp-login.php a vypne zámek.
function epoxis_login_protection_on(): bool {
	return ! defined( 'EPOXIS_LOGIN_PROTECTION_OFF' ) || ! EPOXIS_LOGIN_PROTECTION_OFF;
}

// Adresa přihlášení, výchozí je stejná jako dosud u SiteGuardu. Změna: define( 'EPOXIS_LOGIN_SLUG', 'neco-jineho' );
function epoxis_login_slug(): string {
	return defined( 'EPOXIS_LOGIN_SLUG' ) ? EPOXIS_LOGIN_SLUG : 'login_55023';
}

/** Co s požadavkem: 'login' (naše adresa), 'blocked' (přímý wp-login.php) nebo 'pass'. */
function epoxis_login_route( string $request_uri, string $home_path, string $slug ): string {
	$path = rtrim( (string) parse_url( $request_uri, PHP_URL_PATH ), '/' );
	if ( $path === rtrim( $home_path, '/' ) . '/' . $slug ) {
		return 'login';
	}
	return 'wp-login.php' === basename( $path ) ? 'blocked' : 'pass';
}

// WordPress staví všechny odkazy na přihlášení, odhlášení a obnovu hesla přes wp-login.php, ty přesměrujeme na naši adresu.
function epoxis_login_site_url( string $url, string $path ): string {
	return str_starts_with( $path, 'wp-login.php' ) ? str_replace( 'wp-login.php', epoxis_login_slug(), $url ) : $url;
}
add_filter(
	'site_url',
	function ( $url, $path ) {
		return epoxis_login_protection_on() ? epoxis_login_site_url( $url, (string) $path ) : $url;
	},
	10,
	2
);

add_action(
	'wp_loaded',
	function () {
		if ( ! epoxis_login_protection_on() ) {
			return;
		}
		$route = epoxis_login_route( $_SERVER['REQUEST_URI'] ?? '', (string) wp_parse_url( home_url(), PHP_URL_PATH ), epoxis_login_slug() );

		if ( 'blocked' === $route ) {
			wp_die( 'Stránka nebyla nalezena.', 'Nenalezeno', array( 'response' => 404 ) );
		}
		if ( 'login' === $route ) {
			// wp-login.php počítá s těmito proměnnými v globálním rozsahu.
			global $pagenow, $error, $interim_login, $action, $user_login;
			$pagenow = 'wp-login.php';
			require_once ABSPATH . 'wp-login.php';
			exit;
		}
	}
);

// Zámek podle IP: po 5 chybných pokusech se přihlášení na 15 minut zamkne, a to i pro správné heslo.
// Používá REMOTE_ADDR, což je skutečná adresa jen když hosting neposílá požadavky přes sdílenou proxy.
const EPOXIS_LOGIN_MAX_FAILS    = 5;
const EPOXIS_LOGIN_LOCK_SECONDS = 900;

function epoxis_login_key(): string {
	return 'epoxis_login_' . md5( $_SERVER['REMOTE_ADDR'] ?? '' );
}

function epoxis_login_locked(): bool {
	return epoxis_login_protection_on() && (int) get_transient( epoxis_login_key() ) >= EPOXIS_LOGIN_MAX_FAILS;
}

// Během zámku se pokusy nepočítají, aby útočník zámek neprodlužoval.
add_action(
	'wp_login_failed',
	function () {
		if ( epoxis_login_protection_on() && ! epoxis_login_locked() ) {
			set_transient( epoxis_login_key(), (int) get_transient( epoxis_login_key() ) + 1, EPOXIS_LOGIN_LOCK_SECONDS );
		}
	}
);

// Priorita 100: až po všech kontrolách hesla, jinak by je platné heslo přebilo.
add_filter( 'authenticate', fn( $user ) => epoxis_login_locked() ? new WP_Error( 'epoxis_locked', 'Příliš mnoho pokusů.' ) : $user, 100 );

add_action( 'wp_login', fn() => delete_transient( epoxis_login_key() ) );

// Chyba nesmí říct, jestli je neplatné jméno, nebo heslo. Při zámku řekneme jen to, že je zamčeno.
add_filter( 'login_errors', fn() => epoxis_login_locked() ? 'Příliš mnoho pokusů o přihlášení. Zkuste to za 15 minut.' : 'Přihlášení se nezdařilo.' );
