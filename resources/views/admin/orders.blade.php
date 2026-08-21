<x-admin.shell active="orders" eyebrow="Commerce" title="Orders & Fulfillment">
<div class="mx-auto max-w-7xl space-y-6 p-5 lg:p-8">
                <section class="rounded-3xl border border-neutral-800 bg-neutral-900 p-5">
                    <div class="flex items-center gap-2"><span class="rounded-full border border-blue-500/30 bg-blue-500/10 px-2.5 py-1 text-[10px] font-bold uppercase text-blue-300">Fulfillment Operations</span><span class="text-xs text-neutral-500">Live Order Streams</span></div>
                    <h2 class="mt-2 text-lg font-bold tracking-tight text-white sm:text-xl">Order Fulfillment &amp; Transactions</h2>
                    <p class="mt-1 text-xs text-neutral-400">Process simulated payments, dispatch orders with real carrier snapshots, and inspect immutable customer, item and delivery records.</p>
                </section>

                @if($errors->has('status') || $errors->has('shipment'))
                    <p class="rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-xs text-rose-300">{{ $errors->first('status') ?: $errors->first('shipment') }}</p>
                @endif

                <form method="GET" class="flex flex-col gap-3 rounded-2xl border border-neutral-800 bg-neutral-900 p-4 md:flex-row md:items-center md:justify-between">
                    <input class="w-full rounded-xl border border-neutral-700 bg-neutral-950 px-3.5 py-2 text-xs text-white placeholder:text-neutral-500 md:w-80" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search order #, customer name, email...">
                    <div class="flex gap-2">
                        <select class="rounded-xl border border-neutral-700 bg-neutral-950 px-3 py-2 text-xs text-white" name="status">
                            <option value="">All Fulfillment Statuses</option>
                            @foreach(['pending_payment' => 'Unfulfilled', 'paid' => 'Paid', 'processing' => 'Processing', 'shipped' => 'Shipped', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'] as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['status'] ?? null) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <button class="rounded-xl border border-neutral-700 px-3 py-2 text-xs font-semibold text-neutral-200">Apply</button>
                    </div>
                </form>

                <section class="overflow-hidden rounded-3xl border border-neutral-800 bg-neutral-900">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[940px] text-left text-xs">
                            <thead class="border-b border-neutral-800 bg-neutral-800/60 text-[10px] font-bold uppercase tracking-wider text-neutral-400">
                                <tr><th class="px-4 py-3.5">Order #</th><th class="px-4 py-3.5">Customer</th><th class="px-4 py-3.5">Items / SKUs</th><th class="px-4 py-3.5">Total Amount</th><th class="px-4 py-3.5">Payment</th><th class="px-4 py-3.5">Fulfillment Status</th><th class="px-4 py-3.5">Date</th><th class="px-4 py-3.5 text-right">Action</th></tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-800">
                                @forelse($orders as $order)
                                    @php($payment = $order->payments->last())
                                    <tr class="align-top hover:bg-neutral-800/40">
                                        <td class="px-4 py-4 font-mono font-bold text-white">{{ $order->number }}</td>
                                        <td class="px-4 py-4"><p class="font-semibold text-white">{{ $order->addresses->firstWhere('type', 'shipping')?->first_name ?: 'Guest customer' }}</p><p class="mt-1 font-mono text-[11px] text-neutral-500">{{ $order->email }}</p></td>
                                        <td class="px-4 py-4"><span class="font-semibold text-white">{{ $order->items->count() }} items</span><span class="text-[10px] text-neutral-500"> ({{ $order->items->sum('quantity') }} units)</span></td>
                                        <td class="px-4 py-4 font-mono font-bold text-white">${{ number_format($order->total / 100, 2) }}</td>
                                        <td class="px-4 py-4"><span @class(['text-[10px] font-bold uppercase', 'text-emerald-300' => $payment?->status === 'paid', 'text-amber-300' => $payment?->status !== 'paid'])>{{ $payment?->status ?? 'pending' }}</span></td>
                                        <td class="px-4 py-4"><span @class(['rounded-full px-2.5 py-1 text-[10px] font-bold uppercase', 'bg-emerald-500/15 text-emerald-300' => $order->status === 'delivered', 'bg-blue-500/15 text-blue-300' => in_array($order->status, ['paid', 'processing', 'shipped']), 'bg-amber-500/15 text-amber-300' => $order->status === 'pending_payment', 'bg-neutral-800 text-neutral-400' => $order->status === 'cancelled'])>{{ str_replace('_', ' ', $order->status) }}</span></td>
                                        <td class="px-4 py-4 font-mono text-[11px] text-neutral-500">{{ $order->created_at->format('M j, Y') }}</td>
                                        <td class="px-4 py-4 text-right">
                                            <details class="relative inline-block text-left">
                                                <summary class="cursor-pointer rounded-lg border border-indigo-500/30 bg-indigo-500/10 px-3 py-1.5 text-xs font-semibold text-indigo-300">Details</summary>
                                                <div class="absolute right-0 z-20 mt-2 w-[min(42rem,90vw)] rounded-2xl border border-neutral-700 bg-neutral-900 p-5 shadow-2xl">
                                                    <div class="flex items-center justify-between border-b border-neutral-800 pb-3"><div><p class="font-mono text-base font-black text-white">{{ $order->number }}</p><p class="mt-1 text-xs text-neutral-500">Placed {{ $order->created_at->format('M j, Y H:i') }}</p></div><span class="rounded-full bg-neutral-800 px-2 py-1 text-[10px] font-bold text-neutral-300">{{ str_replace('_', ' ', $order->status) }}</span></div>
                                                    <div class="mt-4 grid gap-4 md:grid-cols-3">
                                                        <section><h3 class="text-xs font-bold text-white">Order items</h3><div class="mt-2 space-y-2">@forelse($order->items as $item)<p class="rounded-lg border border-neutral-800 p-2 text-[11px] text-neutral-400"><b class="block text-white">{{ $item->title }}</b>{{ $item->variant_title }} · {{ $item->sku }} · ×{{ $item->quantity }}<span class="mt-1 block font-mono text-amber-300">${{ number_format($item->line_total / 100, 2) }}</span></p>@empty<p class="text-[11px] text-neutral-500">No line items recorded.</p>@endforelse</div></section>
                                                        <section><h3 class="text-xs font-bold text-white">Shipping address</h3>@php($address = $order->addresses->firstWhere('type', 'shipping'))<p class="mt-2 rounded-lg border border-neutral-800 p-2 text-[11px] leading-5 text-neutral-400">@if($address){{ $address->first_name }} {{ $address->last_name }}<br>{{ $address->line_1 }}@if($address->line_2)<br>{{ $address->line_2 }}@endif<br>{{ $address->postal_code }} {{ $address->city }}@if($address->state), {{ $address->state }}@endif<br>{{ $address->country }}@else No shipping snapshot recorded.@endif</p></section>
                                                        <section><h3 class="text-xs font-bold text-white">Payment summary</h3><p class="mt-2 rounded-lg border border-neutral-800 p-2 text-[11px] leading-5 text-neutral-400">Subtotal <b class="float-right text-white">${{ number_format($order->subtotal / 100, 2) }}</b><br>Shipping <b class="float-right text-white">${{ number_format($order->shipping_amount / 100, 2) }}</b><br>Tax <b class="float-right text-white">${{ number_format($order->tax_amount / 100, 2) }}</b><br><strong class="block border-t border-neutral-700 pt-1 text-amber-300">Total ${{ number_format($order->total / 100, 2) }}</strong></p></section>
                                                    </div>

                                                    @if($order->status === 'processing')
                                                        <form method="POST" action="{{ route('admin.orders.shipment', $order) }}" class="mt-4 grid gap-2 border-t border-neutral-800 pt-4 md:grid-cols-[1fr_1fr_auto]">
                                                            @csrf @method('PATCH')
                                                            <label class="text-[10px] font-bold uppercase tracking-wider text-neutral-400">Dispatch shipment<input name="carrier" value="DHL EcoGlobal Express" required class="mt-1 block w-full rounded-lg border border-neutral-700 bg-neutral-950 px-3 py-2 text-xs text-white"></label>
                                                            <label class="text-[10px] font-bold uppercase tracking-wider text-neutral-400">Tracking number<input name="tracking_number" required placeholder="TRK-DHL-..." class="mt-1 block w-full rounded-lg border border-neutral-700 bg-neutral-950 px-3 py-2 text-xs text-white"></label>
                                                            <button class="self-end rounded-lg bg-emerald-600 px-3 py-2 text-xs font-bold text-white">Dispatch &amp; add tracking</button>
                                                        </form>
                                                    @elseif($order->tracking_number)
                                                        <p class="mt-4 border-t border-neutral-800 pt-4 font-mono text-[11px] text-emerald-300">{{ $order->tracking_carrier }} · {{ $order->tracking_number }}</p>
                                                    @endif

                                                    @if($transitions[$order->id] ?? false)
                                                        @php($nextStatuses = collect($transitions[$order->id])->reject(fn ($status) => $status === 'shipped'))
                                                        @if($nextStatuses->isNotEmpty())
                                                            <form method="POST" action="{{ route('admin.orders.status', $order) }}" class="mt-4 flex gap-2 border-t border-neutral-800 pt-4">
                                                                @csrf @method('PATCH')
                                                                <select class="rounded-lg border border-neutral-700 bg-neutral-950 px-3 py-2 text-xs text-white" name="status">@foreach($nextStatuses as $status)<option value="{{ $status }}">{{ str_replace('_', ' ', $status) }}</option>@endforeach</select>
                                                                <button class="rounded-lg bg-indigo-600 px-3 py-2 text-xs font-bold text-white">Save status</button>
                                                            </form>
                                                        @endif
                                                    @endif
                                                </div>
                                            </details>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="8" class="px-4 py-12 text-center text-sm text-neutral-500">No orders match this stream.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
                {{ $orders->links() }}
            </div>
</x-admin.shell>
