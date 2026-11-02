#!/usr/bin/env python3
# This file is part of Oksigenia Ayvurenda, under the GNU GPL, version 3 or later.
"""A bridge between SIP2 machines and a library in Ayvurenda.

Self-loan machines, return boxes and RFID pads speak SIP2 over plain TCP, on the library's own network. This program
listens to them there and asks the library over HTTPS, with the address of one of its self-loan posts. So what travels
in the clear stays in the building, and a machine is one more post: the same rules, limits and record.

Nothing but the Python standard library. See README.md.
"""

import argparse
import asyncio
import hmac
import json
import logging
import re
import sys
import time
import urllib.error
import urllib.parse
import urllib.request

log = logging.getLogger('sip2-bridge')

#: The longest message a machine may send.
MAX_MESSAGE = 4096
#: How long a machine may stay silent before it is let go, in seconds.
IDLE = 3600
#: How long the library has to answer, in seconds.
ANSWER = 15
#: How many machines at once.
MAX_MACHINES = 32
#: How many connections from one address: a few, so that one device cannot hold every place.
MAX_PER_ADDRESS = 4
#: How long a connection may stay without logging in, in seconds.
LOGIN_WAIT = 30
#: How long a machine has to take an answer, in seconds.
SEND_WAIT = 30
#: How many wrong logins before a connection is let go.
LOGIN_TRIES = 3
#: The protocol this speaks.
VERSION = '2.00'
#: What each message carries before its fields, in characters.
FIXED = {'93': 2, '99': 8, '23': 21, '63': 31, '11': 38, '09': 37, '17': 18, '29': 38, '35': 18, '97': 0}
#: What this answers, in the order of the «supported messages» field of the status answer: patron status, checkout, checkin,
#: block patron, status, resend, login, patron information, end session, fee paid, item information, item status update,
#: patron enable, hold, renew, renew all.
SUPPORTED = 'YYYNYYYYYNYNNNYN'
#: The end of a message that asks for error detection.
TAIL = re.compile(r'(?:AY(\d))?AZ([0-9A-Fa-f]{4})\s*$')


def checksum(message, encoding='utf-8'):
    """The four hexadecimal figures that make the bytes of a message add up to zero: the bytes as they travel."""
    return format(-sum(message.encode(encoding, 'replace')) & 0xFFFF, '04X')


def valid(message, encoding='utf-8'):
    """Whether a message's checksum, if it has one, is right. A card may hold the letters AZ: only the very end counts."""
    tail = TAIL.search(message)
    if not tail:
        return True
    return tail.group(2).upper() == checksum(message[:tail.start(2)], encoding)


def parse(message):
    """A message's code, fixed part, fields, and whether and how it asks for error detection. The first of a repeated field."""
    code = message[:2]
    size = FIXED.get(code, 0)
    fixed = message[2:2 + size]
    tail = TAIL.search(message)
    body = message[2 + size:tail.start()] if tail else message[2 + size:]
    fields = {}
    for piece in body.split('|'):
        if len(piece) >= 2:
            fields.setdefault(piece[:2], piece[2:])
    return code, fixed, fields, (tail is not None), (tail.group(1) if tail else None)


def clean(text):
    """A field's value: what would break the message goes away."""
    return ' '.join(str(text).replace('|', ' ').split())


def field(tag, value):
    """One field, with its bar."""
    return tag + clean(value) + '|'


def now():
    """The date as the protocol writes it: year, month, day, four spaces and the hour."""
    return time.strftime('%Y%m%d    %H%M%S')


def due(said):
    """The field of a due date, as the library writes it, at its own hour: it keeps its four spaces."""
    return 'AH' + str(said.get('due') or '').replace('|', ' ') + '|'


def flag(value):
    """Yes or no, as most fixed parts say it."""
    return 'Y' if value else 'N'


def status_flags(flags):
    """The fourteen places of a patron's status: a space when all is well. Only the first is ever used, for the reasons of
    a public room: why someone is stopped is for the desk."""
    return ('Y' if flags.get('denied') else ' ') + ' ' * 13


class Library:
    """One post of a library, asked over HTTPS."""

    def __init__(self, post):
        address = urllib.parse.urlsplit(post)
        key = urllib.parse.parse_qs(address.query).get('key', [''])[0]
        if address.scheme not in ('https', 'http') or not key:
            raise ValueError('that is not the address of a post')
        self.key = key
        self.url = urllib.parse.urlunsplit((address.scheme, address.netloc,
                                            address.path.rsplit('/', 1)[0] + '/sip_ajax.php', '', ''))

    def ask(self, op, **values):
        """What the library says; raises when it cannot be reached."""
        data = urllib.parse.urlencode({'key': self.key, 'op': op, **values}).encode()
        request = urllib.request.Request(self.url, data=data, method='POST',
                                         headers={'User-Agent': 'sip2-bridge Ayvurenda'})
        with urllib.request.urlopen(request, timeout=ANSWER) as response:
            return json.loads(response.read().decode('utf-8'))


class Machine:
    """What one machine has said so far."""

    def __init__(self, config):
        self.config = config
        self.library = None
        self.last = ''
        self.wrong = 0
        # With a single post and no name for it, the machine does not have to log in.
        if len(config['logins']) == 1 and not config['logins'][0].get('user'):
            self.library = config['libraries'][0]

    async def ask(self, op, **values):
        """The library's answer, or None when it cannot be reached."""
        if not self.library:
            return None
        try:
            return await asyncio.to_thread(self.library.ask, op, **values)
        except (OSError, ValueError, urllib.error.URLError) as error:
            log.warning('the library does not answer: %s', type(error).__name__)
            return None

    async def answer(self, message):
        """The answer to one message, without its sequence or checksum."""
        code, fixed, fields, _, _ = parse(message)
        handler = getattr(self, 'on_' + code, None)
        if handler is None:
            log.info('message %s is not spoken here', code)
            return '96'
        return await handler(fixed, fields)

    async def on_93(self, fixed, fields):
        """Login."""
        user = fields.get('CN', '').encode()
        password = fields.get('CO', '').encode()
        for index, login in enumerate(self.config['logins']):
            if login.get('user') and hmac.compare_digest(login['user'].encode(), user) \
                    and hmac.compare_digest(login.get('password', '').encode(), password):
                self.library = self.config['libraries'][index]
                return '941'
        self.wrong += 1
        return '940'

    async def on_99(self, fixed, fields):
        """The machine says it is there: what it can do here."""
        said = await self.ask('status') or {}
        online = bool(said.get('online'))
        out = '98' + flag(online) + flag(online and said.get('checkin')) + flag(online and said.get('checkout'))
        # The machine waits twenty seconds (in tenths, by verificar on a machine): the library may take fifteen to answer.
        out += flag(online and said.get('renew')) + 'N' + 'N' + '200' + '003' + now() + VERSION
        out += field('AO', said.get('institution', '')) + field('AM', said.get('name', '')) + field('BX', SUPPORTED)
        return out + ('' if online else field('AF', self.config['offline']))

    async def patron(self, fields):
        """What the library says of a card."""
        said = await self.ask('patron', patron=fields.get('AA', ''))
        return said or {'valid': False, 'message': self.config['offline'], 'flags': {'denied': True}}

    @staticmethod
    def patron_fields(said, fields):
        """The fields of a patron's answers. A card here has no password: one sent cannot be right, and none is fine."""
        out = field('AO', fields.get('AO', '')) + field('AA', fields.get('AA', ''))
        out += field('AE', said.get('name', '')) + field('BL', flag(said.get('valid')))
        out += field('CQ', flag(said.get('valid') and not fields.get('AD', '')))
        return out + (field('AF', said['message']) if said.get('message') else '')

    async def on_23(self, fixed, fields):
        """Patron status."""
        said = await self.patron(fields)
        return '24' + status_flags(said.get('flags', {})) + '000' + now() + self.patron_fields(said, fields)

    async def on_63(self, fixed, fields):
        """Patron information: only whether the card is good. How many books someone has, and which, stay theirs."""
        said = await self.patron(fields)
        out = '64' + status_flags(said.get('flags', {})) + '000' + now() + '0000' * 6
        return out + self.patron_fields(said, fields)

    def said_of_book(self, said):
        """The library's answer about a loan, or what to say without it."""
        return said or {'ok': False, 'message': self.config['offline']}

    async def on_11(self, fixed, fields):
        """A book goes out."""
        said = self.said_of_book(await self.ask('checkout', patron=fields.get('AA', ''), item=fields.get('AB', '')))
        good = bool(said.get('ok'))
        out = '12' + ('1' if good else '0') + 'N' + 'U' + flag(good) + now()
        out += field('AO', fields.get('AO', '')) + field('AA', fields.get('AA', '')) + field('AB', fields.get('AB', ''))
        out += field('AJ', said.get('title', '')) + due(said)
        return out + (field('AF', said['message']) if said.get('message') else '')

    async def on_09(self, fixed, fields):
        """A book comes back. With someone waiting for it, the machine is told to set it apart."""
        said = self.said_of_book(await self.ask('checkin', item=fields.get('AB', '')))
        good = bool(said.get('ok'))
        out = '10' + ('1' if good else '0') + flag(good) + 'U' + flag(said.get('alert')) + now()
        out += field('AO', fields.get('AO', '')) + field('AB', fields.get('AB', '')) + field('AQ', fields.get('AO', ''))
        out += field('AJ', said.get('title', ''))
        out += field('CV', '01') if said.get('alert') else ''
        return out + (field('AF', said['message']) if said.get('message') else '')

    async def on_17(self, fixed, fields):
        """What is known of a book."""
        said = await self.ask('item', item=fields.get('AB', '')) or {'message': self.config['offline']}
        out = '18' + said.get('circulation', '01') + '00' + '01' + now()
        out += field('AB', fields.get('AB', '')) + field('AJ', said.get('title', ''))
        out += due(said) if said.get('due') else ''
        return out + (field('AF', said['message']) if said.get('message') else '')

    async def on_29(self, fixed, fields):
        """More time for a book."""
        said = self.said_of_book(await self.ask('renew', patron=fields.get('AA', ''), item=fields.get('AB', '')))
        good = bool(said.get('ok'))
        out = '30' + ('1' if good else '0') + flag(good) + 'U' + 'U' + now()
        out += field('AO', fields.get('AO', '')) + field('AA', fields.get('AA', '')) + field('AB', fields.get('AB', ''))
        out += field('AJ', said.get('title', '')) + due(said)
        return out + (field('AF', said['message']) if said.get('message') else '')

    async def on_35(self, fixed, fields):
        """The reader has finished."""
        return '36Y' + now() + field('AO', fields.get('AO', '')) + field('AA', fields.get('AA', ''))


async def respond(message, machine):
    """The whole answer to a message; None to let the machine go."""
    code, _, _, detect, sequence = parse(message)
    encoding = machine.config['encoding']
    if not valid(message, encoding):
        answer = '96'
    elif code == '97':
        return machine.last or '96'
    elif not machine.library and code not in ('93', '99'):
        log.info('message %s before logging in: the machine is let go', code)
        return None
    else:
        answer = await machine.answer(message)
    if detect:
        answer += ('AY' + sequence if sequence is not None else '') + 'AZ'
        answer += checksum(answer, encoding)
    if not answer.startswith('96'):
        machine.last = answer
    log.info('%s -> %s', code, answer[:2])
    return answer


async def serve(reader, writer, config, room, here):
    """One machine, until it goes. Only the addresses allowed; a few from each; none for long without logging in."""
    peer = (writer.get_extra_info('peername') or ('', 0))[0]
    allowed = config.get('allow')
    if room.locked() or here.get(peer, 0) >= MAX_PER_ADDRESS or (allowed and peer not in allowed):
        writer.close()
        return
    here[peer] = here.get(peer, 0) + 1
    machine = Machine(config)
    buffer = b''
    async with room:
        try:
            while True:
                chunk = await asyncio.wait_for(reader.read(1024), IDLE if machine.library else LOGIN_WAIT)
                if not chunk:
                    return
                buffer += chunk
                while True:
                    ends = [at for at in (buffer.find(b'\r'), buffer.find(b'\n')) if at >= 0]
                    if not ends:
                        break
                    raw, buffer = buffer[:min(ends)], buffer[min(ends) + 1:]
                    if not raw.strip():
                        continue
                    text = await respond(raw.decode(config['encoding'], 'replace').lstrip('\x00 '), machine)
                    if text is None:
                        return
                    writer.write(text.encode(config['encoding'], 'replace') + b'\r')
                    await asyncio.wait_for(writer.drain(), SEND_WAIT)
                    if machine.wrong >= LOGIN_TRIES:
                        log.warning('too many wrong logins: the machine is let go')
                        return
                if len(buffer) > MAX_MESSAGE:
                    log.warning('a message too long: the machine is let go')
                    return
        except (asyncio.TimeoutError, ConnectionError):
            return
        finally:
            here[peer] -= 1
            if not here[peer]:
                del here[peer]
            writer.close()


def load(path):
    """The settings, with what the library needs ready."""
    with open(path, encoding='utf-8') as file:
        config = json.load(file)
    config.setdefault('host', '0.0.0.0')
    config.setdefault('port', 6001)
    config.setdefault('encoding', 'utf-8')
    config.setdefault('offline', 'No hay conexión con la biblioteca. Pregunta en el mostrador.')
    if not config.get('logins'):
        raise ValueError('no logins: each machine needs the address of a post')
    config['libraries'] = [Library(login['post']) for login in config['logins']]
    return config


async def main(config):
    """Listens until told to stop."""
    room = asyncio.Semaphore(MAX_MACHINES)
    here = {}
    server = await asyncio.start_server(lambda r, w: serve(r, w, config, room, here), config['host'], config['port'])
    log.info('listening on %s:%s for %d post(s)', config['host'], config['port'], len(config['logins']))
    async with server:
        await server.serve_forever()


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description='A bridge between SIP2 machines and a library in Ayvurenda.')
    parser.add_argument('config', help='the settings, in JSON')
    arguments = parser.parse_args()
    logging.basicConfig(level=logging.INFO, format='%(asctime)s %(message)s')
    try:
        asyncio.run(main(load(arguments.config)))
    except KeyboardInterrupt:
        sys.exit(0)
