-- Permite al admin bloquear globalmente el checkbox "Facturar por AFIP" del POS,
-- para que ningún cajero pueda facturar ni por error (ej. al acercarse al límite
-- de la categoría de monotributo). El bloqueo se valida también en el servidor
-- (VentasController::guardar()), no es solo un candado visual.
-- Ejecutar una sola vez desde phpMyAdmin.

ALTER TABLE `configuracion_fiscal`
  ADD COLUMN `bloquear_facturacion_afip` TINYINT(1) NOT NULL DEFAULT 0 AFTER `categoria_monotributo`;
-- Nota: si da error porque ya existe la columna, ignorar este ALTER.
