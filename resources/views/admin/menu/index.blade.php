<x-admin-layout title="Menu">
    <div class="flex items-center mb-4">
        <h1 class="text-xl font-semibold">Menu management</h1>
        <a href="{{ route('admin.menu.items.create') }}" class="ml-auto rounded-lg bg-brand-600 text-white px-4 py-2 text-sm font-medium hover:bg-brand-700">+ New item</a>
    </div>

    <form method="POST" action="{{ route('admin.menu.categories.store') }}" class="bg-white rounded-2xl border border-cream-line shadow-card p-4 mb-6 flex flex-wrap gap-3 items-end">
        @csrf
        <div><label class="text-xs font-medium">New category</label><input name="name" required maxlength="80" class="mt-1 block rounded-lg border-stone-300 text-sm"></div>
        <div><label class="text-xs font-medium">Order</label><input name="sort_order" type="number" min="0" value="0" class="mt-1 block w-20 rounded-lg border-stone-300 text-sm"></div>
        <button class="rounded-lg bg-stone-800 text-white px-4 py-2 text-sm">Add category</button>
    </form>

    @foreach ($categories as $c)
        <section class="bg-white rounded-2xl border border-cream-line shadow-card mb-5">
            <form method="POST" action="{{ route('admin.menu.categories.update', $c) }}" class="p-4 border-b flex flex-wrap items-center gap-3">
                @csrf @method('PUT')
                <input name="name" value="{{ $c->name }}" required maxlength="80" class="rounded-lg border-stone-300 text-sm font-semibold">
                <input name="sort_order" type="number" min="0" value="{{ $c->sort_order }}" class="w-20 rounded-lg border-stone-300 text-sm" title="Sort order">
                <label class="text-sm flex items-center gap-1.5"><input type="checkbox" name="is_active" value="1" @checked($c->is_active) class="rounded text-brand-600"> Visible</label>
                <button class="rounded-lg border px-3 py-1.5 text-sm hover:bg-stone-50">Save</button>
                <button form="del-cat-{{ $c->id }}" class="ml-auto text-sm text-red-600 hover:underline">Delete</button>
            </form>
            <form id="del-cat-{{ $c->id }}" method="POST" action="{{ route('admin.menu.categories.destroy', $c) }}" x-data @submit="if (!confirm('Delete this category and all its items?')) $event.preventDefault()">@csrf @method('DELETE')</form>

            <ul class="divide-y">
                @forelse ($c->items as $i)
                    <li class="px-4 py-3 flex items-center gap-3 text-sm">
                        <div class="w-10 h-10 rounded-lg bg-sun-100 overflow-hidden grid place-items-center shrink-0">
                            @if ($i->image_url)<img src="{{ $i->image_url }}" alt="" class="w-full h-full object-cover">@else 🍲 @endif
                        </div>
                        <div class="flex-1 min-w-0"><p class="font-medium">{{ $i->name }}</p><p class="text-xs text-stone-500">${{ number_format($i->base_price, 2) }}</p></div>
                        <form method="POST" action="{{ route('admin.menu.items.toggle', $i) }}">@csrf
                            <button class="rounded-full px-3 py-1 text-xs {{ $i->is_available ? 'bg-emerald-100 text-emerald-700' : 'bg-stone-200 text-stone-600' }}">{{ $i->is_available ? 'Available' : 'Sold out' }}</button>
                        </form>
                        <a href="{{ route('admin.menu.items.edit', $i) }}" class="text-brand-700 hover:underline">Edit</a>
                    </li>
                @empty
                    <li class="px-4 py-4 text-sm text-stone-500">No items in this category.</li>
                @endforelse
            </ul>
        </section>
    @endforeach
</x-admin-layout>
