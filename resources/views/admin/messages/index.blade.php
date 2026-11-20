<x-admin-layout title="Messages">
    <h1 class="text-xl font-bold mb-4">Contact messages</h1>

    <div class="space-y-3">
        @forelse ($messages as $m)
            <article class="bg-white border border-cream-line shadow-card p-5 {{ $m->read_at ? 'opacity-70' : 'border-l-4 border-l-sun-500' }}">
                <div class="flex flex-wrap items-baseline gap-x-4 gap-y-1">
                    <p class="font-bold">{{ $m->name }}</p>
                    <a href="mailto:{{ $m->email }}" class="text-sm text-sun-700 hover:underline">{{ $m->email }}</a>
                    @if ($m->phone)<span class="text-sm text-navy-500">{{ $m->phone }}</span>@endif
                    <span class="ml-auto text-xs text-navy-400">{{ $m->created_at->format('M j, Y g:i A') }}</span>
                </div>
                <p class="mt-3 text-sm whitespace-pre-line text-navy-700">{{ $m->message }}</p>
                <div class="mt-3 flex gap-5 text-xs">
                    <form method="POST" action="{{ route('admin.messages.read', $m) }}">@csrf<button class="underline text-navy-600 hover:text-navy-900">{{ $m->read_at ? 'Mark unread' : 'Mark read' }}</button></form>
                    <form method="POST" action="{{ route('admin.messages.destroy', $m) }}" x-data @submit="if (!confirm('Delete this message?')) $event.preventDefault()">@csrf @method('DELETE')<button class="underline text-brand-700">Delete</button></form>
                </div>
            </article>
        @empty
            <p class="bg-white border border-cream-line p-8 text-center text-navy-400">No messages yet.</p>
        @endforelse
    </div>
    <div class="mt-4">{{ $messages->links() }}</div>
</x-admin-layout>
