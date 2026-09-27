<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTerminalRequest;
use App\Http\Resources\TerminalResource;
use App\Models\Terminal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TerminalController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $terminals = Terminal::query()
            ->orderBy('name')
            ->get();

        return TerminalResource::collection($terminals);
    }

    public function store(StoreTerminalRequest $request): JsonResponse
    {
        $terminal = Terminal::create(
            $request->validated()
        );

        return (new TerminalResource($terminal))
            ->response()
            ->setStatusCode(201);
    }
}
