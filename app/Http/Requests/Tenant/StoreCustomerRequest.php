<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Role-based policies arrive in the security phase; for now any
        // authenticated member of the tenant may create customers.
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            // Resolved against the *tenant* connection, so uniqueness is per tenant.
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique('customers', 'email')],
            'phone' => ['nullable', 'string', 'max:32'],
        ];
    }
}
