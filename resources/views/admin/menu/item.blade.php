<x-admin-layout :title="$item ? 'Edit item' : 'New item'">
<form method="POST" enctype="multipart/form-data" x-data="itemForm(@js(['groups' => $groups]))"
      action="{{ $item ? route('admin.menu.items.update', $item) : route('admin.menu.items.store') }}" class="max-w-3xl space-y-6">
    @csrf
    @if ($item) @method('PUT') @endif
    <h1 class="text-xl font-semibold">{{ $item ? 'Edit item' : 'New item' }}</h1>

    <section class="bg-white rounded-2xl border border-cream-line shadow-card p-5 grid gap-4 sm:grid-cols-2">
        <div class="sm:col-span-2"><label class="text-sm font-medium">Name</label>
            <input name="name" required maxlength="120" value="{{ old('name', $item?->name) }}" class="mt-1 w-full rounded-lg border-stone-300"></div>
        <div><label class="text-sm font-medium">Category</label>
            <select name="category_id" required class="mt-1 w-full rounded-lg border-stone-300">
                @foreach ($categories as $c)<option value="{{ $c->id }}" @selected(old('category_id', $item?->category_id) == $c->id)>{{ $c->name }}</option>@endforeach
            </select></div>
        <div><label class="text-sm font-medium">Base price ($)</label>
            <input name="base_price" type="number" step="0.01" min="0" required value="{{ old('base_price', $item?->base_price) }}" class="mt-1 w-full rounded-lg border-stone-300"></div>
        <div class="sm:col-span-2"><label class="text-sm font-medium">Description</label>
            <textarea name="description" rows="2" maxlength="500" class="mt-1 w-full rounded-lg border-stone-300">{{ old('description', $item?->description) }}</textarea></div>
        <div><label class="text-sm font-medium">Photo <span class="text-stone-400 font-normal">(JPG/PNG/WebP, max 3 MB)</span></label>
            <input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="mt-1 block w-full text-sm">
            @if ($item?->image_url)<img src="{{ $item->image_url }}" class="mt-2 h-16 rounded-lg" alt="">@endif</div>
        <label class="flex items-center gap-2 text-sm self-end"><input type="checkbox" name="is_available" value="1" class="rounded text-brand-600"
               @checked(old('is_available', $item?->is_available ?? true))> Available for ordering</label>
    </section>

    <section class="space-y-3">
        <div class="flex items-center">
            <h2 class="font-semibold">Customization options</h2>
            <button type="button" @click="addGroup()" class="ml-auto rounded-lg border bg-white px-3 py-1.5 text-sm hover:bg-stone-50">+ Add option group</button>
        </div>
        <p class="text-xs text-stone-500">e.g. “Size” (choose one, required) or “Add-ons” (choose many). Prices are added to the base price; use a negative value for a discount.</p>

        <template x-for="(g, gi) in groups" :key="gi">
            <div class="bg-white rounded-2xl border border-cream-line shadow-card p-4 space-y-3">
                <div class="flex flex-wrap gap-3 items-end">
                    <div class="flex-1 min-w-40"><label class="text-xs font-medium">Group name</label>
                        <input :name="`groups[${gi}][name]`" x-model="g.name" required maxlength="80" class="mt-1 w-full rounded-lg border-stone-300 text-sm"></div>
                    <div><label class="text-xs font-medium">Selection</label>
                        <select :name="`groups[${gi}][type]`" x-model="g.type" class="mt-1 block rounded-lg border-stone-300 text-sm">
                            <option value="single">Choose one</option><option value="multiple">Choose many</option></select></div>
                    <div x-show="g.type === 'multiple'"><label class="text-xs font-medium">Max (0 = any)</label>
                        <input :name="`groups[${gi}][max_select]`" x-model.number="g.max_select" type="number" min="0" max="20" class="mt-1 block w-20 rounded-lg border-stone-300 text-sm"></div>
                    <label class="text-sm flex items-center gap-1.5 pb-2">
                        <input type="hidden" :name="`groups[${gi}][is_required]`" value="0">
                        <input type="checkbox" :name="`groups[${gi}][is_required]`" value="1" x-model="g.is_required" class="rounded text-brand-600"> Required</label>
                    <button type="button" @click="removeGroup(gi)" class="text-sm text-red-600 pb-2 hover:underline">Remove group</button>
                </div>

                <div class="space-y-2">
                    <template x-for="(o, oi) in g.options" :key="oi">
                        <div class="flex gap-2 items-center">
                            <input :name="`groups[${gi}][options][${oi}][name]`" x-model="o.name" required maxlength="80" placeholder="Option name" class="flex-1 rounded-lg border-stone-300 text-sm">
                            <input :name="`groups[${gi}][options][${oi}][price_delta]`" x-model.number="o.price_delta" type="number" step="0.01" class="w-28 rounded-lg border-stone-300 text-sm" title="Price change ($)">
                            <label class="text-xs flex items-center gap-1">
                                <input type="hidden" :name="`groups[${gi}][options][${oi}][is_available]`" value="0">
                                <input type="checkbox" :name="`groups[${gi}][options][${oi}][is_available]`" value="1" x-model="o.is_available" class="rounded text-brand-600"> On</label>
                            <button type="button" @click="removeOption(g, oi)" class="text-stone-400 hover:text-red-600" x-show="g.options.length > 1" aria-label="Remove option">✕</button>
                        </div>
                    </template>
                    <button type="button" @click="addOption(g)" class="text-sm text-brand-700 hover:underline">+ Add option</button>
                </div>
            </div>
        </template>
    </section>

    <div class="flex gap-3">
        <button class="rounded-lg bg-brand-600 text-white px-5 py-2.5 font-medium hover:bg-brand-700">Save item</button>
        <a href="{{ route('admin.menu') }}" class="rounded-lg border bg-white px-5 py-2.5 hover:bg-stone-50">Cancel</a>
    </div>
</form>

@if ($item)
    <form method="POST" action="{{ route('admin.menu.items.destroy', $item) }}" class="mt-6" x-data @submit="if (!confirm('Delete this item permanently?')) $event.preventDefault()">
        @csrf @method('DELETE')
        <button class="text-sm text-red-600 hover:underline">Delete this item</button>
    </form>
@endif
</x-admin-layout>
