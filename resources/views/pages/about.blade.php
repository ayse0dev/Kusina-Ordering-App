<x-kusina-layout title="About">
<section class="hero-ambient text-center text-white py-16">
    <p class="script text-5xl !text-sun-300">Aming kuwento</p>
    <h1 class="mt-1 text-3xl sm:text-4xl font-bold">About us</h1>
</section>

<section class="bg-white">
    <div class="max-w-3xl mx-auto px-4 py-16">
        <h2 class="section-title text-3xl">Bringing home to Canada</h2>
        <span class="section-rule"></span>
        <p class="mt-6 italic text-navy-600 text-xl leading-relaxed">“Every dish we serve started at a family table.”</p>

        {{-- Story copy: edit freely in resources/views/pages/about.blade.php --}}
        <div class="mt-8 space-y-5 text-navy-600 leading-relaxed">
            <p>Growing up Filipino means growing up around food: the sizzle of garlic rice before sunrise, a pot of adobo simmering for Sunday lunch, relatives crowding the kitchen long before anyone is hungry. Cooking was how our families said <em>I love you</em>, and the table was where everyone belonged.</p>
            <p>When our family made the journey to Canada, we brought our recipes with us, written in memory more than on paper. Finding the right ingredients was a challenge, and the first batch of sinigang in a new kitchen never tasted exactly like home. But we kept cooking, adjusting, and sharing, until neighbours and friends started asking for a seat at our table.</p>
            <p>Kusina grew from those gatherings. Today we are proud to bring Filipino delicacies to Edson and everyone who passes through, from the classics our lolas taught us to the comfort dishes that remind newcomers of home and introduce curious guests to something new.</p>
            <p>Every plate is cooked fresh, seasoned the way we were taught, and served with <em>malasakit</em>, the Filipino word for caring deeply about the people you serve. Salamat po for letting us share it with you.</p>
        </div>

        <div class="mt-12 grid gap-4 sm:grid-cols-3 text-center">
            @foreach ([['Heritage', 'Family recipes, honoured and shared'], ['Freshness', 'Cooked to order, every day'], ['Malasakit', 'Care in every plate']] as [$t, $d])
                <div class="border border-cream-line p-5">
                    <p class="font-display text-xs font-bold uppercase tracking-[0.2em] text-navy-900">{{ $t }}</p>
                    <p class="text-sm text-navy-500 mt-1">{{ $d }}</p>
                </div>
            @endforeach
        </div>

        <div class="mt-12 flex flex-wrap gap-8">
            <a href="{{ route('menu') }}" class="link-gold-dark">Explore the menu</a>
            <a href="{{ route('reservations.index') }}" class="link-gold-dark">Reserve a table</a>
        </div>
    </div>
</section>
</x-kusina-layout>
