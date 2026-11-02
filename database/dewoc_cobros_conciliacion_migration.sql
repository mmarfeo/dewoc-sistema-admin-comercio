-- Dewoc Cobros: validación de ventas con los cobros recibidos (ver
-- app/Models/CobroRecibidoModel.php, conciliarCobro() y conciliarVenta()).
-- Requiere database/dewoc_cobros_migration.sql. Idempotente. Ejecutar desde phpMyAdmin.

-- Un cobro valida UNA venta y una venta tiene UN cobro que la valida. MySQL no cuenta los
-- NULL como duplicados, así que los cobros sin venta no chocan entre sí.
ALTER TABLE `cobros_recibidos`
  ADD UNIQUE KEY IF NOT EXISTS `uq_cobros_recibidos_venta` (`venta_id`);

-- Para buscar rápido el cobro que valida una venta del mismo monto.
ALTER TABLE `cobros_recibidos`
  ADD KEY IF NOT EXISTS `idx_cobros_recibidos_conciliar` (`resultado`, `venta_id`, `monto`, `recibido_at`);
