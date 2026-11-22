<?php

namespace App\Http\Requests\Concerns;

use App\Models\User;
use App\Services\Usage\ProcessingLimit;
use Closure;
use Illuminate\Validation\Validator;

/**
 * For requests that start processing (new record, retry): adds a
 * `daily_limit` error when the encoder has used today's minutes
 * (PROJECT_SPEC §14). Admins have no limit.
 */
trait ChecksDailyProcessingLimit
{
    /**
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $user = $this->user();
                $message = $user instanceof User ? app(ProcessingLimit::class)->exceededMessage($user) : null;

                if ($message !== null) {
                    $validator->errors()->add('daily_limit', $message);
                }
            },
        ];
    }
}
