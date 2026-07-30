<?php

namespace App\Services\Lines;

use App\Errors\NotFoundError;
use App\Interfaces\Lines\LinesServiceInterface;
use App\Models\Line;
use Illuminate\Http\Request;
use Override;

class LinesService implements LinesServiceInterface
{
    #[Override]
    public function createLine(array $data)
    {
        $newLine = Line::create($data);

        return $newLine;
    }

    #[Override]
    public function getLines(Request $request, ?string $limit)
    {
        $query = Line::query();
        $skuId = $request->query('skuId');

        if($skuId){
            $query->whereHas('performances', function ($p0) use($skuId) {
                $p0->where('sku_id', $skuId);
            });
        }

        if ($limit) {
            return $query->paginate($limit);
        }

        return $query->get();
    }

    #[Override]
    public function getLineById(string $id)
    {
        throw new \Exception('Not implemented');
    }

    #[Override]
    public function getLineByCode(string $code)
    {
        $line = Line::where('code', '=', $code)->first();
        if (! $line) {
            throw new NotFoundError('La línea no existe');
        }

        return $line;
    }

    #[Override]
    public function updateLineById(string $id, array $data)
    {
        $line = $this->getLineByCode($id);
        $line->update($data);
        $line->save();

        return true;
    }

    #[Override]
    public function deleteLineById(string $id)
    {
        $line = $this->getLineByCode($id);
        $line->delete();

        return true;
    }
}
