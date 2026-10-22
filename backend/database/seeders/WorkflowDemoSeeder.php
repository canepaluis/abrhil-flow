<?php

namespace Database\Seeders;

use App\Models\Card;
use App\Models\Sprint;
use App\Models\User;
use App\Services\CardWorkflow;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WorkflowDemoSeeder extends Seeder
{
    private function catalog(string $table, string $name, array $extra = []): int
    {
        $existing = DB::table($table)->where('name', $name)->whereNull('deleted_at')->value('id');
        return $existing ?? DB::table($table)->insertGetId(['name' => $name, ...$extra, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function run(): void
    {
        DB::transaction(function () {
            $settings = DB::table('sprint_settings')->where('id', 1)->lockForUpdate()->first();
            $people = User::whereIn('profile_key', ['luis-alexis-canepa', 'fernando-kumul', 'ana-morales', 'cristopher-itza'])->get()->keyBy('profile_key');
            if ($people->count() !== 4) throw new \RuntimeException('Carga los cuatro perfiles con TeamMemberSeeder antes de generar ejemplos.');
            $luis = $people['luis-alexis-canepa'];
            $fer = $people['fernando-kumul'];
            $today = now(config('sprints.timezone'))->startOfDay();
            $sprint = $settings?->current_sprint_id ? Sprint::find($settings->current_sprint_id) : null;
            $sprint ??= Sprint::whereDate('start_date', '<=', $today->toDateString())->whereDate('end_date', '>=', $today->toDateString())->first();
            if (!$sprint) {
                $end = $today->copy()->addDays(13);
                $next = Sprint::whereDate('start_date', '>', $today->toDateString())->orderBy('start_date')->first();
                if ($next && $end->gte($next->start_date)) $end = $next->start_date->copy()->subDay();
                $sprint = Sprint::create(['name' => 'Sprint de demostración del flujo', 'goal' => 'Evaluar etapas con tarjetas ficticias',
                    'start_date' => $today->toDateString(), 'end_date' => $end->toDateString(),
                    'members_count' => 4, 'daily_minutes' => 480, 'working_days' => [1, 2, 3, 4, 5]]);
            }
            $sprint->participants()->syncWithoutDetaching($people->pluck('id')->all());
            $sprint->update(['members_count' => $sprint->participants()->count()]);
            $team = $this->catalog('teams', 'Equipo AHD');
            $cloud = $this->catalog('projects', 'Abrhil Helpdesk Cloud', ['description' => 'Helpdesk Cloud', 'team_id' => $team]);
            $exe = $this->catalog('projects', 'Abrhil Helpdesk', ['description' => 'Helpdesk Exe (General)', 'team_id' => $team]);
            $platform = $this->catalog('platforms', 'React');
            $type = $this->catalog('card_types', 'Desarrollo', ['description' => 'Desarrollo de funcionalidad']);
            $samples = [
                ['Dashboard', 'Vista compacta de pendientes por sucursal en el inicio', 'documentation_pending', 4, 26, true],
                ['Incidencias', 'Confirmación visual al guardar una nueva minuta', 'documentation_in_progress', 6, 22, false],
                ['Proyectos', 'Guía de criterios para reasignar un proyecto pausado', 'documentation_corrections', 8, 35, true],
                ['Reportes', 'Resumen semanal de solicitudes sin respuesta', 'approval_pending', 4, 44, false],
                ['Notificaciones', 'Selector de destinatarios por equipo en avisos de cierre', 'approval_in_review', 8, 28, true],
                ['Incidencias', 'Indicador de archivos aún pendientes de revisión', 'development_ready', 12, 21, true],
                ['Proyectos', 'Recordatorio configurable antes de vencer una actividad', 'development_in_progress', 10, 19, true],
                ['Configuración', 'Validación de etiquetas duplicadas en categorías', 'development_in_progress', 6, 30, false],
                ['Reportes', 'Descarga de reporte con filtros activos por sucursal', 'development_corrections', 8, 24, true],
                ['Usuarios', 'Restaurar búsqueda al regresar al listado de usuarios', 'testing_pending', 6, 32, false],
                ['Dashboard', 'Comparativo de carga por módulo en el tablero', 'testing_in_progress', 4, 38, true],
                ['Minutas', 'Mostrar zona horaria en la confirmación de reuniones', 'testing_approved', 8, 45, true],
                ['Incidencias', 'Indicador de comentarios internos en el resumen del ticket', 'testing_approved', 6, 36, false],
                ['Recordatorios', 'Ayuda contextual para configurar un recordatorio', 'documentation_pending', 4, 18, true],
            ];
            $descriptions = [
                'Mostrar los pendientes de la sucursal seleccionada, ordenados por vencimiento. Incluir estado vacío y acceso directo a cada registro.',
                'Al guardar una minuta, mostrar su folio y confirmar si los adjuntos se procesaron. Evitar duplicados por doble clic.',
                'Definir quién puede reasignar un proyecto pausado y cómo conservar el avance. El diseño debe contemplar actividades ya iniciadas.',
                'Preparar un resumen semanal de solicitudes sin respuesta, con responsable y antigüedad. Validar destinatarios y fecha de corte.',
                'Permitir elegir equipos destinatarios antes de enviar avisos de cierre. Excluir usuarios inactivos y evitar notificaciones duplicadas.',
                'Identificar archivos pendientes de revisión en los detalles de incidencia. Distinguirlos de archivos revisados sin revelar contenido restringido.',
                'Configurar un recordatorio previo al vencimiento de una actividad. Respetar responsables, zona horaria y cambios de fecha.',
                'Detectar etiquetas repetidas al crear o editar categorías, ignorando espacios y mayúsculas. Mostrar un aviso junto al campo.',
                'Exportar únicamente los registros de la sucursal y filtros activos. Si no hay resultados, descargar encabezados sin reutilizar consultas previas.',
                'Conservar búsqueda y paginación al volver desde el detalle de un usuario. Limpiar la selección si el usuario ya no aparece en el resultado.',
                'Comparar pendientes por módulo y responsable en el tablero. Mantener los totales consistentes al aplicar filtros de sucursal.',
                'Mostrar fecha, hora y zona horaria al confirmar una reunión de minuta. Validar el cambio de día para usuarios en otra zona.',
                'Señalar la existencia de comentarios internos en el resumen del ticket sin exponer su contenido a usuarios cliente.',
                'Agregar ayuda contextual en la configuración de recordatorios, con un ejemplo de destinatarios y fecha de envío antes de guardar.',
            ];
            foreach ($samples as $index => [$module, $title, $state, $hours, $age, $isCloud]) {
                $key = sprintf('DEMO-FLOW-%03d', $index + 1);
                $card = Card::where('demo_key', $key)->first();
                if (!$card) {
                    $stage = CardWorkflow::stage($state);
                    $statusName = match ($stage) { 'documentation' => 'Documentación', 'approval' => 'Aprobación', 'development' => 'Desarrollo', 'testing' => 'Testing', default => 'Liberable' };
                    $origin = match ($state) { 'documentation_corrections' => 'direction', 'development_corrections' => 'testing', default => null };
                    $note = match ($origin) {
                        'direction' => 'Precisar quién autoriza la reasignación y agregar al diseño un ejemplo con actividades ya iniciadas.',
                        'testing' => 'Al exportar desde una sucursal sin resultados se descarga información de la consulta anterior. Corregir y repetir la prueba.',
                        default => null,
                    };
                    $card = Card::create([
                        'demo_key' => $key, 'title' => $title,
                        'description' => $descriptions[$index],
                        'workflow_state' => $state, 'requested_at' => $today->copy()->subDays($age)->toDateString(),
                        'team_id' => $team, 'project_id' => $isCloud ? $cloud : $exe, 'platform_id' => $platform,
                        'module_id' => $this->catalog('modules', $module), 'card_type_id' => $type,
                        'status_id' => $this->catalog('statuses', $statusName, ['order' => array_search($stage, ['documentation', 'approval', 'development', 'testing', 'approved'], true) + 1]),
                        'priority' => ($index % 3) + 1, 'assigned_to' => $isCloud ? $fer->id : $luis->id,
                        'documenter_id' => $luis->id, 'developer_id' => $isCloud ? $fer->id : $luis->id,
                        'tester_id' => $isCloud ? $people['ana-morales']->id : $people['cristopher-itza']->id,
                        'approval_owner_id' => $luis->id, 'creator_id' => $luis->id,
                        'correction_origin' => $origin, 'correction_note' => $note,
                    ]);
                    $path = ['documentation_pending', 'documentation_in_progress', 'approval_pending', 'approval_in_review', 'development_ready', 'development_in_progress', 'testing_pending', 'testing_in_progress', 'testing_approved'];
                    $stop = array_search($state, $path, true);
                    $path = array_slice($path, 0, $stop !== false ? $stop + 1 : ($origin === 'direction' ? 4 : 8));
                    if ($origin) $path[] = $state;
                    $previous = null;
                    foreach ($path as $position => $step) {
                        $card->workflowEvents()->create(['from_state' => $previous, 'to_state' => $step,
                            'note' => $step === $state && $note ? $note : 'Movimiento ficticio de demostración.',
                            'created_at' => $today->copy()->subDays($age)->addDays($position)->setHour(10)]);
                        $previous = $step;
                    }
                }
                // Upgrade only untouched descriptions from the initial demo template.
                if (str_starts_with($card->description, 'Escenario ficticio para ')) {
                    $card->update(['description' => $descriptions[$index]]);
                }
                if (!$sprint->cards()->where('cards.id', $card->id)->exists()) {
                    $sprint->cards()->attach($card->id, ['start_date' => $sprint->start_date->toDateString(),
                        'end_date' => $sprint->end_date->toDateString(), 'estimated_minutes' => $hours * 60,
                        'is_completed' => $state === 'testing_approved']);
                }
            }
            DB::table('sprint_settings')->where('id', 1)->update(['current_sprint_id' => $sprint->id]);
            $this->command?->info('14 tarjetas DEMO-FLOW disponibles en: '.$sprint->name.'. No se modificaron tarjetas reales.');
        });
    }
}
