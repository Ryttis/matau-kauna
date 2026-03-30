<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') – {{ config('app.name', 'Laravel') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --lp-primary: #1946A0; --lp-bg: #f4f6f9; --lp-text: #1a1a1a; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Open Sans', system-ui, sans-serif;
            background: var(--lp-bg);
            color: var(--lp-text);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(25, 70, 160, 0.08);
            max-width: 28rem;
            width: 100%;
            padding: 2rem 1.75rem;
            text-align: center;
        }
        .code {
            font-size: 3rem;
            font-weight: 700;
            color: var(--lp-primary);
            line-height: 1.1;
            margin: 0 0 0.5rem;
        }
        h1 {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0 0 0.75rem;
        }
        p {
            margin: 0 0 1.25rem;
            font-size: 0.95rem;
            line-height: 1.5;
            color: #444;
        }
        a {
            display: inline-block;
            background: var(--lp-primary);
            color: #fff !important;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            padding: 0.65rem 1.25rem;
            border-radius: 8px;
        }
        a:hover { filter: brightness(1.05); }
    </style>
</head>
<body>
    <div class="card">
        @yield('content')
    </div>
</body>
</html>
