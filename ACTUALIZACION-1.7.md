# Actualización 1.7.0 · Mi jornada y ubicaciones

- «Marca tu jornada» aparece antes del resumen de horas. En móvil se compactan el reloj y los espacios; el comentario opcional se despliega al pulsarlo, para dejar visible el botón de marcado.
- «Mis ubicaciones» permite desplegar Entrada y Salida, consultar coordenadas y mapa OpenStreetMap, y abrirlo por separado. Sólo muestra los datos guardados: no solicita ubicación actual ni permite modificarlos.
- En Mi historial, «Ver ubicaciones» permite consultar días anteriores. La consulta siempre corresponde a la cuenta que inició sesión, aunque se alteren los parámetros de la dirección.
- Se muestran las coordenadas vigentes del registro, incluidas correcciones autorizadas. Si no hay coordenadas o falta el marcado, se informa sin inventar una ubicación.

## Instalación

Respalda los archivos y la base. Sube el contenido de `actualizacion-control-horario-1.7.zip` a la carpeta de la aplicación, reemplazando archivos y combinando carpetas. Conserva `config.php` y tu clave de instalación; el paquete no los incluye.

Desde 1.6.0 no necesitas actualizar tablas. Si todavía no aplicaste la actualización de base de 1.6, entra como administrador a `update.php` y pulsa Actualizar base de datos. El paquete incluye los cambios anteriores.

Cierra y vuelve a abrir la PWA o recarga la página para activar los estilos actualizados. El pie debe mostrar Versión 1.7.0.

## Validación

Sintaxis PHP verificada y 9 comprobaciones de integración satisfactorias: orden del marcado, ubicaciones propias, aislamiento entre cuentas, ausencia de edición, enlaces del historial, fechas inválidas y días sin registro. Diseño revisado a 390 × 700: botón de ingreso visible sin desplazamiento. Los mapas integrados no cargan en el navegador de pruebas; se comprobaron las coordenadas y enlaces OpenStreetMap con datos ficticios. Comprobar su carga en el navegador habitual por HTTPS después de subir los archivos.
