## Cards (implementado)

GET /api/cards
POST /api/cards
GET /api/cards/{id}
GET /api/card-catalogs

Los catálogos devuelven `data` con teams, projects (incluye team_id), platforms, statuses, card_types, modules y users (solo id y name).

POST /api/cards requiere title, description, team_id, project_id, platform_id, status_id y card_type_id. assigned_to, module_id, priority y jira_link son opcionales. Devuelve 201 con la card en data; errores de validación devuelven 422 con errors por campo.

## Daily Plan (implementado)

POST /api/daily-plan

## Pendiente de implementación

Edición y eliminación de cards, cierre de sprints y consulta de planeación diaria por fecha. La ruta /tasks del diseño inicial todavía no existe.

## Sprints (implementado)

- `GET /api/sprints`: planes con tarjetas, fechas y capacidad calculada.
- `GET /api/sprints/{id}`: detalle de un sprint.
- `POST /api/sprints`: crear plan (201).
- `PUT /api/sprints/{id}`: reemplazar datos y selección de tarjetas (200).

Ejemplo de cuerpo para crear o actualizar:

```json
{
  "name": "Sprint 01",
  "goal": "Entregar el portal",
  "start_date": "2026-10-19",
  "end_date": "2026-10-30",
  "participant_ids": [1, 2],
  "daily_minutes": 480,
  "working_days": [1, 2, 3, 4, 5],
  "cards": [
    { "id": 1, "start_date": "2026-10-19", "end_date": "2026-10-21", "estimated_minutes": 720 }
  ]
}
```

Días ISO: lunes=1, domingo=7. `cards` puede estar vacío y debe enviarse incluso al actualizar. Las estimaciones deben ser positivas, las tarjetas deben existir y sus fechas deben caer dentro del sprint, incluir algún día laborable y respetar inicio <= fin. La selección no acepta IDs duplicados. Las escrituras son transaccionales; los errores devuelven 422 con `errors` por campo. `capacity_minutes`, `estimated_minutes`, `remaining_minutes` y `working_dates` se calculan en el servidor. No se bloquea la sobrecarga ni la reutilización de una tarjeta en otro sprint.

## Sprint actual, historial y resolución

- `GET /api/sprints/current`: `{data: Sprint|null, mode: "automatic"|"manual", today: "YYYY-MM-DD", timezone: "America/Cancun"}`. En automático usa exclusivamente el último ID creado y verifica las fechas inclusivas del periodo. En manual respeta la selección incluso fuera de fechas.
- `POST /api/sprints/current`: `{ "sprint_id": 123 }` fija un sprint globalmente; `{ "sprint_id": null }` restablece automático. El campo es obligatorio y el ID debe existir.
- `GET /api/sprints/history`: sprints cuya fecha de fin es anterior a hoy, ordenados por fin e ID descendentes.
- `POST /api/sprints/{sprint}/cards/{card}/completion`: `{ "is_completed": true }` registra resolución por sprint. Usa false para corregirla. Devuelve el sprint actualizado; 404 si la tarjeta no pertenece al sprint y 422 si la marca no es booleana.

La creación y edición de sprints aceptan `cards[].is_completed` opcional. Si se omite al actualizar una relación existente, conserva su valor; para relaciones nuevas toma false. Esto permite registrar sprints antiguos con resultados explícitos. Los recursos de sprints incluyen `completed_cards`, `completed_minutes` y, por tarjeta, `is_completed`, `description`, `priority`, `jira_link` y `card_type`, además de los campos anteriores. La marca de resolución es independiente del estado global de la tarjeta y se conserva aunque se archive. `SPRINT_TIMEZONE` configura la fecha usada por selección automática e historial.

La creación y edición de sprints rechazan cualquier solapamiento de fechas con otro sprint (422 en start_date y end_date). Las fechas son inclusivas y se compara todo el periodo, incluidos días no laborables. Un sprint se excluye a sí mismo al editar. La comprobación se ejecuta bajo un bloqueo transaccional compartido por todas las escrituras de planes, antes de cambiar datos o tarjetas.

## Equipo

`GET /api/users` devuelve `{ "data": [{ "id": 1, "name": "…", "roles": [{ "id": 1, "slug": "developer", "name": "Desarrollador" }], "responsibilities": ["Desarrollo"], "time_off": [] }] }`.

No devuelve correo, contraseña, tokens ni claves internas. Los roles iniciales son project-lead, developer y tester. Cada persona puede tener varios roles, con responsabilidades propias. No son permisos de acceso y no habilitan autenticación. El directorio es de consulta; los perfiles iniciales se cargan con TeamMemberSeeder. `GET /api/card-catalogs` mantiene users como lista de id y name para asignaciones.

## Ausencias y capacidad del sprint

- `POST /api/users/{user}/time-off`: crear ausencia, devuelve 201.
- `PUT /api/users/{user}/time-off/{timeOff}`: editar tipo y fechas, devuelve 200.
- `POST /api/users/{user}/time-off/{timeOff}/status`: `{ "is_cancelled": true }` cancela; false reactiva.

Cuerpo de creación/edición: `{ "type": "vacation", "start_date": "2026-10-19", "end_date": "2026-10-23" }`. type acepta vacation o day_off. Las fechas son inclusivas; fin debe ser igual o posterior al inicio. 422 para datos inválidos; 404 si la ausencia no pertenece al usuario de la ruta. GET /api/users incluye time_off, con id, tipo, fechas e is_cancelled. Se conservan los registros cancelados.

POST/PUT /api/sprints requiere `participant_ids`: array no vacío de IDs de usuarios existentes, sin duplicados. members_count es opcional por compatibilidad, pero se ignora su valor y se deriva del número de participantes. Los clientes antiguos deben enviar participant_ids al crear/editar.

Los recursos Sprint incluyen gross_capacity_minutes, absence_minutes, capacity_minutes (neta), requires_participants, participants y planning_warnings. Cada participante incluye id, name, absent_dates, available_minutes y estimated_minutes. Cada tarjeta incluye assigned_to_id, absence_dates, available_minutes y planning_warnings. La API de tarjetas agrega assigned_to.id, sin cambiar assigned_to.name.

Solo los participantes con rol developer aportan capacidad de desarrollo; se descuentan sus días laborables ausentes una sola vez. Los avisos de disponibilidad no bloquean el guardado ni reprograman las tarjetas. Todas las consultas de sprints recalculan la capacidad con las ausencias activas actuales, también para sprints pasados. Los sprints anteriores sin participantes devuelven capacidad de desarrollo cero y requieren configuración explícita.

## Alcance de la capacidad de desarrollo

capacity_minutes, gross_capacity_minutes y absence_minutes se refieren exclusivamente a participantes con rol developer. developer_count indica cuántos aportan capacidad; los roles múltiples no duplican horas. participants[].counts_toward_capacity indica si su disponibilidad se suma al total de desarrollo. Se mantiene la disponibilidad individual de testers y otros roles como información separada.

estimated_minutes conserva el esfuerzo total de todas las tarjetas. development_estimated_minutes suma el esfuerzo asignado a desarrolladores; other_estimated_minutes, el asignado a otros roles definidos; unclassified_estimated_minutes, el de tarjetas sin responsable o sin roles definidos. remaining_minutes = capacity_minutes - development_estimated_minutes. Las tres categorías suman estimated_minutes. cards[].capacity_scope identifica development, other o unclassified. Un desarrollador que no sea participante aporta demanda, pero no capacidad, y conserva el aviso de no pertenecer al sprint.
