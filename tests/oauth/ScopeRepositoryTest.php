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

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use Novamira\OAuth\Repositories\ScopeEntity;
use Novamira\OAuth\Repositories\ScopeRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/oauth/bootstrap.php';
require_once __DIR__ . '/../../includes/oauth/repositories/scope-repository.php';

final class ScopeRepositoryTest extends TestCase
{
    public function testAcceptsAbilityMcpAndCompatibilityAliasScopesOnly(): void
    {
        $repo = new ScopeRepository();
        foreach (['full_access', 'abilities:read', 'abilities', 'mcp', 'read', 'write'] as $scope) {
            self::assertSame('full_access', $repo->getScopeEntityByIdentifier($scope)?->getIdentifier());
        }
        self::assertNull($repo->getScopeEntityByIdentifier('admin'));
        self::assertNull($repo->getScopeEntityByIdentifier(''));
    }

    #[DataProvider('finalizedScopeProvider')]
    public function testFinalizeAlwaysIssuesTheSingleFullAccessGrant(array $requested, array $expected): void
    {
        $repo = new ScopeRepository();
        $client = $this->createMock(ClientEntityInterface::class);

        self::assertSame($expected, $this->ids($repo->finalizeScopes(
            array_map($this->scope(...), $requested),
            'authorization_code',
            $client,
        )));
    }

    /** @return iterable<string, array{list<string>, list<string>}> */
    public static function finalizedScopeProvider(): iterable
    {
        yield 'old readonly alias' => [['abilities:read'], ['full_access']];
        yield 'old full alias' => [['abilities'], ['full_access']];
        yield 'old ability combination' => [['abilities:read', 'abilities'], ['full_access']];
        yield 'mcp' => [['mcp'], ['full_access']];
        yield 'canonical' => [['full_access'], ['full_access']];
        yield 'legacy read alias' => [['read'], ['full_access']];
        yield 'legacy write aliases' => [['read', 'write'], ['full_access']];
        yield 'empty legacy default' => [[], ['full_access']];
        yield 'mixed recognized aliases' => [['mcp', 'abilities:read'], ['full_access']];
    }

    private function scope(string $id): ScopeEntity
    {
        $entity = new ScopeEntity();
        $entity->setIdentifier($id);
        return $entity;
    }

    /**
     * @param array<array-key, ScopeEntityInterface> $scopes
     * @return list<string>
     */
    private function ids(array $scopes): array
    {
        return array_values(array_map(static fn(ScopeEntityInterface $s): string => $s->getIdentifier(), $scopes));
    }
}
