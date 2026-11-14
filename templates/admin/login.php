<?php /** @var string $error */ ?>
<section class="auth">
    <h1>Σύνδεση διαχειριστή</h1>
    <?php if ($error !== ''): ?><p class="alert" role="alert"><?= e($error) ?></p><?php endif; ?>
    <form method="post" action="<?= e(url('admin/login')) ?>">
        <?= csrf_field() ?>
        <label>Όνομα χρήστη
            <input type="text" name="username" required autofocus autocomplete="username" value="<?= e($_POST['username'] ?? '') ?>">
        </label>
        <label>Κωδικός
            <input type="password" name="password" required autocomplete="current-password">
        </label>
        <button type="submit" class="btn primary">Σύνδεση</button>
    </form>
</section>
