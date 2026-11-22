<?php

namespace Tests\Feature\Jobs;

use App\Jobs\CancelProcessingJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CancelProcessingJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_run_is_cancelled_with_its_id_in_the_signed_body(): void
    {
        Http::preventStrayRequests();
        Http::fake(['http://ai-api.test/jobs/run-1/cancel' => Http::response(['ok' => true])]);

        CancelProcessingJob::dispatchSync('01jdz8q7x3v5k9m2n4p6r8t0w1', 'run-1');

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('X-Signature') && $request['job_id'] === 'run-1');
    }
}
