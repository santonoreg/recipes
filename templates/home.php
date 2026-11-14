<?php
/** @var list<array<string,mixed>> $recipes */
/** @var bool $isAdmin */
?>
<section class="hero">
    <p class="kicker">Από το βίντεο στο πιάτο</p>
    <h1>Συνταγές</h1>
    <?php if ($recipes): ?>
        <label class="search">
            <span class="sr-only">Αναζήτηση συνταγής</span>
            <input type="search" id="search" placeholder="Αναζήτηση: τίτλος ή υλικό…" autocomplete="off">
        </label>
    <?php endif; ?>
</section>

<?php if (!$recipes): ?>
    <div class="empty">
        <p>Δεν υπάρχουν ακόμη συνταγές.</p>
        <?php if ($isAdmin): ?><a class="btn primary" href="<?= e(url('admin')) ?>">Πρόσθεσε την πρώτη</a><?php endif; ?>
    </div>
<?php else: ?>
    <ul class="grid" id="grid">
        <?php foreach ($recipes as $row): ?>
            <?php
            $r = $row['recipe'];
            $names = [];
            foreach (ingredient_groups($r) as $group) {
                foreach ($group['items'] as $item) {
                    $names[] = $item['name'];
                }
            }
            $meta = recipe_meta($r);
            ?>
            <li class="card" data-search="<?= e(mb_strtolower($row['title'] . ' ' . implode(' ', $names))) ?>">
                <a href="<?= e(url('recipe/' . $row['id'])) ?>">
                    <div class="card-img">
                        <?php if ($row['poster_file'] !== ''): ?>
                            <img src="<?= e(url("media/{$row['id']}/poster")) ?>" alt="" loading="lazy">
                        <?php endif; ?>
                        <?php if ($row['video_file'] !== ''): ?><span class="play" aria-hidden="true"></span><?php endif; ?>
                    </div>
                    <div class="card-body">
                        <h2><?= e($row['title']) ?></h2>
                        <?php if ($row['description'] !== ''): ?><p><?= e($row['description']) ?></p><?php endif; ?>
                        <?php if ($meta): ?>
                            <p class="card-meta"><?= e(implode(' · ', array_map(fn ($m) => "$m[0]: $m[1]", array_slice($meta, 0, 2)))) ?></p>
                        <?php endif; ?>
                    </div>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
    <p class="empty" id="no-results" hidden>Καμία συνταγή δεν ταιριάζει.</p>
<?php endif; ?>
