<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Mime\MimeTypes;

/**
 * Sert les fichiers du disque « public » (audios, vidéos, images envoyés)
 * quand le lien public/storage → storage/app/public est absent ou cassé
 * sur l'hébergement (cas des hébergements mutualisés où `storage:link`
 * n'a pas été lancé, ou lien symbolique copié depuis un autre poste).
 *
 * Si le lien existe, le serveur web sert les fichiers directement et cette
 * route n'est jamais appelée.
 *
 * Les requêtes partielles (en-tête Range) sont gérées par BinaryFileResponse :
 * indispensable pour lire et avancer dans une vidéo ou un audio.
 */
class PublicStorageController extends Controller
{
    /** Types attendus par les lecteurs (Symfony donne p. ex. application/mp4). */
    private const MEDIA_TYPES = [
        'mp4'  => 'video/mp4',
        'mov'  => 'video/quicktime',
        'webm' => 'video/webm',
        '3gp'  => 'video/3gpp',
        'm4a'  => 'audio/mp4',
        'aac'  => 'audio/aac',
        'mp3'  => 'audio/mpeg',
        'wav'  => 'audio/wav',
        'ogg'  => 'audio/ogg',
        'opus' => 'audio/ogg',
    ];

    public function show(string $path): BinaryFileResponse
    {
        // Pas de remontée de dossier ni de fichier caché (.gitignore, .htaccess…).
        abort_if(str_contains($path, '..') || str_contains($path, "\0"), 404);
        abort_if(collect(explode('/', $path))->contains(fn ($part) => str_starts_with($part, '.')), 404);

        $disk = Storage::disk('public');
        abort_unless($disk->exists($path), 404);

        $root = realpath($disk->path(''));
        $file = realpath($disk->path($path));
        abort_if(!$root || !$file || !str_starts_with($file, $root . DIRECTORY_SEPARATOR) || !is_file($file), 404);

        // Type d'après l'extension (.mp4, .m4a, .jpg…) : les lecteurs vidéo et
        // audio s'y fient ; la détection par le contenu peut se tromper.
        $ext  = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $mime = self::MEDIA_TYPES[$ext] ?? MimeTypes::getDefault()->getMimeTypes($ext)[0] ?? null;

        return response()->file($file, array_filter([
            'Content-Type'  => $mime,
            'Cache-Control' => 'public, max-age=604800',
        ]));
    }
}
