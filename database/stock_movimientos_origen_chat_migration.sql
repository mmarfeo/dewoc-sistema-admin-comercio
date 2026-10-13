-- Suma 'chat_ia' a los orígenes válidos de stock_movimientos. Hasta ahora todo ajuste de
-- stock (venga del modal web o del Chat IA) se guardaba con origen='ajuste_manual', sin
-- forma de distinguir uno de otro — por eso "¿cuándo fue el último ajuste de stock hecho
-- desde el Chat IA?" no se podía responder ni con SQL libre (ver StockModel::ajustar() /
-- ChatController::toolAjustarStock()). Ejecutar una sola vez desde phpMyAdmin.

ALTER TABLE `stock_movimientos`
  MODIFY COLUMN `origen` ENUM('compra','venta','produccion','ajuste_manual','inventario','apertura','chat_ia') NOT NULL;
