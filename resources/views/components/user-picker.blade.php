{{--
    Choisir une personne (compte personnel actif) pour la désigner comme gestionnaire.
    @include('components.user-picker', [
        'id'      => 'event-managers',              // préfixe unique des identifiants
        'label'   => 'Ajouter un co-gestionnaire',
        'addUrl'  => route('events.managers.store', $event->id),   // POST user_id
        'exclude' => [$event->organizer_id, ...],    // comptes à écarter des résultats
        'results' => $pickerResults,                 // résultats rendus par le serveur (?personne=…), ou null
        'anchor'  => 'co-gestionnaires',             // ancre de la page après la recherche sans JavaScript
    ])
    Sans JavaScript : la recherche recharge la page avec ?personne=… et les résultats viennent du serveur
    (UserSearchController::search). Avec JavaScript (resources/js/app.js, [data-user-picker]) : résultats
    au fil de la saisie (route users.search). Chaque résultat est un petit formulaire « Ajouter ».
--}}
@php
    $pickerId = $id ?? 'user-picker';
    $results  = $results ?? null;
    $query    = request('personne', '');
    $exclude  = array_values(array_filter($exclude ?? []));
@endphp
<div data-user-picker data-search-url="{{ route('users.search') }}" data-add-url="{{ $addUrl }}" data-exclude="{{ implode(',', $exclude) }}">
    <form method="GET" action="{{ url()->current() }}{{ !empty($anchor) ? '#' . $anchor : '' }}" role="search" data-user-picker-form data-no-loading>
        <label for="{{ $pickerId }}-q" class="form-label">{{ $label ?? 'Ajouter une personne' }}</label>
        <div class="flex flex-col gap-2 sm:flex-row">
            <input id="{{ $pickerId }}-q" type="search" name="personne" value="{{ $query }}" minlength="2" maxlength="100" required
                   class="form-input min-w-0 flex-1" autocomplete="off" placeholder="Nom ou adresse e-mail"
                   aria-describedby="{{ $pickerId }}-hint" aria-controls="{{ $pickerId }}-results" data-user-picker-input>
            <button type="submit" class="btn-secondary shrink-0"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>Rechercher</button>
        </div>
        <p id="{{ $pickerId }}-hint" class="form-hint">Comptes personnels seulement. Saisissez au moins 2 lettres du nom, ou l'adresse e-mail complète.</p>
    </form>

    <p class="mt-3 text-sm text-slate-500" role="status" data-user-picker-status>
        @if($results !== null && $results->isEmpty())Aucune personne trouvée.@endif
    </p>
    <ul id="{{ $pickerId }}-results" class="divide-y divide-slate-100" data-user-picker-results>
        @foreach($results ?? [] as $person)
        <li class="flex items-center gap-3 py-2.5">
            @include('components.avatar', ['user' => $person, 'size' => 'sm'])
            <span class="min-w-0 flex-1 truncate text-sm font-semibold text-slate-900">{{ $person->display_name }}</span>
            <form method="POST" action="{{ $addUrl }}" data-loading-label="Ajout…">
                @csrf
                <input type="hidden" name="user_id" value="{{ $person->id }}">
                <button type="submit" class="btn-secondary btn-sm" aria-label="Ajouter {{ $person->display_name }}"><i class="fa-solid fa-user-plus" aria-hidden="true"></i>Ajouter</button>
            </form>
        </li>
        @endforeach
    </ul>
</div>
