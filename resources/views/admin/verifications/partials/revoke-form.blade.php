{{-- Retrait de la vérification d'une personne : @include('admin.verifications.partials.revoke-form', ['account' => $user])
     docs/fonctionnalites/verification-des-comptes.md --}}
<form id="verification-revoke-form" method="POST" action="{{ route('admin.users.revoke-verification', $account->id) }}" class="space-y-3" data-loading-label="Retrait…">
    @csrf
    <div>
        <label for="revoke_reason" class="form-label">Motif du retrait *</label>
        <textarea id="revoke_reason" name="revoke_reason" rows="2" required minlength="3" maxlength="255" class="form-input resize-none"
                  placeholder="Ex. : usurpation signalée, fonction terminée…">{{ old('revoke_reason') }}</textarea>
        @error('revoke_reason')<p class="form-error">{{ $message }}</p>@enderror
    </div>
    <button type="submit" class="btn-secondary w-full text-red-700 sm:w-auto">
        <i class="fa-solid fa-ban" aria-hidden="true"></i>Retirer le badge « Compte vérifié »
    </button>
</form>
