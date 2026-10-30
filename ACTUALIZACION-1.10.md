# Control horario 1.10.0 · Correo de marcados

1. Respalda archivos y base de datos. Sube el contenido de actualizacion-control-horario-1.10.zip sobre la instalación existente. Conserva config.php, activation-state.php y mail-config.php si existe. No se requieren cambios de tablas desde 1.9 ni comandos en el hosting.
2. Como administrador, abre Configuración → Correo de confirmación de marcados.
3. Completa servidor SMTP, puerto, cifrado (STARTTLS, normalmente 587, o TLS implícito, normalmente 465), usuario, contraseña, correo remitente autorizado y nombre del remitente. Usa los parámetros entregados por tu proveedor.
4. Activa las confirmaciones, guarda y envía una prueba a tu correo. Verifica entrada y correo no deseado. La prueba utiliza la configuración guardada y se puede enviar incluso antes de activar las confirmaciones.
5. Verifica que cada usuario tenga un correo correcto en su perfil. Las confirmaciones se envían a ese correo, no al administrador. El usuario puede actualizarlo en Mi perfil y el administrador en Personas.

Se envía un mensaje por entrada y otro por salida, sólo después de guardar el marcado. Incluye persona, empresa, fecha, hora, zona horaria y referencia de asistencia. No incluye RUT, coordenadas ni comentarios personales. Los ajustes manuales, las importaciones y los intentos rechazados o duplicados no envían correos.

Las confirmaciones están desactivadas inicialmente. Si falta el correo del usuario o el envío falla, la asistencia permanece guardada, se informa al usuario y queda constancia en Trazabilidad. No se reintenta automáticamente y no hay tareas programadas. La aceptación por SMTP no garantiza que el mensaje llegue a la bandeja de entrada. Consulta también los registros de entrega del proveedor.

El envío se realiza desde PHP mediante PHPMailer 7.1.1 incluido, sin Composer ni frameworks. Necesita OpenSSL y conexión saliente del hosting al puerto SMTP seleccionado. Se valida el certificado TLS; no se permiten conexiones sin cifrado. La llamada al servidor de correo ocurre en la misma solicitud, después de confirmar la asistencia, y puede añadir unos segundos de espera.

Las credenciales se guardan en mail-config.php, un archivo PHP privado en la carpeta de la aplicación. PHP necesita escribir allí al guardar la configuración. No se muestran contraseñas guardadas en el navegador ni en la trazabilidad. Dejar vacía la contraseña conserva la anterior. Respalda este archivo de forma privada, no lo publiques en GitHub y no lo incluyas en paquetes de distribución.
