# 60db PHP SDK

Official PHP SDK for the [60db](https://60db.ai) API: text-to-speech (incl. streaming), speech-to-text,
voices, chat (incl. streaming), memory, phone voice agents (calls, campaigns, integrations, simulations,
AI agent builder), Call analysis, webhook signatures and account endpoints.

Requires PHP 8.1+ and Guzzle 7.

## Install

```bash
composer require sixtydb/sdk
```

## Quick start

```php
use SixtyDB\Client;
use SixtyDB\Audio;

$client = new Client(getenv('SIXTYDB_API_KEY'));

// Text to speech -> WAV bytes
file_put_contents('hello.wav', $client->textToSpeech('Hello from PHP!'));

// Streaming TTS -> raw 16-bit LE mono PCM chunks (default 48000 Hz)
$pcm = '';
foreach ($client->textToSpeechStream('Streaming!', null, ['sample_rate' => 24000]) as $chunk) {
    $pcm .= $chunk;
}
file_put_contents('stream.wav', Audio::pcmToWav($pcm, 24000));

// Speech to text (path, stream resource, or raw bytes)
$result = $client->speechToText('hello.wav', 'en');
echo $result['text'];

// Chat
$res = $client->chat->completion(['messages' => [['role' => 'user', 'content' => 'Hi']]]);
echo $res['choices'][0]['message']['content'];
foreach ($client->chat->stream(['messages' => [['role' => 'user', 'content' => 'Hi']]]) as $chunk) {
    echo $chunk['choices'][0]['delta']['content'] ?? '';
}

// Memory
$client->memory->ingest('Alice likes tea.');
$ctx = $client->memory->context('What does Alice like?');
echo $ctx['data']['prompt_ready'];
```

Errors: every non-2xx response, transport failure or in-stream error throws `SixtyDB\SixtyDBException`
with `getStatus()`, `getMessage()`, `getErrorCode()`, `getField()` and `getBody()`.

Options: `new Client($key, ['baseUrl' => ..., 'timeout' => 60, 'httpClient' => $guzzle, 'handler' => $handler])`.
Responses are decoded JSON arrays (full body, e.g. `['success' => true, 'data' => ...]`).

## Methods

Flat helpers on `$client` forward to the resource shown.

| Method | Endpoint |
|---|---|
| `textToSpeech($text, $voiceId = null, $params = [])` | POST /tts-synthesize (returns WAV bytes) |
| `textToSpeechStream(...)` (Generator of PCM) | POST /tts-synthesize `stream:true` |
| `speechToText($file, $language, $diarize, $context, $filename)` | POST /stt |
| `getVoices`, `getVoice`, `getMyVoices`, `createVoice($name, $files)`, `updateVoice`, `deleteVoice` | /voices, /myvoices |
| `getLanguages` (STT), `getTTSLanguages`, `getTTSModels`, `getSTTModels` | /stt/languages, /tts/languages, /tts/models, /stt/models |
| `chat->completion($params)`, `chat->stream($params)` | POST /v1/chat/completions |
| `memory->ingest/search/context/extractDocument/usage` | /memory/* |
| `getProfile`, `updateProfile`, `getWorkspaces`, `createWorkspace`, `getPlans`, `getCurrentPlan`, `subscribe`, `getUsage`, `signUp`, `signIn` | account |
| `getApiKeys`, `createApiKey`, `deleteApiKey`, `getWebhooks`, `createWebhook`, `deleteWebhook` | account |
| `judge->evaluate/extract/models/usage/health`, `judge->rubrics->list/create/get/update/delete`, `judge->runs->list/get/delete` | /judge/* (unwrapped `data`; `runs->list` returns `['runs','pagination']`) |
| `music->create/list/get/update/delete/download/waitForCompletion/listVoices/createVoice/health` | /songs/* (`create` with `lyrics_file` is multipart; `download` returns MP3 bytes) |
| `agents->list/get/create/update/delete/rotateWebhookSecret/toolEvents/call/streamCall/webCall/backgroundSounds`, `agents->calls->list/get/placeInline/usage/toolEvents/transcript/recording/deleteRecording/result/endStream/hangUpWeb/waitForCompletion`, `agents->phoneLines->list/availableNumbers/get/create/update/delete/setup/check`, `agents->settings->get/update/rotateWebhookSecret` | /agents/* (unwrapped `data`; camelCase keys such as `agentId`, `phoneLineId`, `callerId` are mapped to snake_case) |
| `agents->builder->createDraft/getDraft/waitForDraft` | /agents/builder/* ([AI agent builder](#ai-agent-builder)) |
| `agents->campaigns->list/summary/previewRecipients/create/get/analytics/pause/resume/cancel`, `agents->campaigns->recipients->list/add/preview/template/export/calls/cancelCallback/callbackNow` | /agents/campaigns/* ([Campaigns](#campaigns)) |
| `agents->doNotCall->list/add/remove/removeByPhone` | /agents/do-not-call ([Do-not-call](#do-not-call)) |
| `agents->integrations->catalog/list/create/update/delete/test/rotateSecret/tools/assignAgents/runAction/request/googleSheetTabs/googleSheetColumns` | /agents/integrations/* ([Integrations](#integrations)) |
| `agents->simulations->list/start/estimate/get/waitForRun/pause/resume/cancel/createDraft/updateDraft/rerun/rerunCall/suggestFix/applyFix/undoFix/evals/metrics/compareMetrics/runGolden/getGoldenRun` | /agents/{id}/simulations, /agents/simulations/* ([Simulations](#simulations)) |
| `callAnalysis->analyzers->list/create/get/update/archive`, `callAnalysis->sources->get/set`, `callAnalysis->batches->create/get/items/createUploadSlot/confirmUpload/fetchRecording/fetchRecordings/analyze`, `callAnalysis->agentFix->suggest/apply/undo`, `callAnalysis->dialerNumbers/analyzeAgentCalls/analyzeDialerCalls/analyzeAll/recordings/problems/analytics/overview/getCall` | /deep-analytics/* ([Call analysis](#call-analysis)) |
| `dialer->status`, `dialer->numbers/calls/recordings/billing/webhooks->...` | /dialer/* (full body) |
| `WebhookSignature::compute / verify` | offline HMAC-SHA256 check of `X-60db-Signature` |
| `Audio::pcmToWav($pcm, $sampleRate)` | helper |

```php
use SixtyDB\WebhookSignature;
$ok = WebhookSignature::verify($rawBody, $_SERVER['HTTP_X_60DB_SIGNATURE'] ?? null,
    $_SERVER['HTTP_X_60DB_TIMESTAMP'] ?? null, $secret);
```

## Agents

`$client->agents` needs an API key with the `agents` scope. Methods return the unwrapped `data`. The paged lists
(`campaigns->list`, `campaigns->recipients->list`, `doNotCall->list`, `simulations->list`, `callAnalysis->batches->items`)
return `['data' => [...], 'pagination' => ['total' => .., 'limit' => .., 'offset' => ..]]`. The newer methods take the
API's snake_case field names. Failures throw `SixtyDBException` (`getStatus()`, `getErrorCode()`, `getField()`).

> **Costs.** Placing calls (`agents->call`, `agents->calls->placeInline`), starting a campaign and adding recipients to
> one ring real phones and spend credits. Simulations, reruns, golden runs and fix suggestions spend credits too.

### Calls, web calls, settings and phone lines

```php
// `variables` fill {{name}} in the agent's script
$call = $client->agents->call($agentId, ['to' => '+14155550100', 'phoneLineId' => $lineId, 'variables' => ['name' => 'Asha']]);
$call = $client->agents->calls->waitForCompletion($call['id']);
$usage = $client->agents->calls->usage($call['id']);   // measured, total, services, turns, phone, price_usd
$result = $client->agents->calls->result($call['id']); // per-turn latency slices and eval results
$client->agents->calls->list(['agentId' => $agentId, 'sentiment' => 'happy', 'from' => '2026-11-01']);

// A call that carries its own agent; an Idempotency-Key makes a retry safe
$client->agents->calls->placeInline([
    'to' => '+14155550100',
    'phone_line_id' => $lineId,
    'agent' => ['script' => 'You confirm appointments.', 'voice_id' => $voiceId],
    'settings' => ['max_duration_secs' => 300],
], ['idempotencyKey' => 'appointment-1042']);

// Browser call: send the WebRTC offer, give `answer` to RTCPeerConnection.setRemoteDescription
$web = $client->agents->webCall($agentId, ['offer' => $offerSdp]);   // ['call' => [...], 'answer' => 'v=0...']
$client->agents->calls->hangUpWeb($web['call']['id']);
$client->agents->calls->endStream($streamCallId);                    // a call started with streamCall()

$client->agents->settings->update(['event_webhook_url' => 'https://example.com/hooks/60db']); // returns webhook_secret
$client->agents->backgroundSounds();                                  // names for call_defaults.background_sound

$client->agents->phoneLines->setup($lineId);                          // what to give your carrier
$client->agents->phoneLines->check($lineId, ['called_number' => '+14155550100', 'source_ip' => '203.0.113.9']);
```

BYO-trunk phone lines also take `numbers` (`[['number' => ..., 'agent_id' => ...]]`), `allowed_addresses`,
`allowed_numbers`, `normalize_numbers`, `default_country_code` and `bridge_transfers`; `username` / `password` are
optional for a carrier that trusts your IP.

### AI agent builder

```php
$draft = $client->agents->builder->createDraft([
    'description' => 'Front desk for a dental clinic: book, move and cancel appointments.',
    'direction' => 'inbound',                     // or 'outbound'
    'website_url' => 'https://clinic.example',
    'files' => ['faq.pdf', ['file' => $bytes, 'filename' => 'prices.txt']],  // up to 5 documents
]);
$draft = $client->agents->builder->waitForDraft($draft['id']); // returns on `ready` or `failed` (does not throw on failed)
if ($draft['status'] === 'ready') {
    $agent = $client->agents->create($draft['draft']['agent']);
}
```

### Campaigns

```php
$check = $client->agents->campaigns->previewRecipients(['csv_text' => $csv, 'agent_id' => $agentId]);
$campaign = $client->agents->campaigns->create([
    'name' => 'Renewals', 'agent_id' => $agentId, 'phone_line_id' => $lineId,
    'timezone' => 'Asia/Kolkata', 'csv_text' => $csv,
    'calling_windows' => [['days' => [1, 2, 3, 4, 5], 'start' => '10:00', 'end' => '18:00']],
], ['idempotencyKey' => 'renewals-nov']);

$page = $client->agents->campaigns->list(['status' => 'running', 'limit' => 20]); // ['data' => [...], 'pagination' => [...]]
$client->agents->campaigns->analytics($campaign['id'], ['from' => '2026-11-01', 'to' => '2026-11-30']);
$client->agents->campaigns->pause($campaign['id']);   // resume(), cancel()

$recipients = $client->agents->campaigns->recipients;
$recipients->add($campaign['id'], ['recipients' => [['phone' => '+919800000001', 'variables' => ['name' => 'Asha']]]],
    ['idempotencyKey' => 'renewals-nov-batch-2']);
$waiting = $recipients->list($campaign['id'], ['status' => 'callback']);
file_put_contents('recipients.csv', $recipients->export($campaign['id'], ['status' => 'completed'])); // CSV text
$recipients->callbackNow($campaign['id'], $recipientId);
```

### Do-not-call

```php
$client->agents->doNotCall->add(['phone' => '+919800000001', 'reason' => 'Asked not to be called']);
$entries = $client->agents->doNotCall->list(['q' => '98000']);   // ['data' => [...], 'pagination' => [...]]
$client->agents->doNotCall->removeByPhone('+919800000001');     // or remove($entryId)
```

### Integrations

Key, webhook and MCP providers connect from code (the credentials are tested live). OAuth providers are connected in
the 60db dashboard, because the OAuth callback is bound to the browser that started it.

```php
$catalog = $client->agents->integrations->catalog();
$crm = $client->agents->integrations->create(['provider' => 'hubspot', 'name' => 'HubSpot (sales)', 'fields' => ['token' => $token]]);
$tools = $client->agents->integrations->tools($crm['id']);   // [['key' => 'search_contact', ...], ...]
$client->agents->integrations->assignAgents($crm['id'], [['agent_id' => $agentId, 'tools' => array_column($tools, 'key')]]);
$out = $client->agents->integrations->runAction($crm['id'], 'search_contact', ['email' => 'asha@example.com']); // ['ok' => .., 'result' => ..]
```

### Simulations

```php
$sims = $client->agents->simulations;
$estimate = $sims->estimate($agentId, ['scenarios' => 10, 'repeats' => 2]);                // free
$run = $sims->start($agentId, ['mode' => 'generate', 'count' => 10, 'max_credits' => 20]);
$run = $sims->waitForRun($run['run_id']);   // finished, cancelled or stopped_budget
$fix = $sims->suggestFix($run['run_id']);   // one paid model request; saves nothing
$sims->applyFix($run['run_id'], ['base_hash' => $fix['base_hash'], 'script' => $fix['after']]);
$sims->metrics($agentId, ['source' => 'simulation', 'agent_version' => 3]);
$golden = $sims->runGolden($agentId, 'polite');            // one paid request per golden fixture
```

## Call analysis

`$client->callAnalysis` (base path `/deep-analytics`) needs a user API key with the `call_analysis` permission; without
it the API answers 403 `DA_API_KEY_SCOPE_MISSING`. Ids are UUIDs, and an "analyzer" is an analysis agent. Errors are
the same `SixtyDBException`.

```php
$analyzer = $client->callAnalysis->analyzers->create(['name' => 'Refunds', 'instructions' => 'Find wrong refund answers.']);
$client->callAnalysis->sources->set($analyzer['id'], ['agent_ids' => [$agentId], 'auto_new' => true]);
$client->callAnalysis->analyzeAgentCalls($analyzer['id'], [$callId]);

// Recordings in through a CSV batch
$batch = $client->callAnalysis->batches->create($analyzer['id'], ['source_kind' => 'csv_urls', 'csv_text' => $csv]);
$items = $client->callAnalysis->batches->items($batch['id'], ['state' => 'draft']); // ['data' => [...], 'pagination' => [...]]
$client->callAnalysis->batches->fetchRecordings($batch['id']);
$client->callAnalysis->batches->analyze($analyzer['id'], $batch['id'], $batch['valid']);

$problems = $client->callAnalysis->problems($analyzer['id'], ['from' => '2026-11-01']);
$one = $client->callAnalysis->getCall($callId);                 // one call's analysis
$fix = $client->callAnalysis->agentFix->suggest($callId);
```

## Tests

```bash
composer install
composer test                                  # offline unit tests
SIXTYDB_API_KEY=... composer test-live         # live scenarios (skipped without a key)
```
