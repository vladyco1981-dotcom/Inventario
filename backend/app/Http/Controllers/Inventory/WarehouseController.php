<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    public function index(Request $request)
    {
        $query = Warehouse::orderBy('name');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $warehouses = $query->paginate($request->per_page ?? 15);

        return response()->json($warehouses);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'unique:warehouses,code'],
            'address' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $warehouse = Warehouse::create($validated);

        return response()->json($warehouse, 201);
    }

    public function show(Warehouse $warehouse)
    {
        return response()->json($warehouse->load(['products']));
    }

    public function update(Request $request, Warehouse $warehouse)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'unique:warehouses,code,' . $warehouse->id],
            'address' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $warehouse->update($validated);

        return response()->json($warehouse);
    }

    public function destroy(Warehouse $warehouse)
    {
        if ($warehouse->stockMovements()->count() > 0) {
            return response()->json(['message' => 'No se puede eliminar un almacén con movimientos de stock'], 422);
        }

        $warehouse->delete();

        return response()->json(null, 204);
    }
}
