<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\OAuth\ServerFactory;

use DateInterval;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\ResourceServer;
use Novamira\OAuth\Endpoints\Device;
use Novamira\OAuth\Grants\DeviceCodeGrant;
use Novamira\OAuth\Grants\RefreshTokenGrant;
use Novamira\OAuth\Keys;
use Novamira\OAuth\Repositories\AccessTokenRepository;
use Novamira\OAuth\Repositories\AuthCodeRepository;
use Novamira\OAuth\Repositories\ClientRepository;
use Novamira\OAuth\Repositories\DeviceCodeRepository;
use Novamira\OAuth\Repositories\RefreshTokenRepository;
use Novamira\OAuth\Repositories\ScopeRepository;

if (!defined('ABSPATH')) {
    exit();
}

function build_authorization_server(): AuthorizationServer
{
    $keys = Keys\get();
    $server = new AuthorizationServer(
        new ClientRepository(),
        new AccessTokenRepository(),
        new ScopeRepository(),
        $keys['private'],
        $keys['encryption'],
    );

    $authCodeGrant = new AuthCodeGrant(
        new AuthCodeRepository(),
        new RefreshTokenRepository(),
        new DateInterval('PT1M'),
    );
    $authCodeGrant->setRefreshTokenTTL(new DateInterval('P14D'));
    // PKCE is required by default for public clients in league v8 — no explicit call needed.

    $server->enableGrantType($authCodeGrant, new DateInterval('PT1H'));

    $refreshGrant = new RefreshTokenGrant(new RefreshTokenRepository());
    $refreshGrant->setRefreshTokenTTL(new DateInterval('P14D'));
    $server->enableGrantType($refreshGrant, new DateInterval('PT1H'));

    // RFC 8628, for clients whose browser cannot reach them (a shell over SSH, a container).
    // Same tokens, same lifetimes: only the way the operator approves differs.
    $deviceGrant = new DeviceCodeGrant(new DeviceCodeRepository(), new RefreshTokenRepository(), Device\POLL_INTERVAL);
    $deviceGrant->setRefreshTokenTTL(new DateInterval('P14D'));
    $server->enableGrantType($deviceGrant, new DateInterval('PT1H'));

    return $server;
}

function build_resource_server(): ResourceServer
{
    return new ResourceServer(new AccessTokenRepository(), Keys\get()['public']);
}
