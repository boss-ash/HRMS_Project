<?php
if (!defined('HRMS_LOADED')) {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/auth.php';
}
$base = getBasePath();
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\' https://cdn.tailwindcss.com; style-src \'self\' \'unsafe-inline\' https://cdn.tailwindcss.com https://fonts.googleapis.com; font-src https://fonts.gstatic.com; img-src \'self\' data: https:;');
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    }
}
?>
<!DOCTYPE html>
<html lang="en" id="hrms-html">
<head>
    <script>
    (function(){
        var t = localStorage.getItem('hrms-theme');
        if (t === 'light' || t === 'dark') document.documentElement.setAttribute('data-theme', t);
        else document.documentElement.setAttribute('data-theme', 'dark');
    })();
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Horyzon') ?> | Horyzon</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: { 50: '#eff6ff', 100: '#dbeafe', 200: '#bfdbfe', 300: '#93c5fd', 400: '#60a5fa', 500: '#3b82f6', 600: '#2563eb', 700: '#1d4ed8', 800: '#1e40af', 900: '#1e3a8a' },
                        slate: { 850: '#172033', 950: '#0c1222' }
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .animate-orb-slow {
            animation: orbFloatSlow 22s ease-in-out infinite alternate;
        }
        .animate-orb-medium {
            animation: orbFloatMedium 26s ease-in-out infinite alternate;
        }
        .animate-orb-soft {
            animation: orbPulseSoft 18s ease-in-out infinite alternate;
        }
        .session-warning {
            background-color: rgba(250, 204, 21, 0.12);
            border-color: rgba(250, 204, 21, 0.7);
            box-shadow: 0 0 0 1px rgba(250, 204, 21, 0.35);
        }
        @keyframes orbFloatSlow {
            0% { transform: translate3d(0, 0, 0) scale(1); opacity: 0.55; }
            50% { transform: translate3d(-32px, 18px, 0) scale(1.08); opacity: 0.7; }
            100% { transform: translate3d(18px, -24px, 0) scale(0.98); opacity: 0.5; }
        }
        @keyframes orbFloatMedium {
            0% { transform: translate3d(0, 0, 0) scale(1); opacity: 0.55; }
            50% { transform: translate3d(30px, -24px, 0) scale(1.06); opacity: 0.75; }
            100% { transform: translate3d(-24px, 20px, 0) scale(0.97); opacity: 0.5; }
        }
        @keyframes orbPulseSoft {
            0% { transform: translate3d(-50%, 0, 0) scale(1); opacity: 0.55; }
            50% { transform: translate3d(-48%, -10px, 0) scale(1.05); opacity: 0.75; }
            100% { transform: translate3d(-52%, 8px, 0) scale(0.96); opacity: 0.5; }
        }
        /* Dashboard 3D – depth, orbs, hover lift (same for Admin & Staff) */
        .dashboard-perspective { perspective: 1400px; }
        .card-3d {
            transform-style: preserve-3d;
            transform: translateZ(0);
            transition: transform 0.35s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.35s ease;
            box-shadow:
                0 1px 2px rgba(0,0,0,0.04),
                0 10px 24px -6px rgba(0,0,0,0.3),
                0 28px 56px -14px rgba(0,0,0,0.4),
                inset 0 1px 0 rgba(255,255,255,0.08);
        }
        .card-3d:hover {
            transform: translateZ(20px) translateY(-8px) scale(1.02);
            box-shadow:
                0 4px 8px rgba(0,0,0,0.06),
                0 24px 48px -10px rgba(0,0,0,0.45),
                0 48px 96px -24px rgba(0,0,0,0.5),
                inset 0 1px 0 rgba(255,255,255,0.12);
        }
        .card-3d-edge {
            position: absolute;
            inset: 0;
            border-radius: inherit;
            pointer-events: none;
            overflow: hidden;
        }
        .card-3d-edge::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.14), transparent);
            opacity: 0.9;
        }
        /* 3D orbs on stat cards – sphere effect, number inside */
        .orb-3d {
            width: 88px;
            height: 88px;
            border-radius: 50%;
            flex-shrink: 0;
            transform-style: preserve-3d;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow:
                inset 3px 3px 14px rgba(255,255,255,0.25),
                inset -4px -6px 16px rgba(0,0,0,0.35),
                0 16px 32px -8px rgba(0,0,0,0.4);
            transition: transform 0.4s ease, box-shadow 0.4s ease;
        }
        .card-3d:hover .orb-3d {
            transform: scale(1.08) translateZ(8px);
            box-shadow:
                inset 3px 3px 14px rgba(255,255,255,0.3),
                inset -4px -6px 16px rgba(0,0,0,0.3),
                0 20px 40px -6px rgba(0,0,0,0.45);
        }
        .orb-3d-primary {
            background: radial-gradient(circle at 32% 28%, rgba(255,255,255,0.45), transparent 45%),
                radial-gradient(circle at 72% 72%, rgba(0,0,0,0.25), transparent 45%),
                radial-gradient(ellipse 100% 100% at 50% 50%, #60a5fa, #1e3a8a);
        }
        .orb-3d-emerald {
            background: radial-gradient(circle at 32% 28%, rgba(255,255,255,0.4), transparent 45%),
                radial-gradient(circle at 72% 72%, rgba(0,0,0,0.2), transparent 45%),
                radial-gradient(ellipse 100% 100% at 50% 50%, #34d399, #064e3b);
        }
        .orb-3d-amber {
            background: radial-gradient(circle at 32% 28%, rgba(255,255,255,0.45), transparent 45%),
                radial-gradient(circle at 72% 72%, rgba(0,0,0,0.25), transparent 45%),
                radial-gradient(ellipse 100% 100% at 50% 50%, #fbbf24, #78350f);
        }
        .orb-3d-number {
            font-size: 1.75rem;
            font-weight: 700;
            line-height: 1;
            color: rgba(255,255,255,0.98);
            text-shadow: 0 1px 3px rgba(0,0,0,0.5), 0 2px 10px rgba(0,0,0,0.35);
        }
        .card-3d-diagonal::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 42%;
            height: 100%;
            background: linear-gradient(135deg, transparent 40%, rgba(255,255,255,0.03) 100%);
            clip-path: polygon(30% 0, 100% 0, 100% 100%, 0 100%);
            pointer-events: none;
            border-radius: 0 0.75rem 0.75rem 0;
        }
        .panel-3d {
            transform-style: preserve-3d;
            transform: translateZ(0);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            box-shadow:
                0 4px 16px -4px rgba(0,0,0,0.25),
                0 20px 48px -16px rgba(0,0,0,0.35),
                inset 0 1px 0 rgba(255,255,255,0.06);
        }
        .panel-3d:hover {
            transform: translateZ(10px);
            box-shadow:
                0 14px 28px -6px rgba(0,0,0,0.3),
                0 28px 64px -20px rgba(0,0,0,0.4),
                inset 0 1px 0 rgba(255,255,255,0.1);
        }
        .glow-orb { filter: drop-shadow(0 0 20px currentColor); }
        .float-in {
            animation: floatIn 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) backwards;
        }
        .float-in-1 { animation-delay: 0.05s; }
        .float-in-2 { animation-delay: 0.1s; }
        .float-in-3 { animation-delay: 0.15s; }
        .float-in-4 { animation-delay: 0.2s; }
        .float-in-5 { animation-delay: 0.25s; }
        .float-in-6 { animation-delay: 0.3s; }
        @keyframes floatIn {
            from {
                opacity: 0;
                transform: translateY(24px) translateZ(-24px);
            }
            to {
                opacity: 1;
                transform: translateY(0) translateZ(0);
            }
        }
        /* Theme: light mode – ensure all elements visible */
        [data-theme="light"] body { background: #f1f5f9 !important; color: #0f172a !important; }
        [data-theme="light"] nav { background: #ffffff !important; border-color: #e2e8f0 !important; }
        [data-theme="light"] nav a, [data-theme="light"] nav span { color: #475569 !important; }
        [data-theme="light"] nav .text-slate-50, [data-theme="light"] nav .text-slate-100 { color: #0f172a !important; }
        [data-theme="light"] nav .text-slate-400 { color: #64748b !important; }
        [data-theme="light"] nav .bg-slate-900\/80 { background: #f1f5f9 !important; border-color: #e2e8f0 !important; }
        [data-theme="light"] nav .border-slate-700\/80 { border-color: #e2e8f0 !important; }
        [data-theme="light"] nav .hover\:bg-slate-800\/80:hover { background: #e2e8f0 !important; }
        [data-theme="light"] nav .hover\:text-slate-100:hover { color: #0f172a !important; }
        [data-theme="light"] nav .border-primary-400\/80 { border-color: #3b82f6 !important; }
        [data-theme="light"] nav .bg-primary-500\/20 { background: #dbeafe !important; }
        [data-theme="light"] nav .text-primary-100 { color: #1d4ed8 !important; }
        [data-theme="light"] main { background: #f1f5f9 !important; color: #0f172a !important; }
        [data-theme="light"] main .bg-slate-950, [data-theme="light"] main .bg-slate-950\/95 { background: #f1f5f9 !important; }
        [data-theme="light"] .text-slate-50, [data-theme="light"] .text-slate-100 { color: #0f172a !important; }
        [data-theme="light"] .text-slate-200 { color: #334155 !important; }
        [data-theme="light"] .text-slate-300 { color: #475569 !important; }
        [data-theme="light"] .text-slate-400 { color: #64748b !important; }
        [data-theme="light"] .text-slate-500 { color: #64748b !important; }
        [data-theme="light"] .text-primary-300 { color: #1d4ed8 !important; }
        [data-theme="light"] .text-primary-200 { color: #2563eb !important; }
        [data-theme="light"] .text-primary-100 { color: #1d4ed8 !important; }
        [data-theme="light"] .text-emerald-200\/90, [data-theme="light"] .text-emerald-100\/90 { color: #047857 !important; }
        [data-theme="light"] .text-amber-200\/90, [data-theme="light"] .text-amber-100\/90 { color: #b45309 !important; }
        [data-theme="light"] main .border-slate-700, [data-theme="light"] main .border-slate-800, [data-theme="light"] .border-slate-700\/60, [data-theme="light"] .border-slate-700\/70 { border-color: #cbd5e1 !important; }
        [data-theme="light"] .bg-slate-900\/80, [data-theme="light"] .bg-slate-800\/50, [data-theme="light"] .bg-slate-900\/70 { background: #ffffff !important; border-color: #e2e8f0 !important; }
        [data-theme="light"] .bg-slate-900\/90:hover { background: #f1f5f9 !important; }
        [data-theme="light"] .border-emerald-600\/50 { border-color: #a7f3d0 !important; }
        [data-theme="light"] .bg-emerald-950\/50 { background: #ecfdf5 !important; }
        [data-theme="light"] .border-amber-600\/50 { border-color: #fde68a !important; }
        [data-theme="light"] .bg-amber-950\/50 { background: #fffbeb !important; }
        [data-theme="light"] .card-3d-edge::after { background: linear-gradient(90deg, transparent, rgba(0,0,0,0.06), transparent); }
        [data-theme="light"] .session-warning { background: rgba(245,158,11,0.15) !important; border-color: #f59e0b !important; }
        [data-theme="light"] .min-h-screen.bg-slate-950 { background: #f1f5f9 !important; }
        [data-theme="light"] .bg-slate-950\/80 { background: #ffffff !important; border-color: #e2e8f0 !important; }
        [data-theme="light"] #hrms-theme-toggle { background: #fff !important; border-color: #e2e8f0 !important; color: #475569 !important; }
        [data-theme="light"] #hrms-theme-toggle:hover { background: #f1f5f9 !important; color: #0f172a !important; }
        [data-theme="light"] input, [data-theme="light"] select, [data-theme="light"] textarea { background: #fff !important; border-color: #cbd5e1 !important; color: #0f172a !important; }
        [data-theme="light"] input::placeholder, [data-theme="light"] textarea::placeholder { color: #94a3b8 !important; }
        [data-theme="light"] table th, [data-theme="light"] table td { color: #0f172a !important; border-color: #e2e8f0 !important; }
        [data-theme="light"] table thead th { background: #f1f5f9 !important; color: #475569 !important; }
        [data-theme="light"] .bg-slate-800\/30 { background: rgba(241,245,249,0.8) !important; }
        [data-theme="light"] a.text-slate-200:hover, [data-theme="light"] a.text-slate-300:hover { color: #1d4ed8 !important; }
        [data-theme="light"] .hover\:text-primary-100:hover { color: #1d4ed8 !important; }
        [data-theme="light"] .hover\:border-primary-400\/80:hover { border-color: #3b82f6 !important; }
        [data-theme="light"] code { background: #e2e8f0 !important; color: #0f172a !important; }
        [data-theme="light"] .text-red-100, [data-theme="light"] .text-red-200 { color: #b91c1c !important; }
        [data-theme="light"] .text-emerald-100 { color: #047857 !important; }
        [data-theme="light"] .text-amber-100 { color: #b45309 !important; }
        /* Global loading */
        #hrms-loader { position: fixed; top: 0; left: 0; right: 0; height: 3px; z-index: 9999; background: linear-gradient(90deg, #3b82f6, #34d399); transform-origin: left; animation: hrms-load-progress 1.2s ease-out forwards; pointer-events: none; }
        #hrms-loader.done { animation: hrms-load-done 0.35s ease-out forwards; }
        @keyframes hrms-load-progress { 0% { transform: scaleX(0); opacity: 1; } 70% { transform: scaleX(0.85); opacity: 1; } 100% { transform: scaleX(0.92); opacity: 1; } }
        @keyframes hrms-load-done { 0% { transform: scaleX(0.92); opacity: 1; } 100% { transform: scaleX(1); opacity: 0; } }
        #hrms-spinner { position: fixed; top: 50%; left: 50%; width: 48px; height: 48px; margin: -24px 0 0 -24px; z-index: 9998; pointer-events: none; opacity: 0; transition: opacity 0.2s; }
        #hrms-spinner.visible { opacity: 1; }
        #hrms-spinner::after { content: ''; display: block; width: 48px; height: 48px; border: 3px solid #e2e8f0; border-top-color: #3b82f6; border-radius: 50%; animation: hrms-spin 0.8s linear infinite; }
        [data-theme="dark"] #hrms-spinner::after { border-color: rgba(51,65,85,0.5); border-top-color: #60a5fa; }
        @keyframes hrms-spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased">
<div id="hrms-loader" role="presentation" aria-hidden="true"></div>
<div id="hrms-spinner" class="visible" role="presentation" aria-hidden="true"></div>
<script>
(function(){
    function done(){ var l=document.getElementById('hrms-loader'); var s=document.getElementById('hrms-spinner'); if(l) l.classList.add('done'); if(s) s.classList.remove('visible'); setTimeout(function(){ l=document.getElementById('hrms-loader'); s=document.getElementById('hrms-spinner'); if(l) l.remove(); if(s) s.remove(); }, 400); }
    if (document.readyState === 'complete') done(); else window.addEventListener('load', done);
    function onDocReady(){ document.body.addEventListener('click', function(e){ var a=e.target.closest('a[href]'); if(a && a.hostname===window.location.hostname && a.pathname!==window.location.pathname){ var s=document.getElementById('hrms-spinner'); if(s) s.classList.add('visible'); } }); }
    if (document.readyState==='loading') document.addEventListener('DOMContentLoaded', onDocReady); else onDocReady();
})();
</script>
