<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'Admin PSB - Babul Jannah' ?></title>
    
    <!-- Tailwind CSS v3 Compiled (Local) -->
    <link rel="stylesheet" href="../assets/css/tailwind.min.css">
    
    <!-- Font Awesome (Local) -->
    <link rel="stylesheet" href="../assets/vendor/fontawesome/css/all.min.css">
    
    <!-- Fonts -->
    <link rel="stylesheet" href="../assets/css/fonts.css">
    
    <!-- Custom Style Sheet (Includes Local Fonts: Plus Jakarta Sans) -->
    <link rel="stylesheet" href="../assets/css/style.css">
    
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
        .bg-brand-primary { background-color: #5C3E35 !important; }
        .bg-brand-dark { background-color: #3E2723 !important; }
        .bg-brand-deep { background-color: #4A3029 !important; }
        .bg-brand-secondary { background-color: #A17A6F !important; }
        .text-brand-primary { color: #5C3E35 !important; }
        .text-brand-secondary { color: #A17A6F !important; }
        .border-brand-primary { border-color: #5C3E35 !important; }
        .border-brand-secondary { border-color: #A17A6F !important; }

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
    </style>

    <!-- Custom CSS/JS from specific pages -->
    <?php if (isset($extra_css)) echo $extra_css; ?>
    <?php if (isset($extra_js)) echo $extra_js; ?>
</head>
