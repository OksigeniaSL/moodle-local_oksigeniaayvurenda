# Biblioteca escolar para Moodle (Oksigenia Ayvurenda)

[![Licencia: GPLv3](https://img.shields.io/badge/licencia-GPLv3+-blue.svg)](LICENSE)
![Moodle](https://img.shields.io/badge/Moodle-4.5%E2%80%935.3-orange)
![Estado](https://img.shields.io/badge/estado-prealfa-red)
[![Patrocinio](https://img.shields.io/badge/patrocinio-Oksigenia-00d4ff)](https://oksigenia.com/en/open-source#sponsor)

**Ayvurenda** es una biblioteca escolar para Moodle™ que llevan los bibliotecarios del centro y cuyos lectores son
los usuarios del sitio.

[Read in English](README.md)

## Estado

**Prealfa.** Es la base del plugin, con sus comprobaciones: la biblioteca se construye encima en versiones pequeñas,
cada una probada antes de publicarse. No es para sitios en producción.

## Instalación

1. Copia el plugin en `local/oksigeniaayvurenda` de tu Moodle (de 4.5 a 5.3).
2. Entra en *Administración del sitio > Notificaciones* para terminar la instalación.

## Desarrollo

Código, comentarios y mensajes de commit en inglés; los textos, en los paquetes de idioma (`lang/en`, `lang/es`). Las
pruebas pasan con `moodle-plugin-ci` en GitHub Actions en Moodle 4.5 y 5.3 (PostgreSQL), en 5.3 también con MariaDB
11.4, y en `main`, para ver lo que viene. Más en [CONTRIBUTING.md](CONTRIBUTING.md).

## Licencia

GNU GPL v3 o posterior. Ver [LICENSE](LICENSE).

© 2026 Oksigenia SL — dev@oksigenia.cc

Moodle y sus logotipos son marcas comerciales o marcas registradas de Moodle Pty Ltd o de sus filiales. Oksigenia no
está afiliada a Moodle HQ ni es Moodle Certified Partner.
