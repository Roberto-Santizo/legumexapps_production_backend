<?php

namespace App\Services\CaptureFields;

use App\Enums\CaptureFieldDataType;
use App\Errors\BadRequestError;
use App\Errors\NotFoundError;
use App\Interfaces\CaptureFields\CaptureFieldsServiceInterface;
use App\Models\CaptureField;
use Illuminate\Http\Request;
use Override;

class CaptureFieldsService implements CaptureFieldsServiceInterface
{
    #[Override]
    public function createCaptureField(array $data)
    {
        $this->ensureKeyIsAvailable($data['key']);

        $newCaptureField = CaptureField::create([
            'key' => $data['key'],
            'label' => $data['label'],
            'data_type' => $data['data_type'],
            'capture_type' => null,
            'is_system' => false,
            'is_calculated' => false,
            'depends_on' => null,
            'options' => $data['options'] ?? null,
        ]);

        return $newCaptureField;
    }

    #[Override]
    public function getCaptureFields(?string $limit, Request $request)
    {
        $query = CaptureField::query()->withExists('lineFields');

        if ($request->filled('captureType')) {
            $query->where(function ($p0) use ($request) {
                $p0->where('capture_type', $request->query('captureType'));
                $p0->orWhereNull('capture_type');
            });
        }

        if ($request->filled('isSystem')) {
            $query->where('is_system', $request->boolean('isSystem'));
        }

        $query->orderByRaw('capture_type IS NULL')->orderBy('capture_type')->orderBy('id');

        if ($limit) {
            return $query->paginate($limit);
        }

        return $query->get();
    }

    #[Override]
    public function getCaptureFieldById(string $id)
    {
        $captureField = CaptureField::withExists('lineFields')->find($id, ['*']);
        if (! $captureField) {
            throw new NotFoundError('El campo no existe');
        }

        return $captureField;
    }

    #[Override]
    public function updateCaptureFieldById(string $id, array $data)
    {
        $captureField = $this->getCaptureFieldById($id);

        if ($captureField->is_system) {
            throw new BadRequestError('Los campos de sistema no se pueden modificar');
        }

        if (isset($data['key']) && $data['key'] !== $captureField->key) {
            $this->ensureKeyIsAvailable($data['key'], $captureField->id);
        }

        $dataType = CaptureFieldDataType::from($data['data_type'] ?? $captureField->data_type->value);
        if ($dataType !== CaptureFieldDataType::Select) {
            $data['options'] = null;
        }

        $captureField->update($data);

        return true;
    }

    #[Override]
    public function deleteCaptureFieldById(string $id)
    {
        $captureField = $this->getCaptureFieldById($id);

        if ($captureField->is_system) {
            throw new BadRequestError('Los campos de sistema no se pueden eliminar');
        }

        $captureField->delete();

        return true;
    }

    /**
     * Keys are unique across the whole catalog: the unique index does not cover global (null) fields in pgsql.
     */
    private function ensureKeyIsAvailable(string $key, ?int $ignoreId = null): void
    {
        $isTaken = CaptureField::where('key', $key)
            ->when($ignoreId, fn ($p0) => $p0->where('id', '<>', $ignoreId))
            ->exists();

        if ($isTaken) {
            throw new BadRequestError("Ya existe un campo con la clave {$key}");
        }
    }
}
