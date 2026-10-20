# Actualización 1.9.0

Incluye licencia MIT y registro gratuito del instalador oficial mediante LOOPERS: una clave por instalación, con comprobante firmado y reintentos seguros. Añade la vinculación y el traslado desde Configuración, exclusivos del administrador.

Para actualizar desde 1.8, respalda archivos y base y sube el contenido del paquete de actualización. Conserva `config.php` y `activation-state.php` si existe. No hay cambios de esquema desde 1.8; no se modifican asistencias, usuarios ni horarios.

Las instalaciones existentes no necesitan activarse para seguir trabajando. Las nuevas utilizan una clave generada en https://loopers.cl/proyectos/. Para activar se necesitan cURL, OpenSSL y acceso HTTPS saliente a LOOPERS. El uso diario no consulta el servicio de activación.

La antigua `clave-instalacion.php` ya no se usa. Puedes retirarla. Guarda una copia privada de `activation-state.php` junto a `config.php` y la base de datos. Nunca incluyas estos archivos en GitHub.

Para trasladar a otro dominio o ruta, genera una clave de reemplazo en el portal, migra los datos por separado y vincula la clave desde Configuración. Una copia instalada no se bloquea a distancia.

El software se entrega bajo MIT; el registro del instalador no limita los derechos de uso, modificación o redistribución de esa licencia. Los servicios de soporte son opcionales.
