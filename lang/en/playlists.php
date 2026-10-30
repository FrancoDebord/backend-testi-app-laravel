<?php

// Testimony playlists (docs/fonctionnalites/listes-de-temoignages.md)
return [
    'nav' => 'My playlists',

    'mine' => [
        'title'       => 'My testimony playlists',
        'subheader'   => 'Group your testimonies about the same thing: they follow each other and play one after another.',
        'create'      => 'New playlist',
        'empty_title' => 'No playlists yet',
        'empty_text'  => 'Create a playlist, then add your testimonies from their page or when you publish.',
        'count'       => '{0} No testimonies|{1} 1 testimony|[2,*] :count testimonies',
        'updated'     => 'Updated :date',
    ],

    'form' => [
        'title'             => 'Playlist title',
        'title_placeholder' => 'For example: My mother\'s healing',
        'description'       => 'Description',
        'description_hint'  => 'Optional: what are these testimonies about?',
        'save'              => 'Save',
        'create'            => 'Create playlist',
        'cancel'            => 'Cancel',
    ],

    'show' => [
        'by'              => 'Playlist by :name',
        'play_all'        => 'Play all',
        'share'           => 'Share playlist',
        'edit'            => 'Edit',
        'delete'          => 'Delete playlist',
        'delete_title'    => 'Delete playlist',
        'delete_confirm'  => 'The playlist will be deleted. Your testimonies stay published.',
        'empty'           => 'This playlist has no testimonies yet.',
        'add_title'       => 'Add one of my testimonies',
        'add_choose'      => 'Choose a testimony',
        'add'             => 'Add',
        'add_none'        => 'All your testimonies are already in this playlist.',
        'move_up'         => 'Move up',
        'move_down'       => 'Move down',
        'remove'          => 'Remove from playlist',
        'not_visible'     => 'Visible to you only (not published yet)',
    ],

    'panel' => [
        'label'         => 'Testimony playlist',
        'position'      => ':position of :count',
        'previous'      => 'Previous',
        'next'          => 'Next',
        'autoplay'      => 'Autoplay playlist',
        'now_playing'   => 'Now playing',
        'open'          => 'View playlist',
    ],

    'composer' => [
        'section'       => 'Testimony playlist',
        'help'          => 'Optional. Put this testimony in a playlist so people can follow every testimony about the same thing.',
        'choose'        => 'Existing playlist',
        'none'          => 'No playlist',
        'new'           => 'Or new playlist',
        'new_hint'      => 'If you enter a title, the playlist is created and the testimony is added to it.',
        'journal_note'  => 'Private journal entries are never added to a playlist.',
        'add_to'        => 'Add to playlist',
        'add_to_title'  => 'Add this testimony to a playlist',
        'in_lists'      => 'In your playlists:',
    ],

    'flash' => [
        'created' => 'Playlist created.',
        'updated' => 'Playlist saved.',
        'deleted' => 'Playlist deleted.',
        'added'   => 'Testimony added to the playlist ":title".',
        'removed' => 'Testimony removed from the playlist.',
        'moved'   => 'Order saved.',
    ],

    'errors' => [
        'forbidden'          => 'Only the playlist\'s author can change it.',
        'not_found'          => 'Playlist not found.',
        'not_your_testimony' => 'You can only add your own testimonies.',
        'journal'            => 'A private journal entry cannot be added to a playlist.',
        'full'               => 'A playlist holds at most :max testimonies.',
        'title_required'     => 'Give the playlist a title.',
        'choose_list'        => 'Choose a playlist or enter a title to create one.',
    ],
];
