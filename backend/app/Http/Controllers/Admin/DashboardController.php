<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Order;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function getStats()
    {
        $stats = [
            'total_products' => Product::count(),
            'low_stock_products' => Product::get()->filter(fn($p) => $p->isLowStock())->count(),
            'expiring_soon' => Product::where('track_expiration', true)
                ->whereNotNull('expiration_date')
                ->where('expiration_date', '<=', now()->addDays(30))
                ->count(),
            'total_customers' => \App\Models\Customer::count(),
            'total_suppliers' => \App\Models\Supplier::count(),
            'total_warehouses' => \App\Models\Warehouse::count(),
        ];

        // Ventas del mes
        $stats['monthly_sales'] = Order::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('total_amount');

        // Órdenes por estado
        $stats['orders_by_status'] = Order::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return response()->json($stats);
    }

    public function getRecentActivity()
    {
        $recentMovements = StockMovement::with(['product', 'warehouse', 'user'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $recentOrders = Order::with(['customer', 'items'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'recent_movements' => $recentMovements,
            'recent_orders' => $recentOrders,
        ]);
    }

    public function getSalesChart(Request $request)
    {
        $period = $request->get('period', '30'); // días
        
        $sales = Order::where('status', 'completed')
            ->whereDate('created_at', '>=', now()->subDays($period))
            ->selectRaw('DATE(created_at) as date, SUM(total_amount) as total, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json($sales);
    }

    public function getTopProducts(Request $request)
    {
        $limit = $request->get('limit', 10);
        
        $topProducts = DB::table('order_items')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.status', 'completed')
            ->select('products.id', 'products.name', DB::raw('SUM(order_items.quantity) as total_sold'))
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total_sold')
            ->limit($limit)
            ->get();

        return response()->json($topProducts);
    }

    public function getLowStockAlerts()
    {
        $alerts = Product::with(['category', 'supplier'])
            ->get()
            ->filter(fn($p) => $p->isLowStock())
            ->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'current_stock' => $p->total_stock,
                'min_stock' => $p->min_stock,
                'category' => $p->category?->name,
            ])
            ->values();

        return response()->json($alerts);
    }

    public function getExpiringAlerts()
    {
        $days = 30;
        $alerts = Product::with(['category', 'supplier'])
            ->where('track_expiration', true)
            ->whereNotNull('expiration_date')
            ->where('expiration_date', '<=', now()->addDays($days))
            ->orderBy('expiration_date')
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'expiration_date' => $p->expiration_date,
                'days_to_expire' => now()->diffInDays($p->expiration_date, false),
                'category' => $p->category?->name,
            ]);

        return response()->json($alerts);
    }
}
