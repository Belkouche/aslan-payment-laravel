<?php

declare(strict_types=1);

namespace Aslan\Laravel\Http\Middleware;

use Closure;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies the X-Webhook-Signature header: "t=<unix_seconds>,v1=<hmac_hex>",
 * where hmac = HMAC-SHA256("<t>.<rawBody>", webhook secret). Signatures are
 * compared in constant time and deliveries outside the tolerance window
 * (past or future) are rejected.
 */
final class VerifyAslanWebhookSignature
{
    public const HEADER = 'X-Webhook-Signature';

    public function __construct(private readonly Config $config) {}

    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) $this->config->get('aslan.webhook.secret');

        if ($secret === '') {
            throw new RuntimeException('The Aslan webhook secret is not configured. Set ASLAN_WEBHOOK_SECRET.');
        }

        $header = (string) $request->headers->get(self::HEADER, '');
        $tolerance = (int) $this->config->get('aslan.webhook.tolerance', 300);

        if (! self::isValid($header, $request->getContent(), $secret, $tolerance)) {
            return new JsonResponse(['error' => 'Invalid signature'], 401);
        }

        return $next($request);
    }

    public static function isValid(string $header, string $body, string $secret, int $tolerance = 300): bool
    {
        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');

            if ($key === 't' && ctype_digit($value)) {
                $timestamp = $value;
            } elseif ($key === 'v1' && $value !== '') {
                $signatures[] = $value;
            }
        }

        if ($timestamp === null || $signatures === []) {
            return false;
        }

        if ($tolerance > 0 && abs(time() - (int) $timestamp) > $tolerance) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $body, $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }
}
