/*
| Aperçus de liens (components/link-preview) : les blocs pas encore en cache côté serveur
| (`data-link-preview-url`) sont remplis par GET /liens/apercu quand ils approchent de l'écran,
| deux requêtes à la fois. Sans aperçu, le bloc est retiré. Les commentaires ajoutés sans
| rechargement sont pris en compte. Voir docs/fonctionnalites/liens-et-apercus.md
*/

const ENDPOINT = '/liens/apercu';
const results = new Map(); // url → Promise<aperçu|null>
const queue = [];
let running = 0;

function load(url) {
    if (!results.has(url)) {
        results.set(
            url,
            new Promise((resolve) => {
                queue.push(async () => {
                    try {
                        const response = await fetch(`${ENDPOINT}?url=${encodeURIComponent(url)}`, {
                            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        });
                        resolve(response.ok ? ((await response.json()).data ?? null) : null);
                    } catch {
                        resolve(null);
                    }
                });
                next();
            }),
        );
    }
    return results.get(url);
}

function next() {
    while (running < 2 && queue.length) {
        running++;
        queue
            .shift()()
            .finally(() => {
                running--;
                next();
            });
    }
}

function setText(el, text) {
    if (!el) return;
    el.textContent = text || '';
    el.hidden = !text;
}

async function fill(card) {
    const url = card.dataset.linkPreviewUrl;
    card.removeAttribute('data-link-preview-url');
    const preview = await load(url);
    if (!preview || (!preview.title && !preview.image)) {
        card.remove();
        return;
    }
    const image = card.querySelector('[data-lp-image]');
    if (preview.image && image) {
        image.src = preview.image;
        image.hidden = false;
        card.querySelector('[data-lp-icon]').hidden = true;
    }
    setText(card.querySelector('[data-lp-site]'), preview.siteName || preview.host);
    setText(card.querySelector('[data-lp-title]'), preview.title || preview.host);
    setText(card.querySelector('[data-lp-description]'), preview.description);
    card.hidden = false;
}

// Un bloc caché n'a pas de taille : on observe le texte qui le précède.
const cardsOf = new Map(); // élément observé → blocs à remplir
const visible =
    'IntersectionObserver' in window
        ? new IntersectionObserver(
              (entries) => {
                  entries.forEach((entry) => {
                      if (!entry.isIntersecting) return;
                      visible.unobserve(entry.target);
                      (cardsOf.get(entry.target) || []).forEach(fill);
                      cardsOf.delete(entry.target);
                  });
              },
              { rootMargin: '400px 0px' },
          )
        : null;

function watch(card) {
    if (card.dataset.linkPreviewWatched) return;
    card.dataset.linkPreviewWatched = '1';
    const anchor = card.previousElementSibling || card.parentElement;
    if (!visible || !anchor) {
        fill(card);
        return;
    }
    if (!cardsOf.has(anchor)) {
        cardsOf.set(anchor, []);
        visible.observe(anchor);
    }
    cardsOf.get(anchor).push(card);
}

function scan(root) {
    if (root.matches?.('[data-link-preview-url]')) watch(root);
    root.querySelectorAll?.('[data-link-preview-url]').forEach(watch);
}

// Miniature introuvable : icône de lien à la place.
document.addEventListener(
    'error',
    (e) => {
        const image = e.target;
        if (!(image instanceof HTMLImageElement) || !image.matches('[data-lp-image]')) return;
        image.hidden = true;
        const icon = image.parentElement?.querySelector('[data-lp-icon]');
        if (icon) icon.hidden = false;
    },
    true,
);

document.addEventListener('DOMContentLoaded', () => {
    scan(document);
    new MutationObserver((mutations) => {
        mutations.forEach((m) => m.addedNodes.forEach((node) => node.nodeType === 1 && scan(node)));
    }).observe(document.body, { childList: true, subtree: true });
});
