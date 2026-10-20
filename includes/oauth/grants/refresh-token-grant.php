<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\OAuth\Grants;

use League\OAuth2\Server\Grant\RefreshTokenGrant as LeagueRefreshTokenGrant;
use Psr\Http\Message\ServerRequestInterface;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * League's refresh grant, with the scopes recorded in the old refresh token read as the canonical
 * full-access grant.
 *
 * League refuses a refresh whose requested scope identifiers are not literally among the old
 * token's. The scope repository resolves every synonym to `full_access`, while refresh tokens issued
 * before that change carry `mcp` (or an older alias). Without this, any client sending a scope on
 * refresh would be refused with invalid_scope, and one sending none would be checked against `mcp`
 * resolved to `full_access`, also refused.
 */
// @mago-expect lint:file-name -- includes/ names files in kebab-case, not after the class.
final class RefreshTokenGrant extends LeagueRefreshTokenGrant
{
    /**
     * @param string $clientId
     * @return array<array-key, mixed>
     */
    protected function validateOldRefreshToken(ServerRequestInterface $request, $clientId): array
    {
        /** @var array<array-key, mixed> $data */
        $data = parent::validateOldRefreshToken($request, $clientId);

        return canonicalize_refresh_token_scopes($data);
    }
}

/**
 * @param array<array-key, mixed> $data Decrypted refresh token payload.
 * @return array<array-key, mixed>
 */
function canonicalize_refresh_token_scopes(array $data): array
{
    $scopes = [];
    // @mago-expect analysis:mixed-assignment
    foreach (is_array($data['scopes'] ?? null) ? $data['scopes'] : [] as $scope) {
        if (!is_string($scope)) {
            continue;
        }
        $scopes[] = $scope;
    }
    $data['scopes'] = \Novamira\OAuth\grants_full_access($scopes) ? [\Novamira\OAuth\FULL_ACCESS_SCOPE] : [];

    return $data;
}
