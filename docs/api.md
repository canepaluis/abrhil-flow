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
  "members_count": 2,
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

`GET /api/users` devuelve `{ "data": [{ "id": 1, "name": "…", "roles": [{ "id": 1, "slug": "developer", "name": "Desarrollador" }], "responsibilities": ["Desarrollo"] }] }`.

No devuelve correo, contraseña, tokens ni claves internas. Los roles iniciales son project-lead, developer y tester. Cada persona puede tener varios roles, con responsabilidades propias. No son permisos de acceso y no habilitan autenticación. El directorio es de consulta; los perfiles iniciales se cargan con TeamMemberSeeder. `GET /api/card-catalogs` mantiene users como lista de id y name para asignaciones.
