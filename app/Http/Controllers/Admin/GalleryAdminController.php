<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GalleryPhoto;
use App\Support\ImageSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class GalleryAdminController extends Controller
{
    public function index()
    {
        return view('admin.gallery.index', ['photos' => GalleryPhoto::orderBy('sort_order')->orderByDesc('id')->get()]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'photos' => ['required', 'array', 'max:12'],
            'photos.*' => ['file', 'max:5120'],
            'caption' => ['nullable', 'string', 'max:120'],
        ]);

        $saved = 0;
        $skipped = 0;
        foreach ($request->file('photos') as $file) {
            try {
                GalleryPhoto::create([
                    'path' => ImageSanitizer::store($file, 'gallery', 1600),
                    'caption' => $request->input('caption'),
                    'sort_order' => (int) GalleryPhoto::min('sort_order') - 1,
                ]);
                $saved++;
            } catch (ValidationException) {
                $skipped++;
            }
        }

        if ($saved === 0) {
            throw ValidationException::withMessages(['photos' => 'None of those files were valid JPG, PNG or WebP images.']);
        }

        return back()->with('status', "$saved photo(s) added".($skipped ? ", $skipped skipped (not a valid image)." : '.'));
    }

    public function destroy(GalleryPhoto $photo)
    {
        Storage::disk('public')->delete($photo->path);
        $photo->delete();

        return back()->with('status', 'Photo removed.');
    }
}
