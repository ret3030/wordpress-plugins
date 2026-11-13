<?php
/**
 * Plugin Name: Epoxis Hardening
 * Description: Zabezpečení WordPressu a WooCommerce: výpis uživatelů, bezpečnostní hlavičky, XML-RPC, sitemap, robots.txt, security.txt.
 * Version: 1.0.0
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

// Přihlašovací chyba nesmí říct, jestli neplatné je jméno, nebo heslo.
add_filter( 'login_errors', fn() => 'Přihlášení se nezdařilo.' );

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
