<?php

namespace App\Http\Controllers;

use App\Models\Category;

class MenuController extends Controller
{
    public function index()
    {
        $categories = Category::where('is_active', true)
            ->orderBy('sort_order')
            ->with(['items' => fn ($q) => $q->with('optionGroups.options')])
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'items' => $c->items->map(fn ($i) => [
                    'id' => $i->id,
                    'name' => $i->name,
                    'description' => $i->description,
                    'price' => (float) $i->base_price,
                    'image' => $i->image_url,
                    'available' => $i->is_available,
                    'groups' => $i->optionGroups->map(fn ($g) => [
                        'id' => $g->id,
                        'name' => $g->name,
                        'type' => $g->type,
                        'required' => $g->is_required,
                        'max' => $g->max_select,
                        'options' => $g->options->where('is_available', true)->map(fn ($o) => [
                            'id' => $o->id, 'name' => $o->name, 'delta' => (float) $o->price_delta,
                        ])->values(),
                    ])->values(),
                ])->values(),
            ])->values();

        return view('menu.index', compact('categories'));
    }
}
