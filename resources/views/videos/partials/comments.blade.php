{{-- Liste de commentaires ou de réponses (affichage initial et chargements suivants). --}}
@foreach($comments as $comment)
    @include('videos.partials.comment', ['comment' => $comment, 'testimony' => $testimony])
@endforeach
