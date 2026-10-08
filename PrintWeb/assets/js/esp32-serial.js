document.addEventListener('DOMContentLoaded', function () {
    const scanButton = document.getElementById('scanEsp32Button');
    const status = document.getElementById('esp32ScanStatus');
    const modal = document.getElementById('addEspModal');

    if (!scanButton || !status || !modal) return;

    function showStatus(message, type) {
        status.className = 'alert alert-' + type + ' esp32-scan-status';
        status.textContent = message;
        status.hidden = false;
    }

    function delay(milliseconds) {
        return new Promise(function (resolve) {
            window.setTimeout(resolve, milliseconds);
        });
    }

    function requestDiscovery(reader, writer) {
        const decoder = new TextDecoder();
        const encoder = new TextEncoder();
        let textBuffer = '';
        let timeoutId;
        let scanTimer;
        let readTask;
        let settled = false;
        let resolveFound;
        let rejectFound;

        const found = new Promise(function (resolve, reject) {
            resolveFound = resolve;
            rejectFound = reject;
        });

        readTask = (async function () {
            while (!settled) {
                const result = await reader.read();
                if (result.done) {
                    throw new Error('The USB serial port closed before the ESP32 responded.');
                }
                textBuffer += decoder.decode(result.value, { stream: true });
                const lines = textBuffer.split(/\r?\n/);
                textBuffer = lines.pop() || '';

                for (const line of lines) {
                    if (!line.trim().startsWith('{')) continue;
                    try {
                        const payload = JSON.parse(line);
                        if (payload.protocol === 'printweb-esp32'
                            && typeof payload.device_id === 'string'
                            && typeof payload.device_name === 'string') {
                            settled = true;
                            resolveFound(payload);
                            return;
                        }
                    } catch (error) {
                        // Ignore normal firmware log lines and unrelated JSON output.
                    }
                }
            }
        })().catch(function (error) {
            if (!settled) {
                settled = true;
                rejectFound(error);
            }
        });

        scanTimer = window.setInterval(function () {
            writer.write(encoder.encode('PRINTWEB_SCAN\n')).catch(function (error) {
                if (!settled) {
                    settled = true;
                    rejectFound(error);
                }
            });
        }, 1200);

        timeoutId = window.setTimeout(function () {
            if (!settled) {
                settled = true;
                rejectFound(new Error('No PrintWeb-compatible ESP32 response. Upload the latest PrintWeb firmware and try again.'));
            }
        }, 20000);

        return found.finally(function () {
            settled = true;
            window.clearInterval(scanTimer);
            window.clearTimeout(timeoutId);
            reader.cancel().catch(function () {});
            return readTask.catch(function () {});
        });
    }

    scanButton.addEventListener('click', async function () {
        if (!('serial' in navigator)) {
            showStatus('USB serial scanning requires Chrome or Edge. Open this site through localhost or HTTPS.', 'warning');
            return;
        }
        if (!window.isSecureContext) {
            showStatus('USB serial access is blocked on an insecure page. Open PrintWeb through localhost or HTTPS.', 'warning');
            return;
        }

        scanButton.disabled = true;
        scanButton.innerHTML = '<i class="fas fa-spinner fa-spin" aria-hidden="true"></i> Scanning...';
        showStatus('Choose the ESP32 USB/COM port in the browser dialog. Close Arduino IDE or Thonny serial monitors first.', 'info');

        let port;
        let reader;
        let writer;
        try {
            port = await navigator.serial.requestPort({
                filters: [
                    { usbVendorId: 0x303A },
                    { usbVendorId: 0x10C4 },
                    { usbVendorId: 0x1A86 },
                    { usbVendorId: 0x0403 }
                ]
            });
            await port.open({ baudRate: 115200 });
            reader = port.readable.getReader();
            writer = port.writable.getWriter();
            showStatus('Port opened. Waiting for the ESP32 identification response...', 'info');
            await delay(1500);
            const device = await requestDiscovery(reader, writer);

            const deviceIdInput = modal.querySelector('input[name="device_id"]');
            const deviceNameInput = modal.querySelector('input[name="device_name"]');
            const apiKeyInput = modal.querySelector('input[name="api_key"]');
            if (!deviceIdInput || !deviceNameInput || !apiKeyInput) {
                throw new Error('The device registration form is unavailable.');
            }

            deviceIdInput.value = device.device_id;
            deviceNameInput.value = device.device_name;
            apiKeyInput.value = '';
            showStatus(
                'Detected ' + device.device_name + ' (' + device.device_id + '), firmware ' + (device.firmware_version || 'unknown') + '. Complete registration, then configure the generated API key in the firmware.',
                'success'
            );
            window.openModal('addEspModal');
        } catch (error) {
            if (error.name === 'NotFoundError' || error.name === 'AbortError') {
                showStatus('No USB serial port was selected. Connect the ESP32 and scan again.', 'warning');
            } else {
                showStatus(error.message || 'Could not scan this USB device. Check the connection and try again.', 'danger');
            }
        } finally {
            if (reader) {
                try { await reader.cancel(); } catch (error) {}
                try { reader.releaseLock(); } catch (error) {}
            }
            if (writer) {
                try { writer.releaseLock(); } catch (error) {}
            }
            if (port && port.readable) {
                try { await port.close(); } catch (error) {}
            }
            scanButton.disabled = false;
            scanButton.innerHTML = '<i class="fas fa-search" aria-hidden="true"></i> Scan USB';
        }
    });

    document.querySelectorAll('[data-copy-api-key]').forEach(function (button) {
        button.addEventListener('click', async function () {
            try {
                await navigator.clipboard.writeText(button.dataset.copyApiKey || '');
                const originalText = button.textContent;
                button.textContent = 'Copied';
                window.setTimeout(function () {
                    button.textContent = originalText;
                }, 1600);
            } catch (error) {
                showStatus('Could not copy the API key. Open this page on localhost or HTTPS and allow clipboard access.', 'warning');
            }
        });
    });
});
