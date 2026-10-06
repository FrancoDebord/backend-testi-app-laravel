# Lecture à voix haute des témoignages écrits

Un témoignage écrit peut être écouté plutôt que lu : pratique pour les personnes qui n'ont pas le temps de lire, conduisent, ou lisent difficilement. La voix est produite **sur l'appareil** (synthèse vocale du navigateur ou du téléphone) : aucun traitement côté serveur, aucune clé d'API, aucun coût, aucune donnée envoyée.

## Langue : français ou anglais

Les témoignages n'ont pas de champ « langue » : elle est **déduite du texte** (titre et corps) en comptant les mots les plus fréquents de chaque langue (*le, la, et, est, que…* / *the, and, is, was, of…*), les lettres accentuées comptant pour le français. Le texte est lu en anglais seulement si les mots anglais dominent nettement (au moins 3, et 1,5 fois plus que les mots français) : quelques mots anglais dans un témoignage français (*worship*, *meeting*) ne changent pas la voix.

- **Site** : français par défaut quand le texte ne permet pas de décider. Menu **Français / English** pour corriger ; la liste des voix suit la langue choisie (voix `fr-*` ou `en-*` : par exemple anglais américain et britannique). La langue choisie n'est pas retenue (elle dépend du témoignage) ; la voix est retenue pour chaque langue. Un attribut `data-tts-lang="fr|en"` sur le bloc imposerait la langue, si le serveur la connaissait un jour.
- **Application** : `detectTextLanguage()` (`lib/services/tts_service.dart`, même règle) ; si le texte ne permet pas de décider (texte très court), langue de l'application. Voix `fr-FR` ou `en-US`.

Les libellés du bandeau du site restent en français (le site n'est pas traduit).

## Ce qui est lu

Le titre, le texte du témoignage, puis le verset biblique et sa référence s'il y en a un. Les emojis et les marques de mise en forme (`**`, `*`…) ne sont pas lus.

Le texte est lu **phrase par phrase** (morceaux de 220 caractères au plus, coupés aux virgules ou aux espaces si une phrase est trop longue). Raisons : Chrome interrompt les énoncés longs (environ 15 s) et Android limite la longueur d'un énoncé ; la pause de certains navigateurs mobiles est aussi peu fiable. « Pause » arrête donc la phrase en cours, et « Reprendre » la relit depuis son début.

## Site (page d'un témoignage texte)

Bandeau **« Écouter le témoignage »** en haut de la carte du texte (`resources/views/videos/partials/watch.blade.php`, script `resources/js/tts.js`) :

| Élément | Comportement |
|---|---|
| Bouton principal | Écouter → Pause → Reprendre |
| Arrêter | Revient au début (visible pendant la lecture ou la pause) |
| Vitesse | 0,75× à 2× ; appliquée tout de suite (la phrase en cours est relue) |
| Langue | Français ou English, détectée dans le texte (voir plus haut) |
| Voix | Voix de l'appareil pour la langue choisie, liste affichée s'il y en a au moins deux ; par défaut la voix locale `fr-FR` ou `en-US` |
| État | « Environ N min d'écoute » (160 mots par minute à 1×), « Lecture en cours… », « En pause », « Lecture terminée » (zone `aria-live`) |
| Phrase en cours et barre de progression | Visibles pendant la lecture |

- Vitesse et voix (une par langue) retenues dans le navigateur (`localStorage`, clé `testiapp.tts` : `{ rate, voices: { fr, en } }`).
- Une seule lecture à la fois ; quitter ou recharger la page arrête la lecture.
- Si le navigateur ne propose pas la synthèse vocale, le bandeau reste masqué (aucune erreur). Sans voix installée pour la langue, le navigateur utilise sa voix par défaut avec la langue `fr-FR` ou `en-US`.
- **Pas de démarrage automatique sur le site** : les navigateurs refusent le son sans geste de l'utilisateur.

Attributs HTML : `data-tts` (bloc, masqué au départ), `data-tts-source` (id de l'élément du texte), `data-tts-title`, `data-tts-verse`, `data-tts-lang` (facultatif) ; commandes `data-tts-play`, `data-tts-lang-select`, `data-tts-play-label`, `data-tts-stop`, `data-tts-rate`, `data-tts-voice`, `data-tts-status`, `data-tts-current`, `data-tts-progress-wrap`, `data-tts-progress`.

## Application mobile

Carte **« Écouter ce témoignage »** sur le détail d'un témoignage texte (`lib/features/testimony/widgets/tts_listen_card.dart`, contrôleur `lib/features/testimony/providers/tts_provider.dart`, moteur `lib/services/tts_service.dart`, paquet `flutter_tts`) :

- lecture / pause / arrêt, vitesse 0,75× · 1× · 1,25×, phrase en cours surlignée ;
- option **« Lecture automatique »** (menu de la carte, désactivée par défaut) : la lecture démarre dès l'ouverture d'un témoignage texte (pas sur la version web de l'application, pour la raison ci-dessus) ;
- un seul son à la fois : un témoignage audio en cours est mis en pause, et ouvrir un audio ou une vidéo arrête la lecture vocale ;
- langue de la voix selon la langue du témoignage (voir plus haut), sinon celle de l'application.

## Vérifications

- Site : test dans Chrome avec un moteur simulé (ordre des phrases, pause sans lecture parasite, reprise à la nouvelle vitesse, fin de lecture, passage en anglais et voix retenue par langue) ; rendu à 390 et 1440 px.
- Application : `test/tts_chunking_test.dart` (découpage, lecture, pause, vitesse, un seul témoignage à la fois, détection de la langue) et `test/testimony_layout_test.dart` (lecture automatique).
