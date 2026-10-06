<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Preuve d'un témoignage (image ou PDF, 2 au plus). Voir docs/fonctionnalites/preuves.md */
class TestimonyProof extends Model
{
    use HasUuids;

    protected $fillable = [
        'testimony_id', 'user_id', 'position', 'disk', 'path', 'original_name', 'mime_type', 'size_bytes',
    ];

    protected function casts(): array
    {
        return ['position' => 'integer', 'size_bytes' => 'integer'];
    }

    public function testimony(): BelongsTo
    {
        return $this->belongsTo(Testimony::class);
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    public function sizeLabel(): string
    {
        return $this->size_bytes >= 1048576
            ? number_format($this->size_bytes / 1048576, 1, ',', ' ') . ' Mo'
            : max(1, (int) round($this->size_bytes / 1024)) . ' Ko';
    }
}
