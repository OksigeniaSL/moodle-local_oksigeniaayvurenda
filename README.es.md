# Oksigenia Ayvurenda: gestión de bibliotecas en línea

[![Licencia: GPLv3](https://img.shields.io/badge/licencia-GPLv3+-blue.svg)](LICENSE)
![Moodle](https://img.shields.io/badge/Moodle-4.5%E2%80%935.3-orange)
![Estado](https://img.shields.io/badge/estado-beta-yellow)
[![Patrocinio](https://img.shields.io/badge/patrocinio-Oksigenia-00d4ff)](https://oksigenia.com/en/open-source#sponsor)

**Ayvurenda** es la gestión de bibliotecas en línea, para cualquier biblioteca: el mostrador, el catálogo, el carné de cada
lector y una biblioteca que se visita. Este es su plugin para Moodle™, donde los lectores son los usuarios del sitio.

Desarrollado por [Oksigenia](https://oksigenia.com).

[Read in English](README.md)

## Estado

**Beta.** En uso a diario en una biblioteca, y la base del servicio alojado de [ayvurenda.com](https://ayvurenda.com).
Crece en versiones pequeñas, cada una probada antes de publicarse; lo que trae cada una está en el [CHANGELOG](CHANGELOG.md).

## Instalación

**Lo que necesita.** Moodle de 4.5 a 5.3, con el PHP y la base de datos que pide el propio Moodle (se prueba en
PostgreSQL y MariaDB, y funciona en MySQL 8), y el cron de Moodle cada minuto: el trabajo de noche de la biblioteca va
por él.

1. Copia el plugin en `local/oksigeniaayvurenda` (o en `public/local/oksigeniaayvurenda` si tu Moodle tiene la carpeta `public`), o instala
   el zip desde *Administración del sitio > Extensiones > Instalar complementos*.
2. Entra en *Administración del sitio > Notificaciones* para terminar la instalación.
3. Abre `/local/oksigeniaayvurenda/libraries.php`: crea el centro y su primera biblioteca. Desde ahí, la biblioteca te
   lleva a traer su catálogo (MARC 21 o UNIMARC de cualquier programa, una hoja de cálculo o ISBN a ISBN) y sus
   lectores (cohortes o cursos de Moodle, una lista, OneRoster, LTI 1.3 desde otra plataforma).

**Quién hace qué.** Cinco permisos, dados en el sistema o, en un sitio que comparten varios centros, en la categoría de
cada centro: `view` (los lectores), `desk` (prestar y devolver), `catalogue` (dar de alta y corregir libros), `manage`
(los ajustes del centro, sus copias y sus lectores) y `plans` (los planes de un servicio alojado; nadie lo tiene de
serie).

**Cada noche** (tareas programadas): los lectores al día desde donde vienen (`sync_readers`), los avisos de retraso y de
reservas a las ocho de la hora de cada centro (`send_notices`, cada hora), la copia diaria (`make_copies`) y los
préstamos de cursos antiguos que dejan de decir quién se los llevó (`forget_history`).

**La copia diaria** va en formatos abiertos (MARC 21, Dublin Core, RIS, CSV y una carpeta lista para Koha), para que el
centro la baje, y puede salir además del servidor cifrada con la llave del propio centro (WebDAV, S3 o Google Drive). Un
centro se recupera desde ella, con sus ficheros, en cualquier Moodle con este plugin.

**Ajustes** (*Administración del sitio > Extensiones > Extensiones locales > Biblioteca (Oksigenia Ayvurenda)*): las direcciones de la
red del propio sitio a las que puede llegar un conector o una copia fuera del servidor (`internalhosts`,
`offsitehosts`), un puente Z39.50 para los catálogos que solo hablan Z39.50, un cliente de Google para las copias en
Drive, un sitio que es solo una biblioteca y una categoría por centro para un sitio que comparten varios.

**Opcional.** El bloque `block_oksigeniaayvurenda` enseña a cada lector su biblioteca en su área personal. Las
ilustraciones, los dibujos y las voces de los personajes, la visita y el carné ilustrado van en un paquete de arte aparte
del servicio alojado: sin él, la biblioteca mantiene su aspecto dibujado y todo funciona. La app de Moodle enseña la
biblioteca (préstamos, reservas, búsqueda y el carné) cuando el sitio tiene encendido el servicio móvil.

**Por consola** (`cli/`): una biblioteca inventada para probarla (`demo.php`), importaciones (`import_catalogue.php`,
`import_abies.php`), dar de alta o quitar un centro (`add_entity.php`, `centre.php`, `remove_entity.php`), los préstamos
apuntados en papel (`paper_loans.php`) y los ficheros de un centro recuperado (`recover_files.php`). Cada uno dice cómo
con `--help`.

**Actualizar** es poner la carpeta nueva en lugar de la vieja y entrar en la página de notificaciones; cada versión se
actualiza desde cualquiera anterior.

## Desarrollo

Código, comentarios y mensajes de commit en inglés; los textos, en los paquetes de idioma (`lang/en`, `lang/es`, y `lang/pt_br`, `lang/pt` y `lang/fr`, traducidos a máquina del español y revisados). Las
pruebas pasan con `moodle-plugin-ci` en GitHub Actions en Moodle 4.5 y 5.3 (PostgreSQL), en 5.3 también con MariaDB
11.4, y en `main`, para ver lo que viene. Más en [CONTRIBUTING.md](CONTRIBUTING.md).

## Autoría y procedencia

Ayvurenda lo diseña y lo desarrolla Oksigenia SL. Cada versión sellada lleva, en [`provenance/`](provenance/), un
manifiesto del código con su prueba de fecha y una etiqueta firmada por Oksigenia, que cualquiera puede comprobar con
`provenance/verify.sh`.

## Licencia

GNU GPL v3 o posterior. Ver [LICENSE](LICENSE).

© 2026 Oksigenia SL — dev@oksigenia.cc

Moodle y sus logotipos son marcas comerciales o marcas registradas de Moodle Pty Ltd o de sus filiales. Oksigenia no
está afiliada a Moodle HQ ni es Moodle Certified Partner.
