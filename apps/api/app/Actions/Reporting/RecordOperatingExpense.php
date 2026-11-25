<?php

namespace App\Actions\Reporting;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class RecordOperatingExpense
{
    private const FIELDS = ['expense_date', 'category', 'description', 'amount'];

    public function create(array $input, int $actor): object
    {
        return DB::transaction(function () use ($input, $actor): object {
            $values = Arr::only($input, self::FIELDS);
            $values['amount'] = (int) $values['amount'];
            $hash = hash('sha256', json_encode([$actor, $values], JSON_THROW_ON_ERROR));
            $now = now();

            $inserted = DB::table('operating_expenses')->insertOrIgnore($values + [
                'request_id' => $input['request_id'], 'request_hash' => $hash,
                'version' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]);

            $expense = DB::table('operating_expenses')->where('request_id', $input['request_id'])->lockForUpdate()->first();
            abort_if($expense === null || ! hash_equals($expense->request_hash, $hash), 409, 'Expense request ID is already used.');

            if ($inserted) {
                $this->recordRevision($expense, $actor);
            }

            return $expense;
        }, 3);
    }

    public function update(int $id, array $input, int $actor): object
    {
        return DB::transaction(function () use ($id, $input, $actor): object {
            $expense = DB::table('operating_expenses')->where('id', $id)->lockForUpdate()->first();
            abort_if($expense === null, 404);
            if ((int) $expense->version !== (int) $input['version']) {
                throw new ConflictHttpException('Expense has been updated. Reload it before retrying.');
            }
            if ($expense->voided_at !== null) {
                throw ValidationException::withMessages(['expense' => 'A voided expense cannot be edited.']);
            }
            $values = Arr::only($input, self::FIELDS);
            if (! $values && empty($input['void'])) {
                throw ValidationException::withMessages(['expense' => 'Supply at least one changed field or void the expense.']);
            }
            DB::table('operating_expenses')->where('id', $id)->update($values + [
                'version' => $expense->version + 1,
                'voided_at' => ! empty($input['void']) ? now() : null,
                'updated_at' => now(),
            ]);
            $expense = DB::table('operating_expenses')->where('id', $id)->first();
            $this->recordRevision($expense, $actor);

            return $expense;
        }, 3);
    }

    private function recordRevision(object $expense, int $actor): void
    {
        DB::table('operating_expense_revisions')->insert(Arr::only((array) $expense, self::FIELDS) + [
            'expense_id' => $expense->id, 'version' => $expense->version,
            'voided_at' => $expense->voided_at, 'actor_id' => $actor, 'recorded_at' => now(),
        ]);
    }
}
