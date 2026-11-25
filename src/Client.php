<?php

declare(strict_types=1);

namespace SixtyDB;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\HandlerStack;
use SixtyDB\Resources\Account;
use SixtyDB\Resources\Agents;
use SixtyDB\Resources\CallAnalysis;
use SixtyDB\Resources\Chat;
use SixtyDB\Resources\Dialer;
use SixtyDB\Resources\Judge;
use SixtyDB\Resources\Memory;
use SixtyDB\Resources\Music;
use SixtyDB\Resources\Stt;
use SixtyDB\Resources\Tts;
use SixtyDB\Resources\Voices;

/**
 * 60db API client.
 *
 * Resources: $client->chat, ->memory, ->tts, ->stt, ->voices, ->account, ->judge, ->music, ->agents, ->dialer,
 * ->callAnalysis.
 * Flat helpers (textToSpeech, textToSpeechStream, speechToText, getVoices, getProfile, ...)
 * forward to the matching resource, e.g. `$client->textToSpeech('Hi')`.
 */
final class Client
{
    public const DEFAULT_BASE_URL = 'https://api.60db.ai';

    public readonly Tts $tts;
    public readonly Stt $stt;
    public readonly Voices $voices;
    public readonly Chat $chat;
    public readonly Memory $memory;
    public readonly Account $account;
    public readonly Judge $judge;
    public readonly Music $music;
    public readonly Agents $agents;
    public readonly Dialer $dialer;
    public readonly CallAnalysis $callAnalysis;

    /**
     * @param array{baseUrl?:string,timeout?:float|int,httpClient?:ClientInterface,handler?:callable} $options
     */
    public function __construct(string $apiKey, array $options = [])
    {
        if ($apiKey === '') {
            throw new \InvalidArgumentException('API key is required');
        }
        $guzzle = $options['httpClient'] ?? new GuzzleClient([
            'handler' => isset($options['handler']) ? HandlerStack::create($options['handler']) : HandlerStack::create(),
        ]);
        $http = new Http(
            $guzzle,
            $apiKey,
            rtrim($options['baseUrl'] ?? self::DEFAULT_BASE_URL, '/'),
            (float) ($options['timeout'] ?? 60),
        );
        $this->tts = new Tts($http);
        $this->stt = new Stt($http);
        $this->voices = new Voices($http);
        $this->chat = new Chat($http);
        $this->memory = new Memory($http);
        $this->account = new Account($http);
        $this->judge = new Judge($http);
        $this->music = new Music($http);
        $this->agents = new Agents($http);
        $this->dialer = new Dialer($http);
        $this->callAnalysis = new CallAnalysis($http);
    }

    /** @param array<mixed> $args */
    public function __call(string $name, array $args): mixed
    {
        foreach ([$this->tts, $this->stt, $this->voices, $this->account] as $resource) {
            if (method_exists($resource, $name)) {
                return $resource->$name(...$args);
            }
        }
        throw new \BadMethodCallException("Unknown method SixtyDB\\Client::$name()");
    }
}
