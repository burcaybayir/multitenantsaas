<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Actions\Customers\CreateCustomer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return CustomerResource::collection(Customer::query()->latest('id')->paginate(25));
    }

    public function store(StoreCustomerRequest $request, CreateCustomer $createCustomer): JsonResponse
    {
        /** @var array{name: string, email: string, phone?: string|null} $attributes */
        $attributes = $request->validated();

        return CustomerResource::make($createCustomer->handle($attributes))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Customer $customer): CustomerResource
    {
        return CustomerResource::make($customer);
    }
}
