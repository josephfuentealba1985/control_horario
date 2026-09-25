# Actualización 1.1 · Control horario

## Aplicar en una instalación existente

1. Respalda la base de datos y los archivos desde el panel del hosting.
2. Descomprime `actualizacion-control-horario-1.1.zip` y sube su contenido a la carpeta donde funciona el sistema. Reemplaza los archivos incluidos y conserva las carpetas `app` y `assets` con su estructura.
3. **Conserva tu `config.php`.** El paquete de actualización no incluye este archivo ni la clave de instalación. No reinstales el sistema ni borres la base.
4. Inicia sesión como administrador. Pulsa el aviso de actualización o abre `update.php` en la misma carpeta del sistema.
5. Pulsa **Actualizar base de datos**. Se agregan las tablas necesarias; se conservan las personas, claves, horarios, marcados y auditoría existentes. El usuario MySQL necesita permiso para crear tablas. Si la operación falla, puedes volver a intentarla.
6. Recarga la aplicación. Comprueba los nuevos accesos **Mis justificaciones**, **Solicitudes** e **Importar historial Excel**.

No necesitas consola ni instalar paquetes. Para leer Excel, PHP debe tener SimpleXML y zlib habilitados, además de los requisitos existentes (PDO MySQL e iconv). No se requiere ZipArchive. El límite de subida del hosting debe permitir el tamaño de tu archivo.

## Importar asistencia histórica

En **Personas → Gestionar persona → Importar historial Excel**, selecciona el archivo `.xlsx`, el modo de importación y un motivo obligatorio. Pulsa **Analizar y mostrar vista previa**, revisa los cambios y confirma.

- Sólo el administrador puede importar. Cada archivo corresponde a la persona seleccionada y todos los RUT deben coincidir con ella.
- Se lee la primera hoja, con encabezados en su primera fila: `RUT`, `Fecha`, `Ingreso`, `Salida`. Las columnas opcionales son `lat_ingreso`, `long_ingreso`, `lat_salida`, `long_salida`. El orden de las columnas puede variar.
- Hasta 2.000 filas y 5 MB. Admite fechas/horas reales de Excel o fechas `DD/MM/AAAA` / `AAAA-MM-DD` y horas `HH:MM[:SS]`. Las horas se interpretan en la zona horaria configurada. Si hay fórmulas, pega sus resultados como valores antes de importar.
- Por defecto se conservan las fechas existentes. El modo **Actualizar** permite reemplazar sus horas y coordenadas importadas. Los cambios idénticos no generan duplicados.
- Las filas con ingreso y salida vacíos se omiten: no borran registros ni justifican inasistencias. Un ingreso sin salida queda abierto, sin sumar horas hasta completarse.
- No admite fechas duplicadas, marcados futuros, salidas anteriores al ingreso, jornadas nocturnas o coordenadas incompletas. Los errores se presentan antes de guardar; un conflicto impide confirmar el lote.
- Si hay fechas anteriores al inicio de seguimiento de la persona, se pide confirmar su ampliación y el horario histórico utilizado. Revisa ese horario: influye en la meta de las semanas anteriores.
- Se registra autor, fecha, motivo, archivo, identificación del lote y valores anteriores/nuevos. Las ubicaciones declaradas en Excel se muestran separadas de la evidencia original capturada por el dispositivo; no sustituyen esa evidencia.
- La vista previa vence a los 30 minutos. Si otra persona cambia un registro o el horario durante la revisión, debes analizar nuevamente el archivo.

El archivo proporcionado para las pruebas contiene 55 filas con asistencia y 32 días sin horas; la última jornada tiene ingreso sin salida. Se probó únicamente en una base local aislada. **No se ha importado en la base del hosting.**

## Justificar una inasistencia

El usuario abre **Mis justificaciones**, selecciona una fecha o un rango de hasta 90 días y uno de estos motivos:

- LICENCIA O PERMISO MÉDICO
- FERIADO LEGAL
- ADMINISTRATIVO

Puede añadir una observación. Se admiten días desde su inicio de seguimiento hasta hoy, sin asistencia válida existente. Se crea una solicitud por día. Las solicitudes aparecen en **Solicitudes**, con un contador de pendientes para analistas y administradores; el aviso es dentro del sistema, no por correo.

El analista o administrador aprueba o rechaza con motivo obligatorio. Nadie aprueba su propia solicitud. El usuario puede cancelar una solicitud pendiente o volver a presentar una rechazada, cancelada o revocada. Sólo el administrador puede revocar una aprobación, con motivo y trazabilidad.

**Los tres motivos mantienen la meta semanal y no agregan horas trabajadas.** La aprobación aparece en el historial y en los reportes Excel/PDF. “FERIADO LEGAL” es una justificación personal: los feriados del calendario general siguen descontando las horas programadas de la meta.

Una inasistencia aprobada impide registrar o importar asistencia en ese día. Para corregirla, el administrador debe revocar primero la aprobación.

## Mapa en la revisión

La evidencia original y las coordenadas importadas incluyen un mapa de OpenStreetMap con el punto indicado. Se mantiene el enlace para abrirlo en otra pestaña. La carga requiere Internet y consulta a OpenStreetMap con las coordenadas mostradas. No requiere clave de API.

Si el navegador o la red bloquea el mapa integrado, utiliza el enlace. La carga del mapa integrado debe comprobarse en el navegador habitual del hosting: en el navegador de pruebas integrado el marco quedó vacío, aunque la misma dirección de OpenStreetMap abrió correctamente con mapa y marcador.

## Validación

118 comprobaciones automáticas satisfactorias: 68 sobre las nuevas funciones y 50 sobre instalación y flujos existentes. Pruebas del servidor con PHP 8.2.33 y MariaDB 10.11.13, incluida la actualización de una base anterior y una instalación nueva. Se revisaron las pantallas de importación/solicitudes y el contenido y presentación de los reportes. No se incluyen datos, cuentas o configuración de pruebas en los paquetes.
