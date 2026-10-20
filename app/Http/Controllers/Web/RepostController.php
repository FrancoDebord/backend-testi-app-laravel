<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\RepostException;
use App\Services\Reposts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Partage interne d'un témoignage sur My Testimony depuis le site (page d'un témoignage).
 * Règles communes avec l'API : App\Services\Reposts. Voir docs/fonctionnalites/partage-interne.md
 */
class RepostController extends Controller
{
    public function __construct(private readonly Reposts $reposts) {}

    public function store(Request $request, string $id): RedirectResponse
    {
        $data = $request->validate(Reposts::rules(), Reposts::messages());

        try {
            [, $created] = $this->reposts->repost($request->user(), $id, $data['comment'] ?? null);
        } catch (RepostException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $created ? __('testimonies.repost.created') : __('testimonies.repost.updated'));
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $this->reposts->remove($request->user(), $id);

        return back()->with('success', __('testimonies.repost.removed'));
    }
}
