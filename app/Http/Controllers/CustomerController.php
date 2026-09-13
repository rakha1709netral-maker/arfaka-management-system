<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveCustomerRequest;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->validate(['search' => ['nullable', 'string', 'max:255']])['search'] ?? '';
        $customers = Customer::query()
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->orderBy('name')->orderBy('id')->paginate(10)->withQueryString();

        return view('super-admin.customers.index', compact('customers', 'search'));
    }

    public function create(): View
    {
        return view('super-admin.customers.form', ['customer' => new Customer]);
    }

    public function store(SaveCustomerRequest $request): RedirectResponse
    {
        $customer = Customer::query()->create($request->validated());

        return redirect()->route('super-admin.customers.edit', $customer)
            ->with('status', 'Customer berhasil dibuat.');
    }

    public function edit(Customer $customer): View
    {
        return view('super-admin.customers.form', compact('customer'));
    }

    public function update(SaveCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $customer->update($request->validated());

        return redirect()->route('super-admin.customers.edit', $customer)
            ->with('status', 'Customer berhasil diperbarui.');
    }
}
