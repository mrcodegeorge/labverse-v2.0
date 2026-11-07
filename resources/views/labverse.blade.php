<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="LABVERSE — Enterprise Medical Diagnostic Laboratory Information Management System (LIMS)">
    <title>LABVERSE — Enterprise Laboratory Information Management System</title>

    <!-- Inter & JetBrains Mono Typography -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- LABVERSE Enterprise Healthcare Design System -->
    <link href="/css/labverse.css" rel="stylesheet">
</head>
<body>
    <!-- Main Application Root -->
    <div id="lv-app-root">
        <div class="min-vh-100 d-flex align-items-center justify-content-center">
            <div class="text-center">
                <div class="spinner-border text-primary mb-2" role="status" aria-hidden="true"></div>
                <div class="small fw-semibold text-secondary">Initializing Labverse Clinical Workspace...</div>
            </div>
        </div>
    </div>

    <!-- Accessible Native <dialog> Container -->
    <dialog id="lv-native-dialog" class="lv-dialog" aria-label="Laboratory Action Dialog"></dialog>

    <!-- Contextual Slide-Over Inspector Drawer Host -->
    <div id="lv-drawer-host" class="d-none"></div>

    <!-- Live Toast Notification Region -->
    <div id="lv-toast-container" class="position-fixed bottom-0 end-0 p-3 lv-no-print" style="z-index: 1090;" aria-live="polite" aria-atomic="true"></div>

    <!-- Vendor Scripts: Bootstrap 5 Bundle, Lucide Icons, Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>

    <!-- LABVERSE Modular Application Scripts -->
    <script src="/js/labverse-ui.js"></script>
    <script src="/js/labverse-core-ops.js"></script>
    <script src="/js/labverse-lab-ops.js"></script>
    <script src="/js/labverse-admin-ops.js"></script>
    <script src="/js/labverse-app.js"></script>
</body>
</html>

