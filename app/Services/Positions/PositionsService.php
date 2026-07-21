<?php

namespace App\Services\Positions;

use App\Errors\NotFoundError;
use App\Interfaces\Positions\PositionsServiceInterface;
use App\Models\Position;
use Illuminate\Http\Request;
use Override;

class PositionsService implements PositionsServiceInterface
{
    #[Override]
    public function createPosition(array $data)
    {
        $position = Position::create($data);

        return $position;
    }

    #[Override]
    public function getPositions(?string $limit, Request $request)
    {
        $query = Position::query();

        if($request->query('lineId')) $query->where('line_id', $request->query('lineId'));

        if ($limit) {
            return $query->paginate($limit);
        }

        return $query->get();
    }

    #[Override]
    public function getPositionById(string $id)
    {
        $position = Position::find($id);
        if (! $position) {
            throw new NotFoundError('El puesto no existe');
        }

        return $position;
    }

    #[Override]
    public function updatePositionById(array $data, string $id)
    {
        $position = $this->getPositionById($id);
        $position->update($data);

        return true;
    }

    #[Override]
    public function deletePositionById(string $id)
    {
        $position = $this->getPositionById($id);
        $position->delete();

        return true;
    }
}
