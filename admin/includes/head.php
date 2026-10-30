<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'Admin PSB - Babul Jannah' ?></title>
    
    <!-- Tailwind CSS v3 Compiled (Local with auto cache-busting) -->
    <link rel="stylesheet" href="../assets/css/tailwind.min.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/tailwind.min.css') ?: '1.0' ?>">
    
    <!-- Font Awesome (Local) -->
    <link rel="stylesheet" href="../assets/vendor/fontawesome/css/all.min.css">
    
    <!-- Google Fonts Official CDN: Plus Jakarta Sans & Playfair Display (Guaranteed High-Fidelity & Cross-Browser Consistency) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400;1,600;1,700&family=Playfair+Display:ital,wght@0,600;0,700;1,600&display=swap" rel="stylesheet">

    <!-- Fonts (Local Fallback) -->
    <link rel="stylesheet" href="../assets/css/fonts.css">
    
    <!-- Custom Style Sheet (Includes Local Fonts & Overrides with auto cache-busting) -->
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/style.css') ?: '1.0' ?>">
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" defer></script>

    <!-- Brand Color Styles Fallback & Utilities -->
    <style>
        :root {
            --brand-primary: #5C3E35;
            --brand-primary-dark: #3E2723;
            --brand-primary-deep: #4A3029;
            --brand-secondary: #A17A6F;
            --brand-accent: #FBF9F6;
            --brand-cream: #F3EDE6;
            --brand-dark: #2F2522;
        }

        /* High-Fidelity Font Enforcement across All Devices */
        body, input, textarea, select, button, optgroup {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
        }
        .bg-brand-primary { background-color: #5C3E35 !important; }
        .bg-brand-dark { background-color: #3E2723 !important; }
        .bg-brand-deep { background-color: #4A3029 !important; }
        .bg-brand-secondary { background-color: #A17A6F !important; }
        .text-brand-primary { color: #5C3E35 !important; }
        .text-brand-secondary { color: #A17A6F !important; }
        .border-brand-primary { border-color: #5C3E35 !important; }
        .border-brand-secondary { border-color: #A17A6F !important; }

        /* Card Header & Brand Gradients */
        .card-header-brand, .bg-gradient-brand {
            background: linear-gradient(135deg, #4A3029 0%, #5C3E35 100%) !important;
            color: #FFFFFF !important;
        }

        /* Role Avatar Styles (Guaranteed Vibrant Colors) */
        .avatar-superadmin {
            background: linear-gradient(135deg, #E11D48 0%, #BE123C 100%) !important;
            color: #FFFFFF !important;
        }
        .avatar-panitia {
            background: linear-gradient(135deg, #059669 0%, #047857 100%) !important;
            color: #FFFFFF !important;
        }
        .avatar-bendahara {
            background: linear-gradient(135deg, #2563EB 0%, #1D4ED8 100%) !important;
            color: #FFFFFF !important;
        }
        .avatar-humas {
            background: linear-gradient(135deg, #D97706 0%, #B45309 100%) !important;
            color: #FFFFFF !important;
        }
        .avatar-yayasan {
            background: linear-gradient(135deg, #7C3AED 0%, #6D28D9 100%) !important;
            color: #FFFFFF !important;
        }

        /* Role Badges */
        .badge-superadmin {
            background-color: #FFF1F2 !important;
            color: #9F1239 !important;
            border: 1px solid #FECDD3 !important;
        }
        .badge-panitia {
            background-color: #ECFDF5 !important;
            color: #065F46 !important;
            border: 1px solid #A7F3D0 !important;
        }
        .badge-bendahara {
            background-color: #EFF6FF !important;
            color: #1E40AF !important;
            border: 1px solid #BFDBFE !important;
        }
        .badge-humas {
            background-color: #FFFBEB !important;
            color: #92400E !important;
            border: 1px solid #FDE68A !important;
        }
        .badge-yayasan {
            background-color: #F5F3FF !important;
            color: #5B21B6 !important;
            border: 1px solid #DDD6FE !important;
        }

        /* Extended Color Fallbacks (Never transparent or purged) */
        .bg-rose-50 { background-color: #FFF1F2 !important; }
        .bg-rose-100 { background-color: #FFE4E6 !important; }
        .bg-rose-600 { background-color: #E11D48 !important; }
        .bg-rose-700 { background-color: #BE123C !important; }
        .bg-rose-800 { background-color: #9F1239 !important; }
        .text-rose-600 { color: #E11D48 !important; }
        .text-rose-700 { color: #BE123C !important; }
        .text-rose-800 { color: #9F1239 !important; }
        .border-rose-200 { border-color: #FECDD3 !important; }
        .border-rose-300 { border-color: #FDA4AF !important; }

        .bg-purple-50 { background-color: #FAF5FF !important; }
        .bg-purple-100 { background-color: #F3E8FF !important; }
        .bg-purple-600 { background-color: #9333EA !important; }
        .bg-purple-700 { background-color: #7E22CE !important; }
        .text-purple-600 { color: #9333EA !important; }
        .text-purple-700 { color: #7E22CE !important; }
        .text-purple-800 { color: #6B21A8 !important; }
        .border-purple-200 { border-color: #E9D5FF !important; }

        .bg-blue-50 { background-color: #EFF6FF !important; }
        .bg-blue-100 { background-color: #DBEAFE !important; }
        .bg-blue-600 { background-color: #2563EB !important; }
        .bg-blue-700 { background-color: #1D4ED8 !important; }
        .bg-blue-800 { background-color: #1E40AF !important; }
        .text-blue-600 { color: #2563EB !important; }
        .text-blue-700 { color: #1D4ED8 !important; }
        .text-blue-800 { color: #1E40AF !important; }
        .border-blue-200 { border-color: #BFDBFE !important; }

        .bg-emerald-50 { background-color: #ECFDF5 !important; }
        .bg-emerald-100 { background-color: #D1FAE5 !important; }
        .bg-emerald-600 { background-color: #059669 !important; }
        .bg-emerald-700 { background-color: #047857 !important; }
        .bg-emerald-800 { background-color: #065F46 !important; }
        .text-emerald-600 { color: #059669 !important; }
        .text-emerald-700 { color: #047857 !important; }
        .text-emerald-800 { color: #065F46 !important; }
        .border-emerald-200 { border-color: #A7F3D0 !important; }
        .border-emerald-300 { border-color: #6EE7B7 !important; }

        .bg-amber-50 { background-color: #FFFBEB !important; }
        .bg-amber-100 { background-color: #FEF3C7 !important; }
        .bg-amber-500 { background-color: #F59E0B !important; }
        .bg-amber-600 { background-color: #D97706 !important; }
        .text-amber-600 { color: #D97706 !important; }
        .text-amber-700 { color: #B45309 !important; }
        .text-amber-800 { color: #92400E !important; }
        .border-amber-200 { border-color: #FDE68A !important; }

        /* Solid Action Button Fallbacks (Resistant to Tailwind purge/browser cache) */
        .btn-export-excel {
            background-color: #15803D !important;
            color: #FFFFFF !important;
            border: 1px solid #166534 !important;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }
        .btn-export-excel:hover {
            background-color: #166534 !important;
            color: #FFFFFF !important;
        }
        .btn-import-excel {
            background-color: #1D4ED8 !important;
            color: #FFFFFF !important;
            border: 1px solid #1E40AF !important;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }
        .btn-import-excel:hover {
            background-color: #1E40AF !important;
            color: #FFFFFF !important;
        }
        .btn-detail-santri {
            background-color: #5C3E35 !important;
            color: #FFFFFF !important;
            border: 1px solid #4A3029 !important;
        }
        .btn-detail-santri:hover {
            background-color: #4A3029 !important;
            color: #FFFFFF !important;
        }

        /* ======================================================== */
        /* SCROLLBAR TRANSPARAN SERAGAM GLOBAL (SEMUA HALAMAN ADMIN) */
        /* ======================================================== */
        html, body {
            height: 100%;
            max-height: 100vh;
            overflow: hidden !important;
        }

        /* Firefox */
        * {
            scrollbar-width: thin;
            scrollbar-color: rgba(92, 62, 53, 0.22) transparent;
        }

        /* WebKit (Chrome, Edge, Safari, Opera) */
        ::-webkit-scrollbar {
            width: 6px !important;
            height: 6px !important;
        }
        ::-webkit-scrollbar-track {
            background: transparent !important;
        }
        ::-webkit-scrollbar-thumb {
            background-color: rgba(92, 62, 53, 0.22) !important;
            border-radius: 9999px !important;
            border: 1px solid transparent !important;
            background-clip: padding-box !important;
            transition: background-color 0.2s ease !important;
        }
        ::-webkit-scrollbar-thumb:hover {
            background-color: rgba(92, 62, 53, 0.45) !important;
        }
        ::-webkit-scrollbar-corner {
            background: transparent !important;
        }

        /* Utility .custom-scrollbar yang 100% transparan */
        .custom-scrollbar {
            scrollbar-width: thin !important;
            scrollbar-color: rgba(92, 62, 53, 0.22) transparent !important;
        }
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px !important;
            height: 6px !important;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent !important;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background-color: rgba(92, 62, 53, 0.22) !important;
            border-radius: 9999px !important;
            border: 1px solid transparent !important;
            background-clip: padding-box !important;
            transition: background-color 0.2s ease !important;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background-color: rgba(92, 62, 53, 0.45) !important;
        }

        /* Scrollbar khusus Sidebar Gelap (Thumb Putih Transparan) */
        aside nav,
        aside .custom-scrollbar {
            scrollbar-width: thin !important;
            scrollbar-color: rgba(255, 255, 255, 0.2) transparent !important;
        }
        aside nav::-webkit-scrollbar-thumb,
        aside .custom-scrollbar::-webkit-scrollbar-thumb {
            background-color: rgba(255, 255, 255, 0.2) !important;
        }
        aside nav::-webkit-scrollbar-thumb:hover,
        aside .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background-color: rgba(255, 255, 255, 0.4) !important;
        }
        /* Form Controls Consistency & Focus Glow */
        input[type="text"], input[type="number"], input[type="date"], input[type="email"], input[type="password"], textarea, select {
            border-radius: 0.75rem !important;
        }
        input[type="text"]:focus, input[type="number"]:focus, input[type="date"]:focus, input[type="email"]:focus, input[type="password"]:focus, textarea:focus, select:focus {
            border-color: #5C3E35 !important;
            outline: none !important;
            box-shadow: 0 0 0 3px rgba(92, 62, 53, 0.15) !important;
        }

        /* Grid Column Fallbacks (Prevent Tailwind Purge Collapses) */
        .col-span-1 { grid-column: span 1 / span 1 !important; }
        .col-span-2 { grid-column: span 2 / span 2 !important; }
        .col-span-3 { grid-column: span 3 / span 3 !important; }
        .col-span-4 { grid-column: span 4 / span 4 !important; }
        .col-span-5 { grid-column: span 5 / span 5 !important; }
        .col-span-6 { grid-column: span 6 / span 6 !important; }
        .col-span-7 { grid-column: span 7 / span 7 !important; }
        .col-span-8 { grid-column: span 8 / span 8 !important; }
        .col-span-9 { grid-column: span 9 / span 9 !important; }
        .col-span-10 { grid-column: span 10 / span 10 !important; }
        .col-span-11 { grid-column: span 11 / span 11 !important; }
        .col-span-12 { grid-column: span 12 / span 12 !important; }

        @media (min-width: 640px) {
            .sm\:col-span-1 { grid-column: span 1 / span 1 !important; }
            .sm\:col-span-2 { grid-column: span 2 / span 2 !important; }
            .sm\:col-span-3 { grid-column: span 3 / span 3 !important; }
            .sm\:col-span-4 { grid-column: span 4 / span 4 !important; }
            .sm\:col-span-8 { grid-column: span 8 / span 8 !important; }
        }

        @media (min-width: 768px) {
            .md\:col-span-1 { grid-column: span 1 / span 1 !important; }
            .md\:col-span-2 { grid-column: span 2 / span 2 !important; }
            .md\:col-span-3 { grid-column: span 3 / span 3 !important; }
            .md\:col-span-4 { grid-column: span 4 / span 4 !important; }
            .md\:col-span-5 { grid-column: span 5 / span 5 !important; }
            .md\:col-span-6 { grid-column: span 6 / span 6 !important; }
            .md\:col-span-7 { grid-column: span 7 / span 7 !important; }
            .md\:col-span-8 { grid-column: span 8 / span 8 !important; }
            .md\:col-span-9 { grid-column: span 9 / span 9 !important; }
            .md\:col-span-12 { grid-column: span 12 / span 12 !important; }
        }
    </style>

    <!-- Custom CSS/JS from specific pages -->
    <?php if (isset($extra_css)) echo $extra_css; ?>
    <?php if (isset($extra_js)) echo $extra_js; ?>
</head>
