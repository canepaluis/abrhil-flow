<?php

namespace Database\Seeders;

use App\Models\Project;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class FillProjectCatalog extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Project::insert([
            [
                'name' => 'Abrhil Helpdesk Cloud',
                'description' => 'Proyecto de Helpdesk en su versión Cloud',
                'team_id' => 1,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ],
            [
                'name' => 'Abrhil Helpdesk',
                'description' => 'Proyecto de Helpdesk en su versión Exe',
                'team_id' => 1,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ],
        ]);
    }
}
