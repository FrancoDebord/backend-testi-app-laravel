<?php

// Listes de témoignages (docs/fonctionnalites/listes-de-temoignages.md)
return [
    'nav' => 'Mes listes',

    'mine' => [
        'title'       => 'Mes listes de témoignages',
        'subheader'   => 'Regroupez vos témoignages qui parlent d\'une même chose : ils se suivent et se lisent à la suite.',
        'create'      => 'Nouvelle liste',
        'empty_title' => 'Aucune liste pour le moment',
        'empty_text'  => 'Créez une liste, puis ajoutez-y vos témoignages depuis leur page ou au moment de publier.',
        'count'       => '{0} Aucun témoignage|{1} 1 témoignage|[2,*] :count témoignages',
        'updated'     => 'Modifiée :date',
    ],

    'form' => [
        'title'             => 'Titre de la liste',
        'title_placeholder' => 'Par exemple : Guérison de ma mère',
        'description'       => 'Description',
        'description_hint'  => 'Facultative : de quoi parlent ces témoignages ?',
        'save'              => 'Enregistrer',
        'create'            => 'Créer la liste',
        'cancel'            => 'Annuler',
    ],

    'show' => [
        'by'              => 'Liste de :name',
        'play_all'        => 'Tout lire',
        'share'           => 'Partager la liste',
        'edit'            => 'Modifier',
        'delete'          => 'Supprimer la liste',
        'delete_title'    => 'Supprimer la liste',
        'delete_confirm'  => 'La liste sera supprimée. Vos témoignages restent publiés.',
        'empty'           => 'Cette liste ne contient encore aucun témoignage.',
        'add_title'       => 'Ajouter un de mes témoignages',
        'add_choose'      => 'Choisir un témoignage',
        'add'             => 'Ajouter',
        'add_none'        => 'Tous vos témoignages sont déjà dans cette liste.',
        'move_up'         => 'Monter',
        'move_down'       => 'Descendre',
        'remove'          => 'Retirer de la liste',
        'not_visible'     => 'Visible par vous seulement (pas encore publié)',
    ],

    // Panneau de la page de lecture
    'panel' => [
        'label'         => 'Liste de témoignages',
        'position'      => ':position sur :count',
        'previous'      => 'Précédent',
        'next'          => 'Suivant',
        'autoplay'      => 'Lecture auto de la liste',
        'now_playing'   => 'En cours',
        'open'          => 'Voir la liste',
    ],

    // Formulaire de publication et page d'un témoignage
    'composer' => [
        'section'       => 'Liste de témoignages',
        'help'          => 'Facultatif. Mettez ce témoignage dans une liste pour que les personnes suivent tous ceux qui parlent d\'une même chose.',
        'choose'        => 'Liste existante',
        'none'          => 'Aucune liste',
        'new'           => 'Ou nouvelle liste',
        'new_hint'      => 'Si vous donnez un titre, la liste est créée et le témoignage y est ajouté.',
        'journal_note'  => 'Les entrées du carnet privé ne sont jamais mises dans une liste.',
        'add_to'        => 'Ajouter à une liste',
        'add_to_title'  => 'Ajouter ce témoignage à une liste',
        'in_lists'      => 'Dans vos listes :',
    ],

    'flash' => [
        'created' => 'Liste créée.',
        'updated' => 'Liste enregistrée.',
        'deleted' => 'Liste supprimée.',
        'added'   => 'Témoignage ajouté à la liste « :title ».',
        'removed' => 'Témoignage retiré de la liste.',
        'moved'   => 'Ordre enregistré.',
    ],

    'errors' => [
        'forbidden'          => 'Seul l\'auteur de la liste peut la modifier.',
        'not_found'          => 'Liste introuvable.',
        'not_your_testimony' => 'Vous ne pouvez ajouter que vos propres témoignages.',
        'journal'            => 'Une entrée du carnet privé ne peut pas être mise dans une liste.',
        'full'               => 'Une liste contient au plus :max témoignages.',
        'title_required'     => 'Donnez un titre à la liste.',
        'choose_list'        => 'Choisissez une liste ou donnez un titre pour en créer une.',
    ],
];
