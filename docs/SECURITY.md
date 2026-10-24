# Seguridad

Las credenciales MySQL se guardan únicamente en `config.local.php`, archivo excluido de Git. El instalador no copia configuraciones locales ni bases de prueba a XAMPP.

- Autenticación: hashes con `PASSWORD_DEFAULT`; no se guardan contraseñas en sesión ni texto plano.
- Sesión: identificador regenerado al iniciar sesión; cookie HttpOnly y SameSite=Lax. `Secure` se activa en HTTPS.
- Autorización: cada acción y página administrativa exige `admin` en PHP; ocultar enlaces nunca es el control de seguridad.
- CSRF: cada POST autenticado incluye y valida un token aleatorio de sesión.
- Base de datos: PDO preparado para parámetros dinámicos y claves foráneas activas.
- Entradas: validación de longitud, listas permitidas, fechas, horas enteras y enteros positivos en servidor.
- Salida: `htmlspecialchars` con UTF-8 y comillas codificadas.
- Errores: las validaciones muestran mensajes controlados; los fallos internos usan un mensaje genérico y se registran localmente sin consultas, mensajes sensibles ni rutas completas.

## Limitaciones del MVP

El servidor integrado de PHP es solo para desarrollo. HTTPS, copias de seguridad, límites de intentos y recuperación de contraseña por correo deben configurarse en producción. No se requiere cifrado reversible: las contraseñas se protegen con hash y no se versionan secretos.
