-- ============================================================
-- Limpieza total de Recetas — para poder reimportar desde cero
-- sin recetas ni ingredientes duplicados.
--
-- Borra: recetas, su detalle (ingredientes), historial de costo,
-- versiones guardadas, la asignación a productos y las órdenes
-- de producción asociadas.
--
-- NO toca: productos, materias primas, insumos, ingredientes
-- genéricos ni el stock actual de productos (si alguna orden de
-- producción ya había sumado stock a un producto, ese stock
-- queda como está — esto solo borra el registro histórico de la
-- receta/producción, no revierte movimientos de stock).
--
-- ⚠️ Irreversible. Hacé un backup antes (Herramientas → Exportar
-- en phpMyAdmin, o mysqldump) por si hace falta recuperar algo.
-- Ejecutar manualmente, una sola vez, antes de reimportar.
-- ============================================================

START TRANSACTION;

-- Órdenes de producción: no tienen ON DELETE CASCADE desde recetas,
-- así que hay que borrarlas explícitamente antes o la FK bloquea el DELETE.
DELETE FROM ordenes_produccion;

-- Historial de costo (cascada desde recetas, pero se borra explícito por prolijidad)
DELETE FROM recetas_historial_costo;

-- Versiones guardadas de cada receta
DELETE FROM recetas_versiones;

-- Asignación receta ↔ producto (pivote)
DELETE FROM producto_receta;

-- Detalle de ingredientes de cada receta (incluye sub-recetas)
DELETE FROM receta_detalle;

-- Las recetas en sí
DELETE FROM recetas;

COMMIT;

-- Reiniciar los autoincrementales para que las próximas recetas importadas
-- arranquen de nuevo desde el id 1 (opcional — comentá estas líneas si
-- preferís conservar la numeración de ids existente).
ALTER TABLE recetas AUTO_INCREMENT = 1;
ALTER TABLE receta_detalle AUTO_INCREMENT = 1;
ALTER TABLE recetas_versiones AUTO_INCREMENT = 1;
ALTER TABLE recetas_historial_costo AUTO_INCREMENT = 1;
ALTER TABLE ordenes_produccion AUTO_INCREMENT = 1;
