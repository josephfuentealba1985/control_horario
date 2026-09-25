# Validación de la primera versión

Fecha: 25 de septiembre de 2026.

## Entorno

- Servidor local con **PHP 8.2.33**.
- **MariaDB 10.11.13**, en una base de prueba aislada.
- Pruebas HTTP reales contra instalación, autenticación, formularios y exportaciones.
- Revisión visual en el navegador con tamaños de celular y escritorio.
- Paquete de entrega sin base de prueba, cuentas de ejemplo ni configuración MySQL local.

## Resultados

**91 comprobaciones automáticas satisfactorias:**

- 50 de instalación y flujos principales: instalador web, bloqueo tras instalación, validación CSRF, creación de perfiles, cambio de clave temporal, restricciones por rol, historial personal, justificación obligatoria, horarios, ubicación, duplicados, reportes y restablecimiento de clave.
- 22 adicionales: conservación de evidencia original, anulación/restauración, registros sin salida, horario no definido, protección del acceso administrador, mantenimiento de feriados, validación RUT, cierre de sesión, limitación de intentos y PDF mensual.
- 10 de lógica del navegador/PWA: no almacenar páginas privadas, no interceptar marcados POST, no cachear exportaciones, pantalla pública sin conexión, rechazo de ubicación denegada, fallos de red y prevención de doble envío.
- 9 de funciones base: RUT, fechas bisiestas, cómputo de jornada, colación incluida, ausencia de salida, formato de horas, texto PDF y celdas Excel tratadas como texto en vez de fórmulas.

Además:

- Todos los archivos PHP pasaron revisión de sintaxis con PHP 8.2.33.
- Se comprobó la sintaxis del JavaScript del navegador y del service worker.
- Los Excel fueron abiertos con un lector independiente para comprobar hojas, contenido, horas y seguridad de textos que comienzan con `=`.
- Los PDF se abrieron con un lector independiente y se renderizaron para revisar tablas, acentos, ceros, encabezados, continuidad y números de página.
- Se verificó el caso de una semana de **44 horas**, reducida a **35 horas** por un feriado de lunes.

## Verificaciones de puesta en marcha

El hosting real todavía no está configurado. Tras subir los archivos:

1. Completar el instalador con una base vacía y las credenciales del hosting.
2. Iniciar sesión con un usuario de prueba de la institución desde un celular real por HTTPS.
3. Aceptar ubicación, registrar ingreso y salida y revisar hora, coordenadas y precisión.
4. Probar un permiso de ubicación denegado y una desconexión real.
5. Instalar la PWA en los navegadores que utilizará la institución.
6. Descargar un Excel y un PDF, y comprobar que el panel del hosting permite respaldar la base.

Las pruebas locales de marcado usaron coordenadas simuladas; no se recogió ubicación real de una persona. La revisión móvil comprobó el diseño responsive en el navegador, no un dispositivo físico. Las reglas Apache `.htaccess` se entregan para el hosting y no son ejecutadas por el servidor de desarrollo PHP.
