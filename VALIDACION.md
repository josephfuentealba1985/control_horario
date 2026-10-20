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

## Actualización 1.1

118 comprobaciones satisfactorias: 68 de importación/justificaciones y 50 de regresión, incluida instalación nueva. Actualización de una base anterior conservando datos, importación real en base aislada, conflictos, permisos, trazabilidad, metas sin descuento por justificaciones y exportaciones XLSX/PDF verificadas. Consulta ACTUALIZACION-1.1.md para el alcance y la limitación de visualización del mapa integrado en el navegador de pruebas.


## Actualización 1.2 · 28 de septiembre de 2026

28 comprobaciones automáticas satisfactorias de permisos, coordenadas, concurrencia, preservación de horas y evidencias, y presentación de registros sin referencias al archivo de origen. Reportes XLSX/PDF comprobados con lectores independientes. Consulta ACTUALIZACION-1.2.md.


## Actualización 1.5 · 28 de septiembre de 2026

31 comprobaciones satisfactorias sobre justificación por jefatura, permisos, conflictos, trazabilidad y cómputo de horas justificadas. Caso verificado: 17 trabajadas + 27 justificadas = 44 computadas, meta 44, diferencia 0. Formulario y reporte revisados en navegador; XLSX validado con lector independiente y PDF renderizado y revisado visualmente. Esta versión cambia la regla anterior para todas las ausencias aprobadas. Consulta ACTUALIZACION-1.5.md.


## Actualización 1.6.0 · 29 de septiembre de 2026

54 comprobaciones satisfactorias de actualización e instalación, permisos del Auditor, captura de conexión y exportación. IP de entrada/salida y datos del navegador comprobados con un lector XLSX independiente; PDF abierto con lector independiente. Login y datos de conexión revisados en navegador. PHP 8.2.33 y MariaDB 10.11.13. Datos y credenciales de prueba excluidos de la entrega. Consulta ACTUALIZACION-1.6.md.

## Actualización 1.7.0

Marcado prioritario y compacto en móvil; consulta de las ubicaciones propias de entrada y salida en Mi jornada y Mi historial. Nueve comprobaciones de integración satisfactorias y revisión móvil a 390 × 700. Consulta ACTUALIZACION-1.7.md.

## Actualización 1.8.0 · Cercos GPS

Cercos poligonales en Configuración y asignación individual en Gestión de persona. Sin cercos se permite marcar en cualquier lugar; con cercos se valida entrada y salida en el servidor. Incluye precisión máxima configurable, trazabilidad y edición de contornos. Requiere ejecutar `update.php` como administrador después de subir los archivos. 45 comprobaciones satisfactorias y revisión visual del editor. Consulta ACTUALIZACION-1.8.md.
