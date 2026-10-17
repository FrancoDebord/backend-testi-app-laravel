/*
|--------------------------------------------------------------------------
| Textes des scripts dans la langue de la page (docs/fonctionnalites/langues.md)
|--------------------------------------------------------------------------
| Les gabarits exposent window.I18N = @json(__('js')) (lang/fr/js.php, lang/en/js.php).
| t('app.copied') ; t('app.people_found', { count: 3 }) remplace « :count ».
| Pluriels : tn('app.people_found', n) choisit la clé « .one » (0 et 1 en français, 1 en anglais) ou « .other ».
| Clé absente : la clé elle-même est renvoyée (repère visible pendant le développement).
*/

/** Langue de la page (« fr », « en »), d'après <html lang>. */
export const lang = (document.documentElement.lang || 'fr').slice(0, 2).toLowerCase();

/** Locale des dates et nombres (« fr-FR », « en-GB »). */
export const dateLocale = lang === 'en' ? 'en-GB' : 'fr-FR';

function lookup(key) {
    let value = window.I18N || {};
    for (const part of String(key).split('.')) {
        if (value === null || typeof value !== 'object' || !(part in value)) return undefined;
        value = value[part];
    }
    return value;
}

/** Texte traduit d'une clé pointée, avec remplacement des paramètres « :nom » (plus longs d'abord). */
export function t(key, params = {}) {
    const value = lookup(key);
    let text = typeof value === 'string' ? value : key;
    Object.keys(params)
        .sort((a, b) => b.length - a.length)
        .forEach((name) => {
            text = text.split(`:${name}`).join(String(params[name] ?? ''));
        });
    return text;
}

/** Texte au singulier ou au pluriel : clés « key.one » et « key.other », paramètre :count fourni. */
export function tn(key, count, params = {}) {
    const one = lang === 'fr' ? count <= 1 : count === 1;
    return t(`${key}.${one ? 'one' : 'other'}`, { count: Number(count).toLocaleString(dateLocale), ...params });
}
