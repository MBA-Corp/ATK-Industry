<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Reporting\RecordOperatingExpense;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class OperatingExpenseController extends Controller
{
    private const FIELDS = ['id', 'expense_date', 'category', 'description', 'amount', 'version', 'voided_at'];

    public function store(Request $request, RecordOperatingExpense $record): JsonResponse
    {
        $input = $request->validate($this->rules('required') + ['request_id' => ['required', 'uuid']]);
        $expense = $record->create($input, $request->user()->id);

        return $this->response($expense);
    }

    public function update(Request $request, RecordOperatingExpense $record, int $expenseId): JsonResponse
    {
        $input = $request->validate($this->rules('sometimes') + [
            'version' => ['required', 'integer', 'min:1'],
            'void' => ['sometimes', 'boolean'],
        ]);
        $expense = $record->update($expenseId, $input, $request->user()->id);

        return $this->response($expense);
    }

    public function show(int $expenseId): JsonResponse
    {
        $expense = DB::table('operating_expenses')->where('id', $expenseId)->first();
        abort_if($expense === null, 404);

        return $this->response($expense);
    }

    public function history(Request $request, int $expenseId): JsonResponse
    {
        abort_unless(DB::table('operating_expenses')->where('id', $expenseId)->exists(), 404);
        $request->validate(['per_page' => ['sometimes', 'integer', 'between:1,100']]);
        $history = DB::table('operating_expense_revisions')->where('expense_id', $expenseId)
            ->select('version', 'expense_date', 'category', 'description', 'amount', 'voided_at', 'actor_id', 'recorded_at')
            ->orderBy('version')->paginate($request->integer('per_page', 25))
            ->through(function (object $version): array {
                $row = (array) $version;
                $row['amount'] = (string) $row['amount'];

                return $row;
            });

        return response()->json($history)->header('Cache-Control', 'private, no-store');
    }

    private function rules(string $presence): array
    {
        return [
            'expense_date' => [$presence, 'required', 'date_format:Y-m-d', 'after_or_equal:1000-01-01'],
            'category' => [$presence, 'required', 'string', 'max:80'],
            'description' => [$presence, 'required', 'string', 'max:255'],
            'amount' => [$presence, 'required', 'integer', 'min:1', 'max:'.PHP_INT_MAX],
        ];
    }

    private function response(object $expense): JsonResponse
    {
        $data = Arr::only((array) $expense, self::FIELDS);
        $data['amount'] = (string) $data['amount'];

        return response()->json(['data' => $data])->header('Cache-Control', 'private, no-store');
    }
}
