<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import { toUrl } from '@/lib/utils';
    import { dashboard, login, register } from '@/routes';
    import ArrowRight from 'lucide-svelte/icons/arrow-right';
    import Check from 'lucide-svelte/icons/check';
    import Copy from 'lucide-svelte/icons/copy';
    import Fingerprint from 'lucide-svelte/icons/fingerprint';
    import KeyRound from 'lucide-svelte/icons/key-round';
    import MailCheck from 'lucide-svelte/icons/mail-check';
    import ShieldCheck from 'lucide-svelte/icons/shield-check';

    const auth = $derived(page.props.auth);

    const installCommand =
        'composer create-project laravel/svelte-starter-kit my-app';

    let copied = $state(false);
    let copyFailed = $state(false);
    let resetTimer: ReturnType<typeof setTimeout> | undefined;

    async function copyInstallCommand(): Promise<void> {
        clearTimeout(resetTimer);

        try {
            await navigator.clipboard.writeText(installCommand);
            copied = true;
            copyFailed = false;
        } catch {
            copied = false;
            copyFailed = true;
        }

        resetTimer = setTimeout(() => {
            copied = false;
            copyFailed = false;
        }, 2400);
    }

    type Token = [text: string, tone?: 'key' | 'str' | 'fn' | 'tag'];

    const routeSnippet: Token[][] = [
        [
            ['Route', 'fn'],
            ['::'],
            ['inertia', 'key'],
            ['('],
            ["'/'", 'str'],
            [', '],
            ["'Welcome'", 'str'],
            [')'],
        ],
        [['    ->'], ['name', 'key'], ['('], ["'home'", 'str'], [');']],
        [],
        [
            ['Route', 'fn'],
            ['::'],
            ['middleware', 'key'],
            ['(['],
            ["'auth'", 'str'],
            [', '],
            ["'verified'", 'str'],
            ['])'],
        ],
        [['    ->'], ['group', 'key'], ['(function () {']],
        [
            ['        Route', 'fn'],
            ['::'],
            ['inertia', 'key'],
            ['('],
            ["'dashboard'", 'str'],
            [', '],
            ["'Dashboard'", 'str'],
            [');'],
        ],
        [['    });']],
    ];

    const pageSnippet: Token[][] = [
        [['<script', 'tag'], [' lang'], ['='], ['"ts"', 'str'], ['>', 'tag']],
        [
            ['  import', 'key'],
            [' { Link } '],
            ['from', 'key'],
            [' '],
            ["'@inertiajs/svelte'", 'str'],
            [';'],
        ],
        [
            ['  import', 'key'],
            [' { dashboard } '],
            ['from', 'key'],
            [' '],
            ["'@/routes'", 'str'],
            [';'],
        ],
        [],
        [['  let', 'key'], [' { user } = '], ['$props', 'fn'], ['();']],
        [['<\/script>', 'tag']],
        [],
        [
            ['<Link', 'tag'],
            [' href'],
            ['='],
            ['{dashboard().url}', 'str'],
            ['>', 'tag'],
        ],
        [['  {user.name}']],
        [['</Link>', 'tag']],
    ];

    const stack = [
        ['Laravel', '13'],
        ['Svelte', '5'],
        ['Inertia', '3'],
        ['Tailwind', '4'],
        ['Vite', '8'],
        ['Pest', '5'],
    ];

    const authFeatures = [
        { icon: KeyRound, label: 'Login, registration, password reset' },
        {
            icon: MailCheck,
            label: 'Email verification and password confirmation',
        },
        {
            icon: ShieldCheck,
            label: 'Two-factor with QR setup and recovery codes',
        },
        { icon: Fingerprint, label: 'Passkeys via @laravel/passkeys' },
    ];
</script>

<AppHead title="Laravel + Svelte starter kit" />

<div class="pixel">
    <a class="skip" href="#main">Skip to content</a>

    <header class="nav">
        <div class="shell flex h-16 items-center justify-between gap-6 sm:h-20">
            <a
                href="/"
                class="brand"
                aria-label="Laravel and Svelte starter kit, home"
            >
                <svg viewBox="0 0 32 32" aria-hidden="true" class="h-8 w-8">
                    <rect width="32" height="32" rx="11" fill="currentColor" />
                    <path
                        d="M10.5 16h11m-4.4-4.4 4.4 4.4-4.4 4.4"
                        fill="none"
                        stroke="var(--g-bg)"
                        stroke-width="2.6"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                </svg>
                <span>Laravel <span class="brand-thin">+</span> Svelte</span>
            </a>

            <nav class="flex items-center gap-1 sm:gap-2">
                {#if auth.user}
                    <Link href={toUrl(dashboard())} class="pill pill-filled">
                        Dashboard
                    </Link>
                {:else}
                    <Link href={toUrl(login())} class="pill pill-ghost"
                        >Log in</Link
                    >
                    <Link href={toUrl(register())} class="pill pill-filled">
                        Get started
                    </Link>
                {/if}
            </nav>
        </div>
    </header>

    <main id="main">
        <section class="shell pt-14 pb-8 sm:pt-24 sm:pb-14">
            <h1 class="g-display display rise">
                Start at the interesting part.
            </h1>

            <p class="lead rise rise-1">
                A Laravel 13 and Svelte 5 starter kit where login, passkeys,
                two-factor, typed routes, and a passing test suite already exist
                on the first commit.
            </p>

            <div class="rise rise-2 mt-9 flex flex-wrap items-center gap-3">
                <Link href={toUrl(register())} class="pill pill-filled pill-lg">
                    Get started
                    <ArrowRight class="size-[18px]" aria-hidden="true" />
                </Link>
                <a
                    href="https://laravel.com/docs/starter-kits"
                    target="_blank"
                    rel="noreferrer"
                    class="pill pill-outline pill-lg"
                >
                    Read the docs
                </a>
            </div>

            <div class="rise rise-3 stage mt-14 sm:mt-20">
                <div
                    class="grid min-w-0 gap-4 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]"
                >
                    <figure class="code min-w-0">
                        <figcaption>routes/web.php</figcaption>
                        <pre aria-hidden="true"><code
                                >{#each routeSnippet as line}<span class="line"
                                        >{#each line as [text, tone]}<span
                                                class={tone}>{text}</span
                                            >{/each}</span
                                    >{/each}</code
                            ></pre>
                    </figure>

                    <figure class="code min-w-0">
                        <figcaption>
                            resources/js/pages/Dashboard.svelte
                        </figcaption>
                        <pre aria-hidden="true"><code
                                >{#each pageSnippet as line}<span class="line"
                                        >{#each line as [text, tone]}<span
                                                class={tone}>{text}</span
                                            >{/each}</span
                                    >{/each}</code
                            ></pre>
                    </figure>
                </div>
                <p class="sr-only">
                    Two source files: a Laravel route file rendering Inertia
                    pages, and a Svelte page importing a typed route helper
                    generated by Wayfinder.
                </p>
            </div>
        </section>

        <section class="shell pb-16 sm:pb-24" aria-label="Versions in this kit">
            <ul class="stack-row">
                {#each stack as [name, version]}
                    <li>
                        <span>{name}</span>
                        <span class="ver">{version}</span>
                    </li>
                {/each}
            </ul>
        </section>

        <section class="shell pb-16 sm:pb-24">
            <h2 class="g-display section-title">
                Everything you would have built first.
            </h2>

            <div class="tiles">
                <article class="g-tile g-tone-blue tile-tall">
                    <h3>Auth that is already hard to get wrong</h3>
                    <p>
                        Laravel Fortify handles the whole flow server-side. The
                        Svelte screens are yours to restyle, the security rules
                        are not yours to reinvent.
                    </p>
                    <ul class="feature-list">
                        {#each authFeatures as feature}
                            <li>
                                <feature.icon
                                    class="size-[18px] shrink-0"
                                    stroke-width={1.75}
                                    aria-hidden="true"
                                />
                                <span>{feature.label}</span>
                            </li>
                        {/each}
                    </ul>
                </article>

                <article class="g-tile g-tone-green">
                    <h3>Routes with types attached</h3>
                    <p>
                        Wayfinder generates a function per controller action, so
                        a renamed route breaks the build instead of production.
                    </p>
                    <p class="mono-line">
                        import &#123; dashboard &#125; from '@/routes'
                    </p>
                </article>

                <article class="g-tile g-tone-yellow">
                    <h3>Runes, not stores</h3>
                    <p>
                        Svelte 5 throughout: <code>$state</code>,
                        <code>$derived</code>, and <code>$props</code> in every page
                        and layout, with no legacy reactivity left behind.
                    </p>
                </article>

                <article class="g-tile g-tone-plain tile-wide">
                    <h3>Inertia 3, using the parts that matter</h3>
                    <p>
                        Deferred props for slow panels, prefetching and instant
                        visits for navigation that lands before you notice it,
                        and optimistic updates that roll themselves back when
                        the server disagrees.
                    </p>
                </article>

                <article class="g-tile g-tone-red">
                    <h3>Tests that already pass</h3>
                    <p>
                        Pest 5 covers registration, login, two-factor, passkeys,
                        and profile updates. Run <code>php artisan test</code> before
                        you write a line.
                    </p>
                </article>

                <article class="g-tile g-tone-plain">
                    <h3>Tailwind 4 and Vite 8</h3>
                    <p>
                        CSS-first theme tokens, no config file, and a dev server
                        that reloads Svelte and PHP together through
                        <code>composer run dev</code>.
                    </p>
                </article>
            </div>
        </section>

        <section class="band">
            <div class="shell py-16 text-center sm:py-24">
                <h2 class="g-display">
                    From empty folder to signed-in dashboard.
                </h2>
                <p>
                    One command scaffolds the app, the auth screens, the typed
                    routes, and the test suite. The next command runs it.
                </p>

                <div class="command">
                    <code>{installCommand}</code>
                    <button type="button" onclick={copyInstallCommand}>
                        {#if copied}
                            <Check class="size-4" aria-hidden="true" />
                            Copied
                        {:else}
                            <Copy class="size-4" aria-hidden="true" />
                            Copy
                        {/if}
                    </button>
                </div>

                <p class="status" role="status">
                    {#if copied}
                        Command copied to your clipboard.
                    {:else if copyFailed}
                        Could not reach the clipboard. Select the command and
                        copy it manually.
                    {/if}
                </p>
            </div>
        </section>
    </main>

    <footer class="footer">
        <div
            class="shell flex flex-col gap-4 py-10 sm:flex-row sm:items-center sm:justify-between"
        >
            <p>Built on Laravel 13, Svelte 5, and Inertia 3.</p>
            <nav class="flex flex-wrap gap-x-6 gap-y-2">
                <a
                    href="https://laravel.com/docs"
                    target="_blank"
                    rel="noreferrer"
                >
                    Laravel docs
                </a>
                <a
                    href="https://svelte.dev/docs"
                    target="_blank"
                    rel="noreferrer"
                >
                    Svelte docs
                </a>
                <a
                    href="https://inertiajs.com"
                    target="_blank"
                    rel="noreferrer"
                >
                    Inertia docs
                </a>
                <a
                    href="https://cloud.laravel.com"
                    target="_blank"
                    rel="noreferrer"
                >
                    Deploy
                </a>
            </nav>
        </div>
    </footer>
</div>

<style>
    .pixel {
        min-height: 100vh;
    }

    .shell {
        margin-inline: auto;
        width: 100%;
        max-width: 1160px;
        padding-inline: 1.25rem;
    }

    @media (min-width: 640px) {
        .shell {
            padding-inline: 2rem;
        }
    }

    .skip {
        position: absolute;
        left: -9999px;
        top: 0.75rem;
        z-index: 20;
        border-radius: 999px;
        background: var(--g-blue);
        color: var(--g-on-blue);
        padding: 0.6rem 1.25rem;
        font-size: 0.875rem;
        font-weight: 600;
    }

    .skip:focus {
        left: 1.25rem;
    }

    /* Nav */

    .nav {
        position: sticky;
        top: 0;
        z-index: 10;
        background: var(--g-bg);
        border-bottom: 1px solid var(--g-line);
    }

    .brand {
        display: inline-flex;
        align-items: center;
        gap: 0.65rem;
        color: var(--g-blue);
        font-size: 1.0625rem;
        font-weight: 700;
        letter-spacing: -0.015em;
    }

    .brand span {
        color: var(--g-ink);
    }

    @media (max-width: 430px) {
        .brand > span {
            display: none;
        }
    }

    @media (max-width: 640px) {
        .code pre {
            font-size: 0.75rem;
        }
    }

    .brand-thin {
        color: var(--g-ink-2);
        font-weight: 400;
    }

    /* Pills */

    .pixel :global(.pill) {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        border-radius: 999px;
        padding: 0.6rem 1.15rem;
        font-size: 0.9375rem;
        font-weight: 600;
        line-height: 1.2;
        letter-spacing: -0.005em;
        white-space: nowrap;
        border: 1px solid transparent;
        transition:
            background-color 0.25s var(--g-emphasized),
            border-color 0.25s var(--g-emphasized),
            transform 0.25s var(--g-emphasized);
    }

    .pixel :global(.pill-lg) {
        padding: 0.9rem 1.7rem;
        font-size: 1rem;
    }

    .pixel :global(.pill-filled) {
        background: var(--g-blue);
        color: var(--g-on-blue);
        box-shadow: var(--g-shadow);
    }

    .pixel :global(.pill-filled:hover) {
        background: color-mix(in srgb, var(--g-blue) 88%, var(--g-ink));
        transform: translateY(-1px);
    }

    .pixel :global(.pill-outline) {
        border-color: var(--g-line);
        color: var(--g-ink);
    }

    .pixel :global(.pill-outline:hover) {
        background: var(--g-surface);
        border-color: var(--g-ink-2);
    }

    .pixel :global(.pill-ghost) {
        color: var(--g-ink);
    }

    .pixel :global(.pill-ghost:hover) {
        background: var(--g-surface);
    }

    /* Hero */

    .display {
        max-width: 16ch;
        font-size: clamp(2.75rem, 8.5vw, 5.25rem);
        line-height: 0.98;
        letter-spacing: -0.035em;
    }

    .lead {
        margin-top: 1.5rem;
        max-width: 54ch;
        color: var(--g-ink-2);
        font-size: clamp(1.0625rem, 1.6vw, 1.3125rem);
        font-weight: 300;
        line-height: 1.55;
        text-wrap: pretty;
    }

    .rise {
        opacity: 1;
        transform: translateY(0);
        transition:
            opacity 0.7s var(--g-emphasized),
            transform 0.7s var(--g-emphasized);
    }

    .rise-1 {
        transition-delay: 90ms;
    }
    .rise-2 {
        transition-delay: 180ms;
    }
    .rise-3 {
        transition-delay: 280ms;
    }

    @media (prefers-reduced-motion: no-preference) {
        @starting-style {
            .rise {
                opacity: 0;
                transform: translateY(18px);
            }

            .stage {
                transform: scale(0.97);
            }
        }
    }

    /* Code stage */

    .stage {
        background: var(--g-blue-c);
        border-radius: 40px;
        padding: clamp(0.9rem, 2.5vw, 1.75rem);
        transform: scale(1);
        transition: transform 0.7s var(--g-emphasized) 280ms;
    }

    .code {
        min-width: 0;
        background: var(--g-bg);
        border-radius: 26px;
        overflow: hidden;
        box-shadow: var(--g-shadow);
    }

    .code figcaption {
        border-bottom: 1px solid var(--g-line);
        color: var(--g-ink-2);
        font-family: var(--font-mono);
        font-size: 0.75rem;
        letter-spacing: 0.02em;
        padding: 0.85rem 1.25rem;
    }

    .code pre {
        min-width: 0;
        overflow-x: auto;
        overscroll-behavior-x: contain;
        padding: 1.25rem;
        font-family: var(--font-mono);
        font-size: 0.8125rem;
        line-height: 1.85;
        color: var(--g-ink);
        font-variant-numeric: tabular-nums;
    }

    .code .line {
        display: block;
        min-height: 1.85em;
        white-space: pre;
    }

    .code .key {
        color: var(--g-blue);
        font-weight: 500;
    }
    .code .str {
        color: var(--g-green-ink-2);
    }
    .code .fn {
        color: var(--g-red-ink-2);
    }
    .code .tag {
        color: var(--g-yellow-ink-2);
    }
    /* Version strip */

    .stack-row {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem 0.75rem;
    }

    .stack-row li {
        display: inline-flex;
        align-items: baseline;
        gap: 0.45rem;
        border: 1px solid var(--g-line);
        border-radius: 999px;
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--g-ink-2);
    }

    .stack-row .ver {
        color: var(--g-ink);
        font-weight: 700;
        font-variant-numeric: tabular-nums;
    }

    /* Tiles */

    .section-title {
        max-width: 20ch;
        margin-bottom: 2.5rem;
        font-size: clamp(1.875rem, 4.5vw, 3rem);
    }

    .tiles {
        display: grid;
        gap: 0.875rem;
        grid-template-columns: minmax(0, 1fr);
    }

    @media (min-width: 700px) {
        .tiles {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .tile-wide {
            grid-column: span 2;
        }
    }

    @media (min-width: 1024px) {
        .tiles {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .tile-tall {
            grid-row: span 2;
        }

        .tile-wide {
            grid-column: span 2;
        }
    }

    .tile-tall h3 {
        font-size: clamp(1.5rem, 2.4vw, 1.875rem);
    }

    .feature-list {
        padding-top: 0.5rem;
        display: flex;
        flex-direction: column;
        gap: 0.85rem;
        font-size: 0.9375rem;
        line-height: 1.4;
    }

    .feature-list li {
        display: flex;
        align-items: flex-start;
        gap: 0.7rem;
    }

    .mono-line {
        font-family: var(--font-mono);
        font-size: 0.8125rem !important;
        background: color-mix(in srgb, var(--g-green-ink) 10%, transparent);
        border-radius: 12px;
        padding: 0.65rem 0.85rem;
        white-space: pre-wrap;
        overflow-wrap: anywhere;
    }

    /* Band */

    .band {
        background: var(--g-band);
        color: var(--g-band-ink);
    }

    .band h2 {
        margin-inline: auto;
        max-width: 18ch;
        font-size: clamp(1.875rem, 4.5vw, 3.25rem);
    }

    .band > div > p {
        margin-inline: auto;
        margin-top: 1.25rem;
        max-width: 52ch;
        color: var(--g-band-ink-2);
        font-size: 1.0625rem;
        font-weight: 300;
        line-height: 1.6;
        text-wrap: pretty;
    }

    .command {
        margin: 2.5rem auto 0;
        display: flex;
        width: fit-content;
        max-width: min(100%, 40rem);
        align-items: center;
        gap: 0.75rem;
        border-radius: 999px;
        background: var(--g-band-field);
        padding: 0.4rem 0.4rem 0.4rem 1.4rem;
    }

    .command code {
        display: block;
        min-width: 0;
        overflow-x: auto;
        white-space: nowrap;
        font-family: var(--font-mono);
        font-size: 0.8125rem;
        color: var(--g-band-ink);
        text-align: left;
    }

    .command button {
        display: inline-flex;
        flex-shrink: 0;
        align-items: center;
        gap: 0.45rem;
        border-radius: 999px;
        background: var(--g-band-ink);
        color: var(--g-band);
        padding: 0.6rem 1.1rem;
        font-size: 0.875rem;
        font-weight: 600;
        cursor: pointer;
        transition: opacity 0.25s var(--g-emphasized);
    }

    .command button:hover {
        opacity: 0.88;
    }

    @media (max-width: 640px) {
        .command {
            width: 100%;
            flex-direction: column;
            align-items: stretch;
            gap: 0.85rem;
            border-radius: 28px;
            padding: 1.1rem;
        }

        .command code {
            white-space: pre-wrap;
            overflow-wrap: anywhere;
            text-align: center;
        }

        .command button {
            justify-content: center;
        }
    }

    .band .status {
        margin-top: 1rem;
        min-height: 1.25rem;
        color: var(--g-band-ink-2);
        font-size: 0.875rem;
    }

    /* Footer */

    .footer {
        border-top: 1px solid var(--g-line);
        color: var(--g-ink-2);
        font-size: 0.875rem;
    }

    .footer a:hover {
        color: var(--g-ink);
        text-decoration: underline;
        text-underline-offset: 4px;
    }

    @media (prefers-reduced-motion: reduce) {
        .pixel :global(*) {
            transition-duration: 0.01ms !important;
        }
    }
</style>
