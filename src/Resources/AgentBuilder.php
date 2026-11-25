<?php

declare(strict_types=1);

namespace SixtyDB\Resources;

use SixtyDB\Http;
use SixtyDB\Params;
use SixtyDB\Poll;
use SixtyDB\Upload;

/** AI agent builder: draft an agent from a description and documents. Methods return the unwrapped `data`. */
final class AgentBuilder
{
    public function __construct(private Http $http)
    {
    }

    /**
     * Start a draft (multipart). 202 → `{id, status}`.
     *
     * Each entry of `files` (0-5; pdf, docx, doc, txt, md, rtf, html, csv, xlsx, pptx; up to 20 MB) is a file path,
     * a stream resource, or `['file' => <path|resource|bytes>, 'filename' => 'faq.pdf']` (bytes need a filename).
     *
     * @param array<string,mixed> $params description, direction (inbound|outbound), language?, text?, website_url?, files?
     */
    public function createDraft(array $params): mixed
    {
        $files = $params['files'] ?? [];
        unset($params['files']);
        $parts = Params::fields($params);
        foreach (is_array($files) && array_is_list($files) ? $files : [$files] as $file) {
            [$bytes, $name] = self::file($file);
            $parts[] = ['name' => 'files', 'contents' => $bytes, 'filename' => $name];
        }
        return $this->http->data('POST', '/agents/builder/drafts', ['multipart' => $parts]);
    }

    /** `{id, status, error?, draft?{agent, voice, warnings, suggestions, sources}}`; `draft.agent` is an `agents->create` payload. */
    public function getDraft(string $draftId): mixed
    {
        return $this->http->data('GET', Http::path('/agents/builder/drafts/%s', $draftId));
    }

    /**
     * Poll until the draft is `ready` or `failed` and return it either way. 429, 5xx and network errors are retried.
     *
     * @param array{timeoutMs?:int,intervalMs?:int} $options defaults 300000 / 2000
     */
    public function waitForDraft(string $draftId, array $options = []): mixed
    {
        return Poll::until(
            fn () => $this->getDraft($draftId),
            static fn ($d) => is_array($d) && in_array($d['status'] ?? null, ['ready', 'failed'], true),
            $options['intervalMs'] ?? 2000,
            $options['timeoutMs'] ?? 5 * 60 * 1000,
            'Timed out waiting for the agent draft',
        );
    }

    /** @return array{0:string,1:string} bytes and filename */
    private static function file(mixed $file): array
    {
        $filename = null;
        if (is_array($file)) {
            $filename = $file['filename'] ?? null;
            $file = $file['file'] ?? null;
        }
        [$bytes, $pathName] = Upload::read($file);
        $name = $filename ?? $pathName;
        if (!is_string($name) || $name === '') {
            throw new \InvalidArgumentException("A file given as bytes needs a filename: ['file' => \$bytes, 'filename' => 'faq.pdf']");
        }
        return [$bytes, $name];
    }
}
