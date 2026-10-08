<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Company\UpdateCompanyProfileAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCompanyProfileRequest;
use App\Http\Resources\CompanyProfileResource;
use App\Models\CompanyProfile;
use Illuminate\Http\JsonResponse;

class CompanyProfileController extends Controller
{
    public function show(): JsonResponse
    {
        $profile = CompanyProfile::query()
            ->where(
                'profile_key',
                'PRIMARY'
            )
            ->first();

        return response()->json([
            'data' => $profile === null
                ? null
                : (
                    new CompanyProfileResource(
                        $profile
                    )
                )->resolve(),
        ]);
    }

    public function update(
        UpdateCompanyProfileRequest $request,
        UpdateCompanyProfileAction $action
    ): CompanyProfileResource {
        $profile = $action->execute(
            $request->validated()
        );

        return new CompanyProfileResource(
            $profile
        );
    }
}
