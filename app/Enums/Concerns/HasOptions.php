<?php

namespace App\Enums\Concerns;

/**
 * Labels and select options for a string-backed enum: "top_secret" becomes
 * "Top secret". An enum may define its own label() to override this.
 */
trait HasOptions
{
    public function label(): string
    {
        return ucfirst(str_replace('_', ' ', $this->value));
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $case): array => ['value' => $case->value, 'label' => $case->label()], self::cases());
    }
}
