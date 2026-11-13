<?php
// Spuštění: php tests/run.php
// Testuje logiku pluginu se stuby WordPressu, které zachytí registrované hooky.

define( 'ABSPATH', sys_get_temp_dir() . '/epoxis-test-' . getmypid() . '/' );
define( 'YEAR_IN_SECONDS', 31536000 );
mkdir( ABSPATH );

$hooks = [];
$user_logged_in = false;
$home = 'https://www.epoxis.cz/';

function add_filter( $tag, $fn, $prio = 10, $args = 1 ) { global $hooks; $hooks[ $tag ][] = $fn; }
function add_action( $tag, $fn, $prio = 10, $args = 1 ) { add_filter( $tag, $fn, $prio, $args ); }
function remove_action() {}
function register_activation_hook() {}
function is_user_logged_in() { global $user_logged_in; return $user_logged_in; }
function home_url( $p = '' ) { global $home; return rtrim( $home, '/' ) . ( $p ?: '/' ); }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function wc_get_page_id( $p ) { return [ 'cart' => 10, 'checkout' => 11, 'myaccount' => 12 ][ $p ]; }
function get_permalink( $id ) { return "https://www.epoxis.cz/page-$id/"; }
function __return_false() { return false; }
function __return_empty_string() { return ''; }

$transients = [];
function get_transient( $k ) { global $transients; return $transients[ $k ] ?? false; }
function set_transient( $k, $v ) { global $transients; $transients[ $k ] = $v; }
function delete_transient( $k ) { global $transients; unset( $transients[ $k ] ); }
class WP_Error { public $code; public function __construct( $code = '' ) { $this->code = $code; } }
$_SERVER['REMOTE_ADDR'] = '203.0.113.5';

$fails = 0;
function check( string $name, bool $ok ) {
	global $fails;
	echo ( $ok ? "ok    " : "FAIL  " ) . $name . "\n";
	$fails += $ok ? 0 : 1;
}
function run( string $tag, ...$args ) {
	global $hooks;
	$value = $args[0];
	foreach ( $hooks[ $tag ] as $fn ) {
		$value = $fn( ...$args );
	}
	return $value;
}

require __DIR__ . '/../epoxis-hardening.php';

// REST users
$endpoints = [ '/wp/v2/users' => 1, '/wp/v2/users/(?P<id>[\d]+)' => 1, '/wp/v2/posts' => 1 ];
$anon = run( 'rest_endpoints', $endpoints );
check( 'anonym nevidí /users', ! isset( $anon['/wp/v2/users'] ) && ! isset( $anon['/wp/v2/users/(?P<id>[\d]+)'] ) );
check( 'anonym vidí /posts', isset( $anon['/wp/v2/posts'] ) );
$user_logged_in = true;
check( 'přihlášený vidí /users', isset( run( 'rest_endpoints', $endpoints )['/wp/v2/users'] ) );
$user_logged_in = false;

// Přihlašovací chyba a oEmbed
check( 'generická chyba přihlášení', run( 'login_errors', 'Neplatné uživatelské jméno root' ) === 'Přihlášení se nezdařilo.' );
$oembed = run( 'oembed_response_data', [ 'title' => 'x', 'author_name' => 'david', 'author_url' => 'u' ] );
check( 'oEmbed bez autora', ! isset( $oembed['author_name'] ) && ! isset( $oembed['author_url'] ) && isset( $oembed['title'] ) );

// X-Pingback
check( 'X-Pingback odstraněn', run( 'wp_headers', [ 'X-Pingback' => 'x', 'Link' => 'y' ] ) === [ 'Link' => 'y' ] );

// Hlavičky
$h = epoxis_security_headers();
check( 'HSTS na https', isset( $h['Strict-Transport-Security'] ) );
check( 'základní hlavičky', isset( $h['X-Content-Type-Options'], $h['X-Frame-Options'], $h['Referrer-Policy'], $h['Permissions-Policy'], $h['Content-Security-Policy'] ) );
$home = 'http://localhost/';
check( 'HSTS ne na http', ! isset( epoxis_security_headers()['Strict-Transport-Security'] ) );
$home = 'https://www.epoxis.cz/';

// Sitemap
check( 'sitemap uživatelů odstraněna', run( 'wp_sitemaps_add_provider', new stdClass(), 'users' ) === false );
check( 'sitemap příspěvků zůstává', run( 'wp_sitemaps_add_provider', new stdClass(), 'posts' ) instanceof stdClass );
$args = run( 'wp_sitemaps_posts_query_args', [ 'post__not_in' => [ 5 ] ], 'page' );
check( 'sitemap vyloučí Woo stránky a zachová stávající', $args['post__not_in'] === [ 5, 10, 11, 12 ] );
check( 'sitemap nesahá na jiné typy', run( 'wp_sitemaps_posts_query_args', [], 'product' ) === [] );

// robots.txt
$base = "User-agent: *\nDisallow: /wp-admin/\n\nSitemap: https://www.epoxis.cz/wp-sitemap.xml\n";
$robots = run( 'robots_txt', $base, true );
check( 'robots zachová původní výstup', str_starts_with( $robots, $base ) );
check( 'robots blokuje košík a pokladnu', str_contains( $robots, 'Disallow: /page-10/' ) && str_contains( $robots, 'Disallow: /page-11/' ) && str_contains( $robots, 'Disallow: /page-12/' ) );
check( 'robots blokuje vyhledávání', str_contains( $robots, 'Disallow: /?s=' ) );
check( 'robots nechá neveřejný web být', run( 'robots_txt', 'Disallow: /', false ) === 'Disallow: /' );

// security.txt
$txt = epoxis_security_txt( 'sec@example.cz', 1700000000 );
check( 'security.txt kontakt', str_contains( $txt, "Contact: mailto:sec@example.cz\n" ) );
check( 'security.txt Expires za rok', str_contains( $txt, 'Expires: 2024-11-' ) || str_contains( $txt, 'Expires: 2024-' ) );

// Mazání readme.html a license.txt
touch( ABSPATH . 'readme.html' );
touch( ABSPATH . 'license.txt' );
epoxis_delete_info_files();
check( 'readme.html a license.txt smazány', ! file_exists( ABSPATH . 'readme.html' ) && ! file_exists( ABSPATH . 'license.txt' ) );

// Skrytá adresa přihlášení
check( 'naše adresa otevře přihlášení', 'login' === epoxis_login_route( '/login_55023', '', 'login_55023' ) );
check( 'adresa s lomítkem a parametry otevře přihlášení', 'login' === epoxis_login_route( '/login_55023/?redirect_to=x', '', 'login_55023' ) );
check( 'adresa v podsložce webu', 'login' === epoxis_login_route( '/blog/login_55023', '/blog', 'login_55023' ) );
check( 'přímý wp-login.php je blokovaný', 'blocked' === epoxis_login_route( '/wp-login.php', '', 'login_55023' ) );
check( 'wp-login.php s akcí a lomítkem je blokovaný', 'blocked' === epoxis_login_route( '/wp-login.php/?action=lostpassword', '', 'login_55023' ) );
check( 'běžné stránky projdou', 'pass' === epoxis_login_route( '/produkt/henry/', '', 'login_55023' ) && 'pass' === epoxis_login_route( '/wp-admin/', '', 'login_55023' ) && 'pass' === epoxis_login_route( '/', '', 'login_55023' ) );
check( 'podobný název není blokovaný', 'pass' === epoxis_login_route( '/wp-login.phpx', '', 'login_55023' ) && 'pass' === epoxis_login_route( '/login_550230', '', 'login_55023' ) );
check( 'odkazy na přihlášení vedou na naši adresu', 'https://www.epoxis.cz/login_55023?action=lostpassword' === run( 'site_url', 'https://www.epoxis.cz/wp-login.php?action=lostpassword', 'wp-login.php?action=lostpassword' ) );
check( 'ostatní odkazy se nemění', 'https://www.epoxis.cz/wp-login.php-neco' === run( 'site_url', 'https://www.epoxis.cz/wp-login.php-neco', 'neco' ) );

// Zámek po chybných pokusech
$fail = function () { foreach ( $GLOBALS['hooks']['wp_login_failed'] as $fn ) { $fn( 'root', new WP_Error() ); } };
$locked_auth = function () { return run( 'authenticate', 'ok-user' ); };
for ( $i = 0; $i < 4; $i++ ) { $fail(); }
check( '4 chyby ještě nezamknou', 'ok-user' === $locked_auth() && 'Přihlášení se nezdařilo.' === run( 'login_errors', 'x' ) );
$fail();
check( '5. chyba zamkne přihlášení i pro správné heslo', $locked_auth() instanceof WP_Error );
check( 'při zámku je jiná hláška', str_contains( run( 'login_errors', 'x' ), 'Příliš mnoho' ) );
$count = $GLOBALS['transients'][ epoxis_login_key() ];
$fail(); $fail();
check( 'během zámku se pokusy nepočítají', $count === $GLOBALS['transients'][ epoxis_login_key() ] );
$_SERVER['REMOTE_ADDR'] = '198.51.100.7';
check( 'jiná IP zamčená není', 'ok-user' === $locked_auth() );
$_SERVER['REMOTE_ADDR'] = '203.0.113.5';
unset( $GLOBALS['transients'][ epoxis_login_key() ] ); // uplynutí 15 minut
check( 'po uplynutí zámku jde přihlášení znovu', 'ok-user' === $locked_auth() );
$fail(); $fail();
run( 'wp_login', 'david' );
check( 'úspěšné přihlášení vynuluje počítadlo', ! isset( $GLOBALS['transients'][ epoxis_login_key() ] ) );

// Nouzový vypínač (musí být poslední, konstantu nelze zrušit)
for ( $i = 0; $i < 5; $i++ ) { $fail(); }
define( 'EPOXIS_LOGIN_PROTECTION_OFF', true );
check( 'nouzový vypínač vypne zámek', 'ok-user' === $locked_auth() );
check( 'nouzový vypínač vrátí wp-login.php v odkazech', 'https://www.epoxis.cz/wp-login.php' === run( 'site_url', 'https://www.epoxis.cz/wp-login.php', 'wp-login.php' ) );

rmdir( ABSPATH );

exit( $fails ? 1 : 0 );
