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
 * Ayvurenda: the camera as a barcode reader. The browser's own BarcodeDetector where it has one, and ZXing, loaded
 * only then, where it has not. It stays open between books: each code read goes to the page as if a scanner had
 * typed it, and the same code is not read twice in a row.
 *
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';
    var FORMATS = ['ean_13', 'ean_8', 'upc_a', 'code_128', 'code_39', 'itf', 'qr_code'];
    var zxingLoading = null;

    var loadZxing = function(url) {
        if (window.ZXing) {
            return Promise.resolve(window.ZXing);
        }
        zxingLoading = zxingLoading || new Promise(function(resolve, reject) {
            var script = document.createElement('script');
            script.src = url;
            script.onload = function() {
                resolve(window.ZXing);
            };
            script.onerror = reject;
            document.head.appendChild(script);
        });
        return zxingLoading;
    };

    // A function that reads a code from the video, or gives null.
    var makeDecoder = function(video, zxingurl) {
        if ('BarcodeDetector' in window) {
            return window.BarcodeDetector.getSupportedFormats().then(function(supported) {
                var formats = FORMATS.filter(function(f) {
                    return supported.indexOf(f) >= 0;
                });
                if (formats.indexOf('ean_13') < 0) {
                    throw new Error('no ean');
                }
                var detector = new window.BarcodeDetector({formats: formats});
                return function() {
                    return detector.detect(video).then(function(codes) {
                        return codes.length ? codes[0].rawValue : null;
                    });
                };
            }).catch(function() {
                return null;
            }).then(function(decoder) {
                return decoder || makeZxing(video, zxingurl);
            });
        }
        return makeZxing(video, zxingurl);
    };

    var makeZxing = function(video, zxingurl) {
        return loadZxing(zxingurl).then(function(Z) {
            var hints = new Map();
            hints.set(Z.DecodeHintType.POSSIBLE_FORMATS, [Z.BarcodeFormat.EAN_13, Z.BarcodeFormat.EAN_8,
                Z.BarcodeFormat.UPC_A, Z.BarcodeFormat.CODE_128, Z.BarcodeFormat.CODE_39, Z.BarcodeFormat.ITF,
                Z.BarcodeFormat.QR_CODE]);
            hints.set(Z.DecodeHintType.TRY_HARDER, true);
            var reader = new Z.MultiFormatReader();
            reader.setHints(hints);
            var canvas = document.createElement('canvas');
            var context = canvas.getContext('2d', {willReadFrequently: true});
            return function() {
                var width = video.videoWidth;
                var height = video.videoHeight;
                if (!width || !height) {
                    return Promise.resolve(null);
                }
                var scale = Math.min(1, 960 / width);
                canvas.width = Math.round(width * scale);
                canvas.height = Math.round(height * scale);
                context.drawImage(video, 0, 0, canvas.width, canvas.height);
                try {
                    var source = new Z.HTMLCanvasElementLuminanceSource(canvas);
                    var result = reader.decodeWithState(new Z.BinaryBitmap(new Z.HybridBinarizer(source)));
                    return Promise.resolve(result.getText());
                } catch (e) {
                    return Promise.resolve(null);
                }
            };
        });
    };

    var el = function(tag, cls, text) {
        var node = document.createElement(tag);
        if (cls) {
            node.className = cls;
        }
        if (text !== undefined && text !== null) {
            node.textContent = text;
        }
        return node;
    };

    window.AYVCamera = {
        available: function() {
            return !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia) && window.isSecureContext !== false;
        },

        /**
         * Opens the camera over the page.
         *
         * @param {Object} options onCode(code), onClose(), strings (cameratitle, camerahint, cameraclose, cameranone,
         *     camerastarting, cameraread), zxing (address of the ZXing script), keep (stay open after a read).
         * @return {Function} What closes it.
         */
        open: function(options) {
            var str = options.strings || {};
            var stream = null;
            var closed = false;
            var last = '';
            var lastTime = 0;
            var opener = document.activeElement;
            var layer = el('div', 'ayv-camera');
            layer.setAttribute('role', 'dialog');
            layer.setAttribute('aria-modal', 'true');
            layer.setAttribute('aria-label', str.cameratitle || '');
            var video = el('video', 'ayv-camera-video');
            video.setAttribute('playsinline', '');
            video.muted = true;
            var frame = el('div', 'ayv-camera-frame');
            var hint = el('p', 'ayv-camera-hint', str.camerastarting);
            hint.setAttribute('aria-live', 'polite');
            var close = el('button', 'ayv-button ayv-camera-close', str.cameraclose);
            close.type = 'button';
            var view = el('div', 'ayv-camera-view');
            view.appendChild(video);
            view.appendChild(frame);
            layer.appendChild(view);
            layer.appendChild(hint);
            layer.appendChild(close);
            document.body.appendChild(layer);
            close.focus();

            var stop = function() {
                if (closed) {
                    return;
                }
                closed = true;
                if (stream) {
                    stream.getTracks().forEach(function(track) {
                        track.stop();
                    });
                }
                document.removeEventListener('keydown', onkey);
                layer.remove();
                if (options.onClose) {
                    options.onClose();
                } else if (opener && opener.focus) {
                    opener.focus();
                }
            };
            var onkey = function(e) {
                if (e.key === 'Escape') {
                    stop();
                }
            };
            document.addEventListener('keydown', onkey);
            close.addEventListener('click', stop);

            navigator.mediaDevices.getUserMedia({audio: false, video: {facingMode: {ideal: 'environment'},
                width: {ideal: 1280}, height: {ideal: 720}}}).then(function(s) {
                stream = s;
                if (closed) {
                    stop();
                    return null;
                }
                video.srcObject = s;
                return video.play();
            }).then(function() {
                return closed ? null : makeDecoder(video, options.zxing);
            }).then(function(decode) {
                if (!decode || closed) {
                    return;
                }
                hint.textContent = str.camerahint;
                var tick = function() {
                    if (closed) {
                        return;
                    }
                    decode().then(function(code) {
                        var now = Date.now();
                        if (code && (code !== last || now - lastTime > 2500)) {
                            last = code;
                            lastTime = now;
                            frame.classList.add('ayv-camera-read');
                            setTimeout(function() {
                                frame.classList.remove('ayv-camera-read');
                            }, 300);
                            hint.textContent = String(str.cameraread || '').replace('{$a}', code);
                            if (navigator.vibrate) {
                                navigator.vibrate(40);
                            }
                            options.onCode(code);
                            if (!options.keep) {
                                stop();
                                return;
                            }
                        } else if (code) {
                            lastTime = now;
                        }
                        setTimeout(tick, 120);
                    }).catch(function() {
                        setTimeout(tick, 250);
                    });
                };
                tick();
            }).catch(function() {
                hint.textContent = str.cameranone;
            });
            return stop;
        },

        /**
         * Wires the camera button and the phone panel of a page to what handles a code read.
         *
         * @param {Object} config The page's config: sesskey, relayurl, zxing, strings.
         * @param {Function} handle What a code read does, as if a scanner had typed it.
         * @param {Function} refocus Gives the focus back to the page's field.
         */
        wire: function(config, handle, refocus) {
            var camera = document.getElementById('ayv-camera-open');
            var pair = document.getElementById('ayv-phone-pair');
            var panel = document.getElementById('ayv-phone-panel');
            var stopbutton = document.getElementById('ayv-phone-stop');
            var self = this;
            if (camera && self.available()) {
                camera.hidden = false;
                camera.addEventListener('click', function() {
                    self.open({onCode: handle, strings: config.strings, zxing: config.zxing, keep: true, onClose: refocus});
                });
            }
            if (!pair || !panel) {
                return;
            }
            var timer = null;
            var pull = function() {
                if (document.visibilityState === 'hidden') {
                    timer = setTimeout(pull, 1500);
                    return;
                }
                var body = new URLSearchParams();
                body.set('sesskey', config.sesskey);
                body.set('action', 'pull');
                fetch(config.relayurl, {method: 'POST', credentials: 'same-origin', body: body}).then(function(response) {
                    return response.json();
                }).then(function(answer) {
                    (answer.codes || []).forEach(handle);
                    return answer;
                }).catch(function() {
                    return null;
                }).then(function() {
                    if (timer !== null) {
                        timer = setTimeout(pull, 1500);
                    }
                    return null;
                });
            };
            var toggle = function(on) {
                panel.hidden = !on;
                pair.setAttribute('aria-expanded', on ? 'true' : 'false');
                pair.setAttribute('aria-pressed', on ? 'true' : 'false');
                if (on && timer === null) {
                    timer = setTimeout(pull, 200);
                } else if (!on && timer !== null) {
                    clearTimeout(timer);
                    timer = null;
                }
                refocus();
            };
            pair.addEventListener('click', function() {
                toggle(panel.hidden);
            });
            if (stopbutton) {
                stopbutton.addEventListener('click', function() {
                    toggle(false);
                });
            }
        }
    };
})();
