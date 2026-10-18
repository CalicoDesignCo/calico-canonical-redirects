# Build verification — October 18, 2026

Package: `releases/calico-canonical-redirects-1.0.0.zip` (6,569 bytes).
SHA256: `62129A0E01A72D7B45B9ADA6AB6322691AAA590004DA357B3EEF98F85BA43EA5`.

- PHP 7.4.33: all six PHP files linted; 42 policy, 16 WordPress-adapter and 13 HTTP checks passed.
- PHP 8.3: same lint and 71 checks passed.
- HTTP checks exercised actual PHP response status, Location, Cache-Control and X-Redirect-By headers over a temporary loopback server. WordPress functions were test stubs, not a real WordPress installation.
- Test cases covered exact query preservation (UTMs, click IDs, duplicate keys), HTTP/HTTPS and www/non-www preferences, HEAD/POST handling, no second redirect at the destination, default Preview, emergency disable, invalid hosts/paths, subdirectories, trusted/untrusted proxy headers, settings validation and multisite refusal.
- Admin page rendered in the PHP harness with its four simulator rows. Real WordPress capability and nonce handling are delegated to WordPress Settings API; stub checks do not certify WordPress security or browser rendering.
- Build packaged only three production files under the correct plugin directory. Every ZIP entry was SHA256-compared with the tested source. Development scripts and tests are excluded.
- Public-site checker passed Node syntax validation and was exercised on the real Deerfield website.

## Read-only Deerfield baseline, approximately 5:25 PM Eastern

Test path: `/menu/?utm_source=calico-test&gclid=example%2B123`.

| Origin | Observed |
|---|---|
| http://rosebuddeerfield.com | 200, no redirect; noncanonical |
| http://www.rosebuddeerfield.com | 200, no redirect; noncanonical |
| https://rosebuddeerfield.com | 200, no redirect; correct preferred URL |
| https://www.rosebuddeerfield.com | 200, no redirect; noncanonical |

Both HTTPS connections passed Node's certificate validation from this machine.
This baseline confirms current server responses from one network vantage, not the new plugin's live behavior. No installation or website settings changed. The raw checker output is in `tests/deerfield-before-install.txt`; its three FAIL results are expected before the redirect fix.

## Remaining real-site validation

Install in Preview, confirm certificates/proxy settings, enable temporary 302 mode, clear caches, and rerun the website checker. Verify a private browser and important URLs before selecting permanent 301 mode. Actual WordPress activation, host/CDN/proxy integration, theme/plugin compatibility and browser rendering have not yet been verified.
