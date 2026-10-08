<?php

namespace App\Services\LineFields;

use App\Errors\BadRequestError;
use App\Errors\NotFoundError;
use App\Interfaces\CaptureFields\CaptureFieldsServiceInterface;
use App\Interfaces\LineFields\LineFieldsServiceInterface;
use App\Interfaces\Lines\LinesServiceInterface;
use App\Models\CaptureField;
use App\Models\LineField;
use Override;

class LineFieldsService implements LineFieldsServiceInterface
{
    public function __construct(
        private LinesServiceInterface $linesService,
        private CaptureFieldsServiceInterface $captureFieldsService,
    ) {}

    #[Override]
    public function getLineFields(string $lineCode)
    {
        $line = $this->linesService->getLineByCode($lineCode);

        return $line->lineFields()
            ->with('captureField')
            ->orderBy('order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array{capture_field_id: int, is_required?: bool, order?: int, label?: string|null}  $data
     */
    #[Override]
    public function createLineField(string $lineCode, array $data)
    {
        $line = $this->linesService->getLineByCode($lineCode);
        $captureField = $this->captureFieldsService->getCaptureFieldById($data['capture_field_id']);

        if ($captureField->capture_type !== null && $captureField->capture_type !== $line->capture_type) {
            throw new BadRequestError('El campo no pertenece a la familia de captura de la línea');
        }

        if ($line->lineFields()->where('capture_field_id', $captureField->id)->exists()) {
            throw new BadRequestError('El campo ya está asignado a la línea');
        }

        $isRequired = $data['is_required'] ?? false;
        $this->ensureCalculatedIsNotRequired($captureField, $isRequired);

        return LineField::create([
            'line_id' => $line->id,
            'capture_field_id' => $captureField->id,
            'is_required' => $isRequired,
            'order' => $data['order'] ?? 0,
            'label' => $data['label'] ?? null,
        ]);
    }

    #[Override]
    public function getLineFieldById(string $id)
    {
        $lineField = LineField::with('captureField')->find($id);

        if (! $lineField) {
            throw new NotFoundError('El campo de la línea no existe');
        }

        return $lineField;
    }

    /**
     * @param  array{is_required?: bool, order?: int, label?: string|null}  $data
     */
    #[Override]
    public function updateLineFieldById(string $id, array $data)
    {
        $lineField = $this->getLineFieldById($id);

        $this->ensureCalculatedIsNotRequired($lineField->captureField, $data['is_required'] ?? false);

        $lineField->update($data);

        return true;
    }

    #[Override]
    public function deleteLineFieldById(string $id)
    {
        $lineField = $this->getLineFieldById($id);

        $lineField->delete();

        return true;
    }

    private function ensureCalculatedIsNotRequired(CaptureField $captureField, bool $isRequired): void
    {
        if ($captureField->is_calculated && $isRequired) {
            throw new BadRequestError('Un campo calculado no puede ser obligatorio');
        }
    }
}
