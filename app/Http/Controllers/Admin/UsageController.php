<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexUsageRequest;
use App\Models\User;
use App\Services\Usage\UsageReport;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin Usage (PROJECT_SPEC §8 `/admin/usage`): minutes, tokens and estimated
 * cost per encoder and per record for a date range, with a CSV download.
 */
class UsageController extends Controller
{
    private const int PER_PAGE = 25;

    public function index(IndexUsageRequest $request): Response
    {
        [$from, $to] = $request->range();
        $report = UsageReport::between($from, $to);
        $encoderId = $request->encoderId();

        return Inertia::render('admin/usage-page/usage', [
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'encoder' => $encoderId,
            ],
            'totals' => $report->totals(),
            'daily' => $report->daily(),
            'encoders' => $report->byEncoder(),
            'records' => $report->byRecord($encoderId, self::PER_PAGE)->withQueryString(),
            'encoderName' => $encoderId === null ? null : User::query()->whereKey($encoderId)->value('name'),
        ]);
    }

    /**
     * One row per record (and encoder), streamed so a long range never sits in
     * memory. Text cells that start with = + - @ are prefixed with a quote, so
     * a spreadsheet never runs them as formulas.
     */
    public function export(IndexUsageRequest $request): StreamedResponse
    {
        [$from, $to] = $request->range();
        $rows = UsageReport::between($from, $to)->recordRows($request->encoderId());
        $filename = "usage-{$from->toDateString()}-to-{$to->toDateString()}.csv";

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            fputcsv($out, ['record_id', 'accession_number', 'title', 'encoder', 'transcription_minutes', 'llm_input_tokens', 'llm_output_tokens', 'vision_frames', 'estimated_cost_usd', 'record_deleted'], escape: '');

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['record_id'],
                    self::cell($row['accession_number']),
                    self::cell($row['title']),
                    self::cell($row['encoder']),
                    $row['minutes'],
                    $row['input_tokens'],
                    $row['output_tokens'],
                    $row['vision_frames'],
                    $row['cost'],
                    $row['deleted'] ? 'yes' : 'no',
                ], escape: '');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=utf-8']);
    }

    private static function cell(mixed $value): string
    {
        $text = (string) $value;

        return $text !== '' && in_array($text[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$text : $text;
    }
}
