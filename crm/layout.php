<?php require_once __DIR__ . '/bootstrap.php';
function page_start(string $title, string $active = ''): void { global $config; $flash = take_flash(); ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow,noarchive"><title><?= e($title) ?> · <?= e($config['app_name'] ?? 'Kikku Partnerships CRM') ?></title><link rel="stylesheet" href="style.css"></head><body>
<header class="topbar"><a class="brand" href="index.php"><span>KIKKU</span><small>Partnerships CRM</small></a><nav><a class="<?= $active === 'dashboard' ? 'active' : '' ?>" href="index.php">Dashboard</a><a class="<?= $active === 'prospects' ? 'active' : '' ?>" href="prospects.php">Prospects</a><a class="<?= $active === 'import' ? 'active' : '' ?>" href="import.php">Import CSV</a><a href="export.php">Export backup</a></nav></header><main class="shell">
<?php if ($flash): ?><div class="flash <?= e($flash[1]) ?>"><?= e($flash[0]) ?></div><?php endif; ?>
<?php }
function page_end(): void { ?></main></body></html><?php }
function status_badge(string $status): string { return '<span class="badge ' . e(status_class($status)) . '">' . e($status) . '</span>'; }
