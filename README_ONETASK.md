# ONETASK · Zero Span / AIRLAB

Código Laravel de la plataforma de gestión de tareas, servicio técnico y proyectos, con Filament y Livewire.

Esta copia incorpora las correcciones de gestión de adjuntos, nombres largos, autorización, notificaciones y otros flujos descritas en [CORRECCIONES_ONETASK.md](CORRECCIONES_ONETASK.md).

## Uso inicial

Preparar un entorno de pruebas separado siguiendo [HOSTINGER_PRUEBAS.md](HOSTINGER_PRUEBAS.md). El documento raíz web debe ser public. No configurar esta entrega directamente en la carpeta de producción.

El repositorio conserva composer.lock, package-lock.json y assets compilados. No contiene .env, vendor, node_modules, bases SQL, respaldos ni archivos de clientes.

## Verificación

Después de instalar dependencias de desarrollo en un entorno local:

```sh
php vendor/bin/phpunit -c phpunit.regression.xml
```

Resultado local verificado: 25 pruebas, 97 assertions con PHP 8.3.30. Estas pruebas no utilizan la base real. La validación completa con el respaldo de producción está pendiente.

El README original del proyecto se conserva en README.md junto con sus licencias y créditos.
