# MisterPlan — Reservas de alojamiento

Aplicación para la prueba técnica de MisterPlan, desarrollada con PHP nativo, MySQL, Twig y JavaScript vanilla. Permite consultar, filtrar, crear, confirmar y cancelar reservas, además de revisar su historial.

**Demo publicada:** [Abrir MisterPlan](https://josericardoimbaquingaguana.alwaysdata.net/reservas)

## Índice

- [Tecnologías y requisitos](#tecnologías-y-requisitos)
- [Instalación local](#instalación-local)
- [Funcionalidades y API](#funcionalidades-y-api)
- [Organización y decisiones](#organización-y-decisiones)
- [Pruebas, GitHub Actions y Postman](#pruebas-github-actions-y-postman)
- [Despliegue](#despliegue)
- [Mejoras pendientes y escalabilidad](#mejoras-pendientes-y-escalabilidad)

## Tecnologías y requisitos

- PHP 8.1 o superior y Composer 2.
- PDO, pdo_mysql e iconv. La aplicación incluye un polyfill de mbstring; PHPUnit necesita la extensión mbstring nativa, además de dom, libxml, xml, xmlwriter y tokenizer.
- MySQL 8.0.16 o superior, con InnoDB y utf8mb4. La demo utiliza MariaDB compatible con MySQL.
- Cliente MySQL o una herramienta de importación como MySQL Workbench o phpMyAdmin.
- Docker Compose, solo si se elige la alternativa de MySQL en contenedor.

No se necesita npm para utilizar la aplicación. Node y Chrome solo son necesarios para las pruebas adicionales indicadas más adelante.

## Instalación local

### 1. Clonar e instalar dependencias

```bash
git clone https://github.com/jrimbaquingaguana/MisterPlan-_Jose_Imbaquinga.git
cd MisterPlan-_Jose_Imbaquinga
composer install
composer check-platform-reqs
```

### 2. Configurar el entorno

El `.env` versionado contiene valores locales de demostración. Ajusta la conexión a tu instalación; este es un ejemplo completo para desarrollo:

```dotenv
APP_ENV=dev
APP_DEBUG=false
APP_PORT=8080
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=misterplan_reservas
DB_USER=misterplan_app
DB_PASSWORD=misterplan_local_only
MYSQL_ROOT_PASSWORD=root_local_only
```

Las contraseñas del ejemplo son ficticias y no deben reutilizarse en producción. Las variables reales del entorno tienen prioridad sobre `.env`. `MYSQL_ROOT_PASSWORD` solo se usa para inicializar MySQL con Compose.

### 3. Preparar MySQL

Elige una de estas dos opciones. No se presupone ningún contenedor ni base de datos de otro proyecto.

#### Opción A: MySQL ya instalado

Conecta con una cuenta administradora:

```bash
mysql --host=127.0.0.1 --port=3306 --user=root --password
```

En una instalación nueva, crea la base y el usuario del ejemplo:

```sql
CREATE DATABASE misterplan_reservas
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'misterplan_app'@'127.0.0.1'
  IDENTIFIED BY 'misterplan_local_only';
GRANT SELECT, INSERT, UPDATE ON misterplan_reservas.*
  TO 'misterplan_app'@'127.0.0.1';
```

Si ya tienes un usuario de aplicación, utiliza sus credenciales en `.env` y concédele esos permisos sobre la base propia. La importación del siguiente paso se realiza con la cuenta administradora, que también necesita permisos para crear y reemplazar tablas y ejecutar `LOCK TABLES`.

#### Opción B: MySQL con Compose

`compose.yaml` levanta únicamente MySQL 8.4. Al inicializar un volumen nuevo crea la base y el usuario definidos por `DB_NAME`, `DB_USER` y `DB_PASSWORD`, y configura la contraseña root con `MYSQL_ROOT_PASSWORD`. Usa un `DB_USER` distinto de root, como el del ejemplo anterior.

El archivo actual publica MySQL en `127.0.0.0:${DB_PORT}`. Para esta opción, cambia `DB_HOST` a `127.0.0.0` en `.env`; `DB_PORT=3306` es el puerto publicado del ejemplo. Si está ocupado, elige otro libre, como 3307.

```bash
docker compose config --quiet
docker compose up -d mysql
docker compose ps
```

PHP se ejecuta fuera del contenedor y conecta a la dirección y puerto publicados. Dentro del contenedor MySQL escucha en 3306. Compose no importa el SQL automáticamente.

Los datos persisten en el volumen `mysql_data`. Cambiar las credenciales del `.env` después de inicializarlo no modifica los usuarios existentes; no es necesario borrar el volumen para actualizar el código.

### 4. Importar tablas y datos ficticios

Desde la raíz del proyecto, conecta a la base recién creada:

```bash
mysql --host=127.0.0.1 --port=3306 --user=root --password misterplan_reservas
```

Si utilizas Compose, sustituye el host por `127.0.0.0` y el puerto por el valor de `DB_PORT`; introduce la contraseña `MYSQL_ROOT_PASSWORD`. En el cliente:

```sql
SOURCE database/misterplan_reservas.sql;
```

También puedes importar ese archivo desde una herramienta gráfica seleccionando la base de destino. Incluye tablas, restricciones, nueve reservas ficticias y diecisiete eventos. Contiene `DROP TABLE`: volver a importarlo reemplaza las tablas existentes, por lo que debe usarse en una base nueva y vacía.

### 5. Arrancar PHP

```bash
php -S 127.0.0.1:8080 -t public public/index.php
```

Abre [el listado local](http://127.0.0.1:8080/reservas). `APP_PORT` no arranca el servidor: el puerto efectivo es el indicado en el comando. Este servidor es solo para desarrollo.

## Funcionalidades y API

El listado combina filtros por estado, fechas y nombre del huésped, con paginación y acceso al detalle. Con JavaScript, las consultas, la creación y los cambios de estado funcionan sin recargar la página. Sin JavaScript se pueden consultar listado y detalle, filtrar y paginar mediante GET; la creación muestra un aviso de que requiere JS.

Las reservas se crean en estado `pendiente`. Se permite pasar de pendiente a confirmada o cancelada, y de confirmada a cancelada. Cancelada es un estado terminal. Repetir el estado actual o intentar una transición no permitida devuelve 409 sin añadir eventos.

| Método | Ruta | Respuesta principal |
| --- | --- | --- |
| GET | `/api/health` | 200; comprueba PHP, sin consultar la base de datos |
| GET | `/api/reservas` | 200; `data` y metadatos de paginación en `meta` |
| GET | `/api/reservas/{id}` | 200; reserva e historial en `data` |
| POST | `/api/reservas` | 201; reserva creada y cabecera `Location` |
| PATCH | `/api/reservas/{id}/estado` | 200; detalle actualizado |

Ejemplo de filtros:

```text
/api/reservas?estado=confirmada&desde=2026-11-01&hasta=2026-11-30&q=Prueba&page=1&per_page=10
```

El tamaño predeterminado es 10 y el máximo 100. El orden es fecha de entrada descendente e ID descendente. La búsqueda por nombre trata `%` y `_` como caracteres literales.

El filtro de fechas selecciona estancias que se solapan con el rango: `fecha_salida > desde` y `fecha_entrada <= hasta`. Una estancia que termina en `desde` queda fuera; una que comienza en `hasta` queda incluida. Ambos límites son opcionales y, si se usan juntos, `hasta` debe ser igual o posterior a `desde`.

Las escrituras reciben un objeto JSON con `Content-Type: application/json`. Ejemplo de creación:

```json
{
  "nombre_huesped": "Huésped de prueba",
  "email_huesped": "prueba@example.com",
  "nombre_alojamiento": "Apartamento de prueba",
  "fecha_entrada": "2027-01-10",
  "fecha_salida": "2027-01-12",
  "importe": "180.00",
  "notas": "Llegada por la tarde."
}
```

Para confirmar, enviar `{"estado":"confirmada"}` al endpoint de estado; para cancelar, `{"estado":"cancelada"}`.

Los errores devuelven `error` y, cuando corresponde, un mapa `errors` por campo. Los códigos son 400 para JSON o filtros incorrectos, 404 para ruta/reserva inexistente o ID inválido, 405 para método no permitido, 409 para conflictos, 413 para cuerpos mayores de 64 KiB, 415 para tipo de contenido incorrecto, 422 para validación y 500 para errores internos.

## Organización y decisiones

La estructura sigue **Controller → Service → Repository**: HTTP en los controladores, reglas y transacciones en los servicios, y consultas PDO en los repositorios. Las vistas están en `templates/` y los recursos CSS y JS en `public/assets/`.

MySQL con InnoDB permite guardar la reserva y su evento dentro de una misma transacción. Los cambios de estado utilizan `SELECT ... FOR UPDATE` para comprobar la transición sobre una fila bloqueada; si falla alguna escritura, se revierte la operación completa.

Los importes se almacenan como `DECIMAL(10,2)` y se normalizan y devuelven como cadenas, evitando cálculos monetarios con float en el servidor. La validación comprueba tipos, longitudes, email, fechas e importe, y rechaza campos controlados por el servidor. Se admiten fechas pasadas.

PDO utiliza consultas preparadas. Twig aplica autoescape y JavaScript inserta datos como texto mediante DOM. Los errores internos no muestran detalles SQL ni credenciales al cliente.

## Pruebas, GitHub Actions y Postman

### Unitarias

```bash
composer validate
composer check-platform-reqs
composer test
```

Las pruebas unitarias usan dobles de PDO y no necesitan una base real. PHPUnit requiere mbstring nativa habilitada en el PHP de consola.

### Integración MySQL

```bash
php integration/create-reservas.php
```

Configura estas variables reales del proceso; el script no las lee de `.env`:

```text
MISTERPLAN_TEST_DB_HOST
MISTERPLAN_TEST_DB_PORT
MISTERPLAN_TEST_DB_NAME
MISTERPLAN_TEST_DB_USER
MISTERPLAN_TEST_DB_PASSWORD
```

La base debe estar vacía, ser exclusiva de pruebas y terminar en `_test`. No importes el SQL antes: el script crea y elimina sus tablas. Comprueba creación, rollback, conflictos y concurrencia; necesita permisos DDL/DML, `proc_open` y acceso a la información de sus conexiones. No se da por verificada esta integración en el hosting publicado.

### HTTP y navegador

```bash
node tests/deploy-smoke.mjs
node tests/ui-phase10.mjs
```

La comprobación HTTP necesita Node 18+ y PHP; revisa el arranque, los recursos y errores sin una base real. La prueba de interfaz necesita Node 22+, PHP y Chrome, y utiliza respuestas API simuladas. `CHROME_PATH` permite indicar el ejecutable del navegador. Estas pruebas se ejecutan aparte de las unitarias y no sustituyen la revisión manual con teclado y zoom.

### GitHub Actions

El workflow [PHP checks](.github/workflows/php-checks.yml) se ejecuta en push, pull request y de forma manual. Usa PHP 8.1 y Composer 2 para validar Composer, instalar dependencias, comprobar requisitos y sintaxis PHP y ejecutar las unitarias, sin cobertura ni servicio MySQL.

Ya dispone de una ejecución correcta. Los resultados y logs se consultan en [Actions del repositorio](https://github.com/jrimbaquingaguana/MisterPlan-_Jose_Imbaquinga/actions), seleccionando **PHP checks**. La integración MySQL y las pruebas de navegador se ejecutan por separado.

### Postman

Importa [MisterPlan.postman_collection.json](postman/MisterPlan.postman_collection.json) y ajusta `base_url` al servidor local, por defecto `http://127.0.0.1:8080`, sin barra final. Ten PHP y MySQL disponibles. No hace falta un archivo de entorno separado ni introducir IDs existentes.

Ejecuta las consultas o el ciclo **Crear, confirmar y cancelar** en orden de A a F. Cada ciclo crea una reserva ficticia y la deja cancelada; úsalo solo en una instancia propia o de pruebas. No hay DELETE ni limpieza automática. Si falta un ID válido, las peticiones dependientes se omiten; los 4xx de **Errores esperados** cuentan como resultados correctos.

## Despliegue

La demo está publicada en **alwaysdata**, con **PHP 8.4** y **MariaDB compatible con MySQL**. Las dependencias de producción se instalaron con:

```bash
composer install --no-dev --optimize-autoloader
```

El document root es `public/`, con el código, dependencias y configuración fuera del acceso web. Se incluye `public/.htaccess` para dirigir las rutas dinámicas a `index.php` mediante Apache/mod_rewrite, conservando los archivos estáticos.

La configuración actual utiliza un `.env` privado del servidor con `APP_ENV=production` y `APP_DEBUG=false`. Es distinto del `.env` local versionado y contiene la conexión propia del proveedor; sus credenciales no forman parte de esta documentación. La aplicación usa rutas desde la raíz del dominio.

Para actualizar el código se instalan las dependencias correspondientes sin reimportar el SQL. La publicación de la dirección no implica que se haya auditado o verificado todo el flujo público.

## Mejoras pendientes y escalabilidad

La aplicación no incluye autenticación ni autorización: cualquier persona con acceso puede consultar y modificar reservas. La demo debe utilizar exclusivamente datos ficticios. Antes de utilizar datos reales, la prioridad es incorporar control de acceso.

Otras mejoras son la idempotencia de creación para evitar duplicados por reintentos, las migraciones incrementales y los límites de peticiones. Deshabilitar el botón durante un envío evita dobles clics, pero no resuelve un reintento después de una respuesta de red ambigua.

### 1.000 reservas por hora

Son unas 0,28 creaciones por segundo de promedio. Los picos, las consultas del listado y la concurrencia sobre una misma reserva pueden generar una carga bastante mayor.

Primero mediría latencias, consultas lentas, conexiones y bloqueos. Revisaría los planes con `EXPLAIN` y la utilidad de los índices con datos representativos, especialmente en listado/COUNT y en la búsqueda `LIKE '%texto%'`, que no aprovecha un B-tree como una búsqueda por prefijo.

Después realizaría pruebas de carga con picos y cambios simultáneos. Solo si las mediciones lo justifican evaluaría cachés o réplicas, considerando la coherencia de los resultados tras crear o cambiar una reserva.
