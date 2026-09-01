<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Admin\OrderOperationsService;
use App\Services\CheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request, CheckoutService $checkout): View
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:255'], 'status' => ['nullable', 'in:pending_payment,paid,processing,shipped,delivered,cancelled']]);
        $orders = Order::query()->with(['payments', 'items', 'addresses'])
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($orders) => $orders->where('number', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest()->paginate(20)->withQueryString();

        return view('admin.orders', compact('orders', 'filters') + ['transitions' => $orders->getCollection()->mapWithKeys(fn (Order $order) => [$order->id => $checkout->availableTransitions($order)])]);
    }

    public function updateStatus(Request $request, Order $order, OrderOperationsService $operations): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:pending_payment,paid,processing,shipped,delivered,cancelled']]);
        if ($data['status'] === 'shipped') {
            return redirect()->route('admin.orders.index')->withErrors(['status' => 'Use dispatch to record a carrier and tracking number.']);
        }

        $operations->updateStatus(
            $order,
            $data['status'] === 'paid' ? 'paid' : null,
            in_array($data['status'], ['processing', 'delivered', 'cancelled'], true) ? $data['status'] : null,
            (int) $request->user()->id,
            null,
        );

        return redirect()->route('admin.orders.index');
    }

    public function dispatch(Request $request, Order $order, OrderOperationsService $operations): RedirectResponse
    {
        $data = $request->validate(['carrier' => ['required', 'string', 'max:120'], 'tracking_number' => ['required', 'string', 'max:120']]);
        $operations->dispatch($order, $data['carrier'], $data['tracking_number'], (int) $request->user()->id);

        return redirect()->route('admin.orders.index');
    }
}
