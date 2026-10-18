<?php

namespace Database\Seeders;

use App\Models\LearningTopic;
use Illuminate\Database\Seeder;

class LearningTopicTableSeeder extends Seeder
{
    /** Intermediate to advanced only. Idempotent: only creates missing topics, never changes existing rows. */
    private const TOPICS = [
        'architecture' => [
            'Hexagonal architecture (ports and adapters)',
            'Modular monoliths and enforcing module boundaries',
            'CQRS: when separating reads and writes pays off',
            'Event sourcing trade-offs',
            'Bounded contexts and context mapping',
            'Strangler fig pattern for legacy rewrites',
        ],
        'databases' => [
            'Covering indexes and index-only scans',
            'Composite index column order',
            'Transaction isolation levels and their anomalies',
            'Optimistic vs pessimistic locking',
            'Reading a PostgreSQL EXPLAIN ANALYZE plan',
            'Zero-downtime schema migrations',
        ],
        'distributed-systems' => [
            'Idempotency keys',
            'The transactional outbox pattern',
            'CAP and PACELC in practice',
            'Exactly-once delivery is a myth: at-least-once plus idempotency',
            'Sagas and compensating transactions',
            'Clock skew, logical clocks and ordering events',
        ],
        'testing' => [
            'Property-based testing',
            'Mutation testing',
            'Contract testing between services',
            'Test doubles: fakes vs mocks vs stubs',
            'Characterization tests for legacy code',
            'Making flaky tests deterministic',
        ],
        'performance' => [
            'N+1 detection and eager-loading strategies',
            'Cache invalidation strategies and stampedes',
            'Profiling PHP with flame graphs',
            'Connection pooling and its limits',
            'Back-pressure in queues and workers',
            'Tail latency (p99) and why averages lie',
        ],
        'security' => [
            'CSRF vs CORS: what each actually protects',
            'Timing attacks and constant-time comparison',
            'Mass assignment and safe input mapping',
            'Secret rotation without downtime',
            'SSRF and validating outbound URLs',
            'Signed URLs and expiring tokens',
        ],
        'php-internals' => [
            'OPcache and preloading',
            'PHP references and copy-on-write',
            'Generators and lazy iteration for memory',
            'JIT in PHP: when it helps and when it does not',
            'Fibers and cooperative concurrency',
            'Readonly classes and immutability in PHP',
        ],
        'laravel-internals' => [
            'Laravel container contextual binding',
            'How Laravel middleware pipelines are built',
            'Eloquent model events vs observers vs dispatching events',
            'Queue job uniqueness, overlapping and rate limiting',
            'Laravel request lifecycle from index.php to response',
            'Service providers: register vs boot, and deferred providers',
        ],
        'devops' => [
            'Blue-green vs canary deployments',
            'Multi-stage Docker builds and layer caching',
            'Health checks: liveness vs readiness',
            'Twelve-factor configuration',
            'Structured logging and correlation IDs',
            'Running cron jobs safely in containers',
        ],
        'design-patterns' => [
            'Pipeline pattern',
            'Strategy vs state pattern',
            'Decorator for cross-cutting concerns',
            'Specification pattern for business rules',
            'Null object pattern',
            'Value objects and primitive obsession',
        ],
        'ai-engineering' => [
            'Prompt injection defenses',
            'Structured outputs and schema validation for LLMs',
            'Retrieval-augmented generation pitfalls',
            'Evaluating LLM features with evals',
            'Prompt caching to cut cost and latency',
            'Agent tool design: small, explicit, verifiable tools',
        ],
    ];

    public function run(): void
    {
        foreach (self::TOPICS as $category => $titles) {
            foreach ($titles as $title) {
                LearningTopic::query()->firstOrCreate(['title' => $title], ['category' => $category]);
            }
        }
    }
}
