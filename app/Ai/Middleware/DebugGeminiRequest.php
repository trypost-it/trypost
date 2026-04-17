<?php

declare(strict_types=1);

namespace App\Ai\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\StructuredAgentResponse;

class DebugGeminiRequest
{
    public function handle(AgentPrompt $prompt, Closure $next)
    {
        Log::debug('Agent prompt debug', [
            'agent' => get_class($prompt->agent),
            'model' => $prompt->model,
            'has_structured_output' => $prompt->agent instanceof HasStructuredOutput,
            'has_tools' => $prompt->agent instanceof HasTools,
            'tools_count' => $prompt->agent instanceof HasTools ? count(iterator_to_array($prompt->agent->tools())) : 0,
        ]);

        return $next($prompt)->then(function (AgentResponse $response) {
            Log::debug('Agent response debug', [
                'response_class' => get_class($response),
                'text_length' => strlen($response->text ?? ''),
                'text_preview' => substr($response->text ?? '', 0, 200),
                'is_structured' => $response instanceof StructuredAgentResponse,
                'structured_data' => $response instanceof StructuredAgentResponse ? $response->structured : null,
            ]);
        });
    }
}
