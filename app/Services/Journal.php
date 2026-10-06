<?php

namespace App\Services;

use App\Enums\TestimonyStatus;
use App\Enums\TestimonyVisibility;
use App\Models\Category;
use App\Models\Testimony;
use Illuminate\Database\Eloquent\Builder;

/**
 * Carnet privé : règles communes à l'API mobile et au site web.
 * Voir docs/fonctionnalites/carnet-prive.md
 */
class Journal
{
    /** Entrées du carnet d'un utilisateur, les plus récentes d'abord (?type, ?q). */
    public function entries(string $userId, ?string $type = null, ?string $search = null): Builder
    {
        $query = Testimony::with(['user', 'category'])
            ->where('user_id', $userId)
            ->journal()
            ->latest();

        if ($type) {
            $query->where('type', $type);
        }
        if ($search = trim((string) $search)) {
            $query->where(fn ($q) => $q->where('title', 'like', "%{$search}%")
                                       ->orWhere('body_text', 'like', "%{$search}%"));
        }

        return $query;
    }

    /** Partage une entrée du carnet : elle devient publique et passe en modération. */
    public function share(Testimony $testimony, ?string $categorySlug = null): Testimony
    {
        $slug     = $categorySlug ?: $testimony->category_slug;
        $category = Category::where('slug', $slug)->first();

        $testimony->update([
            'visibility'    => TestimonyVisibility::Public->value,
            'status'        => TestimonyStatus::Pending->value,
            'category_slug' => $slug,
            'category_id'   => $category?->id ?? $testimony->category_id,
        ]);
        $category?->increment('testimony_count');

        return $testimony;
    }

    /** Retire un témoignage du public et le range dans le carnet. */
    public function store(Testimony $testimony): Testimony
    {
        if ($testimony->isInJournal()) {
            return $testimony;
        }

        $wasCounted = $testimony->status !== TestimonyStatus::Draft;
        $testimony->update([
            'visibility'  => TestimonyVisibility::Private->value,
            'status'      => TestimonyStatus::Draft->value,
            'is_featured' => false,
        ]);
        if ($wasCounted) {
            Category::where('slug', $testimony->category_slug)->where('testimony_count', '>', 0)->decrement('testimony_count');
        }

        return $testimony;
    }
}
