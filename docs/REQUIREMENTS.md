# Requisitos implementables - SGSII

## Fuente y decisiones

Fuente principal: `Proyecto Ingeniería del Software.pdf`. El PDF menciona MySQL/MariaDB y el flujo inicial seleccionó SQLite para simplificar el MVP. Una ampliación posterior solicitó ejecución plug-and-play en XAMPP: la aplicación ahora soporta MySQL/MariaDB mediante PDO sin retirar SQLite de las pruebas y del modo local liviano. No hay otro conflicto relevante.

## Requisitos funcionales

- REQ-F-001: docentes y administradores inician sesión; cada usuario puede cambiar su contraseña.
- REQ-F-002: el administrador crea, edita y desactiva docentes.
- REQ-F-003: el administrador crea, edita y desactiva salas, con nombre, número, ubicación y equipos operativos.
- REQ-F-004: el administrador crea, edita y desactiva insumos con código, categoría, cantidad y estado.
- REQ-F-005: usuarios autenticados consultan disponibilidad de salas e insumos por fecha; el estado se expresa con texto e indicador de color.
- REQ-F-006: usuarios crean y cancelan sus propias reservas de salas o insumos; el administrador puede crear reservas para un docente activo y gestionar todas.
- REQ-F-007: el servidor rechaza intervalos superpuestos para una sala y reservas de insumos que excedan el stock disponible.
- REQ-F-008: el administrador registra préstamos y devoluciones de insumos.
- REQ-F-009: usuarios reportan incidencias; el administrador las actualiza hasta resolverlas y ve la disponibilidad resultante del recurso.
- REQ-F-010: el administrador consulta un resumen por rango inclusivo de fechas de reservas, préstamos e incidencias.

## Reglas de negocio

- REQ-B-001: roles válidos: `admin` y `user`; toda autorización se verifica en PHP.
- REQ-B-002: las reservas son intervalos `[inicio, fin)`, en horas exactas y con fin posterior al inicio. Las consecutivas son válidas.
- REQ-B-003: una reserva cancelada no bloquea disponibilidad.
- REQ-B-004: una sala o insumo inactivo, en reparación o fuera de servicio no se puede reservar.
- REQ-B-005: un préstamo abierto cuenta contra la cantidad disponible de un insumo hasta que se registra su devolución. Como el MVP no maneja fecha prevista de devolución, un préstamo nuevo debe dejar stock suficiente para el pico de reservas activas actuales o futuras.
- REQ-B-006: el stock no puede reducirse por debajo de las unidades comprometidas. Un insumo con préstamos abiertos o reservas activas vigentes/futuras debe permanecer operativo y activo hasta liberar esos compromisos, salvo un bloqueo de seguridad provocado por una incidencia según REQ-B-009.
- REQ-B-007: la fecha de una reserva nueva o editada no puede ser anterior al día actual. El día actual sí se permite sin validar la hora transcurrida; una reserva cuya fecha original ya pasó conserva el historial y solo puede cancelarse.
- REQ-B-008: una reserva creada por un docente siempre se asocia a su sesión, aunque el POST incluya otro usuario. El administrador solo puede asignarla a una cuenta activa con rol `user`; al editar se conserva el responsable salvo que el administrador solicite explícitamente un cambio válido.
- REQ-B-009: una incidencia de urgencia alta bloquea el recurso desde su apertura. Una incidencia baja o media solo lo bloquea cuando el administrador la pasa a `in_progress`. Al retirar un bloqueo, el recurso se rehabilita únicamente si no queda otra incidencia bloqueante; resolver una incidencia que nunca bloqueó no altera estados manuales.
- REQ-B-010: los filtros de reportes aceptan exclusivamente fechas reales en formato `YYYY-MM-DD`, requieren `desde <= hasta` e incluyen ambos días límite.

## Requisitos no funcionales y de seguridad

- REQ-NF-001: aplicación web ligera, responsive y compatible con navegadores actuales.
- REQ-NF-002: consultas sencillas para una instalación pequeña; objetivo de respuesta local menor a tres segundos.
- REQ-SEC-001: contraseñas con `password_hash`/`password_verify`; sesión regenerada al iniciar sesión.
- REQ-SEC-002: cookies HttpOnly y SameSite; `Secure` bajo HTTPS.
- REQ-SEC-003: token CSRF en toda acción autenticada que cambia estado.
- REQ-SEC-004: PDO y sentencias preparadas; validación de servidor y codificación HTML de salida.
- REQ-SEC-005: cada docente solo puede consultar sus propias incidencias. El administrador consulta todas y es el único que recibe notas administrativas; parámetros GET/POST no pueden sustituir al usuario de sesión.

## Alcance MVP

Se incluyen todos los RF explícitos del PDF que son operables de forma local: gestión de usuarios, salas, insumos, reservas, préstamos, incidencias y vista-resumen de reportes.

## Fuera de alcance

Recuperación por correo, notificaciones, reservas recurrentes, control físico de acceso, integraciones GURÍ/CREA, compras, mantenimiento preventivo, exportación de documentos y hosting 24/7. No se implementan porque requieren infraestructura o agregan complejidad no necesaria al MVP.

## Suposiciones

La institución crea el administrador inicial mediante el script local. Las reservas de insumos descuentan cantidad durante su intervalo, mientras que los préstamos descuentan cantidad hasta su devolución. Las reservas consecutivas no se suman entre sí al calcular el pico comprometido.
