---
name: php-compatibility-check
description: Check whether a WordPress site (plugins, themes, mu-plugins, drop-ins, custom code) is compatible with a target PHP version and propose fixes. Use when the user asks "is my site compatible with PHP 8.x", "can I switch to PHP 8.x", "check before upgrading PHP", or reports errors after a PHP version change.
read_mode: true
---

# PHP Compatibility Check

You cannot change the site's PHP version: the user does that from their hosting panel. Your job is to tell them, before they switch, what would break on the target version and how to fix it, and to help verify the site after they switch.

## Start Here

1. Read the site's current PHP version.
2. Get the target version from the user. If they did not give one, ask. You may suggest the newest supported branch their host offers.
3. If the target is not newer than the current version, say so and stop unless they want a downgrade check.

## PHP Support Status

As of 21 October 2026 (source: php.net/supported-versions):

| Branch | Active support until | Security support until |
|---|---|---|
| 8.2 | 31 Dec 2024 | 31 Dec 2026 |
| 8.3 | 31 Dec 2025 | 31 Dec 2027 |
| 8.4 | 31 Dec 2026 | 31 Dec 2028 |
| 8.5 | 31 Dec 2027 | 31 Dec 2029 |

8.1 and older are end of life and get no security fixes. 8.6 is in release candidate, with GA expected late 2026.

After that date, apply the policy: each branch gets 2 years of active support from its first stable release, then 2 years of security fixes only, ending 31 December. If you have network access, prefer php.net/supported-versions over this table.

Report the support status of the current and target versions as facts: actively supported, security fixes only, or end of life since a given date. Do not assess whether a version is secure or vulnerable, do not name CVEs, and do not use security to argue for or against upgrading.

## Inventory

Collect everything that runs:

- Active plugins (and network-active plugins on multisite) with `Version` and `RequiresPHP` from `get_plugin_data()`.
- Active theme and parent theme with `Requires PHP` from `wp_get_theme()`.
- mu-plugins (`get_mu_plugins()`, plus loaders that include subdirectories) and drop-ins (`get_dropins()`).
- Pending updates from `get_site_transient('update_plugins')` and `get_site_transient('update_themes')`; each entry carries the new version and its `requires_php`. Call `wp_update_plugins()` / `wp_update_themes()` first if the transients are stale.
- Loaded extensions (`get_loaded_extensions()`): the target build on the host may not ship the same set.
- The WordPress core version, and whether it supports the target PHP version.

Inactive plugins cannot break the running site; list them separately at low priority.

`Requires PHP` is a minimum, not a promise of forward compatibility. A plugin that declares 7.4 may still fatal on 8.x. Use it to rule plugins out, never to rule them in.

## Assess Each Component

For plugins and themes from WordPress.org, check the latest release and when it was last updated (plugins_api / themes_api, or api.wordpress.org). Recently maintained code with a pending update is usually fixed by updating. Code untouched for years is the high-risk group.

Read the code yourself for: custom themes and child themes, mu-plugins, drop-ins, snippets stored by snippet plugins, premium plugins with no visible update, and abandoned plugins.

Check every version step between current and target, not only the target. Distinguish:

- **Breaks the site**: removed functions or extensions, syntax that no longer parses, errors promoted from warnings, changed signatures in classes the code extends.
- **Deprecation**: emits notices, keeps working. Worth fixing, not a blocker.

Also check changes up to and including the current version. Novamira needs PHP 8.0, so the site already runs 8.0 or later, but a removed function fails only when the code calling it runs: code on a rarely used path (an admin action, a cron job, an export, an inactive plugin) can already be broken on the current version without anyone noticing. Report it separately as already broken.

High-signal changes by version (see the php.net migration guides, Appendix "Migrating from PHP X to Y", for the full list):

- **8.0**: `create_function()`, `each()`, `money_format()` removed; string to number comparison changed (`0 == "a"` is false); many warnings became `TypeError`/`ValueError`; required parameter after optional deprecated; `match` is reserved.
- **8.1**: passing `null` to non-nullable internal parameters deprecated; return type mismatches on built-in interfaces (`ArrayAccess`, `Iterator`, `Countable`, `JsonSerializable`) emit deprecations unless `#[\ReturnTypeWillChange]`; `FILTER_SANITIZE_STRING` deprecated.
- **8.2**: dynamic properties deprecated; `utf8_encode()`/`utf8_decode()` deprecated; `${var}` string interpolation deprecated.
- **8.3**: few breaking changes; check `get_class()`/`get_parent_class()` without arguments and `range()` edge cases.
- **8.4**: implicitly nullable parameter types (`Type $x = null`) deprecated; `E_STRICT` removed; IMAP, Pspell, OCI8 and PDO_OCI moved out of core to PECL.
- **8.5**: backtick shell operator deprecated; non-canonical casts (`(boolean)`, `(integer)`, `(double)`, `(binary)`) deprecated; `case` labels ending in `;` deprecated.

Existing deprecation notices in `debug.log` (or the server error log) are the best early warning: they often name the exact file and line that breaks on the next version. Read the recent entries.

If the server has a CLI binary for the target version (common on cPanel/CloudLinux, e.g. `/opt/alt/php84/usr/bin/php`), `php -l` with that binary catches parse errors in custom code. It does not catch runtime errors. If the host offers a staging copy, testing there on the target version is the most reliable check; suggest it.

## Report

Before proposing any change, give the user one report:

1. Current version, target version, support status of both.
2. **Blocks the switch**: what would fatal or stop working, with component, file and line.
3. **Already broken on the current version**: incompatible code that has not run yet.
4. **Needs attention**: deprecations, unverified premium or abandoned code, missing extensions.
5. **Compatible**: a short summary, not a full list.
6. For each item in 2, 3 and 4, the proposed fix.

Mark clearly what you verified (read the code, update declares support) versus what you infer (recently maintained, so probably fine).

## Fixes

If Novamira is in read mode, deliver the report with the proposed fixes as recommendations only: do not offer to apply them.

Propose fixes in this order:

1. **Update** to a version that supports the target. Check the update's `requires_php`. Premium plugins may need an active license; say so rather than working around it.
2. **Patch the code**. For custom code (child theme, mu-plugins, snippets) this is the normal fix. For third-party plugins and themes, warn that the next update overwrites the patch; prefer a patch that lives outside their files (a mu-plugin, a child theme override) when one works.
3. **Replace** the plugin with a maintained alternative, or **postpone** the switch until a fix exists.

Do not change any file, update anything, or activate or deactivate anything without explicit approval for that specific change. Before editing a file, keep a copy of the original so the change can be reverted.

## After the Switch

When the user confirms they switched, verify:

- The front end (home page and a few key pages) and wp-admin load.
- No new fatal errors or warnings in `debug.log` since the switch.
- The PHP version reported by the site matches the target.

If something broke, disabling the failing plugin lets the site recover, and the user can switch PHP back from the hosting panel while you fix it.
