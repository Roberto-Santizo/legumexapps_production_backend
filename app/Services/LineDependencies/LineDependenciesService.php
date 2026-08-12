<?php

namespace App\Services\LineDependencies;

use App\Errors\BadRequestError;
use App\Errors\NotFoundError;
use App\Interfaces\LineDependencies\LineDependenciesServiceInterface;
use App\Models\LineDependency;
use Override;

class LineDependenciesService implements LineDependenciesServiceInterface
{
    #[Override]
    public function create(array $data)
    {
        $newDependency = LineDependency::create($data);
        return $newDependency;
    }

    #[Override]
    public function get(string $lineId)
    {
        $query = LineDependency::query();

        if (!$lineId) throw new BadRequestError("El ID de la línea es requerido");

        $query->where('line_id', $lineId);
        $query->with(['dependantLine', 'dependantLine.positions']);
        return $query->get();
    }

    #[Override]
    public function delete(string $id)
    {
        $dependency = LineDependency::find($id);
        if (!$dependency) throw new NotFoundError("La dependencia no existe");

        $dependency->delete();

        return true;
    }
}
