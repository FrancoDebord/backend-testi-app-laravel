<?php

// Bible: reader resources/views/bible/reader.blade.php and messages of the annotations API (bookmarks, highlights, notes).
return [
    // Quotation marks around a quoted verse (reader, verse of the day, history)
    'quote_open'  => '“',
    'quote_close' => '”',

    'reader' => [
        'books_aria'        => 'Books of the Bible',
        'translation'       => 'Translation',
        'goto_book_label'   => 'Go to a book of the Bible',
        'goto_book'         => 'Go to a book…',
        'old_testament'     => 'Old Testament',
        'new_testament'     => 'New Testament',
        'books'             => 'Books',
        'chapter_lower'     => 'chapter :number',
        'chapter'           => 'Chapter :number',
        'share'             => 'Share',
        'previous_chapter'  => 'Previous chapter',
        'next_chapter'      => 'Next chapter',
        'no_verses'         => 'No verses available',
        'not_imported'      => 'The :translation translation has not been imported yet.',
        'tap_hint'          => 'Tap one or more verses to share them.',
        'select_verse'      => 'Select verse :number',
        'selected_aria'     => 'Selected verses',
        'copy_text'         => 'Copy text',
        'copy_link'         => 'Copy link',
        'clear_selection'   => 'Clear selection',
        'copy_failed'       => 'Copying is not possible in this browser.',
        'link_copied'       => 'Link copied.',
        'verses_copied'     => 'Verses copied.',
    ],

    'messages' => [
        'bookmark_saved'    => 'Verse bookmarked',
        'bookmark_updated'  => 'Label updated',
        'bookmark_deleted'  => 'Bookmark removed',
        'highlight_saved'   => 'Highlight saved',
        'highlight_deleted' => 'Highlight removed',
        'note_saved'        => 'Note saved',
        'note_deleted'      => 'Note deleted',
    ],
];
