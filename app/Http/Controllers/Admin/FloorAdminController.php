<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Floor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FloorAdminController extends Controller
{
    public function index()
    {
        $floors = Floor::orderBy('sort_order')->with('tables')->get()->map(fn ($f) => [
            'id' => $f->id, 'name' => $f->name, 'width' => $f->width, 'height' => $f->height,
            'tables' => $f->tables->map(fn ($t) => $t->only(['id', 'label', 'seats', 'shape', 'x', 'y', 'w', 'h', 'is_active', 'is_vip']))->values(),
        ]);

        return view('admin.floor.index', compact('floors'));
    }

    public function storeFloor(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:60']]);
        Floor::create($data + ['sort_order' => (int) Floor::max('sort_order') + 1]);

        return back();
    }

    /** Replace a floor's table layout with what the editor sends. */
    public function sync(Request $request, Floor $floor)
    {
        $data = $request->validate([
            'tables' => ['present', 'array', 'max:80'],
            'tables.*.id' => ['nullable', 'integer'],
            'tables.*.label' => ['required', 'string', 'max:20'],
            'tables.*.seats' => ['required', 'integer', 'min:1', 'max:30'],
            'tables.*.shape' => ['required', 'in:rect,round'],
            'tables.*.x' => ['required', 'integer', 'min:0', 'max:'.$floor->width],
            'tables.*.y' => ['required', 'integer', 'min:0', 'max:'.$floor->height],
            'tables.*.w' => ['required', 'integer', 'min:30', 'max:400'],
            'tables.*.h' => ['required', 'integer', 'min:30', 'max:400'],
            'tables.*.is_active' => ['boolean'],
            'tables.*.is_vip' => ['boolean'],
        ]);

        DB::transaction(function () use ($floor, $data) {
            $keep = [];
            foreach ($data['tables'] as $row) {
                $attrs = collect($row)->only(['label', 'seats', 'shape', 'x', 'y', 'w', 'h'])->all()
                    + ['is_active' => (bool) ($row['is_active'] ?? true), 'is_vip' => (bool) ($row['is_vip'] ?? false)];

                $table = ! empty($row['id']) ? $floor->tables()->find($row['id']) : null;
                $table ? $table->update($attrs) : $table = $floor->tables()->create($attrs);
                $keep[] = $table->id;
            }

            // Removed tables: delete if unused, otherwise just deactivate to keep reservation history.
            foreach ($floor->tables()->whereNotIn('id', $keep)->get() as $gone) {
                $gone->reservations()->exists() ? $gone->update(['is_active' => false]) : $gone->delete();
            }
        });

        $fresh = $floor->tables()->get()->map(fn ($t) => $t->only(['id', 'label', 'seats', 'shape', 'x', 'y', 'w', 'h', 'is_active', 'is_vip']))->values();

        return response()->json(['tables' => $fresh]);
    }
}
