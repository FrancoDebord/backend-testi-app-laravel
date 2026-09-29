<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Photo de couverture du profil : enregistrement, remplacement, suppression.
 * Partagé par le site (ProfileController) et l'API (POST/DELETE /users/me/cover).
 * Voir docs/fonctionnalites/photo-de-couverture.md
 */
class ProfileCover
{
    public const DIRECTORY = 'profile-covers';

    /** Règles de validation du fichier (image de 8 Mo au plus, 600 × 150 px au moins). */
    public static function rules(bool $required = false): array
    {
        return [
            $required ? 'required' : 'nullable',
            'image', 'mimes:jpg,jpeg,png,webp', 'max:8192',
            'dimensions:min_width=600,min_height=150',
        ];
    }

    public const MESSAGES = [
        'cover.image'      => 'La photo de couverture doit être une image.',
        'cover.mimes'      => 'La photo de couverture doit être au format JPG, PNG ou WebP.',
        'cover.max'        => 'La photo de couverture ne doit pas dépasser 8 Mo.',
        'cover.dimensions' => 'La photo de couverture doit mesurer au moins 600 × 150 pixels.',
        'cover.required'   => 'Choisissez une photo de couverture.',
    ];

    public static function store(User $user, UploadedFile $file): string
    {
        $path = $file->store(self::DIRECTORY, 'public');
        $old  = $user->cover_url;

        $user->update(['cover_url' => asset('storage/' . $path)]);
        self::deleteFile($old);

        return $user->cover_url;
    }

    public static function remove(User $user): void
    {
        $old = $user->cover_url;
        $user->update(['cover_url' => null]);
        self::deleteFile($old);
    }

    /** Supprime l'ancien fichier, seulement s'il est dans le dossier des couvertures. */
    private static function deleteFile(?string $url): void
    {
        if (!$url) return;

        $marker = '/storage/' . self::DIRECTORY . '/';
        if (!Str::contains($url, $marker)) return;

        $path = self::DIRECTORY . '/' . basename(Str::after($url, $marker));
        Storage::disk('public')->delete($path);
    }
}
