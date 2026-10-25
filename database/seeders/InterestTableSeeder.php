<?php

namespace Database\Seeders;

use App\Enums\InterestCategory;
use App\Enums\InterestStatus;
use App\Models\Interest;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class InterestTableSeeder extends Seeder
{
    /** Idempotent: only creates missing interests, never changes existing rows. */
    private const INTERESTS = [
        ['Family', InterestCategory::Life, 'users', false],
        ['Health', InterestCategory::Life, 'heart-pulse', false],
        ['Relationships', InterestCategory::Life, 'heart-handshake', false],
        ['Home', InterestCategory::Life, 'house', false],
        ['Food', InterestCategory::Life, 'utensils', false],
        ['Pets', InterestCategory::Life, 'paw-print', false],
        ['Mental Health', InterestCategory::SelfImprovement, 'brain', false],
        ['Productivity', InterestCategory::SelfImprovement, 'list-checks', false],
        ['Mindfulness', InterestCategory::SelfImprovement, 'flower', false],
        ['Business', InterestCategory::Work, 'briefcase-business', false],
        ['Marketing', InterestCategory::Work, 'megaphone', false],
        ['Leadership', InterestCategory::Work, 'flag', false],
        ['Remote Work', InterestCategory::Work, 'laptop', false],
        ['Artificial Intelligence', InterestCategory::Technology, 'brain-circuit', true],
        ['Blockchain', InterestCategory::Technology, 'blocks', false],
        ['Data Science', InterestCategory::Technology, 'chart-scatter', true],
        ['Gadgets', InterestCategory::Technology, 'smartphone', false],
        ['Makers', InterestCategory::Technology, 'wrench', false],
        ['Security', InterestCategory::Technology, 'shield-check', true],
        ['Tech Companies', InterestCategory::Technology, 'building', false],
        ['Design', InterestCategory::Technology, 'pen-tool', false],
        ['Product Management', InterestCategory::Technology, 'kanban', false],
        ['Programming', InterestCategory::SoftwareDevelopment, 'code', true],
        ['Programming Languages', InterestCategory::SoftwareDevelopment, 'braces', true],
        ['DevOps', InterestCategory::SoftwareDevelopment, 'workflow', true],
        ['Operating Systems', InterestCategory::SoftwareDevelopment, 'terminal', true],
        ['Writing', InterestCategory::Media, 'pencil', false],
        ['Art', InterestCategory::Media, 'palette', false],
        ['Gaming', InterestCategory::Media, 'gamepad-2', false],
        ['Humor', InterestCategory::Media, 'party-popper', false],
        ['Movies', InterestCategory::Media, 'clapperboard', false],
        ['Music', InterestCategory::Media, 'music', false],
        ['News', InterestCategory::Media, 'newspaper', false],
        ['Photography', InterestCategory::Media, 'camera', false],
        ['Podcasts', InterestCategory::Media, 'mic', false],
        ['Television', InterestCategory::Media, 'tv', false],
        ['Economics', InterestCategory::Society, 'chart-line', false],
        ['Education', InterestCategory::Society, 'graduation-cap', false],
        ['Equality', InterestCategory::Society, 'equal', false],
        ['Finance', InterestCategory::Society, 'wallet', false],
        ['Law', InterestCategory::Society, 'scale', false],
        ['Transportation', InterestCategory::Society, 'train-front', false],
        ['Politics', InterestCategory::Society, 'landmark', false],
        ['Science', InterestCategory::Society, 'flask-conical', false],
        ['Mathematics', InterestCategory::Society, 'calculator', false],
        ['Philosophy', InterestCategory::Culture, 'lightbulb', false],
        ['Cultural Studies', InterestCategory::Culture, 'book-open', false],
        ['Fashion', InterestCategory::Culture, 'shirt', false],
        ['Beauty', InterestCategory::Culture, 'sparkles', false],
        ['Language', InterestCategory::Culture, 'languages', false],
        ['Sports', InterestCategory::Culture, 'trophy', false],
        ['Nature', InterestCategory::World, 'trees', false],
        ['Travel', InterestCategory::World, 'plane', false],
    ];

    public function run(): void
    {
        foreach (self::INTERESTS as $position => [$name, $category, $icon, $hasLearning]) {
            Interest::query()->firstOrCreate(['slug' => Str::slug($name)], [
                'name' => $name,
                'category' => $category,
                'icon' => $icon,
                'position' => $position + 1,
                'has_learning' => $hasLearning,
                'status' => InterestStatus::Draft,
            ]);
        }
    }
}
