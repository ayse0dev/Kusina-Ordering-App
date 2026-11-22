@props(['spin' => false])
@php $cid = 'sg'.substr(md5(uniqid('', true)), 0, 8); @endphp
<svg {{ $attributes->class(['animate-glow']) }} viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
    <defs>
        <clipPath id="{{ $cid }}">
            <circle cx="12" cy="12" r="4.2"/>
            @for ($i = 0; $i < 8; $i++)
                <path transform="rotate({{ $i * 45 }} 12 12)" d="M12 1.2 13.7 6.6h-3.4z"/>
            @endfor
        </clipPath>
    </defs>
    <g @class(['animate-spin-slow' => $spin]) style="transform-origin: 12px 12px">
    <circle cx="12" cy="12" r="4.2"/>
    @for ($i = 0; $i < 8; $i++)
        <path transform="rotate({{ $i * 45 }} 12 12)" d="M12 1.2 13.7 6.6h-3.4z"/>
    @endfor
    <g clip-path="url(#{{ $cid }})">
        <rect class="sinag-shine" x="-7" y="-2" width="5" height="28" fill="#fffbe8"/>
    </g>
    </g>
</svg>
