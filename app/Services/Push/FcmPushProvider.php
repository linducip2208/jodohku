<?php

namespace App\Services\Push;

use App\Models\PushToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * FCM provider with v1 primary + legacy fallback.
 * - Recommended: PUSH_FCM_PROJECT_ID + PUSH_FCM_SERVICE_JSON (service-account
 *   JSON path or raw JSON) → FCM HTTP v1 with OAuth2 access token.
 * - Fallback: PUSH_FCM_SERVER_KEY → legacy fcm/send (deprecated by Google,
 *   kept only for transitional installs, logs deprecation).
 * Without either, the provider refuses and the manager falls back to log
 * (never silently drops intent — it is logged).
 */
class FcmPushProvider implements PushProvider
{
    public function name(): string
    {
        return 'fcm';
    }

    public function send(PushToken $token, string $title, string $body, array $data = []): bool
    {
        $projectId = (string) config('push.fcm.project_id', '');
        $serviceJson = (string) config('push.fcm.service_json', '');
        if ($projectId !== '' && $serviceJson !== '') {
            return $this->sendV1($token, $title, $body, $data, $projectId, $serviceJson);
        }

        return $this->sendLegacy($token, $title, $body, $data);
    }

    /** FCM HTTP v1 with service-account OAuth2 (no SDK). */
    protected function sendV1(PushToken $token, string $title, string $body, array $data, string $projectId, string $serviceJson): bool
    {
        $accessToken = $this->accessToken($serviceJson);
        $message = [
            'message' => [
                'token' => $token->token,
                'notification' => ['title' => mb_substr($title, 0, 120), 'body' => mb_substr($body, 0, 240)],
                'data' => array_map('strval', $data),
                'android' => ['priority' => 'HIGH'],
                'apns' => ['headers' => ['apns-priority' => '10']],
            ],
        ];
        $res = Http::withToken($accessToken)->timeout(15)
            ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", $message);
        $json = $res->json();
        $error = (string) ($json['error']['status'] ?? '');
        if (in_array($error, ['UNREGISTERED', 'INVALID_ARGUMENT'], true)) {
            throw new TokenInvalidException($error);
        }

        return $res->successful();
    }

    protected function accessToken(string $serviceJson): string
    {
        return Cache::remember('push:fcm:oauth2', 3300, function () use ($serviceJson) {
            $json = $serviceJson;
            if (is_file($serviceJson)) {
                $json = (string) file_get_contents($serviceJson);
            }
            $svc = json_decode($json, true);
            if (! is_array($svc) || empty($svc['client_email']) || empty($svc['private_key'])) {
                throw new \RuntimeException('Invalid FCM service account JSON.');
            }
            $header = $this->b64url((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $now = time();
            $claims = $this->b64url((string) json_encode([
                'iss' => $svc['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ]));
            $signing = $header.'.'.$claims;
            $sig = '';
            if (! openssl_sign($signing, $sig, $svc['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new \RuntimeException('Cannot sign FCM JWT.');
            }
            $jwt = $signing.'.'.$this->b64url($sig);
            $res = Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);
            $token = (string) ($res->json()['access_token'] ?? '');
            if ($token === '') {
                throw new \RuntimeException('FCM OAuth2 failed.');
            }

            return $token;
        });
    }

    protected function b64url(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    /** Legacy endpoint (deprecated). Kept only as transitional fallback. */
    protected function sendLegacy(PushToken $token, string $title, string $body, array $data = []): bool
    {
        $key = (string) config('push.fcm.server_key', '');
        if ($key === '') {
            throw new \RuntimeException('FCM not configured (set PUSH_FCM_PROJECT_ID + PUSH_FCM_SERVICE_JSON).');
        }
        try {
            Log::warning('push.fcm_legacy_deprecated');
        } catch (\Throwable) {
        }
        $payload = [
            'to' => $token->token,
            'notification' => ['title' => mb_substr($title, 0, 120), 'body' => mb_substr($body, 0, 240)],
            'data' => array_map('strval', $data),
            'priority' => 'high',
        ];
        $res = Http::withHeaders(['Authorization' => 'key='.$key])->timeout(15)->post('https://fcm.googleapis.com/fcm/send', $payload);
        $json = $res->json();
        $error = (string) ($json['results'][0]['error'] ?? '');
        if (in_array($error, ['NotRegistered', 'InvalidRegistration'], true)) {
            throw new TokenInvalidException($error);
        }
        if (! $res->successful() || (int) ($json['success'] ?? 0) < 1) {
            return false;
        }

        return true;
    }
}
