# Correcciones de ONETASK

Estado al 5 de octubre de 2026. Copia del código preparada para publicar, por solicitud del usuario, en airlab2024/onetask.com.co y validar en un entorno de pruebas. No se desplegó en producción.

## Error observado en las capturas

Las dos capturas muestran `SQLSTATE[22001] / 1406: Data too long for column original_filenames_fotos_ingreso` al crear una tarea. La migración histórica creó ese campo como VARCHAR(255), pero la aplicación almacena múltiples nombres como JSON. El nombre de documentos `original_filename` tiene el mismo límite.

Se preparó `database/migrations/2026_10_02_000001_expand_task_filename_metadata.php`: amplía ambos campos a LONGTEXT nullable, conserva su contenido y evita una reducción destructiva en rollback. Se mantiene almacenamiento JSON compatible con los casts existentes, sin forzar una conversión del contenido antiguo a una columna JSON.

**Esta migración todavía no se ejecutó en la base de datos del usuario. El error del servidor no se considera resuelto hasta validar y aplicar el cambio en una copia de esa base.**

## Cambios del repositorio incorporados

Se incorporó el contenido del commit remoto `2fdbb0c0`: abreviatura de clientes, migración correspondiente, equipos ZAG/CMG, opciones de recibido por, estilos y modo oscuro. El repositorio original conserva su rama y HEAD. La publicación se prepara en una copia separada del nuevo repositorio, conservando el commit inicial del destino y sin transferir el historial antiguo.

## Correcciones de archivos

- Componente común TaskFileUpload para fotos, informes y remisiones, tanto en el formulario como en las acciones de tabla.
- Archivos nuevos en disco privado `task_files`, fuera de `public/storage`.
- Nombres internos UUID y extensión obtenida del MIME para evitar sobrescrituras de archivos con el mismo nombre.
- Nombre original guardado separadamente mediante el mecanismo nativo `storeFileNamesIn` de Filament.
- Límite por campo de 20 archivos y 10 MiB por archivo; validación de MIME conservada.
- Lectura compatible con listas JSON, rutas simples antiguas y entradas con path/name. Se conservan referencias antiguas aunque falte el archivo físico, evitando borrarlas al editar otros campos.
- Descargas e imágenes servidas por `/tasks/{task}/files/{field}`. Se autoriza la tarea y se comprueba que la ruta pertenece exactamente al campo solicitado.
- Resolución segura de rutas y rechazo de rutas absolutas, secuencias de recorrido de directorios y archivos fuera de las raíces de almacenamiento.
- Enlaces de remisiones e informes conservan su directorio completo; dejan de construir URLs tomando únicamente basename.
- Se eliminaron las rutas anónimas de archivos, sus duplicados y la ruta GET que creaba enlaces simbólicos.
- Se retiró también public/crear_symlink.php, que permitía intentar crear enlaces desde una URL pública.
- Se rechazan referencias de archivos de otra tarea enviadas manipulando el estado de Livewire.
- Preparado el comando `tasks:privatize-files`: por defecto solamente inspecciona; con `--apply` copia, verifica SHA-256 y elimina el original público únicamente cuando la copia coincide. No se ejecutó sobre archivos reales.

**Los archivos antiguos todavía pueden ser públicos por URLs directas a /storage mientras no se migren.** Las URLs antiguas /archivos dejan de ser la interfaz de descarga; los enlaces internos se actualizaron. Antes de desplegar se debe revisar si existen enlaces externos que requieran redirección compatible.

## Tareas y permisos

- TaskPolicy registrada para usar los métodos que realmente ejecuta Filament 2, en lugar de canAccess de páginas que no se estaba aplicando.
- Permisos para listar, ver, crear y editar; las tareas de usuarios normales se limitan a las asignadas. SUPER ADMINISTRADOR conserva acceso administrativo.
- Permisos Edit task y Update task admitidos para edición; borrado restringido al superadministrador.
- Acciones de archivos y edición en celdas autorizadas en el servidor. Un técnico no puede reasignar desde una celda.
- Validación de fechas, IDs relacionados, estados y valores de celdas. Fecha fin no puede preceder a fecha inicio.
- Cero días restantes se distingue de una fecha no definida.
- Casts de fechas y de archivos compatibles con datos antiguos.
- Códigos calculados incluyendo tareas eliminadas para evitar reutilizar su número. **La concurrencia entre creaciones simultáneas sigue pendiente de resolver con secuencia/índice tras revisar los datos reales.**

## Notificaciones

- Se eliminaron hooks ubicados en el recurso que Filament no ejecutaba.
- Notificación de creación movida al evento del modelo, usando la relación y los argumentos correctos.
- Evita avisos de actualización cuando no cambió ningún campo de negocio.
- Los cambios de archivos se leen con sus casts para evitar mostrar JSON crudo.
- Los correos esperan al commit de la transacción. Se evita escribir dos notificaciones de base de datos con formatos incompatibles para el mismo evento.
- Eliminación usa correctamente el nombre del autor y admite ejecuciones sin usuario autenticado.
- Se retiró el evento de recordatorio que confundía días hasta el fin con días hasta el inicio y usaba una ruta inexistente.
- Zona horaria configurable mediante APP_TIMEZONE, por defecto America/Bogota. Programación a las 08:00 de esa zona, sin solapar ejecuciones; tareas entregadas excluidas del aviso de inicio.
- SMTP, worker real e idempotencia de recordatorios todavía requieren pruebas operativas.

## Correcciones generales

- Kanban/Scrum: no permite actualizar un ticket de otro proyecto, requiere permiso de actualización, verifica el estado destino y rechaza posiciones negativas.
- Scrum sin sprint activo devuelve una colección vacía en vez de acceder a una relación inexistente.
- Comentarios: edición y eliminación se restringen al ticket abierto y al autor o administrador del proyecto; se revalida al ejecutar la operación.
- Roadmap y formularios de épicas: validaciones de acceso al proyecto, pertenencia de épica/sprint/estado y orden de fechas.
- Adjuntos de tickets: las operaciones de carga y borrado comprueban permiso de actualización y pertenencia del adjunto.
- HTML enriquecido saneado con el sanitizador ya instalado de Filament. Nombres dinámicos escapados en widgets; colores restringidos a hexadecimal.
- OIDC: state de sesión obligatorio, comparado y consumido; identidad por subject; correo verificado requerido; no convierte automáticamente una cuenta con contraseña; respeta el registro deshabilitado. Persistencia de oidc_sub corregida.
- Exportación de horas incluye el día final completo y precarga relaciones. Fechas invertidas rechazadas.
- Seeder prepara permisos de tareas/clientes y evita dar todos los permisos a un rol predeterminado nuevo. Los privilegios de roles ya existentes no se revocaron sin revisar la base de datos.

## Verificación

Pruebas en `tests/Regression`, configuración `phpunit.regression.xml`.

La suite construye una aplicación mínima sin cargar .env ni el AppServiceProvider del proyecto: SQLite en memoria, archivos temporales verificados y notificaciones simuladas. No usa la base de datos del usuario.

Comando PowerShell:

```powershell
& 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe' vendor/phpunit/phpunit/phpunit -c phpunit.regression.xml --do-not-cache-result
```

La primera revisión completa de sintaxis después de los cambios comprobó 308 archivos PHP sin errores. Resultado final: 25 pruebas y 97 verificaciones correctas, incluyendo la migración privada en carpetas temporales y el renderizado de enlaces de remisiones.

SQLite no reproduce el límite VARCHAR de MySQL: se comprueban la ampliación del tipo y la conservación de metadata mayor de 255 caracteres. Falta validar el cambio sobre la versión MySQL/MariaDB y la estructura del usuario.

## Respaldo de producción recibido

Se inspeccionaron el SQL y el índice del ZIP sin importar ni ejecutar su contenido. El SQL contiene 35 tablas; confirma ambos campos de nombres en VARCHAR(255), la columna clientes.abreviatura y su migración registrada. La migración de ampliación de nombres no está registrada.

En el ZIP aparecen scripts PHP con inclusiones mediante base64_decode dentro de las carpetas de fotos y remisiones. Requieren una revisión de seguridad aislada; no se incorporan a este repositorio. No se ejecutó ni se extrajo el ZIP a una ubicación servida por Laravel o el hosting.

## Pendientes de validación con la base de datos

1. Revisar estructura de tasks y clientes, tipos de todos los campos de archivos, índices, datos JSON antiguos y tabla migrations.
2. Comparar roles/permisos reales con las nuevas políticas, incluyendo clientes y roles predeterminados existentes.
3. Crear una copia aislada y comprobar migraciones de nombres y abreviatura; no usar migrate:fresh sobre los datos existentes.
4. Reproducir creación/edición con múltiples fotos, informes y remisiones desde el navegador; comprobar lectura de archivos antiguos y ausencia de pérdidas.
5. Inspeccionar archivos públicos antes de migrarlos; revisar conflictos, referencias ausentes y archivos compartidos. Las imágenes/documentos previamente sobrescritos requieren copias de seguridad para recuperarse.
6. Validar notificaciones, correo, queue worker, scheduler y configuración de producción; APP_DEBUG debe estar deshabilitado en el servidor público.
7. Resolver generación concurrente de códigos e índices únicos después de identificar duplicados.
8. Reconstruir migraciones faltantes de creación de tasks y columnas de clientes a partir de la estructura real.
9. Revisar Jira: destino de las solicitudes, autorización directa, selección/paginación, pertenencia de proyectos, deduplicación y reintentos. No corregido en esta entrega.
10. Completar revisión de permisos de clientes/timesheets y pruebas de proveedores OIDC reales; la prueba actual verifica rechazo de state incorrecto sin acceso de red.
11. Planificar actualización de Laravel/Filament/Livewire y endurecimiento de Docker/despliegue. No se cambiaron versiones de dependencias ni se instalaron paquetes.

No se ejecutaron migraciones ni movimientos de archivos reales. La publicación solicitada incluye código y pruebas, sin .env, respaldos, adjuntos, logs, datos SQL, vendor ni node_modules. Se preservan los archivos ajenos a esta tarea y los cambios del repositorio original.
