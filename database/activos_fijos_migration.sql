-- Activos fijos (ver docs/activos-fijos-diseno.md) — registro de bienes de uso (horno,
-- cafetera, mobiliario) con su valor de compra y, opcionalmente, amortización mensual
-- automática (reusa el motor de gastos_recurrentes, categoría "Amortizaciones" ya
-- precargada en gastos_setup.sql).
-- Ejecutar una sola vez desde phpMyAdmin.

CREATE TABLE IF NOT EXISTS `activos_fijos` (
  `id`                  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `nombre`              VARCHAR(150)  NOT NULL,
  `categoria`           VARCHAR(80)   DEFAULT NULL COMMENT 'Agrupación libre (ej. "Cocina", "Mobiliario") — texto simple, no una tabla aparte',
  `origen`              ENUM('compra_nueva','preexistente') NOT NULL,
  `fecha_alta`          DATE          NOT NULL COMMENT 'Fecha de compra real (compra_nueva) o fecha en que se carga el registro (preexistente)',
  `valor`               DECIMAL(12,2) NOT NULL COMMENT 'Costo histórico, fijo — nunca se recalcula. El valor actual se calcula al vuelo restando lo ya amortizado',
  `vida_util_meses`     SMALLINT UNSIGNED DEFAULT NULL COMMENT 'Meses a amortizar desde fecha_alta. NULL = no amortiza, queda solo valuado',
  `proveedor_id`        INT UNSIGNED  DEFAULT NULL,
  `gasto_recurrente_id` INT UNSIGNED  DEFAULT NULL COMMENT 'Plantilla de amortización generada en gastos_recurrentes, si vida_util_meses está cargado',
  `estado`              ENUM('activo','baja','vendido') NOT NULL DEFAULT 'activo',
  `notas`               TEXT          DEFAULT NULL,
  `usuario_id`          INT UNSIGNED  NOT NULL,
  `created_at`          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `eliminado`           TINYINT(1)    NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_activo_estado` (`estado`),
  CONSTRAINT `fk_activo_proveedor`  FOREIGN KEY (`proveedor_id`)        REFERENCES `proveedores` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_activo_recurrente` FOREIGN KEY (`gasto_recurrente_id`) REFERENCES `gastos_recurrentes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_activo_usuario`    FOREIGN KEY (`usuario_id`)          REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tope de repeticiones para una plantilla de gasto recurrente — hoy `gastos_recurrentes`
-- solo sabe "repetir para siempre hasta que se desactive a mano" (ver RecurrenteModel::
-- generarPendientes()). Esto es puramente aditivo: NULL (el default para todas las
-- plantillas existentes, sueldos/alquiler/etc.) mantiene el comportamiento de siempre sin
-- ningún cambio — solo si se carga un número, la plantilla se apaga sola al llegar a esa
-- cantidad de gastos generados (se cuenta contra `gastos.recurrente_id` real, no un
-- contador aparte, para no desincronizarse si algún gasto se edita/borra a mano después).
ALTER TABLE `gastos_recurrentes`
  ADD COLUMN `repeticiones_maximas` SMALLINT UNSIGNED NULL
    COMMENT 'NULL = repite indefinidamente (de siempre). Si se carga, se desactiva sola al generar esa cantidad de gastos.'
    AFTER `activo`;
