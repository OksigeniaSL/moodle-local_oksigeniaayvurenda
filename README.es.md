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

1. Copia el plugin en `local/oksigeniaayvurenda` de tu Moodle (de 4.5 a 5.3).
2. Entra en *Administración del sitio > Notificaciones* para terminar la instalación.

## Desarrollo

Código, comentarios y mensajes de commit en inglés; los textos, en los paquetes de idioma (`lang/en`, `lang/es`). Las
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
