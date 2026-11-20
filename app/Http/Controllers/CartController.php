<?php

namespace App\Http\Controllers;

use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(private CartService $cart) {}

    public function show(): JsonResponse
    {
        return response()->json($this->cart->summary());
    }

    public function add(Request $request): JsonResponse
    {
        $data = $request->validate([
            'item_id' => ['required', 'integer'],
            'option_ids' => ['array', 'max:40'],
            'option_ids.*' => ['integer'],
            'qty' => ['required', 'integer', 'min:1', 'max:'.CartService::MAX_QTY],
            'notes' => ['nullable', 'string', 'max:200'],
        ]);

        $this->cart->add($data['item_id'], $data['option_ids'] ?? [], $data['qty'], $data['notes'] ?? null);

        return response()->json($this->cart->summary());
    }

    public function update(Request $request, string $key): JsonResponse
    {
        $data = $request->validate(['qty' => ['required', 'integer', 'min:0', 'max:'.CartService::MAX_QTY]]);
        $this->cart->setQty($key, $data['qty']);

        return response()->json($this->cart->summary());
    }

    public function destroy(string $key): JsonResponse
    {
        $this->cart->remove($key);

        return response()->json($this->cart->summary());
    }
}
