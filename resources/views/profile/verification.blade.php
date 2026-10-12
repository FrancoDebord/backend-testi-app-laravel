@extends('layouts.app')
@section('title', 'Faire vérifier mon compte')
@php
    // Vérification des comptes personnels : docs/fonctionnalites/verification-des-comptes.md
    $header      = 'Faire vérifier mon compte';
    $subheader   = 'Le badge « Compte vérifié » confirme votre identité auprès de la communauté.';
    $breadcrumbs = [
        ['label' => 'Accueil', 'url' => route('home')],
        ['label' => 'Mon profil', 'url' => route('profiles.show', Auth::id())],
        ['label' => 'Vérification'],
    ];
    $status  = $state['status'];
    $request = $state['request'];
    $oldLinks = old('links', $request['links'] ?? []);
@endphp

@section('content')
<div class="mx-auto max-w-2xl space-y-6">
    @if($status === 'verified')
    <div class="alert-success" role="status">
        <i class="fa-solid fa-circle-check mt-0.5" aria-hidden="true"></i>
        <p class="min-w-0 break-words"><span class="font-semibold">Votre compte est vérifié.</span>@if($user->verified_at) Depuis le {{ $user->verified_at->translatedFormat('j F Y') }}.@endif Le badge apparaît à côté de votre nom sur votre profil, vos témoignages et vos commentaires.</p>
    </div>
    @elseif($status === 'pending')
    <div class="alert-info" role="status">
        <i class="fa-solid fa-hourglass-half mt-0.5" aria-hidden="true"></i>
        <p class="min-w-0 break-words"><span class="font-semibold">Demande en cours d’examen.</span> Envoyée le {{ \Illuminate\Support\Carbon::parse($request['createdAt'])->translatedFormat('j F Y') }}. Vous serez prévenu dans vos notifications.</p>
    </div>
    @elseif(in_array($status, ['rejected', 'revoked'], true))
    <div class="alert-warning" role="status">
        <i class="fa-solid fa-triangle-exclamation mt-0.5" aria-hidden="true"></i>
        <p class="min-w-0 break-words"><span class="font-semibold">{{ $status === 'revoked' ? 'Vérification retirée' : 'Demande refusée' }}</span>@if($state['reason']) : {{ rtrim($state['reason'], ' .') }}@endif.
            @if($state['canRequest']) Vous pouvez envoyer une nouvelle demande.@elseif($state['blockReason']) {{ $state['blockReason'] }}@endif</p>
    </div>
    @endif

    @if($state['canRequest'])
    <section class="card p-5 sm:p-6" aria-labelledby="verification-why">
        <h2 id="verification-why" class="card-title mb-2">Pourquoi faire vérifier mon compte ?</h2>
        <p class="text-sm text-slate-600">Pasteurs, évangélistes, chantres, responsables de ministère, personnalités publiques : le badge aide la communauté à reconnaître votre compte officiel. Il ne donne pas de droits supplémentaires (la création d’événements reste réservée aux organisations vérifiées).</p>
    </section>

    @if($errors->any())
    <div class="alert-error" role="alert">
        <i class="fa-solid fa-circle-exclamation mt-0.5" aria-hidden="true"></i>
        <p>La demande n’a pas pu être envoyée. Merci de corriger les champs signalés.</p>
    </div>
    @endif

    <form method="POST" action="{{ route('profile.verification.store') }}" enctype="multipart/form-data" class="card space-y-5 p-5 sm:p-6"
          data-loading-label="Envoi de la demande…">
        @csrf
        <div>
            <label for="full_name" class="form-label">Nom complet *</label>
            <input id="full_name" type="text" name="full_name" required minlength="3" maxlength="150" autocomplete="name"
                   value="{{ old('full_name', $request['fullName'] ?? $user->display_name) }}" class="form-input">
            <p class="form-hint">Tel qu’il figure sur votre pièce d’identité.</p>
            @error('full_name')<p class="form-error">{{ $message }}</p>@enderror
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="min-w-0">
                <label for="role" class="form-label">Fonction *</label>
                <select id="role" name="role" required class="form-input">
                    <option value="">Choisir…</option>
                    @foreach($roles as $role)
                    <option value="{{ $role->value }}" @selected(old('role', $request['role'] ?? null) === $role->value)>{{ $role->label() }}</option>
                    @endforeach
                </select>
                @error('role')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div class="min-w-0">
                <label for="role_other" class="form-label">Précisez (si « Autre »)</label>
                <input id="role_other" type="text" name="role_other" maxlength="100" value="{{ old('role_other', $request['roleOther'] ?? '') }}" class="form-input" placeholder="Ex. : diacre, auteur…">
                @error('role_other')<p class="form-error">{{ $message }}</p>@enderror
            </div>
        </div>

        <div>
            <label for="church" class="form-label">Église ou ministère de rattachement *</label>
            <input id="church" type="text" name="church" required minlength="2" maxlength="150"
                   value="{{ old('church', $request['church'] ?? '') }}" class="form-input" placeholder="Église de la Grâce, Cotonou">
            @error('church')<p class="form-error">{{ $message }}</p>@enderror
        </div>

        <fieldset class="space-y-2">
            <legend class="form-label">Liens publics (facultatif)</legend>
            <p class="form-hint">Site, page Facebook, chaîne YouTube… qui montrent votre ministère.</p>
            @for($i = 0; $i < \App\Services\PersonalVerification::MAX_LINKS; $i++)
            <label for="link-{{ $i }}" class="sr-only">Lien {{ $i + 1 }}</label>
            <input id="link-{{ $i }}" type="url" name="links[]" maxlength="255" value="{{ $oldLinks[$i] ?? '' }}" class="form-input" placeholder="https://">
            @error('links.' . $i)<p class="form-error">{{ $message }}</p>@enderror
            @endfor
            @error('links')<p class="form-error">{{ $message }}</p>@enderror
        </fieldset>

        <div>
            <label for="documents" class="form-label">Pièces justificatives *</label>
            <input id="documents" type="file" name="documents[]" multiple required accept="image/jpeg,image/png,image/webp,application/pdf" class="form-input" aria-describedby="documents-hint">
            <p id="documents-hint" class="form-hint">Photo de votre pièce d’identité et/ou lettre de votre église. JPG, PNG, WebP ou PDF, 8 Mo au plus chacune, 3 au plus.
                <span class="block"><i class="fa-solid fa-lock text-xs" aria-hidden="true"></i> Ces pièces restent privées : seuls les administrateurs chargés de la vérification peuvent les consulter. Elles ne sont jamais publiées.</span></p>
            @error('documents')<p class="form-error">{{ $message }}</p>@enderror
            @foreach($errors->get('documents.*') as $messages)
            <p class="form-error">{{ $messages[0] }}</p>
            @endforeach
        </div>

        <div>
            <label for="message" class="form-label">Message (facultatif)</label>
            <textarea id="message" name="message" rows="3" maxlength="1000" class="form-input" placeholder="Toute information utile à notre équipe.">{{ old('message') }}</textarea>
            @error('message')<p class="form-error">{{ $message }}</p>@enderror
        </div>

        <div class="flex flex-wrap justify-end gap-2 border-t border-slate-100 pt-5">
            <a href="{{ route('profiles.show', Auth::id()) }}" class="btn-secondary">Annuler</a>
            <button type="submit" class="btn-primary"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i>Envoyer la demande</button>
        </div>
    </form>
    @elseif($status === 'pending' && $request)
    <section class="card p-5 sm:p-6" aria-labelledby="verification-sent">
        <h2 id="verification-sent" class="card-title mb-4">Votre demande</h2>
        <dl class="grid grid-cols-1 gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
            <div class="min-w-0"><dt class="text-slate-500">Nom complet</dt><dd class="break-words text-slate-900">{{ $request['fullName'] }}</dd></div>
            <div class="min-w-0"><dt class="text-slate-500">Fonction</dt><dd class="break-words text-slate-900">{{ $request['roleLabel'] }}</dd></div>
            <div class="min-w-0 sm:col-span-2"><dt class="text-slate-500">Église ou ministère</dt><dd class="break-words text-slate-900">{{ $request['church'] }}</dd></div>
            <div class="min-w-0"><dt class="text-slate-500">Pièces justificatives</dt><dd class="text-slate-900">{{ $request['documentCount'] }} (privées)</dd></div>
        </dl>
    </section>
    @endif
</div>
@endsection
