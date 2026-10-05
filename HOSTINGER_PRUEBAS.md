# Preparación de pruebas en Hostinger

Esta entrega contiene el código y las correcciones de ONETASK para validación. No acredita un despliegue listo para producción. No incluye base de datos, credenciales ni adjuntos de clientes.

## Repositorio y carpetas

- Repositorio: https://github.com/airlab2024/onetask.com.co.git
- Rama de pruebas inicial: main. Mantener el despliegue automático desactivado durante la configuración.
- Ruta de instalación Git, relativa a public_html: onetask-pruebas.
- Documento raíz del subdominio pruebas.onetask.com.co: public_html/onetask-pruebas/public.
- Producción continúa en public_html/onetask y no se debe modificar.

Hostinger requiere una carpeta vacía para instalar Git. La creación del subdominio puede haber creado la subcarpeta public: revisar su contenido antes de instalar. Si el panel rechaza el destino, resolver únicamente la carpeta nueva de pruebas; no vaciar public_html ni onetask. Si ya se instaló el repositorio, no volver a clonarlo encima.

El subdominio solo debe servir public. Como el proyecto queda debajo del public_html del dominio principal, también debe bloquearse el acceso directo por ese dominio a /onetask-pruebas (salvo a public si se necesita). No habilitar el sitio hasta confirmar que ningún host permite descargar .env, composer.json o archivos internos. Proteger el entorno de pruebas con contraseña desde el hosting.

## Configuración pendiente antes de abrir el sitio

1. Crear una base de datos y usuario exclusivos de pruebas. Importar allí una copia del SQL revisado. No utilizar el usuario ni la base de producción.
2. Configurar PHP y extensiones compatibles con composer.lock; las pruebas locales se ejecutaron con PHP 8.3.30. Instalar Composer si no está disponible y revisar los requisitos de plataforma.
3. Crear .env a partir de .env.example y editarlo antes de arrancar la aplicación:
   - APP_ENV=staging, APP_DEBUG=false.
   - APP_URL=https://pruebas.onetask.com.co, APP_FORCE_HTTPS=true.
   - Credenciales de la nueva base de pruebas.
   - SESSION_COOKIE=onetask_pruebas_session, SESSION_DOMAIN=null, SESSION_SECURE_COOKIE=true.
   - MAIL_MAILER=log, BROADCAST_DRIVER=log, QUEUE_CONNECTION=database.
   - APP_TIMEZONE=America/Bogota.
   - Sin credenciales de Jira, OIDC ni otras integraciones de producción; revisar las configuraciones guardadas también en la tabla settings.
4. Si se restauran datos cifrados, conservar la APP_KEY correspondiente de forma privada. No ejecutar key:generate sobre una copia con datos cifrados sin validar sus consecuencias. Nunca publicar .env ni copiarlo a public.
5. Revisar las colas, sesiones y notificaciones restauradas: no arrancar workers ni scheduler con trabajos copiados de producción. Cualquier limpieza debe limitarse a la nueva base de pruebas.
6. En la carpeta del proyecto, instalar las dependencias del lock, sin actualizar versiones:

   ```sh
   composer install --no-dev --prefer-dist --optimize-autoloader
   composer check-platform-reqs --no-dev
   php artisan optimize:clear
   ```

   No utilizar composer install-project, composer update, db:seed ni migrate:fresh para restaurar una base existente. Los assets compilados public/build se incluyen; vendor y node_modules se regeneran por los gestores de dependencias.
7. Dar al usuario PHP permisos de escritura en storage y bootstrap/cache, sin permisos 777. Crear storage/app/task-files si hace falta. Comprobar que task-files no tiene enlaces públicos.
8. Inspeccionar migrate:status. El SQL recibido ya incluye clientes.abreviatura y la migración correspondiente. Las migraciones históricas están incompletas: no ejecutar migrate de todas ellas hasta compararlas con el SQL. Validar inicialmente la migración de capacidad de nombres, en pruebas:

   ```sh
   php artisan migrate --path=database/migrations/2026_10_02_000001_expand_task_filename_metadata.php --force
   ```

   Antes y después, comprobar el tipo y contenido de ambas columnas en tasks. Su rollback no reduce a VARCHAR para evitar truncamientos.
9. Revisar y copiar solo adjuntos legítimos del respaldo; no restaurar scripts PHP de carpetas de fotos/remisiones. El ZIP recibido contiene inclusiones PHP ofuscadas pendientes de análisis. No ejecutar ni restaurar todo ese ZIP.
10. Revisar permisos de los usuarios copiados: nuevas políticas requieren permisos de tareas explícitos. No ejecutar los seeders sobre producción para resolver acceso.
11. Comprobar creación, edición, nombres largos y archivos antiguos/nuevos con perfiles administrador y técnico. Revisar primero el comando php artisan tasks:privatize-files sin --apply; el traslado real se planificará después de comparar referencias y archivos.

## Límites de esta entrega

25 pruebas de regresión y 97 verificaciones pasan con SQLite y carpetas temporales, sin la base de producción. Falta importar y validar la copia MariaDB/MySQL, probar el navegador y revisar los archivos sospechosos y los permisos reales. No activar despliegues de producción hasta completar estas comprobaciones y el plan de reversión.
