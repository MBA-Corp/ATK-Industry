<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUnitRequest;
use App\Http\Requests\UpdateUnitRequest;
use App\Http\Resources\UnitResource;
use App\Models\Unit;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Unit::query();

        $search = trim(
            (string) $request->query('search', '')
        );

        if ($search !== '') {
            $query->where(function ($query) use ($search) {
                $query
                    ->where(
                        'code',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'name',
                        'like',
                        "%{$search}%"
                    );
            });
        }

        if ($request->has('is_active')) {
            $query->where(
                'is_active',
                $request->boolean('is_active')
            );
        }

        $units = $query
            ->orderBy('code')
            ->get();

        return response()->json([
            'data' => UnitResource::collection(
                $units
            )->resolve(),
        ]);
    }

    public function store(
        StoreUnitRequest $request
    ): JsonResponse {
        try {
            $unit = Unit::create(
                $request->validated()
            );
        } catch (QueryException $exception) {
            if (($exception->errorInfo[0] ?? null) === '23000') {
                return response()->json([
                    'message' => 'Unit conflicts with existing catalog data.',
                ], 409);
            }

            throw $exception;
        }

        return (new UnitResource($unit))
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        UpdateUnitRequest $request,
        int $unitId
    ): UnitResource|JsonResponse {
        $unit = Unit::query()
            ->findOrFail($unitId);

        try {
            $unit->update(
                $request->validated()
            );
        } catch (QueryException $exception) {
            if (($exception->errorInfo[0] ?? null) === '23000') {
                return response()->json([
                    'message' => 'Unit conflicts with existing catalog data.',
                ], 409);
            }

            throw $exception;
        }

        return new UnitResource(
            $unit->refresh()
        );
    }
}
