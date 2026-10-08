<?php

namespace App\Actions\Company;

use App\Models\CompanyProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class UpdateCompanyProfileAction
{
    public function execute(array $validated): CompanyProfile
    {
        $logo = $validated['logo'] ?? null;
        $removeLogo = (bool) ($validated['remove_logo'] ?? false);

        unset($validated['logo'], $validated['remove_logo']);

        $newLogoPath = null;

        // Store the new file before changing database state.
        if ($logo instanceof UploadedFile) {
            $newLogoPath = $logo->store(
                'company-profile',
                'public'
            );

            if (! is_string($newLogoPath) || $newLogoPath === '') {
                throw new RuntimeException(
                    'Failed to store company profile logo.'
                );
            }

            $validated['logo_path'] = $newLogoPath;
        } elseif ($removeLogo) {
            $validated['logo_path'] = null;
        }

        try {
            [$profile, $oldLogoPath] = DB::transaction(
                function () use ($validated): array {
                    $now = now();

                    // Atomic singleton creation using the unique key.
                    $inserted = DB::table('company_profiles')
                        ->insertOrIgnore([
                            'profile_key' => 'PRIMARY',
                            'name' => $validated['name'],
                            'address' => $validated['address'],
                            'phone' => $validated['phone'],
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);

                    // Serialize updates to the existing profile.
                    $profile = CompanyProfile::query()
                        ->where('profile_key', 'PRIMARY')
                        ->lockForUpdate()
                        ->firstOrFail();

                    $oldLogoPath = $profile->logo_path;

                    $profile->fill($validated);

                    if (! $profile->save()) {
                        throw new RuntimeException(
                            'Failed to save company profile.'
                        );
                    }

                    // Preserve 201 on creation and 200 on updates.
                    $profile->wasRecentlyCreated = $inserted === 1;

                    return [$profile, $oldLogoPath];
                },
                3
            );
        } catch (Throwable $exception) {
            // Database failed: remove the newly uploaded file.
            if ($newLogoPath !== null) {
                $this->deleteLogoSafely($newLogoPath);
            }

            throw $exception;
        }

        // Database has committed successfully at this point.
        if (
            $oldLogoPath !== null
            && $oldLogoPath !== $profile->logo_path
        ) {
            $this->deleteLogoSafely($oldLogoPath);
        }

        return $profile;
    }

    private function deleteLogoSafely(string $path): void
    {
        try {
            $deleted = Storage::disk('public')->delete($path);

            if (! $deleted) {
                Log::warning(
                    'Company profile logo cleanup failed.',
                    ['path' => $path]
                );
            }
        } catch (Throwable $exception) {
            Log::warning(
                'Company profile logo cleanup encountered an error.',
                [
                    'path' => $path,
                    'error' => $exception->getMessage(),
                ]
            );
        }
    }
}
