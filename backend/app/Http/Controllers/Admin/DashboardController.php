<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;

class DashboardController extends Controller
{
    // Counts, revenue, a 7 day chart, low stock products and the latest orders.
    public function index()
    {
        return view('admin.dashboard', [
            'totalOrders' => Order::count(),
            'pendingOrders' => Order::where('status', Order::STATUS_PENDING)->count(),
            'revenue' => Order::where('status', Order::STATUS_DELIVERED)->sum('total'),
            'lowStockCount' => Product::where('stock', '<=', Product::LOW_STOCK_LIMIT)->count(),
            'chart' => $this->lastSevenDays(),
            'lowStock' => Product::where('stock', '<=', Product::LOW_STOCK_LIMIT)->orderBy('stock')->limit(10)->get(),
            'latestOrders' => Order::with('user')->latest('id')->take(5)->get(),
        ]);
    }

    // Orders per day for the last 7 days, days without orders are filled with zero.
    private function lastSevenDays(): array
    {
        $start = now()->subDays(6)->startOfDay();

        $counts = Order::where('created_at', '>=', $start)
            ->get(['created_at'])
            ->countBy(fn ($o) => $o->created_at->format('Y-m-d'));

        $labels = [];
        $values = [];
        for ($i = 0; $i < 7; $i++) {
            $day = $start->copy()->addDays($i);
            $labels[] = $day->format('d M');
            $values[] = (int) ($counts[$day->format('Y-m-d')] ?? 0);
        }

        return ['labels' => $labels, 'values' => $values];
    }
}
