/**
 * Subida por trozos para adjuntos grandes.
 *
 * Un archivo de 5 GB no se puede enviar en una sola peticion: PHP tendria que
 * bufferizarlo entero en un proceso de Apache y agotaria memoria o tiempo. Aqui
 * el archivo se parte en trozos de 8 MB que se envian en secuencia, y el
 * servidor los va escribiendo a disco.
 *
 * Cada trozo debe llegar en orden porque el servidor escribe al final del
 * archivo; por eso las subidas van en serie y no en paralelo. Cada trozo se
 * reintenta hasta 3 veces con espera creciente.
 *
 * Uso:
 *   const resultado = await window.VirthubChunkedUpload.upload(file, {
 *       onProgress: ({ percent, uploadedBytes, totalBytes, bytesPerSecond, remainingSeconds }) => {},
 *       onStatus: (mensaje) => {},
 *   });
 *   // resultado -> { upload_id, attachment: { name, path, size, type, mime } }
 */
(function () {
    'use strict';

    const RETRIES = 3;
    const BASE_DELAY_MS = 800;

    function csrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta && meta.content) {
            return meta.content;
        }

        const input = document.querySelector('input[name="_token"]');
        return input ? input.value : '';
    }

    function formatBytes(bytes) {
        if (!Number.isFinite(bytes) || bytes < 0) return '—';
        if (bytes >= 1073741824) return (bytes / 1073741824).toFixed(2) + ' GB';
        if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
        if (bytes >= 1024) return Math.round(bytes / 1024) + ' KB';
        return bytes + ' B';
    }

    function formatDuration(seconds) {
        if (!Number.isFinite(seconds) || seconds <= 0) return '—';
        if (seconds < 60) return Math.round(seconds) + ' s';
        if (seconds < 3600) return Math.round(seconds / 60) + ' min';
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.round((seconds % 3600) / 60);
        return hours + ' h ' + minutes + ' min';
    }

    function sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    /**
     * Envia un trozo por XHR para poder medir el progreso de subida.
     * No se usa fetch porque no expone el progreso de envio.
     */
    function sendChunk(url, blob, onChunkProgress) {
        return new Promise((resolve, reject) => {
            const request = new XMLHttpRequest();
            request.open('POST', url, true);
            request.setRequestHeader('Content-Type', 'application/octet-stream');
            request.setRequestHeader('X-CSRF-TOKEN', csrfToken());
            request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            request.setRequestHeader('Accept', 'application/json');

            if (request.upload && typeof onChunkProgress === 'function') {
                request.upload.addEventListener('progress', event => {
                    if (event.lengthComputable) {
                        onChunkProgress(event.loaded);
                    }
                });
            }

            request.addEventListener('load', () => {
                let payload = null;
                try {
                    payload = JSON.parse(request.responseText || '{}');
                } catch (error) {
                    payload = null;
                }

                if (request.status >= 200 && request.status < 300) {
                    resolve(payload || {});
                    return;
                }

                const message = (payload && payload.error)
                    ? payload.error
                    : 'El servidor respondio ' + request.status + '.';

                reject(new Error(message));
            });

            request.addEventListener('error', () => reject(new Error('Fallo de red al enviar el trozo.')));
            request.addEventListener('abort', () => reject(new Error('Subida cancelada.')));

            request.send(blob);
        });
    }

    /**
     * Sube un archivo completo por trozos.
     *
     * @param {File} file
     * @param {{onProgress?: Function, onStatus?: Function, signal?: AbortSignal}} options
     * @returns {Promise<{upload_id: string, attachment: object}>}
     */
    async function upload(file, options) {
        const settings = options || {};
        const onProgress = settings.onProgress || function () {};
        const onStatus = settings.onStatus || function () {};
        const signal = settings.signal;

        const throwIfAborted = () => {
            if (signal && signal.aborted) {
                throw new Error('Subida cancelada.');
            }
        };

        // 1) Abrir la sesion: el servidor decide el tamaño de trozo.
        onStatus('Preparando la subida…');

        const initResponse = await fetch('/attachments/initiate', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                filename: file.name,
                size: file.size,
                mime: file.type || '',
            }),
            signal: signal,
        });

        const initPayload = await initResponse.json().catch(() => ({}));

        if (!initResponse.ok) {
            throw new Error(initPayload.error || 'No se pudo iniciar la subida.');
        }

        const uploadId = initPayload.upload_id;
        const chunkBytes = initPayload.chunk_bytes;
        const totalChunks = initPayload.total_chunks;

        if (!uploadId || !chunkBytes || !totalChunks) {
            throw new Error('El servidor no devolvio una sesion de subida valida.');
        }

        const totalBytes = file.size;
        const startedAt = Date.now();
        let sentBytes = 0;

        // 2) Enviar los trozos en orden.
        for (let index = 0; index < totalChunks; index++) {
            throwIfAborted();

            const start = index * chunkBytes;
            const end = Math.min(start + chunkBytes, totalBytes);
            const blob = file.slice(start, end);
            const url = '/attachments/' + uploadId + '/chunk/' + index;
            let lastError = null;

            for (let attempt = 1; attempt <= RETRIES; attempt++) {
                throwIfAborted();

                try {
                    await sendChunk(url, blob, loaded => {
                        const current = sentBytes + loaded;
                        const elapsed = (Date.now() - startedAt) / 1000;
                        const speed = elapsed > 0 ? current / elapsed : 0;
                        const remaining = speed > 0 ? (totalBytes - current) / speed : NaN;

                        onProgress({
                            percent: Math.min(100, Math.round((current / totalBytes) * 100)),
                            uploadedBytes: current,
                            totalBytes: totalBytes,
                            chunkIndex: index + 1,
                            totalChunks: totalChunks,
                            bytesPerSecond: speed,
                            remainingSeconds: remaining,
                        });
                    });

                    sentBytes = end;
                    lastError = null;
                    break;
                } catch (error) {
                    lastError = error;

                    if (signal && signal.aborted) {
                        throw error;
                    }

                    if (attempt < RETRIES) {
                        onStatus(
                            'Reintentando el trozo ' + (index + 1) + ' de ' + totalChunks +
                            ' (' + attempt + '/' + (RETRIES - 1) + ')…'
                        );
                        await sleep(BASE_DELAY_MS * attempt);
                    }
                }
            }

            if (lastError) {
                // Si un trozo falla de forma definitiva, se descarta la sesion
                // para no dejar gigabytes ocupando disco sin dueno.
                abort(uploadId).catch(() => {});
                throw lastError;
            }

            onStatus(
                'Subiendo… trozo ' + (index + 1) + ' de ' + totalChunks +
                ' (' + formatBytes(sentBytes) + ' de ' + formatBytes(totalBytes) + ')'
            );
        }

        // 3) Ensamblar en el servidor.
        throwIfAborted();
        onStatus('Ensamblando el archivo en el servidor…');

        const completeResponse = await fetch('/attachments/' + uploadId + '/complete', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            signal: signal,
        });

        const completePayload = await completeResponse.json().catch(() => ({}));

        if (!completeResponse.ok) {
            throw new Error(completePayload.error || 'No se pudo ensamblar el archivo.');
        }

        onProgress({
            percent: 100,
            uploadedBytes: totalBytes,
            totalBytes: totalBytes,
            chunkIndex: totalChunks,
            totalChunks: totalChunks,
            bytesPerSecond: 0,
            remainingSeconds: 0,
        });

        return {
            upload_id: uploadId,
            attachment: completePayload.attachment,
        };
    }

    /**
     * Descarta una subida en curso.
     */
    async function abort(uploadId) {
        if (!uploadId) return;

        try {
            await fetch('/attachments/' + uploadId, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
        } catch (error) {
            // Si no se puede avisar al servidor, la limpieza automatica de
            // sesiones abandonadas se encargara mas tarde.
        }
    }

    window.VirthubChunkedUpload = {
        upload: upload,
        abort: abort,
        formatBytes: formatBytes,
        formatDuration: formatDuration,
    };
})();
