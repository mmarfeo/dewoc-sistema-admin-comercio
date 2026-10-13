-- La factura de compra vuelve a incluir en su total (total_con_iva) el valor de los ítems
-- marcados "Es un activo fijo" — así la deuda con el proveedor (Pagos a Proveedores/Cuenta
-- Corriente) queda correcta cuando se compra un bien de uso a crédito. Para que ese valor NO
-- se cuente dos veces como "Compras" en el Resumen Económico, se guarda aparte en esta columna
-- y GastoModel::estadoResultados()/resumenMensual() lo restan al calcular el costo de compras.
-- Ejecutar una sola vez desde phpMyAdmin.

ALTER TABLE `facturas_compra`
  ADD COLUMN `monto_activos_fijos` DECIMAL(12,2) NOT NULL DEFAULT 0
    COMMENT 'Suma de los ítems de esta factura cargados como Activo Fijo — ya incluida en total_con_iva; se resta al calcular "Compras" en el Resumen Económico para no contarla dos veces'
    AFTER `total_con_iva`;
