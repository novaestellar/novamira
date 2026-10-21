<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

if (!defined('ABSPATH')) {
    define('ABSPATH', '/');
}

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/php-version-notice.php';

final class PhpVersionNoticeTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function fixedVersions(): iterable
    {
        yield '8.2 fixed' => ['8.2.35'];
        yield '8.3 fixed' => ['8.3.36'];
        yield '8.4 later patch' => ['8.4.28'];
        yield '8.5 fixed' => ['8.5.12'];
        yield 'future branch' => ['8.6.0'];
        yield 'not a version' => ['unknown'];
    }

    #[DataProvider('fixedVersions')]
    public function testNoNoticeOnFixedVersions(string $version): void
    {
        self::assertSame([], novamira_php_version_notice_lines($version));
    }

    /** @return iterable<string, array{string, string}> */
    public static function affectedVersions(): iterable
    {
        yield '8.2' => ['8.2.34', '8.2.35'];
        yield '8.3' => ['8.3.35', '8.3.36'];
        yield '8.4 with distro suffix' => ['8.4.11-1ubuntu1', '8.4.27'];
        yield '8.5' => ['8.5.11', '8.5.12'];
    }

    #[DataProvider('affectedVersions')]
    public function testNoticeNamesTheFixedRelease(string $version, string $fixed): void
    {
        $text = implode("\n", novamira_php_version_notice_lines($version));

        self::assertStringContainsString('PHP ' . $version . ', older than ' . $fixed, $text);
        self::assertStringContainsString('not a Novamira or WordPress issue', $text);
        self::assertStringContainsString('CVE-2026-109618', $text);
    }

    public function testUnsupportedBranchHasNoFixedRelease(): void
    {
        $text = implode("\n", novamira_php_version_notice_lines('7.4.33'));

        self::assertStringContainsString('PHP 7.4 no longer receives official updates', $text);
        self::assertStringContainsString('not a Novamira or WordPress issue', $text);
    }
}
