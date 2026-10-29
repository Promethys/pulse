<?php

declare(strict_types=1);

namespace App\Reading;

use App\Enums\PostStatus;
use App\Models\LearningPost;
use App\Models\PostItem;

final readonly class Day
{
    /**
     * @param  array{status: PostStatus, sections: list<array{slug: string, name: string, items: list<PostItem>}>, item_count: int}|null  $news
     * @param  array{status: PostStatus, tip: ?LearningPost, topic: ?LearningPost}|null  $learning
     */
    public function __construct(
        public string $date,
        public ?array $news,
        public ?array $learning,
        public ?string $previous_date,
        public ?string $next_date,
    ) {}
}
