<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SourceType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CatalogSourcesMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_enabled_sources_become_defaults_except_newsletters(): void
    {
        $migration = require database_path('migrations/2026_10_24_000003_add_catalog_columns_to_sources_table.php');
        $migration->down();

        foreach (['enabled' => true, 'disabled' => false, 'newsletters' => true] as $slug => $enabled) {
            DB::table('sources')->insert([
                'slug' => $slug,
                'name' => $slug,
                'type' => SourceType::Feed->value,
                'tag' => 'devtools',
                'max_items' => 10,
                'position' => 1,
                'enabled' => $enabled,
            ]);
        }

        $migration->up();

        $this->assertDatabaseHas('sources', ['slug' => 'enabled', 'is_default' => true]);
        $this->assertDatabaseHas('sources', ['slug' => 'disabled', 'is_default' => false]);
        $this->assertDatabaseHas('sources', ['slug' => 'newsletters', 'is_default' => false]);
    }
}
