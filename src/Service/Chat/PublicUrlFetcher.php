<?php

namespace App\Service\Chat;

use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Fetches a URL the user typed (link previews, GIF import) without letting it reach the server's own network.
 *
 * `NoPrivateNetworkHttpClient` checks the address the connection really used, on every redirect hop, so host
 * names that resolve to private addresses and redirects to internal hosts are refused (a literal address
 * check on the typed URL would miss both). The body is read in chunks and stops at `$maxBytes`.
 */
final class PublicUrlFetcher
{
    private HttpClientInterface $client;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = new NoPrivateNetworkHttpClient($client);
    }

    /**
     * @param array<string, string> $headers
     *
     * @return array{status: int, contentType: string, body: string, url: string, truncated: bool}
     *
     * @throws \Throwable when the host is private, unreachable or the request fails
     */
    public function fetch(string $url, array $headers, int $maxBytes, float $timeout = 8.0): array
    {
        $response = $this->client->request('GET', $url, [
            'headers' => $headers,
            'timeout' => $timeout,
            'max_duration' => $timeout * 2,
            'max_redirects' => 5,
        ]);

        $status = $response->getStatusCode();
        $contentType = strtolower(trim(explode(';', (string) ($response->getHeaders(false)['content-type'][0] ?? ''), 2)[0]));

        $body = '';
        $truncated = false;
        foreach ($this->client->stream($response) as $chunk) {
            $body .= $chunk->getContent();
            if (strlen($body) > $maxBytes) {
                $body = substr($body, 0, $maxBytes);
                $truncated = true;
                $response->cancel();
                break;
            }
        }

        $finalUrl = $response->getInfo('url');

        return [
            'status' => $status,
            'contentType' => $contentType,
            'body' => $body,
            'url' => is_string($finalUrl) && $finalUrl !== '' ? $finalUrl : $url,
            'truncated' => $truncated,
        ];
    }
}
