<?php

namespace App\Http\Controllers\Web;

use App\Enums\AccountType;
use App\Enums\UserAccountStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Recherche de personnes pour désigner un gestionnaire (co-gestionnaire d'un événement,
 * gestionnaire d'une organisation) : comptes personnels actifs seulement, par nom ou
 * par adresse e-mail exacte (l'adresse n'est jamais renvoyée).
 * Utilisée par components/user-picker (JavaScript) et, sans JavaScript, par les pages elles-mêmes.
 */
class UserSearchController extends Controller
{
    public const LIMIT = 8;

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q'       => 'nullable|string|max:100',
            'exclude' => 'nullable|string|max:400',
        ]);
        $exclude = array_filter(explode(',', (string) ($data['exclude'] ?? '')), fn ($id) => Str::isUuid($id));

        return response()->json([
            'data' => self::search((string) ($data['q'] ?? ''), [...$exclude, $request->user()->id])
                ->map(fn (User $u) => [
                    'id'          => $u->id,
                    'displayName' => $u->display_name,
                    'initials'    => $u->initials,
                    'avatarUrl'   => $u->avatar_url,
                ])->values(),
        ]);
    }

    /** @param string[] $exclude identifiants à écarter (déjà gestionnaires, organisateur…) */
    public static function search(string $q, array $exclude = []): Collection
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) {
            return collect();
        }
        $like = '%' . addcslashes($q, '%_\\') . '%';

        return User::query()
            ->where('status', UserAccountStatus::Active->value)
            ->where(fn ($w) => $w->whereNull('account_type')->orWhere('account_type', '!=', AccountType::Organization->value))
            ->whereNotIn('id', array_values(array_unique($exclude)))
            ->where(fn ($w) => $w->where('display_name', 'like', $like)->orWhere('email', mb_strtolower($q)))
            ->orderBy('display_name')
            ->limit(self::LIMIT)
            ->get(['id', 'display_name', 'avatar_url']);
    }
}
