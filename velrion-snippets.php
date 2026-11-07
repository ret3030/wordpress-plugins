<?php
/**
 * Plugin Name:       Velrion Snippets
 * Description:       Lehká správa vlastního kódu na jednom místě: PHP, CSS, JS a HTML snippety s přepínačem zapnuto/vypnuto.
 * Version:           1.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Text Domain:       velrion-snippets
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const VELRION_OPTION = 'velrion_snippets';
const VELRION_ERROR_OPTION = 'velrion_error';

/** Kam se který typ snippetu vkládá: klíč místa => popisek. */
const VELRION_PLACES = array(
	'php'  => array(
		'everywhere' => 'Všude',
		'frontend'   => 'Jen web',
		'admin'      => 'Jen administrace',
	),
	'css'  => array(
		'frontend' => 'Web',
		'admin'    => 'Administrace',
	),
	'js'   => array(
		'head'   => 'Web, hlavička',
		'footer' => 'Web, patička',
		'admin'  => 'Administrace',
	),
	'html' => array(
		'head'   => 'Web, hlavička',
		'body'   => 'Web, začátek stránky',
		'footer' => 'Web, patička',
	),
);

/** WordPress hook, na kterém se vypíše CSS, JS a HTML podle místa. */
const VELRION_HOOKS = array(
	'css'  => array(
		'frontend' => 'wp_head',
		'admin'    => 'admin_head',
	),
	'js'   => array(
		'head'   => 'wp_head',
		'footer' => 'wp_footer',
		'admin'  => 'admin_footer',
	),
	'html' => array(
		'head'   => 'wp_head',
		'body'   => 'wp_body_open',
		'footer' => 'wp_footer',
	),
);

/**
 * Snippety jako pole id => [title, type, place, priority, active, code].
 * Jedna autoload volba: celý seznam je jeden dotaz, který WordPress stejně načítá při startu.
 */
function velrion_snippets(): array {
	$snippets = get_option( VELRION_OPTION, array() );
	return is_array( $snippets ) ? $snippets : array();
}

function velrion_save_snippets( array $snippets ): void {
	update_option( VELRION_OPTION, $snippets, true );
}

function velrion_deactivate( int $id, string $message ): void {
	$snippets = velrion_snippets();
	if ( isset( $snippets[ $id ] ) ) {
		$snippets[ $id ]['active'] = false;
		velrion_save_snippets( $snippets );
	}
	update_option( VELRION_ERROR_OPTION, array( 'id' => $id, 'message' => $message ), false );
}

/**
 * Kontrola syntaxe bez spuštění. Obal if (false) brání tomu, aby PHP při
 * kompilaci rovnou deklarovalo funkce, které už běžící snippet definoval.
 */
function velrion_php_syntax_error( string $code ): ?string {
	try {
		eval( 'if (false) {' . "\n" . $code . "\n" . '}' );
	} catch ( ParseError $error ) {
		return sprintf( 'Chyba syntaxe na řádku %d: %s', $error->getLine() - 1, $error->getMessage() );
	}
	return null;
}

/* ------------------------------------------------------------------ */
/* Spouštění                                                           */
/* ------------------------------------------------------------------ */

add_action( 'plugins_loaded', 'velrion_boot', 1 );

function velrion_boot(): void {
	$snippets = array_filter(
		velrion_snippets(),
		function ( $snippet ) {
			return ! empty( $snippet['active'] );
		}
	);
	uasort(
		$snippets,
		function ( $a, $b ) {
			return (int) $a['priority'] <=> (int) $b['priority'];
		}
	);

	foreach ( $snippets as $id => $snippet ) {
		if ( 'php' === $snippet['type'] ) {
			velrion_run_php( (int) $id, $snippet );
		} else {
			velrion_add_output( (int) $id, $snippet );
		}
	}
}

function velrion_run_php( int $id, array $snippet ): void {
	// Nouzový režim: define( 'VELRION_SAFE_MODE', true ); ve wp-config.php vypne všechny PHP snippety.
	if ( defined( 'VELRION_SAFE_MODE' ) && VELRION_SAFE_MODE ) {
		return;
	}
	if ( ( 'frontend' === $snippet['place'] && is_admin() ) || ( 'admin' === $snippet['place'] && ! is_admin() ) ) {
		return;
	}

	$GLOBALS['velrion_running'] = $id;
	try {
		eval( $snippet['code'] );
	} catch ( Throwable $error ) {
		velrion_deactivate( $id, sprintf( '%s (řádek %d)', $error->getMessage(), $error->getLine() ) );
	}
	$GLOBALS['velrion_running'] = null;
}

/** Fatální chyba přímo při spuštění snippetu: snippet vypneme, další načtení stránky už projde. */
register_shutdown_function(
	function () {
		$error = error_get_last();
		$fatal = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR;
		if ( empty( $GLOBALS['velrion_running'] ) || ! $error || ! ( $error['type'] & $fatal ) ) {
			return;
		}
		velrion_deactivate( (int) $GLOBALS['velrion_running'], $error['message'] );
	}
);

function velrion_add_output( int $id, array $snippet ): void {
	$hook = VELRION_HOOKS[ $snippet['type'] ][ $snippet['place'] ] ?? null;
	if ( ! $hook ) {
		return;
	}
	$code = $snippet['code'];
	$tag  = array(
		'css'  => 'style',
		'js'   => 'script',
		'html' => '',
	)[ $snippet['type'] ];

	add_action(
		$hook,
		function () use ( $id, $code, $tag ) {
			if ( $tag ) {
				printf( "<%1\$s id=\"velrion-%2\$d\">\n%3\$s\n</%1\$s>\n", $tag, $id, $code ); // phpcs:ignore WordPress.Security.EscapeOutput
			} else {
				echo $code . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
			}
		},
		(int) $snippet['priority']
	);
}

/* ------------------------------------------------------------------ */
/* Administrace                                                        */
/* ------------------------------------------------------------------ */

add_action(
	'admin_menu',
	function () {
		add_menu_page( 'Snippety', 'Snippety', 'manage_options', 'velrion-snippets', 'velrion_render_page', 'dashicons-editor-code', 81 );
	}
);

add_action( 'admin_post_velrion_save', 'velrion_handle_save' );
add_action( 'admin_post_velrion_toggle', 'velrion_handle_toggle' );
add_action( 'admin_post_velrion_delete', 'velrion_handle_delete' );

add_action(
	'admin_notices',
	function () {
		$error = get_option( VELRION_ERROR_OPTION );
		if ( ! $error || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$snippet = velrion_snippets()[ $error['id'] ] ?? array( 'title' => '#' . $error['id'] );
		printf(
			'<div class="notice notice-error"><p><strong>Velrion Snippets:</strong> snippet <a href="%s">%s</a> byl kvůli chybě vypnut. %s</p></div>',
			esc_url( velrion_url( array( 'edit' => $error['id'] ) ) ),
			esc_html( $snippet['title'] ),
			esc_html( $error['message'] )
		);
	}
);

function velrion_url( array $args = array() ): string {
	return add_query_arg( $args, admin_url( 'admin.php?page=velrion-snippets' ) );
}

function velrion_check_request( string $action ): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Nemáte oprávnění.' );
	}
	check_admin_referer( $action );
}

function velrion_flash( string $message ): void {
	set_transient( 'velrion_flash_' . get_current_user_id(), $message, 60 );
}

function velrion_clear_error( int $id ): void {
	$error = get_option( VELRION_ERROR_OPTION );
	if ( $error && (int) $error['id'] === $id ) {
		delete_option( VELRION_ERROR_OPTION );
	}
}

function velrion_handle_save(): void {
	velrion_check_request( 'velrion_save' );

	$snippets = velrion_snippets();
	$id       = absint( $_POST['id'] ?? 0 );
	if ( ! $id ) {
		$id = $snippets ? max( array_keys( $snippets ) ) + 1 : 1;
	}

	$type  = sanitize_key( $_POST['type'] ?? 'php' );
	$type  = isset( VELRION_PLACES[ $type ] ) ? $type : 'php';
	$place = sanitize_key( $_POST['place'] ?? '' );
	$place = isset( VELRION_PLACES[ $type ][ $place ] ) ? $place : array_key_first( VELRION_PLACES[ $type ] );
	$code  = trim( wp_unslash( $_POST['code'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- kód se ukládá tak, jak je
	if ( 'php' === $type ) {
		$code = trim( preg_replace( '/^<\?php\s*/', '', $code ) );
	}

	$active = ! empty( $_POST['active'] );
	if ( 'php' === $type && $active ) {
		$syntax_error = velrion_php_syntax_error( $code );
		if ( $syntax_error ) {
			$active = false;
			velrion_flash( 'Snippet je uložený, ale zůstal vypnutý. ' . $syntax_error );
		}
	}

	$snippets[ $id ] = array(
		'title'    => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ) ?: 'Bez názvu',
		'type'     => $type,
		'place'    => $place,
		'priority' => (int) ( $_POST['priority'] ?? 10 ),
		'active'   => $active,
		'code'     => $code,
	);
	velrion_save_snippets( $snippets );
	velrion_clear_error( $id );

	wp_safe_redirect( velrion_url( array( 'edit' => $id, 'saved' => 1 ) ) );
	exit;
}

function velrion_handle_toggle(): void {
	velrion_check_request( 'velrion_toggle' );

	$snippets = velrion_snippets();
	$id       = absint( $_POST['id'] ?? 0 );
	if ( isset( $snippets[ $id ] ) ) {
		$snippet  = $snippets[ $id ];
		$activate = empty( $snippet['active'] );
		$error    = $activate && 'php' === $snippet['type'] ? velrion_php_syntax_error( $snippet['code'] ) : null;
		if ( $error ) {
			velrion_flash( sprintf( 'Snippet „%s“ nejde zapnout. %s', $snippet['title'], $error ) );
		} else {
			$snippets[ $id ]['active'] = $activate;
			velrion_save_snippets( $snippets );
			velrion_clear_error( $id );
		}
	}

	wp_safe_redirect( wp_get_referer() ?: velrion_url() );
	exit;
}

function velrion_handle_delete(): void {
	velrion_check_request( 'velrion_delete' );

	$snippets = velrion_snippets();
	$id       = absint( $_POST['id'] ?? 0 );
	unset( $snippets[ $id ] );
	velrion_save_snippets( $snippets );
	velrion_clear_error( $id );

	wp_safe_redirect( velrion_url() );
	exit;
}

function velrion_render_page(): void {
	$flash = get_transient( 'velrion_flash_' . get_current_user_id() );
	if ( $flash ) {
		delete_transient( 'velrion_flash_' . get_current_user_id() );
		printf( '<div class="notice notice-warning is-dismissible"><p>%s</p></div>', esc_html( $flash ) );
	}

	echo '<div class="wrap">';
	if ( isset( $_GET['edit'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		velrion_render_edit( absint( $_GET['edit'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
	} else {
		velrion_render_list();
	}
	echo '</div>';
}

function velrion_post_button( string $action, int $id, string $label, string $class, string $confirm = '' ): void {
	printf( '<form method="post" action="%s" style="display:inline">', esc_url( admin_url( 'admin-post.php' ) ) );
	wp_nonce_field( $action );
	printf( '<input type="hidden" name="action" value="%s"><input type="hidden" name="id" value="%d">', esc_attr( $action ), $id );
	printf(
		'<button type="submit" class="%s"%s>%s</button></form>',
		esc_attr( $class ),
		$confirm ? ' onclick="return confirm(\'' . esc_js( $confirm ) . '\')"' : '',
		esc_html( $label )
	);
}

function velrion_render_list(): void {
	$filter   = sanitize_key( $_GET['type'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
	$snippets = velrion_snippets();
	uasort(
		$snippets,
		function ( $a, $b ) {
			return array( $a['type'], (int) $a['priority'], $a['title'] ) <=> array( $b['type'], (int) $b['priority'], $b['title'] );
		}
	);
	?>
	<h1 class="wp-heading-inline">Snippety</h1>
	<a href="<?php echo esc_url( velrion_url( array( 'edit' => 0 ) ) ); ?>" class="page-title-action">Přidat nový</a>
	<hr class="wp-header-end">

	<ul class="subsubsub">
		<li><a href="<?php echo esc_url( velrion_url() ); ?>"<?php echo $filter ? '' : ' class="current"'; ?>>Vše</a></li>
		<?php foreach ( array_keys( VELRION_PLACES ) as $type ) : ?>
			<li>| <a href="<?php echo esc_url( velrion_url( array( 'type' => $type ) ) ); ?>"<?php echo $filter === $type ? ' class="current"' : ''; ?>><?php echo esc_html( strtoupper( $type ) ); ?></a></li>
		<?php endforeach; ?>
	</ul>

	<table class="wp-list-table widefat fixed striped">
		<thead><tr>
			<th style="width:90px">Stav</th><th>Název</th><th style="width:70px">Typ</th>
			<th style="width:200px">Kde</th><th style="width:70px">Priorita</th><th style="width:150px"></th>
		</tr></thead>
		<tbody>
		<?php if ( ! $snippets ) : ?>
			<tr><td colspan="6">Zatím žádné snippety.</td></tr>
		<?php endif; ?>
		<?php
		foreach ( $snippets as $id => $snippet ) :
			if ( $filter && $filter !== $snippet['type'] ) {
				continue;
			}
			?>
			<tr>
				<td><?php velrion_post_button( 'velrion_toggle', $id, $snippet['active'] ? 'Zapnuto' : 'Vypnuto', $snippet['active'] ? 'button button-primary button-small' : 'button button-small' ); ?></td>
				<td>
					<strong><a href="<?php echo esc_url( velrion_url( array( 'edit' => $id ) ) ); ?>"><?php echo esc_html( $snippet['title'] ); ?></a></strong>
				</td>
				<td><code><?php echo esc_html( strtoupper( $snippet['type'] ) ); ?></code></td>
				<td><?php echo esc_html( VELRION_PLACES[ $snippet['type'] ][ $snippet['place'] ] ?? $snippet['place'] ); ?></td>
				<td><?php echo (int) $snippet['priority']; ?></td>
				<td>
					<a href="<?php echo esc_url( velrion_url( array( 'edit' => $id ) ) ); ?>">Upravit</a> |
					<?php velrion_post_button( 'velrion_delete', $id, 'Smazat', 'button-link button-link-delete', 'Opravdu smazat snippet?' ); ?>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<p class="description">Při fatální chybě PHP snippetu se snippet sám vypne. Kdyby web přesto nešel načíst, přidej do wp-config.php řádek <code>define( 'VELRION_SAFE_MODE', true );</code>.</p>
	<?php
}

function velrion_render_edit( int $id ): void {
	$snippet = velrion_snippets()[ $id ] ?? null;
	if ( ! $snippet ) {
		$id      = 0;
		$snippet = array(
			'title'    => '',
			'type'     => 'php',
			'place'    => 'everywhere',
			'priority' => 10,
			'active'   => false,
			'code'     => '',
		);
	}

	$modes = array(
		'php'  => 'text/x-php',
		'css'  => 'text/css',
		'js'   => 'application/javascript',
		'html' => 'text/html',
	);
	$editor = wp_enqueue_code_editor( array( 'type' => $modes[ $snippet['type'] ] ) );
	?>
	<h1><?php echo $id ? 'Upravit snippet' : 'Nový snippet'; ?></h1>
	<p><a href="<?php echo esc_url( velrion_url() ); ?>">&larr; Zpět na seznam</a></p>
	<?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
		<div class="notice notice-success is-dismissible"><p>Uloženo.</p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'velrion_save' ); ?>
		<input type="hidden" name="action" value="velrion_save">
		<input type="hidden" name="id" value="<?php echo (int) $id; ?>">

		<table class="form-table" role="presentation">
			<tr>
				<th><label for="velrion-title">Název</label></th>
				<td><input id="velrion-title" name="title" type="text" class="regular-text" value="<?php echo esc_attr( $snippet['title'] ); ?>" required></td>
			</tr>
			<tr>
				<th><label for="velrion-type">Typ</label></th>
				<td>
					<select id="velrion-type" name="type">
						<?php foreach ( array_keys( VELRION_PLACES ) as $type ) : ?>
							<option value="<?php echo esc_attr( $type ); ?>"<?php selected( $snippet['type'], $type ); ?>><?php echo esc_html( strtoupper( $type ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="velrion-place">Kde</label></th>
				<td>
					<select id="velrion-place" name="place">
						<?php foreach ( VELRION_PLACES as $type => $places ) : ?>
							<?php foreach ( $places as $place => $label ) : ?>
								<option value="<?php echo esc_attr( $place ); ?>" data-type="<?php echo esc_attr( $type ); ?>"<?php selected( $snippet['type'] === $type && $snippet['place'] === $place ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="velrion-priority">Priorita</label></th>
				<td>
					<input id="velrion-priority" name="priority" type="number" class="small-text" value="<?php echo (int) $snippet['priority']; ?>">
					<p class="description">Nižší číslo se spustí dřív. Standard je 10.</p>
				</td>
			</tr>
			<tr>
				<th>Stav</th>
				<td><label><input name="active" type="checkbox" value="1"<?php checked( $snippet['active'] ); ?>> Zapnuto</label></td>
			</tr>
		</table>

		<p class="description">PHP piš bez úvodního <code>&lt;?php</code>, CSS bez <code>&lt;style&gt;</code> a JS bez <code>&lt;script&gt;</code>. HTML se vloží přesně tak, jak je.</p>
		<textarea id="velrion-code" name="code" rows="24" class="large-text code"><?php echo esc_textarea( $snippet['code'] ); ?></textarea>

		<?php submit_button( 'Uložit snippet' ); ?>
	</form>

	<script>
	// Editor kódu se načítá v patičce administrace, proto až po načtení stránky.
	document.addEventListener( 'DOMContentLoaded', function () {
		const modes = <?php echo wp_json_encode( $modes ); ?>;
		const type = document.getElementById( 'velrion-type' );
		const place = document.getElementById( 'velrion-place' );
		const editorSettings = <?php echo wp_json_encode( $editor ); ?>;
		const editor = editorSettings ? wp.codeEditor.initialize( 'velrion-code', editorSettings ) : null;

		// V nabídce "Kde" ukázat jen místa pro zvolený typ.
		function syncType() {
			let selectedVisible = false;
			for ( const option of place.options ) {
				option.hidden = option.dataset.type !== type.value;
				option.disabled = option.hidden;
				selectedVisible = selectedVisible || ( option.selected && ! option.hidden );
			}
			if ( ! selectedVisible ) {
				place.querySelector( 'option[data-type="' + type.value + '"]' ).selected = true;
			}
			if ( editor ) {
				editor.codemirror.setOption( 'mode', modes[ type.value ] );
			}
		}
		type.addEventListener( 'change', syncType );
		syncType();
	} );
	</script>
	<?php
}
