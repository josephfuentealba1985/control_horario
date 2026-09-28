# Actualización 1.4 · Menú e inicio por perfil

Administradores y analistas ingresan a Vista general. Su menú ya no incluye Mi jornada, Mi historial ni Mis justificaciones. Mi perfil sigue disponible. Los enlaces antiguos a las tres pantallas personales muestran Vista general. Los usuarios mantienen su menú e inicio en Mi jornada. El cambio obligatorio de contraseña temporal conserva su prioridad.

## Aplicar sobre la versión 1.3

Respalda los archivos actuales, descomprime `actualizacion-control-horario-1.4.zip` y sube su contenido a la carpeta del sistema, reemplazando los archivos incluidos y conservando la carpeta `app`. Recarga con Ctrl + F5 o cierra y vuelve a abrir la PWA.

No requiere reinstalar, ejecutar `update.php` ni cambiar la base de datos. Conserva `config.php`; el paquete no lo incluye.

Validación: 21 comprobaciones HTTP satisfactorias con PHP 8.2.33 y MariaDB 10.11.13, cubriendo inicio, menú, enlaces antiguos y acceso a Mi perfil para los tres roles. Los archivos modificados pasaron revisión de sintaxis PHP.
