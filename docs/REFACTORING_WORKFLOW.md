# Flujo de refactorización de SGSII

## Objetivo

Evolucionar el monolito PHP hacia una arquitectura modular, conservando el comportamiento de reservas, disponibilidad, inventario, préstamos, incidencias, reportes y autenticación.

La aplicación seguirá siendo PHP nativo con renderizado del lado del servidor. No se incorporarán frameworks, Composer ni un frontend separado salvo que una necesidad concreta lo justifique.

## Arquitectura objetivo

```text
public/
└── index.php                    Punto de entrada HTTP

src/
├── Http/                        Entrada, request y response
├── Controllers/                 Orquestación de cada caso HTTP
├── Services/                    Casos de uso y reglas de negocio
├── Repositories/                Consultas y persistencia PDO
├── Domain/                      Entidades, valores y constantes
├── Validation/                  Validación de entrada
├── Security/                    Sesión, CSRF y autorización
├── Support/                     Utilidades pequeñas y compartidas
└── Views/                       Plantillas PHP
```

La transición será gradual. `App` se mantendrá como fachada compatible mientras se extraen sus responsabilidades; así las pruebas actuales y los scripts de instalación no tienen que cambiar todos a la vez.

## Orden de trabajo

### Iteración 1: entrada HTTP

- Extraer parsing y validación básica de identificadores y recursos.
- Mantener los mensajes de error actuales.
- No introducir SQL en los componentes HTTP.
- Verificar lint y pruebas después de cada extracción.

Resultado aplicado: `Sgsii\Http\Input` en `src/Http/Input.php`.

### Iteración 2 aplicada: bootstrap

- Se creó Bootstrap::createApplication en src/Bootstrap.php.
- Se centralizó la carga de Security, Database, Input y App.
- Se conservaron la creación de sesión y la revalidación del usuario.
- public/index.php sigue siendo el punto de entrada y conserva sus rutas.

### Iteración 3 aplicada: dispatcher HTTP

- Se creó Sgsii\Http\ActionDispatcher en src/Http/ActionDispatcher.php.
- public/index.php conserva sólo la validación CSRF, el dispatch y el manejo de errores.
- Las acciones y sus mensajes de redirección mantienen el comportamiento existente.

### Iteración 4 aplicada: validación de dominio

- Se creó Sgsii\Validation\Validator en src/Validation/Validator.php.
- App.php ya no contiene validadores genéricos privados.
- Se mantuvieron los límites, mensajes y tipos de error existentes.

### Iteración 5 aplicada: repositorio de recursos

- Se creó Sgsii\Repositories\ResourceRepository en src/Repositories/ResourceRepository.php.
- Las consultas de salas e insumos fueron retiradas de public/index.php.
- Los nombres de las operaciones ahora explican su intención (indActiveRooms, indAllSupplies).

### Iteración 6 aplicada: repositorio de usuarios

- Se creó Sgsii\Repositories\UserRepository en src/Repositories/UserRepository.php.
- Los listados de usuarios y docentes ya no contienen SQL en public/index.php.
- Las consultas combinadas con reservas y préstamos quedan para sus repositorios específicos.

### Iteración 7 aplicada: repositorio de reservas

- Se creó Sgsii\Repositories\ReservationRepository en src/Repositories/ReservationRepository.php.
- Las lecturas de edición y listados de reservas dejaron de contener SQL en public/index.php.
- Las reglas de creación, disponibilidad y cancelación permanecen en App hasta extraer sus casos de uso.

### Próxima iteración: enrutamiento

- Extraer la carga de dependencias, sesión, conexión y revalidación a un bootstrap.
- Convertir el bloque de acciones POST en un controlador o dispatcher pequeño.
- Mantener `?page=` y `action` para no romper enlaces ni formularios existentes.

### Iteración 3: consultas de lectura

- Extraer repositorios de usuarios, recursos, reservas, préstamos, incidencias y reportes.
- Mantener parámetros PDO preparados y compatibilidad SQLite/MySQL.
- Separar consultas de lectura de las operaciones transaccionales.

### Iteración 4: casos de uso

- Extraer servicios para autenticación, reservas, préstamos e incidencias.
- Mover allí las reglas que hoy viven en `App`.
- Inyectar repositorios y reloj cuando una regla dependa del tiempo.

### Iteración 5: vistas y presentación

- Mover las plantillas fuera de `public/index.php`.
- Crear helpers de presentación sin consultas ni reglas de negocio.
- Conservar escape HTML, CSRF y mensajes visibles.

### Iteración 6: endurecimiento

- Centralizar errores HTTP y logging.
- Sustituir estados y tipos repetidos por constantes o valores de dominio.
- Añadir pruebas unitarias para validadores y servicios extraídos.
- Eliminar código muerto sólo después de confirmar que no hay consumidores.

## Reglas para cada cambio

1. Inspeccionar primero la responsabilidad y sus consumidores.
2. Proponer una extracción pequeña y reversible.
3. Mantener nombres públicos o fachadas temporales cuando sea necesario.
4. Escribir comentarios sólo en español y únicamente para decisiones no obvias.
5. Ejecutar `php -l` sobre los archivos modificados.
6. Ejecutar `php tests/run.php` cuando estén disponibles `pdo_sqlite` y `sqlite3`.
7. Documentar qué cambió, por qué, beneficios, riesgos y siguiente paso.

## Límites de la arquitectura

- No crear interfaces o capas sin una segunda implementación real o una necesidad de prueba clara.
- No mover reglas de negocio a las vistas ni consultas a los controladores.
- No introducir un ORM para reemplazar PDO sin una justificación de migración.
- No optimizar consultas antes de preservar y medir el comportamiento actual.
