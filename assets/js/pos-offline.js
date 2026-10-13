/**
 * PosOffline — capa de acceso a IndexedDB para que el POS (ventas/pos.php) pueda
 * seguir vendiendo sin conexión: cachea el catálogo (productos + categorías) y
 * encola las ventas que no se pudieron enviar al servidor hasta que el usuario
 * decida sincronizarlas manualmente (nunca de forma automática).
 *
 * No factura por AFIP offline: el CAE requiere sí o sí conexión con AFIP, así
 * que toda venta encolada acá se sincroniza siempre con facturar_afip=0.
 *
 * Tampoco valida stock al sincronizar (confirmar_sin_stock=1 siempre, ver
 * sincronizarTodo()): el chequeo de stock de VentasController::guardar() existe para
 * evitar vender algo que YA no hay en el momento de cobrar — acá esa venta ya pasó de
 * verdad (el producto salió del local sin conexión, ya se cobró) y no hay ningún
 * cajero mirando la pantalla para responder "¿vender igual?". Sin este flag, el
 * servidor devuelve 409/requiereConfirmacion y la venta queda encolada para siempre,
 * repitiendo el mismo conflicto en cada intento de "Sincronizar" sin ninguna forma de
 * resolverlo — bug real reportado (quedaba trabada sin poder sincronizar nunca).
 */
const PosOffline = (function () {
    const DB_NAME    = 'dewoc_pos_offline';
    const DB_VERSION = 1;
    let dbPromise = null;

    function abrirDB() {
        if (dbPromise) return dbPromise;
        dbPromise = new Promise((resolve, reject) => {
            const req = indexedDB.open(DB_NAME, DB_VERSION);
            req.onupgradeneeded = function (ev) {
                const db = ev.target.result;
                if (!db.objectStoreNames.contains('productos'))         db.createObjectStore('productos', { keyPath: 'id' });
                if (!db.objectStoreNames.contains('categorias'))        db.createObjectStore('categorias', { keyPath: 'id' });
                if (!db.objectStoreNames.contains('ventas_pendientes')) db.createObjectStore('ventas_pendientes', { keyPath: 'local_id' });
            };
            req.onsuccess = () => resolve(req.result);
            req.onerror   = () => reject(req.error);
        });
        return dbPromise;
    }

    function conStore(nombre, modo) {
        return abrirDB().then(db => db.transaction(nombre, modo).objectStore(nombre));
    }

    function getAll(nombre) {
        return conStore(nombre, 'readonly').then(store => new Promise((resolve, reject) => {
            const req = store.getAll();
            req.onsuccess = () => resolve(req.result || []);
            req.onerror   = () => reject(req.error);
        }));
    }

    function clearYCargar(nombre, filas) {
        return abrirDB().then(db => new Promise((resolve, reject) => {
            const t     = db.transaction(nombre, 'readwrite');
            const store = t.objectStore(nombre);
            store.clear();
            (filas || []).forEach(f => store.put(f));
            t.oncomplete = () => resolve();
            t.onerror    = () => reject(t.error);
        }));
    }

    function put(nombre, fila) {
        return conStore(nombre, 'readwrite').then(store => new Promise((resolve, reject) => {
            const req = store.put(fila);
            req.onsuccess = () => resolve();
            req.onerror   = () => reject(req.error);
        }));
    }

    function del(nombre, key) {
        return conStore(nombre, 'readwrite').then(store => new Promise((resolve, reject) => {
            const req = store.delete(key);
            req.onsuccess = () => resolve();
            req.onerror   = () => reject(req.error);
        }));
    }

    // ── Catálogo ─────────────────────────────────────────────────────────────
    async function actualizarCatalogo(baseUrl) {
        const r = await fetch(`${baseUrl}/ventas/catalogo-offline`);
        if (!r.ok) throw new Error('No se pudo descargar el catálogo');
        const data = await r.json();
        await clearYCargar('productos', data.productos);
        await clearYCargar('categorias', data.categorias);
        return data;
    }

    function categoriasLocal() {
        return getAll('categorias');
    }

    async function buscarProductosLocal(q) {
        const productos = await getAll('productos');
        const qNorm = q.toLowerCase();
        return productos
            .filter(p => p.nombre.toLowerCase().includes(qNorm) || (p.codigo || '').toLowerCase().includes(qNorm))
            .slice(0, 30);
    }

    async function productosPorCategoriaLocal(categoriaId) {
        const productos = await getAll('productos');
        return productos.filter(p => String(p.categoria_id) === String(categoriaId));
    }

    // Descuento optimista: para que la próxima venta offline en la misma sesión
    // ya vea el stock actualizado. El descuento real/definitivo lo hace el
    // servidor recién cuando se sincroniza.
    function descontarStockLocal(productoId, cantidad) {
        return conStore('productos', 'readwrite').then(store => new Promise((resolve, reject) => {
            const req = store.get(productoId);
            req.onsuccess = () => {
                const p = req.result;
                // Sin controla_stock (ej. café) no se descuenta nada: se vende como siempre disponible
                if (!p || !p.controla_stock) return resolve();
                p.stock_actual = Math.max(0, parseFloat(p.stock_actual) - cantidad);
                const putReq = store.put(p);
                putReq.onsuccess = () => resolve();
                putReq.onerror   = () => reject(putReq.error);
            };
            req.onerror = () => reject(req.error);
        }));
    }

    // ── Ventas pendientes de sincronizar ─────────────────────────────────────
    async function guardarVentaPendiente(payload) {
        const local_id = 'local_' + Date.now() + '_' + Math.random().toString(36).slice(2, 8);
        const venta = Object.assign({ local_id, creada_en: new Date().toISOString() }, payload);
        await put('ventas_pendientes', venta);
        return venta;
    }

    async function listarVentasPendientes() {
        const ventas = await getAll('ventas_pendientes');
        return ventas.sort((a, b) => (a.creada_en < b.creada_en ? -1 : 1));
    }

    async function contarVentasPendientes() {
        return (await getAll('ventas_pendientes')).length;
    }

    function eliminarVentaPendiente(localId) {
        return del('ventas_pendientes', localId);
    }

    // Sincronización 100% manual: se llama únicamente cuando el usuario aprieta
    // "Sincronizar ahora", nunca sola en segundo plano.
    async function sincronizarTodo(baseUrl, csrfToken, onItem) {
        const pendientes = await listarVentasPendientes();
        const resultado = { ok: 0, error: 0 };

        for (const venta of pendientes) {
            try {
                const fd = new FormData();
                fd.set('_csrf_token', csrfToken);
                fd.set('items_json', JSON.stringify(venta.items));
                fd.set('cliente_id', venta.cliente_id || '');
                fd.set('descuento_global', venta.descuento_global || 0);
                fd.set('metodo_pago', venta.metodo_pago || 'efectivo');
                fd.set('notas', venta.notas || '');
                fd.set('mesa', venta.mesa || '');
                fd.set('facturar_afip', '0');
                fd.set('confirmar_sin_stock', '1');

                const r = await fetch(`${baseUrl}/ventas/guardar`, { method: 'POST', body: fd });
                const d = await r.json();
                if (d.ok) {
                    await eliminarVentaPendiente(venta.local_id);
                    resultado.ok++;
                    if (onItem) onItem(venta, true, null);
                } else {
                    resultado.error++;
                    if (onItem) onItem(venta, false, d.error || 'Error desconocido');
                }
            } catch (e) {
                resultado.error++;
                if (onItem) onItem(venta, false, 'Sin conexión');
                break; // se cortó la red de nuevo: dejamos el resto para el próximo intento
            }
        }

        return resultado;
    }

    return {
        actualizarCatalogo, categoriasLocal, buscarProductosLocal, productosPorCategoriaLocal,
        descontarStockLocal, guardarVentaPendiente, listarVentasPendientes, contarVentasPendientes,
        eliminarVentaPendiente, sincronizarTodo,
    };
})();
