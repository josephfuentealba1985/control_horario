# Actualización 1.3 · Filtro de asistencia por perfil

En Reportes, el selector Persona, las tablas, los totales y las descargas .xlsx/PDF incluyen únicamente cuentas con perfil Usuario. Seleccionar directamente el identificador de un administrador o analista tampoco incorpora sus datos al reporte.

Vista general incluye únicamente cuentas con perfil Usuario activas y cuya fecha de inicio ya está vigente, tanto en Personas activas y los demás contadores como en la tabla Asistencia de hoy. Los usuarios inactivos siguen disponibles en Reportes para consultar su historial.

También se oculta en Vista general la observación automática del archivo de origen de cargas anteriores, manteniendo los comentarios personales.

## Aplicar sobre la versión 1.2

1. Respalda los archivos actuales desde el panel del hosting.
2. Descomprime `actualizacion-control-horario-1.3.zip` y sube su contenido a la carpeta del sistema, reemplazando los archivos incluidos y conservando la estructura de `app`.
3. Recarga el navegador con Ctrl + F5. Si usas la PWA, ciérrala y vuelve a abrirla.

No requiere reinstalación ni ejecutar `update.php`. No se reemplaza `config.php` ni se modifica la base de datos. El paquete incremental requiere la versión 1.2 ya instalada.

## Validación

13 comprobaciones automáticas satisfactorias con PHP 8.2.33 y MariaDB 10.11.13: selección de perfiles, detalle diario, filtro por identificador, selector Persona, tabla y contador de Vista general, usuarios inactivos, fechas de inicio futuras y exportaciones. Se verificó también el contenido de los archivos .xlsx y PDF para confirmar que no incluyan administradores ni analistas.
