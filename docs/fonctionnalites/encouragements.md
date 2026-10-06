# Messages pour inciter à témoigner

## But

Rappeler à chacun que témoigner rend gloire à Dieu et fortifie les autres : des **versets** (Psaume 78:4, Apocalypse 12:11, Marc 5:19…) et des **invitations** sont affichés sur les pages et insérés de temps en temps dans les fils de témoignages, avec un bouton **Témoigner**.

## Contenu

`config/encouragements.php` (source unique du site) :

- `verses` : 14 versets (Louis Segond 1910), `{ ref, text }` ;
- `calls` : 4 invitations sans verset ;
- `feed_every` : un message tous les **8** témoignages dans un fil.

L'application reprend la même liste dans `lib/shared/content/encouragements.dart` (avec trois versets sur les paroles prophétiques : Habakuk 2:3, Nombres 23:19, 1 Timothée 1:18). **Modifier les deux fichiers ensemble.**

Choix du message : versets et invitations en alternance (deux versets, une invitation), selon la position dans le fil et le jour, pour varier sans être aléatoire.

## Où

| Endroit | Site | Application |
|---|---|---|
| Fils de témoignages (un message tous les 8) | Accueil, Explorer, Vidéos, Mon fil (`encourage` des listes) | Accueil (« Pour vous »), Mon fil |
| Bandeau | Accueil, Publier (Psaume 66:16), Mes témoignages (vide), page d'un événement commencé | Publier (Psaume 66:16), page d'un événement (Apocalypse 12:11, « Raconter ce que j'ai vécu »), Paroles prophétiques (Habakuk 2:3) |

Bloc détaillé en accordéon (raisons et versets) : [Pourquoi témoigner ?](pourquoi-temoigner.md).

Composants : site `resources/views/components/encouragement.blade.php` (`<x-encouragement variant="banner|feed" … />`) ; application `EncouragementCard` (`lib/shared/widgets/encouragement_card.dart`).
