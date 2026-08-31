<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = Customer::orderBy('name');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $customers = $query->paginate($request->per_page ?? 15);

        return response()->json($customers);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:customers,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'tax_id' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $customer = Customer::create($validated);

        return response()->json($customer, 201);
    }

    public function show(Customer $customer)
    {
        return response()->json($customer->load(['orders']));
    }

    public function update(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:customers,email,' . $customer->id],
            'phone' => ['nullable', 'string', 'max:20'],
            'tax_id' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $customer->update($validated);

        return response()->json($customer);
    }

    public function destroy(Customer $customer)
    {
        if ($customer->orders()->count() > 0) {
            return response()->json(['message' => 'No se puede eliminar un cliente con pedidos asociados'], 422);
        }

        $customer->delete();

        return response()->json(null, 204);
    }
}
