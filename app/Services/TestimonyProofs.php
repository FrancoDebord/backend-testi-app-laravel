<?php

namespace App\Services;

use App\Models\Testimony;
use App\Models\TestimonyProof;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Preuves des témoignages : 2 fichiers au plus (images JPG, PNG, WebP ou PDF, 10 Mo chacun),
 * rangés sur le disque privé (jamais d'adresse publique). Lecture réservée à l'auteur et à
 * l'équipe de modération, ou ouverte à tous si l'auteur l'a accepté (proofs_public) et que le
 * témoignage est publié. Partagé par le site et l'API. Voir docs/fonctionnalites/preuves.md
 */
class TestimonyProofs
{
    public const MAX = 2;
    public const DISK = 'local';

    public static function rules(bool $required = false): array
    {
        return [$required ? 'required' : 'nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'];
    }

    public static function messages(string $field): array
    {
        return [
            "$field.required" => 'Choisissez un fichier.',
            "$field.file"     => 'La preuve doit être un fichier.',
            "$field.mimes"    => 'La preuve doit être une image (JPG, PNG, WebP) ou un PDF.',
            "$field.max"      => 'La preuve ne doit pas dépasser 10 Mo.',
        ];
    }

    /**
     * Peut voir les preuves : l'auteur, les modérateurs et les administrateurs ; tout le monde
     * quand l'auteur a accepté leur publication et que le témoignage est publié (validé, public).
     */
    public static function canView(?User $user, Testimony $testimony): bool
    {
        if ($user !== null && ($user->id === $testimony->user_id || $user->canModerate())) {
            return true;
        }

        return self::arePublic($testimony);
    }

    /** Preuves montrées au public : accord de l'auteur et témoignage publié. */
    public static function arePublic(Testimony $testimony): bool
    {
        return $testimony->proofs_public
            && $testimony->status->value === 'approved'
            && $testimony->visibility->value === 'public'
            && !$testimony->trashed();
    }

    /** Ajoute une preuve à la première place libre (ou à $position, en remplaçant l'ancienne). */
    public function add(Testimony $testimony, UploadedFile $file, User $user, ?int $position = null): TestimonyProof
    {
        $taken = $testimony->proofs()->pluck('position')->all();
        $position ??= collect(range(1, self::MAX))->first(fn ($p) => !in_array($p, $taken, true));

        if ($position === null || $position < 1 || $position > self::MAX) {
            throw ValidationException::withMessages(['file' => 'Deux preuves au plus par témoignage : retirez-en une pour en ajouter une autre.']);
        }

        $testimony->proofs()->where('position', $position)->get()->each(fn (TestimonyProof $old) => $this->delete($old));

        $path = $file->store('proofs/' . $testimony->id, self::DISK);

        return $testimony->proofs()->create([
            'user_id'       => $user->id,
            'position'      => $position,
            'disk'          => self::DISK,
            'path'          => $path,
            'original_name' => mb_substr($file->getClientOriginalName() ?: 'preuve', 0, 255),
            'mime_type'     => mb_substr((string) $file->getMimeType(), 0, 100),
            'size_bytes'    => (int) $file->getSize(),
        ]);
    }

    public function delete(TestimonyProof $proof): void
    {
        Storage::disk($proof->disk)->delete($proof->path);
        $proof->delete();
    }
}
