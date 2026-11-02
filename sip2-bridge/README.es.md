# Puente SIP2 para Oksigenia Ayvurenda

Las máquinas de autopréstamo, los buzones de devolución y los lectores RFID hablan SIP2. Este programa pequeño hace que funcionen con una biblioteca en Ayvurenda.

Corre en la biblioteca, en un ordenador de la misma red que las máquinas: ellas le hablan en SIP2, sin cifrar, y él habla por HTTPS con Ayvurenda. Así, lo que viaja sin cifrar no sale del edificio. No usa nada más que la biblioteca estándar de Python.

Una máquina es un **puesto de autopréstamo** más: las mismas normas, los mismos límites, y todo lo que hace sale en la página del puesto, donde el personal puede deshacerlo.

## Cómo se monta

1. En Ayvurenda, abre **Autopréstamo** y crea un puesto para cada máquina, marcado como máquina. Copia su dirección: solo se enseña una vez.
2. Copia `config.example.json` a `config.json` y pon el usuario y la contraseña de cada máquina (los que le pongas en la máquina) y la dirección de su puesto. Guarda bien este fichero: esas direcciones abren los puestos. Con una sola máquina que no entra con usuario, quita su `user`. En `allow`, las direcciones de tus máquinas: nada más de la red entra. Sin él, cualquier aparato de la red puede hablar con el puente y, sin usuario, leer carnés y libros como la máquina.
3. Arráncalo, con Python 3.9 o posterior:

        python3 bridge.py config.json

   O con Docker:

        docker build -t ayvurenda-sip2 .
        docker run -d --restart unless-stopped --name ayvurenda-sip2 -p 6001:6001 -v "$PWD/config.json:/etc/sip2-bridge/config.json:ro" ayvurenda-sip2

4. En la máquina, pon la dirección de ese ordenador, el puerto 6001, su usuario y su contraseña, y la versión 2.00 del protocolo.

No abras este puerto a internet: SIP2 no lleva cifrado. Si una máquina está en otro edificio, únelos con una VPN o con stunnel.

Una máquina recoge hasta 60 libros por minuto y 1.500 por hora; si pasa de ahí, su puesto se para hasta que alguien lo abra desde su página. Lo mismo si lee más de 200 carnés distintos en una hora.

## Lo que contesta

| Mensaje | | |
|---|---|---|
| 93 / 94 | Entrada | Cada máquina, con su puesto |
| 99 / 98 | Estado | Si la biblioteca contesta y qué deja hacer |
| 23 / 24 | Estado del lector | Si el carné vale, y su nombre de pila |
| 63 / 64 | Datos del lector | Lo mismo, y nada más |
| 11 / 12 | Préstamo | Con las normas del puesto; si lo lee otra vez el mismo lector, es el mismo préstamo |
| 09 / 10 | Devolución | Si la biblioteca deja que sus puestos recojan libros; si alguien lo espera, el aviso lo dice |
| 17 / 18 | Datos del libro | Título, si está y cuándo vuelve; nunca quién lo tiene |
| 29 / 30 | Ampliar | Si la biblioteca deja ampliar desde casa |
| 35 / 36 | Fin de la visita | |
| 97 / 96 | Repetir | Secuencia y suma de control, si la máquina las usa |

A lo demás contesta con un 96.

## Lo que no hace, a propósito

- **No dice por qué se para a alguien.** Una máquina está en una sala pública: no dice si un lector va con retraso ni cuánto debe, ni siquiera al leer el carné; solo «pasa por el mostrador» al intentar llevarse un libro. Las multas no se pagan en una máquina.
- **No dice qué tiene nadie.** Ni cuáles ni cuántos: lo que alguien lee es suyo.
- **Los carnés no llevan contraseña.** A una máquina que la mande le contesta `CQN`: configúrala para que no la pida.
- **No funciona sin conexión.** Si no llega a la biblioteca, lo dice y la máquina no presta. Una máquina que guarda préstamos para mandarlos después los haría sin que ninguna norma los mire.
- **No es para servicios que comprueban muchos carnés**, como las plataformas de libros electrónicos: un puesto se para al leer demasiados carnés en una hora, como cualquier puesto.

## Para comprobarlo

    python3 -I -B test_bridge.py

## Licencia

GNU GPL, versión 3 o posterior, como el plugin.
