<?php

namespace App\Services;

use App\Models\MenuItem;
use App\Models\Option;
use Illuminate\Validation\ValidationException;

class CartService
{
    private const KEY = 'cart';
    public const MAX_QTY = 50;

    public function add(int $itemId, array $optionIds, int $qty, ?string $notes): void
    {
        $item = MenuItem::with('optionGroups.options')->find($itemId);

        if (! $item || ! $item->is_available) {
            throw ValidationException::withMessages(['item_id' => 'This item is not available right now.']);
        }

        $optionIds = $this->validateOptions($item, $optionIds);
        $notes = $notes !== null ? trim(mb_substr($notes, 0, 200)) : null;
        $key = md5($itemId.'|'.implode(',', $optionIds).'|'.$notes);

        $cart = session(self::KEY, []);
        $existing = $cart[$key]['qty'] ?? 0;
        $cart[$key] = [
            'item_id' => $itemId,
            'option_ids' => $optionIds,
            'notes' => $notes ?: null,
            'qty' => min(self::MAX_QTY, $existing + max(1, $qty)),
        ];
        session([self::KEY => $cart]);
    }

    public function setQty(string $key, int $qty): void
    {
        $cart = session(self::KEY, []);
        if (! isset($cart[$key])) {
            return;
        }
        if ($qty < 1) {
            unset($cart[$key]);
        } else {
            $cart[$key]['qty'] = min(self::MAX_QTY, $qty);
        }
        session([self::KEY => $cart]);
    }

    public function remove(string $key): void
    {
        $cart = session(self::KEY, []);
        unset($cart[$key]);
        session([self::KEY => $cart]);
    }

    public function clear(): void
    {
        session()->forget(self::KEY);
    }

    public function summary(): array
    {
        $cart = session(self::KEY, []);
        if (! $cart) {
            return ['lines' => [], 'subtotal' => 0.0, 'count' => 0];
        }

        $items = MenuItem::whereIn('id', collect($cart)->pluck('item_id')->unique())->get()->keyBy('id');
        $allOptionIds = collect($cart)->pluck('option_ids')->flatten()->unique()->all();
        $options = Option::with('group')->whereIn('id', $allOptionIds)->get()->keyBy('id');

        $lines = [];
        $subtotal = 0.0;
        $count = 0;
        $changed = false;

        foreach ($cart as $key => $line) {
            $item = $items->get($line['item_id']);
            $opts = collect($line['option_ids'])->map(fn ($id) => $options->get($id));

            if (! $item || ! $item->is_available || $opts->contains(fn ($o) => ! $o || ! $o->is_available)) {
                unset($cart[$key]);
                $changed = true;
                continue;
            }

            $unit = (float) $item->base_price + $opts->sum(fn ($o) => (float) $o->price_delta);
            $unit = max(0.0, round($unit, 2));
            $total = round($unit * $line['qty'], 2);
            $subtotal += $total;
            $count += $line['qty'];

            $lines[] = [
                'key' => $key,
                'item_id' => $item->id,
                'name' => $item->name,
                'image_url' => $item->image_url,
                'notes' => $line['notes'],
                'qty' => $line['qty'],
                'unit_price' => $unit,
                'line_total' => $total,
                'options' => $opts->map(fn ($o) => [
                    'group' => $o->group->name, 'name' => $o->name, 'price_delta' => (float) $o->price_delta,
                ])->all(),
            ];
        }

        if ($changed) {
            session([self::KEY => $cart]);
        }

        return ['lines' => $lines, 'subtotal' => round($subtotal, 2), 'count' => $count];
    }

    private function validateOptions(MenuItem $item, array $optionIds): array
    {
        $optionIds = collect($optionIds)->map(fn ($v) => (int) $v)->unique()->sort()->values()->all();
        $valid = [];
        $errors = [];

        foreach ($item->optionGroups as $group) {
            $groupOptionIds = $group->options->where('is_available', true)->pluck('id')->all();
            $picked = array_values(array_intersect($optionIds, $groupOptionIds));
            $valid = array_merge($valid, $picked);

            if ($group->is_required && count($picked) === 0) {
                $errors['options'] = "Please choose an option for “{$group->name}”.";
            }
            if ($group->type === 'single' && count($picked) > 1) {
                $errors['options'] = "Choose only one option for “{$group->name}”.";
            }
            if ($group->type === 'multiple' && $group->max_select > 0 && count($picked) > $group->max_select) {
                $errors['options'] = "Choose up to {$group->max_select} for “{$group->name}”.";
            }
        }

        if (count($valid) !== count($optionIds)) {
            $errors['options'] = 'One or more selected options are invalid.';
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        sort($valid);

        return $valid;
    }
}
