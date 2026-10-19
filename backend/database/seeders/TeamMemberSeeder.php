<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TeamMemberSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $roleIds = [];
            foreach (['project-lead' => 'Líder de proyecto', 'developer' => 'Desarrollador', 'tester' => 'Tester'] as $slug => $name) {
                $roleIds[$slug] = Role::updateOrCreate(['slug' => $slug], ['name' => $name])->id;
            }
            $profiles = [
                ['luis-alexis-canepa', 'Luis Alexis Canepa', ['project-lead', 'developer'], ['Diseño', 'Documentación', 'Planeación', 'Desarrollo', 'Liberación', 'Presentación de avances a dirección']],
                ['fernando-kumul', 'Fernando Kumul', ['developer'], ['Diseño', 'Documentación', 'Desarrollo']],
                ['ana-morales', 'Ana Morales', ['tester'], ['Testing']],
                ['cristopher-itza', 'Cristopher Itza', ['tester'], ['Testing']],
            ];
            foreach ($profiles as [$key, $name, $roles, $responsibilities]) {
                $user = User::where('profile_key', $key)->first()
                    ?? User::where('name', $name)->whereNull('profile_key')->first()
                    ?? new User;
                // Preserve any real email/password and existing card assignments.
                $user->fill(['profile_key' => $key, 'name' => $name, 'responsibilities' => $responsibilities])->save();
                $user->roles()->sync(array_map(fn ($slug) => $roleIds[$slug], $roles));
            }
        });
    }
}
