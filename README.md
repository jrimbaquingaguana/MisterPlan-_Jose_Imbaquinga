# MisterPlan — Reservas de alojamiento

Aplicación desarrollada para la prueba técnica de MisterPlan. Permite consultar, filtrar, crear, confirmar y cancelar reservas, además de revisar su historial.

Está hecha con PHP nativo, MySQL, Twig y JavaScript vanilla. No necesita npm ni compilación del frontend.

## Requisitos

- PHP 8.1 o superior y Composer 2.
- Extensiones PDO, pdo_mysql, mbstring e iconv.
- Para PHPUnit: dom, libxml, xmlwriter y tokenizer.
- MySQL 8.0.16 o superior, con InnoDB y utf8mb4.
- Cliente MySQL o una herramienta como MySQL Workbench para importar el SQL.
- Docker Compose, si se utiliza la base de datos incluida en el proyecto.

Composer permite comprobar las extensiones y versiones necesarias.

## Instalación local

### 1. Instalar dependencias

Desde la carpeta del proyecto:

```sh
composer install
composer check-platform-reqs
```

### 2. Configurar la conexión

El repositorio incluye un `.env` con valores de desarrollo. Ajusta la conexión a tu instalación:

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

Las contraseñas de este ejemplo son solo para desarrollo. El usuario de conexión debe existir y tener permisos sobre la base de datos.

Las variables del entorno tienen prioridad sobre `.env`. `MYSQL_ROOT_PASSWORD` se utiliza únicamente para inicializar MySQL con Docker Compose.

### 3. Crear la base de datos e importar los ejemplos

Conecta con una cuenta que tenga permisos para crear la base e importar las tablas:

```sh
mysql --host=127.0.0.1 --port=3306 --user=root --password
```

Desde el cliente, y estando en la carpeta del proyecto:

```sql
CREATE DATABASE misterplan_reservas
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE misterplan_reservas;

SOURCE database/misterplan_reservas.sql;
```

También puedes importar el archivo desde MySQL Workbench o phpMyAdmin, seleccionando primero la base de datos.

El SQL contiene las tablas, sus restricciones y datos ficticios: nueve reservas y diecisiete eventos. Incluye instrucciones `DROP TABLE`, por lo que debe importarse en una base nueva: volver a ejecutarlo reemplaza las tablas existentes.

Para ejecutar la aplicación basta con un usuario con permisos `SELECT`, `INSERT` y `UPDATE` sobre esta base.

### Alternativa: MySQL con Docker

El archivo `compose.yaml` levanta únicamente MySQL. PHP se ejecuta en el equipo y el SQL se importa por separado.

En `.env`, utiliza un usuario distinto de `root`. Si el puerto 3306 ya está ocupado, configura:

```dotenv
DB_PORT=3307
```

Arranca el servicio:

```sh
docker compose config --quiet
docker compose up -d mysql
docker compose ps
```

Compose crea la base y el usuario definidos en `.env` al inicializar un volumen nuevo. Después importa el SQL siguiendo el paso anterior, usando el puerto publicado.

PHP conecta a `127.0.0.1:3307` si elegiste ese puerto. Dentro del contenedor, MySQL sigue escuchando en 3306.

Los datos se conservan en un volumen. Cambiar las contraseñas del `.env` después del primer arranque no cambia las del MySQL ya inicializado.

### 4. Arrancar PHP

```sh
php -S 127.0.0.1:8080 -t public public/index.php
```

Abre:

http://127.0.0.1:8080/reservas

El puerto del comando debe coincidir con el que quieras utilizar; `APP_PORT` no inicia el servidor automáticamente.

## Uso

El listado permite combinar estado, fechas y nombre del huésped. También incluye paginación y acceso al detalle de cada reserva.

Con JavaScript, los filtros, la paginación, la creación y los cambios de estado se realizan sin recargar la página. Sin JavaScript se pueden consultar el listado y el detalle, y utilizar los filtros mediante GET.

Las reservas nuevas empiezan en `pendiente`. Las transiciones permitidas son:

- Pendiente a confirmada.
- Pendiente a cancelada.
- Confirmada a cancelada.

Una reserva cancelada no puede reabrirse. Repetir el estado actual devuelve un conflicto y no genera otro evento.

## API

| Método | Ruta | Función |
| --- | --- | --- |
| GET | `/api/health` | Comprobar que PHP responde, sin consultar MySQL |
| GET | `/api/reservas` | Listar y filtrar reservas |
| GET | `/api/reservas/{id}` | Consultar una reserva y su historial |
| POST | `/api/reservas` | Crear una reserva |
| PATCH | `/api/reservas/{id}/estado` | Confirmar o cancelar |

Ejemplo de filtros:

```text
/api/reservas?estado=confirmada&desde=2026-11-01&hasta=2026-11-30&q=Carlos&page=1&per_page=10
```

El tamaño predeterminado es de diez reservas por página y el máximo es cien. El orden es por fecha de entrada descendente y, en caso de empate, por ID descendente.

La búsqueda por nombre trata `%` y `_` como caracteres literales.

El filtro de fechas busca estancias que coincidan con el rango seleccionado. Una reserva que sale el día `desde` queda fuera; una que entra el día `hasta` queda incluida. Ambos límites son opcionales.

Las escrituras reciben un objeto JSON con `Content-Type: application/json`.

Ejemplo de creación:

```json
{
  "nombre_huesped": "María García",
  "email_huesped": "maria@example.com",
  "nombre_alojamiento": "Apartamento Sol",
  "fecha_entrada": "2027-01-10",
  "fecha_salida": "2027-01-12",
  "importe": "180.00",
  "notas": "Llegada por la tarde."
}
```

Para cambiar el estado:

```json
{
  "estado": "confirmada"
}
```

Los principales códigos de respuesta son:

| Código | Significado |
| --- | --- |
| 200 | Consulta o cambio de estado correcto |
| 201 | Reserva creada, con cabecera `Location` |
| 400 | JSON o filtros incorrectos |
| 404 | Ruta o reserva inexistente |
| 405 | Método no permitido |
| 409 | Transición de estado no permitida |
| 413 | Cuerpo superior a 64 KiB |
| 415 | Tipo de contenido no admitido |
| 422 | Datos que no superan la validación |
| 500 | Error interno |

Los errores de validación incluyen un mapa `errors` con los mensajes de cada campo.

## Organización y decisiones

Separé el código en controladores, servicios y repositorios. Los controladores gestionan HTTP, los servicios coordinan las reglas y transacciones, y los repositorios contienen las consultas SQL.

Elegí MySQL porque es la base preferida en el enunciado. InnoDB permite guardar cada reserva y su evento dentro de la misma transacción. En los cambios de estado se utiliza `SELECT ... FOR UPDATE` para comprobar la transición sobre una fila bloqueada.

Los importes se almacenan como `DECIMAL(10,2)` y se devuelven como cadenas para evitar cálculos monetarios con `float`.

La validación del servidor comprueba tipos, longitudes, email, fechas e importe. También rechaza campos que controla el servidor, como el ID o el estado inicial. Se permiten fechas pasadas porque el enunciado no las prohíbe.

Twig utiliza autoescape y JavaScript introduce los datos como texto mediante el DOM. No se utiliza `|raw`. Las consultas PDO usan parámetros preparados y los errores internos no muestran detalles al cliente.

## Pruebas

Validación del proyecto y pruebas unitarias:

```sh
composer validate
composer check-platform-reqs
composer test
```

Las pruebas unitarias no necesitan MySQL. PHPUnit requiere la extensión mbstring nativa habilitada en PHP CLI.

Hay dos comprobaciones adicionales:

```sh
node tests/deploy-smoke.mjs
node tests/ui-phase10.mjs
```

La primera necesita Node 18 o superior y comprueba el arranque HTTP, los recursos y las respuestas de error. La segunda necesita Node 22 o superior y Chrome; utiliza respuestas API simuladas para comprobar la interfaz. Se puede indicar el ejecutable del navegador mediante `CHROME_PATH`.

### Integración con MySQL

```sh
php integration/create-reservas.php
```

Necesita estas variables de entorno:

```text
MISTERPLAN_TEST_DB_HOST
MISTERPLAN_TEST_DB_PORT
MISTERPLAN_TEST_DB_NAME
MISTERPLAN_TEST_DB_USER
MISTERPLAN_TEST_DB_PASSWORD
```

La base debe estar vacía, ser exclusiva para pruebas y terminar en `_test`. No hay que importar el SQL previamente: el script crea y elimina sus tablas.

Estas pruebas comprueban creación, rollback, conflictos y concurrencia. Necesitan permisos para crear y eliminar tablas, `proc_open` y acceso a la información de las conexiones utilizada por el script.

## Despliegue

La URL pública está pendiente.

El servidor debe tener PHP y sus extensiones, MySQL y HTTPS. El directorio público debe apuntar a `public/`, dejando el código, las dependencias y la configuración fuera del acceso web.

Para instalar las dependencias de producción:

```sh
composer install --no-dev --optimize-autoloader
composer check-platform-reqs --no-dev
```

Se incluye `public/.htaccess` para Apache 2.4 con `mod_rewrite`. En otro servidor habrá que configurar el envío de las rutas dinámicas a `public/index.php`.

La aplicación está preparada para publicarse en la raíz de un dominio o subdominio. Las credenciales de producción se configuran mediante variables privadas del hosting, con `APP_ENV=production` y `APP_DEBUG=false`.

Antes de entregar falta comprobar el funcionamiento en el hosting y añadir aquí la URL definitiva.

## Comprobaciones con GitHub Actions

El workflow **PHP checks** está configurado para ejecutarse en cada push y pull request, y también permite ejecución manual. Comprueba Composer y los requisitos de plataforma, instala las dependencias, revisa la sintaxis de los PHP propios versionados y ejecuta las pruebas unitarias con PHP 8.1, sin una base de datos real ni cobertura.

Los resultados y logs se consultan en la pestaña **Actions** del repositorio, seleccionando **PHP checks** y una ejecución. La integración MySQL y las pruebas de navegador se ejecutan aparte. La ejecución real en GitHub queda pendiente hasta subir el workflow.

## Probar la API con Postman

Importa `postman/MisterPlan.postman_collection.json` en Postman y ajusta la variable de colección `base_url` al puerto local utilizado (por defecto, `http://127.0.0.1:8080`, sin barra final). No hace falta importar un entorno ni introducir IDs existentes. Arranca PHP con el comando local anterior y ten disponible MySQL con el esquema importado.

Puedes ejecutar la carpeta **Consultas** o el ciclo **Crear, confirmar y cancelar**, siempre en orden de A a F. El ciclo genera y guarda su propio ID; si falta, las peticiones dependientes se omiten y la consola explica el motivo. Utiliza una versión de Postman que soporte `pm.execution.skipRequest()` y evita variables de entorno con los mismos nombres que las de colección.

Cada ejecución del ciclo crea otra reserva ficticia y la deja cancelada. Úsalo solo en una instancia propia o exclusiva de pruebas; no hay DELETE ni limpieza automática. En **Errores esperados**, los códigos 4xx indicados cuentan como comprobaciones correctas.

## Qué mejoraría con más tiempo

Añadiría autenticación y autorización antes de utilizar datos reales. Actualmente, cualquier persona con acceso a la aplicación puede consultar y modificar reservas.

También incorporaría idempotencia en la creación para evitar duplicados por reintentos, límites de peticiones y migraciones para actualizar el esquema sin reimportarlo. El bloqueo del botón evita dobles envíos desde la interfaz, pero no garantiza que un reintento tras un fallo de red no duplique una reserva.

## Escalabilidad: 1.000 reservas por hora

Son unas 0,28 creaciones por segundo de promedio, aunque los picos y las consultas pueden aumentar bastante la carga.

Primero mediría tiempos de respuesta, consultas lentas, conexiones y bloqueos. Revisaría los planes con `EXPLAIN` y comprobaría los índices con un volumen de datos representativo.

La búsqueda por nombre con `LIKE '%texto%'` merece especial atención al crecer, porque un índice B-tree convencional no permite resolverla como una búsqueda por prefijo.

Después haría pruebas de carga con peticiones concurrentes. Si las mediciones muestran un problema, evaluaría caché o réplicas de lectura, teniendo en cuenta que los resultados deben mantenerse coherentes después de crear o cambiar una reserva.
