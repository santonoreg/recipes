// Συνταγές — δημόσιες σελίδες: αναζήτηση, εκτύπωση, επιβεβαίωση αφαίρεσης
(() => {
    'use strict';

    // Αναζήτηση στην αρχική (τίτλος + υλικά), χωρίς τόνους
    const fold = (s) => s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/ς/g, 'σ');
    const search = document.getElementById('search');
    if (search) {
        const cards = [...document.querySelectorAll('#grid .card')];
        const none = document.getElementById('no-results');
        cards.forEach((c) => { c.dataset.search = fold(c.dataset.search || ''); });
        search.addEventListener('input', () => {
            const words = fold(search.value).split(/\s+/).filter(Boolean);
            let shown = 0;
            cards.forEach((c) => {
                const ok = words.every((w) => c.dataset.search.includes(w));
                c.hidden = !ok;
                if (ok) shown++;
            });
            none.hidden = shown > 0;
        });
    }

    // Εκτύπωση
    document.querySelectorAll('[data-print]').forEach((b) => b.addEventListener('click', () => window.print()));

    // Επιβεβαίωση πριν από αφαίρεση
    document.querySelectorAll('form[data-confirm]').forEach((f) => {
        f.addEventListener('submit', (ev) => {
            if (!window.confirm(f.dataset.confirm)) ev.preventDefault();
        });
    });
})();
