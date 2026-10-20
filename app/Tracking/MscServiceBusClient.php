<?php

namespace App\Tracking;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MscServiceBusClient
{

    private string $queueUrl;

    private string $keyName;

    private string $key;

    public function __construct()
    {

        $parts = [];

        foreach (explode(';', (string) config('tracking.msc.service_bus.connection_string')) as $part) {

            if (str_contains($part, '=')) {

                [$name, $value] = explode('=', trim($part), 2);

                $parts[strtolower($name)] = $value;

            }

        }

        $endpoint = parse_url($parts['endpoint'] ?? '');

        $queue = trim((string) config('tracking.msc.service_bus.queue'), '/');

        if (! is_array($endpoint) || ! in_array($endpoint['scheme'] ?? '', ['sb', 'https'], true)
            || ! preg_match('/^[a-z0-9-]+\.servicebus\.windows\.net$/i', $endpoint['host'] ?? '')
            || isset($endpoint['user']) || isset($endpoint['pass']) || isset($endpoint['port'])
            || isset($endpoint['query']) || isset($endpoint['fragment'])
            || ! in_array($endpoint['path'] ?? '', ['', '/'], true)
            || ! preg_match('/^[a-zA-Z0-9_~.\/-]+$/D', $queue)
            || in_array('..', explode('/', $queue), true)
            || empty($parts['sharedaccesskeyname']) || empty($parts['sharedaccesskey'])) {

            throw new RuntimeException('Configuración SAS de Service Bus incompleta o inválida.');

        }
        if (isset($parts['entitypath']) && trim($parts['entitypath'], '/') !== $queue) {

            throw new RuntimeException('EntityPath no coincide con MSC_SERVICE_BUS_QUEUE.');

        }

        $this->queueUrl = 'https://'.strtolower($endpoint['host']).'/'.$queue;

        $this->keyName = $parts['sharedaccesskeyname'];

        $this->key = $parts['sharedaccesskey'];

    }

    public function source(): string
    {

        return $this->queueUrl;

    }

    private function authorization(): string
    {

        $resource = rawurlencode($this->queueUrl);

        $expires = time() + 3600;

        $signature = rawurlencode(base64_encode(hash_hmac('sha256', $resource."\n".$expires, $this->key, true)));

        return 'SharedAccessSignature sr='.$resource.'&sig='.$signature.'&se='.$expires.'&skn='.rawurlencode($this->keyName);

    }

    /** Receive under a lock; never use receive-and-delete. */
    public function receive(): ?array
    {

        $response = $this->request('POST', $this->queueUrl.'/messages/head?timeout=20');

        if ($response->status() === 204) {

            return null;

        }
        if ($response->status() !== 201) {

            throw new RuntimeException('Service Bus: recepción fallida (HTTP '.$response->status().').');

        }
        $location = $response->header('Location');

        $this->validateLocation($location);

        return ['body' => $response->body(), 'headers' => $response->headers(),
            'broker' => json_decode($response->header('BrokerProperties'), true, 512, JSON_THROW_ON_ERROR),
            'location' => $location];

    }

    public function complete(string $location): void
    {

        $this->validateLocation($location);

        $response = $this->request('DELETE', $location);

        if ($response->status() !== 200) {

            throw new RuntimeException('Service Bus: confirmación fallida (HTTP '.$response->status().'). El mensaje guardado puede volver a entregarse.');

        }

    }

    private function validateLocation(string $location): void
    {

        if (! str_starts_with($location, $this->queueUrl.'/messages/')
            || str_contains($location, '..') || str_contains($location, '#')) {

            throw new RuntimeException('Service Bus devolvió una ubicación de bloqueo inválida.');

        }

    }

    private function request(string $method, string $url): Response
    {

        try {

            // No redirects or automatic retries: a lost receive response may still hold a lock.
            return Http::withHeaders(['Authorization' => $this->authorization()])
                ->withOptions(['allow_redirects' => false])->connectTimeout(10)->timeout(30)
                ->send($method, $url);

        } catch (ConnectionException) {

            throw new RuntimeException('No se pudo conectar con Service Bus; se reintentará sin exponer credenciales.');

        }

    }

}
