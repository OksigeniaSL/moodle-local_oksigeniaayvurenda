// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Ayvurenda: what a recording of a reading goes through before it is sent, in the browser and without encoding it again
 * (no server needs ffmpeg). A reading is recorded in pieces, one per pause: a piece read badly is thrown away and read
 * again. The pieces of a WebM with Opus (what Chrome, Edge and Firefox record) are joined packet by packet into one file;
 * the silence and the tap of the button at its ends are cut the same way; and the voice is left at the level of the
 * others by the gain the Opus header carries, which every player applies. What is recorded is kept in the device
 * (IndexedDB) until it is sent: a page closed or a battery run out loses nothing.
 *
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';

    // EBML: an element is its id (1 to 4 bytes, with its length marker) and its size (1 to 8 bytes), then its data.
    var vint = function(bytes, at, keepMarker) {
        var first = bytes[at];
        var length = 1;
        var mask = 0x80;
        while (length <= 8 && !(first & mask)) {
            length++;
            mask >>= 1;
        }
        if (length > 8 || at + length > bytes.length) {
            return null;
        }
        var value = keepMarker ? first : first & (mask - 1);
        var unknown = (first & (mask - 1)) === mask - 1;
        for (var i = 1; i < length; i++) {
            value = value * 256 + bytes[at + i];
            unknown = unknown && bytes[at + i] === 0xff;
        }
        return {length: length, value: value, unknown: unknown};
    };
    var element = function(bytes, at) {
        var id = vint(bytes, at, true);
        if (!id) {
            return null;
        }
        var size = vint(bytes, at + id.length, false);
        if (!size) {
            return null;
        }
        var data = at + id.length + size.length;
        return {id: id.value, at: at, data: data, end: size.unknown ? bytes.length : data + size.value, unknown: size.unknown};
    };
    var uint = function(bytes, from, to) {
        var value = 0;
        for (var i = from; i < to; i++) {
            value = value * 256 + bytes[i];
        }
        return value;
    };
    var ID = {EBML: 0x1A45DFA3, SEGMENT: 0x18538067, INFO: 0x1549A966, TRACKS: 0x1654AE6B, CLUSTER: 0x1F43B675,
        TIMECODE: 0xE7, SIMPLEBLOCK: 0xA3, BLOCKGROUP: 0xA0, BLOCK: 0xA1, SCALE: 0x2AD7B1, CODECPRIVATE: 0x63A2};

    /**
     * The parts of a WebM a recorder makes: its header, its tracks, and its Opus packets with their times.
     *
     * @param {ArrayBuffer} buffer
     * @return {Object|null} header (bytes), tracks (bytes of the whole element), scale (ns per tick), packets ({time in ms,
     *     frame bytes}); null if it is not such a WebM.
     */
    var parse = function(buffer) {
        var bytes = new Uint8Array(buffer);
        var head = element(bytes, 0);
        if (!head || head.id !== ID.EBML) {
            return null;
        }
        var segment = element(bytes, head.end);
        if (!segment || segment.id !== ID.SEGMENT) {
            return null;
        }
        var out = {header: bytes.subarray(0, head.end), tracks: null, scale: 1000000, packets: []};
        var at = segment.data;
        while (at < segment.end) {
            var child = element(bytes, at);
            if (!child) {
                break;
            }
            if (child.id === ID.INFO) {
                for (var i = child.data; i < child.end;) {
                    var info = element(bytes, i);
                    if (!info) {
                        break;
                    }
                    if (info.id === ID.SCALE) {
                        out.scale = uint(bytes, info.data, info.end);
                    }
                    i = info.end;
                }
            } else if (child.id === ID.TRACKS) {
                out.tracks = bytes.slice(child.at, child.end);
            } else if (child.id === ID.CLUSTER) {
                var base = 0;
                // A cluster of unknown size ends where the next one starts.
                var end = child.end;
                for (var j = child.data; j < end;) {
                    var inner = element(bytes, j);
                    if (!inner) {
                        end = j;
                        break;
                    }
                    if (inner.id === ID.CLUSTER) {
                        end = j;
                        break;
                    }
                    if (inner.id === ID.TIMECODE) {
                        base = uint(bytes, inner.data, inner.end);
                    } else if (inner.id === ID.SIMPLEBLOCK || inner.id === ID.BLOCKGROUP) {
                        var block = inner;
                        if (inner.id === ID.BLOCKGROUP) {
                            block = element(bytes, inner.data);
                            if (!block || block.id !== ID.BLOCK) {
                                j = inner.end;
                                continue;
                            }
                        }
                        var track = vint(bytes, block.data, false);
                        var rel = (bytes[block.data + track.length] << 8) | bytes[block.data + track.length + 1];
                        rel = rel > 32767 ? rel - 65536 : rel;
                        var frame = bytes.slice(block.data + track.length + 3, Math.min(block.end, bytes.length));
                        out.packets.push({time: (base + rel) * out.scale / 1000000, frame: frame});
                    }
                    j = inner.end;
                }
                at = end;
                continue;
            }
            at = child.end;
        }
        return out.tracks && out.packets.length ? out : null;
    };

    // Writing EBML.
    var idBytes = function(id) {
        var list = [];
        while (id > 0) {
            list.unshift(id % 256);
            id = Math.floor(id / 256);
        }
        return list;
    };
    var sizeBytes = function(size) {
        // Eight bytes always: simple, and any size fits.
        var list = [0x01];
        var rest = [];
        for (var i = 0; i < 7; i++) {
            rest.unshift(size % 256);
            size = Math.floor(size / 256);
        }
        return list.concat(rest);
    };
    var make = function(id, parts) {
        var length = parts.reduce(function(sum, part) {
            return sum + part.length;
        }, 0);
        var all = new Uint8Array(idBytes(id).length + 8 + length);
        all.set(idBytes(id), 0);
        all.set(sizeBytes(length), idBytes(id).length);
        var at = idBytes(id).length + 8;
        parts.forEach(function(part) {
            all.set(part, at);
            at += part.length;
        });
        return all;
    };
    var uintBytes = function(value, length) {
        var list = new Uint8Array(length);
        for (var i = length - 1; i >= 0; i--) {
            list[i] = value % 256;
            value = Math.floor(value / 256);
        }
        return list;
    };
    var floatBytes = function(value) {
        var list = new Uint8Array(8);
        new DataView(list.buffer).setFloat64(0, value);
        return list;
    };

    /**
     * The tracks element with the gain of the Opus header set: the gain every Opus player applies, in dB.
     *
     * @param {Uint8Array} tracks
     * @param {number} gain
     * @return {Uint8Array}
     */
    var withGain = function(tracks, gain) {
        var copy = tracks.slice();
        for (var i = 0; i + 8 < copy.length; i++) {
            // «OpusHead», then version, channels, pre-skip (2), rate (4) and the output gain (2, Q7.8, little-endian).
            if (copy[i] === 0x4F && copy[i + 1] === 0x70 && copy[i + 2] === 0x75 && copy[i + 3] === 0x73 &&
                    copy[i + 4] === 0x48 && copy[i + 5] === 0x65 && copy[i + 6] === 0x61 && copy[i + 7] === 0x64) {
                var q = Math.max(-32768, Math.min(32767, Math.round(gain * 256)));
                q = q < 0 ? q + 65536 : q;
                copy[i + 16] = q % 256;
                copy[i + 17] = Math.floor(q / 256);
                break;
            }
        }
        return copy;
    };

    /**
     * One WebM from Opus packets, its length written and its clusters short enough for their times.
     *
     * @param {Object} first What parse() gave of the first piece: its header and tracks are kept.
     * @param {Object[]} packets time (ms, from 0) and frame.
     * @param {number} gain dB for the Opus header.
     * @param {number} duration ms.
     * @return {Blob}
     */
    var build = function(first, packets, gain, duration) {
        var info = make(ID.INFO, [make(ID.SCALE, [uintBytes(1000000, 4)]), make(0x4489, [floatBytes(duration)]),
            make(0x4D80, [new TextEncoder().encode('Ayvurenda')]), make(0x5741, [new TextEncoder().encode('Ayvurenda')])]);
        var clusters = [];
        var i = 0;
        while (i < packets.length) {
            var base = Math.floor(packets[i].time);
            var blocks = [make(ID.TIMECODE, [uintBytes(base, 4)])];
            // A cluster of up to 20 s: a block's time is a 16-bit offset from it.
            while (i < packets.length && packets[i].time - base < 20000) {
                var rel = Math.round(packets[i].time - base);
                var header = new Uint8Array([0x81, (rel >> 8) & 0xff, rel & 0xff, 0x80]);
                blocks.push(make(ID.SIMPLEBLOCK, [header, packets[i].frame]));
                i++;
            }
            clusters.push(make(ID.CLUSTER, blocks));
        }
        var segment = make(ID.SEGMENT, [info, gain ? withGain(first.tracks, gain) : first.tracks].concat(clusters));
        return new Blob([first.header, segment], {type: 'audio/webm'});
    };

    /**
     * How long an Opus packet lasts, in ms, from its first byte (RFC 6716, 3.1): browsers record 20 or 60 ms.
     *
     * @param {Uint8Array} frame
     * @return {number}
     */
    var lasts = function(frame) {
        if (!frame.length) {
            return 0;
        }
        var config = frame[0] >> 3;
        var size = config < 12 ? [10, 20, 40, 60][config % 4] : (config < 16 ? [10, 20][config % 2] : [2.5, 5, 10, 20][config % 4]);
        var code = frame[0] & 3;
        var count = code === 0 ? 1 : (code === 3 ? (frame.length > 1 ? frame[1] & 0x3f : 1) : 2);
        return size * count;
    };

    /**
     * How long a piece lasts, from its packets.
     *
     * @param {Object} piece What parse() gave.
     * @return {number} ms.
     */
    var length = function(piece) {
        var packets = piece.packets;
        if (!packets.length) {
            return 0;
        }
        var last = packets[packets.length - 1];
        return last.time + lasts(last.frame) - packets[0].time;
    };

    /**
     * The pieces of a reading, one after another, as packets on one line of time: each piece starts where the last ended.
     *
     * @param {Object[]} pieces What parse() gave of each.
     * @return {Object[]} time and frame.
     */
    var line = function(pieces) {
        var all = [];
        var offset = 0;
        pieces.forEach(function(piece) {
            if (!piece.packets.length) {
                return;
            }
            var start = piece.packets[0].time;
            var end = start;
            piece.packets.forEach(function(packet) {
                end = packet.time + lasts(packet.frame);
                all.push({time: offset + packet.time - start, frame: packet.frame});
            });
            offset += end - start;
        });
        return all;
    };

    /**
     * Where the voice starts and ends, and how loud it is, from the decoded sound: what is under a whisper is silence;
     * a moment is left before and after, and the tap of the button at the start is not voice.
     *
     * @param {AudioBuffer} audio
     * @return {Object} start and end (ms), loudness (dBFS of the voice), peak (dBFS).
     */
    var measure = function(audio) {
        var data = audio.getChannelData(0);
        var rate = audio.sampleRate;
        var span = Math.round(rate * 0.05);
        var levels = [];
        var peak = 0;
        for (var at = 0; at + span <= data.length; at += span) {
            var sum = 0;
            for (var k = at; k < at + span; k++) {
                sum += data[k] * data[k];
                peak = Math.max(peak, Math.abs(data[k]));
            }
            levels.push(Math.sqrt(sum / span));
        }
        var loudest = levels.reduce(function(max, level) {
            return Math.max(max, level);
        }, 0);
        // Voice: a window above a twentieth of the loudest and above a floor.
        var threshold = Math.max(loudest / 20, 0.003);
        // The first 300 ms are the tap of the button.
        var skip = Math.ceil(0.3 / 0.05);
        var first = -1;
        var last = -1;
        var voiced = [];
        for (var w = 0; w < levels.length; w++) {
            if (levels[w] > threshold && w >= skip) {
                first = first < 0 ? w : first;
                last = w;
                voiced.push(levels[w]);
            }
        }
        var length = data.length / rate * 1000;
        if (first < 0) {
            return {start: 0, end: length, loudness: -60, peak: peak ? 20 * Math.log10(peak) : -60};
        }
        var mean = Math.sqrt(voiced.reduce(function(s, v) {
            return s + v * v;
        }, 0) / voiced.length);
        return {
            start: Math.max(0, first * 50 - 300),
            end: Math.min(length, (last + 1) * 50 + 600),
            loudness: 20 * Math.log10(mean),
            peak: 20 * Math.log10(peak || 0.00001),
        };
    };

    // The level all readings are left at (dBFS of the voice), and how much a voice is raised or lowered at most.
    var TARGET = -20;
    var gainFor = function(measured) {
        var gain = TARGET - measured.loudness;
        // Never so much that the loudest moment clips.
        gain = Math.min(gain, -1 - measured.peak);
        return Math.max(-12, Math.min(18, Math.round(gain * 10) / 10));
    };

    /**
     * The reading, from its pieces: joined, with its ends cut where it says, and at the level of the others.
     *
     * @param {Object[]} pieces What parse() gave of each.
     * @param {number} start ms to cut at the start.
     * @param {number} end ms where it ends.
     * @param {number} gain dB.
     * @return {Object} blob and duration (ms).
     */
    var cut = function(pieces, start, end, gain) {
        var kept = line(pieces).filter(function(packet) {
            return packet.time >= start && packet.time < end;
        }).map(function(packet) {
            return {time: packet.time - start, frame: packet.frame};
        });
        var duration = kept.length ? kept[kept.length - 1].time + lasts(kept[kept.length - 1].frame) : 0;
        return {blob: build(pieces[0], kept, gain, duration), duration: duration};
    };

    // The draft in the device: the pieces of a reading of a book, until it is sent or thrown away.
    var DB = 'ayvurenda-voces';
    var open = function() {
        return new Promise(function(resolve, reject) {
            if (!window.indexedDB) {
                reject(new Error('no indexedDB'));
                return;
            }
            var request = window.indexedDB.open(DB, 1);
            request.onupgradeneeded = function() {
                request.result.createObjectStore('drafts');
            };
            request.onsuccess = function() {
                resolve(request.result);
            };
            request.onerror = function() {
                reject(request.error);
            };
        });
    };
    var store = function(mode, work) {
        return open().then(function(db) {
            return new Promise(function(resolve, reject) {
                var tx = db.transaction('drafts', mode);
                var result = work(tx.objectStore('drafts'));
                tx.oncomplete = function() {
                    resolve(result && 'result' in result ? result.result : undefined);
                };
                tx.onerror = function() {
                    reject(tx.error);
                };
            });
        });
    };
    var draft = {
        /**
         * Keeps the pieces of a reading and what goes with them.
         *
         * @param {string} key The book and the part.
         * @param {Object} value pieces (Blobs), lengths (ms of each), part, title, backdrop, mime, time.
         * @return {Promise}
         */
        save: function(key, value) {
            return store('readwrite', function(drafts) {
                return drafts.put(value, key);
            }).catch(function() {
                return null;
            });
        },
        load: function(key) {
            return store('readonly', function(drafts) {
                return drafts.get(key);
            }).catch(function() {
                return null;
            });
        },
        drop: function(key) {
            return store('readwrite', function(drafts) {
                return drafts.delete(key);
            }).catch(function() {
                return null;
            });
        },
    };

    window.ayvVoiceEdit = {parse: parse, line: line, build: build, measure: measure, gainFor: gainFor, cut: cut, lasts: lasts,
        length: length, draft: draft, TARGET: TARGET};
})();
