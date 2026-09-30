<?php

namespace App\Services\PackingMaterialTransactions;

use App\Errors\NotFoundError;
use App\Interfaces\PackingMaterialTransactions\PackingMaterialTransactionsServiceInterface;
use App\Models\PackingMaterialTransaction;
use App\Models\PackingMaterialTransactionItem;
use App\Models\WeeklyPlanTask;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

        $uploadedKeys = [];

        try {
            $data['responsable_signature'] = $uploadedKeys[] = $this->uploadSignature($data['responsable_signature']);
            $data['user_signature'] = $uploadedKeys[] = $this->uploadSignature($data['user_signature']);

            $packingMaterialTransaction = DB::transaction(function () use ($data, $items) {
                $packingMaterialTransaction = PackingMaterialTransaction::create($data);

                foreach ($items as $item) {
                    $item['pm_transaction_id'] = $packingMaterialTransaction->id;
                    PackingMaterialTransactionItem::create($item);
                }

                return $packingMaterialTransaction;
            });
        } catch (\Throwable $th) {
            if ($uploadedKeys !== []) {
                Storage::disk('s3')->delete($uploadedKeys);
            }

            throw $th;
        }

        $task->status = 2;
        $task->save();

        return $packingMaterialTransaction->load('items');
    }

    #[Override]
    public function getPackingMaterialTransactions(?string $limit, Request $request)
    {
        $query = PackingMaterialTransaction::query();

        if ($request->query('reference')) {
            $query->where('reference', 'LIKE', '%'.$request->query('reference').'%');
        }

        if ($request->query('responsable')) {
            $query->where('responsable', 'LIKE', '%'.$request->query('responsable').'%');
        }

        if ($request->filled('type')) {
            $query->where('type', $request->query('type'));
        }

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

    /**
     * Upload a signature image to S3 with public visibility and return its key.
     */
    private function uploadSignature(UploadedFile $signature): string
    {
        return Storage::disk('s3')->putFileAs(
            'packing-material-transactions/signatures',
            $signature,
            Str::uuid().'.png',
            ['visibility' => 'public'],
        );
    }
}
