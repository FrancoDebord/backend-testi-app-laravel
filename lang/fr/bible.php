<?php

// Bible : lecteur resources/views/bible/reader.blade.php et messages de l'API des annotations (signets, surlignages, notes).
return [
    // Guillemets autour d'un verset cité (lecteur, verset du jour, historique)
    'quote_open'  => '« ',
    'quote_close' => ' »',

    'reader' => [
        'books_aria'        => 'Livres de la Bible',
        'translation'       => 'Traduction',
        'goto_book_label'   => 'Aller à un livre de la Bible',
        'goto_book'         => 'Aller à un livre…',
        'old_testament'     => 'Ancien Testament',
        'new_testament'     => 'Nouveau Testament',
        'books'             => 'Livres',
        'chapter_lower'     => 'chapitre :number',
        'chapter'           => 'Chapitre :number',
        'share'             => 'Partager',
        'previous_chapter'  => 'Chapitre précédent',
        'next_chapter'      => 'Chapitre suivant',
        'no_verses'         => 'Aucun verset disponible',
        'not_imported'      => 'La traduction :translation n’a pas encore été importée.',
        'tap_hint'          => 'Touchez un ou plusieurs versets pour les partager.',
        'select_verse'      => 'Sélectionner le verset :number',
        'selected_aria'     => 'Versets sélectionnés',
        'copy_text'         => 'Copier le texte',
        'copy_link'         => 'Copier le lien',
        'clear_selection'   => 'Effacer la sélection',
        'copy_failed'       => 'Copie impossible dans ce navigateur.',
        'link_copied'       => 'Lien copié.',
        'verses_copied'     => 'Versets copiés.',
    ],

    'messages' => [
        'bookmark_saved'    => 'Verset marqué',
        'bookmark_updated'  => 'Étiquette mise à jour',
        'bookmark_deleted'  => 'Signet supprimé',
        'highlight_saved'   => 'Surlignage enregistré',
        'highlight_deleted' => 'Surlignage supprimé',
        'note_saved'        => 'Note enregistrée',
        'note_deleted'      => 'Note supprimée',
    ],
];
