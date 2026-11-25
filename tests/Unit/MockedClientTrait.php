<?php

declare(strict_types=1);

namespace SixtyDB\Tests\Unit;

use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use SixtyDB\Client;

trait MockedClientTrait
{
    /** @var list<array{request:\Psr\Http\Message\RequestInterface}> */
    private array $history = [];

    /** @param list<Response> $responses */
    private function client(array $responses): Client
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));
        return new Client('test-key', ['httpClient' => new Guzzle(['handler' => $stack]), 'baseUrl' => 'https://example.test/']);
    }

    private function jsonResponse(array $body, int $status = 200): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($body));
    }

    /** @return array{0:string,1:string,2:\Psr\Http\Message\RequestInterface} "METHOD /path?query", raw body, request */
    private function sent(int $i): array
    {
        $r = $this->history[$i]['request'];
        $q = $r->getUri()->getQuery();
        return [$r->getMethod() . ' ' . $r->getUri()->getPath() . ($q !== '' ? "?$q" : ''), (string) $r->getBody(), $r];
    }

    /**
     * Run each case against a client answering `{success, data: {ok: true}}`; check the unwrapped data, the route and
     * the body (null = no body, string = exact raw body, array = decoded JSON).
     *
     * @param list<array{0:callable,1:string,2:array<mixed>|string|null}> $cases
     */
    private function assertRoutes(array $cases): void
    {
        $ok = $this->jsonResponse(['success' => true, 'data' => ['ok' => true]]);
        $c = $this->client(array_fill(0, count($cases), $ok));
        foreach ($cases as $i => [$call, $route, $body]) {
            $this->assertSame(['ok' => true], $call($c), "data of case $i ($route)");
            [$gotRoute, $raw] = $this->sent($i);
            $this->assertSame($route, $gotRoute, "route of case $i");
            if ($body === null) {
                $this->assertSame('', $raw, "no body for $route");
            } elseif (is_string($body)) {
                $this->assertSame($body, $raw, "body of $route");
            } else {
                $this->assertSame($body, json_decode($raw, true), "body of $route");
            }
        }
        $this->assertCount(count($cases), $this->history);
    }
}
