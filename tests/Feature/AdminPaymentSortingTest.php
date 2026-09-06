<?php

namespace Tests\Feature;

use App\Models\InvoicePaymentAllocation;
use App\Models\Payment;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPaymentSortingTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_headers_sort_displayed_financial_values_in_both_directions(): void
    {
        $admin = User::factory()->create();
        UserGroup::create(['user_id' => $admin->id, 'slug' => 'admin']);
        $this->actingAs($admin);
        $first = Payment::factory()->create(['total_amount' => 100, 'payment_method' => 'cash']);
        $second = Payment::factory()->create(['total_amount' => 200, 'payment_method' => 'bank_transfer', 'cleared_at' => null]);
        InvoicePaymentAllocation::factory()->create(['payment_id' => $first->id, 'allocated_amount' => 90]);
        InvoicePaymentAllocation::factory()->create(['payment_id' => $second->id, 'allocated_amount' => 20]);
        Payment::factory()->create(['kind' => Payment::KIND_REFUND, 'refund_of_payment_id' => $second->id, 'total_amount' => 175]);
        foreach (['total_amount' => [$first->id, $second->id], 'allocated' => [$second->id, $first->id], 'unallocated' => [$second->id, $first->id], 'clearance' => [$first->id, $second->id]] as $field => $ids) {
            foreach (['asc' => $ids, 'desc' => array_reverse($ids)] as $direction => $expected) {
                $this->get(route('admin.payment.index', ['list_sort' => $field, 'list_direction' => $direction]))->assertOk()
                    ->assertViewHas('customerPayments', fn ($payments) => $payments->pluck('id')->all() === $expected)
                    ->assertSee('list_sort=allocated', false)->assertSee('list_sort=unallocated', false)
                    ->assertSee('list_sort=payment_method', false)->assertSee('list_sort=total_amount', false);
            }
        }
        $this->getJson(route('admin.payment.index', ['list_sort' => 'unallocated; DROP TABLE payments']))->assertUnprocessable();
    }
}
