-- Bypass de Gemini para los accesos directos de solo lectura (ver
-- docs/chat-accesos-sin-llm-analisis.md). `accion_interna` es la clave que usa
-- ChatController::accionDirecta() para resolver la respuesta con una consulta PHP directa
-- en vez de pasar por el loop de function-calling. VARCHAR, no ENUM: una clave nueva se
-- agrega por código (match() del controller), no amerita una migración de esquema.
-- Los accesos sin accion_interna (custom del admin, o los de acción como "Nuevo cliente")
-- siguen yendo por Gemini exactamente como hoy. Ejecutar una sola vez desde phpMyAdmin.

ALTER TABLE `chat_accesos_directos`
  ADD COLUMN `accion_interna` VARCHAR(40) NULL
    COMMENT 'Clave para bypass de Gemini (ver ChatController::accionDirecta()). NULL = sigue yendo por Gemini.'
    AFTER `texto`;

UPDATE `chat_accesos_directos` SET `accion_interna` = 'ventas_semana'    WHERE `label` = '📊 Ventas esta semana';
UPDATE `chat_accesos_directos` SET `accion_interna` = 'ganancia_mensual' WHERE `label` = '💰 Ganancia bruta mensual';
UPDATE `chat_accesos_directos` SET `accion_interna` = 'top_productos'    WHERE `label` = '🏆 Top 5 productos';
UPDATE `chat_accesos_directos` SET `accion_interna` = 'stock_bajo_minimo' WHERE `label` = '📦 Stock bajo mínimo';
UPDATE `chat_accesos_directos` SET `accion_interna` = 'compras_mes'      WHERE `label` = '🛒 Compras del mes';
UPDATE `chat_accesos_directos` SET `accion_interna` = 'caja_hoy'         WHERE `label` = '🏧 Movimiento de caja hoy';
UPDATE `chat_accesos_directos` SET `accion_interna` = 'clientes_nuevos'  WHERE `label` = '👥 Clientes nuevos';
