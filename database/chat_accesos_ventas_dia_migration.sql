-- Convierte los accesos directos YA EXISTENTES "Ventas del dia" (id=13) y "🔍 Ventas de
-- Ayer" (id=15) para que usen el bypass de Gemini (accion_interna) en vez de mandarle un
-- prompt libre al modelo — hoy ambos van por Gemini con accion_interna=NULL, que es
-- justamente la causa del bug reportado: el modelo mezcla en una sola cifra las unidades
-- de un producto vendido suelto con las unidades entregadas como componente de una promo
-- (precio $0, ya cobradas dentro del precio de la promo), inflando el total narrado.
--
-- Se actualizan por ID (no por label) porque ya existen en la base con exactamente esos
-- ids — confirmado por consulta directa en Hostinger — y así no hace falta re-crear la fila
-- ni reasignarla a usuarios (ya están asignadas). Ver ChatController::accionVentasDia() y
-- ReportesModel::ventasPorProductoDia(). Ejecutar una sola vez desde phpMyAdmin/Hostinger.

UPDATE `chat_accesos_directos` SET `accion_interna` = 'ventas_hoy'  WHERE `id` = 13;
UPDATE `chat_accesos_directos` SET `accion_interna` = 'ventas_ayer' WHERE `id` = 15;

-- Arreglo de paso, sin relación con lo de arriba: el acceso id=3 ("🏆 Top 5 productos del
-- mes") debería tener accion_interna='top_productos' desde chat_accesos_accion_interna_migration.sql,
-- pero esa migración vieja hacía "WHERE label = '🏆 Top 5 productos'" (sin "del mes") — en
-- algún momento se le agregó "del mes" al label desde /chat/accesos y ese UPDATE dejó de
-- matchear, así que quedó yendo por Gemini de más en vez de por el bypass ya implementado.
UPDATE `chat_accesos_directos` SET `accion_interna` = 'top_productos' WHERE `id` = 3;

-- Nota: si algún UPDATE de los de arriba afecta 0 filas, es porque el id cambió desde que
-- se armó esta migración — volver a confirmar el id correcto con:
--   SELECT id, label, texto, accion_interna FROM chat_accesos_directos ORDER BY id;
