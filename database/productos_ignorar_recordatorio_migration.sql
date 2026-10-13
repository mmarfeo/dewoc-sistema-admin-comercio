-- Permite marcar productos de reventa para que el Chat IA deje de recordarlos como
-- "incompletos" (sin precio de venta y/o sin categoría) — ver
-- database/recordatorio_productos_incompletos_migration.sql para el recordatorio original.
--
-- Motivo: el recordatorio existente (ProductoModel::findIncompletos()) solo se puede
-- posponer un día entero por usuario (columna usuarios.recordatorio_productos_fecha),
-- pero hay productos que se compraron una sola vez y el usuario ya sabe que nunca los va
-- a dar de alta/activar para la venta — no tiene sentido que sigan apareciendo cada día.
-- Este flag es por producto y permanente (a diferencia del throttle diario, que es
-- global y se resetea solo). Se marca desde el Chat vía la tool "ignorar_producto_recordatorio"
-- (ChatController::toolIgnorarProductoRecordatorio()), con tarjeta de confirmación como
-- el resto de las acciones (ver pedir_confirmacion en obtenerHerramientas()).
--
-- Ejecutar una sola vez desde phpMyAdmin/Hostinger.

ALTER TABLE `productos`
  ADD COLUMN `ignorar_recordatorio` TINYINT(1) NOT NULL DEFAULT 0
    COMMENT '1 = el usuario pidió no ser recordado más de este producto incompleto (Chat IA) — no afecta activo/precio_venta.'
    AFTER `activo`;
-- Nota: si da error porque ya existe la columna, ignorar este ALTER.
