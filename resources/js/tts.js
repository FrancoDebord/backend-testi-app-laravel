/*
|--------------------------------------------------------------------------
| Lecture à voix haute des témoignages écrits — docs/fonctionnalites/lecture-vocale.md
|--------------------------------------------------------------------------
| Synthèse vocale du navigateur (Web Speech API) : aucun serveur, aucune clé.
| Français ou anglais : langue détectée dans le texte, modifiable dans le menu.
| Le bloc [data-tts] reste masqué si le navigateur ne sait pas lire à voix haute.
|
| Le texte est lu phrase par phrase : Chrome coupe les énoncés longs (~15 s),
| et la pause de certains navigateurs mobiles est peu fiable. « Pause » arrête
| donc la phrase en cours et « Reprendre » la relit depuis son début.
*/

const synth = 'speechSynthesis' in window && 'SpeechSynthesisUtterance' in window ? window.speechSynthesis : null;
const MAX_CHUNK = 220;
const WORDS_PER_MINUTE = 160;
const STORAGE_KEY = 'testiapp.tts';

function loadPrefs() {
    try {
        return JSON.parse(window.localStorage.getItem(STORAGE_KEY)) || {};
    } catch {
        return {};
    }
}

function savePrefs(prefs) {
    try {
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(prefs));
    } catch {
        // Stockage indisponible (navigation privée) : réglages non retenus.
    }
}

// Découpe en phrases, puis coupe les phrases trop longues aux virgules ou aux espaces.
function splitText(text) {
    const sentences = text
        .replace(/\p{Extended_Pictographic}[\u{FE0F}\u{1F3FB}-\u{1F3FF}\u{200D}]*/gu, ' ') // emojis : non lus
        .replace(/[*_~#]{2,}|\*/g, ' ') // marques de mise en forme restées dans le texte
        .replace(/\s+/g, ' ')
        .trim()
        .match(/[^.!?…]+[.!?…]+["»”)]*|[^.!?…]+$/g) ?? [];
    const chunks = [];
    for (let sentence of sentences) {
        sentence = sentence.trim();
        while (sentence.length > MAX_CHUNK) {
            const slice = sentence.slice(0, MAX_CHUNK);
            const cut = Math.max(slice.lastIndexOf(', '), slice.lastIndexOf('; '), slice.lastIndexOf(' '));
            const at = cut > MAX_CHUNK / 2 ? cut + 1 : MAX_CHUNK;
            chunks.push(sentence.slice(0, at).trim());
            sentence = sentence.slice(at).trim();
        }
        if (/[\p{L}\p{N}]/u.test(sentence)) chunks.push(sentence); // ignore la ponctuation seule
    }
    return chunks;
}

// Langues de lecture : code de la voix par défaut et libellé du menu.
const LANGUAGES = {
    fr: { tag: 'fr-FR', label: 'Français' },
    en: { tag: 'en-US', label: 'English' },
};

// Mots très fréquents propres à chaque langue (les témoignages n'ont pas de champ « langue »).
const STOPWORDS = {
    fr: new Set('le la les des du au aux et est une un que qui dans pour pas je tu il elle nous vous ils elles ce cette mon ma mes ton ta tes son sa ses avec sur mais ont été être avait était suis dieu seigneur jésus'.split(' ')),
    en: new Set('the and is are was were of to in that it for you with my i he she we they this not have has had be been his her but at our your from what god lord jesus'.split(' ')),
};

/** Langue probable d'un texte : « en » si les mots anglais dominent nettement, sinon « fr ». */
function detectLanguage(text) {
    const words = text.toLowerCase().match(/\p{L}+/gu) ?? [];
    let fr = (text.match(/[éèêàùçœâîôû]/gi) ?? []).length / 2; // les accents penchent vers le français
    let en = 0;
    for (const w of words) {
        if (STOPWORDS.fr.has(w)) fr += 1;
        if (STOPWORDS.en.has(w)) en += 1;
    }
    return en >= 3 && en > fr * 1.5 ? 'en' : 'fr';
}

function voicesFor(lang) {
    return synth.getVoices().filter((v) => v.lang?.toLowerCase().replace('_', '-').startsWith(lang));
}

function initTts(box) {
    const source = document.getElementById(box.dataset.ttsSource);
    if (!source) return;

    // Texte lu : titre, corps, puis le verset (comme l'application mobile).
    const withStop = (s) => (s && !/[.!?…:;]$/.test(s) ? `${s}.` : s);
    const title = withStop((box.dataset.ttsTitle || '').trim());
    const verse = withStop((box.dataset.ttsVerse || '').trim());
    const body = source.textContent || '';
    const chunks = splitText([title, body, verse].filter(Boolean).join(' '));
    if (!chunks.length) return;
    // Langue : data-tts-lang si le serveur la connaît, sinon détectée dans le texte ; modifiable dans le menu.
    const detected = LANGUAGES[box.dataset.ttsLang] ? box.dataset.ttsLang : detectLanguage([title, body].join(' '));

    const playBtn = box.querySelector('[data-tts-play]');
    const playLabel = box.querySelector('[data-tts-play-label]');
    const playIcon = playBtn.querySelector('i');
    const stopBtn = box.querySelector('[data-tts-stop]');
    const rateSelect = box.querySelector('[data-tts-rate]');
    const voiceSelect = box.querySelector('[data-tts-voice]');
    const langSelect = box.querySelector('[data-tts-lang-select]');
    const status = box.querySelector('[data-tts-status]');
    const progressWrap = box.querySelector('[data-tts-progress-wrap]');
    const progress = box.querySelector('[data-tts-progress]');
    const current = box.querySelector('[data-tts-current]');

    const prefs = loadPrefs();
    prefs.voices ??= prefs.voice ? { fr: prefs.voice } : {}; // voix retenue pour chaque langue
    delete prefs.voice;
    langSelect.value = detected;
    const lang = () => langSelect.value;
    const words = body.trim().split(/\s+/).length;
    let index = 0;
    let state = 'idle'; // idle | playing | paused
    let token = 0; // invalide les fins d'énoncé d'une lecture annulée

    if (prefs.rate && [...rateSelect.options].some((o) => o.value === String(prefs.rate))) {
        rateSelect.value = String(prefs.rate);
    }

    const durationText = () => {
        const minutes = Math.max(1, Math.round(words / (WORDS_PER_MINUTE * Number(rateSelect.value))));
        return `Environ ${minutes} min d'écoute`;
    };

    const render = () => {
        const labels = { idle: 'Écouter le témoignage', playing: 'Pause', paused: 'Reprendre' };
        const icons = { idle: 'fa-solid fa-volume-high', playing: 'fa-solid fa-pause', paused: 'fa-solid fa-play' };
        playLabel.textContent = labels[state];
        playIcon.className = icons[state];
        playBtn.setAttribute('aria-pressed', state === 'playing' ? 'true' : 'false');
        stopBtn.hidden = state === 'idle';
        progressWrap.hidden = state === 'idle';
        progress.style.width = `${Math.round((index / chunks.length) * 100)}%`;
        current.hidden = state === 'idle';
        current.textContent = state === 'idle' ? '' : chunks[index] ?? '';
        status.textContent = state === 'idle' ? durationText() : state === 'paused' ? 'En pause' : 'Lecture en cours…';
    };

    // Voix de la langue choisie : chargées de façon asynchrone par certains navigateurs (événement voiceschanged).
    const fillVoices = () => {
        const voices = voicesFor(lang());
        const current = prefs.voices[lang()] || '';
        voiceSelect.replaceChildren(
            ...voices.map((v) => {
                const option = new Option(v.name.replace(/^(Microsoft|Google)\s+/, ''), v.voiceURI);
                option.selected = v.voiceURI === current;
                return option;
            }),
        );
        if (!voices.some((v) => v.voiceURI === current)) {
            const tag = LANGUAGES[lang()].tag;
            const preferred = voices.find((v) => v.lang === tag && v.localService) ?? voices.find((v) => v.lang === tag) ?? voices[0];
            if (preferred) voiceSelect.value = preferred.voiceURI;
        }
        voiceSelect.hidden = voices.length < 2;
    };

    const speak = () => {
        const myToken = ++token;
        const utterance = new SpeechSynthesisUtterance(chunks[index]);
        const voice = voicesFor(lang()).find((v) => v.voiceURI === voiceSelect.value);
        utterance.lang = voice?.lang ?? LANGUAGES[lang()].tag;
        if (voice) utterance.voice = voice;
        utterance.rate = Number(rateSelect.value);
        utterance.onend = () => {
            if (myToken !== token || state !== 'playing') return;
            index += 1;
            if (index >= chunks.length) {
                stop();
                status.textContent = 'Lecture terminée';
                return;
            }
            render();
            speak();
        };
        utterance.onerror = (e) => {
            if (myToken !== token || e.error === 'interrupted' || e.error === 'canceled') return;
            state = 'paused';
            render();
            window.flash?.('La lecture à voix haute a été interrompue. Appuyez sur « Reprendre ».', 'warning');
        };
        synth.speak(utterance);
    };

    const play = () => {
        synth.cancel(); // une seule lecture à la fois (autre onglet, lecture précédente)
        state = 'playing';
        render();
        speak();
    };

    const pause = () => {
        token += 1;
        synth.cancel();
        state = 'paused';
        render();
    };

    const stop = () => {
        token += 1;
        synth.cancel();
        state = 'idle';
        index = 0;
        render();
    };

    playBtn.addEventListener('click', () => (state === 'playing' ? pause() : play()));
    stopBtn.addEventListener('click', stop);

    // Un changement de vitesse ou de voix s'applique tout de suite : la phrase en cours est relue.
    const onSettingChange = () => {
        if (voiceSelect.value) prefs.voices[lang()] = voiceSelect.value;
        prefs.rate = Number(rateSelect.value);
        savePrefs(prefs);
        if (state === 'playing') play();
        else render();
    };
    rateSelect.addEventListener('change', onSettingChange);
    voiceSelect.addEventListener('change', onSettingChange);
    // Changer de langue recharge la liste des voix, sans retenir la langue : elle dépend du témoignage.
    langSelect.addEventListener('change', () => {
        fillVoices();
        if (state === 'playing') play();
    });

    // Quitter la page arrête la lecture (certains navigateurs continuent sinon).
    window.addEventListener('pagehide', () => {
        token += 1;
        synth.cancel();
    });

    fillVoices();
    synth.addEventListener?.('voiceschanged', fillVoices);
    render();
    box.hidden = false;
}

document.addEventListener('DOMContentLoaded', () => {
    if (!synth) return;
    synth.cancel(); // lecture restée active après un rechargement
    document.querySelectorAll('[data-tts]').forEach(initTts);
});
