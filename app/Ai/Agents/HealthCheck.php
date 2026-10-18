<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/** Smallest possible structured prompt, used by digest:ai to check a provider's key, model and JSON output. */
class HealthCheck implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return 'You are a health check. Always answer with ok set to true.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'ok' => $schema->boolean()->required(),
        ];
    }
}
