<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Enums\PersonalVerificationRole;
use App\Enums\VerificationStatus;
use App\Models\AppNotification;
use App\Models\User;
use App\Models\VerificationRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Vérification des comptes personnels (badge « Compte vérifié ») : demande avec pièces
 * justificatives privées, examen par un administrateur, retrait. Le badge reste porté par
 * users.verification_status (comme pour les organisations) ; une personne vérifiée
 * n'obtient aucun droit supplémentaire (événements, directs : organisations seulement).
 * Utilisé par l'API mobile et le site. Voir docs/fonctionnalites/verification-des-comptes.md
 */
class PersonalVerification
{
    /** Disque privé (storage/app/private) : aucune URL publique. */
    public const DISK = 'local';
    public const DIRECTORY = 'verification-requests';

    /** Délai avant une nouvelle demande après un refus ou un retrait. */
    public const RETRY_DELAY_HOURS = 24;

    public const MAX_DOCUMENTS = 3;
    public const MAX_DOCUMENT_KB = 8192;
    public const DOCUMENT_MIMES = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
    public const MAX_LINKS = 3;

    public static function rules(): array
    {
        return [
            'full_name'   => ['required', 'string', 'min:3', 'max:150'],
            'role'        => ['required', Rule::enum(PersonalVerificationRole::class)],
            'role_other'  => ['required_if:role,other', 'nullable', 'string', 'max:100'],
            'church'      => ['required', 'string', 'min:2', 'max:150'],
            'links'       => ['nullable', 'array', 'max:' . self::MAX_LINKS],
            'links.*'     => ['nullable', 'url:http,https', 'max:255'],
            'message'     => ['nullable', 'string', 'max:1000'],
            'documents'   => ['required', 'array', 'min:1', 'max:' . self::MAX_DOCUMENTS],
            'documents.*' => ['file', 'mimes:' . implode(',', self::DOCUMENT_MIMES), 'max:' . self::MAX_DOCUMENT_KB],
        ];
    }

    public static function messages(): array
    {
        return [
            'full_name.required'     => 'Indiquez votre nom complet.',
            'full_name.min'          => 'Indiquez votre nom complet.',
            'role.required'          => 'Choisissez votre fonction.',
            'role_other.required_if' => 'Précisez votre fonction.',
            'church.required'        => 'Indiquez votre église ou votre ministère.',
            'links.max'              => 'Trois liens au plus.',
            'links.*.url'            => 'Chaque lien doit être une adresse web (https://…).',
            'documents.required'     => 'Ajoutez au moins une pièce justificative.',
            'documents.min'          => 'Ajoutez au moins une pièce justificative.',
            'documents.max'          => 'Trois pièces justificatives au plus.',
            'documents.*.mimes'      => 'Pièce justificative : photo (JPG, PNG, WebP) ou PDF.',
            'documents.*.max'        => 'Chaque pièce justificative doit faire 8 Mo au plus.',
            'documents.*.file'       => 'Pièce justificative invalide.',
        ];
    }

    public static function latestRequest(User $user): ?VerificationRequest
    {
        return VerificationRequest::where('user_id', $user->id)->latest()->first();
    }

    /** Date à partir de laquelle une nouvelle demande est possible (null : tout de suite). */
    public static function retryAt(User $user, ?VerificationRequest $latest = null): ?Carbon
    {
        $latest ??= self::latestRequest($user);
        if (!$latest || !in_array($latest->status, [VerificationRequest::REJECTED, VerificationRequest::REVOKED], true)) {
            return null;
        }
        $at = ($latest->reviewed_at ?? $latest->updated_at)->copy()->addHours(self::RETRY_DELAY_HOURS);
        return $at->isFuture() ? $at : null;
    }

    /** Raison pour laquelle la personne ne peut pas (encore) demander la vérification, sinon null. */
    public static function blockReason(User $user, ?VerificationRequest $latest = null): ?string
    {
        if ($user->isOrganization()) {
            return 'Les organisations sont vérifiées à partir des informations de leur profil.';
        }
        if ($user->isVerified()) {
            return 'Votre compte est déjà vérifié.';
        }
        $latest ??= self::latestRequest($user);
        if ($latest?->isPending()) {
            return 'Votre demande est en cours d’examen.';
        }
        if ($at = self::retryAt($user, $latest)) {
            return 'Vous pourrez envoyer une nouvelle demande à partir du ' . $at->translatedFormat('j F Y à H:i') . '.';
        }
        return null;
    }

    /**
     * État présenté au demandeur (API GET/POST users/me/verification, page du site).
     * Les pièces ne sont jamais renvoyées (ni chemin ni URL) : seulement leur nombre.
     */
    public static function state(User $user): array
    {
        $latest = self::latestRequest($user);
        $status = match (true) {
            $user->isVerified()                                => 'verified',
            $latest?->status === VerificationRequest::PENDING  => 'pending',
            $latest?->status === VerificationRequest::REJECTED => 'rejected',
            $latest?->status === VerificationRequest::REVOKED  => 'revoked',
            default                                            => 'none',
        };
        $block = self::blockReason($user, $latest);

        return [
            'status'      => $status,
            'isVerified'  => $user->isVerified(),
            'canRequest'  => $block === null,
            'blockReason' => $block,
            'retryAt'     => self::retryAt($user, $latest)?->toIso8601String(),
            'reason'      => in_array($status, ['rejected', 'revoked'], true) ? $latest?->review_note : null,
            'verifiedAt'  => $user->isVerified() ? $user->verified_at?->toIso8601String() : null,
            'request'     => $latest ? [
                'id'            => $latest->id,
                'status'        => $latest->status,
                'fullName'      => $latest->full_name,
                'role'          => $latest->role?->value,
                'roleLabel'     => $latest->roleLabel(),
                'roleOther'     => $latest->role_other,
                'church'        => $latest->church,
                'links'         => array_values($latest->links ?? []),
                'message'       => $latest->message,
                'documentCount' => count($latest->documents ?? []),
                'createdAt'     => $latest->created_at?->toIso8601String(),
                'reviewedAt'    => $latest->reviewed_at?->toIso8601String(),
            ] : null,
            'roles'       => PersonalVerificationRole::options(),
            'limits'      => [
                'maxDocuments'  => self::MAX_DOCUMENTS,
                'maxDocumentKb' => self::MAX_DOCUMENT_KB,
                'documentTypes' => self::DOCUMENT_MIMES,
                'maxLinks'      => self::MAX_LINKS,
            ],
        ];
    }

    /**
     * Enregistre une demande (données déjà validées). Lève VerificationActionException si
     * la personne ne peut pas demander la vérification.
     * @param UploadedFile[] $files
     */
    public static function submit(User $user, array $data, array $files): VerificationRequest
    {
        if ($reason = self::blockReason($user)) {
            throw new VerificationActionException($reason, 422);
        }

        $documents = [];
        foreach (array_values($files) as $file) {
            $ext  = strtolower($file->getClientOriginalExtension() ?: ($file->extension() ?: 'bin'));
            $path = $file->storeAs(self::DIRECTORY . '/' . $user->id, Str::uuid() . '.' . $ext, self::DISK);
            $documents[] = [
                'path' => $path,
                'name' => mb_substr($file->getClientOriginalName() ?: ('piece.' . $ext), 0, 120),
                'mime' => $file->getMimeType() ?: 'application/octet-stream',
                'size' => (int) $file->getSize(),
            ];
        }

        $links = array_values(array_filter(array_map(
            fn ($l) => is_string($l) && trim($l) !== '' ? trim($l) : null,
            (array) ($data['links'] ?? [])
        )));
        $role = PersonalVerificationRole::from($data['role']);
        $roleOther = $role === PersonalVerificationRole::Other ? trim((string) ($data['role_other'] ?? '')) : '';

        return DB::transaction(function () use ($user, $data, $documents, $links, $role, $roleOther) {
            $request = VerificationRequest::create([
                'user_id'    => $user->id,
                'full_name'  => trim($data['full_name']),
                'role'       => $role->value,
                'role_other' => $roleOther !== '' ? $roleOther : null,
                'church'     => trim($data['church']),
                'links'      => $links ?: null,
                'message'    => isset($data['message']) && trim($data['message']) !== '' ? trim($data['message']) : null,
                'documents'  => $documents,
                'status'     => VerificationRequest::PENDING,
            ]);

            $user->forceFill([
                'verification_status' => VerificationStatus::Pending->value,
                'verified_at'         => null,
                'verified_by'         => null,
                'verification_note'   => null,
            ])->save();

            return $request;
        });
    }

    public static function approve(VerificationRequest $request, User $admin): void
    {
        if (!$request->isPending()) {
            throw new VerificationActionException('Cette demande a déjà été traitée.', 422);
        }
        $user = $request->user;
        if (!$user || $user->trashed()) {
            throw new VerificationActionException('Ce compte a été supprimé.', 422);
        }

        $before = AccountApproval::snapshot($user);
        DB::transaction(function () use ($request, $user, $admin) {
            $request->update([
                'status'      => VerificationRequest::APPROVED,
                'review_note' => null,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);
            $user->forceFill([
                'verification_status' => VerificationStatus::Verified->value,
                'verified_at'         => now(),
                'verified_by'         => $admin->id,
                'verification_note'   => null,
                'approval_source'     => 'verification_form', // docs/fonctionnalites/badge-approuve.md
            ])->save();
        });
        AccountApproval::audit($user, $admin, 'user.badge_granted', $before, 'Demande de vérification approuvée');

        self::notify($user, $admin, NotificationType::AccountVerified,
            'Votre compte est vérifié. Le badge « Compte vérifié » apparaît désormais sur votre profil et vos publications.');
    }

    public static function reject(VerificationRequest $request, User $admin, string $reason): void
    {
        if (!$request->isPending()) {
            throw new VerificationActionException('Cette demande a déjà été traitée.', 422);
        }
        $reason = trim($reason);
        $user   = $request->user;

        DB::transaction(function () use ($request, $user, $admin, $reason) {
            $request->update([
                'status'      => VerificationRequest::REJECTED,
                'review_note' => $reason,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);
            if ($user && !$user->isOrganization()) {
                $user->forceFill([
                    'verification_status' => VerificationStatus::Rejected->value,
                    'verified_at'         => null,
                    'verified_by'         => $admin->id,
                    'verification_note'   => $reason,
                    'approval_source'     => null,
                ])->save();
            }
        });

        if ($user && !$user->trashed()) {
            self::notify($user, $admin, NotificationType::AccountVerificationRejected,
                'Votre demande de vérification a été refusée : ' . rtrim($reason, ' .') . '. '
                . 'Vous pourrez envoyer une nouvelle demande dans ' . self::RETRY_DELAY_HOURS . ' heures.');
        }
    }

    /** Retire la vérification d'une personne (badge supprimé, demande marquée « retirée »). */
    public static function revoke(User $user, User $admin, string $reason): void
    {
        if ($user->isOrganization()) {
            throw new VerificationActionException('Utilisez la vérification des organisations pour ce compte.', 422);
        }
        if (!$user->isVerified()) {
            throw new VerificationActionException('Ce compte n’est pas vérifié.', 422);
        }
        $reason = trim($reason);
        $before = AccountApproval::snapshot($user);

        DB::transaction(function () use ($user, $admin, $reason) {
            VerificationRequest::where('user_id', $user->id)
                ->where('status', VerificationRequest::APPROVED)
                ->update([
                    'status'      => VerificationRequest::REVOKED,
                    'review_note' => $reason,
                    'reviewed_by' => $admin->id,
                    'reviewed_at' => now(),
                    'updated_at'  => now(),
                ]);
            $user->forceFill([
                'verification_status' => VerificationStatus::Rejected->value,
                'verified_at'         => null,
                'verified_by'         => $admin->id,
                'verification_note'   => $reason,
                'approval_source'     => null,
            ])->save();
        });
        AccountApproval::audit($user, $admin, 'user.badge_revoked', $before, $reason);

        // Badge retiré (et non demande refusée) : type dédié (docs/fonctionnalites/badge-approuve.md).
        self::notify($user, $admin, NotificationType::AccountApprovalRevoked,
            'La vérification de votre compte a été retirée : ' . rtrim($reason, ' .') . '.');
    }

    /** Pièce justificative n° $index si le fichier existe encore, sinon null. */
    public static function document(VerificationRequest $request, int $index): ?array
    {
        $doc = array_values($request->documents ?? [])[$index] ?? null;
        if (!is_array($doc) || empty($doc['path']) || !Storage::disk(self::DISK)->exists($doc['path'])) {
            return null;
        }
        return $doc;
    }

    /** Réponse HTTP privée (affichage dans le navigateur, jamais mise en cache). */
    public static function documentResponse(VerificationRequest $request, int $index)
    {
        $doc = self::document($request, $index);
        abort_unless($doc !== null, 404);

        return Storage::disk(self::DISK)->response($doc['path'], $doc['name'] ?? null, [
            'Content-Type'            => $doc['mime'] ?? 'application/octet-stream',
            'Cache-Control'           => 'private, no-store, max-age=0',
            'X-Content-Type-Options'  => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'",
        ], 'inline');
    }

    /** Supprime les pièces d'un compte (suppression du compte : données sensibles). */
    public static function purgeDocuments(User $user): void
    {
        Storage::disk(self::DISK)->deleteDirectory(self::DIRECTORY . '/' . $user->id);
        VerificationRequest::where('user_id', $user->id)->update(['documents' => json_encode([])]);
    }

    /** Demande vue par l'administration (API). Les pièces sont servies par une route réservée. */
    public static function adminPayload(VerificationRequest $r, bool $detail = false): array
    {
        $user = $r->user;
        $data = [
            'id'            => $r->id,
            'status'        => $r->status,
            'statusLabel'   => $r->statusLabel(),
            'fullName'      => $r->full_name,
            'role'          => $r->role?->value,
            'roleLabel'     => $r->roleLabel(),
            'church'        => $r->church,
            'createdAt'     => $r->created_at?->toIso8601String(),
            'reviewedAt'    => $r->reviewed_at?->toIso8601String(),
            'reviewNote'    => $r->review_note,
            'documentCount' => count($r->documents ?? []),
            'user'          => $user ? [
                'id'          => $user->id,
                'displayName' => $user->display_name,
                'avatarUrl'   => $user->avatar_url,
                'email'       => $user->email,
                'isVerified'  => $user->isVerified(),
                'isDeleted'   => $user->trashed(),
            ] : null,
        ];
        if ($detail) {
            $docs = array_values($r->documents ?? []);
            $data['links']     = array_values($r->links ?? []);
            $data['message']   = $r->message;
            $data['reviewer']  = $r->reviewer ? ['id' => $r->reviewer->id, 'displayName' => $r->reviewer->display_name] : null;
            $data['documents'] = array_map(fn (int $i) => [
                'index'   => $i,
                'name'    => $docs[$i]['name'] ?? ('Pièce ' . ($i + 1)),
                'mime'    => $docs[$i]['mime'] ?? null,
                'size'    => $docs[$i]['size'] ?? null,
                'isImage' => str_starts_with((string) ($docs[$i]['mime'] ?? ''), 'image/'),
                // Jeton administrateur exigé : jamais d'URL publique.
                'url'     => url('/api/v1/admin/verification-requests/' . $r->id . '/documents/' . $i),
            ], array_keys($docs));
        }
        return $data;
    }

    /**
     * Notification dans l'application ; le push FCM part automatiquement
     * (AppNotificationObserver, docs/fonctionnalites/notifications-push.md).
     */
    private static function notify(User $recipient, User $admin, NotificationType $type, string $message): void
    {
        AppNotification::create([
            'recipient_id' => $recipient->id,
            'actor_id'     => $admin->id,
            'actor_name'   => $admin->display_name,
            'type'         => $type->value,
            'message'      => mb_substr($message, 0, 255),
            'created_at'   => now(),
        ]);
    }
}
