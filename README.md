# Control horario · LOOPERS

Aplicación PWA de asistencia para una sola empresa o institución. PHP nativo, MariaDB/MySQL, HTML, CSS y JavaScript. No usa frameworks, Composer, Node, procesos permanentes ni tareas programadas en el hosting.

## Instalación desde el navegador

1. En el panel de tu hosting, selecciona **PHP 8.2** y habilita **PDO MySQL** e **iconv** si no están activos. El instalador comprueba estos requisitos. No necesita la extensión ZIP para exportar Excel.
2. Crea una **base de datos vacía** MariaDB/MySQL y un usuario con permisos sobre ella. Anota el servidor, puerto, nombre de base, usuario y contraseña. Estos datos no son los del administrador de la aplicación.
3. Sube **el contenido de esta carpeta**, incluidos `.htaccess` y las carpetas `app` y `assets`, al directorio público elegido. Puede ser la raíz del dominio o una subcarpeta, por ejemplo `public_html/asistencia`.
4. Comprueba que el dominio tenga HTTPS y abre `https://tu-dominio/asistencia/`. Si subiste los archivos a la raíz, abre directamente el dominio. No abras los archivos PHP haciendo doble clic en Windows.
5. Desde el administrador de archivos del hosting, abre **`clave-instalacion.php`** y copia el texto entre comillas después de `return`. Ingrésalo como **clave de instalación**. Esta clave protege la creación inicial del administrador; no se muestra al visitar el archivo por web.
6. Completa los datos de MySQL, empresa, zona horaria, nombre, RUT y contraseña del primer administrador. Pulsa **Instalar y crear administrador**.
7. Inicia sesión. En **Personas**, crea las cuentas y asigna sus horarios. En **Configuración**, registra los feriados que aplican a la institución.
8. Cuando la instalación termine, puedes eliminar `install.php` y `clave-instalacion.php` desde el panel. El instalador ya queda bloqueado al existir `config.php`. Conserva `config.php`: contiene la conexión privada a la base.

No hay una contraseña predeterminada ni cuentas de demostración en el paquete. El primer administrador se crea durante la instalación.

### Permisos de archivos

El proceso PHP necesita escribir `config.php` una sola vez durante la instalación. Utiliza los permisos habituales de tu hosting; no es necesario conceder escritura pública total. Luego puedes retirar la escritura del directorio de la aplicación. Las operaciones diarias se guardan en la base de datos, no en archivos.

Si aparece un error 500 antes del instalador en un hosting Apache, revisa su registro de errores: algunos proveedores restringen `Options -Indexes`. En ese caso, elimina sólo esa línea de `.htaccess` y deshabilita el listado de directorios desde el panel. Las reglas incluidas están pensadas para Apache 2.4/LiteSpeed. En Nginx, solicita al proveedor bloquear `/app/`, `config.php`, `clave-instalacion.php` y archivos de respaldo/documentación. No se necesitan reglas de reescritura de URLs.

Si utilizas un proxy/CDN que termina HTTPS, configura en el hosting la detección fiable de HTTPS. La aplicación no confía automáticamente en cabeceras de proxy enviadas por el cliente. Mantén la conexión HTTPS también entre el proxy y el hosting.

## Cómo se utiliza

### Usuario

- Ingresa con RUT y contraseña. Se valida el dígito verificador del RUT.
- En **Mi jornada**, registra ingreso y salida. El ingreso admite un comentario opcional para justificar atrasos.
- Cada marcado pide ubicación al dispositivo y requiere conexión al servidor. El registro usa la hora del servidor, no la hora editable del teléfono.
- **Mi historial** muestra la semana actual, el mes o un rango personalizado de hasta 366 días.
- **Mi perfil** permite cambiar correo, teléfono, dirección y contraseña. Los demás datos, incluido el RUT, sólo los modifica el administrador.

### Analista

- **Vista general** muestra la asistencia de las personas activas con perfil Usuario.
- **Reportes** permite filtrar por persona y fechas, consultar cómputos semanales y descargar Excel `.xlsx` o PDF.
- **Revisar** permite corregir un registro existente o crear uno faltante. El motivo es obligatorio. No se atribuye ubicación a registros ingresados manualmente.
- Cada revisión permite consultar evidencia geográfica original y el historial de cambios del registro.

### Administrador

- Tiene todas las funciones anteriores.
- **Personas** permite crear cuentas con cualquiera de los tres perfiles, editar datos, activar/desactivar accesos y restablecer contraseñas.
- Las contraseñas temporales requieren cambio al primer ingreso. Restablecer una clave invalida las sesiones anteriores. La clave se entrega por el canal interno de la institución; el sistema no envía correos.
- Configura el horario individual por día y su fecha de vigencia, o selecciona **Sin horario definido**.
- Puede anular y restablecer registros. No existe borrado físico de asistencias desde la aplicación.
- **Configuración** gestiona la empresa y los feriados. Empresa/institución y cargo de cada persona son campos informativos: no crean empresas separadas.
- **Trazabilidad** muestra autor, fecha, motivo y valores anteriores/nuevos. Las claves y sus hashes no se incluyen en la auditoría.

## Reglas de cálculo

- Semana de **lunes a domingo**.
- Horario base: lunes a jueves **08:00–17:00** (9 horas diarias), viernes **08:00–16:00** (8 horas). Total **44 horas**.
- La colación está incluida; no se marca ni se descuenta.
- Se permite **un ingreso y una salida por persona y fecha local**. No se permiten jornadas que terminen al día siguiente.
- Si falta una salida, el registro queda abierto y suma **cero horas** hasta su corrección. No se cierra automáticamente a la hora programada. Se puede iniciar una nueva jornada otro día, manteniendo la anterior pendiente.
- Los tiempos efectivos se calculan entre ingreso y salida, usando minutos completos por jornada. Se muestran como **horas:minutos**, no horas decimales. Las horas del dispositivo se almacenan con segundos y se muestran al minuto; puede haber una diferencia de un minuto al comparar sólo las horas visibles.
- La meta se calcula con el horario vigente de cada día. Un feriado descuenta las horas programadas para ese día: lunes feriado en el horario base → meta **35:00**. Si se trabaja en el feriado, las horas efectivas sí se suman.
- Los días anteriores a la fecha de inicio de la persona no cuentan. Los rangos que cortan una semana se indican como parciales.
- Un horario individual diferente puede dar una meta diferente de 44 horas. Sin horario definido no se inventa una meta; la referencia general de 44 horas se muestra como información.
- Las diferencias de horas y los atrasos son informativos. Esta versión no calcula remuneraciones, horas extraordinarias pagaderas ni reglas de compensación.
- Los cambios de horario tienen vigencia. Cambiar una vigencia pasada o corregir un feriado recalcula los períodos afectados, y queda auditado. Para conservar reportes tal como fueron emitidos, guarda el PDF/Excel descargado.
- Desactivar una cuenta impide el acceso y la elimina de la vista general activa, pero conserva sus registros y su disponibilidad en reportes. Esta versión no gestiona fecha de término de contrato.

## Ubicación y PWA

Se guardan latitud, longitud, precisión informada por el dispositivo, fecha y hora de cada marcado. Sólo se solicita ubicación al pulsar el botón; no hay seguimiento continuo, fotos ni huellas. No se aplica geocerca ni radio máximo: el requisito acordado es registrar la ubicación. La geolocalización depende del dispositivo y no constituye autenticación biométrica ni protección contra manipulación del GPS.

La aplicación se puede instalar desde el navegador cuando éste lo permite:

- **Android / escritorio:** menú del navegador → Instalar aplicación, o botón de instalación cuando esté disponible.
- **iPhone/iPad:** Safari → Compartir → Agregar a inicio.

Sin conexión se muestra una pantalla informativa. **No se guardan marcados pendientes de sincronización ni páginas personales en la caché de la PWA.** Si se corta Internet durante un marcado, consulta el historial al reconectar antes de repetirlo.

## Seguridad y mantenimiento

- Contraseñas con `password_hash`/`password_verify`, consultas preparadas, protección CSRF, comprobación de permisos en el servidor y escape de textos en pantalla.
- Cookies de sesión HttpOnly, SameSite y Secure en HTTPS. Sesiones con 30 minutos de inactividad y máximo 12 horas.
- Límite de intentos de ingreso por cuenta y por dirección IP.
- Marcados y cambios se confirman en transacciones. Restricción única persona/fecha y control de versión para evitar sobrescribir correcciones simultáneas.
- Ubicaciones originales separadas de los horarios corregidos.
- La auditoría es ineditable desde la aplicación. Un operador con acceso directo a la base de datos sí tiene control técnico sobre ella.
- No contiene servicios externos de analítica, tipografías remotas, dependencias CDN ni claves de API. Los mapas integrados consultan OpenStreetMap al cargar la evidencia e incluyen sus coordenadas. Se conserva el enlace externo.
- Mantén respaldos periódicos de la base mediante el panel del hosting y conserva una copia privada de `config.php` y de los archivos del proyecto. Guarda los respaldos fuera del directorio público.
- Para trasladar la instalación, copia archivos y base, y ajusta los datos privados de `config.php` desde el panel. No ejecutes el instalador sobre una base ya utilizada.
- Al actualizar assets, cambia la versión de `CACHE` en `sw.js` para renovar la caché pública.

## Organización de archivos

| Archivo o carpeta | Función |
|---|---|
| `index.php` | Entrada, sesión y navegación |
| `install.php` | Instalación guiada |
| `export.php` | Descargas de reportes |
| `app/core.php` | Conexión, validación, fecha y utilidades |
| `app/actions.php` | Operaciones y permisos |
| `app/reports.php` | Cálculo semanal y detalle diario |
| `app/views.php` | Pantallas |
| `app/exporters.php` | Generadores XLSX/PDF sin paquetes externos |
| `app/schema.php` | Tablas creadas por el instalador |
| `assets/` | Estilos, JavaScript e iconos PWA |
| `manifest.webmanifest`, `sw.js`, `offline.html` | Instalación y funcionamiento PWA |
| `logo.png` | Logo LOOPERS original proporcionado |

Compatible con PHP 8.2.33 y MariaDB 10.11.13. La validación local se documenta en `VALIDACION.md`. La puesta en marcha final requiere los datos reales de MySQL del hosting y una prueba de ubicación en los dispositivos de uso.

## Actualización 1.1

Incluye importación de asistencia XLSX por persona, solicitudes de justificación y mapas integrados. Consulta ACTUALIZACION-1.1.md para actualizar una instalación existente desde el navegador, sin reinstalar.

## Actualización 1.2

Revisión unificada de ubicaciones, eliminación de referencias automáticas al origen de los registros y edición de coordenadas exclusiva del administrador, con motivo y trazabilidad. Consulta ACTUALIZACION-1.2.md. No requiere cambios de tablas sobre la versión 1.1.


## Actualización 1.5 · Horas justificadas

Analistas y administradores pueden registrar justificaciones aprobadas por jefatura desde Revisión de marcado, con motivo y trazabilidad. Todas las ausencias aprobadas, incluidas las anteriores, cubren el horario individual como horas justificadas. Computadas = trabajadas + justificadas; diferencia = computadas menos meta. Consulta ACTUALIZACION-1.5.md: esta regla reemplaza el cómputo de ausencias de versiones anteriores.


## Actualización 1.6.0 · Auditor, conexión y versión

IP y datos del navegador en los nuevos marcados, perfil Auditor de consulta y exportación, y versión visible en el login y pie de la aplicación. El Auditor puede cambiar únicamente su propio contacto y contraseña. Requiere actualizar la base mediante `update.php` como administrador después de subir los archivos. Consulta ACTUALIZACION-1.6.md.

## Actualización 1.7.0

Marcado prioritario y compacto en móvil; consulta de las ubicaciones propias de entrada y salida en Mi jornada y Mi historial. Nueve comprobaciones de integración satisfactorias y revisión móvil a 390 × 700. Consulta ACTUALIZACION-1.7.md.
