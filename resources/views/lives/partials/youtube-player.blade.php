{{--
    Lecteur YouTube d'un direct (source « youtube ») : pendant le direct (lives.show) et comme rediffusion
    (lives/partials/ended-summary). Domaine youtube-nocookie (App\Support\YouTube::embedUrl), format 16:9.
    @include('lives.partials.youtube-player', ['youtube' => $live->youtube()])
--}}
@if($youtube)
<div class="relative aspect-video w-full overflow-hidden rounded-xl bg-black" data-youtube-player>
    <iframe src="{{ $youtube['embedUrl'] }}" title="{{ __('lives.show.youtube_player') }} — {{ $live->title }}" class="absolute inset-0 h-full w-full"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
            referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
</div>
@endif
