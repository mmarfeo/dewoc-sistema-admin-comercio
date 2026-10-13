-- ============================================================
--  Módulo: Costos Fijos Recurrentes (Alquiler, Seguros, Servicios,
--  Honorarios, Sueldos fijos mensuales, etc.)
--
--  Genera automáticamente el gasto/sueldo del mes cuando llega el
--  día configurado, sin que el usuario tenga que crearlo a mano.
--  El monto queda editable en cada registro generado por si hubo
--  un aumento de precio o de consumo. Se puede suspender/reactivar
--  cada plantilla sin borrarla (por ejemplo, si se dejó de usar un
--  servicio o se cambió de proveedor).
--
--  La generación es "perezosa": no corre un cron en el servidor,
--  se dispara sola al primer login del día (ver Controller::requireAuth()).
--
--  Ejecutar una vez sobre la base de datos existente.
-- ============================================================

CREATE TABLE IF NOT EXISTS `gastos_recurrentes` (
  `id`                      INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `tipo`                    ENUM('gasto','sueldo') NOT NULL DEFAULT 'gasto',
  `descripcion`             VARCHAR(255)  NOT NULL,
  `categoria_id`            INT(11)       DEFAULT NULL COMMENT 'Requerido si tipo = gasto',
  `proveedor_id`            INT UNSIGNED  DEFAULT NULL COMMENT 'Opcional si tipo = gasto',
  `empleado_id`             INT UNSIGNED  DEFAULT NULL COMMENT 'Requerido si tipo = sueldo (solo modalidad mensual/quincenal)',
  `monto`                   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `dia_generacion`          TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Día del mes (1-28) en que se genera',
  `activo`                  TINYINT(1)    NOT NULL DEFAULT 1 COMMENT 'Suspender sin borrar (servicio dado de baja, cambio de proveedor, etc.)',
  `ultimo_periodo_generado` CHAR(7)       DEFAULT NULL COMMENT 'YYYY-MM del último período ya generado',
  `notas`                   VARCHAR(255)  DEFAULT NULL,
  `usuario_id`              INT UNSIGNED  NOT NULL,
  `eliminado`               TINYINT(1)    NOT NULL DEFAULT 0,
  `created_at`              TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`              TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_gr_activo` (`activo`),
  CONSTRAINT `fk_gr_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `gasto_categorias` (`id`),
  CONSTRAINT `fk_gr_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_gr_empleado`  FOREIGN KEY (`empleado_id`)  REFERENCES `empleados` (`id`)  ON DELETE CASCADE,
  CONSTRAINT `fk_gr_usuario`   FOREIGN KEY (`usuario_id`)   REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Trazabilidad: qué gasto/pago fue generado automáticamente y desde qué plantilla
ALTER TABLE `gastos`
  ADD COLUMN IF NOT EXISTS `recurrente_id` INT UNSIGNED NULL DEFAULT NULL AFTER `proveedor_id`;
ALTER TABLE `gastos`
  ADD CONSTRAINT `fk_gasto_recurrente` FOREIGN KEY (`recurrente_id`) REFERENCES `gastos_recurrentes` (`id`) ON DELETE SET NULL;
-- Nota: si da error porque ya existe la columna/constraint, ignorar estos ALTER.

ALTER TABLE `pagos_proveedores`
  ADD COLUMN IF NOT EXISTS `recurrente_id` INT UNSIGNED NULL DEFAULT NULL AFTER `empleado_id`;
ALTER TABLE `pagos_proveedores`
  ADD CONSTRAINT `fk_pago_recurrente` FOREIGN KEY (`recurrente_id`) REFERENCES `gastos_recurrentes` (`id`) ON DELETE SET NULL;
-- Nota: si da error porque ya existe la columna/constraint, ignorar estos ALTER.
