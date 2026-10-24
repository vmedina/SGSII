# SGSII — Explicación técnica para presentación oral

## 1. ¿Qué es la aplicación?

SGSII es una aplicación web monolítica desarrollada en PHP. Su objetivo es gestionar salas e insumos informáticos de una institución educativa.

La aplicación permite:

- autenticar docentes y administradores;
- administrar usuarios, salas e insumos;
- consultar disponibilidad;
- crear y cancelar reservas;
- registrar préstamos y devoluciones;
- reportar y resolver incidencias;
- consultar reportes por rango de fechas.

Es un MVP pensado para ejecutarse localmente con XAMPP, aunque también puede trabajar con SQLite para desarrollo y pruebas.

## 2. Arquitectura general

La arquitectura es monolítica y está organizada por responsabilidades:

```text
Navegador
   │  GET para páginas / POST para acciones
   ▼
public/index.php
   │
   ├── Seguridad: sesión, CSRF y autorización
   ├── Selección y autorización de la página solicitada
   ├── ActionDispatcher: interpreta la acción POST
   ├── App y Services: aplican reglas de negocio
   ├── Repositories: ejecutan consultas PDO
   └── Views: generan HTML
          │
          ▼
     SQLite o MySQL/MariaDB
```

El punto de entrada es `public/index.php`. El directorio `src/` contiene la lógica interna y no debería exponerse directamente desde el servidor web.

### Capas principales

1. **Entrada web**: recibe la petición, inicia la sesión, determina la página y genera la respuesta.
2. **HTTP**: `Request`, `Response`, `Router`, `Input` y `ActionDispatcher` aíslan detalles de HTTP. En el flujo web principal, `public/index.php` obtiene la página desde `page` y aplica directamente las comprobaciones de acceso; `Router` también se utiliza al construir `ApplicationContext` y en las pruebas del contrato HTTP.
3. **Servicios**: contienen las reglas de negocio, por ejemplo disponibilidad, stock y ciclo de incidencias.
4. **Repositorios**: encapsulan las consultas SQL y devuelven datos a los servicios o a las vistas.
5. **Persistencia**: PDO permite utilizar SQLite o MySQL/MariaDB con sentencias preparadas.
6. **Vistas**: plantillas PHP que reciben datos y escapan la salida HTML.

`App` funciona como una fachada para operaciones frecuentes y `Bootstrap` construye las dependencias de cada ejecución.

## 3. Flujo de una petición

### Petición GET

1. El navegador solicita una URL como `?page=availability`.
2. `public/index.php` carga `Bootstrap.php` y crea la aplicación.
3. Se inicia una sesión con cookie HttpOnly y SameSite.
4. Se revalida que el usuario guardado en sesión siga existiendo y activo.
5. Se comprueba si la página requiere autenticación o rol administrador; la página se obtiene del parámetro `page` y, si falta, se elige `login` o `dashboard` según la sesión.
6. Se consultan los datos necesarios mediante repositorios.
7. La vista genera HTML y escapa los datos dinámicos con `htmlspecialchars`.

### Petición POST

1. El formulario incluye un token CSRF.
2. `verify_csrf()` compara el token enviado con el token de sesión.
3. `ActionDispatcher` identifica la acción mediante el campo `action`.
4. Se valida la autorización en el servidor; ocultar un botón no es una medida de seguridad.
5. El servicio correspondiente valida los datos y ejecuta la regla de negocio.
6. Las escrituras críticas se realizan dentro de una transacción.
7. Se guarda un mensaje temporal y se redirige a la página correspondiente.
8. Si ocurre un error controlado, se muestra un mensaje entendible; los errores internos se registran y no se muestran con detalles técnicos.

## 4. Modelo de datos

La base contiene seis tablas principales:

- `users`: docentes y administradores, con rol, estado y hash de contraseña.
- `rooms`: salas, número, ubicación, cantidad de equipos y estado activo.
- `supplies`: insumos, código patrimonial, categoría, stock y estado operativo.
- `reservations`: reserva de una sala o de un insumo, con fecha, intervalo y cantidad.
- `loans`: préstamo de insumos hasta registrar `returned_at`.
- `incidents`: incidencia asociada a una sala o insumo, con urgencia, estado y notas administrativas.

Las claves foráneas protegen las relaciones. Las restricciones de la base también impiden combinaciones inválidas, por ejemplo una reserva que tenga sala e insumo al mismo tiempo.

## 5. Reglas de reservas y disponibilidad

Las reservas utilizan intervalos de horas exactas y el criterio de solapamiento:

```text
inicio_existente < fin_solicitado
AND fin_existente > inicio_solicitado
```

Por eso dos reservas consecutivas, como 09:00–10:00 y 10:00–11:00, son válidas.

### Salas

Una sala solo puede tener una reserva activa en un intervalo determinado. También debe estar activa y no bloqueada por una incidencia.

### Insumos

Para un insumo se suma la cantidad de reservas que se superponen y se agregan los préstamos abiertos. La suma no puede superar el stock total.

La aplicación calcula el pico de reservas por horario, no la suma simple de todas las reservas del día. Así evita contar dos veces reservas consecutivas que nunca coinciden.

Las reservas pasadas no se pueden editar; solo se pueden cancelar. Las reservas canceladas dejan de bloquear disponibilidad.

Cuando un docente crea o edita una reserva, el responsable siempre es el usuario de la sesión. Un administrador puede elegir un docente activo y, al editar, el responsable se conserva salvo que se solicite explícitamente un cambio válido.

## 6. Inventario y préstamos

Antes de registrar un préstamo, `LoanService`:

1. valida el insumo, docente, cantidad y notas;
2. comprueba que el insumo esté activo y operativo;
3. calcula préstamos abiertos más reservas futuras superpuestas;
4. verifica que el nuevo préstamo no supere el stock;
5. guarda el préstamo dentro de una transacción.

Un insumo con unidades comprometidas no puede reducir su stock por debajo de esa cantidad, cambiar arbitrariamente de estado ni desactivarse. La devolución libera las unidades comprometidas.

## 7. Incidencias y disponibilidad

Una incidencia puede ser de urgencia baja, media o alta.

- Una incidencia alta bloquea inmediatamente el recurso.
- Una incidencia baja o media se vuelve bloqueante cuando el administrador la pasa a `in_progress`.
- Una incidencia resuelta deja de bloquear.
- El recurso solo se rehabilita cuando no queda otra incidencia bloqueante.

El bloqueo se refleja en el estado del recurso: las salas pasan a inactivas y los insumos pasan a `repair`. De esta manera, la lógica normal de disponibilidad ya rechaza nuevas reservas.

Estas operaciones se ejecutan con transacciones y bloqueos para evitar que dos administradores modifiquen simultáneamente el mismo recurso de forma incoherente.

## 8. Seguridad

Las medidas principales son:

- contraseñas almacenadas con `password_hash` y verificadas con `password_verify`;
- regeneración del identificador de sesión después del login;
- cookies HttpOnly y SameSite=Lax, con Secure cuando se usa HTTPS;
- token CSRF en cada acción POST que cambia datos;
- autorización en PHP para páginas y acciones administrativas;
- consultas PDO preparadas para evitar inyección SQL;
- validación de tipos, longitudes, rangos, fechas, horas y valores permitidos;
- escape HTML de toda salida dinámica;
- mensajes genéricos para errores internos y registro local de metadatos del error.

El archivo `config.local.php` contiene las credenciales locales y no debe versionarse. El MVP está pensado para un entorno local; un despliegue público requeriría HTTPS, copias de seguridad, límites de intentos y recuperación de contraseña.

## 9. Configuración y ejecución

En XAMPP, el instalador prepara Apache, MySQL, la base `sgsii`, las tablas y el administrador inicial. La aplicación usa MySQL/MariaDB cuando existe `config.local.php`.

Si no existe ese archivo, `Database::configuration()` selecciona SQLite por defecto. Esto facilita el desarrollo local y la ejecución de pruebas aisladas.

El esquema se encuentra en:

- `database/schema.mysql.sql` para MySQL/MariaDB;
- `database/schema.sql` para SQLite.

## 10. Pruebas y calidad

La suite automatizada cubre infraestructura, seguridad, autenticación, HTTP, CRUD, reservas, disponibilidad, inventario, préstamos, incidencias y reportes.

La última ejecución validada durante la elaboración de este documento fue:

```text
Resultado: 40/40 pruebas aprobadas.
```

Además, todos los archivos PHP de producción fueron comprobados con el verificador de sintaxis de PHP sin errores.

## 11. Guion breve para la exposición oral

Una explicación de dos o tres minutos puede seguir este orden:

1. **Propósito**: SGSII gestiona salas, insumos, reservas, préstamos e incidencias.
2. **Arquitectura**: es un monolito PHP con `public/index.php` como entrada, servicios para reglas y repositorios para SQL.
3. **Flujo**: cada petición valida sesión y permisos; los POST validan CSRF y delegan la acción al servicio correspondiente.
4. **Regla clave**: la disponibilidad combina reservas, préstamos abiertos, stock e incidencias.
5. **Concurrencia**: las operaciones críticas usan transacciones y bloqueos para no sobreasignar recursos.
6. **Seguridad**: hashes de contraseña, sesiones seguras, CSRF, autorización en servidor, PDO preparado y escape HTML.
7. **Calidad**: la suite funcional tiene 40 pruebas aprobadas.

## 12. Límites actuales

El MVP no incluye notificaciones, reservas recurrentes, recuperación de contraseña por correo, integración con otros sistemas institucionales, compras, mantenimiento preventivo ni exportación de reportes. Estas funciones podrían añadirse como evoluciones posteriores sin cambiar la separación principal entre entrada web, servicios, repositorios y persistencia.
