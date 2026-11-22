<?php

namespace App\Http\Controllers\FunctionTools;

use App\Models\Record;
use App\Services\Chat\ChatScope;

/**
 * A tool about one record. Adds a required `record_id` argument, resolves it
 * through the asker's ChatScope (an ID that isn't an approved record the
 * account may see gets the same "not available" answer as one that doesn't
 * exist, so nothing leaks), and tags the moments it returns with the record.
 */
abstract class RecordTool implements FunctionTool
{
    /**
     * Properties of the tool's own arguments (record_id is added).
     *
     * @return array<string, mixed>
     */
    abstract protected function properties(): array;

    /**
     * Rules for the tool's own arguments.
     *
     * @return array<string, mixed>
     */
    abstract protected function recordRules(): array;

    /**
     * @param  bool  $internal  the account may see the record's internal fields
     * @param  array<string, mixed>  $arguments
     */
    abstract protected function handleRecord(Record $record, bool $internal, array $arguments, ChatScope $scope): ToolResult;

    public function parameters(): array
    {
        $properties = [
            'record_id' => ['type' => 'string', 'description' => 'The record id (from search_records or the page context).'],
            ...$this->properties(),
        ];

        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => array_keys($properties),
            'additionalProperties' => false,
        ];
    }

    public function rules(): array
    {
        return [
            'record_id' => ['required', 'string', 'size:26', 'alpha_num'],
            ...$this->recordRules(),
        ];
    }

    public function handle(ChatScope $scope, array $arguments): ToolResult
    {
        $record = $scope->find((string) $arguments['record_id']);

        if ($record === null) {
            return ToolResult::error('No approved record with that id is available to this account. Use search_records to find one.');
        }

        return $this->handleRecord($record, $scope->seesInternal($record), $arguments, $scope)->forRecord($record->id);
    }
}
