// Συνταγές — διαχείριση: νέα συνταγή (σύνδεσμος / αρχείο / καταγραφή οθόνης) και παρακολούθηση εργασιών
(() => {
    'use strict';

    const base = document.body.dataset.base.replace(/\/$/, '');
    const csrf = document.body.dataset.csrf;
    const $ = (id) => document.getElementById(id);
    const panel = $('new-recipe');
    if (!panel) return;

    const CHUNK = 1024 * 1024; // 1 MB: χωράει στα προεπιλεγμένα όρια του php.ini
    const statusLabels = { queued: 'Σε αναμονή', running: 'Σε εξέλιξη', done: 'Έτοιμη', error: 'Απέτυχε' };

    const message = $('job-message');
    const submit = $('job-submit');
    const progress = $('upload-progress');
    let mode = 'url';
    let captured = null; // Blob από την καταγραφή οθόνης

    const say = (text, isError = false) => {
        message.textContent = text;
        message.classList.toggle('error', isError);
    };

    async function post(path, body, headers = {}) {
        const res = await fetch(base + path, {
            method: 'POST',
            body,
            headers: { 'X-CSRF-Token': csrf, Accept: 'application/json', ...headers },
            credentials: 'same-origin',
        });
        let data = {};
        try { data = await res.json(); } catch { /* κενή απάντηση */ }
        if (!res.ok) throw new Error(data.error || `Σφάλμα ${res.status}`);
        return data;
    }

    // ---------- καρτέλες ----------
    panel.querySelectorAll('[data-tab]').forEach((tab) => {
        tab.addEventListener('click', () => {
            mode = tab.dataset.tab;
            panel.querySelectorAll('[data-tab]').forEach((t) => t.setAttribute('aria-selected', String(t === tab)));
            panel.querySelectorAll('[data-panel]').forEach((p) => { p.hidden = p.dataset.panel !== mode; });
            say('');
        });
    });

    // ---------- καταγραφή οθόνης ----------
    const startBtn = $('capture-start');
    const stopBtn = $('capture-stop');
    const capStatus = $('capture-status');
    let recorder = null;
    let timer = null;

    startBtn.addEventListener('click', async () => {
        if (!navigator.mediaDevices?.getDisplayMedia || !window.MediaRecorder) {
            capStatus.textContent = 'Ο browser δεν υποστηρίζει καταγραφή οθόνης.';
            return;
        }
        let stream;
        try {
            stream = await navigator.mediaDevices.getDisplayMedia({ video: { frameRate: 15 }, audio: true });
        } catch {
            capStatus.textContent = 'Η καταγραφή ακυρώθηκε.';
            return;
        }
        const type = ['video/webm;codecs=vp9,opus', 'video/webm;codecs=vp8,opus', 'video/webm']
            .find((t) => MediaRecorder.isTypeSupported(t)) || '';
        const parts = [];
        const hasAudio = stream.getAudioTracks().length > 0;
        const started = Date.now();
        captured = null;
        recorder = new MediaRecorder(stream, type ? { mimeType: type, videoBitsPerSecond: 2500000 } : undefined);
        recorder.ondataavailable = (ev) => { if (ev.data.size) parts.push(ev.data); };
        recorder.onstop = () => {
            clearInterval(timer);
            stream.getTracks().forEach((t) => t.stop());
            captured = new Blob(parts, { type: 'video/webm' });
            startBtn.hidden = false;
            stopBtn.hidden = true;
            const mb = (captured.size / 1048576).toFixed(1);
            capStatus.textContent = `Καταγράφηκαν ${mb} MB${hasAudio ? '' : ' (χωρίς ήχο)'}. Πάτα «Βγάλε τη συνταγή».`;
        };
        stream.getVideoTracks()[0].addEventListener('ended', () => recorder.state !== 'inactive' && recorder.stop());
        recorder.start(1000);
        startBtn.hidden = true;
        stopBtn.hidden = false;
        timer = setInterval(() => {
            const s = Math.floor((Date.now() - started) / 1000);
            const mm = String(Math.floor(s / 60)).padStart(2, '0');
            const ss = String(s % 60).padStart(2, '0');
            capStatus.textContent = `🔴 Εγγραφή ${mm}:${ss}${hasAudio ? '' : ' — χωρίς ήχο: δεν τσέκαρες «κοινή χρήση ήχου»'}`;
        }, 500);
    });
    stopBtn.addEventListener('click', () => recorder && recorder.state !== 'inactive' && recorder.stop());

    // ---------- ανέβασμα σε κομμάτια ----------
    async function upload(blob, ext) {
        const uid = [...crypto.getRandomValues(new Uint8Array(16))].map((b) => b.toString(16).padStart(2, '0')).join('');
        const total = Math.max(1, Math.ceil(blob.size / CHUNK));
        progress.hidden = false;
        for (let i = 0; i < total; i++) {
            await post(`/admin/upload?uid=${uid}&ext=${ext}&i=${i}`, blob.slice(i * CHUNK, (i + 1) * CHUNK), {
                'Content-Type': 'application/octet-stream',
            });
            progress.value = Math.round(((i + 1) / total) * 100);
            say(`Ανέβασμα ${progress.value}%…`);
        }
        progress.hidden = true;
        return `${uid}.${ext}`;
    }

    // ---------- υποβολή ----------
    submit.addEventListener('click', async () => {
        const form = new FormData();
        form.set('text', $('job-text').value);
        form.set('url', $('job-url').value.trim());
        submit.disabled = true;
        try {
            if (mode === 'url') {
                if (!/^https?:\/\/\S+$/i.test(form.get('url'))) throw new Error('Δώσε έναν σύνδεσμο που ξεκινά με http:// ή https://');
            } else if (mode === 'upload') {
                const file = $('job-file').files[0];
                if (!file) throw new Error('Διάλεξε ένα αρχείο βίντεο.');
                const ext = (file.name.split('.').pop() || '').toLowerCase();
                if (!['mp4', 'm4v', 'mov', 'webm', 'mkv', 'avi'].includes(ext)) throw new Error('Δεκτά αρχεία: mp4, mov, webm, mkv, avi.');
                form.set('upload', await upload(file, ext));
            } else {
                if (!captured || !captured.size) throw new Error('Κάνε πρώτα μια καταγραφή.');
                form.set('upload', await upload(captured, 'webm'));
            }
            await post('/admin/jobs', form);
            say('Ξεκίνησε. Η πρόοδος φαίνεται παρακάτω.');
            $('job-url').value = '';
            $('job-text').value = '';
            $('job-file').value = '';
            captured = null;
            capStatus.textContent = '';
            refresh();
        } catch (err) {
            progress.hidden = true;
            say(err.message, true);
        } finally {
            submit.disabled = false;
        }
    });

    // ---------- εργασίες ----------
    const list = $('job-list');
    const empty = $('jobs-empty');
    let jobs = JSON.parse($('jobs').dataset.jobs || '[]');
    let known = new Map(jobs.map((j) => [j.id, j.status]));
    let pollTimer = null;

    function el(tag, props = {}, ...children) {
        const node = Object.assign(document.createElement(tag), props);
        node.append(...children);
        return node;
    }

    function render() {
        empty.hidden = jobs.length > 0;
        list.replaceChildren(...jobs.map((job) => {
            const top = el('div', { className: 'job-top' },
                el('span', { className: `badge ${job.status}`, textContent: statusLabels[job.status] || job.status }),
                el('span', { className: 'job-source', textContent: job.source, title: job.source }));
            if (job.status === 'done' && job.recipe_id) {
                top.append(el('a', { className: 'btn small', href: `${base}/recipe/${job.recipe_id}`, textContent: 'Προβολή' }));
            }
            if (job.status === 'done' || job.status === 'error') {
                const hide = el('button', { type: 'button', className: 'link', textContent: 'Απόκρυψη' });
                hide.addEventListener('click', async () => {
                    await post(`/admin/jobs/${job.id}/delete`, new FormData());
                    jobs = jobs.filter((j) => j.id !== job.id);
                    render();
                });
                top.append(hide);
            }
            const item = el('li', { className: 'job' }, top);
            if (job.log && job.status !== 'done') {
                const pre = el('pre', { textContent: job.log.trim() });
                item.append(pre);
                requestAnimationFrame(() => { pre.scrollTop = pre.scrollHeight; });
            }
            return item;
        }));
    }

    async function refresh() {
        clearTimeout(pollTimer);
        try {
            const res = await fetch(`${base}/admin/jobs`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (res.ok) {
                jobs = (await res.json()).jobs;
                // μόλις ολοκληρωθεί μια εργασία, ξαναφόρτωσε για να φανεί η νέα συνταγή στη λίστα
                const finished = jobs.some((j) => j.status === 'done' && known.has(j.id) && known.get(j.id) !== 'done');
                known = new Map(jobs.map((j) => [j.id, j.status]));
                if (finished) {
                    window.location.reload();
                    return;
                }
                render();
            }
        } catch { /* προσωρινό σφάλμα δικτύου — ξαναδοκιμάζουμε */ }
        if (jobs.some((j) => j.status === 'queued' || j.status === 'running')) {
            pollTimer = setTimeout(refresh, 2000);
        }
    }

    render();
    refresh();
})();
