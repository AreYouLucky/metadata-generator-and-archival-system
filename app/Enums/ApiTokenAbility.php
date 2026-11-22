<?php

namespace App\Enums;

/**
 * What a public-API token may do (Sanctum abilities). A token also acts only
 * within its owner's own permissions.
 */
enum ApiTokenAbility: string
{
    case ReadRecords = 'records:read';
    case CreateRecords = 'records:create';

    public function label(): string
    {
        return match ($this) {
            self::ReadRecords => 'Read records, their status and exports',
            self::CreateRecords => 'Create records (upload or import, starts processing)',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $case): array => ['value' => $case->value, 'label' => $case->label()], self::cases());
    }
}
