<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AdminExpenseSortingTest extends TestCase
{
    use RefreshDatabase;

    public function test_date_heading_changes_description_sorting_and_preserves_search_and_filters(): void
    {
        [$early, $middle, $late] = $this->expenses();
        Expense::factory()->create(['supplier' => 'Other supplier', 'description' => 'Unrelated expense']);
        $filters = ['search' => 'binary', 'list_total_amount_min' => 50, 'per_page' => 10, 'page' => 1];

        foreach ([route('admin.expense.index'), route('admin.supplier.show', $early->supplier_id)] as $url) {
            $response = $this->get($url.'?'.http_build_query($filters))->assertOk();
            $this->assertSame([$late->id, $middle->id, $early->id], $response->viewData('expenses')->pluck('id')->all());

            $response = $this->get($url.'?'.http_build_query($filters + ['list_sort' => 'description', 'list_direction' => 'asc']))->assertOk();
            $this->assertSame([$middle->id, $late->id, $early->id], $response->viewData('expenses')->pluck('id')->all());

            foreach (['asc' => [$early->id, $middle->id, $late->id], 'desc' => [$late->id, $middle->id, $early->id]] as $direction => $expected) {
                $link = $this->sortLink($response, 'Date');
                parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY), $query);
                $this->assertSame('paid_on', $query['list_sort']);
                $this->assertSame($direction, $query['list_direction']);
                $this->assertSame('binary', $query['search']);
                $this->assertSame('50', $query['list_total_amount_min']);
                $this->assertSame('10', $query['per_page']);
                $this->assertArrayNotHasKey('page', $query);

                $response = $this->get($link->getAttribute('href'))->assertOk();
                $this->assertSame($expected, $response->viewData('expenses')->pluck('id')->all());
                $heading = $this->sortLink($response, 'Date')->parentNode;
                $this->assertSame($direction === 'asc' ? 'ascending' : 'descending', $heading->getAttribute('aria-sort'));
                $icon = $heading->getElementsByTagName('i')->item(0);
                $this->assertStringContainsString($direction === 'asc' ? 'fa-arrow-up' : 'fa-arrow-down', $icon->getAttribute('class'));
                $this->assertSame('none', $this->sortLink($response, 'Description')->parentNode->getAttribute('aria-sort'));
            }
        }
    }

    public function test_expense_headers_sort_their_displayed_values_in_both_directions(): void
    {
        [$early, $middle, $late] = $this->expenses();

        foreach ([route('admin.expense.index'), route('admin.supplier.show', $early->supplier_id)] as $url) {
            foreach (['Description' => [$middle->id, $late->id, $early->id], 'Invoice ID' => [$late->id, $early->id, $middle->id], 'Amount' => [$middle->id, $late->id, $early->id]] as $label => $ids) {
                $response = $this->get($url.'?search=binary')->assertOk();
                foreach ([$ids, array_reverse($ids)] as $expected) {
                    $response = $this->get($this->sortLink($response, $label)->getAttribute('href'))->assertOk();
                    $this->assertSame($expected, $response->viewData('expenses')->pluck('id')->all());
                }
            }
        }
    }

    private function expenses(): array
    {
        $admin = User::factory()->create();
        UserGroup::create(['user_id' => $admin->id, 'slug' => 'admin']);
        $this->actingAs($admin);

        return [
            Expense::factory()->create(['supplier' => 'Binary Supplies', 'description' => 'Zulu item', 'paid_on' => '2026-06-01', 'invoice_id' => 'B-002', 'total_amount' => 300]),
            Expense::factory()->create(['supplier' => 'Binary Supplies', 'description' => 'Alpha item', 'paid_on' => '2026-06-02', 'invoice_id' => 'B-003', 'total_amount' => 100]),
            Expense::factory()->create(['supplier' => 'Binary Supplies', 'description' => 'Middle item', 'paid_on' => '2026-06-03', 'invoice_id' => 'B-001', 'total_amount' => 200]),
        ];
    }

    private function sortLink(TestResponse $response, string $label): \DOMElement
    {
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $link = (new \DOMXPath($document))->query('//th/a[starts-with(@aria-label, "Sort by '.$label.',")]')->item(0);
        $this->assertInstanceOf(\DOMElement::class, $link, $label.' should have a sort link');

        return $link;
    }
}
