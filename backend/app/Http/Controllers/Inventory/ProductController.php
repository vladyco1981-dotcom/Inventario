<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with(['category', 'supplier', 'warehouses']);

        // Filtros opcionales
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        if ($request->has('low_stock')) {
            $query->havingRaw('SUM(product_warehouse.quantity) <= products.min_stock');
        }

        if ($request->has('expiring_soon')) {
            $days = $request->expiring_soon ?? 30;
            $query->where('track_expiration', true)
                ->whereNotNull('expiration_date')
                ->where('expiration_date', '<=', now()->addDays($days));
        }

        $products = $query->orderBy('name')->paginate($request->per_page ?? 15);

        return response()->json($products);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'unique:products,sku'],
            'barcode' => ['nullable', 'string', 'unique:products,barcode'],
            'category_id' => ['required', 'exists:categories,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'description' => ['nullable', 'string'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'min_stock' => ['required', 'integer', 'min:0'],
            'expiration_date' => ['nullable', 'date'],
            'track_expiration' => ['boolean'],
            'image_path' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'warehouses' => ['nullable', 'array'],
            'warehouses.*.warehouse_id' => ['required_with:warehouses', 'exists:warehouses,id'],
            'warehouses.*.quantity' => ['required_with:warehouses', 'integer', 'min:0'],
            'warehouses.*.expiration_date' => ['nullable', 'date'],
        ]);

        // Manejo de imagen si se sube
        if ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')->store('products', 'public');
        }

        $product = Product::create($validated);

        // Asignar stock inicial en almacenes
        if (isset($validated['warehouses'])) {
            foreach ($validated['warehouses'] as $warehouse) {
                $product->warehouses()->attach($warehouse['warehouse_id'], [
                    'quantity' => $warehouse['quantity'],
                    'expiration_date' => $warehouse['expiration_date'] ?? null,
                ]);
            }
        }

        return response()->json($product->load(['category', 'supplier', 'warehouses']), 201);
    }

    public function show(Product $product)
    {
        return response()->json($product->load(['category', 'supplier', 'warehouses', 'stockMovements']));
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'unique:products,sku,' . $product->id],
            'barcode' => ['nullable', 'string', 'unique:products,barcode,' . $product->id],
            'category_id' => ['required', 'exists:categories,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'description' => ['nullable', 'string'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'min_stock' => ['required', 'integer', 'min:0'],
            'expiration_date' => ['nullable', 'date'],
            'track_expiration' => ['boolean'],
            'image_path' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        // Manejo de imagen si se sube
        if ($request->hasFile('image')) {
            // Eliminar imagen anterior si existe
            if ($product->image_path) {
                Storage::disk('public')->delete($product->image_path);
            }
            $validated['image_path'] = $request->file('image')->store('products', 'public');
        }

        $product->update($validated);

        return response()->json($product->load(['category', 'supplier', 'warehouses']));
    }

    public function destroy(Product $product)
    {
        // Eliminar imagen si existe
        if ($product->image_path) {
            Storage::disk('public')->delete($product->image_path);
        }

        $product->delete();

        return response()->json(null, 204);
    }

    public function lowStock()
    {
        $products = Product::with(['category', 'supplier'])
            ->get()
            ->filter(fn($p) => $p->isLowStock())
            ->values();

        return response()->json($products);
    }

    public function expiringSoon($days = 30)
    {
        $products = Product::with(['category', 'supplier'])
            ->where('track_expiration', true)
            ->whereNotNull('expiration_date')
            ->where('expiration_date', '<=', now()->addDays($days))
            ->orderBy('expiration_date')
            ->get();

        return response()->json($products);
    }
}
