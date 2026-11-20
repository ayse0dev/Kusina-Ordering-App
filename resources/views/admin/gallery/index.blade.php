<x-admin-layout title="Gallery">
    <div class="flex flex-wrap items-end gap-3 mb-4">
        <div>
            <h1 class="text-xl font-bold">Food gallery</h1>
            <p class="text-sm text-navy-500">These photos appear in the “From our kitchen” section on the home page, newest first.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.gallery.store') }}" enctype="multipart/form-data"
          class="bg-white border border-cream-line shadow-card p-5 mb-6 grid gap-4 sm:grid-cols-3 items-end">
        @csrf
        <div class="sm:col-span-2">
            <label class="text-xs font-bold" for="photos">Photos <span class="font-normal text-navy-400">(JPG, PNG or WebP · up to 12 at a time · 5 MB each)</span></label>
            <input id="photos" type="file" name="photos[]" multiple required accept="image/jpeg,image/png,image/webp" class="mt-1 block w-full text-sm">
        </div>
        <div>
            <label class="text-xs font-bold" for="caption">Caption <span class="font-normal text-navy-400">(optional, used as alt text)</span></label>
            <input id="caption" name="caption" maxlength="120" class="mt-1 w-full" placeholder="e.g. Crispy pata">
        </div>
        <div class="sm:col-span-3"><button class="btn-primary">Upload photos</button></div>
    </form>

    @if ($photos->isEmpty())
        <p class="bg-white border border-cream-line p-10 text-center text-navy-400">No photos yet. Upload your first dish above.</p>
    @else
        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-3">
            @foreach ($photos as $p)
                <figure class="group relative aspect-square bg-navy-100 overflow-hidden border border-cream-line">
                    <img src="{{ $p->url }}" alt="{{ $p->caption ?? 'Kusina dish' }}" class="w-full h-full object-cover" loading="lazy">
                    <form method="POST" action="{{ route('admin.gallery.destroy', $p) }}" x-data @submit="if (!confirm('Remove this photo?')) $event.preventDefault()"
                          class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/80 to-transparent p-2 opacity-0 group-hover:opacity-100 focus-within:opacity-100 transition flex items-end justify-between gap-2">
                        @csrf @method('DELETE')
                        <span class="text-[11px] text-white/90 truncate">{{ $p->caption }}</span>
                        <button class="text-[11px] uppercase tracking-widest text-white bg-brand-700 hover:bg-brand-800 px-2 py-1">Remove</button>
                    </form>
                </figure>
            @endforeach
        </div>
    @endif
</x-admin-layout>
