<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Catalog\ImportCatalogAction;
use App\Http\Controllers\Controller;
use App\Support\CatalogWorkbookReader;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogImportController extends Controller
{
    public function preview(Request $request, CatalogWorkbookReader $reader, ImportCatalogAction $action): JsonResponse
    {
        $this->validateFile($request);

        return response()->json(['data' => $action->preview($reader->read($request->file('file')->getRealPath()))]);
    }

    public function commit(Request $request, CatalogWorkbookReader $reader, ImportCatalogAction $action): JsonResponse
    {
        $this->validateFile($request);
        $data = $request->validate([
            'idempotency_key' => 'required|uuid',
            'preview_hash' => 'required|string|size:64',
            'acknowledge_provisional_source' => 'required|accepted',
        ]);
        try {
            return response()->json(['data' => $action->commit(
                $reader->read($request->file('file')->getRealPath()),
                $data['idempotency_key'], $data['preview_hash'], $request->user()->id,
            )]);
        } catch (UniqueConstraintViolationException) {
            return response()->json(['message' => 'Concurrent import/catalog conflict. Retry with the same key or preview again.'], 409);
        }
    }

    private function validateFile(Request $request): void
    {
        $request->validate([
            'file' => 'required|file|max:10240|extensions:xlsx',
            'conversion_mode' => 'required|in:to_base',
            'zero_price_policy' => 'required|in:disable_sales',
        ]);
    }
}
