# Abrhil Flow

Herramienta para gestión de sprints y planificación diaria orientada a equipos de desarrollo.

## Objetivo

Reemplazar el uso de Excel para:
- Seguimiento de tareas
- Planeación diaria
- Visualización de carga de trabajo

## Stack

- Frontend: React (Vite)
- Backend: Laravel
- Base de datos: (por definir)

## Estado

En desarrollo (fase inicial)
## Pantalla de cards (desarrollo local)

1. Configura la base de datos en `backend/.env` e instala las dependencias con `composer install` si es necesario.
2. Ejecuta `php artisan migrate` desde `backend`. La pantalla requiere datos en los catálogos de equipos, proyectos, plataformas, tipos y estados. Los seeders existentes `FillTeamsCatalog`, `FillProjectCatalog`, `FillPlatformCatalog`, `FillCardTypeCatalog` y `FillStatusCatalog` pueden ejecutarse individualmente con `php artisan db:seed --class=NombreDelSeeder` en una base nueva (equipo antes que proyecto). No los repitas sobre datos existentes: insertan registros y el seeder de proyectos presupone el equipo con ID 1. Módulos y responsables son opcionales.
3. Desde `backend`, inicia `php artisan serve --host=127.0.0.1 --port=8000`.
4. Desde `frontend`, instala dependencias con `npm install` si es necesario e inicia `npm run dev -- --port 5173 --strictPort`.
5. Abre http://localhost:5173. Puedes consultar, buscar por título y crear cards. El listado se actualiza después de guardar.

`frontend/.env` contiene `VITE_API_URL=http://127.0.0.1:8000/api`. Para otra instalación copia `.env.example` a `.env` y ajusta la dirección. Reinicia Vite cuando cambies esta variable. Las variables VITE son públicas y no deben contener secretos.

CORS permite por defecto http://localhost:5173 y http://127.0.0.1:5173. Para otro origen agrega `FRONTEND_URL` a `backend/.env` (varios orígenes separados por comas) y ejecuta `php artisan config:clear`.

La API incluye `GET /api/card-catalogs`, `GET /api/cards` y `POST /api/cards`. La descripción es obligatoria, conforme a la tabla actual. Las rutas mantienen el acceso sin autenticación del proyecto; la autenticación sigue pendiente para el uso real.

Validación: `npm run build` y `npm run lint` desde frontend; `php artisan test` desde backend. Los tests de cards usan SQLite en memoria y no escriben en la base de desarrollo.

Si PHP indica `could not find driver` para SQLite, puedes ejecutar los tests sin modificar php.ini con `php -d extension=pdo_sqlite vendor/phpunit/phpunit/phpunit` desde backend (requiere que la extensión esté instalada).

## Planeación de sprints

El módulo **Planeación de sprints** permite crear y editar nombre, objetivo, periodo, integrantes, horas diarias y días laborables. Por defecto usa lunes a viernes y 8 horas al día. Cada tarjeta seleccionada tiene fechas propias dentro del sprint y una estimación de esfuerzo en horas; la API almacena minutos enteros. Las fechas son inclusivas y el periodo máximo es de un año.

Capacidad = días laborables × integrantes × minutos diarios. El esfuerzo total es la suma de las estimaciones de las tarjetas. La sobrecarga se advierte sin bloquear el guardado. No se descuentan festivos, vacaciones ni reuniones; ajusta horas diarias o días laborables para reflejar la disponibilidad real. No se calcula capacidad individual ni se impide planear una tarjeta en varios sprints: cada sprint conserva su propia estimación. Las barras representan el periodo planeado, no progreso ni un reparto automático de horas.

La migración `2026_10_18_000001_create_sprints_table.php` agrega `sprints` y `sprint_cards` sin modificar las tarjetas existentes. En otros entornos ejecuta `php artisan migrate`. No hace falta cambiar `VITE_API_URL`: ambos módulos comparten la conexión de `frontend/.env`.

Al editar, el guardado reemplaza la selección completa de tarjetas del sprint. Quitar una tarjeta del plan no elimina la tarjeta. Una tarjeta archivada permanece visible en los planes existentes para conservar el esfuerzo histórico; debes quitarla del plan antes de volver a guardarlo. La versión inicial no incluye cierre de sprint, control de concurrencia entre editores ni permisos de acceso.
