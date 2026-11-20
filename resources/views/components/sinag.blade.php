{{-- Sinag: the eight-rayed sun, a nod to the sun of the Philippine flag. Inherits color from text-*. --}}
<svg {{ $attributes }} viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
    <circle cx="12" cy="12" r="4.2"/>
    @for ($i = 0; $i < 8; $i++)
        <path transform="rotate({{ $i * 45 }} 12 12)" d="M12 1.2 13.7 6.6h-3.4z"/>
    @endfor
</svg>
