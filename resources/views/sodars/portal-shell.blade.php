<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $portal }} | SODARS</title>
    <style>
        :root {
            --emerald: #014D40;
            --emerald-deep: #062F28;
            --saffron: #C76B00;
            --surface: #FFFFFF;
            --canvas: #F5F7F6;
            --border: #E5EDEA;
            --text: #17211F;
            --muted: #64746F;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Inter, "Plus Jakarta Sans", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: var(--text);
            background: var(--canvas);
        }

        .shell {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 280px minmax(0, 1fr);
        }

        aside {
            background: var(--emerald-deep);
            color: white;
            padding: 28px 22px;
        }

        .brand {
            font-size: 26px;
            font-weight: 800;
            letter-spacing: 0;
            margin-bottom: 6px;
        }

        .domain {
            color: #B8CEC8;
            font-size: 13px;
            margin-bottom: 36px;
        }

        nav {
            display: grid;
            gap: 8px;
        }

        nav span {
            display: block;
            padding: 12px 14px;
            border-left: 3px solid transparent;
            color: #DBE8E4;
            font-size: 14px;
        }

        nav span.active {
            border-color: var(--saffron);
            background: rgba(255, 255, 255, 0.08);
            color: #fff;
        }

        main {
            display: grid;
            grid-template-rows: auto 1fr;
            min-width: 0;
        }

        header {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 28px;
            background: rgba(255, 255, 255, 0.86);
            border-bottom: 1px solid var(--border);
            backdrop-filter: blur(12px);
        }

        header strong {
            font-size: 18px;
        }

        .status {
            color: var(--emerald);
            font-size: 13px;
            font-weight: 700;
        }

        .content {
            padding: 32px;
        }

        .hero {
            display: grid;
            gap: 22px;
            max-width: 1120px;
        }

        h1 {
            margin: 0;
            font-size: clamp(34px, 5vw, 64px);
            line-height: 1.02;
            letter-spacing: 0;
            max-width: 820px;
        }

        .lede {
            max-width: 760px;
            color: var(--muted);
            font-size: 18px;
            line-height: 1.7;
        }

        .actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .button {
            display: inline-flex;
            align-items: center;
            min-height: 44px;
            padding: 0 18px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 14px;
            text-decoration: none;
        }

        .primary {
            background: var(--emerald);
            color: white;
        }

        .secondary {
            border: 1px solid var(--border);
            color: var(--emerald);
            background: white;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
            margin-top: 28px;
        }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 18px;
        }

        .metric {
            color: var(--emerald);
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 6px;
        }

        .label {
            color: var(--muted);
            font-size: 13px;
            line-height: 1.45;
        }

        @media (max-width: 860px) {
            .shell {
                grid-template-columns: 1fr;
            }

            aside {
                display: none;
            }

            header,
            .content {
                padding-left: 18px;
                padding-right: 18px;
            }

            .grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 520px) {
            .grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="shell">
        <aside>
            <div class="brand">SODARS</div>
            <div class="domain">{{ $domain }}</div>
            <nav aria-label="Portal modules">
                <span class="active">Dashboard</span>
                <span>Locations</span>
                <span>Providers</span>
                <span>Inventory</span>
                <span>Campaigns</span>
                <span>Bookings</span>
                <span>Finance</span>
                <span>Reports</span>
                <span>Settings</span>
            </nav>
        </aside>
        <main>
            <header>
                <strong>{{ $portal }}</strong>
                <span class="status">Sprint 1 shell online</span>
            </header>
            <section class="content">
                <div class="hero">
                    <h1>Outdoor advertising operations, booking, and marketplace control center.</h1>
                    <p class="lede">
                        The Sprint 1 foundation is wired for Laravel 12, Sanctum API authentication,
                        Spatie RBAC, the SODARS MySQL schema, location taxonomy, and portal entry shells.
                    </p>
                    <div class="actions">
                        <a class="button primary" href="/api/health">API Health</a>
                        <a class="button secondary" href="/api/locations/countries">Location API</a>
                    </div>
                    <div class="grid">
                        <div class="card">
                            <div class="metric">49</div>
                            <div class="label">Core production tables represented in the schema migration.</div>
                        </div>
                        <div class="card">
                            <div class="metric">30m</div>
                            <div class="label">Configured booking hold window for the next sprint engine.</div>
                        </div>
                        <div class="card">
                            <div class="metric">16</div>
                            <div class="label">Permission modules seeded for Admin, Branch, Provider, and Agent roles.</div>
                        </div>
                        <div class="card">
                            <div class="metric">4</div>
                            <div class="label">Portal shells available: Website, Admin, Business, and Agents.</div>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
