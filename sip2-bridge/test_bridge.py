#!/usr/bin/env python3
# This file is part of Oksigenia Ayvurenda, under the GNU GPL, version 3 or later.
"""The bridge's own tests: the protocol, with a library made up here. python3 -I -B test_bridge.py"""

import asyncio
import importlib.util
import os
import unittest

spec = importlib.util.spec_from_file_location('bridge', os.path.join(os.path.dirname(os.path.abspath(__file__)), 'bridge.py'))
bridge = importlib.util.module_from_spec(spec)
spec.loader.exec_module(bridge)

DATE = '20261102    120000'


class Made:
    """A library that says what it is told to."""

    def __init__(self, answers=None):
        self.answers = answers or {}
        self.asked = []

    def ask(self, op, **values):
        """What it was told; None stands for a library that cannot be reached."""
        self.asked.append((op, values))
        said = self.answers.get(op)
        if said is None:
            raise OSError('no answer')
        return said


def machine(answers=None, logins=None):
    """A machine with a made-up library."""
    config = {'logins': logins or [{'post': 'https://x/y/self.php?key=k'}], 'offline': 'Sin conexión', 'encoding': 'utf-8'}
    config['libraries'] = [Made(answers) for _ in config['logins']]
    return bridge.Machine(config), config['libraries'][0]


def say(message, who):
    """The answer to a message."""
    return asyncio.run(bridge.respond(message, who))


class ChecksumTest(unittest.TestCase):
    """Error detection."""

    def test_known_message(self):
        message = '9900302.00AY1AZ'
        total = sum(message.encode()) + int(bridge.checksum(message), 16)
        self.assertEqual(0, total & 0xFFFF)

    def test_valid(self):
        message = '9900302.00AY1AZ'
        self.assertTrue(bridge.valid(message + bridge.checksum(message)))
        self.assertFalse(bridge.valid(message + '0000'))
        self.assertTrue(bridge.valid('9900302.00'))

    def test_the_bytes_as_they_travel(self):
        message = 'AMBiblioteca de demostración|AZ'
        self.assertEqual(0, (sum(message.encode('utf-8')) + int(bridge.checksum(message), 16)) & 0xFFFF)
        self.assertEqual(0, (sum(message.encode('cp850')) + int(bridge.checksum(message, 'cp850'), 16)) & 0xFFFF)
        self.assertNotEqual(bridge.checksum(message), bridge.checksum(message, 'cp850'))

    def test_a_card_may_hold_the_letters(self):
        message = '23000' + DATE + 'AOx|AAAZ123|AC|'
        self.assertTrue(bridge.valid(message))
        self.assertEqual('AZ123', bridge.parse(message)[2]['AA'])


class ParseTest(unittest.TestCase):
    """Reading a message."""

    def test_fixed_and_fields(self):
        code, fixed, fields, detect, sequence = bridge.parse('11YN' + DATE + ' ' * 18 + 'AOlib|AA100|AB200|AC|')
        self.assertEqual('11', code)
        self.assertEqual(38, len(fixed))
        self.assertEqual('100', fields['AA'])
        self.assertEqual('200', fields['AB'])
        self.assertFalse(detect)
        self.assertIsNone(sequence)

    def test_tail(self):
        message = '17' + DATE + 'AOlib|AB200|AY3AZ'
        _, _, fields, detect, sequence = bridge.parse(message + bridge.checksum(message))
        self.assertEqual('200', fields['AB'])
        self.assertTrue(detect)
        self.assertEqual('3', sequence)


class AnswerTest(unittest.TestCase):
    """What a machine hears."""

    def test_status_online_and_not(self):
        who, _ = machine({'status': {'online': True, 'checkin': True, 'checkout': True, 'renew': False, 'institution': 'demo',
                                     'name': 'Biblioteca'}})
        answer = say('9900302.00', who)
        self.assertTrue(answer.startswith('98YYYNNN200003'))
        self.assertEqual('2.00', answer[32:36])
        self.assertIn('AOdemo|', answer)
        self.assertIn('BX' + bridge.SUPPORTED + '|', answer)
        who, _ = machine({})
        answer = say('9900302.00', who)
        self.assertTrue(answer.startswith('98NNNNNN'))
        self.assertIn('AFSin conexión|', answer)

    def test_login(self):
        logins = [{'user': 'a', 'password': 'b', 'post': 'https://x/y/self.php?key=k'}]
        who, _ = machine({'status': {'online': True}}, logins)
        self.assertIsNone(say('17' + DATE + 'AOx|AB1|', who), 'nothing before logging in')
        self.assertEqual('940', say('9300CNa|COmala|', who))
        self.assertEqual('941', say('9300CNa|COb|', who))
        self.assertIsNotNone(who.library)

    def test_a_login_with_accents(self):
        logins = [{'user': 'máquina', 'password': 'ñandú', 'post': 'https://x/y/self.php?key=k'}]
        who, _ = machine({}, logins)
        self.assertEqual('940', say('9300CNotra|COñu|', who), 'it does not fall over')
        self.assertEqual('941', say('9300CNmáquina|COñandú|', who))

    def test_wrong_logins_are_counted(self):
        logins = [{'user': 'a', 'password': 'b', 'post': 'https://x/y/self.php?key=k'}]
        who, _ = machine({}, logins)
        for _ in range(3):
            say('9300CNa|COmala|', who)
        self.assertEqual(3, who.wrong)

    def test_patron(self):
        who, library = machine({'patron': {'valid': True, 'name': 'Ana'}})
        answer = say('63000' + DATE + ' ' * 10 + 'AOx|AA100|AC|AD|', who)
        self.assertEqual(('patron', {'patron': '100'}), library.asked[0])
        self.assertEqual('64' + ' ' * 14 + '000', answer[:19])
        self.assertEqual('0000' * 6, answer[37:61], 'how many books someone has stays theirs')
        self.assertIn('AEAna|', answer)
        self.assertIn('BLY|', answer)
        self.assertIn('CQY|', answer)

    def test_a_password_cannot_be_right(self):
        who, _ = machine({'patron': {'valid': True, 'name': 'Ana', 'flags': {}}})
        self.assertIn('CQN|', say('23000' + DATE + 'AOx|AA100|AC|AD1234|', who))

    def test_unknown_or_stopped(self):
        who, _ = machine({'patron': {'valid': False, 'flags': {'denied': True}, 'message': 'No sale'}})
        answer = say('23000' + DATE + 'AOx|AA100|AC|', who)
        self.assertEqual('24Y' + ' ' * 13, answer[:16])
        self.assertIn('BLN|', answer)
        self.assertIn('AFNo sale|', answer)

    def test_checkout(self):
        who, library = machine({'checkout': {'ok': True, 'title': 'El | faro', 'due': '20261109    235959'}})
        answer = say('11YN' + DATE + ' ' * 18 + 'AOx|AA100|AB200|AC|', who)
        self.assertEqual(('checkout', {'patron': '100', 'item': '200'}), library.asked[0])
        self.assertTrue(answer.startswith('121NUY'))
        self.assertIn('AJEl faro|', answer, 'a bar in a title would break the message')
        self.assertIn('AH20261109    235959|', answer, 'at the hour the library says, with its four spaces')

    def test_checkout_refused_or_unreachable(self):
        who, _ = machine({'checkout': {'ok': False, 'title': 'El faro', 'message': 'Pasa por el mostrador'}})
        answer = say('11YN' + DATE + ' ' * 18 + 'AOx|AA100|AB200|AC|', who)
        self.assertTrue(answer.startswith('120NUN'))
        self.assertIn('AFPasa por el mostrador|', answer)
        who, _ = machine({})
        answer = say('11YN' + DATE + ' ' * 18 + 'AOx|AA100|AB200|AC|', who)
        self.assertTrue(answer.startswith('120NUN'))
        self.assertIn('AFSin conexión|', answer)

    def test_checkin(self):
        who, _ = machine({'checkin': {'ok': True, 'title': 'El faro', 'alert': True, 'message': 'Apártalo'}})
        answer = say('09N' + DATE + DATE + 'APx|AOx|AB200|AC|', who)
        self.assertTrue(answer.startswith('101YUY'))
        self.assertIn('CV01|', answer)
        self.assertIn('AQx|', answer)

    def test_item(self):
        who, _ = machine({'item': {'circulation': '04', 'title': 'El faro', 'due': '20261109    235959'}})
        answer = say('17' + DATE + 'AOx|AB200|', who)
        self.assertTrue(answer.startswith('18040001'))
        self.assertIn('AJEl faro|', answer)
        self.assertIn('AH', answer)

    def test_renew(self):
        who, library = machine({'renew': {'ok': True, 'title': 'El faro', 'due': '20261109    235959'}})
        answer = say('29NN' + DATE + ' ' * 18 + 'AOx|AA100|AB200|', who)
        self.assertEqual(('renew', {'patron': '100', 'item': '200'}), library.asked[0])
        self.assertTrue(answer.startswith('301YUU'))

    def test_end(self):
        who, _ = machine({})
        self.assertTrue(say('35' + DATE + 'AOx|AA100|', who).startswith('36Y'))

    def test_what_is_not_spoken(self):
        who, _ = machine({})
        self.assertEqual('96', say('15+' + DATE + 'AOx|', who))

    def test_detection_comes_back_and_resend(self):
        who, _ = machine({'status': {'online': True}})
        message = '9900302.00AY7AZ'
        answer = say(message + bridge.checksum(message), who)
        self.assertRegex(answer, r'AY7AZ[0-9A-F]{4}$')
        self.assertTrue(bridge.valid(answer))
        self.assertEqual(answer, say('97', who), 'the same again')
        self.assertTrue(say(message + '0000', who).startswith('96'), 'a wrong checksum asks again')


class SettingsTest(unittest.TestCase):
    """The address of a post."""

    def test_address(self):
        library = bridge.Library('https://x.example/local/oksigeniaayvurenda/self.php?key=abc')
        self.assertEqual('abc', library.key)
        self.assertEqual('https://x.example/local/oksigeniaayvurenda/sip_ajax.php', library.url)
        with self.assertRaises(ValueError):
            bridge.Library('https://x.example/nothing')
        with self.assertRaises(ValueError):
            bridge.Library('ftp://x.example/self.php?key=abc')


if __name__ == '__main__':
    unittest.main()
