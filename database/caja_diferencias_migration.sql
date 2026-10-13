-- Cierre de caja ciego: separa efectivo de Mercado Pago (y otros medios) en el
-- cálculo de lo esperado en caja, y guarda el desglose de lo declarado al cerrar.
-- Ejecutar una sola vez desde phpMyAdmin.

-- Antes de esto, todo ingreso de venta se contaba como si fuera efectivo físico
-- sin importar el medio de pago (débito/crédito/transferencia/Mercado Pago se
-- sumaban igual que efectivo al calcular la diferencia de caja).
ALTER TABLE `caja_movimientos`
  ADD COLUMN IF NOT EXISTS `metodo_pago`
    ENUM('efectivo','debito','credito','transferencia','mercadopago') DEFAULT NULL
    COMMENT 'Medio de pago de la venta que originó el ingreso. NULL = movimiento manual, se trata como efectivo físico.'
    AFTER `tipo`;

-- Backfill para movimientos ya existentes de turnos que sigan abiertos al migrar.
UPDATE `caja_movimientos` cm
JOIN `ventas` v ON v.id = cm.venta_id
SET cm.metodo_pago = v.metodo_pago
WHERE cm.venta_id IS NOT NULL AND cm.metodo_pago IS NULL;

-- Desglose de la declaración de cierre (efectivo/Mercado Pago) + flag de si fue
-- un cierre ciego. Las columnas existentes monto_cierre_declarado/
-- monto_cierre_calculado/diferencia se mantienen (pasan a representar
-- efectivo+MP en vez de todos los medios mezclados) para no romper
-- reportes/caja.php ni caja/historial.php.
ALTER TABLE `turnos`
  ADD COLUMN IF NOT EXISTS `monto_cierre_declarado_efectivo` DECIMAL(14,2) DEFAULT NULL AFTER `monto_cierre_declarado`,
  ADD COLUMN IF NOT EXISTS `monto_cierre_declarado_mp`       DECIMAL(14,2) DEFAULT NULL AFTER `monto_cierre_declarado_efectivo`,
  ADD COLUMN IF NOT EXISTS `monto_cierre_calculado_efectivo` DECIMAL(14,2) DEFAULT NULL AFTER `monto_cierre_calculado`,
  ADD COLUMN IF NOT EXISTS `monto_cierre_calculado_mp`       DECIMAL(14,2) DEFAULT NULL AFTER `monto_cierre_calculado_efectivo`,
  ADD COLUMN IF NOT EXISTS `diferencia_efectivo`             DECIMAL(14,2) DEFAULT NULL AFTER `diferencia`,
  ADD COLUMN IF NOT EXISTS `diferencia_mp`                   DECIMAL(14,2) DEFAULT NULL AFTER `diferencia_efectivo`,
  ADD COLUMN IF NOT EXISTS `cierre_ciego`
    TINYINT(1) NOT NULL DEFAULT 0
    COMMENT 'El usuario que cerró no tenía permiso editar en caja (declaró sin ver el esperado)'
    AFTER `diferencia_mp`;
