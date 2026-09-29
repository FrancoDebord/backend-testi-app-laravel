{{-- Médaillon de l'intervenant, dans un coin de la vidéo (à placer dans le conteneur relatif du lecteur). --}}
<div class="absolute right-3 bottom-3 aspect-[3/4] w-24 overflow-hidden rounded-lg bg-slate-800 shadow-lg ring-2 ring-white/80 sm:w-40" data-stage-pip hidden>
    <video class="h-full w-full object-cover" autoplay playsinline muted data-stage-pip-video hidden></video>
    <div class="absolute inset-0 flex items-center justify-center" data-stage-pip-avatar>
        <span class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-slate-600 text-base font-semibold text-white sm:h-16 sm:w-16 sm:text-lg" data-stage-pip-initials></span>
    </div>
    <div class="absolute inset-x-0 bottom-0 flex items-center gap-1 bg-slate-900/75 px-1.5 py-1 text-[11px] font-medium text-white">
        <i class="fa-solid fa-microphone shrink-0 text-[10px]" aria-hidden="true" data-stage-pip-mic></i>
        <span class="truncate" data-stage-pip-name></span>
    </div>
</div>
