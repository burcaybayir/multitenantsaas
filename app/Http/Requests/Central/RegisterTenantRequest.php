<?php

declare(strict_types=1);

namespace App\Http\Requests\Central;

use App\Data\TenantAdmin;
use App\Data\TenantRegistration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Public sign-up; abuse is handled by rate limiting.
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'subdomain' => strtolower(trim((string) $this->input('subdomain'))),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:120'],
            'subdomain' => [
                'required',
                'string',
                'min:3',
                'max:63',
                // A single DNS label: no dots, no leading/trailing hyphen.
                'regex:/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/',
                Rule::notIn(config('installhub.reserved_subdomains')),
                Rule::unique('domains', 'domain'),
            ],
            'admin_name' => ['required', 'string', 'max:120'],
            'admin_email' => ['required', 'string', 'email:rfc', 'max:255'],
            'admin_password' => ['required', 'string', Password::min(12)->letters()->numbers(), 'confirmed'],
        ];
    }

    public function toRegistration(): TenantRegistration
    {
        return new TenantRegistration(
            companyName: $this->string('company_name')->toString(),
            subdomain: $this->string('subdomain')->toString(),
            admin: new TenantAdmin(
                name: $this->string('admin_name')->toString(),
                email: $this->string('admin_email')->lower()->toString(),
                // Hash here, at the edge: the plaintext is never queued.
                passwordHash: Hash::make($this->string('admin_password')->toString()),
            ),
        );
    }
}
