<?php

namespace App\Support;

/**
 * Réparation des audios MP4 / M4A dont la première trame est la configuration du codec AAC
 * (docs/fonctionnalites/lecture-audio.md).
 *
 * Certains enregistreurs Android écrivent l'AudioSpecificConfig (2 octets, p. ex. 0x1208) comme
 * premier échantillon audio. Chrome et Edge refusent alors tout le fichier
 * (« PIPELINE_ERROR_DECODE: Failed to send audio packet… size=2 ») ; d'autres lecteurs l'ignorent.
 *
 * La réparation retire cet échantillon des tables (stsz, stsc, stts, stco/co64, mdhd) sans toucher
 * aux données audio : PHP seul, sans ffmpeg, et le fichier n'est réécrit que si le défaut est trouvé.
 * Fichiers lus par flux : seule la boîte moov est chargée en mémoire.
 */
class Mp4AudioRepair
{
    /** Boîtes conteneurs parcourues jusqu'aux tables d'échantillons. */
    private const CONTAINERS = ['moov', 'trak', 'mdia', 'minf', 'stbl'];

    /** Boîtes qui rendraient le retrait d'un échantillon incertain : fichier laissé tel quel. */
    private const UNSUPPORTED = ['ctts', 'stss', 'sdtp', 'stz2', 'mvex'];

    /** Extensions du format MP4 (ISO BMFF) concernées. */
    public const EXTENSIONS = ['mp4', 'm4a', 'm4b', 'mov', '3gp', '3g2'];

    /** Le fichier a-t-il le défaut ? */
    public static function needsRepair(string $path): bool
    {
        return self::plan($path) !== null;
    }

    /**
     * Après un envoi : répare un fichier du disque $disk (local seulement) s'il en a besoin.
     * Ne fait jamais échouer l'envoi.
     */
    public static function repairStored(string $disk, string $path): bool
    {
        if (!in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::EXTENSIONS, true)
            || config("filesystems.disks.{$disk}.driver") !== 'local') {
            return false;
        }

        try {
            return self::repair(\Illuminate\Support\Facades\Storage::disk($disk)->path($path));
        } catch (\Throwable $e) {
            report($e);
            return false;
        }
    }

    /**
     * MP4 sans piste vidéo (son seul) : un .m4a que finfo annonce « video/mp4 ». Sert à classer
     * l'envoi en audio (dossier, conversion en 32k / 64k / 128k plutôt qu'en 240p…).
     */
    public static function isAudioOnly(string $path): bool
    {
        $size = @filesize($path);
        $in   = $size ? @fopen($path, 'rb') : false;
        if (!$in) {
            return false;
        }

        try {
            $tree = self::readMoov($in, $size)['tree'] ?? null;
            if ($tree === null) {
                return false;
            }
            $handlers = array_map(fn ($t) => self::handler($t), array_filter($tree['children'], fn ($b) => $b['type'] === 'trak'));

            return in_array('soun', $handlers, true) && !in_array('vide', $handlers, true);
        } catch (\Throwable) {
            return false;
        } finally {
            fclose($in);
        }
    }

    /** Boîtes de premier niveau et boîte moov détaillée, ou null. */
    private static function readMoov($in, int $size): ?array
    {
        $top  = self::topLevelBoxes($in, $size);
        $moov = collect($top)->firstWhere('type', 'moov');
        if (!$moov || $moov['size'] > 32 * 1024 * 1024) {
            return null;
        }
        fseek($in, $moov['offset']);
        $tree = self::parse(fread($in, $moov['size']))[0] ?? null;

        return $tree && $tree['type'] === 'moov' ? ['top' => $top, 'moov' => $moov, 'tree' => $tree] : null;
    }

    /** Répare le fichier sur place ; true s'il a été modifié. */
    public static function repair(string $path): bool
    {
        $plan = self::plan($path);
        if ($plan === null) {
            return false;
        }

        $temp = $path . '.repair-' . bin2hex(random_bytes(4));
        $in   = fopen($path, 'rb');
        $out  = fopen($temp, 'wb');
        if (!$in || !$out) {
            return false;
        }

        try {
            foreach ($plan['top'] as $box) {
                if ($box['type'] === 'moov') {
                    fwrite($out, $plan['moov']);
                    continue;
                }
                fseek($in, $box['offset']);
                stream_copy_to_stream($in, $out, $box['size']);
            }
        } finally {
            fclose($in);
            fclose($out);
        }

        clearstatcache();
        if (filesize($temp) !== filesize($path) + strlen($plan['moov']) - $plan['moovSize'] || !rename($temp, $path)) {
            @unlink($temp);
            return false;
        }

        return true;
    }

    /**
     * Nouvelle boîte moov si le fichier a le défaut, sinon null.
     *
     * @return array{top: list<array{type: string, offset: int, size: int}>, moov: string, moovSize: int}|null
     */
    private static function plan(string $path): ?array
    {
        $size = @filesize($path);
        $in   = $size ? @fopen($path, 'rb') : false;
        if (!$in) {
            return null;
        }

        try {
            $read = self::readMoov($in, $size);
            if ($read === null) {
                return null;
            }
            ['top' => $top, 'moov' => $moov, 'tree' => $tree] = $read;
            $mdatOffsets = collect($top)->where('type', 'mdat')->pluck('offset');
            if ($mdatOffsets->isEmpty()) {
                return null;
            }

            $tracks = array_values(array_filter($tree['children'], fn ($b) => $b['type'] === 'trak'));
            // Audio seul : une piste son, aucune piste vidéo.
            $handlers = array_map(fn ($t) => self::handler($t), $tracks);
            if (count($tracks) !== 1 || $handlers[0] !== 'soun') {
                return null;
            }

            $stbl = &self::child($tree, ['trak', 'mdia', 'minf', 'stbl']);
            if ($stbl === null || array_intersect(self::childTypes($stbl), self::UNSUPPORTED)) {
                return null;
            }

            $config = self::audioSpecificConfig(self::payload($stbl, 'stsd'));
            $first  = self::firstSample($stbl);
            if ($config === null || $first === null || $first['size'] !== strlen($config) || $first['size'] > 16) {
                return null;
            }

            fseek($in, $first['offset']);
            if (fread($in, $first['size']) !== $config) {
                return null;
            }

            if (!self::dropFirstSample($tree, $stbl, $first['size'])) {
                return null;
            }

            // moov avant les données : les positions des données se décalent de la variation de taille de moov.
            $newMoov = self::serialize($tree);
            $delta = strlen($newMoov) - $moov['size'];
            if ($delta !== 0 && $moov['offset'] < $mdatOffsets->min()) {
                self::shiftChunkOffsets($stbl, $delta);
                $newMoov = self::serialize($tree);
            }

            return ['top' => $top, 'moov' => $newMoov, 'moovSize' => $moov['size']];
        } catch (\Throwable) {
            return null;
        } finally {
            fclose($in);
        }
    }

    // ---------- Lecture des boîtes ----------

    /** @return list<array{type: string, offset: int, size: int}> */
    private static function topLevelBoxes($in, int $fileSize): array
    {
        $boxes = [];
        $offset = 0;
        while ($offset + 8 <= $fileSize) {
            fseek($in, $offset);
            $header = fread($in, 16);
            $size = unpack('N', substr($header, 0, 4))[1];
            $type = substr($header, 4, 4);
            if ($size === 1) {
                $size = unpack('J', substr($header, 8, 8))[1];
            } elseif ($size === 0) {
                $size = $fileSize - $offset;
            }
            if ($size < 8 || $offset + $size > $fileSize) {
                throw new \RuntimeException('Boîte MP4 invalide');
            }
            $boxes[] = ['type' => $type, 'offset' => $offset, 'size' => $size];
            $offset += $size;
        }

        return $boxes;
    }

    /** Boîtes d'un bloc : conteneurs détaillés, autres gardées telles quelles. */
    private static function parse(string $data): array
    {
        $boxes = [];
        $offset = 0;
        $length = strlen($data);
        while ($offset + 8 <= $length) {
            $size = unpack('N', substr($data, $offset, 4))[1];
            $type = substr($data, $offset + 4, 4);
            $headerSize = 8;
            if ($size === 1) {
                $size = unpack('J', substr($data, $offset + 8, 8))[1];
                $headerSize = 16;
            } elseif ($size === 0) {
                $size = $length - $offset;
            }
            if ($size < $headerSize || $offset + $size > $length) {
                throw new \RuntimeException('Boîte MP4 invalide');
            }
            $body = substr($data, $offset + $headerSize, $size - $headerSize);
            $boxes[] = in_array($type, self::CONTAINERS, true)
                ? ['type' => $type, 'children' => self::parse($body)]
                : ['type' => $type, 'payload' => $body];
            $offset += $size;
        }

        return $boxes;
    }

    private static function serialize(array $box): string
    {
        $body = isset($box['children'])
            ? implode('', array_map(fn ($c) => self::serialize($c), $box['children']))
            : $box['payload'];

        return pack('N', strlen($body) + 8) . $box['type'] . $body;
    }

    /** Référence vers le descendant suivant le chemin (premier de chaque type), ou null. */
    private static function &child(array &$box, array $path): ?array
    {
        $null = null;
        if ($path === []) {
            return $box;
        }
        foreach ($box['children'] ?? [] as $i => $c) {
            if ($c['type'] === $path[0]) {
                return self::child($box['children'][$i], array_slice($path, 1));
            }
        }

        return $null;
    }

    private static function childTypes(array $box): array
    {
        return array_column($box['children'] ?? [], 'type');
    }

    private static function payload(array $box, string $type): ?string
    {
        foreach ($box['children'] ?? [] as $c) {
            if ($c['type'] === $type) {
                return $c['payload'] ?? null;
            }
        }

        return null;
    }

    private static function setPayload(array &$box, string $type, string $payload): void
    {
        foreach ($box['children'] as $i => $c) {
            if ($c['type'] === $type) {
                $box['children'][$i]['payload'] = $payload;
                return;
            }
        }
    }

    private static function handler(array $trak): ?string
    {
        $mdia = collect($trak['children'])->firstWhere('type', 'mdia');
        $hdlr = $mdia ? self::payload($mdia, 'hdlr') : null;

        return $hdlr !== null && strlen($hdlr) >= 12 ? substr($hdlr, 8, 4) : null;
    }

    // ---------- Contenu des tables ----------

    /** Octets de DecoderSpecificInfo (descripteur 0x05) de la boîte esds de l'entrée mp4a. */
    private static function audioSpecificConfig(?string $stsd): ?string
    {
        if ($stsd === null || ($pos = strpos($stsd, 'esds')) === false) {
            return null;
        }
        $esds = substr($stsd, $pos + 8); // après « esds » et version + flags

        // ES_Descriptor (0x03) → DecoderConfigDescriptor (0x04) → DecoderSpecificInfo (0x05).
        $p = self::descriptorBody($esds, 0, 0x03);
        if ($p === null || $p + 3 > strlen($esds)) {
            return null;
        }
        $flags = ord($esds[$p + 2]);
        $p += 3; // ES_ID, drapeaux
        if ($flags & 0x80) {
            $p += 2; // dependsOn_ES_ID
        }
        if ($flags & 0x40) {
            $p += 1 + ord($esds[$p] ?? "\0"); // URL
        }
        if ($flags & 0x20) {
            $p += 2; // OCR_ES_Id
        }
        $length = 0;
        $p = self::descriptorBody($esds, $p, 0x04);
        $p = $p === null ? null : self::descriptorBody($esds, $p + 13, 0x05, $length);

        return $p === null || $length < 1 || $length > 16 ? null : substr($esds, $p, $length);
    }

    /** Début du contenu du descripteur $tag attendu en $offset (null sinon). */
    private static function descriptorBody(string $data, int $offset, int $tag, ?int &$length = null): ?int
    {
        if ($offset >= strlen($data) || ord($data[$offset]) !== $tag) {
            return null;
        }
        [$length, $read] = self::descriptorLength($data, $offset + 1);
        if ($length < 0 || $offset + 1 + $read + $length > strlen($data)) {
            return null;
        }

        return $offset + 1 + $read;
    }

    /** Longueur d'un descripteur MPEG-4 (1 à 4 octets, 7 bits chacun). */
    private static function descriptorLength(string $data, int $offset): array
    {
        $length = 0;
        for ($i = 0; $i < 4 && $offset + $i < strlen($data); $i++) {
            $byte = ord($data[$offset + $i]);
            $length = ($length << 7) | ($byte & 0x7F);
            if (!($byte & 0x80)) {
                return [$length, $i + 1];
            }
        }

        return [-1, 0];
    }

    /** Taille et position du premier échantillon. */
    private static function firstSample(array $stbl): ?array
    {
        $stsz = self::payload($stbl, 'stsz');
        if ($stsz === null || strlen($stsz) < 16) {
            return null;
        }
        $constant = unpack('N', substr($stsz, 4, 4))[1];
        $count = unpack('N', substr($stsz, 8, 4))[1];
        if ($constant !== 0 || $count < 2) {
            return null;
        }
        $offset = self::chunkOffsets($stbl)[0] ?? null;

        return $offset === null ? null : ['size' => unpack('N', substr($stsz, 12, 4))[1], 'offset' => $offset];
    }

    /** @return list<int> */
    private static function chunkOffsets(array $stbl): array
    {
        if (($co = self::payload($stbl, 'stco')) !== null) {
            $n = unpack('N', substr($co, 4, 4))[1];
            return $n ? array_values(unpack('N' . $n, substr($co, 8, 4 * $n))) : [];
        }
        if (($co = self::payload($stbl, 'co64')) !== null) {
            $n = unpack('N', substr($co, 4, 4))[1];
            return $n ? array_values(unpack('J' . $n, substr($co, 8, 8 * $n))) : [];
        }

        return [];
    }

    private static function writeChunkOffsets(array &$stbl, array $offsets): void
    {
        $head = pack('N', 0) . pack('N', count($offsets));
        if (self::payload($stbl, 'stco') !== null) {
            if (max($offsets) > 0xFFFFFFFF || min($offsets) < 0) {
                throw new \RuntimeException('Position hors limites');
            }
            self::setPayload($stbl, 'stco', $head . pack('N*', ...$offsets));
        } else {
            self::setPayload($stbl, 'co64', $head . pack('J*', ...$offsets));
        }
    }

    private static function shiftChunkOffsets(array &$stbl, int $delta): void
    {
        self::writeChunkOffsets($stbl, array_map(fn ($o) => $o + $delta, self::chunkOffsets($stbl)));
    }

    /** Retire le premier échantillon (taille $size) de toutes les tables de la piste. */
    private static function dropFirstSample(array &$tree, array &$stbl, int $size): bool
    {
        // stsc : le premier bloc perd un échantillon (il doit lui en rester au moins un).
        $stsc = self::payload($stbl, 'stsc');
        $entries = [];
        $n = unpack('N', substr($stsc, 4, 4))[1];
        for ($i = 0; $i < $n; $i++) {
            $entries[] = array_values(unpack('N3', substr($stsc, 8 + 12 * $i, 12)));
        }
        if ($entries === [] || $entries[0][0] !== 1 || $entries[0][1] < 2) {
            return false;
        }
        $nextFirstChunk = $entries[1][0] ?? PHP_INT_MAX;
        $first = $entries[0];
        $replacement = [[1, $first[1] - 1, $first[2]]];
        if ($nextFirstChunk > 2 && count(self::chunkOffsets($stbl)) > 1) {
            $replacement[] = [2, $first[1], $first[2]];
        }
        $entries = array_merge($replacement, array_slice($entries, 1));
        self::setPayload($stbl, 'stsc', pack('N', 0) . pack('N', count($entries))
            . implode('', array_map(fn ($e) => pack('N3', ...$e), $entries)));

        // stsz : une entrée de moins.
        $stsz = self::payload($stbl, 'stsz');
        $count = unpack('N', substr($stsz, 8, 4))[1];
        self::setPayload($stbl, 'stsz', substr($stsz, 0, 4) . pack('N', 0) . pack('N', $count - 1) . substr($stsz, 16));

        // stts : un échantillon de moins dans la première série ; durée retirée.
        $stts = self::payload($stbl, 'stts');
        $n = unpack('N', substr($stts, 4, 4))[1];
        $runs = [];
        for ($i = 0; $i < $n; $i++) {
            $runs[] = array_values(unpack('N2', substr($stts, 8 + 8 * $i, 8)));
        }
        if ($runs === []) {
            return false;
        }
        $removed = $runs[0][1];
        $runs[0][0]--;
        if ($runs[0][0] === 0) {
            array_shift($runs);
        }
        self::setPayload($stbl, 'stts', pack('N', 0) . pack('N', count($runs))
            . implode('', array_map(fn ($r) => pack('N2', ...$r), $runs)));

        // stco / co64 : le premier bloc commence après l'échantillon retiré.
        $offsets = self::chunkOffsets($stbl);
        $offsets[0] += $size;
        self::writeChunkOffsets($stbl, $offsets);

        // mdhd : durée de la piste (unités de la piste).
        $mdia = &self::child($tree, ['trak', 'mdia']);
        $mdhd = self::payload($mdia, 'mdhd');
        if ($mdhd !== null) {
            if (ord($mdhd[0]) === 1) {
                $duration = unpack('J', substr($mdhd, 24, 8))[1];
                $mdhd = substr_replace($mdhd, pack('J', max(0, $duration - $removed)), 24, 8);
            } else {
                $duration = unpack('N', substr($mdhd, 16, 4))[1];
                $mdhd = substr_replace($mdhd, pack('N', max(0, $duration - $removed)), 16, 4);
            }
            self::setPayload($mdia, 'mdhd', $mdhd);
        }

        return true;
    }
}
