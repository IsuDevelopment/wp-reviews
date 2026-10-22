# isudev/plugin-sdk

Shared cloud plumbing for isuDev WordPress plugins (PHP 8.2+, WP 6.9+, WPCS):

| Class | Job |
| --- | --- |
| `Config` | Product slug, option/hook/error prefix, option names, features, API URL + license key callables, CLI parent |
| `Sdk` | Builds the rest; `register()` hooks `GET isudev-<slug>/v1/site-verify` and `wp <prefix> register|license`; `uninstall()` |
| `Api_Client` | `request()` (site token, one re-register retry on `<prefix>_api_invalid_site_token`), `send()` (one round trip) |
| `Site_Registration` | `token()`, `forget()`, `register()`, `verify()` |
| `License` | 12 h cache, `refresh()`, `has()` (filter `<prefix>_has_feature`), `is_pro()`, keeps a good Pro state through outages (retry after 1 h) |
| `Links` | Pricing, buy, account, docs and download links from the platform's public `/v1/catalog` (no site token, cached a week, also refreshed by the license check), falling back to `Config::$links` |
| `Cli_Command` | `register` and `license` subcommands under the plugin's own command |

Settings (API URL, write-only license key) stay in the plugin; the SDK reads them through the `Config` callables.

## Install in a plugin (prefixed with Strauss)

Every plugin ships its own copy under its own namespace, so two isuDev plugins on one site never clash. Repo: https://github.com/IsuDevelopment/isudev-plugin-sdk (public). Release = git tag `vX.Y.Z`; plugins require `^0.1` and get it with `composer update isudev/plugin-sdk`.

```json
{
	"repositories": [ { "type": "vcs", "url": "https://github.com/IsuDevelopment/isudev-plugin-sdk.git" } ],
	"require": { "isudev/plugin-sdk": "^0.1" },
	"require-dev": { "brianhenryie/strauss": "^0.31" },
	"extra": {
		"strauss": {
			"target_directory": "vendor-prefixed",
			"namespace_prefix": "IsuDev\\<Product>\\Vendor\\",
			"classmap_prefix": "IsuDev_<Product>_",
			"packages": [ "isudev/plugin-sdk" ],
			"delete_vendor_packages": false
		}
	},
	"scripts": {
		"prefix-namespaces": [ "strauss" ],
		"post-install-cmd": [ "@prefix-namespaces" ],
		"post-update-cmd": [ "@prefix-namespaces" ]
	}
}
```

To try unreleased SDK changes in a plugin, temporarily use a path repository: `{ "type": "path", "url": "../../isudev-plugin-sdk", "options": { "symlink": false, "versions": { "isudev/plugin-sdk": "0.1.0" } } }`.

Then in the plugin:

```php
require_once __DIR__ . '/vendor-prefixed/autoload.php';

use IsuDev\Reviews\Vendor\IsuDev\PluginSdk\Config;
use IsuDev\Reviews\Vendor\IsuDev\PluginSdk\Sdk;

$sdk = new Sdk(
	new Config(
		product: 'reviews',
		prefix: 'isurev',
		agent: 'isudev-reviews/' . VERSION,
		api_url: static fn(): string => Settings::get( 'api_url' ),
		license_key: static fn(): string => Settings::get( 'license_key' ),
		features: array( 'auto_sync', 'facebook' ),
		// Until the platform answered once; afterwards its links (admin → Products) win.
		links: array( 'pricing' => 'https://accounts.isudev.com/buy/reviews' ),
	)
);

// plugins_loaded, after WP_CLI::add_command( 'isurev', … ).
$sdk->register();

$sdk->license()->has( 'auto_sync' );
$sdk->api()->request( 'POST', '/reviews/v1/fetch', $body );
$sdk->links()->get( 'pricing' ); // "Get Pro" link, never hard-coded
```

Default option names: `<prefix>_site_token`, `<prefix>_verify_nonce` (transient), `<prefix>_license`, `<prefix>_links`; override with `options: array( 'license' => '…' )`. Exclude `vendor-prefixed/` in the plugin's `phpcs.xml.dist`, and run `composer install` (with dev, for Strauss) before packaging a release.

`composer lint` runs WPCS on `src/`.
