<?php

namespace App\Services\PackingMaterialTransactions;

use App\Errors\NotFoundError;
use App\Interfaces\PackingMaterialTransactions\PackingMaterialTransactionsServiceInterface;
use App\Models\PackingMaterialTransaction;
use App\Models\PackingMaterialTransactionItem;
use App\Models\WeeklyPlanTask;
use Illuminate\Support\Facades\DB;
use Override;

class PackingMaterialTransactionsService implements PackingMaterialTransactionsServiceInterface
{
    #[Override]
    public function createPackingMaterialTransaction(array $data)
    {
        $items = $data['items'];
        $data['user_id'] = auth()->user()->id;
        unset($data['items']);
        $task = WeeklyPlanTask::find($data['weekly_plan_task_id']);

        $packingMaterialTransaction = DB::transaction(function () use ($data, $items) {
            $packingMaterialTransaction = PackingMaterialTransaction::create($data);

            foreach ($items as $item) {
                $item['pm_transaction_id'] = $packingMaterialTransaction->id;
                PackingMaterialTransactionItem::create($item);
            }

            return $packingMaterialTransaction;
        });

        $task->status = 2;
        $task->save();
        return $packingMaterialTransaction->load('items');
    }

    #[Override]
    public function getPackingMaterialTransactions(?string $limit)
    {
        $query = PackingMaterialTransaction::query();

        if ($limit) {
            return $query->paginate($limit);
        }

        return $query->get();
    }

    #[Override]
    public function getPackingMaterialTransactionById(string $id)
    {
        $packingMaterialTransaction = PackingMaterialTransaction::find($id);
        if (! $packingMaterialTransaction) {
            throw new NotFoundError('La transacción de material de empaque no existe');
        }

        return $packingMaterialTransaction;
    }

    #[Override]
    public function updatePackingMaterialTransactionById(array $data, string $id)
    {
        $packingMaterialTransaction = $this->getPackingMaterialTransactionById($id);
        $packingMaterialTransaction->update($data);

        return true;
    }

    #[Override]
    public function deletePackingMaterialTransactionById(string $id)
    {
        $packingMaterialTransaction = $this->getPackingMaterialTransactionById($id);

        DB::transaction(function () use ($packingMaterialTransaction): void {
            $packingMaterialTransaction->items()->delete();
            $packingMaterialTransaction->delete();
        });

        return true;
    }
}
