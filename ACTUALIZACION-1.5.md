# Actualización 1.5 · Justificación por jefatura y horas cubiertas

## Instalar sobre la versión 1.4

1. Respalda los archivos y la base de datos desde el panel del hosting.
2. Descomprime `actualizacion-control-horario-1.5.zip` y sube todo su contenido a la carpeta del sistema, reemplazando los archivos incluidos y conservando las carpetas `app` y `assets`.
3. Conserva `config.php`. No se incluye en el paquete.
4. Recarga con Ctrl + F5 o cierra y vuelve a abrir la PWA.

**No necesitas reinstalar ni ejecutar `update.php` sobre la versión 1.4.** Se usan las tablas existentes. No se cargan datos de prueba al hosting.

## Registrar una justificación por jefatura

En **Reportes → Revisar**, para un día sin asistencia válida, analistas y administradores ven **Justificación de ausencia** y el botón **Justificar ausencia por jefatura**.

Selecciona LICENCIA O PERMISO MÉDICO, FERIADO LEGAL o ADMINISTRATIVO, escribe un motivo obligatorio y pulsa **Guardar justificación aprobada**. La justificación queda aprobada inmediatamente y aparece en el historial, los reportes y Solicitudes al filtrar por Aprobadas o Todos.

La pantalla indica cuántas horas cubre el día. No crea horas de ingreso/salida ni evidencia de ubicación. Si existe una solicitud pendiente, permite resolverla desde esta vista y conserva la observación original del usuario. También permite corregir el tipo de una justificación, dejando registro del valor anterior, nuevo, autor, fecha y motivo.

No admite días futuros, anteriores al inicio de seguimiento ni personas con perfil distinto de Usuario. Una fecha con asistencia válida no admite una justificación de ausencia; primero debe revisarse el marcado. Si otra persona cambia la justificación, se exige volver a abrir la pantalla antes de guardar. Sólo un administrador puede revocar una aprobación, desde Solicitudes, con motivo.

## Nueva regla de cómputo

Esta regla reemplaza la de las versiones anteriores y aplica a **todas las ausencias aprobadas**, incluidas las existentes y las solicitadas por los usuarios:

- **Trabajadas:** horas efectivas de jornadas con salida válida.
- **Justificadas:** horas programadas para los días con ausencia aprobada y sin asistencia válida.
- **Computadas:** trabajadas + justificadas.
- **Diferencia:** computadas − meta.

La meta conserva el horario individual y el descuento de feriados del calendario. Los descansos, días sin horario y feriados del calendario no agregan horas justificadas. FERIADO LEGAL como justificación personal cubre la jornada programada; es distinto del feriado del calendario general.

Ejemplo: 17 horas trabajadas + 27 justificadas = 44 computadas, meta 44 y diferencia 0. No se genera un déficit por esas ausencias aprobadas. Las solicitudes pendientes, rechazadas, canceladas o revocadas no cubren horas. Al revocar, el cómputo se recalcula.

Mi jornada muestra las horas computadas y su desglose; Mi historial, Reportes y las descargas .xlsx/PDF distinguen trabajadas y justificadas. La regla también recalcula los períodos pasados al consultarlos, según el horario vigente de cada fecha. No requiere reingresar aprobaciones anteriores.

## Validación

31 comprobaciones HTTP y de cómputo satisfactorias con PHP 8.2.33 y MariaDB 10.11.13: permisos, motivo obligatorio, categorías, aprobación inmediata, correcciones, concurrencia, ausencia de marcados ficticios, conservación de notas, trazabilidad, jornada de 44 horas, feriados, descansos, días sin horario, aprobación normal y revocación. Se revisaron el formulario y los reportes en navegador, el contenido del archivo .xlsx y las dos páginas renderizadas del PDF. Todos los archivos PHP pasaron revisión de sintaxis.
