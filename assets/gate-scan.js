/**
 * Venture Events — Gate scanner (html5-qrcode + AJAX check-in)
 *
 * Flow: Start → clear previous result → scan one QR → check-in → stop camera → show status.
 * Staff taps Start again for the next guest.
 */
(function () {
    'use strict';

    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    function parseTicketFromText(text) {
        var raw = (text || '').trim();
        if (!raw) {
            return { id: 0, token: '', raw: raw };
        }

        var id = 0;
        var token = '';
        var query = '';

        var qPos = raw.indexOf('?');
        if (qPos !== -1) {
            query = raw.slice(qPos + 1);
        } else if (raw.indexOf('id=') !== -1) {
            query = raw;
        }

        if (query) {
            var hash = query.indexOf('#');
            if (hash !== -1) {
                query = query.slice(0, hash);
            }
            try {
                var params = new URLSearchParams(query);
                id = parseInt(params.get('id') || '0', 10) || 0;
                token = params.get('token') || '';
            } catch (e) {
                query.split('&').forEach(function (pair) {
                    var parts = pair.split('=');
                    var k = decodeURIComponent(parts[0] || '');
                    var v = decodeURIComponent((parts[1] || '').replace(/\+/g, ' '));
                    if (k === 'id') {
                        id = parseInt(v, 10) || 0;
                    }
                    if (k === 'token') {
                        token = v;
                    }
                });
            }
        }

        return { id: id, token: token, raw: raw };
    }

    ready(function () {
        var root = document.getElementById('ve-gate-scan');
        if (!root || root.getAttribute('data-can-scan') !== '1') {
            return;
        }

        var Html5QrcodeCtor =
            (typeof Html5Qrcode !== 'undefined' && Html5Qrcode) ||
            window.Html5Qrcode ||
            (window.__Html5QrcodeLibrary__ && window.__Html5QrcodeLibrary__.Html5Qrcode);

        if (typeof Html5QrcodeCtor !== 'function') {
            if (window.console && console.warn) {
                console.warn('VE gate scan: html5-qrcode failed to load.');
            }
            return;
        }

        var eventId = root.getAttribute('data-event-id') || '0';
        var ajaxUrl = root.getAttribute('data-ajax-url') || '';
        var nonce = root.getAttribute('data-nonce') || '';

        var startBtn = document.getElementById('ve-gate-start');
        var stopBtn = document.getElementById('ve-gate-stop');
        var resultEl = document.getElementById('ve-gate-result');
        var headlineEl = document.getElementById('ve-gate-headline');
        var tierEl = document.getElementById('ve-gate-tier');
        var nameEl = document.getElementById('ve-gate-name');
        var entryEl = document.getElementById('ve-gate-entry');
        var msgEl = document.getElementById('ve-gate-msg');

        var scanner = null;
        var running = false;
        var busy = false;
        var starting = false;

        function setButtonsScanning(isScanning) {
            if (startBtn) {
                startBtn.hidden = !!isScanning;
            }
            if (stopBtn) {
                stopBtn.hidden = !isScanning;
            }
        }

        function clearResult() {
            if (!resultEl) {
                return;
            }
            resultEl.hidden = true;
            resultEl.classList.remove('ve-gate-scan__result--ok', 've-gate-scan__result--error');
            if (headlineEl) {
                headlineEl.textContent = '';
            }
            if (tierEl) {
                tierEl.textContent = '';
                tierEl.hidden = true;
            }
            if (nameEl) {
                nameEl.textContent = '';
                nameEl.hidden = true;
            }
            if (entryEl) {
                entryEl.textContent = '';
                entryEl.hidden = true;
            }
            if (msgEl) {
                msgEl.textContent = '';
                msgEl.hidden = true;
            }
        }

        function showSuccess(data) {
            if (!resultEl) {
                return;
            }
            resultEl.hidden = false;
            resultEl.classList.remove('ve-gate-scan__result--error');
            resultEl.classList.add('ve-gate-scan__result--ok');
            if (headlineEl) {
                headlineEl.textContent = '✓ ' + (data.headline || 'Valid ticket');
            }
            if (tierEl) {
                tierEl.textContent = data.tier_name || '';
                tierEl.hidden = !data.tier_name;
            }
            if (nameEl) {
                nameEl.textContent = data.guest_name || '';
                nameEl.hidden = !data.guest_name;
            }
            if (entryEl) {
                entryEl.textContent = data.entry_line || '';
                entryEl.hidden = !data.entry_line;
            }
            if (msgEl) {
                msgEl.hidden = true;
                msgEl.textContent = '';
            }
        }

        function showError(data) {
            if (!resultEl) {
                return;
            }
            resultEl.hidden = false;
            resultEl.classList.remove('ve-gate-scan__result--ok');
            resultEl.classList.add('ve-gate-scan__result--error');
            if (headlineEl) {
                headlineEl.textContent = '✗ ' + (data.headline || 'Invalid ticket');
            }
            if (tierEl) {
                tierEl.textContent = '';
                tierEl.hidden = true;
            }
            if (nameEl) {
                nameEl.textContent = '';
                nameEl.hidden = true;
            }
            if (entryEl) {
                entryEl.textContent = '';
                entryEl.hidden = true;
            }
            if (msgEl) {
                var m = data.message || '';
                msgEl.textContent = m;
                msgEl.hidden = !m;
            }
        }

        function vibrate(ok) {
            if (navigator.vibrate) {
                try {
                    navigator.vibrate(ok ? 80 : [40, 40, 40]);
                } catch (e) {
                    /* ignore */
                }
            }
        }

        /**
         * Stop camera and restore Start button. Result panel is left as-is
         * (cleared only when Start is pressed again).
         *
         * @returns {Promise}
         */
        function stopScanner() {
            if (!scanner || !running) {
                running = false;
                setButtonsScanning(false);
                return Promise.resolve();
            }

            return scanner
                .stop()
                .then(function () {
                    try {
                        scanner.clear();
                    } catch (e) {
                        /* ignore */
                    }
                })
                .catch(function () {
                    /* already stopped */
                })
                .then(function () {
                    running = false;
                    setButtonsScanning(false);
                });
        }

        function postCheckIn(parsed) {
            var body = new FormData();
            body.append('action', 've_gate_check_in');
            body.append('nonce', nonce);
            body.append('event_id', eventId);
            body.append('id', String(parsed.id || 0));
            body.append('token', parsed.token || '');
            body.append('raw', parsed.raw || '');

            return fetch(ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: body,
            })
                .then(function (res) {
                    return res.json().then(function (json) {
                        return { httpOk: res.ok, json: json };
                    });
                })
                .then(function (pack) {
                    var json = pack.json || {};
                    if (json.success && json.data) {
                        showSuccess(json.data);
                        vibrate(true);
                    } else {
                        var err = json.data && typeof json.data === 'object' ? json.data : {};
                        showError({
                            headline: err.headline || 'Invalid ticket',
                            message: err.message || 'Check-in failed.',
                        });
                        vibrate(false);
                    }
                })
                .catch(function () {
                    showError({
                        headline: 'Invalid ticket',
                        message: 'Network error. Check connection and try again.',
                    });
                    vibrate(false);
                });
        }

        /**
         * QR detected: stop camera, look up ticket, show status, wait for Start again.
         */
        function onScanSuccess(decodedText) {
            if (busy || !running) {
                return;
            }
            busy = true;

            var raw = (decodedText || '').trim();
            var parsed = parseTicketFromText(raw);

            // Stop immediately so the same code is not re-fired while AJAX runs.
            stopScanner()
                .then(function () {
                    if (!parsed.id || !parsed.token) {
                        showError({
                            headline: 'Invalid ticket',
                            message: 'Could not read ticket code.',
                        });
                        vibrate(false);
                        return;
                    }
                    return postCheckIn(parsed);
                })
                .finally(function () {
                    busy = false;
                });
        }

        function startScanner() {
            if (running || starting || busy) {
                return;
            }
            starting = true;

            // Fresh result for the next guest
            clearResult();

            if (!scanner) {
                scanner = new Html5QrcodeCtor('ve-gate-reader');
            }

            var config = {
                fps: 8,
                qrbox: function (viewfinderWidth, viewfinderHeight) {
                    var minEdge = Math.min(viewfinderWidth, viewfinderHeight);
                    var size = Math.floor(minEdge * 0.75);
                    size = Math.max(180, Math.min(size, 320));
                    return { width: size, height: size };
                },
                aspectRatio: 1.0,
                disableFlip: false,
            };

            function onStarted() {
                running = true;
                starting = false;
                setButtonsScanning(true);
            }

            function onFailed(err, err2) {
                running = false;
                starting = false;
                setButtonsScanning(false);
                if (window.console && console.warn) {
                    console.warn('VE gate scan camera error', err, err2);
                }
            }

            scanner
                .start({ facingMode: 'environment' }, config, onScanSuccess, function () {
                    /* frame miss — ignore */
                })
                .then(onStarted)
                .catch(function (err) {
                    return scanner
                        .start({ facingMode: 'user' }, config, onScanSuccess, function () {})
                        .then(onStarted)
                        .catch(function (err2) {
                            onFailed(err, err2);
                        });
                });
        }

        if (startBtn) {
            startBtn.addEventListener('click', function () {
                startScanner();
            });
        }
        if (stopBtn) {
            stopBtn.addEventListener('click', function () {
                if (busy) {
                    return;
                }
                stopScanner();
            });
        }
    });
})();
