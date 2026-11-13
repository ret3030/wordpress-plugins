<?php
// Spuštění: php tests/run.php
// Testuje includes/badges.php se stuby WordPressu a WooCommerce.

define( 'ABSPATH', __DIR__ . '/' );
define( 'DAY_IN_SECONDS', 86400 );

$transients = array();
$db_posts   = array(); // id => [meta]
$queries    = 0;

class WC_Product {
	public $id;
	public $parent;
	public function __construct( $id, $parent = 0 ) { $this->id = $id; $this->parent = $parent; }
	public function get_id() { return $this->id; }
	public function get_parent_id() { return $this->parent; }
}

$hooks = array();
function add_action( $tag, $fn ) { global $hooks; $hooks[ $tag ][] = $fn; }
function add_filter( $tag, $fn ) { add_action( $tag, $fn ); }
function absint( $n ) { return abs( (int) $n ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_hex_color( $c ) { return preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', (string) $c ) ? $c : ''; }
function get_transient( $k ) { global $transients; return $transients[ $k ] ?? false; }
function set_transient( $k, $v ) { global $transients; $transients[ $k ] = $v; }
function delete_transient( $k ) { global $transients; unset( $transients[ $k ] ); }
function get_posts() { global $db_posts, $queries; $queries++; return array_map( function ( $id ) { return (object) array( 'ID' => $id ); }, array_keys( $db_posts ) ); }
function get_post_meta( $id, $key ) { global $db_posts; return $db_posts[ $id ][ $key ] ?? ''; }
function get_term_children( $id ) { return array( 1 => array( 10, 11 ) )[ $id ] ?? array(); } // 1 = Všechny lampy, děti 10 a 11
function wc_get_product_term_ids( $id ) { return array( 100 => array( 10 ), 101 => array( 12 ), 102 => array( 1 ) )[ $id ] ?? array(); }

$fails = 0;
function check( string $name, bool $ok ) {
	global $fails;
	echo ( $ok ? 'ok    ' : 'FAIL  ' ) . $name . "\n";
	$fails += $ok ? 0 : 1;
}

require __DIR__ . '/../includes/badges.php';
require __DIR__ . '/../includes/admin.php';

// Čištění: textový odznak (nastavení z formuláře).
$text_badge = array( 'type' => 'text', 'text' => 'Expedice do 72h 📦', 'bg_color' => '#609933', 'text_color' => '#ffffff', 'position' => 'top-left', 'offset_top' => '10', 'size' => 'medium', 'shape' => 'rectangle', 'show_on_single' => 'yes', 'custom_css' => '' );
$s      = epxb_clean_settings( $text_badge );
check( 'textový odznak zůstane textový', 'text' === $s['type'] );
check( 'text s emoji se zachová', 'Expedice do 72h 📦' === $s['text'] );
check( 'nezaškrtnuté zobrazení znamená ne', 'no' === $s['show_on_loop'] && 'yes' === $s['show_on_single'] );
check( 'pozice a tvar se zachovají', 'top-left' === $s['position'] && 'rectangle' === $s['shape'] );

$evil = epxb_clean_settings( array( 'type' => 'icon', 'icon' => 'nope', 'bg_color' => 'red;}</style><script>', 'text_color' => 'url(x)', 'position' => 'middle', 'size' => 'huge', 'shape' => '"><x', 'text' => '<b>ahoj</b>' ) );
check( 'barvy se ověřují', '#4a8f3f' === $evil['bg_color'] && '#ffffff' === $evil['text_color'] );
check( 'neplatné volby spadnou na výchozí', 'top-right' === $evil['position'] && 'medium' === $evil['size'] && 'circle' === $evil['shape'] && 'eco' === $evil['icon'] );
check( 'text bez HTML', 'ahoj' === $evil['text'] );
check( 'dlouhý text se zkrátí', 60 === mb_strlen( epxb_clean_settings( array( 'text' => str_repeat( 'á', 200 ) ) )['text'] ) );

// Ikony
check( 'všechny ikony mají SVG, popisek, barvu a roh', count( array_filter( epxb_presets(), function ( $p ) { return $p['svg'] && $p['label'] && sanitize_hex_color( $p['color'] ) && in_array( $p['position'], array( 'top-left', 'top-right' ), true ); } ) ) === count( epxb_presets() ) );
check( 'předvolby obsahují eco, bulb a engraving', isset( epxb_presets()['eco'], epxb_presets()['bulb'], epxb_presets()['engraving'] ) );
check( 'neznámá ikona nevrátí nic', '' === epxb_icon_svg( 'nope' ) );
foreach ( epxb_presets() as $key => $p ) {
	$xml = @simplexml_load_string( epxb_icon_svg( $key ) );
	check( "SVG $key je platné XML", false !== $xml );
}

// HTML odznaku
$html = epxb_badge_html( array( 'id' => 7, 'settings' => epxb_clean_settings( array( 'type' => 'icon', 'icon' => 'bulb', 'text' => 'Vyměnitelná "žárovka"' ) ) ) );
check( 'ikonový odznak má svg, role a escapovaný popisek', str_contains( $html, '<svg' ) && str_contains( $html, 'role="img"' ) && str_contains( $html, 'aria-label="Vyměnitelná &quot;žárovka&quot;"' ) );
check( 'popisek je viditelný prvek vedle ikony', str_contains( $html, '<span class="epxb-label">Vyměnitelná &quot;žárovka&quot;</span>' ) );
check( 'ikonový odznak jde zaměřit dotykem a klávesnicí', str_contains( $html, 'tabindex="0"' ) );
$no_label = epxb_badge_html( array( 'id' => 7, 'settings' => epxb_clean_settings( array( 'type' => 'icon', 'icon' => 'bulb', 'text' => '' ) ) ) );
check( 'bez popisku se prvek popisku nevykreslí', ! str_contains( $no_label, 'epxb-label' ) );
$html = epxb_badge_html( array( 'id' => 8, 'settings' => $s ) );
check( 'textový odznak nemá svg ani popisek', ! str_contains( $html, '<svg' ) && ! str_contains( $html, 'epxb-label' ) && str_contains( $html, 'Expedice do 72h' ) );

// Výběr pro produkt
$icon_eco = epxb_clean_settings( array_merge( epxb_defaults(), array( 'icon' => 'eco' ) ) );
$db_posts = array(
	1 => array( '_badge_settings' => $icon_eco, '_assigned_categories' => array( 1 ) ),               // Všechny lampy (rodič 10 a 11)
	2 => array( '_badge_settings' => $s, '_assigned_products' => array( 101 ) ),                       // jen produkt 101, jen detail
	3 => array( '_badge_settings' => array_merge( $icon_eco, array( 'show_on_loop' => 'no' ) ), '_assigned_categories' => array( 12 ) ),
);
$ids = function ( $product, $where ) { return array_column( epxb_badges_for_product( $product, $where ), 'id' ); };

check( 'kategorie platí i pro podkategorii (produkt 100 je v 10)', array( 1 ) === $ids( new WC_Product( 100 ), 'loop' ) );
check( 'produkt 101 na detailu má ruční i kategoriový odznak', array( 2, 3 ) === $ids( new WC_Product( 101 ), 'single' ) );
check( 'produkt 101 ve výpisu nemá odznak 3 (vypnuto) ani 2 (jen detail)', array() === $ids( new WC_Product( 101 ), 'loop' ) );
check( 'varianta bere nadřazený produkt', array( 1 ) === $ids( new WC_Product( 999, 100 ), 'loop' ) );
check( 'produkt bez shody nemá nic', array() === $ids( new WC_Product( 555 ), 'single' ) );

// Cache: druhé čtení nesahá do databáze
$queries = 0;
epxb_get_badges();
epxb_get_badges();
check( 'odznaky se načtou jedním dotazem a pak z cache', 0 === $queries );
epxb_flush_cache();
epxb_get_badges();
check( 'po vyprázdnění cache se načte znovu', 1 === $queries );

// Zakládání hotových ikon
$options = array();
$inserted = array();
function get_option( $k, $default = false ) { global $options; return $options[ $k ] ?? $default; }
function update_option( $k, $v ) { global $options; $options[ $k ] = $v; }
function wp_insert_post( $a ) { global $inserted; $inserted[] = $a; return 900 + count( $inserted ); }
function is_wp_error() { return false; }
function wp_slash( $v ) { return $v; }
function update_post_meta() {}
$seed = $hooks['admin_init'][0];

$seed();
check( 'první spuštění založí všechny ikony jako koncepty', count( $inserted ) === count( epxb_presets() ) && array( 'draft' ) === array_unique( array_column( $inserted, 'post_status' ) ) );
$seed();
check( 'druhé spuštění nic nezaloží', count( $inserted ) === count( epxb_presets() ) );
$before = count( $inserted );
$options['epxb_seeded'] = array_diff( array_keys( epxb_presets() ), array( 'gift' ) ); // jedna ikona chybí
$seed();
check( 'chybějící ikona se doplní', $before + 1 === count( $inserted ) && 'Super dárek' === end( $inserted )['post_title'] );
$before = count( $inserted );
$options['epxb_seeded'] = '2.0.0'; // starý formát z verze 2.0.0
$seed();
check( 'starý formát nevytvoří duplicity', $before === count( $inserted ) );

// Rohy
$corners = epxb_corners_html( array(
	array( 'settings' => array( 'position' => 'top-right' ), 'html' => '<i>a</i>' ),
	array( 'settings' => array( 'position' => 'top-left' ), 'html' => '<i>b</i>' ),
	array( 'settings' => array( 'position' => 'top-right' ), 'html' => '<i>c</i>' ),
) );
check( 'odznaky ve stejném rohu jsou v jednom kontejneru', 1 === substr_count( $corners, 'epxb-top-right' ) && str_contains( $corners, '<i>a</i><i>c</i>' ) );
check( 'různé rohy mají různé kontejnery', 2 === substr_count( $corners, 'class="epxb-corner' ) );
check( 'bez odznaků je výstup prázdný', '' === epxb_corners_html( array() ) );

exit( $fails ? 1 : 0 );
