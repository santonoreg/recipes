<?php
/** @var array<string,mixed> $row */
/** @var array<string,mixed> $recipe */
/** @var bool $isAdmin */
$groups = ingredient_groups($recipe);
$showTitles = count($groups) > 1;
$meta = recipe_meta($recipe);
$hasVideo = $row['video_file'] !== '';
$poster = $row['poster_file'] !== '' ? url("media/{$row['id']}/poster") : '';
?>
<article class="recipe<?= $hasVideo ? ' has-video' : '' ?>">
    <header class="recipe-head">
        <p class="kicker">Συνταγή</p>
        <h1><?= e($row['title']) ?></h1>
        <?php if ($row['description'] !== ''): ?><p class="lead"><?= e($row['description']) ?></p><?php endif; ?>

        <?php if ($meta): ?>
            <dl class="meta">
                <?php foreach ($meta as [$k, $v]): ?>
                    <div><dt><?= e($k) ?></dt><dd><?= e($v) ?></dd></div>
                <?php endforeach; ?>
            </dl>
        <?php endif; ?>

        <div class="actions no-print">
            <a class="btn primary" href="<?= e(url("recipe/{$row['id']}/pdf")) ?>">Κατέβασμα PDF</a>
            <button type="button" class="btn" data-print>Εκτύπωση</button>
            <?php if ($isAdmin): ?>
                <form method="post" action="<?= e(url("admin/recipes/{$row['id']}/delete")) ?>"
                      data-confirm="Να αφαιρεθεί οριστικά η συνταγή «<?= e($row['title']) ?>» μαζί με το βίντεό της;">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn danger">Αφαίρεση</button>
                </form>
            <?php endif; ?>
        </div>
    </header>

    <div class="recipe-body">
        <?php if ($hasVideo): ?>
            <aside class="recipe-video no-print">
                <video controls preload="metadata" playsinline
                       <?= $poster !== '' ? 'poster="' . e($poster) . '"' : '' ?>
                       src="<?= e(url("media/{$row['id']}/video")) ?>"></video>
                <?php if ($row['source_url'] !== ''): ?>
                    <p class="source">
                        Πηγή:
                        <a href="<?= e($row['source_url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($row['uploader'] !== '' ? $row['uploader'] : 'αρχικό post') ?></a>
                    </p>
                <?php endif; ?>
            </aside>
        <?php endif; ?>

        <div class="recipe-text">
            <?php if ($groups): ?>
                <section class="ingredients">
                    <h2>Υλικά</h2>
                    <div class="box">
                        <?php foreach ($groups as $group): ?>
                            <?php if ($showTitles && trim((string) $group['title']) !== ''): ?>
                                <h3><?= e($group['title']) ?></h3>
                            <?php endif; ?>
                            <ul>
                                <?php foreach ($group['items'] as $item): ?>
                                    <li>
                                        <label>
                                            <input type="checkbox" class="no-print">
                                            <span class="qty"><?= e(qty($item) ?: '–') ?></span>
                                            <span class="name"><?= e($item['name']) ?><?php if (trim((string) ($item['note'] ?? '')) !== ''): ?><span class="note">, <?= e($item['note']) ?></span><?php endif; ?><?php if (($item['source'] ?? '') === 'εκτίμηση'): ?> <span class="star" title="Εκτίμηση">*</span><?php endif; ?></span>
                                        </label>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endforeach; ?>
                    </div>
                    <?php if (has_estimates($recipe)): ?>
                        <p class="legend"><span class="star">*</span> εκτίμηση: η ποσότητα δεν αναφέρθηκε στο βίντεο</p>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <section class="steps">
                <h2>Εκτέλεση</h2>
                <ol>
                    <?php foreach ($recipe['steps'] ?? [] as $step): ?>
                        <li><?= e($step) ?></li>
                    <?php endforeach; ?>
                </ol>
            </section>

            <?php if (!empty($recipe['tips'])): ?>
                <section class="tips">
                    <h2>Συμβουλές</h2>
                    <ul>
                        <?php foreach ($recipe['tips'] as $tip): ?><li><?= e($tip) ?></li><?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>

            <?php if (!empty($recipe['uncertainties'])): ?>
                <section class="notes">
                    <h3>Σημειώσεις</h3>
                    <ul>
                        <?php foreach ($recipe['uncertainties'] as $note): ?><li><?= e($note) ?></li><?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>

            <?php if (!$hasVideo && $row['source_url'] !== ''): ?>
                <p class="source">Πηγή: <a href="<?= e($row['source_url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($row['uploader'] !== '' ? $row['uploader'] : 'αρχικό post') ?></a></p>
            <?php endif; ?>
            <p class="print-only source"><?= e($row['source_url']) ?></p>
        </div>
    </div>

    <p class="back no-print"><a href="<?= e(url('')) ?>">← Όλες οι συνταγές</a></p>
</article>
