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

Capacidad neta de desarrollo = suma de días laborables disponibles de los participantes con rol Desarrollador × minutos diarios. Solo se descuentan sus días libres y vacaciones. Los testers y otros roles conservan su seguimiento, pero no aportan horas a desarrollo. El esfuerzo total es la suma de las estimaciones de las tarjetas. La sobrecarga se advierte sin bloquear el guardado. No se descuentan festivos ni reuniones automáticamente; ajusta horas diarias o días laborables para reflejar la disponibilidad real. Se muestra disponibilidad individual y se permite planear una tarjeta en distintos sprints, cada uno con su propia estimación. Las barras representan el periodo planeado, no progreso ni reparto automático de horas.

La migración `2026_10_18_000001_create_sprints_table.php` agrega `sprints` y `sprint_cards` sin modificar las tarjetas existentes. En otros entornos ejecuta `php artisan migrate`. No hace falta cambiar `VITE_API_URL`: ambos módulos comparten la conexión de `frontend/.env`.

Al editar, el guardado reemplaza la selección completa de tarjetas del sprint. Quitar una tarjeta del plan no elimina la tarjeta. Una tarjeta archivada permanece visible en los planes existentes para conservar el esfuerzo histórico; debes quitarla del plan antes de volver a guardarlo. La versión inicial no incluye cierre de sprint, control de concurrencia entre editores ni permisos de acceso.

## Inicio, sprint actual e historial

El inicio muestra el sprint actual con objetivo, fechas, capacidad, esfuerzo, tarjetas resueltas, pendientes programadas para hoy y pendientes fuera de fecha. Las tareas incluyen descripción, responsable, tipo, prioridad, fechas, estimación y enlace de Jira cuando exista.

La selección automática toma el último sprint **creado** (ID mayor), aunque se haya capturado con fechas pasadas o futuras. Solo lo muestra si hoy está entre su inicio y fin, inclusive; en caso contrario muestra que no hay sprint actual. El selector del inicio permite fijar cualquier sprint manualmente para todo el sistema y volver a automático. Capturar un sprint histórico puede cambiar el candidato automático; fija el sprint que estés trabajando si deseas conservarlo como actual.

La fecha del servidor se calcula en `America/Cancun`, configurable con `SPRINT_TIMEZONE` en `backend/.env`. En otros entornos aplica `php artisan migrate` y limpia la caché de configuración si cambias esta variable.

El historial incluye los sprints con fin anterior a hoy. Para registrar un sprint pasado usa **Historial > Agregar sprint pasado**, captura sus fechas reales y selecciona **Resuelta en este sprint** en las tarjetas correspondientes. No se requiere que las fechas sean futuras para crear un sprint.

La marca de resolución se guarda en `sprint_cards.is_completed`, por tarjeta y sprint. Se puede corregir desde Inicio, Historial o al editar el plan, sin cambiar el estado global de la tarjeta ni su marca en otros sprints. Las relaciones existentes comienzan con `false`: no se atribuyen resoluciones históricas por deducción. Descripción, responsable y estado global se consultan de la tarjeta actual; no constituyen una copia histórica. El esfuerzo resuelto mostrado es estimado, no tiempo real registrado. El porcentaje refleja cantidad de tarjetas resueltas, no horas consumidas. No se realizan conversiones automáticas entre estados como Liberado y esta marca explícita de resolución.

Los sprints no pueden compartir días de calendario, incluidos fines de semana y días no laborables. Inicio y fin son inclusivos: si un sprint termina el 20, otro puede empezar el 21. La restricción aplica al crear o editar, a periodos pasados y futuros, y excluye al propio sprint al editarlo. Si hay conflicto la API devuelve 422 en start_date y end_date, indicando el nombre y periodo del sprint existente. Las escrituras de planes se serializan mediante la fila única de sprint_settings para evitar cruces entre solicitudes simultáneas. La restricción protege las escrituras de la API; las modificaciones SQL directas no pasan por esta validación.

## Equipo: usuarios, roles y responsabilidades

El módulo **Equipo** muestra los perfiles y permite buscar por nombre o responsabilidad y filtrar por rol, además de registrar ausencias. `GET /api/users` devuelve id, nombre, roles, responsabilidades y ausencias. Los usuarios también están disponibles en el catálogo de responsables de tarjetas.

El modelo admite varios roles por usuario mediante `roles` y `role_user`. Las responsabilidades se almacenan individualmente en users.responsibilities y no se deducen del rol:

- Luis Alexis Canepa: Líder de proyecto y Desarrollador. Diseño, documentación, planeación, desarrollo, liberación y presentación de avances a dirección.
- Fernando Kumul: Desarrollador. Diseño, documentación y desarrollo.
- Ana Morales: Tester. Testing.
- Cristopher Itza: Tester. Testing.

Para instalar en otro entorno: `php artisan migrate` y `php artisan db:seed --class=TeamMemberSeeder`. El seeder usa una clave estable por persona, reutiliza un usuario con el mismo nombre si todavía no tiene clave y conserva correo, contraseña e ID existentes. Puede repetirse sin duplicar estos cuatro perfiles; vuelve a aplicar sus roles y responsabilidades iniciales. No elimina otros usuarios. Las instalaciones nuevas incluyen este seeder desde DatabaseSeeder.

Los nuevos perfiles tienen email y password nulos, ya que todavía no se proporcionaron correos ni credenciales. Esta entrega es un directorio del equipo, no un sistema de autenticación o autorización. Los roles describen funciones de trabajo y todavía no conceden permisos. No hay edición de perfiles ni inicio de sesión en esta versión. La asignación de testers a Cloud/Exe y las aprobaciones por etapa se definirán posteriormente. La reversión de la migración elimina roles y responsabilidades pero conserva email/password como columnas opcionales para no invalidar los perfiles sin credenciales.

## Días libres, vacaciones y disponibilidad

En **Equipo**, cada persona tiene un apartado **Días libres y vacaciones** para registrar, editar, cancelar y reactivar periodos. Son días completos e incluyen ambas fechas. Cancelar conserva el registro y permite reactivarlo. Dos periodos superpuestos de una misma persona descuentan cada día una sola vez.

Los sprints ahora requieren seleccionar **participantes reales**; members_count se deriva de esa selección. La capacidad base cuenta únicamente los días laborables de participantes con rol Desarrollador y la jornada diaria del sprint. La capacidad neta descuenta únicamente las ausencias de esos desarrolladores que coinciden con días laborables dentro del periodo. Personas ajenas al sprint y ausencias fuera del periodo no reducen la capacidad.

Al cambiar una ausencia, el servidor recalcula todos los sprints relacionados en cada consulta: listado, detalle, inicio e historial. No hay copia de la capacidad que quede desactualizada. Si tienes una pantalla abierta, pulsa Actualizar o vuelve a entrar al módulo para consultar los cambios. La vista de planeación también calcula una previsión con las ausencias cargadas desde Equipo.

El plan muestra, por participante, horas disponibles, esfuerzo asignado y días de ausencia. Por tarjeta, avisa si el responsable está ausente en sus fechas, no forma parte del sprint o no tiene horas suficientes. No cambia fechas, responsables ni estimaciones automáticamente y permite guardar planes con avisos para revisarlos. Los avisos no son una distribución automática del trabajo entre días; varias tarjetas pueden requerir ajustar su orden aun si cada una cabe por separado.

**Sprints antiguos:** como solo guardaban un número de integrantes, no es posible identificar sus personas sin intervención. Muestran capacidad de desarrollo cero y un aviso `requires_participants` hasta configurar sus participantes. Abre Editar plan, selecciona sus participantes y guarda para activar el descuento de ausencias; la actualización requiere esta selección. No se asigna por defecto todo el equipo ni se adivinan participantes a partir de una sola tarjeta.

Instalación en otros entornos: `php artisan migrate`. La migración agrega user_time_offs y sprint_user sin modificar fechas ni seleccionar usuarios para planes previos. No necesitas cambiar frontend/.env.

### Capacidad efectiva de desarrollo

Solo el rol con slug `developer` aporta horas a la capacidad global. Luis y Fernando cuentan; Ana y Cristopher no. Una persona con varios roles que incluyan Desarrollador cuenta una sola vez. Los demás participantes conservan sus tareas, ausencias y disponibilidad individual para seguimiento, pero ni sus horas ni sus ausencias afectan la capacidad de desarrollo.

La carga contra esa capacidad incluye el esfuerzo de tarjetas cuyo responsable tiene el rol Desarrollador. Las tarjetas asignadas a otros roles y las que no tienen responsable o roles definidos se muestran aparte. Se conserva el esfuerzo total y el avance general de todas las tarjetas. Los avisos de disponibilidad individual siguen aplicando a testers y otros roles; las tarjetas sin clasificar generan un aviso para completar su asignación.

La clasificación usa los roles y el responsable actuales; no divide automáticamente una tarjeta en fases de desarrollo y testing. La capacidad restante se calcula como capacidad neta de desarrollo menos esfuerzo asignado a desarrolladores. No hace falta una migración nueva para este ajuste.

## Seguimiento por etapas de las tarjetas

El sprint actual muestra contadores por Documentación, Dirección, Desarrollo, Testing y Testing aprobado, además de un aviso y filtro de correcciones. La asociación al sprint sustituye la selección de alcance antes llamada Liberable; no significa que testing haya aprobado la tarjeta ni que esté publicada en producción.

Flujo: Por documentar > En documentación > Por enviar a dirección > En aprobación por dirección > Disponible para desarrollo > En desarrollo > Pendiente de testing > En testing > Aprobada por testing. Desde la revisión de dirección puede regresar a Corregir documentación y desde testing a Corregir desarrollo. Ambas devoluciones requieren observaciones; al reenviar se retira el aviso activo y se conserva la observación en card_workflow_events. Cancelar requiere motivo y conserva la tarjeta y el historial.

Cada tarjeta permite configurar responsables separados para documentación/diseño, seguimiento con dirección, desarrollo y testing. Para iniciar documentación, desarrollo o testing debe existir un responsable para esa fase. El desarrollador debe tener rol developer y el tester rol tester. La capacidad sigue siendo exclusivamente de desarrollo y conserva developer_id como propietario de ese esfuerzo aunque assigned_to cambie a un tester. La disponibilidad de la fase actual toma en cuenta a quien la atiende.

La antigüedad usa requested_at (fecha original de creación) o, si no se proporcionó, created_at. No representa días en una etapa. requested_at no acepta fechas futuras. Las tarjetas existentes inician como Por documentar: no se deducen ni se inventan aprobaciones de etapas a partir de sus estados anteriores. El campo workflow es la fuente del seguimiento detallado; status_id se actualiza al estado general correspondiente cuando ese catálogo exista.

Desde una tarjeta en Inicio o Tarjetas, usa Gestionar etapa y responsables. El historial muestra fechas y observaciones. Como todavía no hay inicio de sesión, los registros no acreditan la identidad de quien registró la aprobación; son seguimiento operativo, no autorización de acceso.

La migración 2026_10_22_000001_add_card_workflow agrega campos de etapas, responsables, fecha original e historial. No crea el módulo de liberaciones a producción.

### Tarjetas de demostración

WorkflowDemoSeeder crea 14 tarjetas ficticias identificadas DEMO-FLOW-001 a DEMO-FLOW-014, con títulos diferentes de los del Excel. Incluyen todas las colas del flujo y dos avisos de correcciones. No tienen enlaces ni identificadores de Jira reales. Cloud y Exe/General usan los proyectos ya existentes; General no se crea como un tercer producto.

Ejecutar: php artisan db:seed --class=WorkflowDemoSeeder (requiere los cuatro perfiles de TeamMemberSeeder). El seeder usa el sprint fijado manualmente, o el que incluya hoy; si no hay ninguno crea un periodo de demostración sin cruzar los existentes. Agrega los cuatro participantes de ejemplo, conserva participantes/tarjetas existentes y fija ese sprint para que el resultado sea visible al inicio. Al repetirlo no duplica tarjetas ni sobrescribe cambios hechos en ellas. No se ejecuta desde DatabaseSeeder para no cargar ejemplos involuntariamente en otras instalaciones.
