<?php
/** @var array<string,mixed> $row */
/** @var array<string,mixed> $recipe */
$groups = ingredient_groups($recipe);
$showTitles = count($groups) > 1;
$meta = recipe_meta($recipe);
$upper = fn (string $s) => mb_strtoupper(strtr($s, [
    'ά' => 'α', 'έ' => 'ε', 'ή' => 'η', 'ί' => 'ι', 'ό' => 'ο', 'ύ' => 'υ', 'ώ' => 'ω', 'ΐ' => 'ϊ', 'ΰ' => 'ϋ',
    'Ά' => 'Α', 'Έ' => 'Ε', 'Ή' => 'Η', 'Ί' => 'Ι', 'Ό' => 'Ο', 'Ύ' => 'Υ', 'Ώ' => 'Ω',
]));
?>
<style>
    body { font-family: dejavusans; color: #3B3430; font-size: 10.5pt; line-height: 1.45; }
    .kicker { font-size: 8pt; font-weight: bold; color: #B4532A; letter-spacing: 1pt; }
    h1 { font-family: dejavuserif; font-size: 24pt; color: #2A2420; margin: 2pt 0 0 0; line-height: 1.2; }
    .desc { font-family: dejavuserif; font-style: italic; font-size: 11pt; color: #8A7E74; margin-top: 6pt; }
    table.meta { width: 100%; border-top: 1.2pt solid #2A2420; border-bottom: 0.6pt solid #E8DCCD; margin-top: 14pt; }
    table.meta td { padding: 8pt 10pt 8pt 0; vertical-align: top; }
    .meta-k { font-size: 7pt; font-weight: bold; color: #8A7E74; }
    .meta-v { font-family: dejavuserif; font-weight: bold; font-size: 12pt; color: #2A2420; }
    h2 { font-family: dejavuserif; font-size: 15pt; color: #2A2420; margin: 20pt 0 8pt 0;
         border-bottom: 0.6pt solid #E8DCCD; padding-bottom: 4pt; }
    .box { background-color: #FAF4EC; padding: 10pt 14pt; }
    table.ing { width: 100%; border-collapse: collapse; }
    table.ing td { padding: 3.5pt 0; vertical-align: top; border-bottom: 0.4pt solid #E8DCCD; }
    table.ing tr.last td { border-bottom: none; }
    table.ing td.qty { width: 32mm; text-align: right; font-weight: bold; color: #2A2420; padding-right: 8pt; }
    table.ing td.group { font-size: 8pt; font-weight: bold; color: #B4532A; padding-top: 10pt; border-bottom: none; }
    .muted { color: #8A7E74; }
    .accent { color: #B4532A; }
    table.steps { width: 100%; border-collapse: collapse; }
    table.steps td { padding: 7pt 0; vertical-align: top; border-bottom: 0.4pt solid #E8DCCD; font-size: 11pt; }
    table.steps tr.last td { border-bottom: none; }
    table.steps td.num { width: 14mm; font-family: dejavuserif; font-weight: bold; font-size: 16pt; color: #B4532A; }
    .tips { border-left: 2.5pt solid #B4532A; padding-left: 12pt; }
    .tips p { margin: 0 0 4pt 0; }
    .notes-title { font-size: 8pt; font-weight: bold; color: #B4532A; margin-top: 18pt; }
    .notes p { font-style: italic; font-size: 9pt; color: #8A7E74; margin: 2pt 0; }
    .legend { font-size: 8pt; color: #8A7E74; margin-top: 4pt; }
</style>

<div class="kicker">ΣΥΝΤΑΓΗ</div>
<h1><?= e($recipe['title'] ?? $row['title']) ?></h1>
<?php if (trim((string) ($recipe['description'] ?? '')) !== ''): ?>
    <div class="desc"><?= e($recipe['description']) ?></div>
<?php endif; ?>

<?php if ($meta): ?>
    <table class="meta"><tr>
        <?php foreach ($meta as [$k, $v]): ?>
            <td><div class="meta-k"><?= e($upper($k)) ?></div><div class="meta-v"><?= e($v) ?></div></td>
        <?php endforeach; ?>
    </tr></table>
<?php endif; ?>

<?php if ($groups): ?>
    <h2>Υλικά</h2>
    <div class="box">
        <table class="ing">
            <?php foreach ($groups as $gi => $group): ?>
                <?php if ($showTitles && trim((string) $group['title']) !== ''): ?>
                    <tr><td colspan="2" class="group"<?= $gi === 0 ? ' style="padding-top:0"' : '' ?>><?= e($upper($group['title'])) ?></td></tr>
                <?php endif; ?>
                <?php foreach ($group['items'] as $ii => $item): ?>
                    <?php $last = $ii === count($group['items']) - 1 && $gi === count($groups) - 1; ?>
                    <tr<?= $last ? ' class="last"' : '' ?>>
                        <td class="qty"><?= e(qty($item) ?: '–') ?></td>
                        <td><?= e($item['name']) ?><?php if (trim((string) ($item['note'] ?? '')) !== ''): ?><span class="muted">, <?= e($item['note']) ?></span><?php endif; ?><?php if (($item['source'] ?? '') === 'εκτίμηση'): ?> <span class="accent">*</span><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </table>
    </div>
    <?php if (has_estimates($recipe)): ?>
        <div class="legend"><span class="accent">*</span> εκτίμηση: η ποσότητα δεν αναφέρθηκε στο βίντεο</div>
    <?php endif; ?>
<?php endif; ?>

<h2>Εκτέλεση</h2>
<table class="steps">
    <?php $steps = $recipe['steps'] ?? []; foreach ($steps as $n => $step): ?>
        <tr<?= $n === count($steps) - 1 ? ' class="last"' : '' ?>>
            <td class="num"><?= sprintf('%02d', $n + 1) ?></td>
            <td><?= e($step) ?></td>
        </tr>
    <?php endforeach; ?>
</table>

<?php if (!empty($recipe['tips'])): ?>
    <h2>Συμβουλές</h2>
    <div class="tips">
        <?php foreach ($recipe['tips'] as $tip): ?>
            <p><span class="accent">●</span>&nbsp;&nbsp;<?= e($tip) ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (!empty($recipe['uncertainties'])): ?>
    <div class="notes-title">ΣΗΜΕΙΩΣΕΙΣ</div>
    <div class="notes">
        <?php foreach ($recipe['uncertainties'] as $note): ?>
            <p>– <?= e($note) ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
