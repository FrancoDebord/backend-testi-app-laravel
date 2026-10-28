/*
 * Service worker du site My Testimony (docs/fonctionnalites/pwa.md).
 *
 * Volontairement prudent : les pages contiennent l'état de la personne connectée (réactions, jeton CSRF),
 * elles ne sont donc JAMAIS mises en cache. Seuls sont gardés :
 *   - les fichiers compilés /build/assets/* (noms avec empreinte, donc immuables) : cache d'abord ;
 *   - les icônes /icons/* : cache, mis à jour en arrière-plan ;
 *   - la page /offline.html, affichée quand une page ne peut pas être chargée faute de réseau.
 * Jamais l'API, les envois de formulaires (POST…), les médias ni les autres domaines.
 *
 * Changer VERSION vide les anciens caches au prochain chargement.
 */
const VERSION = 'v1';
const STATIC_CACHE = `mytestimony-static-${VERSION}`;
const OFFLINE_URL = '/offline.html';
const PRECACHE = [OFFLINE_URL, '/icons/pwa/icon-192.png', '/icons/arise-shine-krea.png'];
// Chaque compilation crée de nouveaux noms : au-delà, les plus anciens fichiers compilés sont retirés.
const MAX_BUILD_FILES = 40;

async function putBuildFile(request, response) {
    const cache = await caches.open(STATIC_CACHE);
    await cache.put(request, response);
    const builds = (await cache.keys()).filter((r) => new URL(r.url).pathname.startsWith('/build/assets/'));
    await Promise.all(builds.slice(0, Math.max(0, builds.length - MAX_BUILD_FILES)).map((r) => cache.delete(r)));
}

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then((cache) => cache.addAll(PRECACHE.map((url) => new Request(url, { cache: 'reload' }))))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys
                .filter((key) => key.startsWith('mytestimony-') && key !== STATIC_CACHE)
                .map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET' || request.headers.has('range')) return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    // Pages : toujours le réseau ; sans réseau, la page « hors ligne ».
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => caches.match(OFFLINE_URL)),
        );
        return;
    }

    // Fichiers compilés (empreinte dans le nom) : cache d'abord.
    if (url.pathname.startsWith('/build/assets/')) {
        event.respondWith(
            caches.match(request).then((cached) => cached || fetch(request).then((response) => {
                if (response.ok) event.waitUntil(putBuildFile(request, response.clone()));
                return response;
            })),
        );
        return;
    }

    // Icônes : la version en cache tout de suite, mise à jour en arrière-plan.
    if (url.pathname.startsWith('/icons/')) {
        event.respondWith(
            caches.open(STATIC_CACHE).then((cache) => cache.match(request).then((cached) => {
                const network = fetch(request).then((response) => {
                    if (response.ok) cache.put(request, response.clone());
                    return response;
                }).catch(() => cached);
                return cached || network;
            })),
        );
    }
    // Tout le reste (API, /storage, médias…) : le navigateur, sans le service worker.
});
