<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;

class MessageAdminController extends Controller
{
    public function index()
    {
        return view('admin.messages.index', ['messages' => ContactMessage::latest()->paginate(20)]);
    }

    public function toggleRead(ContactMessage $message)
    {
        $message->update(['read_at' => $message->read_at ? null : now()]);

        return back();
    }

    public function destroy(ContactMessage $message)
    {
        $message->delete();

        return back();
    }
}
