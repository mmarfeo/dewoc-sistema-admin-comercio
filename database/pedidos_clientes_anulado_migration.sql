-- Al anular en el POS la venta resultante de un pedido del Portal de Clientes Mayoristas,
-- el pedido debe dejar de figurar como "Convertido" en /portal/pedidos. Antes no había
-- ningún estado que representara esto: el pedido quedaba "convertido" para siempre, aunque
-- la venta ya no existiera. Ejecutar una sola vez.

ALTER TABLE `pedidos_clientes`
  MODIFY COLUMN `estado` ENUM('pendiente','aceptado','rechazado','convertido','anulado') NOT NULL DEFAULT 'pendiente';
