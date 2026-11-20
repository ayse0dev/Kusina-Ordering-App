<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\MenuItem;
use App\Support\ImageSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MenuAdminController extends Controller
{
    public function index()
    {
        return view('admin.menu.index', [
            'categories' => Category::orderBy('sort_order')->with('items')->get(),
        ]);
    }

    public function categoryStore(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        Category::create([
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['name']),
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => true,
        ]);

        return back()->with('status', 'Category added.');
    }

    public function categoryUpdate(Request $request, Category $category)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $category->update([
            'name' => $data['name'],
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('status', 'Category updated.');
    }

    public function categoryDestroy(Category $category)
    {
        $category->items->each(fn ($i) => $i->image_path && Storage::disk('public')->delete($i->image_path));
        $category->delete();

        return back()->with('status', 'Category deleted.');
    }

    public function itemForm(?MenuItem $item = null)
    {
        $item?->load('optionGroups.options');

        return view('admin.menu.item', [
            'item' => $item,
            'categories' => Category::orderBy('sort_order')->get(['id', 'name']),
            'groups' => $item
                ? $item->optionGroups->map(fn ($g) => [
                    'name' => $g->name, 'type' => $g->type, 'is_required' => $g->is_required, 'max_select' => $g->max_select,
                    'options' => $g->options->map(fn ($o) => [
                        'name' => $o->name, 'price_delta' => (float) $o->price_delta, 'is_available' => $o->is_available,
                    ])->values(),
                ])->values()
                : [],
        ]);
    }

    public function itemSave(Request $request, ?MenuItem $item = null)
    {
        $data = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'base_price' => ['required', 'numeric', 'min:0', 'max:99999'],
            'image' => ['nullable', 'file', 'max:3072'],
            'groups' => ['nullable', 'array', 'max:10'],
            'groups.*.name' => ['required', 'string', 'max:80'],
            'groups.*.type' => ['required', 'in:single,multiple'],
            'groups.*.is_required' => ['nullable', 'boolean'],
            'groups.*.max_select' => ['nullable', 'integer', 'min:0', 'max:20'],
            'groups.*.options' => ['required', 'array', 'min:1', 'max:30'],
            'groups.*.options.*.name' => ['required', 'string', 'max:80'],
            'groups.*.options.*.price_delta' => ['nullable', 'numeric', 'min:-9999', 'max:9999'],
            'groups.*.options.*.is_available' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($request, $data, &$item) {
            $attrs = [
                'category_id' => $data['category_id'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'base_price' => $data['base_price'],
                'is_available' => $request->boolean('is_available'),
            ];

            if ($request->hasFile('image')) {
                $new = ImageSanitizer::store($request->file('image'));
                if ($item?->image_path) {
                    Storage::disk('public')->delete($item->image_path);
                }
                $attrs['image_path'] = $new;
            }

            $item = $item ? tap($item)->update($attrs) : MenuItem::create($attrs);

            // Replace option groups wholesale (orders keep their own snapshots).
            $item->optionGroups()->delete();
            foreach (array_values($data['groups'] ?? []) as $gi => $g) {
                $group = $item->optionGroups()->create([
                    'name' => $g['name'],
                    'type' => $g['type'],
                    'is_required' => (bool) ($g['is_required'] ?? false),
                    'max_select' => $g['type'] === 'multiple' ? (int) ($g['max_select'] ?? 0) : 1,
                    'sort_order' => $gi,
                ]);
                foreach (array_values($g['options']) as $oi => $o) {
                    $group->options()->create([
                        'name' => $o['name'],
                        'price_delta' => $o['price_delta'] ?? 0,
                        'is_available' => (bool) ($o['is_available'] ?? true),
                        'sort_order' => $oi,
                    ]);
                }
            }
        });

        return redirect()->route('admin.menu')->with('status', 'Item saved.');
    }

    public function itemToggle(MenuItem $item)
    {
        $item->update(['is_available' => ! $item->is_available]);

        return back();
    }

    public function itemDestroy(MenuItem $item)
    {
        if ($item->image_path) {
            Storage::disk('public')->delete($item->image_path);
        }
        $item->delete();

        return redirect()->route('admin.menu')->with('status', 'Item deleted.');
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        $i = 2;
        while (Category::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
