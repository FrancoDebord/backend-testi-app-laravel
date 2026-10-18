{{-- Liste de commentaires ou de réponses (affichage initial et chargements suivants). --}}
{{-- « J'aime » de la personne lus en une requête pour toute la liste (docs/fonctionnalites/jaime-commentaires.md) --}}
@php app(\App\Services\CommentLikes::class)->preload('testimony_comment', $comments, Auth::user()); @endphp
@foreach($comments as $comment)
    @include('videos.partials.comment', ['comment' => $comment, 'testimony' => $testimony])
@endforeach
