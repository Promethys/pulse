<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;

class TagTableSeeder extends Seeder
{
    /** Idempotent: only creates missing tags, never changes existing rows. */
    private const TAGS = [
        'ai' => 'AI',
        'laravel' => 'Laravel',
        'php' => 'PHP',
        'mcp' => 'MCP',
        'devtools' => 'Dev tools',
        'engineering' => 'Engineering',
    ];

    public function run(): void
    {
        foreach (self::TAGS as $slug => $name) {
            Tag::query()->firstOrCreate(['slug' => $slug], ['name' => $name]);
        }
    }
}
