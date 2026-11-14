<?php /** @var string $error */ /** @var bool $local */ ?>
<section class="auth">
    <h1>Πρώτη ρύθμιση</h1>
    <p class="hint">Δημιούργησε τον λογαριασμό διαχειριστή. Με αυτόν θα συνδέεσαι για να προσθέτεις και να αφαιρείς συνταγές.</p>
    <?php if ($error !== ''): ?><p class="alert" role="alert"><?= e($error) ?></p><?php endif; ?>
    <?php if ($local): ?>
        <form method="post" action="<?= e(url('admin/setup')) ?>">
            <?= csrf_field() ?>
            <label>Όνομα χρήστη
                <input type="text" name="username" required autofocus autocomplete="username" value="<?= e($_POST['username'] ?? '') ?>">
            </label>
            <label>Κωδικός (τουλάχιστον 8 χαρακτήρες)
                <input type="password" name="password" required minlength="8" autocomplete="new-password">
            </label>
            <label>Κωδικός ξανά
                <input type="password" name="password2" required minlength="8" autocomplete="new-password">
            </label>
            <button type="submit" class="btn primary">Δημιουργία</button>
        </form>
    <?php endif; ?>
</section>
