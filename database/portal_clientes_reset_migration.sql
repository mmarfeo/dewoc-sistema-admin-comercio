-- Cambio de contraseña para el Portal de Clientes Mayoristas: agrega a `clientes` las mismas
-- columnas de reset-token que ya tiene `usuarios` (ver AuthController::recuperar()), para poder
-- reusar el mismo esquema de "olvidé mi contraseña" del lado del cliente. Ejecutar una sola vez
-- desde phpMyAdmin — el ADD COLUMN lleva "IF NOT EXISTS" así que un reintento no rompe nada.

ALTER TABLE `clientes`
  ADD COLUMN IF NOT EXISTS `reset_token`   VARCHAR(64) DEFAULT NULL AFTER `portal_activo`,
  ADD COLUMN IF NOT EXISTS `reset_expires` DATETIME    DEFAULT NULL AFTER `reset_token`;
