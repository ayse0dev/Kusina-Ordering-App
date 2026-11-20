<x-admin-layout title="Order history">
    <h1 class="text-xl font-semibold mb-4">Order history</h1>
    <div class="bg-white rounded-2xl border border-cream-line shadow-card overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="text-left text-stone-500 bg-stone-50">
                <tr><th class="p-3">Order</th><th class="p-3">Placed</th><th class="p-3">Customer</th><th class="p-3">Type</th><th class="p-3">Status</th><th class="p-3">Payment</th><th class="p-3 text-right">Total</th></tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($orders as $o)
                    <tr>
                        <td class="p-3 font-medium"><a class="text-brand-700 hover:underline" href="{{ route('track', $o->code) }}" target="_blank">{{ $o->code }}</a></td>
                        <td class="p-3 whitespace-nowrap">{{ $o->created_at->format('M j, g:i A') }}</td>
                        <td class="p-3">{{ $o->customer_name }}<br><span class="text-xs text-stone-500">{{ $o->customer_phone }}</span></td>
                        <td class="p-3">{{ $o->order_type->label() }}</td>
                        <td class="p-3">{{ $o->status->label() }}</td>
                        <td class="p-3">{{ ucfirst($o->payment_status) }} · {{ $o->payment_method }}</td>
                        <td class="p-3 text-right">${{ number_format($o->total, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="p-6 text-center text-stone-500">No orders yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $orders->links() }}</div>
</x-admin-layout>
