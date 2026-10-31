<?php

declare(strict_types=1);

namespace Tests\Feature\Digest;

use App\Digest\Catalog\ProposalImport;
use App\Digest\Catalog\ProposalImportParser;
use App\Models\Interest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProposalImportParserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Interest::factory()->create(['slug' => 'pets']);
    }

    public function test_it_keeps_valid_rows_and_normalizes_them(): void
    {
        $import = $this->parse([$this->row(['name' => '  Dogs  ', 'url' => ' https://dogs.test/feed ', 'reason' => str_repeat('a', 600)])]);

        $this->assertSame([], $import->rejected);
        $this->assertCount(1, $import->rows);
        $this->assertSame('pets', $import->rows[0]['interest']);
        $this->assertSame('Dogs', $import->rows[0]['name']);
        $this->assertSame('https://dogs.test/feed', $import->rows[0]['url']);
        $this->assertSame(500, mb_strlen($import->rows[0]['reason']));
    }

    public function test_it_reads_json_inside_a_code_fence_with_prose_around_it(): void
    {
        $text = "Here are the feeds:\n```json\n".json_encode([$this->row()], JSON_THROW_ON_ERROR)."\n```\nEnjoy!";

        $this->assertCount(1, (new ProposalImportParser)->parse($text)->rows);
    }

    /**
     * @param  array<string, mixed>  $change
     */
    #[DataProvider('badRows')]
    public function test_a_bad_row_is_rejected_with_its_number_and_the_rest_kept(array $change, string $message): void
    {
        $import = $this->parse([$this->row(), $this->row(['url' => 'https://other.test/feed', ...$change])]);

        $this->assertCount(1, $import->rows);
        $this->assertCount(1, $import->rejected);
        $this->assertStringContainsString('Row 2', $import->rejected[0]);
        $this->assertStringContainsString($message, $import->rejected[0]);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function badRows(): array
    {
        return [
            'unknown interest' => [['interest' => 'nope'], 'unknown interest'],
            'missing interest' => [['interest' => null], 'unknown interest'],
            'blank name' => [['name' => '  '], 'name'],
            'name not a string' => [['name' => 42], 'name'],
            'not a url' => [['url' => 'not a url'], 'url'],
            'wrong scheme' => [['url' => 'ftp://other.test/feed'], 'url'],
            'bad site url' => [['site_url' => 'javascript:alert(1)'], 'site_url'],
        ];
    }

    public function test_a_row_that_is_not_an_object_is_rejected(): void
    {
        $import = $this->parse(['just text', $this->row()]);

        $this->assertCount(1, $import->rows);
        $this->assertStringContainsString('Row 1', $import->rejected[0]);
    }

    public function test_a_url_repeated_in_the_paste_is_rejected_once(): void
    {
        $import = $this->parse([$this->row(), $this->row(['url' => 'https://DOGS.test/feed/'])]);

        $this->assertCount(1, $import->rows);
        $this->assertStringContainsString('duplicate', $import->rejected[0]);
    }

    public function test_a_missing_site_url_and_reason_are_allowed(): void
    {
        $row = $this->row();
        unset($row['site_url'], $row['reason']);

        $import = $this->parse([$row]);

        $this->assertNull($import->rows[0]['site_url']);
        $this->assertSame('', $import->rows[0]['reason']);
    }

    #[DataProvider('badDocuments')]
    public function test_input_that_is_not_a_json_array_is_rejected_as_a_whole(string $text): void
    {
        $import = (new ProposalImportParser)->parse($text);

        $this->assertSame([], $import->rows);
        $this->assertCount(1, $import->rejected);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function badDocuments(): array
    {
        return ['empty' => [''], 'prose' => ['no json here'], 'broken' => ['[{"name":'], 'object' => ['{"name":"x"}']];
    }

    public function test_it_refuses_more_than_the_row_limit(): void
    {
        $rows = array_map(fn (int $index) => $this->row(['url' => "https://site{$index}.test/feed"]), range(1, ProposalImportParser::MAX_ROWS + 1));

        $import = $this->parse($rows);

        $this->assertSame([], $import->rows);
        $this->assertStringContainsString((string) ProposalImportParser::MAX_ROWS, $import->rejected[0]);
    }

    /**
     * @param  array<int, mixed>  $rows
     */
    private function parse(array $rows): ProposalImport
    {
        return (new ProposalImportParser)->parse(json_encode($rows, JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, mixed>  $change
     * @return array<string, mixed>
     */
    private function row(array $change = []): array
    {
        return [...[
            'interest' => 'pets',
            'name' => 'Dogs',
            'url' => 'https://dogs.test/feed',
            'site_url' => 'https://dogs.test/',
            'reason' => 'Regular original posts.',
        ], ...$change];
    }
}
