# Actualización 1.6.0

## Qué incorpora

- IP de conexión, datos del navegador y, cuando están disponibles, plataforma y condición de navegador móvil en los nuevos marcados de ingreso y salida. Se consultan en **Revisión de marcado → Conexión y dispositivo del marcado**. La trazabilidad también muestra estos datos en los nuevos eventos; el detalle diario exportado a XLSX incluye IP, navegador y plataforma de ingreso y salida.
- Perfil **Auditor**: Vista general, Reportes, Solicitudes, Revisión de marcado y Trazabilidad, con consulta y exportación XLSX/PDF. No puede marcar, corregir, aprobar solicitudes, importar, administrar personas ni configurar el sistema. Sólo puede modificar sus propios datos de contacto y contraseña desde Mi perfil.
- **Versión 1.6.0** visible en el pie del login y de las pantallas de la aplicación. Indica la versión de los archivos instalados.

## Actualizar una instalación existente

1. Respalda la base de datos y los archivos desde el panel del hosting.
2. Extrae `actualizacion-control-horario-1.6.zip` y sube su contenido a la carpeta de la aplicación, reemplazando los archivos y combinando las carpetas. Conserva tu `config.php`; el paquete de actualización no contiene este archivo ni reemplaza la clave de instalación.
3. Inicia sesión como administrador y abre **Actualizar sistema** en el aviso, o visita `https://tu-dominio/ruta/update.php`.
4. Pulsa **Actualizar base de datos**. No requiere consola. La cuenta de base de datos debe tener permisos para crear y modificar tablas. La actualización conserva usuarios, contraseñas, registros y horarios; puede repetirse si se interrumpe.
5. Recarga la aplicación. Comprueba que el pie muestre **Versión 1.6.0**. En **Personas**, el administrador ya puede crear o asignar el perfil **Auditor**.
6. Realiza un marcado de prueba por HTTPS y comprueba sus datos en Revisión de marcado. Prueba una cuenta Auditor y una descarga de reporte.

Para una instalación nueva utiliza `control_horario.zip` y el instalador habitual; ya incorpora las tablas nuevas.

## Alcance de los datos

- La captura comienza después de actualizar la base. Los registros históricos, manuales e importados no reciben una IP o un dispositivo de marcado inventados. Las operaciones nuevas de corrección tienen su propio contexto en la trazabilidad.
- Las correcciones de horario o coordenadas no reemplazan los datos de conexión del marcado original.
- Se guarda la IP de la conexión recibida por el servidor. Si el hosting está detrás de un proxy/CDN, puede aparecer su IP: el proveedor debe configurar la dirección real del cliente únicamente para proxies de confianza. La aplicación no confía en cabeceras reenviadas arbitrariamente por el cliente.
- El navegador decide qué información de equipo comunica. Estos datos son orientativos y pueden ser modificados por el cliente; no identifican de forma única un dispositivo y no incluyen IMEI ni dirección MAC.
- Los PDF mantienen el reporte de asistencia. El detalle técnico de conexión se consulta en pantalla y en las columnas adicionales del XLSX.

## Validación realizada

54 comprobaciones de integración satisfactorias con PHP 8.2.33 y MariaDB 10.11.13: actualización conservando datos, repetición de actualización, instalación nueva, creación de Auditor, cambio de contraseña inicial, permisos de consulta, bloqueo de todas las acciones de gestión y marcado, captura en entrada/salida, rechazo de IP reenviada falsa, escape de datos del navegador y exportación XLSX/PDF. Exportaciones verificadas con lectores independientes. Login y panel de conexión revisados visualmente con datos ficticios locales.

La instalación en producción y la comprobación de la IP real del hosting quedan pendientes de subir esta actualización.
