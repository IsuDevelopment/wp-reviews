<?php
/**
 * Generates .agents/code-map.md from the source, so agents orient without grepping.
 *
 * Usage: php bin/code-map.php [--check]
 * --check exits 1 when the committed map is stale (run by `composer check`).
 *
 * @package IsuDev\Reviews
 */

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI script, runs outside WordPress.

$root  = dirname( __DIR__ );
$check = in_array( '--check', $argv, true );

/**
 * Relative path of a file under the plugin root.
 *
 * @param string $root Plugin root.
 * @param string $file Absolute path.
 */
function isurev_rel( string $root, string $file ): string {
	return ltrim( substr( $file, strlen( $root ) ), '/' );
}

/**
 * PHP files under the given folders, sorted.
 *
 * @param string       $root Plugin root.
 * @param list<string> $dirs Folders relative to the root.
 * @return list<string>
 */
function isurev_php_files( string $root, array $dirs ): array {
	$files = array();
	foreach ( $dirs as $dir ) {
		$path = $root . '/' . $dir;
		if ( is_file( $path ) ) {
			$files[] = $path;
			continue;
		}
		if ( ! is_dir( $path ) ) {
			continue;
		}
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $file ) {
			if ( 'php' === $file->getExtension() ) {
				$files[] = $file->getPathname();
			}
		}
	}
	sort( $files );

	return $files;
}

/**
 * First sentence of the docblock right before a class/interface/trait, or of the file docblock.
 *
 * @param string $src PHP source.
 */
function isurev_summary( string $src ): string {
	if ( preg_match( '#/\*\*((?:(?!\*/).)*)\*/\s*(?:final\s+|abstract\s+)?(?:class|interface|trait)\s#s', $src, $m ) || preg_match( '#/\*\*((?:(?!\*/).)*)\*/#s', $src, $m ) ) {
		$lines = array();
		foreach ( explode( "\n", $m[1] ) as $line ) {
			$line = trim( preg_replace( '#^\s*\*\s?#', '', $line ) );
			if ( '' === $line || str_starts_with( $line, '@' ) ) {
				if ( $lines ) {
					break;
				}
				continue;
			}
			$lines[] = $line;
		}

		return implode( ' ', $lines );
	}

	return '';
}

$out   = array();
$out[] = '# Code map';
$out[] = '';
$out[] = '**Generated** by `php bin/code-map.php` (`composer code-map`). Do not edit by hand; `composer check` fails when it is stale.';
$out[] = '';

// Classes.
$out[] = '## PHP classes (`includes/`, namespace `IsuDev\\Reviews`)';
$out[] = '';
$out[] = '| File | Type | Summary | Public methods |';
$out[] = '| --- | --- | --- | --- |';
$hooks = array();
$rest  = array();
foreach ( isurev_php_files( $root, array( 'isudev-reviews.php', 'uninstall.php', 'includes', 'src', 'templates' ) ) as $file ) {
	$src = (string) file_get_contents( $file );
	$rel = isurev_rel( $root, $file );

	if ( preg_match_all( '#\\\\?(add_action|add_filter|do_action|apply_filters)\(\s*([^,\)]+)#', $src, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $hook ) {
			$name = trim( $hook[2] );
			if ( str_contains( $name, 'isurev' ) || str_starts_with( $hook[1], 'add_' ) ) {
				$hooks[ $hook[1] ][ $name ][] = $rel;
			}
		}
	}
	if ( preg_match_all( '#register_rest_route\(\s*self::NAMESPACE,\s*\'([^\']+)\'.*?\'methods\'\s*=>\s*([^,]+)#s', $src, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $route ) {
			$rest[] = '| `' . trim( $route[2], " '\\" ) . '` | `/isudev-reviews/v1' . $route[1] . '` | `' . $rel . '` |';
		}
	}

	if ( ! str_starts_with( $rel, 'includes/' ) && 'isudev-reviews.php' !== $rel ) {
		continue;
	}
	if ( ! preg_match( '#^\s*(?:final\s+|abstract\s+)?(class|interface|trait)\s+(\w+)#m', $src, $type ) ) {
		$summary = preg_match( '#Description:\s*(.+)#', $src, $desc ) ? 'Plugin header, constants, autoloader, boot. ' . trim( $desc[1] ) : isurev_summary( $src );
		$out[]   = '| `' . $rel . '` | file | ' . $summary . ' | |';
		continue;
	}
	preg_match_all( '#^\s*public\s+(?:static\s+)?function\s+(\w+)#m', $src, $methods );
	$public = array_filter( $methods[1], static fn( $name ) => '__construct' !== $name );
	$out[] = '| `' . $rel . '` | ' . $type[1] . ' `' . $type[2] . '` | ' . isurev_summary( $src ) . ' | ' . ( $public ? '`' . implode( '`, `', $public ) . '`' : '' ) . ' |';
}
$out[] = '';

// Hooks.
$out[] = '## Hooks';
$out[] = '';
$out[] = 'Own hooks (`isurev_*`) are the extension points; `add_*` rows show what the plugin attaches to.';
$out[] = '';
$out[] = '| Kind | Hook | Where |';
$out[] = '| --- | --- | --- |';
ksort( $hooks );
foreach ( $hooks as $kind => $names ) {
	ksort( $names );
	foreach ( $names as $name => $files ) {
		$out[] = '| ' . $kind . ' | `' . str_replace( '|', '\\|', $name ) . '` | `' . implode( '`, `', array_unique( $files ) ) . '` |';
	}
}
$out[] = '';

// REST.
$out[] = '## REST routes (plugin, for the editor and admin)';
$out[] = '';
$out[] = '| Method | Route | File |';
$out[] = '| --- | --- | --- |';
$out   = array_merge( $out, $rest );
$out[] = '| `GET` | `/isudev-reviews/v1/site-verify` | SDK (`vendor-prefixed/isudev/plugin-sdk`), called by the platform during registration |';
$out[] = '';

// WP-CLI.
$cli = (string) file_get_contents( $root . '/includes/class-cli.php' );
preg_match_all( '#\*\s+@subcommand\s+([\w-]+)#', $cli, $named );
preg_match_all( '#^\s*public function (\w+)\(\s*array#m', $cli, $commands );
$out[] = '## WP-CLI (`wp isurev`)';
$out[] = '';
$subs = array_map( static fn( $name ) => str_replace( '_', '-', $name ), $commands[1] );
$out[] = '- Plugin: `' . implode( '`, `', $subs ) . '` (`includes/class-cli.php`).';
$out[] = '- SDK: `register`, `license`.';
$out[] = '- Help: `wp help isurev <command>`.';
$out[] = '';

// Options and tables.
$settings = (string) file_get_contents( $root . '/includes/class-settings.php' );
preg_match( '#function defaults\(\): array \{.*?array\((.*?)\);#s', $settings, $defaults );
preg_match_all( "#'(\w+)'\s*=>#", $defaults[1] ?? '', $keys );
$out[] = '## Options and tables';
$out[] = '';
$out[] = '- `isurev_settings`: `' . implode( '`, `', $keys[1] ) . '`. Secrets (`license_key`, `google_api_key`) are write-only in the UI and CLI.';
$out[] = '- `isurev_db_version`: schema version (`Schema::DB_VERSION`).';
$out[] = '- SDK: `isurev_site_token`, `isurev_license` (license cache), `isurev_verify_nonce` (transient during registration).';
$schema = (string) file_get_contents( $root . '/includes/class-schema.php' );
if ( preg_match_all( '#CREATE TABLE \{\$(\w+)\} \((.*?)\) \{\$charset\}#s', $schema, $tables, PREG_SET_ORDER ) ) {
	foreach ( $tables as $table ) {
		preg_match_all( '#^\s*([a-z_]+)\s+(?:bigint|int|varchar|decimal|datetime|text|longtext|tinyint)#m', $table[2], $cols );
		$out[] = '- Table `{prefix}isurev_' . $table[1] . '`: ' . implode( ', ', $cols[1] ) . '.';
	}
}
$out[] = '';

// Block and templates.
$block = json_decode( (string) file_get_contents( $root . '/src/blocks/reviews/block.json' ), true );
$out[] = '## Block';
$out[] = '';
$out[] = '- `' . $block['name'] . '` (apiVersion ' . $block['apiVersion'] . '), source `src/blocks/reviews/`, built to `build/` by `npm run build`, rendered by `render.php` through `Renderer`.';
$out[] = '- Attributes: `' . implode( '`, `', array_keys( $block['attributes'] ?? array() ) ) . '`.';
$out[] = '';
$out[] = '## Templates (`templates/<slug>/`: `template.json`, `card.php`, `style.css`)';
$out[] = '';
foreach ( glob( $root . '/templates/*/template.json' ) as $json ) {
	$meta  = json_decode( (string) file_get_contents( $json ), true );
	$out[] = '- `' . basename( dirname( $json ) ) . '`: ' . ( $meta['title'] ?? '' ) . ', tier ' . ( $meta['tier'] ?? 'free' ) . ', layouts ' . implode( '/', (array) ( $meta['layouts'] ?? array() ) ) . '.';
}
$out[] = '';

$map  = implode( "\n", $out ) . "\n";
$path = $root . '/.agents/code-map.md';

if ( $check ) {
	if ( ! is_file( $path ) || file_get_contents( $path ) !== $map ) {
		fwrite( STDERR, ".agents/code-map.md is stale. Run: composer code-map\n" );
		exit( 1 );
	}
	echo ".agents/code-map.md is current.\n";
	exit( 0 );
}

if ( ! is_dir( dirname( $path ) ) ) {
	mkdir( dirname( $path ), 0755, true );
}
file_put_contents( $path, $map );
echo "Wrote .agents/code-map.md\n";
