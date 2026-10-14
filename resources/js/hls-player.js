/*
|--------------------------------------------------------------------------
| Lecture des vidéos Bunny Stream (HLS) — docs/fonctionnalites/videos-bunny-stream.md
|--------------------------------------------------------------------------
| <video data-hls="https://…/playlist.m3u8" [data-hls-fallback="…mp4"] [data-original="…mp4"]>
| Safari (iPhone, Mac) lit le HLS nativement ; ailleurs hls.js (chargé à la demande). Erreur fatale :
| MP4 de secours s'il existe (option « MP4 Fallback » de Bunny), sinon message d'erreur du lecteur.
*/

export async function initHlsPlayers(root = document) {
    const videos = [...root.querySelectorAll('video[data-hls]')];
    if (!videos.length) return;

    let Hls = null;
    for (const video of videos) {
        const url = video.dataset.hls;
        const fallback = video.dataset.hlsFallback || video.dataset.original || '';
        const useFallback = () => {
            if (fallback && video.src !== fallback) {
                video.src = fallback;
                return true;
            }
            video.parentElement?.querySelector('[data-player-error]')?.removeAttribute('hidden');
            return false;
        };

        if (video.canPlayType('application/vnd.apple.mpegurl')) {
            video.src = url;
            continue;
        }
        if (!Hls) {
            try {
                Hls = (await import('hls.js')).default;
            } catch {
                useFallback();
                continue;
            }
        }
        if (!Hls.isSupported()) {
            useFallback();
            continue;
        }
        const hls = new Hls({ capLevelToPlayerSize: true });
        hls.on(Hls.Events.ERROR, (_event, data) => {
            if (!data.fatal) return;
            if (data.type === Hls.ErrorTypes.MEDIA_ERROR) {
                hls.recoverMediaError();
                return;
            }
            hls.destroy();
            useFallback();
        });
        hls.loadSource(url);
        hls.attachMedia(video);
    }
}
