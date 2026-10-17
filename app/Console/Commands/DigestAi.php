<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Ai\Agents\HealthCheck;
use App\Digest\Ai\AiConfigurationException;
use App\Digest\Ai\ProviderChain;
use Illuminate\Console\Command;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;

class DigestAi extends Command
{
    protected $signature = 'digest:ai';

    protected $description = 'Send a tiny structured prompt to each provider of DIGEST_AI_PROVIDERS and report which ones work';

    public function handle(): int
    {
        try {
            $chain = ProviderChain::fromConfig();
        } catch (AiConfigurationException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $rows = [];
        $failed = false;

        // One provider at a time: through the failover chain, a broken primary would go unnoticed.
        foreach ($chain as $provider => $model) {
            try {
                $response = (new HealthCheck)->prompt('Health check.', provider: [$provider => $model], timeout: (int) config('digest.ai_timeout'));
                $ok = $response instanceof StructuredAgentResponse && ($response->toArray()['ok'] ?? false) === true;
                $rows[] = [$provider, $model, $ok ? 'OK' : 'Unexpected answer'];
                $failed = $failed || ! $ok;
            } catch (Throwable $e) {
                $rows[] = [$provider, $model, $e->getMessage()];
                $failed = true;
            }
        }

        $this->table(['Provider', 'Model', 'Result'], $rows);

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
