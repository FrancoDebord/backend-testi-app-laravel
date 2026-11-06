<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\Testimony;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $categories = Category::active()->get();

        // testimonyCount : témoignages publiés de la catégorie, comptés comme la liste
        // GET /testimonies?category= (même filtre published(), même slug). Le compteur
        // stocké testimony_count inclut aussi les témoignages en attente ou refusés.
        $published = Testimony::query()->published()
            ->selectRaw('category_slug, count(*) as aggregate')
            ->groupBy('category_slug')
            ->pluck('aggregate', 'category_slug');
        foreach ($categories as $category) {
            $category->setAttribute('published_testimony_count', (int) ($published[$category->slug] ?? 0));
        }

        return $this->success(CategoryResource::collection($categories));
    }

    public function show(string $slug): JsonResponse
    {
        $category = Category::where('slug', $slug)->where('is_active', true)->first();

        if (!$category) return $this->notFound();

        return $this->success(new CategoryResource($category));
    }
}
