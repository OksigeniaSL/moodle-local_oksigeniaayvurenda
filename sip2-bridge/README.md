# SIP2 bridge for Oksigenia Ayvurenda

Self-loan machines, return boxes and RFID pads speak SIP2. This small program lets them work with a library in Ayvurenda.

It runs in the library, on a computer on the same network as the machines: they talk SIP2 to it, in the clear, and it talks HTTPS to Ayvurenda. So what travels unencrypted stays inside the building. It uses nothing but the Python standard library.

A machine is one more **self-loan post**: the same rules, the same limits, and everything it does appears on the post's page, where the staff can undo it.

## Setting it up

1. In Ayvurenda, open **Self-loan** and create a post for each machine, marked as a machine. Copy its address: it is shown only once.
2. Copy `config.example.json` to `config.json`, and put in each machine's user and password (the ones you set on the machine) and the address of its post. Keep this file private: those addresses open the posts. With a single machine that does not log in, leave its `user` out. In `allow`, the addresses of your machines: nothing else on the network gets in. Without it, any device on the network can talk to the bridge, and without a login it can read cards and books like the machine.
3. Start it, with Python 3.9 or later:

        python3 bridge.py config.json

   Or with Docker:

        docker build -t ayvurenda-sip2 .
        docker run -d --restart unless-stopped --name ayvurenda-sip2 -p 6001:6001 -v "$PWD/config.json:/etc/sip2-bridge/config.json:ro" ayvurenda-sip2

4. On the machine, set the address of that computer, port 6001, its user and password, and protocol version 2.00.

Do not open this port to the internet: SIP2 has no encryption. If a machine is in another building, connect them by VPN or stunnel.

A machine can take back up to 60 books a minute and 1,500 an hour; over that, its post stops until someone reopens it on its page. So does reading more than 200 different cards in an hour.

## What it answers

| Message | | |
|---|---|---|
| 93 / 94 | Login | Each machine, with its own post |
| 99 / 98 | Status | Whether the library answers, and what it allows |
| 23 / 24 | Patron status | Whether the card is good, and the first name |
| 63 / 64 | Patron information | The same, and no more |
| 11 / 12 | Checkout | With the post's rules; if the same reader reads it again, the same loan |
| 09 / 10 | Checkin | If the library lets its posts take books back; if someone is waiting for it, the alert says so |
| 17 / 18 | Item information | Title, whether it is in, and when it is due; never who has it |
| 29 / 30 | Renew | If the library lets its readers renew from home |
| 35 / 36 | End session | |
| 97 / 96 | Resend | Sequence and checksum, if the machine uses them |

Anything else gets a 96.

## What it does not do, on purpose

- **It does not say why someone is stopped.** A machine stands in a public room: it does not say whether a reader is late or how much they owe, not even when a card is read, only "please go to the desk" when they try to take a book. Fines are not paid at a machine.
- **It does not say what anyone has.** Neither which books nor how many: what someone reads is theirs.
- **Cards have no password here.** A machine that sends one gets `CQN`: set it not to ask for one.
- **It does not work offline.** If the library cannot be reached, it says so and the machine does not lend. A self-check that stores loans to send later would take them without any rule looking at them.
- **It is not for services that check many cards**, such as e-book platforms: a post stops when it reads too many cards in an hour, as any post does.

## Checking it

    python3 -I -B test_bridge.py

## License

GNU GPL, version 3 or later, like the plugin.
