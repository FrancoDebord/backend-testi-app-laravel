<?php

/*
|--------------------------------------------------------------------------
| Messages pour inciter à témoigner (versets Louis Segond 1910)
|--------------------------------------------------------------------------
| Affichés sur les pages et insérés de temps en temps dans les fils de témoignages.
| La même liste est reprise dans l'application (lib/shared/content/encouragements.dart) :
| la modifier aux deux endroits. Voir docs/fonctionnalites/encouragements.md
*/

return [
    // Un message tous les N témoignages dans un fil.
    'feed_every' => 8,

    'verses' => [
        ['ref' => 'Apocalypse 12:11', 'text' => "Ils l'ont vaincu à cause du sang de l'agneau et à cause de la parole de leur témoignage, et ils n'ont pas aimé leur vie jusqu'à craindre la mort."],
        ['ref' => 'Psaume 78:4', 'text' => "Nous ne les cacherons point à leurs enfants; nous dirons à la génération future les louanges de l'Éternel, et sa puissance, et les prodiges qu'il a opérés."],
        ['ref' => 'Psaume 66:16', 'text' => "Venez, écoutez, vous tous qui craignez Dieu, et je raconterai ce qu'il a fait à mon âme."],
        ['ref' => 'Marc 5:19', 'text' => "Va dans ta maison, vers les tiens, et raconte-leur tout ce que le Seigneur t'a fait, et comment il a eu pitié de toi."],
        ['ref' => 'Psaume 105:1-2', 'text' => "Louez l'Éternel, invoquez son nom! Faites connaître parmi les peuples ses hauts faits! Chantez, chantez en son honneur! Parlez de toutes ses merveilles!"],
        ['ref' => 'Psaume 145:4', 'text' => "Que chaque génération célèbre tes œuvres, et publie tes hauts faits!"],
        ['ref' => 'Psaume 9:2', 'text' => "Je louerai l'Éternel de tout mon cœur, je raconterai toutes tes merveilles."],
        ['ref' => 'Luc 8:39', 'text' => "Retourne dans ta maison, et raconte tout ce que Dieu t'a fait."],
        ['ref' => 'Psaume 118:17', 'text' => "Je ne mourrai pas, je vivrai, et je raconterai les œuvres de l'Éternel."],
        ['ref' => 'Psaume 126:3', 'text' => "L'Éternel a fait pour nous de grandes choses; nous sommes dans la joie."],
        ['ref' => 'Actes 4:20', 'text' => "Car nous ne pouvons pas ne pas parler de ce que nous avons vu et entendu."],
        ['ref' => 'Psaume 107:2', 'text' => "Qu'ainsi disent les rachetés de l'Éternel, ceux qu'il a délivrés de la main de l'ennemi."],
        ['ref' => 'Ésaïe 43:10', 'text' => "Vous êtes mes témoins, dit l'Éternel."],
        ['ref' => 'Psaume 40:10', 'text' => "J'annonce la justice dans la grande assemblée; voici, je ne ferme pas mes lèvres, Éternel, tu le sais!"],
    ],

    // Paroles prophétiques du carnet (pages /carnet/paroles) : Habakuk 2:3 en tête et sur l'écran « Proclamer ».
    'prophecy_verses' => [
        ['ref' => 'Habakuk 2:3', 'text' => "Car c'est une prophétie dont le temps est déjà fixé, elle marche vers son terme, et elle ne mentira pas; si elle tarde, attends-la, car elle s'accomplira, elle s'accomplira certainement."],
        ['ref' => 'Nombres 23:19', 'text' => "Dieu n'est point un homme pour mentir, ni fils d'un homme pour se repentir. Ce qu'il a dit, ne le fera-t-il pas? Ce qu'il a déclaré, ne l'exécutera-t-il pas?"],
        ['ref' => '1 Timothée 1:18', 'text' => "Le commandement que je t'adresse, Timothée, mon enfant, selon les prophéties faites précédemment à ton sujet, c'est que, d'après elles, tu combattes le bon combat."],
    ],

    // Invitations (sans verset), en alternance avec les versets.
    'calls' => [
        "Dieu a fait quelque chose pour vous ? Racontez-le : votre témoignage peut fortifier quelqu'un aujourd'hui.",
        "Une prière exaucée, une guérison, une porte ouverte ? Partagez-la pour la gloire de Dieu.",
        "Ce que Dieu a fait pour vous, il peut le faire pour un autre. Témoignez !",
        "Ne gardez pas pour vous les merveilles de Dieu : votre histoire peut changer une vie.",
    ],

    /*
    | « Pourquoi témoigner ? » (composant <x-why-testify />, accordéon) : raisons bibliques de témoigner,
    | chacune avec ses versets (Louis Segond 1910). Copiée dans l'application (whyTestifyReasons,
    | lib/shared/content/encouragements.dart) : modifier les deux. Voir docs/fonctionnalites/pourquoi-temoigner.md
    */
    'why_testify' => [
        [
            'title' => "C'est l'appel du Seigneur",
            'summary' => "Jésus demande à ceux qu'il a touchés de raconter ce qu'il a fait.",
            'icon' => 'fa-bullhorn',
            'verses' => [
                ['ref' => 'Marc 5:19', 'text' => "Va dans ta maison, vers les tiens, et raconte-leur tout ce que le Seigneur t'a fait, et comment il a eu pitié de toi."],
                ['ref' => 'Ésaïe 43:10', 'text' => "Vous êtes mes témoins, dit l'Éternel, vous, et mon serviteur que j'ai choisi."],
                ['ref' => 'Actes 1:8', 'text' => "Mais vous recevrez une puissance, le Saint-Esprit survenant sur vous, et vous serez mes témoins à Jérusalem, dans toute la Judée, dans la Samarie, et jusqu'aux extrémités de la terre."],
            ],
        ],
        [
            'title' => 'Le témoignage donne la victoire',
            'summary' => "La parole du témoignage, avec le sang de l'Agneau, triomphe de l'ennemi.",
            'icon' => 'fa-shield-halved',
            'verses' => [
                ['ref' => 'Apocalypse 12:11', 'text' => "Ils l'ont vaincu à cause du sang de l'agneau et à cause de la parole de leur témoignage, et ils n'ont pas aimé leur vie jusqu'à craindre la mort."],
                ['ref' => 'Psaume 107:2', 'text' => "Qu'ainsi disent les rachetés de l'Éternel, ceux qu'il a délivrés de la main de l'ennemi."],
            ],
        ],
        [
            'title' => 'Il rend gloire à Dieu',
            'summary' => "Raconter ses merveilles, c'est le louer devant tous.",
            'icon' => 'fa-hands-praying',
            'verses' => [
                ['ref' => 'Psaume 105:1-2', 'text' => "Louez l'Éternel, invoquez son nom! Faites connaître parmi les peuples ses hauts faits! Chantez, chantez en son honneur! Parlez de toutes ses merveilles!"],
                ['ref' => 'Psaume 9:2', 'text' => "Je louerai l'Éternel de tout mon cœur, je raconterai toutes tes merveilles."],
                ['ref' => 'Matthieu 5:16', 'text' => "Que votre lumière luise ainsi devant les hommes, afin qu'ils voient vos bonnes œuvres, et qu'ils glorifient votre Père qui est dans les cieux."],
            ],
        ],
        [
            'title' => 'Il fortifie et console les autres',
            'summary' => 'Ce que Dieu a fait pour vous devient une espérance pour un autre.',
            'icon' => 'fa-hand-holding-heart',
            'verses' => [
                ['ref' => 'Psaume 66:16', 'text' => "Venez, écoutez, vous tous qui craignez Dieu, et je raconterai ce qu'il a fait à mon âme."],
                ['ref' => '2 Corinthiens 1:4', 'text' => "Qui nous console dans toutes nos afflictions, afin que, par la consolation dont nous sommes l'objet de la part de Dieu, nous puissions consoler ceux qui se trouvent dans quelque affliction!"],
            ],
        ],
        [
            'title' => "Il conduit d'autres à la foi",
            'summary' => 'Un simple récit peut amener toute une ville à Jésus.',
            'icon' => 'fa-people-group',
            'verses' => [
                ['ref' => 'Jean 4:39', 'text' => "Plusieurs Samaritains de cette ville crurent en Jésus à cause de cette déclaration formelle de la femme: Il m'a dit tout ce que j'ai fait."],
                ['ref' => '1 Pierre 3:15', 'text' => "Mais sanctifiez dans vos cœurs Christ le Seigneur, étant toujours prêts à vous défendre, avec douceur et respect, devant quiconque vous demande raison de l'espérance qui est en vous."],
            ],
        ],
        [
            'title' => 'Il transmet la foi aux générations',
            'summary' => 'Nos enfants doivent connaître les œuvres de Dieu.',
            'icon' => 'fa-seedling',
            'verses' => [
                ['ref' => 'Psaume 78:4', 'text' => "Nous ne les cacherons point à leurs enfants; nous dirons à la génération future les louanges de l'Éternel, et sa puissance, et les prodiges qu'il a opérés."],
                ['ref' => 'Psaume 145:4', 'text' => "Que chaque génération célèbre tes œuvres, et publie tes hauts faits!"],
            ],
        ],
        [
            'title' => 'On ne peut pas se taire',
            'summary' => "Celui qui a vu l'œuvre de Dieu ne garde pas ses lèvres fermées.",
            'icon' => 'fa-comment-dots',
            'verses' => [
                ['ref' => 'Actes 4:20', 'text' => "Car nous ne pouvons pas ne pas parler de ce que nous avons vu et entendu."],
                ['ref' => 'Psaume 40:10', 'text' => "J'annonce la justice dans la grande assemblée; voici, je ne ferme pas mes lèvres, Éternel, tu le sais!"],
                ['ref' => 'Psaume 118:17', 'text' => "Je ne mourrai pas, je vivrai, et je raconterai les œuvres de l'Éternel."],
            ],
        ],
    ],
];
