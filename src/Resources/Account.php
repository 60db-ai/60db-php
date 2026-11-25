<?php

declare(strict_types=1);

namespace SixtyDB\Resources;

use SixtyDB\Http;

/** Auth, workspaces, billing, analytics, API keys, webhooks. Return the full JSON body. */
final class Account
{
    public function __construct(private Http $http)
    {
    }

    /** @param array<string,mixed> $params email, password, ... @return array<mixed> */
    public function signUp(array $params): array
    {
        return $this->http->json('POST', '/auth/signup', ['json' => $params]);
    }

    /** @return array<mixed> */
    public function signIn(string $email, string $password): array
    {
        return $this->http->json('POST', '/auth/login', ['json' => ['email' => $email, 'password' => $password]]);
    }

    /** @return array<mixed> */
    public function getProfile(): array
    {
        return $this->http->json('GET', '/auth/me');
    }

    /** @param array<string,mixed> $params @return array<mixed> */
    public function updateProfile(array $params): array
    {
        return $this->http->json('PUT', '/auth/profile', ['json' => $params]);
    }

    /** @return array<mixed> */
    public function getWorkspaces(): array
    {
        return $this->http->json('GET', '/workspaces');
    }

    /** @param array<string,mixed> $extra @return array<mixed> */
    public function createWorkspace(string $name, array $extra = []): array
    {
        return $this->http->json('POST', '/workspaces', ['json' => ['name' => $name] + $extra]);
    }

    /** @return array<mixed> */
    public function getPlans(): array
    {
        return $this->http->json('GET', '/plans');
    }

    /** @return array<mixed> */
    public function getCurrentPlan(): array
    {
        return $this->http->json('GET', '/billing/current-plan');
    }

    /** @return array<mixed> */
    public function subscribe(string $planId): array
    {
        return $this->http->json('POST', '/billing/subscribe', ['json' => ['plan_id' => $planId]]);
    }

    /** @return array<mixed> */
    public function getUsage(): array
    {
        return $this->http->json('GET', '/analytics/usage');
    }

    /** @return array<mixed> */
    public function getApiKeys(): array
    {
        return $this->http->json('GET', '/developer/api');
    }

    /** @return array<mixed> */
    public function createApiKey(string $name): array
    {
        return $this->http->json('POST', '/developer/api', ['json' => ['name' => $name]]);
    }

    /** @return array<mixed> */
    public function deleteApiKey(string $id): array
    {
        return $this->http->json('DELETE', '/developer/api/' . rawurlencode($id));
    }

    /** @return array<mixed> */
    public function getWebhooks(): array
    {
        return $this->http->json('GET', '/webhooks');
    }

    /** @param list<string> $events @param array<string,mixed> $extra @return array<mixed> */
    public function createWebhook(string $url, array $events, array $extra = []): array
    {
        return $this->http->json('POST', '/webhooks', ['json' => ['url' => $url, 'events' => $events] + $extra]);
    }

    /** @return array<mixed> */
    public function deleteWebhook(string $id): array
    {
        return $this->http->json('DELETE', '/webhooks/' . rawurlencode($id));
    }
}
