// ============================================================
//  main.js — MaisonSmart
//  Inspiré du pattern : select.value → split('_') → [key, order]
//  → tri du tableau de nœuds DOM → réinjection dans le conteneur
// ============================================================

document.addEventListener('DOMContentLoaded', () => {

    // ── 1. Navigation entre modules ──────────────────────────
    const modBtns = document.querySelectorAll('.mod-btn');
    const modules = document.querySelectorAll('.module');

    modBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const target = btn.dataset.mod;

            // Désactiver tout
            modBtns.forEach(b => b.classList.remove('active'));
            modules.forEach(m => m.classList.remove('active'));

            // Activer la cible
            btn.classList.add('active');
            const modEl = document.getElementById('mod-' + target);
            if (modEl) modEl.classList.add('active');
        });
    });

    // ── 2. Filtres module Information ─────────────────────────
    // Reprend la même logique de filtrage multi-critères que
    // le fichier recherche.php fourni en exemple
    const filterInfo = () => {
        const type   = document.getElementById('f-type')?.value   ?? '';
        const piece  = document.getElementById('f-piece')?.value  ?? '';
        const search = document.getElementById('f-search')?.value.toLowerCase() ?? '';

        const cards   = document.querySelectorAll('.info-card');
        const emptyEl = document.getElementById('info-empty');
        let visible   = 0;

        cards.forEach(card => {
            const matchType  = !type  || card.dataset.type  === type;
            const matchPiece = !piece || card.dataset.piece === piece;
            const matchSearch = !search
                || card.dataset.nom.includes(search)
                || card.querySelector('.info-card-name')?.textContent.toLowerCase().includes(search)
                || card.querySelector('.piece-tag')?.textContent.toLowerCase().includes(search);

            const show = matchType && matchPiece && matchSearch;
            card.classList.toggle('hidden', !show);
            if (show) visible++;
        });

        if (emptyEl) emptyEl.classList.toggle('hidden', visible > 0);
    };

    // Attacher les événements filtres
    ['f-type', 'f-piece', 'f-search'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener(el.tagName === 'SELECT' ? 'change' : 'input', filterInfo);
        }
    });

    // ── 3. Tri des cartes capteurs ────────────────────────────
    // Pattern identique au tri de recherchev2.js fourni :
    //   select.value.split('_') → [key, order]
    //   sort sur data-attributes → réinjection dans le conteneur
    const applySorting = () => {
        const select = document.getElementById('tri-capteurs');
        if (!select) return;

        const [key, order] = select.value.split('_');
        const container    = document.getElementById('capteurs-box');
        if (!container) return;

        const cards = Array.from(container.querySelectorAll('.capteur-card'));

        cards.sort((a, b) => {
            let av, bv;

            if (key === 'nom') {
                // Tri alphabétique
                av = a.dataset.nom ?? '';
                bv = b.dataset.nom ?? '';
                return order === 'asc'
                    ? av.localeCompare(bv)
                    : bv.localeCompare(av);
            } else {
                // Tri numérique (valeur ou statut)
                av = parseFloat(a.dataset[key] ?? 0);
                bv = parseFloat(b.dataset[key] ?? 0);
                return order === 'asc' ? av - bv : bv - av;
            }
        });

        // Réinjection dans le DOM (même pattern que recherchev2.js)
        container.innerHTML = '';
        cards.forEach(card => container.appendChild(card));
    };

    // Appliquer le tri au chargement et à chaque changement
    const triSelect = document.getElementById('tri-capteurs');
    if (triSelect) {
        triSelect.addEventListener('change', applySorting);
        applySorting(); // tri initial
    }

    // ── 4. Animation entrée des barres du graphique ───────────
    // Déclenche l'animation des barres après un court délai
    const bars = document.querySelectorAll('.bar-fill');
    setTimeout(() => {
        bars.forEach(bar => {
            bar.style.transition = 'height 0.6s ease';
        });
    }, 100);

    // ── 5. Confirmation avant actions destructrices ───────────
    document.querySelectorAll('.btn-danger').forEach(btn => {
        btn.addEventListener('click', e => {
            const confirmed = confirm('Confirmer cette action ?');
            if (!confirmed) e.preventDefault();
        });
    });

});

// ── Exposer filterInfo globalement (appelé depuis oninput HTML) ──
function filterInfo() {
    const type   = document.getElementById('f-type')?.value   ?? '';
    const piece  = document.getElementById('f-piece')?.value  ?? '';
    const search = document.getElementById('f-search')?.value.toLowerCase() ?? '';

    const cards   = document.querySelectorAll('.info-card');
    const emptyEl = document.getElementById('info-empty');
    let visible   = 0;

    cards.forEach(card => {
        const matchType   = !type  || card.dataset.type  === type;
        const matchPiece  = !piece || card.dataset.piece === piece;
        const matchSearch = !search
            || card.dataset.nom.includes(search)
            || card.querySelector('.info-card-name')?.textContent.toLowerCase().includes(search)
            || card.querySelector('.piece-tag')?.textContent.toLowerCase().includes(search);

        const show = matchType && matchPiece && matchSearch;
        card.classList.toggle('hidden', !show);
        if (show) visible++;
    });

    if (emptyEl) emptyEl.classList.toggle('hidden', visible > 0);
}

// ── Exposer applySorting globalement (appelé depuis onchange HTML) ──
function applySorting() {
    const select = document.getElementById('tri-capteurs');
    if (!select) return;

    const [key, order] = select.value.split('_');
    const container    = document.getElementById('capteurs-box');
    if (!container) return;

    const cards = Array.from(container.querySelectorAll('.capteur-card'));

    cards.sort((a, b) => {
        if (key === 'nom') {
            const av = a.dataset.nom ?? '';
            const bv = b.dataset.nom ?? '';
            return order === 'asc' ? av.localeCompare(bv) : bv.localeCompare(av);
        }
        const av = parseFloat(a.dataset[key] ?? 0);
        const bv = parseFloat(b.dataset[key] ?? 0);
        return order === 'asc' ? av - bv : bv - av;
    });

    container.innerHTML = '';
    cards.forEach(card => container.appendChild(card));
}
