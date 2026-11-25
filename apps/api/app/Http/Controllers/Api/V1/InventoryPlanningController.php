<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Inventory\QueryInventoryPlans;
use App\Http\Controllers\Controller;
use App\Http\Requests\InventoryPlanningPolicyRequest;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryPlanningController extends Controller
{
    public function show(QueryInventoryPlans $plans, int $productId): JsonResponse
    {
        return DB::transaction(function () use ($plans, $productId): JsonResponse {
            $products = $plans->products()->where('p.id', $productId)->get();
            abort_if($products->isEmpty(), 404);

            return $this->respond(['data' => $plans->calculate($products)->sole()]);
        });
    }

    public function update(InventoryPlanningPolicyRequest $request, QueryInventoryPlans $plans, int $productId): JsonResponse
    {
        return DB::transaction(function () use ($request, $plans, $productId): JsonResponse {
            Product::query()->where('is_active', true)->lockForUpdate()->findOrFail($productId);
            $product = $plans->products()->where('p.id', $productId)->first();
            if ($product->base_unit_code === null) {
                throw ValidationException::withMessages(['product' => 'An active base unit is required.']);
            }
            $data = $request->validated();
            $activationDay = CarbonImmutable::parse($product->created_at, 'UTC')->setTimezone('Asia/Jakarta');
            // The creation day is partial unless it started exactly at local midnight.
            if (! $activationDay->isStartOfDay()) {
                $activationDay = $activationDay->addDay();
            }
            if ($data['history_from'] < $activationDay->toDateString()) {
                throw ValidationException::withMessages(['history_from' => 'History must start on or after the first full day of product records.']);
            }

            DB::table('inventory_planning_policies')->updateOrInsert(['product_id' => $productId], $data + [
                'updated_by' => $request->user()->id, 'updated_at' => now(),
            ]);

            return $this->respond(['data' => $plans->calculate($plans->products()->where('p.id', $productId)->get())->sole()]);
        }, 3);
    }

    public function alerts(Request $request, QueryInventoryPlans $plans): JsonResponse
    {
        $data = $request->validate([
            'after_product_id' => ['sometimes', 'integer', 'min:0'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'kind' => ['sometimes', 'in:reorder,overstock,insufficient_data'],
        ]);

        return DB::transaction(function () use ($data, $plans): JsonResponse {
            $size = (int) ($data['per_page'] ?? 25);
            $products = $plans->products()->where('p.id', '>', $data['after_product_id'] ?? 0)->limit($size + 1)->get();
            $window = $products->take($size);
            $alerts = $plans->calculate($window)->filter(fn (array $plan) => match ($data['kind'] ?? 'reorder') {
                'overstock' => $plan['overstock'],
                'insufficient_data' => $plan['method'] === 'insufficient_data',
                default => in_array($plan['stock_status'], ['out_of_stock', 'critical', 'reorder_needed'], true),
            })->map(function (array $plan): array {
                unset($plan['daily_demand']);

                return $plan;
            })->values();

            return $this->respond([
                'data' => $alerts,
                'next_after_product_id' => $products->count() > $size ? (int) $window->last()->id : null,
            ]);
        });
    }

    private function respond(array $payload): JsonResponse
    {
        return response()->json($payload)->header('Cache-Control', 'private, no-store');
    }
}
