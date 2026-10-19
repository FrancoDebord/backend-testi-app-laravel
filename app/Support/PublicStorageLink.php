<?php

namespace App\Support;

use Illuminate\Support\Facades\Artisan;
use Throwable;

/**
 * Lien public/storage → storage/app/public, selon config('filesystems.public_link') (STORAGE_PUBLIC_LINK).
 *
 * - false (défaut) : le lien est retiré s'il existe ; Laravel sert alors /storage/… (PublicStorageController).
 *   Nécessaire quand Apache refuse les liens symboliques (AH00037 « Symbolic link not allowed or link target
 *   not accessible ») : sinon toutes les images et tous les audios envoyés répondent 403.
 * - true : le lien est créé s'il manque (serveur qui suit les liens : fichiers servis sans PHP).
 *
 * Appelé par POST /api/v1/_deploy à chaque déploiement et par php artisan storage:public-link.
 */
final class PublicStorageLink
{
    /** Met le lien en accord avec la configuration ; renvoie ce qui a été fait (journal du déploiement). */
    public static function sync(?string $link = null): string
    {
        $link ??= public_path('storage');

        try {
            if (config('filesystems.public_link')) {
                if (file_exists($link) || is_link($link)) {
                    return 'présent';
                }
                if ($link !== public_path('storage')) {
                    symlink(storage_path('app/public'), $link);
                } else {
                    Artisan::call('storage:link');
                }

                return 'créé';
            }

            if (is_link($link)) {
                // Sous Windows, un lien vers un dossier se retire avec rmdir.
                if (!@unlink($link) && !@rmdir($link)) {
                    return 'impossible de retirer le lien public/storage : le supprimer à la main (FTP ou SSH)';
                }

                return 'lien retiré : fichiers servis par Laravel';
            }
            if (is_dir($link)) {
                return 'public/storage est un vrai dossier (copie) : le supprimer pour servir les fichiers à jour';
            }

            return 'absent : fichiers servis par Laravel';
        } catch (Throwable $e) {
            return 'impossible : ' . $e->getMessage();
        }
    }
}
