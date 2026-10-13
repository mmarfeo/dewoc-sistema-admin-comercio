-- ============================================================
--  Proyecciones — Fase 1 (ver docs/prediccion-ventas-compras.md)
--  Proyección de ventas/compra de materia prima/margen por producto,
--  generada por cron (cron/proyecciones_generar.php), no en vivo.
--  Ejecutar una vez sobre la base de datos existente.
-- ============================================================

-- La tabla `feriados` ya existe (database/feriados_setup.sql) y hoy la usa
-- Sueldos/Empleados para el recargo de jornadas. Se reutiliza acá agregándole
-- `tipo`, nullable para no romper los feriados ya cargados a mano sin clasificar
-- (esos NULL simplemente no se usan para ponderar Proyecciones).
ALTER TABLE `feriados`
  ADD COLUMN `tipo` ENUM('inamovible','trasladable','turistico') NULL
    COMMENT 'NULL = cargado a mano sin clasificar, no se usa para ponderar Proyecciones'
    AFTER `nombre`;

-- Configuración de Proyecciones (fila única, mismo patrón que configuracion_fiscal).
CREATE TABLE IF NOT EXISTS `proyecciones_config` (
  `id`             TINYINT UNSIGNED NOT NULL,
  `horizonte_dias` SMALLINT UNSIGNED NOT NULL DEFAULT 14,
  `usuario_id`     INT UNSIGNED DEFAULT NULL,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_proycfg_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `proyecciones_config` (`id`, `horizonte_dias`) VALUES (1, 14);

-- Snapshot generado por el cron. No se purgan corridas viejas: el volumen es
-- acotado para un comercio individual y la Fase 2 (fuera de este alcance, ver
-- el doc) va a necesitar las corridas anteriores para comparar contra lo real.
-- La lectura siempre toma la corrida más nueva: WHERE DATE(created_at) =
-- (SELECT MAX(DATE(created_at)) FROM proyecciones).
CREATE TABLE IF NOT EXISTS `proyecciones` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tipo`             ENUM('venta','compra_mp','margen') NOT NULL,
  `entidad_tipo`     ENUM('producto','materia_prima','insumo') NOT NULL,
  `entidad_id`       INT UNSIGNED NOT NULL,
  `fecha_desde`      DATE NOT NULL,
  `fecha_hasta`      DATE NOT NULL,
  `valor_proyectado` DECIMAL(14,4) NOT NULL,
  `metodo`           ENUM('promedio_movil_ponderado','regresion_lineal','ml_externo') NOT NULL,
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_proy_tipo_entidad` (`tipo`, `entidad_tipo`, `entidad_id`),
  KEY `idx_proy_created`      (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
