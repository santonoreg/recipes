<?php
/** @var list<array<string,mixed>> $recipes */
/** @var list<array<string,mixed>> $jobs */
/** @var bool $whisper */
/** @var bool $apiKey */
?>
<section class="admin-head">
    <p class="kicker">Διαχείριση</p>
    <h1>Νέα συνταγή</h1>
</section>

<?php if (!$apiKey): ?>
    <p class="alert" role="alert">Λείπει το <code>ANTHROPIC_API_KEY</code> από το αρχείο <code>.env</code>. Χωρίς αυτό δεν βγαίνει συνταγή.</p>
<?php endif; ?>
<?php if (!$whisper): ?>
    <p class="alert soft">Δεν βρέθηκε Python με Whisper (<code>PYTHON_BIN</code> στο <code>.env</code>). Οι συνταγές θα βγαίνουν μόνο από το κείμενο του post και την εικόνα, χωρίς την ομιλία.</p>
<?php endif; ?>

<section class="panel" id="new-recipe">
    <div class="tabs" role="tablist">
        <button type="button" role="tab" aria-selected="true" data-tab="url">Σύνδεσμος post</button>
        <button type="button" role="tab" aria-selected="false" data-tab="upload">Αρχείο βίντεο</button>
        <button type="button" role="tab" aria-selected="false" data-tab="capture">Καταγραφή οθόνης</button>
    </div>

    <div class="tab-panel" data-panel="url">
        <label>Σύνδεσμος post ή βίντεο
            <input type="url" id="job-url" placeholder="https://www.facebook.com/…" autocomplete="off">
        </label>
    </div>

    <div class="tab-panel" data-panel="upload" hidden>
        <label>Βίντεο από τον υπολογιστή (mp4, mov, webm, mkv, avi)
            <input type="file" id="job-file" accept="video/*,.mkv">
        </label>
        <p class="hint">Χρήσιμο όταν το post είναι ιδιωτικό και δεν κατεβαίνει μόνο του.</p>
    </div>

    <div class="tab-panel" data-panel="capture" hidden>
        <ol class="hint">
            <li>Άνοιξε το post σε άλλη καρτέλα του browser.</li>
            <li>Πάτα <strong>Έναρξη καταγραφής</strong>, διάλεξε εκείνη την καρτέλα και τσέκαρε «Κοινή χρήση ήχου καρτέλας».</li>
            <li>Παίξε το βίντεο <strong>με ήχο</strong>. Όταν τελειώσει, γύρνα εδώ και πάτα <strong>Τέλος</strong>.</li>
        </ol>
        <div class="capture">
            <button type="button" class="btn" id="capture-start">● Έναρξη καταγραφής</button>
            <button type="button" class="btn danger" id="capture-stop" hidden>■ Τέλος</button>
            <span id="capture-status" aria-live="polite"></span>
        </div>
    </div>

    <label>Κείμενο post (προαιρετικό)
        <textarea id="job-text" rows="4" placeholder="Αν τα υλικά είναι γραμμένα στο post και δεν τα βρει μόνο του, κάνε επικόλληση εδώ."></textarea>
    </label>

    <div class="submit-row">
        <button type="button" class="btn primary" id="job-submit">Βγάλε τη συνταγή</button>
        <span id="job-message" role="status" aria-live="polite"></span>
    </div>
    <progress id="upload-progress" max="100" value="0" hidden></progress>
</section>

<section id="jobs" data-jobs='<?= e(json_encode(array_map(App\App::jobView(...), $jobs), JSON_UNESCAPED_UNICODE)) ?>'>
    <h2>Εργασίες</h2>
    <ul class="jobs" id="job-list"></ul>
    <p class="hint" id="jobs-empty">Καμία εργασία ακόμη.</p>
</section>

<section>
    <h2>Συνταγές <span class="count"><?= count($recipes) ?></span></h2>
    <?php if (!$recipes): ?>
        <p class="hint">Δεν υπάρχουν ακόμη συνταγές.</p>
    <?php else: ?>
        <ul class="admin-list">
            <?php foreach ($recipes as $row): ?>
                <li>
                    <div class="thumb">
                        <?php if ($row['poster_file'] !== ''): ?>
                            <img src="<?= e(url("media/{$row['id']}/poster")) ?>" alt="" loading="lazy">
                        <?php endif; ?>
                    </div>
                    <div class="info">
                        <a href="<?= e(url('recipe/' . $row['id'])) ?>"><?= e($row['title']) ?></a>
                        <small><?= e(greek_date((string) $row['created_at'])) ?><?= $row['uploader'] !== '' ? ' · ' . e($row['uploader']) : '' ?></small>
                    </div>
                    <form method="post" action="<?= e(url("admin/recipes/{$row['id']}/delete")) ?>"
                          data-confirm="Να αφαιρεθεί οριστικά η συνταγή «<?= e($row['title']) ?>» μαζί με το βίντεό της;">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn danger small">Αφαίρεση</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
