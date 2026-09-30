<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\Models\Customer;

final class CreateCustomer
{
    /**
     * @param  array{name: string, email: string, phone?: string|null}  $attributes
     */
    public function handle(array $attributes): Customer
    {
        return Customer::query()->create($attributes);
    }
}
