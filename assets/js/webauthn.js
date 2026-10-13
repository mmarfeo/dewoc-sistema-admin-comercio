/**
 * Login sin contraseña vía WebAuthn (huella/Face ID/PIN del dispositivo).
 * Los helpers de conversión binaria replican exactamente el formato "RFC 1342-like"
 * que usa lbuchs/webauthn del lado del servidor (ver vendor/lbuchs/webauthn/_test/client.html).
 */
(function (window) {
    'use strict';

    function soportado() {
        return !!(window.PublicKeyCredential && navigator.credentials && navigator.credentials.create && navigator.credentials.get);
    }

    function recursiveBase64StrToArrayBuffer(obj) {
        const prefix = '=?BINARY?B?';
        const suffix = '?=';
        if (typeof obj === 'object' && obj !== null) {
            for (const key in obj) {
                if (typeof obj[key] === 'string') {
                    const str = obj[key];
                    if (str.substring(0, prefix.length) === prefix && str.substring(str.length - suffix.length) === suffix) {
                        const raw = window.atob(str.substring(prefix.length, str.length - suffix.length));
                        const bytes = new Uint8Array(raw.length);
                        for (let i = 0; i < raw.length; i++) bytes[i] = raw.charCodeAt(i);
                        obj[key] = bytes.buffer;
                    }
                } else {
                    recursiveBase64StrToArrayBuffer(obj[key]);
                }
            }
        }
    }

    function arrayBufferToBase64(buffer) {
        let binary = '';
        const bytes = new Uint8Array(buffer);
        for (let i = 0; i < bytes.byteLength; i++) binary += String.fromCharCode(bytes[i]);
        return window.btoa(binary);
    }

    function fetchJson(url, options) {
        return window.fetch(url, options).then((r) => r.json());
    }

    /** Flujo de login: challenge -> credentials.get() -> verificar -> redirect */
    async function login() {
        const base = window.APP_BASE || '';
        const args = await fetchJson(base + '/webauthn/login-challenge', { method: 'GET', cache: 'no-cache' });
        if (args.ok === false) throw new Error(args.error || 'No se pudo iniciar el login.');

        recursiveBase64StrToArrayBuffer(args);
        const cred = await navigator.credentials.get(args);

        const payload = {
            id: arrayBufferToBase64(cred.rawId),
            clientDataJSON: arrayBufferToBase64(cred.response.clientDataJSON),
            authenticatorData: arrayBufferToBase64(cred.response.authenticatorData),
            signature: arrayBufferToBase64(cred.response.signature),
        };

        const res = await fetchJson(base + '/webauthn/login-verificar', {
            method: 'POST',
            cache: 'no-cache',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });

        if (!res.ok) throw new Error(res.error || 'No se pudo verificar el dispositivo.');
        window.location.href = res.redirect;
    }

    /** Flujo de registro: challenge -> credentials.create() -> verificar */
    async function registrar(deviceName) {
        const base = window.APP_BASE || '';
        const args = await fetchJson(base + '/webauthn/registro-challenge', { method: 'GET', cache: 'no-cache' });
        if (args.ok === false) throw new Error(args.error || 'No se pudo iniciar el registro.');

        recursiveBase64StrToArrayBuffer(args);
        const cred = await navigator.credentials.create(args);

        const payload = {
            deviceName: deviceName || '',
            clientDataJSON: cred.response.clientDataJSON ? arrayBufferToBase64(cred.response.clientDataJSON) : null,
            attestationObject: cred.response.attestationObject ? arrayBufferToBase64(cred.response.attestationObject) : null,
        };

        const res = await fetchJson(base + '/webauthn/registro-verificar', {
            method: 'POST',
            cache: 'no-cache',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.APP_CSRF || '' },
            body: JSON.stringify(payload),
        });

        if (!res.ok) throw new Error(res.error || 'No se pudo registrar el dispositivo.');
        return res;
    }

    /** Engancha el botón de la pantalla de login: solo lo muestra si el navegador soporta WebAuthn */
    function iniciarBotonLogin({ boxEl, btnEl, msgEl }) {
        if (!soportado() || !boxEl || !btnEl) return;
        boxEl.classList.remove('d-none');

        btnEl.addEventListener('click', async () => {
            if (msgEl) { msgEl.classList.add('d-none'); msgEl.textContent = ''; }
            btnEl.disabled = true;
            try {
                await login();
            } catch (err) {
                btnEl.disabled = false;
                if (msgEl) {
                    msgEl.textContent = err.message || 'No se pudo ingresar con el dispositivo. Probá con tu contraseña.';
                    msgEl.classList.remove('d-none');
                }
            }
        });
    }

    window.DewocWebauthn = { soportado, login, registrar, iniciarBotonLogin };
})(window);
