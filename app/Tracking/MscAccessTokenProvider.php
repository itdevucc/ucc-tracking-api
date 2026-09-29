<?php

namespace App\Tracking;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use LogicException;

class MscAccessTokenProvider
{
    public function token(): string
    {
        return Cache::remember('tracking:msc:access-token', now()->addMinutes(50), function () {
            $clientId = config('tracking.msc.client_id');
            $tenantId = config('tracking.msc.tenant_id');
            $scope = config('tracking.msc.scope');

            if (! $clientId || ! $tenantId || ! $scope) {
                throw new LogicException('Faltan MSC_CLIENT_ID, MSC_TENANT_ID o MSC_SCOPE.');
            }

            $tokenUrl = "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token";
            $response = Http::asForm()->post($tokenUrl, [
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'scope' => $scope,
                'client_assertion_type' => 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer',
                'client_assertion' => $this->clientAssertion($tokenUrl, $clientId),
            ])->throw();

            return $response->json('access_token');
        });
    }

    private function clientAssertion(string $audience, string $clientId): string
    {
        [$certificate, $privateKey] = $this->certificateAndKey();
        $thumbprint = $this->base64Url(hex2bin(openssl_x509_fingerprint($certificate, 'sha1')));
        $now = now()->timestamp;
        $header = ['alg' => 'RS256', 'typ' => 'JWT', 'x5t' => $thumbprint];
        $claims = [
            'aud' => $audience,
            'iss' => $clientId,
            'sub' => $clientId,
            'jti' => (string) Str::uuid(),
            'nbf' => $now - 5,
            'exp' => $now + 300,
        ];
        $unsigned = $this->base64Url(json_encode($header, JSON_THROW_ON_ERROR)).'.'.$this->base64Url(json_encode($claims, JSON_THROW_ON_ERROR));

        if (! openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new LogicException('No se pudo firmar el client_assertion de MSC.');
        }

        return $unsigned.'.'.$this->base64Url($signature);
    }

    /** @return array{0: \OpenSSLCertificate, 1: \OpenSSLAsymmetricKey} */
    private function certificateAndKey(): array
    {
        $certificatePath = config('tracking.msc.certificate_path');
        $keyPath = config('tracking.msc.private_key_path');
        $password = (string) config('tracking.msc.certificate_password');

        if (! $certificatePath || ! is_file($certificatePath)) {
            throw new LogicException('MSC_CERTIFICATE_PATH no existe.');
        }

        if (in_array(strtolower(pathinfo($certificatePath, PATHINFO_EXTENSION)), ['pfx', 'p12'], true)) {
            if (! openssl_pkcs12_read(file_get_contents($certificatePath), $store, $password)) {
                throw new LogicException('No se pudo abrir el certificado PKCS#12 de MSC.');
            }

            return [openssl_x509_read($store['cert']), openssl_pkey_get_private($store['pkey'], $password)];
        }

        if (! $keyPath || ! is_file($keyPath)) {
            throw new LogicException('MSC_PRIVATE_KEY_PATH no existe.');
        }

        return [
            openssl_x509_read(file_get_contents($certificatePath)),
            openssl_pkey_get_private(file_get_contents($keyPath), $password),
        ];
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
