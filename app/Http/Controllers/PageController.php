<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use App\Models\GalleryPhoto;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function home()
    {
        $photos = GalleryPhoto::orderBy('sort_order')->orderByDesc('id')->limit(12)->get()
            ->map(fn ($p) => ['url' => $p->url, 'alt' => $p->caption ?: 'A dish from Kusi Nna'])->values();

        return view('pages.home', compact('photos'));
    }

    public function about()
    {
        return view('pages.about');
    }

    public function contact()
    {
        return view('pages.contact');
    }

    public function contactStore(Request $request)
    {
        // Honeypot: real visitors never fill this hidden field.
        if ($request->filled('website')) {
            return redirect()->route('contact')->with('status', 'Thank you! We’ll be in touch soon.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'regex:/^[0-9+\-\s()]{7,20}$/'],
            'message' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        ContactMessage::create($data);

        return redirect()->route('contact')->with('status', 'Thank you! We’ll be in touch soon.');
    }
}
