<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Category::query();

        $search = trim(
            (string) $request->query('search', '')
        );

        if ($search !== '') {
            $query->where(
                'name',
                'like',
                "%{$search}%"
            );
        }

        if ($request->has('is_active')) {
            $query->where(
                'is_active',
                $request->boolean('is_active')
            );
        }

        $categories = $query
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => CategoryResource::collection(
                $categories
            )->resolve(),
        ]);
    }

    public function store(
        StoreCategoryRequest $request
    ): JsonResponse {
        try {
            $category = Category::create(
                $request->validated()
            );
        } catch (QueryException $exception) {
            if (($exception->errorInfo[0] ?? null) === '23000') {
                return response()->json([
                    'message' => 'Category conflicts with existing catalog data.',
                ], 409);
            }

            throw $exception;
        }

        return (new CategoryResource($category))
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        UpdateCategoryRequest $request,
        int $categoryId
    ): CategoryResource|JsonResponse {
        $category = Category::query()
            ->findOrFail($categoryId);

        try {
            $category->update(
                $request->validated()
            );
        } catch (QueryException $exception) {
            if (($exception->errorInfo[0] ?? null) === '23000') {
                return response()->json([
                    'message' => 'Category conflicts with existing catalog data.',
                ], 409);
            }

            throw $exception;
        }

        return new CategoryResource(
            $category->refresh()
        );
    }
}
