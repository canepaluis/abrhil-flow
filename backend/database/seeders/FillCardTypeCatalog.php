<?php

namespace Database\Seeders;

use App\Models\CardType;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class FillCardTypeCatalog extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            [
                'name' => 'Desarrollo',
                'description' => 'Tarjetas para nuevos desarrollos',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ],
                    [
                'name' => 'Bug',
                'description' => 'Tarjetas para defectos en funcionamiento',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ],
            [
                'name' => 'Task',
                'description' => 'Tarjetas para tareas de soporte',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ],
            [
                'name' => 'HotFix',
                'description' => 'Tarjetas para fallos de emergencia',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ],
            [
                'name' => 'Soporte',
                'description' => 'Tarjetas para tareas de soporte a usuarios',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]
        ];
        
        CardType::insert($data); 
    }
}
