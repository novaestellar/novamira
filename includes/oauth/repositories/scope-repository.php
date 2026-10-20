<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\OAuth\Repositories;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\ScopeTrait;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;

if (!defined('ABSPATH')) {
    exit();
}

final class ScopeEntity implements ScopeEntityInterface
{
    use EntityTrait;
    use ScopeTrait;
}

// @mago-expect lint:single-class-per-file
final class ScopeRepository implements ScopeRepositoryInterface
{
    public function getScopeEntityByIdentifier(mixed $identifier): ?ScopeEntityInterface
    {
        // Every synonym resolves to the canonical grant, so a request for `mcp` (what earlier releases
        // advertised) and a request for `full_access` produce the same token.
        if (!in_array((string) $identifier, \Novamira\OAuth\supported_scopes(), strict: true)) {
            return null;
        }
        return self::full_access();
    }

    /** @param array<array-key, ScopeEntityInterface> $scopes */
    public function finalizeScopes(
        array $scopes,
        mixed $grantType,
        ClientEntityInterface $clientEntity,
        mixed $userIdentifier = null,
    ): array {
        // Every accepted authorization and refresh flow converges on the single full-access grant.
        return [self::full_access()];
    }

    public static function full_access(): ScopeEntity
    {
        $entity = new ScopeEntity();
        $entity->setIdentifier(\Novamira\OAuth\FULL_ACCESS_SCOPE);
        return $entity;
    }
}
