/*
| « J'aime » sur les commentaires (témoignages, événements, verset du jour) — docs/fonctionnalites/jaime-commentaires.md
| Bouton : <button data-comment-like="URL" aria-pressed="true|false"> (resources/views/components/comment-like.blade.php).
| POST pour aimer, DELETE pour retirer ; le serveur renvoie { data: { likesCount, isLikedByMe } }.
| Délégation sur le document : fonctionne aussi pour les commentaires ajoutés dynamiquement.
*/
const flash = (message, type = 'success') => window.flash?.(message, type);

function render(button, { likesCount, isLikedByMe }) {
    button.setAttribute('aria-pressed', isLikedByMe ? 'true' : 'false');
    const icon = button.querySelector('[data-like-icon]');
    if (icon) {
        icon.classList.toggle('fa-solid', isLikedByMe);
        icon.classList.toggle('fa-regular', !isLikedByMe);
    }
    const count = button.querySelector('[data-like-count]');
    if (count) count.textContent = String(likesCount);
}

document.addEventListener('click', async (event) => {
    const button = event.target.closest('button[data-comment-like]');
    if (!button || button.getAttribute('aria-busy') === 'true') return;
    event.preventDefault();

    const wasLiked = button.getAttribute('aria-pressed') === 'true';
    const countEl = button.querySelector('[data-like-count]');
    const before = { likesCount: Number(countEl?.textContent || 0), isLikedByMe: wasLiked };
    // Affichage immédiat, corrigé par la réponse du serveur.
    render(button, { likesCount: Math.max(0, before.likesCount + (wasLiked ? -1 : 1)), isLikedByMe: !wasLiked });
    button.setAttribute('aria-busy', 'true');

    try {
        const response = await fetch(button.dataset.commentLike, {
            method: wasLiked ? 'DELETE' : 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
            credentials: 'same-origin',
        });
        const payload = await response.json().catch(() => null);
        if (!response.ok || !payload?.data) {
            throw new Error(payload?.message || button.dataset.likeError || '');
        }
        render(button, payload.data);
    } catch (error) {
        render(button, before);
        if (error.message) flash(error.message, 'error');
    } finally {
        button.removeAttribute('aria-busy');
    }
});
