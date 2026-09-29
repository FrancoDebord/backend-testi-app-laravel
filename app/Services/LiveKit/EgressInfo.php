<?php

namespace App\Services\LiveKit;

/**
 * Lecture tolérante d'un EgressInfo LiveKit : l'API serveur et les webhooks
 * n'emploient pas toujours la même écriture (egress_id / egressId, statut texte ou numérique).
 */
final class EgressInfo
{
    private const STATUSES = [
        0 => 'EGRESS_STARTING', 1 => 'EGRESS_ACTIVE', 2 => 'EGRESS_ENDING', 3 => 'EGRESS_COMPLETE',
        4 => 'EGRESS_FAILED', 5 => 'EGRESS_ABORTED', 6 => 'EGRESS_LIMIT_REACHED',
    ];

    public function __construct(private readonly array $info) {}

    public function id(): ?string
    {
        return $this->pick($this->info, 'egress_id', 'egressId');
    }

    /** EGRESS_STARTING | EGRESS_ACTIVE | EGRESS_ENDING | EGRESS_COMPLETE | EGRESS_FAILED | EGRESS_ABORTED | EGRESS_LIMIT_REACHED */
    public function status(): string
    {
        $status = $this->info['status'] ?? 'EGRESS_STARTING';

        return is_numeric($status) ? (self::STATUSES[(int) $status] ?? 'EGRESS_STARTING') : (string) $status;
    }

    public function isInProgress(): bool
    {
        return in_array($this->status(), ['EGRESS_STARTING', 'EGRESS_ACTIVE', 'EGRESS_ENDING'], true);
    }

    public function error(): ?string
    {
        return ($this->info['error'] ?? '') ?: null;
    }

    /** Durée du fichier produit, en secondes (LiveKit la donne en nanosecondes). */
    public function durationSeconds(): int
    {
        $file = $this->file();
        $ns = $file['duration'] ?? null;
        if ($ns === null) {
            $start = $this->pick($this->info, 'started_at', 'startedAt');
            $end = $this->pick($this->info, 'ended_at', 'endedAt');
            $ns = ($start && $end) ? ((int) $end - (int) $start) : 0;
        }

        return (int) round(((int) $ns) / 1_000_000_000);
    }

    public function hasFile(): bool
    {
        $file = $this->file();

        return $file !== null && ((int) ($file['size'] ?? 1)) > 0;
    }

    /** Taille du fichier produit, en octets (0 si inconnue). */
    public function sizeBytes(): int
    {
        return max(0, (int) ($this->file()['size'] ?? 0));
    }

    private function file(): ?array
    {
        $results = $this->pick($this->info, 'file_results', 'fileResults');
        if (is_array($results) && $results) {
            return $results[0];
        }
        $single = $this->info['file'] ?? null;

        return is_array($single) ? $single : null;
    }

    private function pick(array $a, string ...$keys): mixed
    {
        foreach ($keys as $k) {
            if (array_key_exists($k, $a)) {
                return $a[$k];
            }
        }

        return null;
    }
}
