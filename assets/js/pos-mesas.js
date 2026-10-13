/**
 * PosMesas — persistencia en localStorage de las "mesas" (pestañas) abiertas
 * en el POS (ventas/pos.php): ventas que el cajero cargó pero todavía no
 * cobró, para poder dejarlas en pausa y seguir vendiendo otras.
 *
 * No conoce el DOM ni la forma exacta de una "mesa" — solo guarda/lee el
 * objeto { activeId, tabs } que le pase pos.php. Si localStorage no está
 * disponible (modo privado restrictivo, storage lleno, etc.) degrada a
 * memoria de esta misma sesión en vez de romper el POS.
 */
const PosMesas = (function () {
    const KEY = 'dewoc_pos_mesas_v1';
    let memoriaFallback = null;

    function generarId() {
        return 'mesa_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 8);
    }

    function cargar() {
        try {
            const raw = localStorage.getItem(KEY);
            if (!raw) return memoriaFallback;
            return JSON.parse(raw);
        } catch {
            return memoriaFallback;
        }
    }

    function guardarTodo(estado) {
        memoriaFallback = estado;
        try {
            localStorage.setItem(KEY, JSON.stringify(estado));
        } catch {
            // localStorage no disponible: se sigue trabajando en memoria (memoriaFallback)
        }
    }

    return { generarId, cargar, guardarTodo };
})();
