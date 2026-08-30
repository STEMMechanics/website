<?php

namespace Database\Factories;

use App\Models\Organisation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organisation>
 */
class OrganisationFactory extends Factory
{
    protected $model = Organisation::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'type' => fake()->randomElement(array_keys(Organisation::TYPES)),
            'parent_id' => null,
            'billing_address' => null,
            'billing_address2' => null,
            'billing_city' => null,
            'billing_state' => null,
            'billing_postcode' => null,
            'billing_country' => null,
            'shipping_address' => null,
            'shipping_address2' => null,
            'shipping_city' => null,
            'shipping_state' => null,
            'shipping_postcode' => null,
            'shipping_country' => null,
            'invoice_email_to' => null,
            'invoice_email_cc' => null,
            'invoice_email_subject' => null,
            'invoice_email_message' => null,
            'notes' => null,
        ];
    }
}
