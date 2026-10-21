<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class GutenbergFinalizerStatusUrlTest extends TestCase
{
    public function testRuntimeUrlIsAccepted(): void
    {
        self::assertTrue($this->scenario()['runtime_url']);
    }

    public function testBatchUrlOpensOnlyItsOwnBatch(): void
    {
        $result = $this->scenario();
        self::assertTrue($result['batch_url']);
        self::assertSame('rest_forbidden', $result['batch_token_on_other_batch']);
        self::assertSame('rest_forbidden', $result['runtime_token_on_batch']);
    }

    public function testUrlsCarryAnExpiryOfOneHour(): void
    {
        $result = $this->scenario();
        self::assertGreaterThan(3590, $result['expires_in']);
        self::assertLessThanOrEqual(3600, $result['expires_in']);
    }

    public function testExpiredUrlIsRefusedWithARenewalHint(): void
    {
        $result = $this->scenario();
        self::assertSame('gutenberg_finalizer_status_url_expired', $result['expired']);
        self::assertStringContainsString('novamira/gutenberg-get-pending-batch', $result['expired_message']);
    }

    public function testTamperedExpiryAndBareSecretAreRefused(): void
    {
        $result = $this->scenario();
        self::assertSame('rest_forbidden', $result['extended_expiry']);
        self::assertSame('rest_forbidden', $result['secret_as_token']);
        self::assertSame('rest_forbidden', $result['missing_token']);
    }

    public function testUrlsDependOnTheSiteSalt(): void
    {
        self::assertSame('rest_forbidden', $this->scenario()['other_salt']);
    }

    public function testStatusResponseKeepsTheCallersExpiry(): void
    {
        $result = $this->scenario();
        self::assertSame([$result['kept_expires']], $result['response_expires']);
    }

    public function testRotationInvalidatesIssuedUrls(): void
    {
        self::assertSame('rest_forbidden', $this->scenario()['after_rotation']);
    }

    public function testTokenOnlyStatusOmitsTitlesAndSummaries(): void
    {
        $result = $this->scenario();
        $payload = $result['untitled_payload'];
        $full = json_encode($result['full_batch'], JSON_THROW_ON_ERROR);
        self::assertStringContainsString('Private launch page', $full);
        self::assertStringContainsString('Rewrite pricing', $full);
        self::assertStringContainsString('Secret pricing', $full);

        $encoded = json_encode($payload, JSON_THROW_ON_ERROR);

        self::assertSame('finalized', $payload['batch']['status']);
        self::assertSame('Gutenberg batch #10', $payload['batch']['label']);
        self::assertArrayNotHasKey('target_title', $payload['batch']['items'][0]);
        self::assertArrayNotHasKey('change_summary', $payload['batch']['items'][0]);
        self::assertSame('finalized', $payload['batch']['items'][0]['status']);
        self::assertStringNotContainsString('Private launch page', $encoded);
        self::assertStringNotContainsString('Rewrite pricing', $encoded);
        self::assertStringNotContainsString('Agent note', $encoded);
        self::assertStringNotContainsString('Secret pricing', $encoded);
        self::assertSame('novamira/gutenberg-get-pending-batch', $payload['batch']['details_ability']);
    }

    /** @return array<string, mixed> */
    private function scenario(): array
    {
        static $result = null;
        if (is_array($result)) {
            return $result;
        }

        $script = <<<'PHP'
            define('ABSPATH', '/');
            class WP_Error {
                public function __construct(private string $code, private string $message = '', mixed $data = null) {}
                public function get_error_code(): string { return $this->code; }
                public function get_error_message(): string { return $this->message; }
            }
            class WP_REST_Response {
                public function __construct(public array $data) {}
            }
            class WP_REST_Request {
                public function __construct(private array $query) {}
                public function get_query_params(): array { return $this->query; }
                public function get_params(): array { return $this->query; }
            }
            class WP_Post {
                public string $post_type = 'novamira_gb_change';
                public string $post_title = '';
                public string $post_excerpt = '';
                public string $post_content = '';
                public string $post_status = 'draft';
                public string $post_date_gmt = '2026-01-01 00:00:00';
                public int $post_parent = 0;
                public function __construct(public int $ID) {}
            }
            function add_action(...$args): void {}
            function add_filter(...$args): void {}
            function __(string $text, string $domain = ''): string { return $text; }
            function is_wp_error(mixed $value): bool { return $value instanceof WP_Error; }
            function current_time(string $type, bool $gmt = false): string { return gmdate('Y-m-d H:i:s'); }
            function get_current_user_id(): int { return 0; }
            function novamira_current_user_can_manage(): bool { return false; }
            function user_can(mixed $user, string $capability, mixed ...$args): bool { return false; }
            function get_post(int $id): ?WP_Post { return $GLOBALS['posts'][$id] ?? null; }
            function get_post_meta(int $post_id, string $key, bool $single = false): mixed {
                return $GLOBALS['meta'][$post_id][$key] ?? '';
            }
            function update_post_meta(int $post_id, string $key, mixed $meta_value): bool {
                $GLOBALS['meta'][$post_id][$key] = $meta_value;
                return true;
            }
            function delete_post_meta(int $post_id, string $key): bool {
                unset($GLOBALS['meta'][$post_id][$key]);
                return true;
            }
            function get_option(string $option, mixed $default_value = false): mixed {
                return $GLOBALS['options'][$option] ?? $default_value;
            }
            function update_option(string $option, mixed $value, mixed $autoload = null): bool {
                $GLOBALS['options'][$option] = $value;
                return true;
            }
            function add_option(string $option, mixed $value, mixed $deprecated = '', mixed $autoload = null): bool {
                return update_option($option, $value);
            }
            function delete_option(string $option): bool {
                unset($GLOBALS['options'][$option]);
                return true;
            }
            function wp_salt(string $scheme = 'auth'): string { return $GLOBALS['salt']; }
            function get_transient(string $transient): mixed { return false; }
            function set_transient(string $transient, mixed $value, int $expiration = 0): bool { return true; }
            function admin_url(string $path = ''): string { return 'https://example.test/wp-admin/' . $path; }
            function rest_url(string $path = ''): string { return 'https://example.test/wp-json/' . $path; }
            function add_query_arg(array $args, string $url): string { return $url . '?' . http_build_query($args); }
            function get_the_title(WP_Post|int $post): string {
                $post = $post instanceof WP_Post ? $post : get_post($post);
                return $post?->post_title ?? '';
            }
            function parse_blocks(string $content): array { return []; }
            function get_posts(array $args): array {
                return array_values(array_filter($GLOBALS['posts'], static function (WP_Post $post) use ($args): bool {
                    if (isset($args['post_parent']) && $post->post_parent !== $args['post_parent']) { return false; }
                    foreach ($args['meta_query'] ?? [] as $query) {
                        if (!is_array($query) || !isset($query['key'])) { continue; }
                        $actual = get_post_meta($post->ID, $query['key'], true);
                        if (!in_array($actual, (array) $query['value'], true)) { return false; }
                    }
                    return true;
                }));
            }
            require $argv[1] . '/includes/abilities/gutenberg/bootstrap.php';
            require $argv[1] . '/includes/abilities/gutenberg/runtime.php';
            require $argv[1] . '/includes/abilities/gutenberg/rest.php';

            $gb = 'Novamira\\Abilities\\Gutenberg\\';
            $GLOBALS['options'] = [];
            $GLOBALS['meta'] = [];
            $GLOBALS['salt'] = 'site-salt';

            $target = new WP_Post(5);
            $target->post_type = 'page';
            $target->post_title = 'Private launch page';
            $batch = new WP_Post(10);
            $batch->post_title = 'Launch batch for Private launch page';
            $batch->post_excerpt = 'Agent note';
            $item = new WP_Post(11);
            $item->post_parent = 10;
            $item->post_excerpt = 'Rewrite pricing';
            $GLOBALS['posts'] = [5 => $target, 10 => $batch, 11 => $item];
            update_post_meta(10, constant($gb . 'META_KIND'), 'batch');
            update_post_meta(11, constant($gb . 'META_KIND'), 'item');
            update_post_meta(11, constant($gb . 'META_TARGET_ID'), 5);
            update_post_meta(11, constant($gb . 'META_TARGET_TYPE'), 'page');
            update_post_meta(10, constant($gb . 'META_LAST_ERROR'), 'Failed on Private launch page');
            update_post_meta(11, constant($gb . 'META_VALIDATION_ERRORS'), [[
                'target_id' => 5,
                'target_title' => 'Private launch page',
                'block_name' => 'core/paragraph',
                'path' => '0',
                'category' => 'validation',
                'code' => 'block_validation_failed',
                'message' => 'Expected <p>Secret pricing</p>',
                'suppressed_count' => 0,
            ]]);
            ($gb . 'set_status')(10, 'finalized');
            ($gb . 'set_status')(11, 'finalized');

            $check = static function (array $query) use ($gb): mixed {
                $result = ($gb . 'rest_can_poll_finalizer_runtime')(new WP_REST_Request($query));
                return $result instanceof WP_Error ? $result->get_error_code() : $result;
            };
            $query = static function (string $url): array {
                parse_str((string) parse_url($url, PHP_URL_QUERY), $args);
                return $args;
            };

            $runtime = $query(($gb . 'finalizer_runtime_poll_url')());
            $batch_args = $query(($gb . 'finalizer_runtime_poll_url')(10));
            $expired_expires = time() - 1;
            $expired = ($gb . 'rest_can_poll_finalizer_runtime')(new WP_REST_Request([
                'batch_id' => 10,
                'expires' => $expired_expires,
                'token' => ($gb . 'finalizer_runtime_url_token')(10, $expired_expires),
            ]));
            $secret = ($gb . 'finalizer_runtime_url_secret')();

            $result = [
                'runtime_url' => $check($runtime),
                'batch_url' => $check($batch_args),
                'expires_in' => (int) $batch_args['expires'] - time(),
                'batch_token_on_other_batch' => $check(['batch_id' => 12] + $batch_args),
                'runtime_token_on_batch' => $check(['batch_id' => 10] + $runtime),
                'expired' => $expired->get_error_code(),
                'expired_message' => $expired->get_error_message(),
                'extended_expiry' => $check(['expires' => (int) $batch_args['expires'] + 3600] + $batch_args),
                'secret_as_token' => $check(['token' => $secret]),
                'missing_token' => $check([]),
                'untitled_payload' => ($gb . 'rest_finalizer_runtime_status_payload')(10),
                'full_batch' => ($gb . 'shape_batch')($batch),
            ];
            $GLOBALS['salt'] = 'other-salt';
            $result['other_salt'] = $check($batch_args);
            $GLOBALS['salt'] = 'site-salt';
            ($gb . 'rotate_finalizer_runtime_url_secret')();
            $result['after_rotation'] = $check($batch_args);
            $kept_expires = time() + 120;
            $response = ($gb . 'rest_poll_finalizer_runtime_status')(new WP_REST_Request([
                'batch_id' => 10,
                'expires' => $kept_expires,
                'token' => ($gb . 'finalizer_runtime_url_token')(10, $kept_expires),
            ]));
            preg_match_all('/expires=(\d+)/', json_encode($response->data, JSON_UNESCAPED_SLASHES), $found);
            $result['kept_expires'] = $kept_expires;
            $result['response_expires'] = array_values(array_unique(array_map('intval', $found[1])));
            echo json_encode($result, JSON_THROW_ON_ERROR);
            PHP;

        $command = sprintf(
            '%s -r %s %s 2>&1',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($script),
            escapeshellarg(dirname(__DIR__, levels: 2)),
        );
        $output = (string) shell_exec($command);
        $decoded = json_decode($output, associative: true);
        self::assertIsArray($decoded, $output);
        $result = $decoded;

        return $result;
    }
}
