<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Purchasing\UpsertPurchaseInvoiceAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePurchaseInvoiceRequest;
use App\Models\Purchase;
use App\Models\PurchaseInvoiceAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PurchaseInvoiceController extends Controller
{
    public function show(int $purchaseId): JsonResponse
    {
        $purchase = Purchase::query()
            ->findOrFail($purchaseId);

        $attachment = $purchase
            ->invoiceAttachment()
            ->first();

        return response()->json([
            'data' => $attachment === null
                ? null
                : $this->toData($attachment),
        ]);
    }

    public function store(
        StorePurchaseInvoiceRequest $request,
        int $purchaseId,
        UpsertPurchaseInvoiceAction $action
    ): JsonResponse {
        [$attachment, $created] = $action->execute(
            $purchaseId,
            $request->file('invoice'),
            (int) $request->user()->id
        );

        return response()->json([
            'data' => $this->toData($attachment),
        ], $created ? 201 : 200);
    }

    public function download(
        int $purchaseId
    ): StreamedResponse {
        $attachment = PurchaseInvoiceAttachment::query()
            ->where('purchase_id', $purchaseId)
            ->firstOrFail();

        $disk = Storage::disk($attachment->disk);

        abort_unless(
            $disk->exists($attachment->path),
            404,
            'Purchase invoice file not found.'
        );

        return $disk->download(
            $attachment->path,
            $attachment->original_name,
            [
                'Content-Type' => $attachment->mime_type,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ]
        );
    }

    private function toData(
        PurchaseInvoiceAttachment $attachment
    ): array {
        return [
            'id' => $attachment->id,
            'purchase_id' => $attachment->purchase_id,
            'original_name' => $attachment->original_name,
            'mime_type' => $attachment->mime_type,
            'size_bytes' => $attachment->size_bytes,
            'uploaded_by' => $attachment->uploaded_by,
            'created_at' => $attachment->created_at,
            'updated_at' => $attachment->updated_at,
        ];
    }
}
