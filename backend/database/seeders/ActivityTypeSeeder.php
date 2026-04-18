<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Seeder;

class ActivityTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('activity_types')->insert([
            [
                'name' => 'Desarrollo',
                'description' => 'Desarrollo de tarjetas'
            ],
            [
                'name' => 'Documentación',
                'description' => 'Documentación de tarjetas'
            ],
            [
                'name' => 'Pruebas',
                'description' => 'Actividades de índole de pruebas de desarrollo',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ],
            [
                'name' => 'Reuniones',
                'description' => 'Juntas para revisión de temas con índole en el desarrollo',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ],
            [
                'name' => 'Soporte',
                'description' => 'Actividades para revisión de malas funcionalidades',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ],
            [
                'name' => 'Review',
                'description' => 'Actividades ligadas a la presentación de avances para los PO',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ],
            [
                'name' => 'Planeación',
                'description' => 'Actividades de índole de planeación para sprints',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ],
        ]);
    }
}
