# Calico Canonical Redirects 1.0.0

Source: `source/calico-canonical-redirects`. Installable package: `releases/calico-canonical-redirects-1.0.0.zip`.
New reusable WordPress plugin; no live website has been changed by this build.

## Website test procedure

1. Install the ZIP and activate. Open **Settings → Canonical Redirects**. The default is **Preview**. The four-row simulator proposes redirects without issuing them.
2. Verify **Settings → General → Site Address** is the desired HTTPS domain. The plugin follows www or non-www exactly as configured. It does not change that setting.
3. Check HTTPS works without certificate warnings for both hostnames. If using a TLS proxy, get its exact immediate peer IPs from your host and confirm it overwrites `X-Forwarded-Proto`. Enter those IPs. Do not guess or copy an observed peer IP into the trust list without confirming its role. CIDRs are unsupported. Cloudflare requires Full (strict) TLS; native host HTTPS is simpler.
4. Check the certificate confirmation box, select **Test — temporary 302**, and save. Purge the host/CDN/page cache. Test a known real page on all four domain/scheme versions in a private browser. Confirm the preferred HTTPS URL remains stable, paths and tracking survive, and no loops occur. This mode does issue redirects to visitors until switched back.
5. Optional command from this project folder:

   ```powershell
   node scripts/check-site.cjs https://rosebuddeerfield.com /menu/ test
   ```

   This makes read-only public GET requests, verifies certificates, follows up to five redirects, reports redirect owners when provided, and checks the final URL/query and page status. Run before installing to capture a baseline, then repeat in Test mode. A host/CDN redirect can run first; a PASS verifies convergence, not attribution to this plugin. First-hop status differences are reported as notes.
6. When satisfied, switch to **Live — permanent 301**. Clear caches and repeat with `live` as the final command argument. Browser-cached 301s can persist after deactivation, which is why Test mode comes first.

## Recovery

Set Preview or deactivate through wp-admin. The plugin never redirects admin/login requests. If needed, add this to wp-config.php before WordPress loads:

```php
define('CALICO_CANONICAL_REDIRECTS_DISABLED', true);
```

Remove it to enable the plugin again. Purge server/CDN caches. Other plugins and server rules remain independently active.

## Developer verification

```powershell
.\scripts\test.ps1 -Php 'C:\path\to\php.exe'
.\scripts\build.ps1 -Php 'C:\path\to\php.exe'
```

Requires Node and PHP 7.4+. Build runs lint and the policy, adapter and real HTTP tests first, packages production files only, and verifies each ZIP entry against tested source. It refuses to overwrite an existing release. HTTP tests bind an ephemeral loopback port and stop the PHP server afterward. WordPress function boundaries are simulated: these tests are not a full WordPress installation, real TLS proxy, browser, cache, theme, or plugin compatibility test.

71 assertions per PHP runtime: 42 policy, 16 adapter, 13 HTTP. See `TEST-RESULTS.md` for the build verification record.

## Scope

Front-end GET/HEAD requests only. No rewrite-file edits, site URL changes, page content changes, SEO canonical-tag changes, request history, or remote calls. Query strings remain byte-for-byte intact for accepted requests. Unsafe/ambiguous requests are skipped. Login/admin/API/form submissions and static files are excluded; use hosting rules for all-resource enforcement. Multisite, IP-based sites, custom ports and proxy CIDRs are not supported. Trusted proxy HTTPS is normalized early so WordPress sees the same scheme when redirect mode is active. This does not replace a host's complete proxy setup.

WordPress APIs: [template_redirect](https://developer.wordpress.org/reference/hooks/template_redirect/), [wp_safe_redirect](https://developer.wordpress.org/reference/functions/wp_safe_redirect/), [HTTPS behind proxies](https://developer.wordpress.org/reference/functions/is_ssl/).
