<?php

namespace App\Http\Controllers\Web;

use App\Enums\OrganizationType;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\AccountDelegateAction;
use App\Models\Follow;
use App\Models\Testimony;
use App\Models\User;
use App\Services\AccountDelegation;
use App\Services\EventActionException;
use App\Services\EventService;
use App\Services\OrganizationAccounts;
use App\Services\ProfileCover;
use App\Support\Countries;
use App\Support\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(string $id): View
    {
        $profile    = User::withPublishedTestimonyCount()->findOrFail($id);
        $isOwner    = Auth::id() === $id;
        $isFollowing = Auth::check() && Follow::where('follower_id', Auth::id())
                                               ->where('following_id', $id)
                                               ->exists();

        $testimoniesQuery = Testimony::with(['user', 'category'])
            ->where('user_id', $id)
            ->latest();

        if (!$isOwner) $testimoniesQuery->published();

        $testimonies = $testimoniesQuery->paginate(12);

        // Partages internes récents (docs/fonctionnalites/partage-interne.md).
        $reposts = app(\App\Services\Reposts::class)->ofUser($profile)->limit(12)->get();

        // Événements organisés : à venir / en cours, puis passés (témoignages et commentaires à revoir).
        $events = fn () => \App\Models\Event::query()->published()->where('organizer_id', $profile->id)
            ->with(\App\Services\FeedInserts::eventRelations());
        $upcomingEvents = $events()->upcoming()->limit(6)->get();
        $pastEvents     = $events()->past()->limit(6)->get();

        // Témoignage de conversion mis en avant (publié ; l'auteur voit aussi le sien en attente) :
        // docs/fonctionnalites/temoignage-de-conversion.md
        $conversions = app(\App\Services\ConversionTestimonies::class);
        $conversionTestimony = ($isOwner ? $conversions->of($profile) : $conversions->publishedOf($profile->id))?->load('user');
        if ($conversionTestimony?->isInJournal()) {
            $conversionTestimony = null;
        }

        // Listes de témoignages qui ont au moins un témoignage visible (docs/fonctionnalites/listes-de-temoignages.md).
        $playlists = app(\App\Services\TestimonyPlaylists::class)->publicOf($profile, auth()->user());

        return view('profile.show', compact('profile', 'isOwner', 'isFollowing', 'testimonies', 'reposts', 'upcomingEvents', 'pastEvents', 'conversionTestimony', 'playlists'));
    }

    public function edit(): View
    {
        return view('profile.edit', [
            'user'              => Auth::user(),
            'organizationTypes' => OrganizationType::cases(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user  = Auth::user();
        $isOrg = $user->isOrganization();

        // Organisation : pas de « Nom complet », le nom affiché est le nom de
        // l'organisation (comme l'application mobile). Règles et mise à jour
        // des champs organisation partagées avec l'API (PUT /users/me).
        // Voir docs/fonctionnalites/comptes-organisation.md
        $data = $request->validate([
            'display_name' => $isOrg ? ['nullable', 'string', 'max:100'] : ['required', 'string', 'max:100'],
            // Liste du site ; l'éventuelle valeur déjà enregistrée (ancien compte, application) reste acceptée.
            'country'      => ['nullable', 'string', 'max:100', Rule::in(Countries::allowed($user->country))],
            'bio'          => 'nullable|string|max:500',
            'avatar'       => 'nullable|image|max:5120',
            'cover'        => ProfileCover::rules(),
            'remove_cover' => 'nullable|boolean',
        ]
        // Numéro vérifié par SMS : non modifiable ici (il sert à la connexion par téléphone, depuis l'application).
        + ($user->hasVerifiedPhone() ? [] : PhoneNumber::rules($isOrg, $user))
        + OrganizationAccounts::updateRules($user), PhoneNumber::MESSAGES + ProfileCover::messages() + [
            'country.in' => __('account.register.country_in'),
        ]);

        if (!$user->hasVerifiedPhone()) {
            $phone = PhoneNumber::toE164($data['phone_country'] ?? null, $data['phone'] ?? null);
            $user->setContactPhone($phone, $data['phone_country'] ?? null);
        }

        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('avatars', 'public');
            $user->update(['avatar_url' => asset('storage/' . $path)]);
        }

        // Photo de couverture : nouvelle image, ou suppression (case « Retirer la couverture »).
        if ($request->hasFile('cover')) {
            ProfileCover::store($user, $request->file('cover'));
        } elseif ($request->boolean('remove_cover')) {
            ProfileCover::remove($user);
        }

        $user->update([
            'display_name' => $isOrg ? $user->display_name : $data['display_name'],
            'country'      => $data['country'] ?? $user->country,
            'bio'          => $data['bio'] ?? null,
        ]);

        if (!$isOrg) {
            return back()->with('success', __('profile.updated'));
        }

        // Organisation vérifiée renommée, ou refusée puis modifiée : repasse
        // en attente de vérification (OrganizationAccounts::updateProfile).
        $before = $user->verification_status;
        OrganizationAccounts::updateProfile($user, $data);
        if ($user->organization_name) {
            $user->update(['display_name' => mb_substr($user->organization_name, 0, 100)]);
        }

        $resent = in_array($before, [VerificationStatus::Verified, VerificationStatus::Rejected], true)
            && $user->verification_status === VerificationStatus::Pending;

        return back()->with('success', $resent
            ? __('profile.updated_reverify')
            : __('profile.updated'));
    }

    public function savedTestimonies(): View
    {
        $testimonies = Auth::user()
            ->savedTestimonies()
            ->with(['user', 'category'])
            ->published()
            ->latest('saved_at')
            ->paginate(12);

        return view('profile.saved', compact('testimonies'));
    }

    public function settings(Request $request): View
    {
        $user     = Auth::user();
        $settings = $user->settings ?? new \App\Models\UserSetting();

        // Administrateurs du compte (tous les comptes, sauf ceux de l'équipe) et comptes que
        // j'administre : docs/fonctionnalites/administrateurs-de-compte.md
        $isStaff       = AccountDelegation::isStaff($user);
        $orgManagers   = $isStaff ? collect() : $user->organizationManagers()->orderBy('display_name')->get();
        $managedOrgs   = $user->managedOrganizations()->orderBy('display_name')->get();
        $delegateLog   = $isStaff ? collect() : AccountDelegateAction::where('account_id', $user->id)
            ->latest('created_at')->limit(20)->get();
        // Recherche sans JavaScript (?personne=…) : components/user-picker
        $pickerResults = !$isStaff && $orgManagers->count() < AccountDelegation::MAX_MANAGERS && $request->filled('personne')
            ? UserSearchController::search((string) $request->query('personne'), [$user->id, ...$orgManagers->pluck('id')])
            : null;

        return view('profile.settings', compact('settings', 'isStaff', 'orgManagers', 'managedOrgs', 'delegateLog', 'pickerResults'));
    }

    // ─── Administrateurs du compte (docs/fonctionnalites/administrateurs-de-compte.md) ───

    public function addOrganizationManager(Request $request, EventService $events): RedirectResponse
    {
        $request->validate(['user_id' => 'required|uuid'], [
            'user_id.required' => __('account.managers.choose_person'),
            'user_id.uuid'     => __('account.managers.choose_person_in_list'),
        ]);
        $org = $request->user();

        try {
            $target = $events->addOrganizationManager($org, $org, $request->input('user_id'));
        } catch (EventActionException $e) {
            return redirect()->to(route('profile.settings') . '#administrateurs')->with('error', $e->getMessage());
        }

        return redirect()->to(route('profile.settings') . '#administrateurs')
            ->with('success', __('account.managers.added', ['name' => $target->display_name]));
    }

    public function removeOrganizationManager(Request $request, EventService $events, string $user): RedirectResponse
    {
        $org = $request->user();

        try {
            $events->removeOrganizationManager($org, $org, $user);
        } catch (EventActionException $e) {
            return redirect()->to(route('profile.settings') . '#administrateurs')->with('error', $e->getMessage());
        }

        return redirect()->to(route('profile.settings') . '#administrateurs')->with('success', __('account.managers.removed'));
    }

    /** Ne plus administrer un compte qu'on m'a confié. */
    public function leaveOrganization(Request $request, EventService $events, string $organization): RedirectResponse
    {
        $org = User::find($organization);
        abort_if(!$org, 404);

        try {
            $events->removeOrganizationManager($org, $request->user(), $request->user()->id);
        } catch (EventActionException $e) {
            return redirect()->to(route('profile.settings') . '#comptes-administres')->with('error', $e->getMessage());
        }

        return redirect()->to(route('profile.settings') . '#comptes-administres')
            ->with('success', __('account.managers.left_account', ['name' => $org->organization_name ?: $org->display_name]));
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'is_private_account' => 'nullable|boolean',
            'comment_permission' => 'nullable|in:everyone,followers,nobody',
            'push_comments'      => 'nullable|boolean',
            'push_likes'         => 'nullable|boolean',
            'push_prayers'       => 'nullable|boolean',
            'push_approval'      => 'nullable|boolean',
            'push_new_followed'  => 'nullable|boolean',
            'push_follows'       => 'nullable|boolean',
            'push_events'        => 'nullable|boolean',
            'push_account'       => 'nullable|boolean',
            'push_moderation'    => 'nullable|boolean',
            'app_theme'          => 'nullable|in:light,dark,system',
            'language'           => 'nullable|string|max:5',
        ]);

        $settings = Auth::user()->settings ?? \App\Models\UserSetting::create(['user_id' => Auth::id()]);
        $settings->update($data);

        return back()->with('success', __('profile.settings_saved'));
    }
}
