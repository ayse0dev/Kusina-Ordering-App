<x-kusina-layout title="Contact us">
<section class="hero-ambient text-center text-white py-16">
    <p class="script text-5xl !text-sun-300">Kumusta!</p>
    <h1 class="mt-1 text-3xl sm:text-4xl font-bold">Contact us</h1>
</section>

<section class="bg-white">
    <div class="max-w-5xl mx-auto px-4 py-14 grid gap-12 md:grid-cols-5">
        <div class="md:col-span-2 space-y-7">
            <div>
                <h2 class="section-title text-xl">Find us</h2><span class="section-rule"></span>
                <p class="mt-4 text-navy-600">{{ config('kusina.contact.address') }}</p>
            </div>
            <div>
                <h2 class="section-title text-xl">Opening hours</h2><span class="section-rule"></span>
                <p class="mt-4 text-navy-600">Every day<br><strong class="text-navy-900">9:00 AM – 10:00 PM</strong></p>
            </div>
            @if (config('kusina.contact.phone') || config('kusina.contact.email'))
                <div>
                    <h2 class="section-title text-xl">Reach us</h2><span class="section-rule"></span>
                    <p class="mt-4 text-navy-600">
                        @if (config('kusina.contact.phone')) <a class="hover:text-sun-700" href="tel:{{ config('kusina.contact.phone') }}">{{ config('kusina.contact.phone') }}</a><br>@endif
                        @if (config('kusina.contact.email')) <a class="hover:text-sun-700" href="mailto:{{ config('kusina.contact.email') }}">{{ config('kusina.contact.email') }}</a>@endif
                    </p>
                </div>
            @endif
            <p class="text-sm text-navy-500">Planning a visit? <a href="{{ route('reservations.index') }}" class="link-gold-dark">Reserve a table</a></p>
        </div>

        <div class="md:col-span-3">
            <h2 class="section-title text-xl">Send us a message</h2><span class="section-rule"></span>
            <p class="script text-3xl mt-2">Sabihin mo sa amin</p>

            @if (session('status'))
                <div class="mt-5 bg-sun-50 border border-sun-200 text-sun-800 px-4 py-3 text-sm">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="mt-5 bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm"><ul class="list-disc list-inside">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
            @endif

            <form method="POST" action="{{ route('contact.store') }}" class="mt-6 space-y-4">
                @csrf
                <div class="hidden" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div><label class="text-sm font-bold" for="name">Name</label>
                        <input id="name" name="name" value="{{ old('name') }}" required maxlength="100" class="mt-1 w-full"></div>
                    <div><label class="text-sm font-bold" for="email">Email</label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" required maxlength="150" class="mt-1 w-full"></div>
                </div>
                <div><label class="text-sm font-bold" for="phone">Phone <span class="font-normal text-navy-400">(optional)</span></label>
                    <input id="phone" name="phone" value="{{ old('phone') }}" maxlength="20" inputmode="tel" class="mt-1 w-full"></div>
                <div><label class="text-sm font-bold" for="message">Message</label>
                    <textarea id="message" name="message" rows="5" required minlength="5" maxlength="2000" class="mt-1 w-full">{{ old('message') }}</textarea></div>
                <button class="btn-primary">Send message</button>
            </form>
        </div>
    </div>
</section>
</x-kusina-layout>
