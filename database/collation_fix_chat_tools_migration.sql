-- ============================================================
--  Corrige el mismo problema de collation ya documentado en
--  database/socios_migration.sql: en servidores MySQL/MariaDB nuevos
--  el collation por defecto de utf8mb4 cambió a utf8mb4_uca1400_ai_ci,
--  distinto al utf8mb4_unicode_ci que usa `pagos_proveedores` (fijado
--  explícito) y otras tablas migradas a mano. Las tablas de abajo
--  declaran solo DEFAULT CHARSET=utf8mb4 sin COLLATE, así que quedaron
--  con el collation "de servidor" — al mezclarlas con pagos_proveedores
--  en un UNION/COALESCE (ej. ChatController::toolResumenComprasPagos())
--  MySQL tira "Illegal mix of collations for operation 'UNION'".
--  Ejecutar una vez sobre la base de datos existente.
-- ============================================================

ALTER TABLE `usuarios`         CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `proveedores`      CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `empleados`        CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `facturas_compra`  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
