<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::with(['customer', 'user', 'warehouse', 'items.product'])
            ->orderBy('created_at', 'desc');

        // Filtros opcionales
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn($q) => $q->where('name', 'like', "%{$search}%"));
            });
        }

        $orders = $query->paginate($request->per_page ?? 15);

        return response()->json($orders);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        DB::beginTransaction();
        try {
            $warehouse = Warehouse::findOrFail($validated['warehouse_id']);
            $taxRate = $validated['tax_rate'] ?? 0;
            $globalDiscount = $validated['discount_amount'] ?? 0;

            // Calcular totales
            $subtotal = 0;
            $taxAmount = 0;
            $itemsData = [];

            foreach ($validated['items'] as $item) {
                $product = Product::findOrFail($item['product_id']);
                
                // Verificar stock
                $pivot = $product->warehouses()->where('warehouse_id', $warehouse->id)->first();
                $currentStock = $pivot ? $pivot->pivot->quantity : 0;
                
                if ($currentStock < $item['quantity']) {
                    throw new \Exception("Stock insuficiente para el producto: {$product->name}");
                }

                $itemSubtotal = $item['quantity'] * $item['unit_price'];
                $itemDiscount = $item['discount_amount'] ?? 0;
                $itemTax = ($itemSubtotal - $itemDiscount) * ($taxRate / 100);
                $itemTotal = $itemSubtotal - $itemDiscount + $itemTax;

                $subtotal += $itemSubtotal;
                $taxAmount += $itemTax;

                $itemsData[] = [
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $itemSubtotal,
                    'tax_amount' => $itemTax,
                    'discount_amount' => $itemDiscount,
                    'total_amount' => $itemTotal,
                    'expiration_date' => $pivot?->pivot->expiration_date ?? null,
                ];
            }

            $totalAmount = $subtotal + $taxAmount - $globalDiscount;

            // Crear orden
            $order = Order::create([
                'customer_id' => $validated['customer_id'],
                'user_id' => auth()->id(),
                'warehouse_id' => $warehouse->id,
                'status' => 'pending',
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'discount_amount' => $globalDiscount,
                'total_amount' => $totalAmount,
                'notes' => $validated['notes'] ?? null,
            ]);

            // Guardar items
            foreach ($itemsData as $itemData) {
                $order->items()->create($itemData);
            }

            DB::commit();

            return response()->json($order->load(['customer', 'items.product']), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function show(Order $order)
    {
        return response()->json($order->load(['customer', 'user', 'warehouse', 'items.product', 'payments']));
    }

    public function updateStatus(Request $request, Order $order)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:pending,confirmed,completed,cancelled'],
        ]);

        $order->update($validated);

        // Si se completa, descontar stock
        if ($validated['status'] === 'completed') {
            DB::beginTransaction();
            try {
                foreach ($order->items as $item) {
                    $pivot = $item->product->warehouses()
                        ->where('warehouse_id', $order->warehouse_id)
                        ->first();
                    
                    if ($pivot) {
                        $newQuantity = $pivot->pivot->quantity - $item->quantity;
                        $pivot->update(['quantity' => $newQuantity]);
                        
                        // Registrar movimiento
                        \App\Models\StockMovement::create([
                            'type' => 'exit',
                            'product_id' => $item->product_id,
                            'warehouse_id' => $order->warehouse_id,
                            'quantity' => $item->quantity,
                            'previous_quantity' => $pivot->pivot->quantity + $item->quantity,
                            'new_quantity' => $newQuantity,
                            'user_id' => auth()->id(),
                            'reason' => "Venta - Orden {$order->order_number}",
                            'reference_number' => $order->order_number,
                        ]);
                    }
                }
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json(['message' => $e->getMessage()], 422);
            }
        }

        return response()->json($order->load(['customer', 'items.product']));
    }

    public function registerPayment(Request $request, Order $order)
    {
        $validated = $request->validate([
            'payment_method' => ['required', 'in:cash,credit_card,debit_card,transfer,other'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'transaction_reference' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($order->balance_due < $validated['amount']) {
            return response()->json(['message' => 'El monto excede el saldo pendiente'], 422);
        }

        $payment = $order->payments()->create([
            'payment_method' => $validated['payment_method'],
            'amount' => $validated['amount'],
            'transaction_reference' => $validated['transaction_reference'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'paid_at' => now(),
        ]);

        // Marcar como completada si se pagó todo
        if ($order->balance_due - $validated['amount'] <= 0) {
            $order->update(['status' => 'completed']);
        }

        return response()->json($payment, 201);
    }

    public function destroy(Order $order)
    {
        if ($order->status !== 'pending' && $order->status !== 'cancelled') {
            return response()->json(['message' => 'Solo se pueden eliminar órdenes pendientes o canceladas'], 422);
        }

        $order->delete();

        return response()->json(null, 204);
    }
}
