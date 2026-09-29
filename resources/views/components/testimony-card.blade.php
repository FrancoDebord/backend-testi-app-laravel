{{--
    Carte de témoignage des grilles (accueil, exploration, profil, sauvegardes, mes témoignages).
    Même présentation que la page Vidéos ; ouvre la page du témoignage (/testimonies/{id}).
    @include('components.testimony-card', ['testimony' => $t, 'short' => false, 'large' => false])
--}}
@include('videos.partials.card', [
    'testimony' => $testimony,
    'url'       => route('testimonies.show', $testimony->id),
    'short'     => ($short ?? false) === true,
    'large'     => ($large ?? false) === true,
])
