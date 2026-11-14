<?php
/** @var array<string,mixed> $row */
/** @var array<string,mixed> $recipe */
/** @var bool $isAdmin */
$groups = ingredient_groups($recipe);
$showTitles = count($groups) > 1;
$steps = $recipe['steps'] ?? [];
$hasVideo = $row['video_file'] !== '';
$poster = $row['poster_file'] !== '' ? url("media/{$row['id']}/poster") : '';
$sourceName = $row['uploader'] !== '' ? $row['uploader'] : 'αρχικό post';

$stats = [];
foreach ([['Προετοιμασία', 'prep_time', 'clock'], ['Μαγείρεμα', 'cook_time', 'flame'], ['Συνολικά', 'total_time', 'timer'], ['Μερίδες', 'servings', 'users']] as [$label, $key, $ico]) {
    $value = trim((string) ($recipe[$key] ?? ''));
    if ($value !== '') {
        $stats[] = [$label, $value, $ico];
    }
}
$stats[] = ['Υλικά', (string) ingredient_count($recipe), 'basket'];
$stats[] = ['Βήματα', (string) count($steps), 'list'];
?>
<article class="recipe">
    <header class="recipe-hero<?= $poster !== '' ? ' has-img' : '' ?>">
        <?php if ($poster !== ''): ?><img class="no-print" src="<?= e($poster) ?>" alt=""><?php endif; ?>
        <div class="recipe-hero-text">
            <p class="kicker">Συνταγή</p>
            <h1><?= e($row['title']) ?></h1>
            <?php if ($row['description'] !== ''): ?><p class="lead"><?= e($row['description']) ?></p><?php endif; ?>
        </div>
    </header>

    <div class="statbar">
        <dl class="stats">
            <?php foreach ($stats as [$label, $value, $ico]): ?>
                <div>
                    <span class="stat-icon"><?= icon($ico) ?></span>
                    <dt><?= e($label) ?></dt>
                    <dd><?= e($value) ?></dd>
                </div>
            <?php endforeach; ?>
        </dl>
        <div class="actions no-print">
            <button type="button" class="btn" data-print><?= icon('printer') ?>Εκτύπωση</button>
            <a class="btn primary" href="<?= e(url("recipe/{$row['id']}/pdf")) ?>"><?= icon('download') ?>Κατέβασμα PDF</a>
        </div>
    </div>

    <div class="recipe-cols">
        <?php if ($groups): ?>
            <section class="ingredients">
                <h2>Υλικά</h2>
                <?php foreach ($groups as $group): ?>
                    <?php if ($showTitles && trim((string) $group['title']) !== ''): ?>
                        <h3><?= e($group['title']) ?></h3>
                    <?php endif; ?>
                    <ul class="ing-list">
                        <?php foreach ($group['items'] as $item): ?>
                            <?php $note = trim((string) ($item['note'] ?? '')); ?>
                            <li>
                                <label>
                                    <input type="checkbox" class="no-print">
                                    <span class="name"><?= e($item['name']) ?><?php if (($item['source'] ?? '') === 'εκτίμηση'): ?> <span class="star" title="Εκτίμηση">*</span><?php endif; ?><?php if ($note !== ''): ?><small><?= e($note) ?></small><?php endif; ?></span>
                                    <span class="detail"><?= e(qty($item)) ?></span>
                                </label>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endforeach; ?>
                <?php if (has_estimates($recipe)): ?>
                    <p class="legend"><span class="star">*</span> εκτίμηση: η ποσότητα δεν αναφέρθηκε στο βίντεο</p>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <section class="method">
            <h2>Εκτέλεση</h2>
            <ol class="step-list">
                <?php foreach ($steps as $n => $step): ?>
                    <li>
                        <span class="num"><?= $n + 1 ?></span>
                        <div>
                            <p><?= e($step) ?></p>
                            <?php if (($t = step_time((string) $step)) !== ''): ?>
                                <span class="time-chip"><?= icon('timer') ?><?= e($t) ?></span>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ol>

            <?php if (!empty($recipe['tips'])): ?>
                <div class="tips">
                    <h3>Συμβουλές</h3>
                    <ul>
                        <?php foreach ($recipe['tips'] as $tip): ?><li><?= e($tip) ?></li><?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!empty($recipe['uncertainties'])): ?>
                <div class="notes">
                    <h3>Σημειώσεις</h3>
                    <ul>
                        <?php foreach ($recipe['uncertainties'] as $note): ?><li><?= e($note) ?></li><?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <?php if ($hasVideo): ?>
        <section class="video-section no-print">
            <h2>Βίντεο</h2>
            <video controls preload="metadata" playsinline
                   <?= $poster !== '' ? 'poster="' . e($poster) . '"' : '' ?>
                   src="<?= e(url("media/{$row['id']}/video")) ?>"></video>
            <?php if ($row['source_url'] !== ''): ?>
                <p class="source">Πηγή: <a href="<?= e($row['source_url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($sourceName) ?></a></p>
            <?php endif; ?>
        </section>
    <?php elseif ($row['source_url'] !== ''): ?>
        <p class="source no-print">Πηγή: <a href="<?= e($row['source_url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($sourceName) ?></a></p>
    <?php endif; ?>
    <p class="print-only source"><?= e($row['source_url']) ?></p>

    <footer class="recipe-foot no-print">
        <a href="<?= e(url('')) ?>">← Όλες οι συνταγές</a>
        <?php if ($isAdmin): ?>
            <form method="post" action="<?= e(url("admin/recipes/{$row['id']}/delete")) ?>"
                  data-confirm="Να αφαιρεθεί οριστικά η συνταγή «<?= e($row['title']) ?>» μαζί με το βίντεό της;">
                <?= csrf_field() ?>
                <button type="submit" class="btn danger small"><?= icon('trash') ?>Αφαίρεση συνταγής</button>
            </form>
        <?php endif; ?>
    </footer>
</article>
