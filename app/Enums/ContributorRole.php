<?php

namespace App\Enums;

/**
 * Cast and production roles credited on a record.
 */
enum ContributorRole: string
{
    case Producer = 'producer';
    case Director = 'director';
    case Videographer = 'videographer';
    case Editor = 'editor';
    case Presenter = 'presenter';
    case Cast = 'cast';
    case Contributor = 'contributor';

    public function label(): string
    {
        return match ($this) {
            self::Presenter => 'Presenter / Host',
            default => ucfirst($this->value),
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $role): array => ['value' => $role->value, 'label' => $role->label()], self::cases());
    }
}
