<?php
// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

if (!defined('ABSPATH')) {
    define('ABSPATH', '/');
}
if (!function_exists('add_action')) {
    function add_action(
        string $hook_name,
        callable|string $callback,
        int $priority = 10,
        int $accepted_args = 1,
    ): bool {
        $GLOBALS['novamira_test_actions'][] = [$hook_name, $callback, $priority, $accepted_args];
        return true;
    }
}
if (!function_exists('__')) {
    function __(string $text, string $domain = 'default'): string
    {
        return $text;
    }
}

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/oauth/bootstrap.php';
require_once __DIR__ . '/../../includes/oauth/consent.php';
require_once __DIR__ . '/../../includes/oauth/endpoints/introspect.php';

final class AbilityScopeLifecycleTest extends TestCase
{
    #[DataProvider('authorizationScopeProvider')]
    public function testAuthorizationScopeValidationMapsEveryRecognizedRequestToFullAccess(
        string $requested,
        ?string $expected,
    ): void {
        self::assertSame($expected, \Novamira\OAuth\normalize_authorization_scope($requested));
    }

    /** @return iterable<string, array{string, string|null}> */
    public static function authorizationScopeProvider(): iterable
    {
        yield 'default legacy' => ['', 'full_access'];
        yield 'old readonly alias' => ['abilities:read', 'full_access'];
        yield 'old full alias' => ['abilities', 'full_access'];
        yield 'old ability combination' => ['abilities abilities:read', 'full_access'];
        yield 'mcp' => ['mcp', 'full_access'];
        yield 'canonical' => ['full_access', 'full_access'];
        yield 'canonical with synonym' => ['full_access mcp', 'full_access'];
        yield 'legacy aliases' => ['read write', 'full_access'];
        yield 'unknown' => ['admin', null];
        yield 'mixed recognized aliases' => ['mcp abilities read', 'full_access'];
    }

    public function testConsentAlwaysDescribesFullAccess(): void
    {
        foreach (['full_access', 'mcp', 'abilities', 'abilities:read'] as $scope) {
            $grant = \Novamira\OAuth\Consent\consent_grant_details($scope);
            self::assertStringContainsString('full', strtolower($grant['label']));
            self::assertCount(5, $grant['risks']);
            self::assertStringContainsString('third-party', $grant['description']);
            self::assertStringContainsString('REST API', $grant['label']);
            self::assertStringContainsString('application password', $grant['description']);
            self::assertStringContainsString('temporary administrator', implode(' ', $grant['risks']));
        }
    }

    #[DataProvider('grantedScopeProvider')]
    public function testIntrospectionReportsTheActualGrantedScope(mixed $granted, string $expected): void
    {
        self::assertSame(
            $expected,
            \Novamira\OAuth\Endpoints\Introspect\granted_scope_string($granted),
        );
    }

    /** @return iterable<string, array{mixed, string}> */
    public static function grantedScopeProvider(): iterable
    {
        yield 'full access' => [['full_access'], 'full_access'];
        yield 'token issued as mcp' => [['mcp'], 'mcp'];
        yield 'legacy ability token' => [['abilities'], 'abilities'];
        yield 'legacy readonly token' => ['abilities:read', 'abilities:read'];
        yield 'invalid input' => [null, ''];
    }
}
