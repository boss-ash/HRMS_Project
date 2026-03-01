<?php
define('HRMS_LOADED', true);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$base = getBasePath();

$announcements = [];
$res = @mysqli_query($conn, "SELECT * FROM announcements WHERE is_published = 1 ORDER BY created_at DESC LIMIT 50");
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) $announcements[] = $row;
}

$pageTitle = 'Announcements';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/nav.php';
?>
<main class="min-h-[calc(100vh-4rem)] bg-slate-950 text-slate-50">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="mb-6">
            <p class="text-[10px] uppercase tracking-[0.24em] text-slate-400 mb-1">Company</p>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-50">Announcements</h1>
            <p class="text-sm text-slate-400 mt-1">Company news and updates.</p>
        </div>

        <section class="space-y-4">
            <?php if (empty($announcements)): ?>
            <div class="rounded-2xl border border-slate-700/70 bg-slate-900/80 p-8 text-center text-slate-500">No announcements yet.</div>
            <?php else: ?>
            <?php foreach ($announcements as $a): ?>
            <article class="rounded-2xl border border-slate-700/70 bg-slate-900/80 p-5">
                <h2 class="text-lg font-semibold text-slate-100"><?= htmlspecialchars($a['title']) ?></h2>
                <p class="text-xs text-slate-500 mt-1"><?= htmlspecialchars($a['created_at']) ?></p>
                <div class="mt-3 text-sm text-slate-300 whitespace-pre-wrap"><?= nl2br(htmlspecialchars($a['content'] ?? '')) ?></div>
            </article>
            <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </div>
</main>
</body>
</html>
