<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit();
}

/**
 * The PHP advisory the agent tells the user about: the first fixed release of each supported branch.
 * Branches missing from the list (8.1 and older) have no fixed upstream release.
 *
 * @return array{date: string, cves: list<string>, fixed: array<string, string>}
 */
function novamira_php_version_advisory(): array
{
    return [
        'date' => '2026-10-21',
        'cves' => ['CVE-2026-109618', 'CVE-2026-109619'],
        'fixed' => [
            '8.2' => '8.2.35',
            '8.3' => '8.3.36',
            '8.4' => '8.4.27',
            '8.5' => '8.5.12',
        ],
    ];
}

/**
 * Agent instruction lines when this PHP version predates the advisory fix, empty otherwise.
 *
 * Linux distributions often backport the fix without changing the version number, so the
 * version only says the site may be affected.
 *
 * @return list<string>
 */
function novamira_php_version_notice_lines(string $version = PHP_VERSION): array
{
    $matches = [];
    if (!preg_match('/^(\d+)\.(\d+)\.(\d+)/', $version, $matches)) {
        return [];
    }

    $advisory = novamira_php_version_advisory();
    $branch = $matches[1] . '.' . $matches[2];
    $plain_version = $matches[1] . '.' . $matches[2] . '.' . $matches[3];
    $fixed = $advisory['fixed'][$branch] ?? null;

    if ($fixed !== null && version_compare($plain_version, $fixed, operator: '>=')) {
        return [];
    }
    if ($fixed === null && version_compare($plain_version, version2: '8.2.0', operator: '>=')) {
        return [];
    }

    $source =
        'The PHP project published a security advisory on '
        . $advisory['date']
        . ' about two critical remote code execution vulnerabilities in PHP itself ('
        . implode(', ', $advisory['cves'])
        . '). They affect PHP on any website, not WordPress or Novamira.';
    $status = $fixed !== null
        ? 'This server reports PHP '
        . $version
        . ', older than '
        . $fixed
        . ', the first fixed release for PHP '
        . $branch
        . '.'
        : 'This server reports PHP '
        . $version
        . '. PHP '
        . $branch
        . ' no longer receives official updates, so it has no fixed release: only a hosting provider or Linux distribution backport, or an upgrade to a supported PHP version, fixes it.';

    return [
        '## PHP version notice',
        '',
        'IMPORTANT: ' . $source . ' ' . $status,
        'At the start of your first reply, before anything else, tell the user that the PHP version on this server may be affected and recommend updating PHP through their hosting provider. Make clear this is a PHP issue, not a Novamira or WordPress issue: updating Novamira or WordPress does not fix it. Hosting providers and Linux distributions often apply the fix without changing the version number, so the user should confirm with the host whether it is already patched.',
        'Do not change PHP versions, php.ini, or server configuration yourself unless the user explicitly asks.',
    ];
}
