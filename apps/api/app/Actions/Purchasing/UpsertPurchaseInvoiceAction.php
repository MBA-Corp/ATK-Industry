<?php

namespace App\Actions\Purchasing;

use App\Models\Purchase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class UpsertPurchaseInvoiceAction
{
    public function execute(
        int $purchaseId,
        UploadedFile $file,
        int $uploadedBy
    ): array {
        // Reject invalid purchases before storing the file.
        Purchase::query()->findOrFail($purchaseId);

        $disk = 'local';

        $newPath = $file->store(
            "purchase-invoices/{$purchaseId}",
            $disk
        );

        if (! is_string($newPath) || $newPath === '') {
            throw new RuntimeException(
                'Failed to store purchase invoice.'
            );
        }

        $originalName = basename(
            str_replace('\\', '/', $file->getClientOriginalName())
        );

        $originalName = Str::limit(
            $originalName ?: 'invoice',
            200,
            ''
        );

        try {
            [
                $attachment,
                $created,
                $oldDisk,
                $oldPath,
            ] = DB::transaction(
                function () use (
                    $purchaseId,
                    $file,
                    $uploadedBy,
                    $disk,
                    $newPath,
                    $originalName
                ): array {
                    // Lock the parent purchase to serialize replacements.
                    $purchase = Purchase::query()
                        ->lockForUpdate()
                        ->findOrFail($purchaseId);

                    $existing = $purchase
                        ->invoiceAttachment()
                        ->first();

                    $created = $existing === null;
                    $oldDisk = $existing?->disk;
                    $oldPath = $existing?->path;

                    $attributes = [
                        'disk' => $disk,
                        'path' => $newPath,
                        'original_name' => $originalName,
                        'mime_type' => (string) $file->getMimeType(),
                        'size_bytes' => (int) $file->getSize(),
                        'uploaded_by' => $uploadedBy,
                    ];

                    if ($created) {
                        $attachment = $purchase
                            ->invoiceAttachment()
                            ->create($attributes);
                    } else {
                        $existing->update($attributes);
                        $attachment = $existing;
                    }

                    return [
                        $attachment->refresh(),
                        $created,
                        $oldDisk,
                        $oldPath,
                    ];
                },
                3
            );
        } catch (Throwable $exception) {
            // Database failed: clean up the new file.
            $this->deleteSafely($disk, $newPath);

            throw $exception;
        }

        // The database transaction has committed successfully.
        if (
            $oldDisk !== null
            && $oldPath !== null
            && ($oldDisk !== $disk || $oldPath !== $newPath)
        ) {
            $this->deleteSafely($oldDisk, $oldPath);
        }

        return [$attachment, $created];
    }

    private function deleteSafely(
        string $disk,
        string $path
    ): void {
        try {
            $deleted = Storage::disk($disk)->delete($path);

            if (! $deleted) {
                Log::warning(
                    'Purchase invoice file cleanup failed.',
                    [
                        'disk' => $disk,
                        'path' => $path,
                    ]
                );
            }
        } catch (Throwable $exception) {
            Log::warning(
                'Purchase invoice cleanup encountered an error.',
                [
                    'disk' => $disk,
                    'path' => $path,
                    'error' => $exception->getMessage(),
                ]
            );
        }
    }
}
