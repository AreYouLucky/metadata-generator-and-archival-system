<?php

namespace App\Http\Controllers\FunctionTools;

use App\Services\Chat\ChatScope;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * The chat's function tools (Phase 7): the only way the model learns
 * anything about the archive. Lists their definitions for the AI service, and
 * runs a call: unknown names are refused, arguments are validated with each
 * tool's rules, failures become an error result (never an exception), and
 * an oversized result is replaced by an error asking for a narrower call.
 */
class FunctionToolbox
{
    /**
     * Every tool the model may call.
     *
     * @var list<class-string<FunctionTool>>
     */
    public const array TOOLS = [
        SearchRecordsTool::class,
        GetRecordOverviewTool::class,
        SearchTranscriptTool::class,
        ReadTranscriptTool::class,
        GetGeneratedMetadataTool::class,
        GetVisualContentTool::class,
        GetSpeakersTool::class,
        GetArchivalDetailsTool::class,
        GetTechnicalDetailsTool::class,
    ];

    /**
     * Results longer than this (JSON characters) are refused; the AI service
     * accepts at most 20,000.
     */
    public const int MAX_OUTPUT_CHARS = 16000;

    /** @var array<string, FunctionTool>|null */
    private ?array $tools = null;

    public function __construct(private Container $container) {}

    /**
     * The definitions sent with every chat step.
     *
     * @return list<array{name: string, description: string, parameters: array<string, mixed>}>
     */
    public function definitions(): array
    {
        return array_values(array_map(fn (FunctionTool $tool): array => [
            'name' => $tool->name(),
            'description' => $tool->description(),
            'parameters' => $tool->parameters(),
        ], $this->tools()));
    }

    public function has(string $name): bool
    {
        return isset($this->tools()[$name]);
    }

    /**
     * Run one call the model made, within what the asker may see.
     *
     * @param  array<string, mixed>  $arguments
     */
    public function run(ChatScope $scope, string $name, array $arguments): ToolResult
    {
        $tool = $this->tools()[$name] ?? null;

        if ($tool === null) {
            return ToolResult::error("There is no tool named {$name}.");
        }

        $validator = Validator::make($arguments, $tool->rules());

        if ($validator->fails()) {
            return ToolResult::error('Invalid arguments: '.implode(' ', $validator->errors()->all()));
        }

        try {
            $result = $tool->handle($scope, $validator->validated());
        } catch (Throwable $exception) {
            Log::warning('Chat tool failed', ['user_id' => $scope->user->id, 'tool' => $name, 'error' => $exception->getMessage()]);

            return ToolResult::error('The tool failed; this data cannot be read right now.');
        }

        if (mb_strlen($result->toJson()) > self::MAX_OUTPUT_CHARS) {
            return ToolResult::error('The result is too large. Ask for a shorter time range or fewer results.');
        }

        return $result;
    }

    /**
     * @return array<string, FunctionTool>
     */
    private function tools(): array
    {
        if ($this->tools === null) {
            $this->tools = [];

            foreach (self::TOOLS as $class) {
                $tool = $this->container->make($class);
                $this->tools[$tool->name()] = $tool;
            }
        }

        return $this->tools;
    }
}
