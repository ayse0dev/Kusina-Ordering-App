@props(['model' => 'payMethod', 'active' => 'true'])
@php
    $brand = [
        'GCash' => ['#007CFF', '#fff', 'GCash'],
        'Maya' => ['#0B0B0B', '#2EE59D', 'maya'],
        'GrabPay' => ['#00B14F', '#fff', 'GrabPay'],
    ];
@endphp
<div x-data="{ detail: 'GCash', bank: '', acct: '', holder: '', cardno: '', exp: '',
               fmtCard() { this.cardno = this.cardno.replace(/\D/g, '').slice(0, 19).replace(/(.{4})/g, '$1 ').trim(); },
               fmtExp() { let d = this.exp.replace(/\D/g, '').slice(0, 4); this.exp = d.length > 2 ? d.slice(0, 2) + '/' + d.slice(2) : d; } }"
     class="space-y-2">

    @foreach (config('kusina.payment_methods') as $key => $m)
        <label class="block cursor-pointer">
            <input type="radio" name="payment_method" value="{{ $key }}" x-model="{{ $model }}" :disabled="!({{ $active }})" class="sr-only peer">
            <div class="border border-cream-line px-3 py-2.5 peer-checked:border-sun-500 peer-checked:bg-sun-50 hover:bg-cream">
                <p class="text-sm font-bold">{{ $m['label'] }}</p>
                <p class="text-xs text-navy-500">{{ $m['hint'] }}</p>
            </div>
        </label>
    @endforeach

    <div x-show="{{ $model }} === 'ewallet'" x-cloak class="border border-sun-300 bg-sun-50/40 p-3 space-y-3">
        <div class="flex flex-wrap gap-2" role="radiogroup" aria-label="E-wallet provider">
            @foreach ($brand as $name => [$bg, $fg, $text])
                <label class="cursor-pointer">
                    <input type="radio" name="payment_detail" value="{{ $name }}" x-model="detail" :disabled="!({{ $active }}) || {{ $model }} !== 'ewallet'" class="sr-only peer">
                    <span class="flex items-center gap-2 border border-cream-line bg-white px-2.5 py-1.5 peer-checked:border-green-600 peer-checked:ring-2 peer-checked:ring-green-200 hover:bg-cream">
                        <svg viewBox="0 0 64 24" class="h-6 w-16" role="img" aria-label="{{ $name }} logo">
                            <rect width="64" height="24" rx="5" fill="{{ $bg }}"/>
                            <text x="32" y="16.5" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-weight="800" font-size="12" fill="{{ $fg }}">{{ $text }}</text>
                        </svg>
                        <span class="text-xs font-semibold">{{ $name }}</span>
                    </span>
                </label>
            @endforeach
        </div>
        <div>
            <label class="text-sm font-medium" x-text="detail + ' account number'"></label>
            <input name="account_number" x-model="acct" inputmode="tel" maxlength="15" autocomplete="off" placeholder="09XX XXX XXXX"
                   :required="{{ $model }} === 'ewallet' && ({{ $active }})" :disabled="!({{ $active }}) || {{ $model }} !== 'ewallet'" class="mt-1 w-full">
            <p class="mt-1 text-xs text-navy-500">The mobile number linked to your wallet. Demo only: no real payment is taken.</p>
        </div>
    </div>

    <div x-show="{{ $model }} === 'card'" x-cloak class="border border-sun-300 bg-sun-50/40 p-3 space-y-3">
        <div>
            <label class="text-sm font-medium" for="pf-bank">Bank</label>
            <select id="pf-bank" name="payment_detail" x-model="bank" :required="{{ $model }} === 'card' && ({{ $active }})" :disabled="!({{ $active }}) || {{ $model }} !== 'card'" class="mt-1 w-full">
                <option value="">Select your bank</option>
                @foreach (config('kusina.payment_methods.card.banks') as $b)<option value="{{ $b }}">{{ $b }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="text-sm font-medium" for="pf-holder">Name on card</label>
            <input id="pf-holder" name="card_holder" x-model="holder" maxlength="60" autocomplete="cc-name"
                   :required="{{ $model }} === 'card' && ({{ $active }})" :disabled="!({{ $active }}) || {{ $model }} !== 'card'" class="mt-1 w-full">
        </div>
        <div>
            <label class="text-sm font-medium" for="pf-card">Card number</label>
            <input id="pf-card" name="card_number" x-model="cardno" @input="fmtCard()" inputmode="numeric" minlength="13" maxlength="23" autocomplete="off" placeholder="0000 0000 0000 0000"
                   :required="{{ $model }} === 'card' && ({{ $active }})" :disabled="!({{ $active }}) || {{ $model }} !== 'card'" class="mt-1 w-full">
        </div>
        <div class="w-32">
            <label class="text-sm font-medium" for="pf-exp">Expiry</label>
            <input id="pf-exp" name="card_expiry" x-model="exp" @input="fmtExp()" inputmode="numeric" maxlength="5" autocomplete="off" placeholder="MM/YY" pattern="(0[1-9]|1[0-2])/[0-9]{2}"
                   :required="{{ $model }} === 'card' && ({{ $active }})" :disabled="!({{ $active }}) || {{ $model }} !== 'card'" class="mt-1 w-full">
        </div>
        <p class="text-xs text-navy-500">Demo only: use any test number. Nothing is charged and only the last 4 digits are kept for your receipt.</p>
    </div>
</div>
