<?php

namespace App\Actions\Company;

use App\Models\CompanyProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Throwable;

class UpdateCompanyProfileAction
{
    public function execute(
        array $validated
    ): CompanyProfile {
        $profile = CompanyProfile::query()
            ->firstOrNew([
                'profile_key' => 'PRIMARY',
            ]);

        $oldLogoPath = $profile->logo_path;

        $newLogoPath = null;

        $logo = $validated['logo']
            ?? null;

        $removeLogo = (bool) (
            $validated['remove_logo']
            ?? false
        );

        unset(
            $validated['logo'],
            $validated['remove_logo']
        );

        if ($logo instanceof UploadedFile) {
            $newLogoPath = $logo->store(
                'company-profile',
                'public'
            );

            $validated['logo_path'] =
                $newLogoPath;
        } elseif ($removeLogo) {
            $validated['logo_path'] = null;
        }

        try {
            $profile->fill(
                $validated
            );

            $profile->profile_key =
                'PRIMARY';

            $profile->save();
        } catch (Throwable $exception) {
            if ($newLogoPath !== null) {
                Storage::disk('public')
                    ->delete(
                        $newLogoPath
                    );
            }

            throw $exception;
        }

        if (
            $oldLogoPath !== null
            && $oldLogoPath
                !== $profile->logo_path
        ) {
            Storage::disk('public')
                ->delete(
                    $oldLogoPath
                );
        }

        return $profile->refresh();
    }
}
