-- Historial de equivalencias entre nombres de tickets de proveedores e ítems del sistema.
-- Permite que el chat IA recuerde automáticamente a qué ítem corresponde cada nombre
-- usado por un proveedor específico (ej: "AZUCAR COMUN TIPO A" de DIA = "Azúcar" en el sistema).
CREATE TABLE IF NOT EXISTS `proveedor_item_alias` (
  `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `proveedor_id`  INT UNSIGNED  NOT NULL,
  `nombre_ticket` VARCHAR(255)  NOT NULL COMMENT 'Nombre del ítem en minúsculas, tal como aparece en el ticket',
  `tipo_item`     ENUM('materia_prima','insumo','producto') NOT NULL DEFAULT 'materia_prima',
  `item_id`       INT UNSIGNED  NOT NULL,
  `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_prov_ticket` (`proveedor_id`, `nombre_ticket`),
  KEY `idx_pia_proveedor` (`proveedor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Equivalencias entre nombres en tickets de proveedores e ítems del sistema (aprendizaje del chat IA)';
