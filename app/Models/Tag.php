<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['slug', 'name'])]
class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use HasFactory;

    public static function displayName(string $slug): string
    {
        $name = static::query()->where('slug', $slug)->value('name');

        return is_string($name) ? $name : Str::headline($slug);
    }
}
