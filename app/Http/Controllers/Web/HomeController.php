<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\DailyVerse;
use App\Models\Testimony;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(Request $request): View
    {
        $category = $request->query('category');
        $type     = $request->query('type');

        $feedQuery = Testimony::with('user')
            ->published()
            ->latest();

        if ($category) $feedQuery->ofCategory($category);
        if ($type) $feedQuery->where('type', $type);

        $feed       = $feedQuery->paginate(12);
        $featured   = Testimony::with('user')->featured()->orderByDesc('views_count')->limit(5)->get();
        $categories = Category::active()->get();
        $verse      = DailyVerse::today();

        return view('home.index', compact('feed', 'featured', 'categories', 'verse', 'category', 'type'));
    }
}
