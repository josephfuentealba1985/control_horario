# Actualización 1.2 · Revisión de marcados

## Instalar sobre la versión 1.1

1. Respalda los archivos y la base de datos desde tu panel del hosting.
2. Descomprime `actualizacion-control-horario-1.2.zip` y sube su contenido a la carpeta del sistema, reemplazando los archivos incluidos y conservando la estructura de carpetas.
3. Conserva tu `config.php`. El paquete no incluye configuraciones, claves ni datos de prueba.
4. Recarga el navegador (Ctrl + F5). Si usas la PWA, ciérrala y vuelve a abrirla para recibir los estilos actualizados.

**No necesitas reinstalar ni ejecutar `update.php` si ya tienes la versión 1.1.** Este cambio utiliza las tablas existentes. Si todavía estás en la primera versión sin importaciones, aplica antes la actualización de base de datos descrita en la guía 1.1.

## Cambios

- La revisión, el historial y los reportes ya no muestran etiquetas de importación ni nombres del archivo de origen. Las observaciones automáticas de cargas anteriores también se ocultan, sin borrar los datos internos ni alterar comentarios personales.
- Las nuevas cargas no añaden observaciones automáticas al registro.
- Los campos Ingreso y Salida tienen sus etiquetas y controles alineados; “opcional” aparece junto a la etiqueta de salida.
- La sección **Ubicaciones del registro** muestra el punto de ingreso y el de salida con sus mapas y enlaces.
- Sólo el administrador ve **Modificar coordenadas** y puede guardar latitud/longitud de ingreso y salida, con un motivo obligatorio. Analistas y usuarios no pueden hacerlo, aunque intenten enviar la operación directamente.
- Para quitar una ubicación, deja vacíos ambos campos de esa ubicación. Para añadirla, completa ambos usando punto decimal. Latitud: -90 a 90. Longitud: -180 a 180.
- **Guardar coordenadas** es independiente de **Guardar corrección**: no cambia las horas. Si otra persona modifica el registro antes de guardar, se solicita abrirlo nuevamente para evitar sobrescribir cambios.
- La trazabilidad conserva autor, fecha, motivo, coordenadas anteriores y nuevas. Los antecedentes originales se mantienen internamente; la pantalla muestra la ubicación vigente. Una corrección administrativa de coordenadas prevalece sobre cargas posteriores del mismo registro hasta una nueva corrección administrativa.

## Validación

28 comprobaciones automáticas satisfactorias en un entorno local con servidor PHP 8.2.33 y MariaDB 10.11.13. Incluyen permisos por rol, CSRF, motivo obligatorio, límites geográficos, pares incompletos, cero como coordenada válida, control de cambios simultáneos, edición de registros históricos, conservación de horas y antecedentes originales, y descarga de reportes. El contenido de los reportes XLSX y PDF se verificó con lectores independientes. Todos los archivos PHP pasaron revisión de sintaxis.

La base de datos del hosting no fue modificada durante estas pruebas.

Se revisó visualmente la alineación de Ingreso/Salida y el editor de coordenadas en el navegador local. El mapa utiliza el mismo servicio de la versión anterior.

