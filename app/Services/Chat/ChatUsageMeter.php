<?php

namespace App\Services\Chat;

use Illuminate\Container\Attributes\Scoped;

/**
 * Collects the AI usage of one chat answer (model steps and embeddings made
 * by the tools), so RecordChat can bill it to the person who asked. Scoped:
 * a fresh meter per request or queued job.
 */
#[Scoped]
class ChatUsageMeter
{
    /** @var list<array{kind: string, quantity: float, estimated_cost?: float|null}> */
    private array $items = [];

    /**
     * @param  list<array{kind: string, quantity: float, estimated_cost?: float|null}>  $usage
     */
    public function add(array $usage): void
    {
        foreach ($usage as $item) {
            $this->items[] = $item;
        }
    }

    /**
     * Everything collected so far, emptying the meter.
     *
     * @return list<array{kind: string, quantity: float, estimated_cost?: float|null}>
     */
    public function take(): array
    {
        $items = $this->items;
        $this->items = [];

        return $items;
    }
}
