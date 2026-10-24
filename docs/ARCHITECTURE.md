# Arquitectura

## Diseño

Aplicación monolítica PHP nativa con un único punto de entrada (`public/index.php`), plantillas PHP y acceso PDO compatible con MySQL/MariaDB o SQLite. Las rutas se seleccionan con el parámetro `page`; las acciones mutables se reciben por `POST` con `action`.

`public/index.php` orquesta petición, autenticación, autorización y vistas. `src/App.php` concentra acceso a datos, validación y reglas de reserva. `src/Security.php` concentra sesión, CSRF y escape de salida. Esta separación mantiene el MVP pequeño sin ocultar la lógica importante.

## Flujo

1. La petición inicia una sesión segura y abre el motor configurado en `config.local.php`; sin configuración local usa SQLite.
2. Una acción POST valida CSRF y permiso antes de modificar datos.
3. La capa `App` valida datos y usa sentencias PDO preparadas.
4. Se muestra una plantilla con `htmlspecialchars` para todo contenido dinámico.

## Datos

- `users`: docentes y administradores.
- `rooms`: salas activables/desactivables.
- `supplies`: catálogo/stock y estado operativo.
- `reservations`: reserva de exactamente una sala o insumo.
- `loans`: préstamo con devolución opcional.
- `incidents`: fallo sobre una sala o insumo.

Las relaciones se validan con claves foráneas. Para reservas de salas, el conflicto usa `inicio_existente < fin_solicitado AND fin_existente > inicio_solicitado`. Para reservar insumos se suma la cantidad superpuesta y los préstamos abiertos. Para prestar insumos se suman los préstamos abiertos, la cantidad solicitada y el pico de reservas activas actuales o futuras; se usa el pico por horario para no acumular reservas consecutivas. Esa misma cantidad comprometida impide reducir el stock por debajo del límite, cambiar el estado operativo o desactivar el insumo. Las reservas canceladas o vencidas y los préstamos devueltos dejan de bloquear. La fecha de reserva se compara con el reloj de la aplicación; este reloj puede fijarse en pruebas para controlar fecha y zona horaria. Las reservas históricas no se reprograman. La consulta GET de disponibilidad y la creación de reservas comparten la validación de recurso, fecha, horario, cantidad y la misma función de disponibilidad. La comprobación y la escritura se ejecutan dentro de una transacción; SQLite usa `BEGIN IMMEDIATE` y MySQL bloquea con `SELECT ... FOR UPDATE` la fila del recurso, evitando que solicitudes concurrentes rompan el inventario. El propietario de una reserva se resuelve en `App`: para un docente se usa siempre el usuario de sesión; para un administrador se valida que el destinatario sea un docente activo, y una edición conserva el propietario si no se solicita un cambio explícito.

El ciclo de incidencias usa el mismo mecanismo transaccional y bloqueo por recurso. Una incidencia alta, o una baja/media puesta `in_progress` por ADMIN, es bloqueante. Las salas bloqueadas se marcan inactivas y los insumos bloqueados pasan a `repair`, por lo que la lógica existente de disponibilidad y reservas los rechaza. Al retirar el último bloqueo se restablece el estado operativo; si permanece otra incidencia bloqueante, el recurso continúa indisponible. Las incidencias no bloqueantes nunca rehabilitan un recurso que ya estaba fuera de servicio por otra causa. El listado se obtiene mediante `App::incidentsFor`: para USER filtra siempre por el identificador de sesión y selecciona solo campos públicos; para ADMIN devuelve el conjunto global con notas administrativas.

Los reportes se obtienen mediante `App::reportSummary`. El método valida las dos fechas con el mismo validador estricto utilizado por reservas y comprueba el orden antes de ejecutar consultas. Los tres conteos usan `BETWEEN`, por lo que los límites son inclusivos y consistentes.

## Rutas principales

`login`, `dashboard`, `availability`, `rooms`, `supplies`, `users`, `reservations`, `loans`, `incidents`, `reports` y `password`. `availability` es una consulta GET autenticada; las páginas de administración requieren rol `admin` en el servidor.
