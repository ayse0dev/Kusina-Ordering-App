@props(['title' => 'Receipt', 'back' => '/'])
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title }} · {{ config('app.name') }}</title>
    <style>
        :root { color-scheme: light; }
        body { margin: 0; background: #ece7dc; font: 14px/1.5 Arial, Helvetica, sans-serif; color: #1c1917; }
        .sheet { max-width: 420px; margin: 24px auto; background: #fff; padding: 28px 24px; border: 1px solid #d6cdb8; }
        h1 { margin: 0; font-size: 18px; letter-spacing: .18em; text-transform: uppercase; text-align: center; }
        .sub { text-align: center; color: #78716c; font-size: 12px; margin: 2px 0 16px; }
        .rule { border: 0; border-top: 1px dashed #a8a29e; margin: 14px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 3px 0; vertical-align: top; }
        td:last-child { text-align: right; }
        .muted { color: #78716c; }
        .total td { font-weight: bold; font-size: 16px; padding-top: 6px; }
        .stamp { text-align: center; margin: 14px 0 0; font-weight: bold; letter-spacing: .2em; }
        .paid { color: #047857; } .due { color: #b45309; }
        .actions { max-width: 420px; margin: 16px auto; display: flex; gap: 8px; justify-content: center; }
        .actions button, .actions a { font: inherit; padding: 8px 16px; border: 1px solid #1c1917; background: #1c1917; color: #f5d57a; text-decoration: none; cursor: pointer; }
        .actions a { background: #fff; color: #1c1917; }
        @media print { body { background: #fff; } .sheet { border: 0; margin: 0 auto; } .actions { display: none; } }
    </style>
</head>
<body>
    <div class="actions"><button type="button" onclick="window.print()">🖨 Print receipt</button><a href="{{ $back }}">Back</a></div>
    <div class="sheet">
        <h1>{{ config('app.name') }}</h1>
        <p class="sub">{{ config('kusina.contact.address') }}@if (config('kusina.contact.phone')) · {{ config('kusina.contact.phone') }}@endif</p>
        {{ $slot }}
        <hr class="rule">
        <p class="sub" style="margin:0">Demo system: no real payment was taken.<br>Thank you for choosing {{ config('app.name') }}.</p>
    </div>
</body>
</html>
