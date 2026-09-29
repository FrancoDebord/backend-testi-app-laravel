<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Testimony;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExploreController extends Controller
{
    public function index(Request $request): View
    {
        $q        = $request->query('q');
        $type     = $request->query('type', 'all');
        $sort     = $request->query('sort', 'recent');
        $category = $request->query('category');

        $query = Testimony::with(['user', 'category'])->published();

        if ($q) {
            $query->where(fn($q2) => $q2->where('title', 'like', "%{$q}%")
                                         ->orWhere('body_text', 'like', "%{$q}%"));
        }

        if ($type !== 'all') $query->where('type', $type);
        if ($category) $query->ofCategory($category);

        match($sort) {
            'popular'     => $query->orderByDesc('like_count'),
            'recommended' => $query->orderByDesc('views_count'),
            default       => $query->latest(),
        };

        $results    = $query->paginate(12)->withQueryString();
        $categories = Category::active()->get();

        return view('explore.index', compact('results', 'categories', 'q', 'type', 'sort', 'category'));
    }
}
