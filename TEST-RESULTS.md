# Verification

The initial redirect engine passed 42 policy, 16 adapter and 13 actual local HTTP checks on PHP 7.4.33 and PHP 8.3.33. HTTP tests use a loopback PHP server with simulated WordPress boundaries. Coverage includes query preservation, host/scheme normalization, unsafe input, trusted proxies, excluded methods, modes and recovery. These tests do not simulate production TLS proxies or CDNs.

The website owner reported successful initial installation and operation in Test and Live modes. This is user-confirmed behavior, not independent proof of every integration or WordPress version.

Version 1.1.0 adds separate updater tests for stable release selection, pinned assets, version comparisons, cache handling and API failure behavior. Release-specific WordPress activation, package and upgrade evidence is recorded in the GitHub Release notes. Metadata/stub checks alone do not prove a real-site upgrade.

Run `scripts/test.ps1` to repeat PHP lint, policy, adapter, HTTP and updater checks. Test scripts are excluded from the installable ZIP. No production site is used for downgrade or failure tests.
