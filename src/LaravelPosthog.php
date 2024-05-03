<?php

namespace QodeNL\LaravelPosthog;

use Auth;
use Log;
use PostHog\PostHog;
use QodeNL\LaravelPosthog\Jobs\PosthogAliasJob;
use QodeNL\LaravelPosthog\Jobs\PosthogBaseJob;
use QodeNL\LaravelPosthog\Jobs\PosthogCaptureJob;
use QodeNL\LaravelPosthog\Jobs\PosthogIdentifyJob;
use function Symfony\Component\Translation\t;

class LaravelPosthog
{

    protected string $sessionId;

    public function __construct()
    {
        $this->sessionId = Auth::user()
            ? config('posthog.user_prefix', 'user') . ':' . Auth::user()->id
            : sha1(session()->getId());
    }

    private function posthogEnabled(): bool
    {
        if (strcmp(env('posthog.allow_domain'), request()->host())) {
            return false;
        } 

        if (!config('posthog.enabled') || config('posthog.key') === '') {
            return false;
        }

        return true;
    }

    public function identify(string $email, array $properties = []): void
    {
        if ($this->posthogEnabled()) {
            PosthogIdentifyJob::dispatch($this->sessionId, $email, $properties);
        } else {
            Log::debug('PosthogIdentifyJob not dispatched because posthog is disabled');
        }
    }

    public function capture(string $event, array $properties = []): void
    {
        if ($this->posthogEnabled()) {
            PosthogCaptureJob::dispatch($this->sessionId, $event, $properties);
        } else {
            Log::debug('PosthogCaptureJob not dispatched because posthog is disabled');
        }
    }

    public function alias(string $userId): void
    {
        if ($this->posthogEnabled()) {
            PosthogAliasJob::dispatch($this->sessionId, $userId);
        } else {
            Log::debug('PosthogAliasJob not dispatched because posthog is disabled');
        }
    }

    public function isFeatureEnabled(
        string $featureKey,
        array $groups = [],
        array $personProperties = [],
        array $groupProperties = [],
    ): bool {
        return (bool) $this->getFeatureFlag(
            $featureKey,
            $groups,
            $personProperties,
            $groupProperties,
        );
    }

    public function getFeatureFlag(
        string $featureKey,
        array $groups = [],
        array $personProperties = [],
        array $groupProperties = [],
    ): null|bool|string {
        if ($this->posthogEnabled()) {
            (new PosthogBaseJob())->init();

            return Posthog::getFeatureFlag(
                $featureKey,
                $this->sessionId,
                $groups,
                $personProperties,
                $groupProperties,
                config('posthog.feature_flags.evaluate_locally') ?? false,
                config('posthog.feature_flags.send_events') ?? true,
            );
        }

        return config('posthog.feature_flags.default_enabled') ?? false;
    }

    public function getAllFlags(
        array $groups = [],
        array $personProperties = [],
        array $groupProperties = [],
    ): array {
        if ($this->posthogEnabled()) {
            (new PosthogBaseJob())->init();

            return Posthog::getAllFlags(
                $this->sessionId,
                $groups,
                $personProperties,
                $groupProperties,
                config('posthog.feature_flags.evaluate_locally') ?? false
            );
        }

        return [];
    }

}