<?php

namespace Tests\Feature\Reports;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsInventoryWorld;
use Tests\Concerns\FakesAuthServer;
use Tests\TestCase;

/**
 * The report dataset contract end to end, through the real auth middleware
 * with pizzasys faked. Datasets run on the test SQLite database.
 */
class ReportDatasetQueryTest extends TestCase
{
    use BuildsInventoryWorld, FakesAuthServer;

    private const ENTRIES = '/api/report-datasets/inventory.entries/query';

    private const COUNTS = '/api/report-datasets/inventory.counts/query';

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildInventoryWorld();
        $this->fakeAuthServer();

        // Store 1, 21 Oct: a first count and a later duplicate of the same day.
        $this->entry(1, '2026-10-21', 'daily', [1 => 5, 2 => 1]);
        $this->entry(1, '2026-10-21', 'daily', [1 => 7]);
        $this->entry(1, '2026-10-22', 'weekly', [1 => 10, 2 => 4]);
        $this->entry(2, '2026-10-21', 'daily', [1 => 2]);
        // Outside the period below.
        $this->entry(1, '2026-10-30', 'daily', [1 => 99]);
    }

    private function body(array $overrides = []): array
    {
        return array_replace([
            'stores' => ['03795-00001'],
            'date_from' => '2026-10-21',
            'date_to' => '2026-10-27',
            'columns' => ['store', 'date', 'type'],
            'group_by' => ['store', 'date', 'type'],
            'aggregates' => [
                ['column' => 'entries', 'fn' => 'count', 'alias' => 'entries'],
                ['column' => 'items_counted', 'fn' => 'sum', 'alias' => 'items_counted'],
            ],
            'limit' => 50,
        ], $overrides);
    }

    public function test_the_catalog_names_this_service_and_lists_both_datasets(): void
    {
        $response = $this->getJson('/api/report-datasets', $this->headers())
            ->assertOk()
            ->assertJsonPath('service', config('services.auth_server.service_name'))
            ->assertJsonPath('contract_version', 1)
            ->assertJsonPath('datasets.0.key', 'inventory.entries')
            ->assertJsonPath('datasets.0.path', '/report-datasets/inventory.entries/query')
            ->assertJsonPath('datasets.0.grain', ['store', 'date'])
            ->assertJsonPath('datasets.0.heavy', false)
            ->assertJsonPath('datasets.0.dated', true)
            ->assertJsonPath('datasets.1.key', 'inventory.counts')
            ->assertJsonPath('datasets.1.heavy', true)
            ->assertJsonPath('datasets.1.dated', true);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $response->json('contract_sha1'));
        $this->assertSame(
            ['store', 'date', 'type', 'status', 'entries', 'items_counted'],
            array_column($response->json('datasets.0.columns'), 'key'),
        );
        $this->assertSame(
            ['daily', 'weekly', 'period'],
            array_column($response->json('datasets.0.columns.2.options'), 'value'),
        );
        $this->assertSame(
            ['store', 'date', 'item', 'unit', 'quantity', 'count'],
            array_column($response->json('datasets.1.columns'), 'key'),
        );
    }

    public function test_entries_are_counted_per_store_date_and_type(): void
    {
        $response = $this->postJson(self::ENTRIES, $this->body(), $this->headers())
            ->assertOk()
            ->assertJson([
                'columns' => [
                    ['key' => 'store', 'type' => 'string'],
                    ['key' => 'date', 'type' => 'date'],
                    ['key' => 'type', 'type' => 'enum'],
                    ['key' => 'entries', 'type' => 'integer'],
                    ['key' => 'items_counted', 'type' => 'integer'],
                ],
                'next_cursor' => null,
                'truncated' => false,
                'meta' => ['row_count' => 2, 'contract_version' => 1],
            ]);

        // The duplicate shows as two entries; their items add up (2 + 1).
        $this->assertEquals([
            ['03795-00001', '2026-10-21', 'daily', 2, 3],
            ['03795-00001', '2026-10-22', 'weekly', 1, 2],
        ], $response->json('rows'));

        // pizzasys judged this exact route, with the store codes of the body.
        Http::assertSent(fn (Request $request) => $request['path'] === self::ENTRIES
            && $request['method'] === 'POST'
            && $request['store_context']['body']['stores'] === ['03795-00001']
            && $request['store_context']['path']['dataset'] === 'inventory.entries');
    }

    public function test_counts_read_the_latest_entry_of_a_store_and_date(): void
    {
        $response = $this->postJson(self::COUNTS, $this->body([
            'columns' => ['store', 'date', 'item', 'unit'],
            'group_by' => ['store', 'date', 'item', 'unit'],
            'aggregates' => [
                ['column' => 'quantity', 'fn' => 'sum', 'alias' => 'quantity'],
                ['column' => 'count', 'fn' => 'count', 'alias' => 'count'],
            ],
        ]), $this->headers())
            ->assertOk()
            ->assertJsonPath('meta.row_count', 3);

        // 21 Oct counts only the later entry (dough 7); the first one's sauce is ignored.
        $this->assertEquals([
            ['03795-00001', '2026-10-21', 'Dough', 'Box', 7, 1],
            ['03795-00001', '2026-10-22', 'Dough', 'Box', 10, 1],
            ['03795-00001', '2026-10-22', 'Sauce', 'Bag', 4, 1],
        ], $response->json('rows'));
    }

    public function test_counts_aggregate_over_a_row_filter(): void
    {
        $response = $this->postJson(self::COUNTS, $this->body([
            'stores' => [],
            'columns' => ['item'],
            'group_by' => ['item'],
            'filters' => [['column' => 'item', 'op' => 'eq', 'value' => 'Dough']],
            'aggregates' => [
                ['column' => 'quantity', 'fn' => 'max', 'alias' => 'most'],
                ['column' => 'quantity', 'fn' => 'sum', 'alias' => 'total'],
            ],
        ]), $this->headers())
            ->assertOk();

        // Latest entries only: 7 (store 1, 21 Oct), 10 (22 Oct), 2 (store 2).
        $this->assertEquals([['Dough', 10, 19]], $response->json('rows'));
    }

    public function test_empty_stores_means_no_store_filter(): void
    {
        $this->postJson(self::ENTRIES, $this->body([
            'stores' => [],
            'columns' => ['store'],
            'group_by' => ['store'],
            'aggregates' => [['column' => 'entries', 'fn' => 'count', 'alias' => 'entries']],
        ]), $this->headers())
            ->assertOk()
            ->assertJsonPath('rows', [['03795-00001', 3], ['03795-00002', 1]]);
    }

    public function test_a_store_code_this_service_does_not_know_matches_nothing(): void
    {
        $this->postJson(self::ENTRIES, $this->body(['stores' => ['03795-00099']]), $this->headers())
            ->assertOk()
            ->assertJsonPath('rows', []);
    }

    public function test_unknown_or_unofferable_columns_are_a_422(): void
    {
        $this->postJson(self::ENTRIES, $this->body(['columns' => ['store', 'submitted_by']]), $this->headers())
            ->assertUnprocessable()
            ->assertJsonPath('errors.columns', ['Unknown column submitted_by.']);

        // Measures cannot be grouped, and only the listed functions are offered.
        $this->postJson(self::COUNTS, $this->body(['columns' => ['quantity'], 'group_by' => []]), $this->headers())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('columns');

        $this->postJson(self::ENTRIES, $this->body(['aggregates' => [['column' => 'entries', 'fn' => 'sum']]]), $this->headers())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('aggregates.0.fn');
    }

    public function test_the_period_is_not_capped(): void
    {
        $this->postJson(self::ENTRIES, $this->body(['date_from' => '2020-01-01', 'date_to' => '2026-10-27']), $this->headers())
            ->assertOk();

        $this->postJson(self::COUNTS, $this->body([
            'date_from' => '2020-01-01',
            'date_to' => '2026-10-27',
            'columns' => ['store', 'date'],
            'group_by' => ['store', 'date'],
            'aggregates' => [],
        ]), $this->headers())
            ->assertOk();
    }

    public function test_a_store_pizzasys_refuses_is_a_403(): void
    {
        $this->tokenAuthorized = false;

        $this->postJson(self::ENTRIES, $this->body(['stores' => ['03795-00002']]), $this->headers())->assertForbidden();
        $this->getJson('/api/report-datasets', $this->headers())->assertForbidden();
    }

    public function test_the_routes_need_a_token(): void
    {
        $this->postJson(self::ENTRIES, $this->body())->assertUnauthorized();
        $this->getJson('/api/report-datasets')->assertUnauthorized();
    }

    public function test_stream_mode_sends_ndjson_columns_rows_and_the_done_line(): void
    {
        $page = $this->postJson(self::ENTRIES, $this->body(), $this->headers())->assertOk()->json();

        $response = $this->postJson(self::ENTRIES, $this->body(['stream' => true, 'limit' => null]), $this->headers())->assertOk();

        $this->assertStringStartsWith('application/x-ndjson', $response->headers->get('Content-Type'));
        $lines = array_map(fn (string $line) => json_decode($line, true), explode("\n", rtrim($response->streamedContent(), "\n")));
        $rows = array_slice($lines, 1, -1);

        $this->assertSame(['columns' => $page['columns']], $lines[0]);
        $this->assertNotEmpty($rows);
        $this->assertEqualsCanonicalizing($page['rows'], $rows);
        $this->assertSame(['done' => true, 'row_count' => count($rows)], end($lines));
    }

    public function test_a_stream_query_with_a_limit_is_a_422(): void
    {
        $this->postJson(self::ENTRIES, $this->body(['stream' => true, 'limit' => 10]), $this->headers())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('limit');
    }
}
