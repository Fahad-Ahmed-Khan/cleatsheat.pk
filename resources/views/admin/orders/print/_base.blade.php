<!doctype html>
<html>
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <style>
            * { box-sizing: border-box; }
            body { margin: 0; padding: 0; font-family: DejaVu Sans, Arial, sans-serif; color: #111827; }
            .page { page-break-after: always; }
            .page:last-child { page-break-after: auto; }
            .muted { color: #6b7280; }
            .small { font-size: 11px; }
            .h1 { font-size: 18px; font-weight: 700; margin: 0; }
            .row { display: flex; gap: 10px; }
            .col { flex: 1; }
            .box { border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px; }
            .kv { display: flex; justify-content: space-between; gap: 12px; }
            .kv strong { font-weight: 700; }
            .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
        </style>
        @stack('styles')
    </head>
    <body>
        @yield('content')
    </body>
</html>

