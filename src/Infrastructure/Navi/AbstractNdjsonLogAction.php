<?php

declare(strict_types=1);

namespace App\Infrastructure\Navi;

use Navi\Domain\Execution\Action;
use Navi\Domain\Execution\ActionResult;
use Navi\Domain\Execution\Context;
use Navi\Domain\Execution\Event;
use Navi\Domain\Execution\ExecutionState;

abstract readonly class AbstractNdjsonLogAction implements Action
{
    public function __construct(
        protected string $logFile,
    ) {
    }

    final public function execute(Context $context, ExecutionState $state): ActionResult
    {
        $record = $this->buildRecord($context);
        $line = json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) . "\n";

        $dir = \dirname($this->logFile);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return ActionResult::continueWith($context, Event::fromName('flowvox.trace_failed', ['reason' => 'mkdir']));
        }

        if (false === @file_put_contents($this->logFile, $line, \FILE_APPEND | \LOCK_EX)) {
            return ActionResult::continueWith($context, Event::fromName('flowvox.trace_failed', ['reason' => 'write']));
        }

        return ActionResult::continueWith($context, Event::fromName('flowvox.trace_written'));
    }

    /**
     * @return array<string, mixed>
     */
    abstract protected function buildRecord(Context $context): array;

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    protected function baseRecord(
        string $sessionId,
        string $eventType,
        string $occurredAt,
        array $payload,
    ): array {
        $record = [
            'schema_version' => 1,
            'at' => gmdate('c'),
            'session_id' => $sessionId,
            'event' => $eventType,
            'occurred_at' => $occurredAt,
            'payload' => $payload,
        ];

        foreach ($this->indexedFields($payload) as $key => $value) {
            $record[$key] = $value;
        }

        return $record;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    protected function indexedFields(array $payload): array
    {
        $fields = [];
        foreach (['provider', 'recording_id', 'path', 'source', 'error'] as $key) {
            if (array_key_exists($key, $payload)) {
                $fields[$key] = $payload[$key];
            }
        }

        if (array_key_exists('text_length', $payload)) {
            $fields['text_length'] = $payload['text_length'];
        } elseif (array_key_exists('text', $payload) && \is_string($payload['text'])) {
            $fields['text_length'] = mb_strlen($payload['text']);
        }

        return $fields;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    protected function slimPayload(string $eventType, array $payload): array
    {
        if ($eventType !== 'transcription_partial' || !isset($payload['text']) || !\is_string($payload['text'])) {
            return $payload;
        }

        $text = $payload['text'];
        unset($payload['text']);

        $payload['text_preview'] = mb_substr($text, 0, 120);
        $payload['text_length'] = mb_strlen($text);

        return $payload;
    }
}
