<?php
/** @var string $content */
/** @var string $title */
/** @var bool $isAdmin */
$site = App\Config::get('SITE_NAME', 'Συνταγές');
$isAdminPage = str_starts_with($template ?? '', 'admin/');
?>
<!doctype html>
<html lang="el">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(($title ?? '') !== '' && $title !== $site ? "$title · $site" : $site) ?></title>
    <?php if (!empty($description)): ?><meta name="description" content="<?= e($description) ?>"><?php endif; ?>
    <?php if ($isAdminPage): ?><meta name="robots" content="noindex"><?php endif; ?>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body data-base="<?= e(url('')) ?>"<?= $isAdmin ? ' data-csrf="' . e(App\Auth::csrf()) . '"' : '' ?>>
<header class="site-header">
    <div class="wrap">
        <a class="brand" href="<?= e(url('')) ?>"><span class="brand-mark" aria-hidden="true"></span><?= e($site) ?></a>
        <?php if ($isAdmin): ?>
            <nav class="site-nav">
                <a href="<?= e(url('admin')) ?>">Διαχείριση</a>
                <form method="post" action="<?= e(url('admin/logout')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="link">Αποσύνδεση</button>
                </form>
            </nav>
        <?php endif; ?>
    </div>
</header>

<main class="wrap">
    <?= $content ?>
</main>

<footer class="site-footer">
    <div class="wrap">
        <span><?= e($site) ?></span>
        <?php if (!$isAdmin): ?><a href="<?= e(url('admin')) ?>">Διαχείριση</a><?php endif; ?>
    </div>
</footer>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
<?php if ($isAdmin && $isAdminPage): ?><script src="<?= e(asset('js/admin.js')) ?>" defer></script><?php endif; ?>
</body>
</html>
