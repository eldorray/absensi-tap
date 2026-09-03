<script module lang="ts">
    import { dashboard } from '@/routes';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    };
</script>

<script lang="ts">
    import { page } from '@inertiajs/svelte';
    import CalendarCheck from 'lucide-svelte/icons/calendar-check';
    import CalendarDays from 'lucide-svelte/icons/calendar-days';
    import ChevronLeft from 'lucide-svelte/icons/chevron-left';
    import ChevronRight from 'lucide-svelte/icons/chevron-right';
    import FileSpreadsheet from 'lucide-svelte/icons/file-spreadsheet';
    import FileText from 'lucide-svelte/icons/file-text';
    import Flame from 'lucide-svelte/icons/flame';
    import HandCoins from 'lucide-svelte/icons/hand-coins';
    import Layers from 'lucide-svelte/icons/layers';
    import PiggyBank from 'lucide-svelte/icons/piggy-bank';
    import Receipt from 'lucide-svelte/icons/receipt';
    import Trophy from 'lucide-svelte/icons/trophy';
    import Users from 'lucide-svelte/icons/users';
    import AppHead from '@/components/AppHead.svelte';

    const user = $derived(page.props.auth.user);
    const firstName = $derived(user.name.split(' ')[0]);

    const now = new Date();
    const monthStart = new Date(now.getFullYear(), now.getMonth(), 1);
    const monthEnd = new Date(now.getFullYear(), now.getMonth() + 1, 0);

    const rupiah = (value: number) =>
        'Rp ' + new Intl.NumberFormat('id-ID').format(value);

    const long = new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
    const short = new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'short',
    });
    const monthLabel = new Intl.DateTimeFormat('id-ID', {
        month: 'long',
        year: 'numeric',
    }).format(now);
    const weekday = new Intl.DateTimeFormat('id-ID', { weekday: 'short' });

    const greeting =
        now.getHours() < 11
            ? 'Selamat pagi'
            : now.getHours() < 15
              ? 'Selamat siang'
              : now.getHours() < 19
                ? 'Selamat sore'
                : 'Selamat malam';

    /* A week centred on today, the way the reference strip reads. */
    const strip = Array.from({ length: 7 }, (_, index) => {
        const date = new Date(
            now.getFullYear(),
            now.getMonth(),
            now.getDate() - 3 + index,
        );

        return {
            date,
            label: weekday.format(date).toUpperCase(),
            day: date.getDate(),
            today: index === 3,
        };
    });

    // ponytail: demo numbers live here until real models exist, then they move to a controller prop.
    const stats = {
        transactions: 54,
        lastEntry: short.format(
            new Date(now.getFullYear(), now.getMonth(), 13),
        ),
        streak: 0,
        longestStreak: 29,
        recordedDays: 13,
        balance: 9894500,
        wallets: 3,
        categories: 16,
        income: 0,
        expense: 6702000,
        savings: 0,
        expenseTrend: [12, 30, 18, 44, 26, 62, 38, 51, 22, 34, 19, 28],
        transactionTrend: [8, 14, 6, 17, 11, 20, 9, 13, 16, 7, 12, 5],
    };

    /** Sparkline points for a 120x36 box. */
    const points = (values: number[]): string => {
        const max = Math.max(...values, 1);
        const step = 120 / (values.length - 1);

        return values
            .map((value, index) => `${index * step},${36 - (value / max) * 32}`)
            .join(' L');
    };

    const line = (values: number[]): string => `M${points(values)}`;
    const area = (values: number[]): string =>
        `M0,36 L${points(values)} L120,36 Z`;
</script>

<AppHead title="Dashboard" />

<div class="mx-auto flex w-full max-w-7xl flex-col gap-4 px-4 py-5 sm:px-6">
    <!-- Hero -->
    <section class="hero">
        <svg class="hero-art" viewBox="0 0 420 200" aria-hidden="true">
            <ellipse
                cx="330"
                cy="60"
                rx="110"
                ry="52"
                fill="currentColor"
                opacity="0.18"
            />
            <ellipse
                cx="250"
                cy="150"
                rx="70"
                ry="46"
                fill="currentColor"
                opacity="0.12"
            />
            <rect
                x="150"
                y="18"
                width="180"
                height="62"
                rx="31"
                fill="none"
                stroke="#fff"
                stroke-width="3"
                opacity="0.75"
            />
            <circle
                cx="368"
                cy="52"
                r="24"
                fill="none"
                stroke="#fff"
                stroke-width="3"
                opacity="0.75"
            />
            <path
                d="M352 76 A52 52 0 0 0 316 132"
                fill="none"
                stroke="currentColor"
                stroke-width="4"
                opacity="0.55"
            />
        </svg>

        <div
            class="relative flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between"
        >
            <div>
                <p
                    class="flex items-center gap-2 text-[0.7rem] font-semibold tracking-[0.16em] uppercase opacity-80"
                >
                    <Users class="size-4" aria-hidden="true" />
                    {user.name}
                </p>
                <h1 class="g-display mt-3 text-[clamp(1.9rem,4vw,2.75rem)]">
                    {greeting}, {firstName}
                </h1>
                <p class="mt-2 max-w-[46ch] text-sm opacity-85">
                    Mulai hari dengan catatan yang rapi &middot; {long.format(
                        monthStart,
                    )}
                    - {long.format(monthEnd)}
                </p>
            </div>

            <div class="flex shrink-0 gap-3">
                <button type="button" class="hero-btn">
                    <FileSpreadsheet class="size-4" aria-hidden="true" />
                    Excel
                </button>
                <button type="button" class="hero-btn">
                    <FileText class="size-4" aria-hidden="true" />
                    PDF
                </button>
            </div>
        </div>
    </section>

    <!-- Calendar, streaks, balance -->
    <div class="grid gap-4 lg:grid-cols-12">
        <section class="card lg:col-span-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <span class="chip">
                    {stats.streak > 0
                        ? `Streak ${stats.streak} hari`
                        : 'Belum ada streak'}
                </span>
                <p class="text-xs text-muted-foreground">
                    {stats.transactions} transaksi &middot; Terakhir:
                    <span class="font-semibold text-foreground"
                        >{stats.lastEntry}</span
                    >
                </p>
            </div>

            <div class="mt-6 flex items-center justify-center gap-6">
                <button
                    type="button"
                    class="nav-btn"
                    aria-label="Bulan sebelumnya"
                >
                    <ChevronLeft class="size-4" aria-hidden="true" />
                </button>
                <p class="text-base font-semibold capitalize">{monthLabel}</p>
                <button
                    type="button"
                    class="nav-btn"
                    aria-label="Bulan berikutnya"
                >
                    <ChevronRight class="size-4" aria-hidden="true" />
                </button>
            </div>

            <div class="mt-5 grid grid-cols-7 gap-1 text-center">
                {#each strip as day (day.day)}
                    <div>
                        <p
                            class="text-[0.65rem] font-semibold tracking-wider text-muted-foreground uppercase"
                        >
                            {day.label}
                        </p>
                        <p class="day" class:day-today={day.today}>{day.day}</p>
                    </div>
                {/each}
            </div>

            <button
                type="button"
                class="mt-5 inline-flex items-center justify-center gap-2 self-center text-sm font-medium text-primary"
            >
                <CalendarDays class="size-4" aria-hidden="true" />
                Lihat satu bulan
            </button>
        </section>

        <section class="flex flex-col gap-3 lg:col-span-3">
            <article class="card flex-row items-center gap-4 py-4">
                <span class="icon-dot"
                    ><Flame class="size-4" aria-hidden="true" /></span
                >
                <div>
                    <p class="meta">Streak berjalan</p>
                    <p class="text-2xl font-semibold">{stats.streak} hari</p>
                </div>
            </article>
            <article class="card flex-row items-center gap-4 py-4">
                <span class="icon-dot"
                    ><Trophy class="size-4" aria-hidden="true" /></span
                >
                <div>
                    <p class="meta">Streak terpanjang</p>
                    <p class="text-2xl font-semibold">
                        {stats.longestStreak} hari
                    </p>
                </div>
            </article>
            <article class="card flex-row items-center gap-4 py-4">
                <span class="icon-dot"
                    ><CalendarCheck class="size-4" aria-hidden="true" /></span
                >
                <div>
                    <p class="meta">Hari tercatat bulan ini</p>
                    <p class="text-2xl font-semibold">
                        {stats.recordedDays} / {monthEnd.getDate()}
                    </p>
                </div>
            </article>
        </section>

        <section class="balance lg:col-span-4">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm font-semibold tracking-[0.1em] uppercase">
                        Fino
                    </p>
                    <p class="mt-1 text-sm opacity-80">Total saldo</p>
                </div>
                <Layers class="size-6 opacity-70" aria-hidden="true" />
            </div>

            <p
                class="mt-10 text-right text-[clamp(1.75rem,3.4vw,2.4rem)] font-semibold tracking-tight"
            >
                {rupiah(stats.balance)}
            </p>

            <p class="mt-6 font-mono text-sm tracking-[0.3em] opacity-70">
                &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull;
                &bull;&bull;&bull;&bull; 0000
            </p>

            <div class="mt-3 flex items-end justify-between text-sm">
                <span class="font-semibold">Semua dompet</span>
                <span class="opacity-80">{stats.wallets} dompet aktif</span>
            </div>
        </section>
    </div>

    <!-- Totals -->
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article class="card">
            <span class="icon-dot"
                ><HandCoins class="size-4" aria-hidden="true" /></span
            >
            <p class="meta mt-4">Pemasukan</p>
            <p class="amount">{rupiah(stats.income)}</p>
            <div class="foot">Dari tambah saldo dompet</div>
        </article>

        <article class="card">
            <span class="icon-dot"
                ><Flame class="size-4" aria-hidden="true" /></span
            >
            <p class="meta mt-4">Pengeluaran</p>
            <p class="amount">{rupiah(stats.expense)}</p>
            <svg
                class="mt-3 h-9 w-full"
                viewBox="0 0 120 36"
                preserveAspectRatio="none"
                aria-hidden="true"
            >
                <path
                    d={area(stats.expenseTrend)}
                    fill="var(--g-blue)"
                    opacity="0.25"
                />
                <path
                    d={line(stats.expenseTrend)}
                    fill="none"
                    stroke="var(--g-blue)"
                    stroke-width="2"
                />
            </svg>
            <div class="foot">Semua catatan periode ini</div>
        </article>

        <article class="card">
            <span class="icon-dot"
                ><Receipt class="size-4" aria-hidden="true" /></span
            >
            <p class="meta mt-4">Transaksi</p>
            <p class="amount">{stats.transactions}</p>
            <svg
                class="mt-3 h-9 w-full"
                viewBox="0 0 120 36"
                preserveAspectRatio="none"
                aria-hidden="true"
            >
                {#each stats.transactionTrend as value, index (index)}
                    <rect
                        x={index * 10}
                        y={36 - (value / 20) * 32}
                        width="6"
                        height={(value / 20) * 32}
                        rx="2"
                        fill="var(--g-blue)"
                    />
                {/each}
            </svg>
            <div class="foot">
                {stats.wallets} dompet &middot; {stats.categories} kategori
            </div>
        </article>

        <article class="card">
            <span class="icon-dot"
                ><PiggyBank class="size-4" aria-hidden="true" /></span
            >
            <p class="meta mt-4">Tabungan</p>
            <p class="amount">{rupiah(stats.savings)}</p>
            <div class="foot">Kategori Tabungan</div>
        </article>
    </div>
</div>

<style>
    .hero {
        position: relative;
        overflow: hidden;
        border-radius: 28px;
        padding: clamp(1.5rem, 3vw, 2.25rem);
        background: linear-gradient(
            115deg,
            var(--g-lime) 0%,
            var(--g-lime-2) 100%
        );
        color: var(--g-lime-ink);
    }

    .hero-art {
        position: absolute;
        top: 0;
        right: 0;
        height: 100%;
        color: var(--g-lime-ink);
        pointer-events: none;
    }

    .hero-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        border-radius: 999px;
        background: var(--g-band);
        padding: 0.75rem 1.5rem;
        font-size: 0.9375rem;
        font-weight: 600;
        color: var(--g-band-ink);
        transition: transform 0.3s var(--g-emphasized);
    }

    .hero-btn:hover {
        transform: translateY(-2px);
    }

    .card {
        display: flex;
        flex-direction: column;
        border-radius: 28px;
        background: var(--card);
        padding: 1.25rem 1.5rem;
    }

    .balance {
        display: flex;
        flex-direction: column;
        border-radius: 28px;
        padding: 1.5rem;
        background: linear-gradient(
            150deg,
            var(--g-lime) 0%,
            var(--g-lime-2) 100%
        );
        color: var(--g-lime-ink);
    }

    .chip {
        align-self: flex-start;
        border-radius: 999px;
        background: color-mix(in srgb, var(--g-ink) 8%, transparent);
        padding: 0.4rem 0.9rem;
        font-size: 0.8125rem;
        font-weight: 500;
    }

    .nav-btn {
        display: grid;
        place-items: center;
        border-radius: 999px;
        padding: 0.5rem;
        color: var(--g-ink-2);
    }

    .nav-btn:hover {
        background: color-mix(in srgb, var(--g-ink) 8%, transparent);
    }

    .day {
        display: grid;
        place-items: center;
        margin: 0.4rem auto 0;
        width: 2.25rem;
        height: 2.25rem;
        border-radius: 999px;
        background: color-mix(in srgb, var(--g-ink) 6%, transparent);
        font-size: 0.9375rem;
        font-weight: 500;
    }

    .day-today {
        background: var(--g-lime);
        color: var(--g-lime-ink);
        font-weight: 700;
    }

    .icon-dot {
        display: grid;
        place-items: center;
        width: 2.5rem;
        height: 2.5rem;
        flex-shrink: 0;
        border-radius: 999px;
        background: color-mix(in srgb, var(--g-ink) 8%, transparent);
        color: var(--g-blue-ink-2);
    }

    .meta {
        font-size: 0.7rem;
        font-weight: 600;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: var(--g-ink-2);
    }

    .amount {
        margin-top: 0.35rem;
        font-size: clamp(1.5rem, 2.6vw, 1.875rem);
        font-weight: 600;
        letter-spacing: -0.02em;
    }

    .foot {
        margin-top: auto;
        padding-top: 0.85rem;
        font-size: 0.8125rem;
        color: var(--g-ink-2);
        border-top: 1px solid color-mix(in srgb, var(--g-ink) 10%, transparent);
    }
</style>
