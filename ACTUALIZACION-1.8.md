# Actualización 1.8.0 · Cercos GPS

## Uso

1. En **Configuración → Administrar cercos GPS**, crea un cerco y escribe su nombre.
2. Acerca o desplaza el mapa hasta la sede. También puedes centrarlo mediante latitud y longitud.
3. Pulsa los vértices del perímetro en orden. El último punto se une automáticamente al primero. No repitas el primer punto al terminar. Se admiten entre 3 y 200 vértices por cerco.
4. Usa **Terminar dibujo**, arrastra vértices si necesitas ajustarlos, ingresa el motivo y guarda. **Continuar dibujo**, **Deshacer punto** y **Limpiar dibujo** permiten corregirlo.
5. En **Personas → Gestión de persona → Cercos GPS**, selecciona una, varias o todas las zonas que corresponden a esa persona. Guarda con un motivo.
6. Para permitir el marcado desde cualquier lugar, deja todas las casillas desmarcadas y guarda. La ubicación sigue siendo obligatoria.

La asignación se configura después de crear la persona y sólo corresponde al perfil Usuario. Los cercos creados no se asignan automáticamente. Elegir todos significa seleccionar los existentes: un cerco nuevo requiere una nueva asignación.

## Reglas

- Se valida en el servidor tanto la entrada como la salida. Basta con estar dentro de uno de los cercos asignados; se puede ingresar en una sede y salir en otra autorizada. El borde cuenta como interior.
- La precisión máxima admitida es configurable por cerco, entre 5 y 1000 metros; inicialmente 100. El punto debe estar dentro y la precisión informada debe cumplir el límite. Este valor no amplía el perímetro ni garantiza que todo el margen de error esté dentro.
- Un rechazo no crea una entrada ni cierra una jornada. La pantalla explica si el problema es estar fuera o tener poca precisión.
- Crear, editar, archivar y asignar cercos requiere perfil Administrador. Los cambios tienen motivo, autor, fecha y valores anteriores/nuevos en Trazabilidad.
- No se puede archivar un cerco mientras tenga asignaciones. Archivar conserva su historial.
- Un cambio de cerco se aplica al siguiente marcado de las personas asignadas, incluida la salida de una jornada abierta. No recalcula ni invalida marcados históricos.
- La trazabilidad del marcado conserva el cerco y su revisión utilizados para autorizarlo. Los cambios del contorno se conservan en la auditoría del cerco.
- Las correcciones manuales del analista/administrador y las importaciones históricas mantienen su flujo de justificación y trazabilidad; no son marcados GPS en vivo.
- Sin las tablas nuevas se mantiene el funcionamiento previo. Tras actualizar, nadie tendrá restricciones hasta que el administrador asigne cercos.

## Actualización en el hosting

1. Respalda la base y los archivos desde el panel del hosting.
2. Sube el contenido de **actualizacion-control-horario-1.8.zip**, combinando carpetas y reemplazando archivos. Conserva `config.php` y la clave de instalación; no se incluyen en el paquete de actualización.
3. Inicia sesión como administrador y abre **Actualizar sistema**, o visita `update.php`.
4. Pulsa **Actualizar base de datos**. Conserva cuentas y registros y crea las tablas de cercos/asignaciones. No requiere consola y puede repetirse si se interrumpe.
5. Recarga o vuelve a abrir la PWA. Comprueba **Versión 1.8.0**, crea una zona de prueba y prueba entrada/salida dentro y fuera con un celular real por HTTPS.

Para una instalación nueva, el paquete completo `control_horario.zip` ya incluye las tablas. No se requiere Composer ni servicios adicionales en el hosting.

## Mapa y alcance

El editor usa Leaflet 1.9.4, una biblioteca de mapas incluida localmente con su licencia, y cartografía OpenStreetMap. Requiere Internet para cargar el fondo del mapa; no hace falta una clave API. No descarga mapas masivamente ni los guarda para uso sin conexión. Las peticiones del fondo del mapa se realizan desde el navegador a OpenStreetMap.

La ubicación y precisión proceden del navegador/dispositivo. El cerco impone una regla sobre esos datos; una PWA no puede garantizar que el equipo no falsifique su ubicación. No realiza seguimiento continuo ni solicita ubicación para dibujar el mapa.

## Comprobaciones

45 comprobaciones de integración satisfactorias en PHP 8.2.33 y MariaDB 10.11.13: migración repetible, conservación de asistencia, esquema de instalación nueva, geometría, límites, polígonos inválidos, varias zonas, entrada/salida fuera, precisión insuficiente, marcado sin restricciones, permisos, CSRF, conflictos de edición, archivo seguro y trazabilidad.

Editor comprobado visualmente: mapa cargado, dibujo de cuatro vértices, guardado y recuperación del polígono. Pruebas realizadas con cuentas y ubicaciones ficticias locales. No se ha instalado en el hosting.
