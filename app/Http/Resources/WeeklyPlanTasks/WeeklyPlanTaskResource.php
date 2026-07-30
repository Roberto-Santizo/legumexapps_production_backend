<?php

namespace App\Http\Resources\WeeklyPlanTasks;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WeeklyPlanTaskResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */

    private $statusMessages = [
        1 => 'Pendiente Entrega Material de Empaque',
        2 => 'Lista para confirmación de asignaciónes',
        3 => 'Lista para ejecución',
        4 => 'En Progreso',
        5 => 'Finalizada',
    ];
    public function toArray(Request $request): array
    {
        return [
            'id' =>                     $this->id,
            'boxes' =>                  $this->boxes,
            'produced_boxes' =>         $this->produced_boxes,
            'pallets' =>                $this->pallets,
            'produced_pallets' =>       $this->produced_pallets,
            'hours' =>                  $this->hours,
            'weighed_pounds' =>         $this->weighed_pounds,
            'destination' =>            $this->destination,
            'operation_date' =>         $this->operation_date,
            'operation_date_string' =>  $this->operation_date ? $this->operation_date->format('d-m-Y') : 'SIN PROGRAMACIÓN',
            'start_date' =>             $this->start_date,
            'end_date' =>               $this->end_date,
            'weekly_plan_id' =>         $this->weekly_plan_id,
            'line_sku_id' =>            $this->line_sku_id,
            'sku_name'=>                $this->performance->sku->product_name,
            'sku_code'=>                $this->performance->sku->code,
            'line_name'=>               $this->performance->line->name,
            'line_code'=>               $this->performance->line->code,
            'status'=>                  $this->statusMessages[$this->status]
        ];
    }
}
