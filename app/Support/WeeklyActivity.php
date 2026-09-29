<?php

namespace App\Support;

use App\Models\Testimony;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Activité des derniers jours (témoignages publiés hors carnet, inscriptions), jour par jour,
 * avec les totaux de la période précédente pour l'évolution (« +12 % »).
 * Utilisé par l'accueil et le tableau de bord de l'administration.
 */
class WeeklyActivity
{
    /**
     * @return array{days: array<int, array{date: Carbon, testimonies: int, users: int}>,
     *               testimonies: int, users: int, previousTestimonies: int, previousUsers: int}
     */
    public static function lastDays(int $days = 7): array
    {
        $from     = now()->subDays($days - 1)->startOfDay();
        $previous = $from->copy()->subDays($days);

        $perDay = fn ($query) => (clone $query)->where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')->pluck('total', 'day');
        $before = fn ($query) => (clone $query)->whereBetween('created_at', [$previous, $from])->count();

        $testimonyQuery = Testimony::query()->withoutJournal();
        $userQuery      = User::query();

        $testimonies = $perDay($testimonyQuery);
        $users       = $perDay($userQuery);

        $list = collect(range(0, $days - 1))->map(function (int $i) use ($from, $testimonies, $users) {
            $date = $from->copy()->addDays($i);
            $key  = $date->toDateString();
            return ['date' => $date, 'testimonies' => (int) ($testimonies[$key] ?? 0), 'users' => (int) ($users[$key] ?? 0)];
        })->all();

        return [
            'days'                => $list,
            'testimonies'         => (int) collect($list)->sum('testimonies'),
            'users'               => (int) collect($list)->sum('users'),
            'previousTestimonies' => $before($testimonyQuery),
            'previousUsers'       => $before($userQuery),
        ];
    }

    /** Évolution en pour cent (null si la période précédente est vide). */
    public static function change(int $current, int $previous): ?int
    {
        return $previous > 0 ? (int) round(($current - $previous) / $previous * 100) : null;
    }
}
