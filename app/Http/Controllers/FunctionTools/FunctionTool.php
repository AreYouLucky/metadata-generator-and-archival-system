<?php

namespace App\Http\Controllers\FunctionTools;

use App\Services\Chat\ChatScope;

/**
 * A function the "Ask me" model may call (Phase 7). The model learns about
 * the archive only through these tools and is told to answer only from what
 * they return. Every tool reads through the asker's ChatScope: approved
 * records the account may see, with internal fields only where the account
 * may open the record. Tools about one record extend RecordTool, which
 * checks the `record_id` the model sends against that scope.
 *
 * Register a new tool in FunctionToolbox::TOOLS.
 */
interface FunctionTool
{
    /**
     * Snake case, unique (the AI service's chat prompt refers to some names).
     */
    public function name(): string;

    /**
     * What the tool returns and when to use it, for the model.
     */
    public function description(): string;

    /**
     * Strict JSON schema of the arguments: every property listed in
     * `required`, optional ones typed with "null", no additional properties.
     *
     * @return array<string, mixed>
     */
    public function parameters(): array;

    /**
     * Laravel validation rules for the arguments the model sent.
     *
     * @return array<string, mixed>
     */
    public function rules(): array;

    /**
     * @param  array<string, mixed>  $arguments  validated
     */
    public function handle(ChatScope $scope, array $arguments): ToolResult;
}
