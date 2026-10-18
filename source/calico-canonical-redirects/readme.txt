=== Calico Canonical Redirects ===
Contributors: calico
Requires at least: 6.2
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Preview, test, and enable front-end HTTPS and preferred-domain redirects.

== Description ==
Uses the HTTPS Website Address (home option), including its preferred www or
non-www domain. Preserves the original path and query string. Only accepts
requests on that domain and its www alternate. Starts in Preview mode.

Modes: Preview (no plugin redirects), Test (302), Live (301).
Trusted proxy IPs must be explicitly configured when HTTPS terminates upstream.
This plugin does not change .htaccess, WordPress URLs, canonicals, or page content.
No analytics or request logging. WordPress checks the public GitHub repository
for stable plugin releases. No GitHub account token is required.

== Installation ==
1. Upload the ZIP through Plugins > Add New > Upload Plugin and activate.
2. Open Settings > Canonical Redirects. Default mode is Preview.
3. Confirm the preferred Website Address is HTTPS and certificates cover both hosts.
4. Review proxy configuration with your host if needed.
5. Select Test (302), save, and verify actual HTTP and HTTPS URLs on both hosts.
6. Select Live (301) only after the tests pass. Clear CDN/hosting caches.

== Recovery ==
Set Preview, deactivate, or define CALICO_CANONICAL_REDIRECTS_DISABLED as true
in wp-config.php before WordPress loads. Cached permanent redirects may persist.
Deleting the plugin leaves its small settings option for reinstalls.

== Updates and privacy ==
WordPress periodically requests public release metadata from api.github.com.
When an administrator starts an update, it downloads that version's installable
ZIP from GitHub. The updater sends no page content or visitor data and does not
enable automatic updates. WordPress administrators control that preference.
For 1.0.0 installations, manually install 1.1.0 once to add the updater.

== Limitations ==
Single-site only. Public DNS domains and standard ports only. Exact proxy IPs,
not CIDR ranges or multi-hop forwarded headers. Use Cloudflare Full (strict).
Front-end GET/HEAD reaching WordPress only; server-served files and requests,
login/admin, REST, AJAX, cron, XML-RPC, CLI, previews, and POST are outside scope.
Server rules, CDN rules and other plugins can still issue their own redirects.
The plugin cannot repair certificates, DNS, mixed content or unrelated GSC issues.
PHP 7.4 and 8.3 local tests use simulated WordPress boundaries; no full WordPress
version compatibility certification is claimed by this initial package.

== Changelog ==
= 1.1.0 =
Public GitHub update support with version-pinned assets, GPL license text,
stable ZIP filename and public documentation. Redirect behavior unchanged.

= 1.0.0 =
Initial preview, 302/301 modes, simulator, proxy trust and emergency disable.
