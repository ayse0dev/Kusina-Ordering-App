<x-admin-layout title="Vouchers">
    <h1 class="text-xl font-bold mb-4">Vouchers &amp; discount codes</h1>

    <div class="grid gap-5 lg:grid-cols-2 mb-8">
        <form method="POST" action="{{ route('admin.vouchers.discount') }}" class="bg-white border border-cream-line shadow-card p-5 space-y-3">
            @csrf
            <h2 class="font-display text-xs font-bold uppercase tracking-[0.2em]">New discount code</h2>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="text-xs font-bold">Code</label><input name="code" required maxlength="24" placeholder="KAINNA10" value="{{ old('code') }}" class="mt-1 w-full uppercase"></div>
                <div><label class="text-xs font-bold">Type</label>
                    <select name="type" class="mt-1 w-full"><option value="percent">Percent off</option><option value="fixed">Fixed $ off</option></select></div>
                <div><label class="text-xs font-bold">Value</label><input name="value" type="number" step="0.01" min="0.01" required value="{{ old('value') }}" class="mt-1 w-full"></div>
                <div><label class="text-xs font-bold">Min. spend ($)</label><input name="min_spend" type="number" step="0.01" min="0" value="{{ old('min_spend') }}" class="mt-1 w-full"></div>
                <div><label class="text-xs font-bold">Max uses</label><input name="max_uses" type="number" min="1" value="{{ old('max_uses') }}" class="mt-1 w-full"></div>
                <div><label class="text-xs font-bold">Expires</label><input name="expires_at" type="date" value="{{ old('expires_at') }}" class="mt-1 w-full"></div>
            </div>
            <div><label class="text-xs font-bold">Note (internal)</label><input name="description" maxlength="150" value="{{ old('description') }}" class="mt-1 w-full"></div>
            <button class="btn-primary">Create code</button>
        </form>

        <form method="POST" action="{{ route('admin.vouchers.gift') }}" class="bg-white border border-cream-line shadow-card p-5 space-y-3 h-fit">
            @csrf
            <h2 class="font-display text-xs font-bold uppercase tracking-[0.2em]">Issue a gift voucher</h2>
            <p class="text-xs text-navy-500">For in-store sales or goodwill. Online purchases appear below automatically once paid.</p>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="text-xs font-bold">Amount ($)</label><input name="amount" type="number" step="0.01" min="1" required class="mt-1 w-full"></div>
                <div><label class="text-xs font-bold">Recipient</label><input name="recipient_name" maxlength="100" class="mt-1 w-full"></div>
            </div>
            <div><label class="text-xs font-bold">Note (internal)</label><input name="description" maxlength="150" class="mt-1 w-full"></div>
            <button class="btn-primary">Issue voucher</button>
        </form>
    </div>

    <h2 class="font-display text-xs font-bold uppercase tracking-[0.2em] mb-2">Discount codes</h2>
    <div class="bg-white border border-cream-line shadow-card overflow-x-auto mb-8">
        <table class="w-full text-sm">
            <thead class="text-left text-navy-500 bg-cream"><tr><th class="p-3">Code</th><th class="p-3">Offer</th><th class="p-3">Min spend</th><th class="p-3">Used</th><th class="p-3">Expires</th><th class="p-3">Status</th><th class="p-3"></th></tr></thead>
            <tbody class="divide-y divide-cream-line">
                @forelse ($discounts as $v)
                    <tr>
                        <td class="p-3 font-bold tracking-wider">{{ $v->code }}<br><span class="text-xs font-normal text-navy-400">{{ $v->description }}</span></td>
                        <td class="p-3">{{ $v->label() }}</td>
                        <td class="p-3">{{ $v->min_spend ? '$'.number_format($v->min_spend, 2) : '—' }}</td>
                        <td class="p-3">{{ $v->uses }}{{ $v->max_uses ? ' / '.$v->max_uses : '' }}</td>
                        <td class="p-3">{{ $v->expires_at?->format('M j, Y') ?? 'Never' }}</td>
                        <td class="p-3"><span class="px-2 py-0.5 text-xs {{ $v->isUsable() ? 'bg-emerald-100 text-emerald-700' : 'bg-navy-100 text-navy-600' }}">{{ $v->isUsable() ? 'Active' : ($v->status === 'void' ? 'Disabled' : 'Expired') }}</span></td>
                        <td class="p-3"><form method="POST" action="{{ route('admin.vouchers.toggle', $v) }}">@csrf<button class="text-xs underline text-navy-600 hover:text-navy-900">{{ $v->status === 'active' ? 'Disable' : 'Enable' }}</button></form></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="p-6 text-center text-navy-400">No discount codes yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <h2 class="font-display text-xs font-bold uppercase tracking-[0.2em] mb-2">Gift vouchers</h2>
    <div class="bg-white border border-cream-line shadow-card overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="text-left text-navy-500 bg-cream"><tr><th class="p-3">Code</th><th class="p-3">From → For</th><th class="p-3">Value</th><th class="p-3">Balance</th><th class="p-3">Issued</th><th class="p-3">Status</th><th class="p-3"></th></tr></thead>
            <tbody class="divide-y divide-cream-line">
                @forelse ($gifts as $v)
                    <tr>
                        <td class="p-3 font-bold tracking-wider">{{ $v->code }}</td>
                        <td class="p-3">{{ $v->buyer_name ?: '—' }} → {{ $v->recipient_name ?: '—' }}<br><span class="text-xs text-navy-400">{{ $v->description }}</span></td>
                        <td class="p-3">${{ number_format($v->value, 2) }}</td>
                        <td class="p-3 font-bold">${{ number_format($v->balance, 2) }}</td>
                        <td class="p-3">{{ ($v->paid_at ?? $v->created_at)->format('M j, Y') }}</td>
                        <td class="p-3"><span class="px-2 py-0.5 text-xs {{ $v->status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-800' }}">{{ ucfirst($v->status) }}</span></td>
                        <td class="p-3">@if ($v->status !== 'pending')<form method="POST" action="{{ route('admin.vouchers.toggle', $v) }}">@csrf<button class="text-xs underline text-navy-600 hover:text-navy-900">{{ $v->status === 'active' ? 'Void' : 'Re-activate' }}</button></form>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="p-6 text-center text-navy-400">No gift vouchers yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin-layout>
