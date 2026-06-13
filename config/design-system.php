<?php
/**
 * config/design-system.php
 * ─────────────────────────────────────────────────────────────────────────────
 * SINGLE SOURCE OF TRUTH for the Lyra Academy LMS design system.
 *
 * Usage (top of every page, before any HTML output):
 *   require_once __DIR__ . '/../config/design-system.php';
 *   // Then echo lms_head("Page Title"); inside <html>…</head> position.
 *
 * This file provides:
 *  • Unified Tailwind CSS configuration (tokens, spacing, typography, colours)
 *  • Role-based accent colour overrides (admin / instructor / student)
 *  • Shared base <style> block (icon rendering, scrollbar, transitions)
 *  • Helper functions:  lms_head(), lms_sidebar(), lms_topbar()
 * ─────────────────────────────────────────────────────────────────────────────
 */

require_once __DIR__ . '/csrf.php';

// ── Colour Palettes (role-aware accent layer) ─────────────────────────────────
$LMS_PALETTES = [
    // Material Design 3 base – shared across all roles
    'base' => [
        'primary'                    => '#003d9b',
        'primary-container'          => '#0052cc',
        'on-primary'                 => '#ffffff',
        'on-primary-container'       => '#c4d2ff',
        'primary-fixed'              => '#dae2ff',
        'primary-fixed-dim'          => '#b2c5ff',
        'on-primary-fixed'           => '#001848',
        'on-primary-fixed-variant'   => '#0040a2',
        'inverse-primary'            => '#b2c5ff',
        'secondary'                  => '#505f76',
        'secondary-container'        => '#d0e1fb',
        'on-secondary'               => '#ffffff',
        'on-secondary-container'     => '#54647a',
        'secondary-fixed'            => '#d3e4fe',
        'secondary-fixed-dim'        => '#b7c8e1',
        'on-secondary-fixed'         => '#0b1c30',
        'on-secondary-fixed-variant' => '#38485d',
        'tertiary'                   => '#3b4358',
        'tertiary-container'         => '#535a70',
        'on-tertiary'                => '#ffffff',
        'on-tertiary-container'      => '#cbd2ec',
        'tertiary-fixed'             => '#dae2fd',
        'tertiary-fixed-dim'         => '#bec6e0',
        'on-tertiary-fixed'          => '#131b2e',
        'on-tertiary-fixed-variant'  => '#3f465c',
        'error'                      => '#ba1a1a',
        'error-container'            => '#ffdad6',
        'on-error'                   => '#ffffff',
        'on-error-container'         => '#93000a',
        'surface'                    => '#f7f9fb',
        'surface-dim'                => '#d8dadc',
        'surface-bright'             => '#f7f9fb',
        'surface-container-lowest'   => '#ffffff',
        'surface-container-low'      => '#f2f4f6',
        'surface-container'          => '#eceef0',
        'surface-container-high'     => '#e6e8ea',
        'surface-container-highest'  => '#e0e3e5',
        'surface-variant'            => '#e0e3e5',
        'on-surface'                 => '#191c1e',
        'on-surface-variant'         => '#434654',
        'background'                 => '#f7f9fb',
        'on-background'              => '#191c1e',
        'outline'                    => '#737685',
        'outline-variant'            => '#c3c6d6',
        'inverse-surface'            => '#2d3133',
        'inverse-on-surface'         => '#eff1f3',
        'surface-tint'               => '#0c56d0',
    ],
    // Role accent overrides (merged on top of base)
    'admin'      => ['role-accent' => '#003d9b', 'role-accent-dim' => '#b2c5ff', 'role-label' => 'Admin Portal'],
    'instructor' => ['role-accent' => '#1a6b3c', 'role-accent-dim' => '#b7e4c7', 'role-label' => 'Instructor Portal'],
    'student'    => ['role-accent' => '#6a1a8c', 'role-accent-dim' => '#e3b8f7', 'role-label' => 'Student Portal'],
    'guest'      => ['role-accent' => '#5b4800', 'role-accent-dim' => '#f5e0a0', 'role-label' => 'Lyra Academy'],
];

// ── Tailwind Config Object (JSON) ─────────────────────────────────────────────
// Emitted once per page. Kept as PHP variable so it can be role-extended.
function lms_tailwind_config_json(string $role = 'admin'): string {
    global $LMS_PALETTES;
    $colors = array_merge($LMS_PALETTES['base'], $LMS_PALETTES[$role] ?? []);
    $colorsJson = json_encode($colors, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    return <<<JSON
    tailwind.config = {
        darkMode: "class",
        theme: {
            extend: {
                colors: $colorsJson,
                borderRadius: {
                    "DEFAULT": "0.25rem",
                    "sm":      "0.375rem",
                    "md":      "0.5rem",
                    "lg":      "0.625rem",
                    "xl":      "0.875rem",
                    "2xl":     "1rem",
                    "full":    "9999px"
                },
                spacing: {
                    "base":          "8px",
                    "xs":            "4px",
                    "sm":            "12px",
                    "md":            "24px",
                    "lg":            "40px",
                    "xl":            "64px",
                    "sidebar-width": "260px",
                    "topbar-height": "64px",
                    "container-max": "1280px"
                },
                fontFamily: {
                    "sans":     ["Inter", "system-ui", "sans-serif"],
                    "h1":       ["Inter"],
                    "h2":       ["Inter"],
                    "h3":       ["Inter"],
                    "body-lg":  ["Inter"],
                    "body-md":  ["Inter"],
                    "label-md": ["Inter"],
                    "label-sm": ["Inter"]
                },
                fontSize: {
                    "h1":       ["32px", {"lineHeight":"1.2",  "letterSpacing":"-0.02em", "fontWeight":"700"}],
                    "h2":       ["24px", {"lineHeight":"1.3",  "letterSpacing":"-0.01em", "fontWeight":"600"}],
                    "h3":       ["20px", {"lineHeight":"1.4",  "letterSpacing":"-0.01em", "fontWeight":"600"}],
                    "h4":       ["17px", {"lineHeight":"1.4",  "letterSpacing":"0",        "fontWeight":"600"}],
                    "body-lg":  ["16px", {"lineHeight":"1.6",  "letterSpacing":"0",        "fontWeight":"400"}],
                    "body-md":  ["14px", {"lineHeight":"1.5",  "letterSpacing":"0",        "fontWeight":"400"}],
                    "body-sm":  ["13px", {"lineHeight":"1.5",  "letterSpacing":"0",        "fontWeight":"400"}],
                    "label-md": ["12px", {"lineHeight":"1.4",  "letterSpacing":"0.02em",  "fontWeight":"500"}],
                    "label-sm": ["11px", {"lineHeight":"1.2",  "letterSpacing":"0.05em",  "fontWeight":"600"}]
                },
                boxShadow: {
                    "card":   "0 1px 3px 0 rgba(0,0,0,.06), 0 1px 2px -1px rgba(0,0,0,.06)",
                    "card-md":"0 4px 6px -1px rgba(0,0,0,.07), 0 2px 4px -2px rgba(0,0,0,.07)",
                    "card-lg":"0 10px 15px -3px rgba(0,0,0,.07), 0 4px 6px -4px rgba(0,0,0,.07)",
                    "modal":  "0 20px 25px -5px rgba(0,0,0,.15), 0 8px 10px -6px rgba(0,0,0,.1)"
                }
            }
        }
    };
JSON;
}

// ── Shared Base Styles ────────────────────────────────────────────────────────
function lms_base_styles(): string {
    return <<<CSS
    /* ── Base Styles ────────────────────────────────────────────────────────── */
    *, *::before, *::after { box-sizing: border-box; }

    html { scroll-behavior: smooth; }

    body {
        font-family: 'Inter', system-ui, sans-serif;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
    }

    /* Material Symbols */
    .material-symbols-outlined {
        font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        vertical-align: middle;
        line-height: 1;
        display: inline-flex;
        align-items: center;
        user-select: none;
    }
    .icon-fill { font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24; }

    /* ── Sidebar Layout ──────────────────────────────────────────────────────── */
    .lms-sidebar {
        width: 260px;
        position: fixed;
        top: 0; left: 0; bottom: 0;
        z-index: 50;
        display: flex;
        flex-direction: column;
        transition: width 200ms ease, transform 200ms ease;
        overflow-y: auto;
        overflow-x: hidden;
    }
    .lms-main {
        margin-left: 260px;
        padding-top: 64px;
        min-height: 100vh;
        transition: margin-left 200ms ease;
    }
    .lms-topbar {
        position: fixed;
        top: 0; right: 0;
        left: 260px;
        height: 64px;
        z-index: 40;
        display: flex;
        align-items: center;
        transition: left 200ms ease;
    }

    /* ── Sidebar Nav Items ───────────────────────────────────────────────────── */
    .nav-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 16px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 500;
        text-decoration: none;
        transition: background-color 150ms ease, color 150ms ease;
        cursor: pointer;
        border: none;
        background: none;
        width: 100%;
    }
    .nav-item:hover { background-color: rgba(0,0,0,.05); }
    .nav-item:focus-visible { outline: 2px solid #003d9b; outline-offset: -2px; border-radius: 8px; }
    .nav-item.active {
        background-color: rgba(0, 61, 155, .1);
        color: #003d9b;
        font-weight: 600;
        border-left: 3px solid #003d9b;
        padding-left: 13px;
    }

    /* ── Card Components ─────────────────────────────────────────────────────── */
    .lms-card {
        background: #ffffff;
        border: 1px solid #e6e8ea;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,.06);
        transition: box-shadow 200ms ease;
    }
    .lms-card:hover { box-shadow: 0 4px 6px -1px rgba(0,0,0,.07); }

    .stat-card {
        background: #ffffff;
        border: 1px solid #e6e8ea;
        border-radius: 12px;
        padding: 20px 24px;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .stat-card-icon {
        width: 44px; height: 44px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 12px;
    }
    .stat-card-value {
        font-size: 28px;
        font-weight: 700;
        line-height: 1.2;
        letter-spacing: -0.02em;
    }
    .stat-card-label {
        font-size: 13px;
        font-weight: 500;
        color: #737685;
    }

    /* ── Button System ───────────────────────────────────────────────────────── */
    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 9px 18px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        line-height: 1;
        text-decoration: none;
        cursor: pointer;
        border: none;
        transition: opacity 150ms ease, box-shadow 150ms ease, transform 80ms ease;
        white-space: nowrap;
        user-select: none;
    }
    .btn:active { transform: translateY(1px); }
    .btn:disabled { opacity: .5; cursor: not-allowed; pointer-events: none; }

    .btn-primary   { background: #003d9b; color: #fff; }
    .btn-primary:hover { background: #0040a2; box-shadow: 0 2px 8px rgba(0,61,155,.25); }

    .btn-secondary { background: #d0e1fb; color: #003d9b; }
    .btn-secondary:hover { background: #c4d2ff; }

    .btn-danger    { background: #ba1a1a; color: #fff; }
    .btn-danger:hover { background: #a31616; box-shadow: 0 2px 8px rgba(186,26,26,.25); }

    .btn-ghost     { background: transparent; color: #434654; border: 1px solid #c3c6d6; }
    .btn-ghost:hover { background: #f2f4f6; }

    .btn-sm { padding: 6px 12px; font-size: 12px; border-radius: 6px; }
    .btn-lg { padding: 12px 24px; font-size: 16px; border-radius: 10px; }
    .btn-icon { padding: 8px; border-radius: 8px; }

    /* ── Badge / Chip System ─────────────────────────────────────────────────── */
    .badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 9px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }
    .badge-success  { background: #dcfce7; color: #166534; }
    .badge-warning  { background: #fef9c3; color: #854d0e; }
    .badge-danger   { background: #fee2e2; color: #991b1b; }
    .badge-info     { background: #dbeafe; color: #1e40af; }
    .badge-neutral  { background: #f1f5f9; color: #475569; }

    /* ── Table System ────────────────────────────────────────────────────────── */
    .lms-table { width: 100%; border-collapse: collapse; }
    .lms-table th {
        padding: 10px 16px;
        text-align: left;
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: #737685;
        background: #f2f4f6;
        border-bottom: 1px solid #e6e8ea;
    }
    .lms-table th:first-child { border-radius: 8px 0 0 0; }
    .lms-table th:last-child  { border-radius: 0 8px 0 0; }
    .lms-table td {
        padding: 12px 16px;
        font-size: 14px;
        border-bottom: 1px solid #eceef0;
        vertical-align: middle;
    }
    .lms-table tbody tr:last-child td { border-bottom: none; }
    .lms-table tbody tr:hover td { background: #f7f9fb; }

    /* ── Form Controls ───────────────────────────────────────────────────────── */
    .lms-input, .lms-select, .lms-textarea {
        width: 100%;
        padding: 9px 12px;
        border: 1px solid #c3c6d6;
        border-radius: 8px;
        font-size: 14px;
        font-family: 'Inter', sans-serif;
        background: #fff;
        color: #191c1e;
        transition: border-color 150ms ease, box-shadow 150ms ease;
    }
    .lms-input:focus-visible, .lms-select:focus-visible, .lms-textarea:focus-visible {
        border-color: #003d9b;
        box-shadow: 0 0 0 3px rgba(0,61,155,.12);
        outline: none;
    }
    .lms-label {
        display: block;
        font-size: 12px;
        font-weight: 600;
        color: #434654;
        margin-bottom: 6px;
        letter-spacing: 0.02em;
    }
    .form-group { display: flex; flex-direction: column; gap: 4px; margin-bottom: 16px; }

    /* ── Modal System ────────────────────────────────────────────────────────── */
    .modal-backdrop {
        position: fixed; inset: 0;
        background: rgba(0,0,0,.45);
        backdrop-filter: blur(4px);
        z-index: 200;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        pointer-events: none;
        transition: opacity 200ms ease;
    }
    .modal-backdrop.open {
        opacity: 1;
        pointer-events: all;
    }
    .modal-box {
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 20px 25px -5px rgba(0,0,0,.15), 0 8px 10px -6px rgba(0,0,0,.1);
        width: 100%;
        max-width: 540px;
        max-height: 90vh;
        overflow-y: auto;
        transform: translateY(12px) scale(.98);
        transition: transform 200ms ease;
    }
    .modal-backdrop.open .modal-box { transform: translateY(0) scale(1); }
    .modal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 20px 24px 16px;
        border-bottom: 1px solid #e6e8ea;
    }
    .modal-title  { font-size: 17px; font-weight: 600; color: #191c1e; }
    .modal-body   { padding: 20px 24px; }
    .modal-footer {
        padding: 16px 24px 20px;
        border-top: 1px solid #e6e8ea;
        display: flex;
        justify-content: flex-end;
        gap: 12px;
    }

    /* ── Progress Bar ────────────────────────────────────────────────────────── */
    .progress-track {
        height: 6px;
        background: #e6e8ea;
        border-radius: 999px;
        overflow: hidden;
    }
    .progress-fill {
        height: 100%;
        border-radius: 999px;
        background: #003d9b;
        transition: width 600ms cubic-bezier(.4,0,.2,1);
    }

    /* ── Accessibility: Reduced Motion ─────────────────────────────────────── */
    @media (prefers-reduced-motion: reduce) {
        *, *::before, *::after {
            animation-duration: 0.01ms !important;
            animation-iteration-count: 1 !important;
            transition-duration: 0.01ms !important;
            scroll-behavior: auto !important;
        }
    }

    /* ── Focus Visible ─────────────────────────────────────────────────────── */
    :focus-visible {
        outline: 2px solid #003d9b;
        outline-offset: 2px;
    }
    .lms-input:focus-visible, .lms-select:focus-visible, .lms-textarea:focus-visible {
        outline: none;
        border-color: #003d9b;
        box-shadow: 0 0 0 3px rgba(0,61,155,.12);
    }

    /* ── Scrollbar Styling ───────────────────────────────────────────────────── */
    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: #c3c6d6; border-radius: 999px; }
    ::-webkit-scrollbar-thumb:hover { background: #737685; }

    /* ── Alert / Toast Strips ────────────────────────────────────────────────── */
    .alert {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
        border-radius: 10px;
        font-size: 14px;
        margin-bottom: 16px;
    }
    .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
    .alert-error   { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    .alert-warning { background: #fef9c3; color: #854d0e; border: 1px solid #fde68a; }
    .alert-info    { background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; }

    /* ── Utility: page section wrapper ──────────────────────────────────────── */
    .page-content {
        padding: 32px;
        max-width: 1280px;
    }
    .page-header  {
        margin-bottom: 28px;
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
    }
    .section-title { font-size: 20px; font-weight: 600; color: #191c1e; margin-bottom: 16px; }
CSS;
}

// ── HTML <head> Helper ────────────────────────────────────────────────────────
/**
 * Emit a full <head> block.
 *
 * @param string $title  Browser/tab title
 * @param string $role   admin | instructor | student | guest
 * @param array  $extra  Extra <link> or <meta> tags (raw HTML strings)
 */
function lms_head(string $title, string $role = 'admin', array $extra = []): void { ?>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title><?= htmlspecialchars($title) ?> | Lyra Academy</title>
    <meta name="description" content="Lyra Academy – Music School Learning Management System"/>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com"/>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>

    <!-- Tailwind Design Tokens -->
    <script id="tailwind-config">
        <?= lms_tailwind_config_json($role) ?>
    </script>

    <!-- LMS Base Styles -->
    <style>
        <?= lms_base_styles() ?>
    </style>
    <?php foreach ($extra as $tag) echo $tag . "\n"; ?>
<?php }

require_once __DIR__ . '/nav.php';

/**
 * Render the LMS sidebar for a given role.
 *
 * @param string $role         admin | instructor | student
 * @param string $activeHref   The current page href (matched against nav entries)
 */
function lms_sidebar(string $role, string $activeHref = ''): void {
    global $LMS_SIDEBARS;
    $cfg   = $LMS_SIDEBARS[$role] ?? $LMS_SIDEBARS['admin'];
    $color = $cfg['color'];
    $name  = $_SESSION['name'] ?? 'User';
    $initials = strtoupper(substr($name, 0, 2));
    $roleLabel = ucfirst($role);
    ?>
    <a href="#lms-main-content" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-[200] focus:bg-primary focus:text-white focus:px-4 focus:py-2 focus:rounded-lg focus:font-semibold focus:shadow-lg">Skip to main content</a>
    <aside class="lms-sidebar bg-surface-container-low border-r border-outline-variant flex flex-col py-md px-sm"
           id="lms-sidebar"
           aria-label="<?= htmlspecialchars($cfg['label']) ?> navigation">

        <!-- Brand -->
        <div class="mb-lg px-sm flex items-center gap-sm">
            <div class="w-10 h-10 rounded-lg flex items-center justify-center text-white shrink-0"
                 style="background:<?= $color ?>">
                <span class="material-symbols-outlined icon-fill text-xl" aria-hidden="true"><?= $cfg['icon'] ?></span>
            </div>
            <div class="min-w-0">
                <h1 class="text-h3 font-semibold text-on-surface truncate">Lyra Academy</h1>
                <p class="text-label-md text-outline"><?= htmlspecialchars($cfg['label']) ?></p>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 flex flex-col gap-xs" aria-label="Main navigation">
            <?php foreach ($cfg['nav'] as $item):
                $isActive = ($item['href'] === $activeHref) || (rtrim($_SERVER['PHP_SELF'], '/') === rtrim($item['href'], '/'));
                $activeClass = $isActive ? 'active' : 'text-secondary hover:bg-surface-container';
                ?>
                <a href="<?= htmlspecialchars($item['href']) ?>"
                   class="nav-item <?= $activeClass ?>"
                   <?= $isActive ? 'aria-current="page"' : '' ?>>
                    <span class="material-symbols-outlined <?= $isActive ? 'icon-fill' : '' ?> shrink-0"
                          aria-hidden="true"
                          style="<?= $isActive ? "color:$color" : '' ?>">
                        <?= $item['icon'] ?>
                    </span>
                    <span><?= htmlspecialchars($item['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <!-- Footer: Logout + User -->
        <div class="mt-auto pt-md border-t border-outline-variant">
            <form action="/api/auth/logout.php" method="POST" class="m-0">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <button type="submit"
                   class="nav-item text-secondary hover:bg-surface-container w-full text-left cursor-pointer"
                   id="nav-logout">
                    <span class="material-symbols-outlined shrink-0" aria-hidden="true">logout</span>
                    <span>Logout</span>
                </button>
            </form>
            <div class="mt-sm px-sm flex items-center gap-sm">
                <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold shrink-0"
                     style="background:<?= $color ?>">
                    <?= htmlspecialchars($initials) ?>
                </div>
                <div class="min-w-0">
                    <p class="text-label-md font-semibold text-on-surface truncate"><?= htmlspecialchars($name) ?></p>
                    <p class="text-label-sm text-outline"><?= htmlspecialchars($roleLabel) ?></p>
                </div>
            </div>
        </div>
    </aside>
    <?php
}

/**
 * Render the LMS top navigation bar.
 *
 * @param string $role         admin | instructor | student
 * @param string $pageTitle    Human-readable page title shown in topbar
 * @param string $searchPlaceholder  Search input placeholder text
 */
function lms_topbar(string $role, string $pageTitle = '', string $searchPlaceholder = 'Search…'): void {
    global $LMS_PALETTES;
    $color = $LMS_PALETTES[$role]['role-accent'] ?? '#003d9b';
    ?>
    <header class="lms-topbar bg-surface border-b border-outline-variant px-md"
            id="lms-topbar"
            aria-label="Top navigation bar">
        <div class="flex items-center flex-1 gap-md">
            <?php if ($pageTitle): ?>
                <h2 class="text-h3 font-semibold text-on-surface whitespace-nowrap hidden md:block">
                    <?= htmlspecialchars($pageTitle) ?>
                </h2>
                <div class="w-px h-5 bg-outline-variant hidden md:block"></div>
            <?php endif; ?>
            <!-- Search -->
            <div class="relative w-full max-w-sm">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-lg pointer-events-none" aria-hidden="true">search</span>
                <input type="search"
                       class="lms-input pl-9"
                       placeholder="<?= htmlspecialchars($searchPlaceholder) ?>"
                       aria-label="Search"
                       name="search"
                       autocomplete="off"
                       id="topbar-search"/>
            </div>
        </div>
        <div class="flex items-center gap-sm ml-md">
            <!-- Notifications -->
            <button class="btn btn-ghost btn-icon relative text-on-surface-variant hover:text-on-surface"
                    title="Notifications"
                    aria-label="View notifications"
                    id="btn-notifications">
                <span class="material-symbols-outlined" aria-hidden="true">notifications</span>
                <span class="absolute top-1 right-1 w-2 h-2 rounded-full border-2 border-surface"
                      style="background:<?= $color ?>"></span>
            </button>
            <!-- Help -->
            <button class="btn btn-ghost btn-icon text-on-surface-variant hover:text-on-surface"
                    title="Help"
                    aria-label="Help"
                    id="btn-help">
                <span class="material-symbols-outlined" aria-hidden="true">help_outline</span>
            </button>
        </div>
    </header>
    <?php
}

/**
 * Render the LMS public/guest top navigation bar.
 *
 * @param string $activeHref   The current page href (matched against nav entries for active highlight)
 */
function lms_public_navbar(string $activeHref = ''): void {
    global $LMS_SIDEBARS;
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $isLoggedIn = isset($_SESSION['user_id']);
    $userName = $isLoggedIn ? $_SESSION['name'] : '';
    $userRole = $isLoggedIn ? $_SESSION['role'] : 'guest';
    
    // Determine the dashboard link based on role
    $dashboardHref = '/';
    if ($isLoggedIn) {
        if ($userRole === 'student') {
            $dashboardHref = '/40_Student_Dashboard/index.php';
        } elseif ($userRole === 'instructor') {
            $dashboardHref = '/17_Instructor_Dashboard/index.php';
        } elseif ($userRole === 'admin') {
            $dashboardHref = '/02_Admin_Dashboard/index.php';
        }
    }
    $publicNavItems = $LMS_SIDEBARS['guest']['nav'] ?? [];
    ?>
    <a href="#lms-main-content" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-[200] focus:bg-primary focus:text-white focus:px-4 focus:py-2 focus:rounded-lg focus:font-semibold focus:shadow-lg">Skip to main content</a>
    <nav class="fixed top-0 left-0 w-full h-[64px] bg-surface border-b border-outline-variant shadow-sm z-50 flex justify-between items-center px-md">
        <div class="flex justify-between items-center w-full max-w-container-max mx-auto h-full">
            <div class="flex items-center gap-md">
                <a href="/43_Public_Homepage/index.php" class="font-h2 text-h2 text-primary font-bold tracking-tight">Lyra Academy</a>
                <div class="hidden md:flex items-center gap-md ml-lg">
                    <?php foreach ($publicNavItems as $item):
                        $isActive = ($activeHref === $item['href'])
                            || ($item['href'] === '/43_Public_Homepage/index.php' && $activeHref === '/')
                            || ($item['href'] === '/42_Public_Course_Catalog/index.php' && $activeHref === '/45_Public_Course_Detail/index.php');
                        $linkClass = $isActive
                            ? 'text-primary font-bold border-b-2 border-primary pb-1 font-label-md text-label-md'
                            : 'text-secondary hover:text-primary transition-colors font-label-md text-label-md';
                    ?>
                        <a class="<?= $linkClass ?>" href="<?= htmlspecialchars($item['href']) ?>">
                            <?= htmlspecialchars($item['label']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="flex items-center gap-sm">
                <?php if ($isLoggedIn): ?>
                    <span class="text-body-md text-on-surface font-semibold hidden sm:inline">Hello, <?= htmlspecialchars($userName) ?></span>
                    <a href="<?= htmlspecialchars($dashboardHref) ?>" class="px-md py-xs rounded-lg font-label-md text-label-md bg-primary text-on-primary hover:opacity-90 active:scale-95 transition-all">Dashboard</a>
                    <form action="/api/auth/logout.php" method="POST" class="inline m-0">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                        <button type="submit" class="px-md py-xs rounded-lg font-label-md text-label-md bg-secondary text-white hover:opacity-90 active:scale-95 transition-all cursor-pointer">Logout</button>
                    </form>
                <?php else: ?>
                    <a href="/auth/login.php" class="px-md py-xs rounded-lg font-label-md text-label-md text-primary hover:bg-surface-container-high transition-all">Login</a>
                    <a href="/auth/register.php" class="px-md py-xs rounded-lg font-label-md text-label-md bg-primary text-on-primary hover:opacity-90 active:scale-95 transition-all">Join Now</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    <?php
}
