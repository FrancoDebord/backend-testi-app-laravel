<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $categories = Category::active()->get();

        return $this->success(CategoryResource::collection($categories));
    }

    public function show(string $slug): JsonResponse
    {
        $category = Category::where('slug', $slug)->where('is_active', true)->first();

        if (!$category) return $this->notFound();

        return $this->success(new CategoryResource($category));
    }
}
