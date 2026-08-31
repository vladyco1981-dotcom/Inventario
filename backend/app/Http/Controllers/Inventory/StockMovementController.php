<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\StockMovement;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockMovementController extends Controller
{
    public function index(Request $request)
    {
        $query = StockMovement::with(['product', 'warehouse', 'user', 'supplier'])
            ->orderBy('created_at', 'desc');

        // Filtros opcionales
        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->has('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $movements = $query->paginate($request->per_page ?? 20);

        return response()->json($movements);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => ['required', 'in:entry,exit,adjustment,transfer'],
            'product_id' => ['required', 'exists:products,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string'],
            'reference_number' => ['nullable', 'string'],
            'expiration_date' => ['nullable', 'date'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
        ]);

        $product = Product::findOrFail($validated['product_id']);
        $warehouse = Warehouse::findOrFail($validated['warehouse_id']);

        DB::beginTransaction();
        try {
            // Obtener cantidad actual
            $pivot = $product->warehouses()->where('warehouse_id', $warehouse->id)->first();
            $previousQuantity = $pivot ? $pivot->pivot->quantity : 0;

            // Calcular nueva cantidad
            $newQuantity = $validated['type'] === 'entry' 
                ? $previousQuantity + $validated['quantity']
                : $previousQuantity - $validated['quantity'];

            if ($newQuantity < 0) {
                throw new \Exception('Stock insuficiente para realizar esta salida');
            }

            // Crear movimiento
            $movement = StockMovement::create([
                'type' => $validated['type'],
                'product_id' => $validated['product_id'],
                'warehouse_id' => $validated['warehouse_id'],
                'quantity' => $validated['quantity'],
                'previous_quantity' => $previousQuantity,
                'new_quantity' => $newQuantity,
                'user_id' => auth()->id(),
                'supplier_id' => $validated['supplier_id'] ?? null,
                'reason' => $validated['reason'] ?? null,
                'reference_number' => $validated['reference_number'] ?? null,
                'expiration_date' => $validated['expiration_date'] ?? null,
            ]);

            // Actualizar stock en la tabla pivote
            if ($pivot) {
                $pivot->update([
                    'quantity' => $newQuantity,
                    'expiration_date' => $validated['expiration_date'] ?? $pivot->expiration_date,
                ]);
            } else {
                $product->warehouses()->attach($warehouse->id, [
                    'quantity' => $newQuantity,
                    'expiration_date' => $validated['expiration_date'] ?? null,
                ]);
            }

            DB::commit();

            return response()->json($movement->load(['product', 'warehouse', 'user']), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function show(StockMovement $stockMovement)
    {
        return response()->json($stockMovement->load(['product', 'warehouse', 'user', 'supplier']));
    }

    public function adjustStock(Request $request)
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'quantity' => ['required', 'integer'],
            'reason' => ['required', 'string'],
        ]);

        $validated['type'] = 'adjustment';
        
        return $this->store(new Request($validated));
    }
}
