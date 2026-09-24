<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Mini Checkout Emas') — Jualemas</title>
    <style>
        :root {
            --gold: #b8860b;
            --gold-light: #d4a017;
            --gold-dark: #8a6508;
            --ink: #1f2937;
            --muted: #6b7280;
            --bg: #f6f4ef;
            --card: #ffffff;
            --line: #e7e3da;
            --ok: #15803d;
            --ok-bg: #e9f9ef;
            --risk: #b91c1c;
            --risk-bg: #fdeaea;
            --warn-bg: #fef6e0;
            --warn: #92400e;
            --shadow: 0 1px 3px rgba(31, 41, 55, .07), 0 4px 14px rgba(31, 41, 55, .05);
            --shadow-lg: 0 8px 28px rgba(31, 41, 55, .12);
            --radius: 14px;
        }

        * { box-sizing: border-box; }
        html { -webkit-text-size-adjust: 100%; }

        body {
            margin: 0;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: var(--bg);
            color: var(--ink);
            line-height: 1.55;
        }

        /* Header */
        .site-header {
            background: linear-gradient(135deg, #1f2937 0%, #111827 100%);
            color: #fff;
            padding: .9rem 1.25rem;
            position: sticky;
            top: 0;
            z-index: 10;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .18);
        }
        .site-header .inner {
            max-width: 1080px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            gap: .75rem;
        }
        .brand-mark {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--gold-light), var(--gold-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            color: #fff;
            font-size: 1.1rem;
            box-shadow: inset 0 -2px 4px rgba(0,0,0,.25), 0 2px 6px rgba(0,0,0,.3);
        }
        .brand-name { font-weight: 800; font-size: 1.15rem; letter-spacing: .2px; }
        .brand-sub { font-size: .72rem; color: #9ca3af; margin-top: -2px; }

        .container {
            max-width: 1080px;
            margin: 0 auto;
            padding: 1.25rem 1rem 3rem;
        }

        h1 {
            font-size: 1.55rem;
            margin: .5rem 0 1.25rem;
            display: flex;
            align-items: center;
            gap: .6rem;
        }
        h2 { font-size: 1.05rem; margin: 0 0 .35rem; }

        /* Product grid */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1rem;
        }
        .product-card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            padding: 1.1rem;
            box-shadow: var(--shadow);
            display: flex;
            flex-direction: column;
            gap: .55rem;
            transition: transform .15s ease, box-shadow .15s ease;
        }
        .product-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        .product-card h2 { font-size: 1.08rem; }
        .product-meta { display: flex; align-items: center; justify-content: space-between; gap: .5rem; }

        .price {
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--gold-dark);
            letter-spacing: -.3px;
        }
        .price small { font-weight: 600; font-size: .78rem; color: var(--muted); }

        .chip {
            display: inline-flex;
            align-items: center;
            gap: .3rem;
            padding: .22rem .6rem;
            border-radius: 999px;
            font-size: .75rem;
            font-weight: 700;
            white-space: nowrap;
        }
        .chip-stock { background: var(--ok-bg); color: var(--ok); }
        .chip-stock::before {
            content: "";
            width: 7px; height: 7px;
            border-radius: 50%;
            background: currentColor;
        }
        .chip-out { background: var(--risk-bg); color: var(--risk); }
        .chip-out::before {
            content: "";
            width: 7px; height: 7px;
            border-radius: 50%;
            background: currentColor;
        }

        /* Form */
        .checkout-form { display: flex; flex-direction: column; gap: .5rem; margin-top: .35rem; }
        .qty-row {
            display: flex;
            align-items: center;
            gap: .5rem;
        }
        .qty-row label { font-size: .82rem; color: var(--muted); font-weight: 600; }
        input[type="number"] {
            flex: 1;
            padding: .55rem .7rem;
            border: 1.5px solid var(--line);
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            background: #fbfaf7;
            color: var(--ink);
            min-width: 0;
        }
        input[type="number"]:focus {
            outline: none;
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(212, 160, 23, .18);
        }

        .btn {
            display: block;
            width: 100%;
            padding: .7rem 1rem;
            background: linear-gradient(135deg, var(--gold-light), var(--gold-dark));
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 2px 6px rgba(184, 134, 11, .35);
            transition: filter .15s ease, transform .1s ease;
        }
        .btn:hover { filter: brightness(1.06); }
        .btn:active { transform: scale(.98); }
        .btn:disabled {
            background: #d3cfc4;
            box-shadow: none;
            cursor: not-allowed;
        }
        .btn-outline {
            display: inline-block;
            background: transparent;
            color: var(--gold-dark);
            border: 1.5px solid var(--gold);
            box-shadow: none;
            padding: .55rem 1rem;
        }
        .btn-outline:hover { background: rgba(184, 134, 11, .08); filter: none; }

        .out-of-stock {
            color: var(--risk);
            font-weight: 600;
            font-size: .9rem;
            padding: .6rem;
            background: var(--risk-bg);
            border-radius: 10px;
            text-align: center;
        }

        /* Alerts */
        .alert {
            padding: .8rem 1rem;
            border-radius: 12px;
            margin-bottom: 1.1rem;
            font-weight: 600;
            font-size: .92rem;
        }
        .alert-success { background: var(--ok-bg); color: var(--ok); border: 1px solid #c6ecd2; }
        .alert-error { background: var(--risk-bg); color: var(--risk); border: 1px solid #f5c6c6; }
        .error { color: var(--risk); font-size: .82rem; font-weight: 600; }

        .pay-form { margin-top: .9rem; }

        .swal2-popup { border-radius: 16px; font-family: inherit; }
        .swal2-title { font-size: 1.15rem; }
        .swal2-html-container { font-size: .92rem; }

        /* Order detail */
        .order-panel {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            max-width: 640px;
        }
        .order-panel .panel-head {
            background: linear-gradient(135deg, #1f2937, #111827);
            color: #fff;
            padding: 1rem 1.25rem;
        }
        .order-panel .panel-head .order-no { font-weight: 800; font-size: 1.1rem; letter-spacing: .3px; }
        .order-panel .panel-head .order-no-hint { font-size: .72rem; color: #9ca3af; }

        .order-rows { padding: .4rem 1.25rem 1.1rem; }
        .order-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            padding: .85rem 0;
            border-bottom: 1px solid #f0ede5;
        }
        .order-row:last-child { border-bottom: none; }
        .order-row dt { color: var(--muted); font-size: .86rem; }
        .order-row dd { margin: 0; font-weight: 700; text-align: right; }
        .order-row .total dd { font-size: 1.25rem; color: var(--gold-dark); }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            padding: .3rem .75rem;
            border-radius: 999px;
            font-size: .78rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .4px;
        }
        .badge-pending { background: var(--warn-bg); color: var(--warn); border: 1px solid #f0ddb0; }
        .badge-paid { background: var(--ok-bg); color: var(--ok); border: 1px solid #c6ecd2; }

        .payment-box {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 1.1rem 1.25rem;
            margin-top: 1.25rem;
            max-width: 640px;
        }
        .payment-box h2 { margin-bottom: .4rem; }
        .payment-box .token-line { font-size: .85rem; color: var(--muted); }
        .payment-box code {
            background: #f3efe6;
            padding: .12rem .4rem;
            border-radius: 6px;
            font-size: .85rem;
            color: var(--gold-dark);
            font-weight: 700;
        }
        pre {
            background: #111827;
            color: #d1d5db;
            padding: 1rem;
            border-radius: 12px;
            overflow-x: auto;
            font-size: .8rem;
            line-height: 1.5;
            margin: .8rem 0 0;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            margin-top: 1.25rem;
            color: var(--muted);
            font-weight: 600;
            font-size: .9rem;
            text-decoration: none;
            padding: .5rem .8rem;
            border-radius: 10px;
            border: 1.5px solid var(--line);
            background: var(--card);
        }
        .back-link:hover { color: var(--gold-dark); border-color: var(--gold); }

        /* Mobile */
        @media (max-width: 600px) {
            .container { padding: 1rem .85rem 2.5rem; }
            h1 { font-size: 1.3rem; }
            .product-grid { grid-template-columns: 1fr; gap: .85rem; }
            .product-card { padding: 1rem; }
            input[type="number"] { padding: .75rem .8rem; font-size: 1.05rem; }
            .btn { padding: .85rem 1rem; font-size: 1.05rem; border-radius: 12px; }
            .order-row { flex-direction: row; }
            .order-panel .panel-head { padding: .9rem 1rem; }
            .order-rows { padding: .4rem 1rem 1rem; }
            .payment-box { padding: 1rem; }
            .qty-row { justify-content: space-between; gap: .75rem; }
            .qty-row label { flex-shrink: 0; }
            .brand-name { font-size: 1.05rem; }
        }
        @media (max-width: 340px) {
            .product-meta { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="inner">
            <div class="brand-mark">J</div>
            <div>
                <div class="brand-name">Jualemas</div>
                <div class="brand-sub">Mini Checkout Emas</div>
            </div>
        </div>
    </header>
    <div class="container">
        @yield('content')
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // SweetAlert2 via CDN, dengan fallback ke alert() native kalau offline.
        window.toast = function (icon, title, text) {
            if (window.Swal) {
                Swal.fire({ icon: icon, title: title, text: text, confirmButtonColor: '#b8860b' });
            } else {
                alert(title + (text ? '\n\n' + text : ''));
            }
        };
    </script>
    @stack('scripts')
</body>
</html>