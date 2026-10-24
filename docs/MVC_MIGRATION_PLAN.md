# Plan de migración a MVC puro

## Objetivo

Separar progresivamente el proyecto en Modelos, Vistas y Controladores sin cambiar las URLs existentes, los formularios, los mensajes visibles, las reglas de negocio, la seguridad ni la compatibilidad SQLite/MySQL.

La migración será incremental. `App` se conservará temporalmente como fachada para evitar un cambio masivo en las pruebas y en los scripts. Cada extracción debe poder revertirse sin perder funcionalidad.

## Diagnóstico actual

- `public/index.php` es entrada HTTP, router por `page`, autorización de páginas, controlador de lectura, composición de datos y renderizador HTML.
- `src/Http/ActionDispatcher.php` concentra el dispatch de POST, parte de la autorización, transformación de entrada y redirecciones.
- `src/App.php` mezcla fachada de aplicación, consultas PDO, validación de dominio, transacciones y reglas de reservas, inventario, préstamos, incidencias y reportes.
- `src/Repositories/` contiene sólo lecturas simples de usuarios, recursos y reservas.
- `src/Security.php` contiene sesión, CSRF, autorización, escape, flash y manejo de errores.
- No hay una capa de vistas separada: las plantillas están embebidas en `public/index.php`.
- Las pruebas de dominio llaman directamente a `App`, por lo que ese contrato público es una restricción de compatibilidad durante la transición.

## Arquitectura objetivo

```text
public/index.php
  -> Bootstrap / Router
  -> Controllers
       -> Services / casos de uso
            -> Repositories / persistencia PDO
            -> Domain / validadores, valores y reglas puras
       -> ViewRenderer
            -> Views/*.php
```

Reglas de dependencia:

1. `public/` no contiene SQL ni reglas de negocio.
2. Los controladores traducen HTTP a comandos y preparan modelos para la vista; no deciden disponibilidad ni transacciones.
3. Los servicios ejecutan casos de uso y coordinan transacciones; no generan HTML.
4. Los repositorios encapsulan consultas PDO y no conocen `$_GET`, `$_POST`, sesiones ni redirecciones.
5. Las vistas sólo reciben datos y helpers de presentación; no consultan la base.
6. `Security` se dividirá en componentes especializados sin relajar CSRF, autorización, escape ni regeneración de sesión.

## Contratos que no pueden cambiar

- Rutas `?page=login|dashboard|availability|rooms|supplies|users|reservations|loans|incidents|reports|password`.
- Acciones POST actuales: login, logout, password, CRUD, reservation, cancel_reservation, loan, return_loan, incident y update_incident.
- Mensajes de validación y errores controlados, salvo mejora explícita acompañada por pruebas.
- Reglas de conflicto horario: `inicio_existente < fin_solicitado` y `fin_existente > inicio_solicitado`.
- Cálculo de stock comprometido: préstamos abiertos más pico simultáneo de reservas futuras.
- Bloqueo transaccional: `BEGIN IMMEDIATE` en SQLite y `SELECT ... FOR UPDATE` en MySQL cuando corresponde.
- Propiedad y visibilidad de reservas e incidencias, roles admin/user, CSRF y escape HTML.
- Fecha controlable por reloj inyectado para las pruebas y fechas históricas no reprogramables.

## Plan atómico

Cada tarea debe terminar con `php -l` sobre los archivos tocados y, cuando el entorno tenga `pdo_sqlite` y `sqlite3`, `php tests/run.php`.

### Fase 0: línea base y contratos

- [x] **M0.1** Registrar el flujo actual y los límites de cada módulo en este documento. Dependencia: ninguna. Aceptación: diagnóstico y mapa de dependencias revisados.
- [x] **M0.2** Ejecutar lint de todos los PHP y registrar disponibilidad de la suite. Dependencia: M0.1. Aceptación: lint de 43 archivos correcto; suite bloqueada por extensión `sqlite3` ausente.
- [x] **M0.3** Activar `pdo_sqlite` y `sqlite3` en el entorno de desarrollo. Dependencia: M0.2. Aceptación: `php tests/run.php` termina con todas las pruebas aprobadas.
- [x] **M0.4** Añadir una prueba de contrato para cada ruta y acción POST existente. Dependencia: M0.3. Aceptación: el router nuevo puede compararse con el comportamiento actual.
- [x] **M0.5** Añadir pruebas de regresión para respuestas de error, redirecciones, flash y códigos HTTP. Dependencia: M0.3. Aceptación: quedan fijados los efectos HTTP que hoy están implícitos en `index.php` y `ActionDispatcher`.

### Fase 1: cimientos y composición

- [x] **M1.1** Crear `src/Http/Request.php` para encapsular método, query, post y sesión de la petición. Dependencia: M0.4. Aceptación: ningún controlador lee superglobales directamente.
- [x] **M1.2** Crear `src/Http/Response.php` para HTML, redirección y código HTTP. Dependencia: M1.1. Aceptación: los controladores dejan de llamar directamente a `header()` y `exit` cuando sean extraídos; la implementación queda disponible para esa migración.
- [x] **M1.3** Crear `src/Http/Router.php` con el mapa de páginas y el control de páginas protegidas. Dependencia: M1.2. Aceptación: `?page=` conserva rutas, autenticación y respuesta 403.
- [x] **M1.4** Crear `src/Views/ViewRenderer.php` y un helper de contexto seguro. Dependencia: M1.2. Aceptación: el renderer carga una vista por nombre sin ejecutar SQL.
- [x] **M1.5** Extender `Bootstrap` para construir PDO, repositorios, componentes HTTP y renderer en un único composition root; los servicios y controladores se conectarán al crearse en M3/M4. Dependencia: M1.3 y M1.4. Aceptación: sólo Bootstrap conoce el cableado concreto.
- [x] **M1.6** Mantener `App` como fachada compatible para el contrato usado por las pruebas, dejando la delegación interna para las extracciones de M2-M4. Dependencia: M1.5. Aceptación: no se cambia la API pública de `App`.

### Fase 2: modelos de lectura y repositorios

- [x] **M2.1** Extraer `DashboardRepository` para las reservas activas del día. Dependencia: M1.6. Aceptación: dashboard no contiene SQL y conserva orden y campos actuales.
- [x] **M2.2** Extraer `LoanRepository` para listado y devolución. Dependencia: M2.1. Aceptación: ninguna consulta de préstamos queda en vistas o controladores.
- [x] **M2.3** Extraer `IncidentRepository` para listado público y administrativo. Dependencia: M2.2. Aceptación: las notas administrativas nunca aparecen en la consulta de usuario.
- [x] **M2.4** Extraer `ReportRepository` para los tres conteos inclusivos. Dependencia: M2.3. Aceptación: filtros y límites de fecha conservan el comportamiento probado.
- [x] **M2.5** Completar repositorios CRUD de usuarios, salas e insumos. Dependencia: M2.4. Aceptación: toda consulta de lectura/escritura queda detrás de un repositorio nombrado.
- [x] **M2.6** Sustituir consultas directas restantes de `public/index.php` y `ActionDispatcher` por métodos de repositorio. Dependencia: M2.1-M2.5. Aceptación: búsqueda de SQL en esas capas devuelve cero coincidencias.

### Fase 3: servicios de dominio y casos de uso

- [x] **M3.1** Extraer `AuthenticationService` para login, cambio de contraseña y revalidación. Dependencia: M2.5. Aceptación: hashes, usuario activo y mensajes actuales se conservan.
- [x] **M3.2** Extraer `ResourceService` para CRUD, desactivación y estados operativos. Dependencia: M2.5. Aceptación: invariantes de stock e incidencias siguen transaccionales.
- [x] **M3.3** Extraer `ReservationService` para crear, editar, cancelar y consultar disponibilidad. Dependencia: M2.5. Aceptación: propiedad, fecha, solapamiento, cantidad, reloj y bloqueo se mantienen.
- [x] **M3.4** Extraer `LoanService` para prestar y devolver. Dependencia: M3.2 y M3.3. Aceptación: el pico de reservas futuras continúa contando como stock comprometido.
- [x] **M3.5** Extraer `IncidentService` para reportar, cambiar estado y recalcular disponibilidad. Dependencia: M3.2. Aceptación: múltiples bloqueos y rehabilitación parcial conservan su semántica.
- [x] **M3.6** Extraer `ReportService` para validar fechas y coordinar el repositorio. Dependencia: M2.4. Aceptación: validación estricta y orden de fechas permanecen fuera de la vista.
- [x] **M3.7** Inyectar reloj y coordinador transaccional en los servicios que lo necesitan. Dependencia: M3.3-M3.5. Aceptación: las pruebas no dependen del reloj del sistema ni de transacciones implícitas.
- [x] **M3.8** Hacer que `App` delegue todos sus métodos a servicios y repositorios, sin conservar reglas duplicadas. Dependencia: M3.1-M3.7. Aceptación: la suite existente sigue llamando a `App` sin cambios.

### Fase 4: controladores MVC

- [ ] **M4.1** Crear `AuthController` para login, logout y contraseña. Dependencia: M1.5 y M3.1. Aceptación: CSRF, regeneración de sesión, flash y redirecciones coinciden.
- [ ] **M4.2** Crear `DashboardController` y `AvailabilityController`. Dependencia: M2.1, M3.3. Aceptación: GET sólo prepara modelos; ninguna regla de disponibilidad se duplica.
- [ ] **M4.3** Crear `ResourceController` para salas e insumos. Dependencia: M2.5, M3.2. Aceptación: autorización admin se aplica en servidor y formularios siguen funcionando.
- [ ] **M4.4** Crear `UserController` y `ReservationController`. Dependencia: M2.5, M3.1, M3.3. Aceptación: asignación administrativa y propiedad del docente se conservan.
- [ ] **M4.5** Crear `LoanController`, `IncidentController` y `ReportController`. Dependencia: M2.2-M2.4, M3.4-M3.6. Aceptación: listados, filtros y mutaciones no contienen SQL ni HTML.
- [ ] **M4.6** Reducir `ActionDispatcher` a un adaptador temporal o reemplazarlo por dispatch de controladores. Dependencia: M4.1-M4.5. Aceptación: todas las acciones POST pasan por un controlador identificable.

### Fase 5: vistas y salida HTTP

- [ ] **M5.1** Extraer layout, navegación, flash y formularios compartidos a `src/Views/layouts` y `src/Views/partials`. Dependencia: M1.4 y M4.1. Aceptación: escape, CSRF y navegación no cambian.
- [ ] **M5.2** Extraer vistas de autenticación, dashboard y disponibilidad. Dependencia: M4.2. Aceptación: las vistas sólo consumen el modelo recibido.
- [ ] **M5.3** Extraer vistas de salas, insumos y usuarios. Dependencia: M4.3. Aceptación: edición, estados y enlaces conservan sus nombres y parámetros.
- [ ] **M5.4** Extraer vistas de reservas, préstamos, incidencias y reportes. Dependencia: M4.4 y M4.5. Aceptación: no quedan bloques de HTML de esas páginas en `public/index.php`.
- [ ] **M5.5** Convertir `public/index.php` en front controller mínimo. Dependencia: M5.1-M5.4. Aceptación: sólo carga Bootstrap, crea la respuesta y delega al router.

### Fase 6: seguridad, pruebas y cierre

- [ ] **M6.1** Separar sesión, autorización, CSRF, flash, escape y errores en componentes bajo `src/Security/` o equivalentes. Dependencia: M5.5. Aceptación: no se degradan las pruebas de seguridad existentes.
- [ ] **M6.2** Mover `Validator` y `Input` a contratos de dominio/HTTP sin duplicar mensajes. Dependencia: M3.8 y M6.1. Aceptación: entradas inválidas producen los mismos errores controlados.
- [ ] **M6.3** Añadir pruebas unitarias de servicios y pruebas de integración de controladores. Dependencia: M3.8 y M4.6. Aceptación: reglas críticas tienen cobertura directa además de la suite heredada.
- [ ] **M6.4** Ejecutar la suite completa en SQLite y una verificación funcional en MySQL/MariaDB. Dependencia: M6.3. Aceptación: todas las pruebas pasan en ambos motores.
- [ ] **M6.5** Actualizar `docs/ARCHITECTURE.md`, README y scripts si cambian rutas de carga o responsabilidades. Dependencia: M5.5. Aceptación: la documentación describe la arquitectura real.
- [ ] **M6.6** Eliminar `App`, dispatcher y helpers antiguos sólo cuando no haya consumidores. Dependencia: M6.1-M6.5. Aceptación: búsqueda de referencias y suite completa no muestran código muerto funcional.
- [ ] **M6.7** Registrar una etiqueta de migración y una revisión manual de seguridad. Dependencia: M6.6. Aceptación: no quedan SQL en HTTP/vistas, ni superglobales en servicios/repositorios, ni controles de autorización sólo visuales.

## Puertas de verificación

Después de cada tarea:

1. `php -l` para cada PHP modificado.
2. Prueba específica del módulo afectado.
3. `php tests/run.php` al cerrar cada fase, cuando estén disponibles `pdo_sqlite` y `sqlite3`.
4. Revisión de `git diff` para confirmar que no hubo cambios de esquema, URLs o mensajes no previstos.

Antes de retirar la fachada `App`:

- Suite funcional completa aprobada.
- Pruebas de concurrencia de reservas, préstamos e invariantes de insumos aprobadas.
- Pruebas de CSRF, autorización, sesión y visibilidad de incidencias aprobadas.
- Verificación manual de login, CRUD, reserva, disponibilidad, préstamo, incidencia y reporte en SQLite y MySQL.

## Bitácora

| Fecha | Estado | Registro |
|---|---|---|
| 2026-09-05 | Completado | Se estudió el flujo desde `public/index.php`, Bootstrap, dispatcher, `App`, repositorios, seguridad y pruebas. |
| 2026-09-05 | Completado | Se identificó la mezcla principal: HTTP/renderizado/SQL en `public/index.php` y reglas/persistencia en `App`. |
| 2026-09-05 | Completado | `php -l` pasó para 43 archivos PHP usando `C:\xampp\php\php.exe`. |
| 2026-09-05 | Completado | Se habilitó `sqlite3` en `C:\xampp\php\php.ini`; `pdo_sqlite` ya estaba activo. |
| 2026-09-05 | Completado | Se corrigió la transacción SQLite para que PDO observe sus límites y se adquiere temprano el bloqueo del recurso en operaciones concurrentes. |
| 2026-09-05 | Completado | Los workers de concurrencia se ejecutan con `-n` para evitar cargar dos veces las extensiones cuando el entorno las tiene activas globalmente. |
| 2026-09-05 | Completado | `php tests/run.php`: 19/19 pruebas aprobadas. M0.3 queda cerrada. |
| 2026-09-05 | Completado | Se añadió `tests/http_contract_test.php` con contratos para 11 rutas, 15 acciones POST, formularios y páginas administrativas. |
| 2026-09-05 | Completado | `php tests/run.php`: 20/20 pruebas aprobadas. M0.4 queda cerrada. |
| 2026-09-05 | Completado | Se añadió `tests/http_response_contract_test.php` para 403/500, redirecciones, flash y mensajes públicos de error. |
| 2026-09-05 | Completado | `php tests/run.php`: 21/21 pruebas aprobadas. M0.5 queda cerrada. |
| 2026-09-05 | Completado | Se creó `src/Http/Request.php` con constructor inyectable, `fromGlobals()` y acceso separado a método, query, POST y sesión. |
| 2026-09-05 | Completado | Bootstrap carga `Request` y `tests/http_request_test.php` cubre construcción directa y desde globals. |
| 2026-09-05 | Completado | `php tests/run.php`: 22/22 pruebas aprobadas. M1.1 queda cerrada. |
| 2026-09-05 | Completado | Se creó `src/Http/Response.php` con HTML, redirecciones, headers, códigos HTTP y `send()`, además de `tests/http_response_test.php` y carga desde Bootstrap. |
| 2026-09-05 | Completado | `php tests/run.php`: 23/23 pruebas aprobadas. M1.2 queda cerrada. |
| 2026-09-05 | Completado | Se creó `src/Http/Router.php` con 11 páginas, 5 páginas administrativas, reglas de login y compatibilidad con páginas desconocidas. |
| 2026-09-05 | Completado | Bootstrap carga `Router` y `tests/http_router_test.php` cubre rutas por defecto, query, protección y compatibilidad. |
| 2026-09-05 | Completado | `php tests/run.php`: 24/24 pruebas aprobadas. M1.3 queda cerrada. |
| 2026-09-05 | Completado | Se crearon `src/Views/ViewRenderer.php` y `src/Views/ViewContext.php` con carga restringida, datos explícitos y escape HTML. |
| 2026-09-05 | Completado | Bootstrap carga el renderer y `tests/view_renderer_test.php` cubre renderizado, escape y rechazo de vistas inválidas. |
| 2026-09-05 | Completado | `php tests/run.php`: 25/25 pruebas aprobadas. M1.4 queda cerrada. |
| 2026-09-05 | Completado | Se creó `Sgsii\ApplicationContext` y Bootstrap compone aplicación, Request, Router, ViewRenderer y repositorios desde un único punto. |
| 2026-09-05 | Completado | `tests/bootstrap_context_test.php` verifica el cableado y `php tests/run.php`: 26/26 pruebas aprobadas. M1.5 queda cerrada. |
| 2026-09-05 | Completado | Se añadió `tests/app_facade_contract_test.php` para proteger los métodos públicos y el retorno PDO de `App::connect()`. |
| 2026-09-05 | Completado | `php tests/run.php`: 27/27 pruebas aprobadas. M1.6 queda cerrada y la Fase 1 queda completada. |
| 2026-09-05 | Completado | Se creó `src/Repositories/DashboardRepository.php` y se eliminó la consulta directa equivalente del dashboard. |
| 2026-09-05 | Completado | Bootstrap y `ApplicationContext` exponen el repositorio; `tests/dashboard_repository_test.php` cubre filtros, nombres, tipos y orden. |
| 2026-09-05 | Completado | `php tests/run.php`: 28/28 pruebas aprobadas. M2.1 queda cerrada. |
| 2026-09-05 | Completado | Se creó `src/Repositories/LoanRepository.php`; el listado salió de `public/index.php` y la devolución de `ActionDispatcher`. |
| 2026-09-05 | Completado | Bootstrap y `ApplicationContext` exponen `LoanRepository`; `tests/loan_repository_test.php` cubre listado, joins, orden y devolución. |
| 2026-09-05 | Completado | `php tests/run.php`: 29/29 pruebas aprobadas. M2.2 queda cerrada; la creación de préstamos permanece en `App` para M3.4. |
| 2026-09-05 | Completado | Se creó `src/Repositories/IncidentRepository.php` y el listado de incidencias salió de `public/index.php`. |
| 2026-09-05 | Completado | La proyección docente omite `admin_notes` y `reporter_id`; la administrativa conserva notas y reportante. `tests/incident_repository_test.php` lo verifica. |
| 2026-09-05 | Completado | Bootstrap y `ApplicationContext` exponen `IncidentRepository`; `php tests/run.php`: 30/30 pruebas aprobadas. M2.3 queda cerrada. |
| 2026-09-05 | Completado | Se creó `src/Repositories/ReportRepository.php`; `App::reportSummary()` conserva validación y delega los tres conteos. |
| 2026-09-05 | Completado | Bootstrap y `ApplicationContext` exponen `ReportRepository`; `tests/report_repository_test.php` cubre límites inclusivos y reservas canceladas. |
| 2026-09-05 | Completado | `php tests/run.php`: 31/31 pruebas aprobadas. M2.4 queda cerrada. |
| 2026-09-05 | Completado | UserRepository y ResourceRepository incorporan operaciones CRUD nombradas; `App` delega las escrituras sin mover validación ni invariantes. |
| 2026-09-05 | Completado | `tests/crud_repository_test.php` cubre crear, actualizar y desactivar usuarios, salas e insumos. |
| 2026-09-05 | Completado | `php tests/run.php`: 32/32 pruebas aprobadas. M2.5 queda cerrada. |
| 2026-09-05 | Completado | Se añadió `ReservationRepository::findOwnerAndStatus()` y se eliminó el último SQL directo de `ActionDispatcher`. |
| 2026-09-05 | Completado | `tests/http_sql_boundary_test.php` protege que `public/index.php` y `ActionDispatcher` no contengan SQL. |
| 2026-09-05 | Completado | `php tests/run.php`: 33/33 pruebas aprobadas. M2.6 queda cerrada y la Fase 2 queda completada. |
| 2026-09-05 | Completado | Se creó `src/Services/AuthenticationService.php`; `App` delega login y cambio de contraseña, y `Security` delega revalidación. |
| 2026-09-05 | Completado | `tests/authentication_service_test.php` cubre hashes, usuario activo, sesión válida y errores de contraseña. |
| 2026-09-05 | Completado | `php tests/run.php`: 34/34 pruebas aprobadas. M3.1 queda cerrada. |
| 2026-09-05 | Completado | Se creó `src/Services/ResourceService.php`; `App` delega CRUD, desactivación y reglas de stock/estado. |
| 2026-09-05 | Completado | `tests/resource_service_test.php` cubre CRUD básico; las pruebas existentes conservan bloqueos, invariantes y concurrencia. |
| 2026-09-05 | Completado | `php tests/run.php`: 35/35 pruebas aprobadas. M3.2 queda cerrada. |
| 2026-09-05 | Completado | Se creó `src/Services/ReservationService.php`; `App` delega creación, edición, cancelación y disponibilidad. |
| 2026-09-05 | Completado | `tests/reservation_service_test.php` y las pruebas existentes cubren reloj, solapamiento, concurrencia, asignación y fechas. |
| 2026-09-05 | Completado | `php tests/run.php`: 36/36 pruebas aprobadas. M3.3 queda cerrada; el cuerpo histórico de App queda para limpieza M3.8. |
| 2026-09-05 | Completado | Se creó `src/Services/LoanService.php`; `App` delega altas y devoluciones de préstamos. |
| 2026-09-05 | Completado | `tests/loan_service_test.php` y las pruebas existentes cubren stock comprometido, reservas futuras, devoluciones y concurrencia. |
| 2026-09-05 | Completado | `php tests/run.php`: 37/37 pruebas aprobadas. M3.4 queda cerrada. |
| 2026-09-05 | Completado | Se creó `src/Services/IncidentService.php`; `App` delega reportes, cambios de estado y disponibilidad. |
| 2026-09-05 | Completado | `tests/incident_service_test.php` y las pruebas existentes cubren bloqueos, múltiples incidencias, rehabilitación y transacciones. |
| 2026-09-05 | Completado | `php tests/run.php`: 38/38 pruebas aprobadas. M3.5 queda cerrada. |
| 2026-09-05 | Completado | Se creó `src/Services/ReportService.php`; `App` delega validación de fechas y coordinación del reporte. |
| 2026-09-05 | Completado | `tests/report_service_test.php` y `report_filters_test.php` cubren fechas estrictas, rangos invertidos y límites inclusivos. |
| 2026-09-05 | Completado | `php tests/run.php`: 39/39 pruebas aprobadas. M3.6 queda cerrada. |
| 2026-09-05 | Completado | Se creó `src/Services/TransactionCoordinator.php`; Resource, Reservation, Loan e IncidentService delegan begin/commit/rollback. |
| 2026-09-05 | Completado | Bootstrap comparte el coordinador en `ApplicationContext`; los servicios mantienen reloj opcional inyectable para pruebas deterministas. |
| 2026-09-05 | Completado | `tests/transaction_coordinator_test.php` cubre commit/rollback y `cmd.exe /c ... tests/run.php`: 40/40 pruebas aprobadas. M3.7 queda cerrada. |
| 2026-09-05 | Completado | `App.php` fue reducido a fachada: delega autenticación, recursos, reservas, préstamos, incidencias y reportes; se retiraron cuerpos duplicados. |
| 2026-09-05 | Completado | `app_facade_contract_test.php` conserva la API pública y `cmd.exe /c ... tests/run.php`: 40/40 pruebas aprobadas. M3.8 queda cerrada y la Fase 3 queda completada. |
| 2026-09-05 | Próximo | Ejecutar M4.1: crear `AuthController` para login, logout y contraseña. |
