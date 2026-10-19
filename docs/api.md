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
