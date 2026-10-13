-- Módulo: Solicitudes de Inventario (puntuales y recurrentes).
-- Agrega un tipo nuevo 'inventario' a `solicitudes`: en vez de pedir un ítem puntual
-- para comprar, el admin arma un pedido de recuento eligiendo categorías y/o ítems
-- concretos (producto/materia_prima/insumo/ingrediente_generico), que después filtra
-- lo que se ve en /stock/inventario. Las plantillas recurrentes (`solicitudes_recurrentes`)
-- generan esa solicitud solas cada semana, mismo patrón que `gastos_recurrentes` pero
-- por día de la semana en vez de día del mes.
-- Ejecutar una sola vez desde phpMyAdmin.

-- ── Plantillas recurrentes (se crean antes por la FK de solicitudes.recurrente_id) ──
CREATE TABLE IF NOT EXISTS `solicitudes_recurrentes` (
  `id`                     INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `descripcion`            VARCHAR(255)  NOT NULL COMMENT 'Ej. "Inventario semanal — Helados"',
  `dia_semana`             TINYINT UNSIGNED NOT NULL COMMENT '1=lunes .. 7=domingo (ISO-8601, ver date(''N''))',
  `prioridad`              ENUM('baja','media','alta') NOT NULL DEFAULT 'media',
  `asignado_a`             INT UNSIGNED  DEFAULT NULL COMMENT 'NULL = abierta a cualquiera',
  `activo`                 TINYINT(1)    NOT NULL DEFAULT 1 COMMENT 'Suspender sin borrar',
  `ultima_fecha_generada`  DATE          DEFAULT NULL COMMENT 'Lunes de la última semana en que ya se generó',
  `usuario_id`             INT UNSIGNED  NOT NULL COMMENT 'Quién creó la plantilla',
  `eliminado`              TINYINT(1)    NOT NULL DEFAULT 0,
  `created_at`             DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`             DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_solrec_activo` (`activo`),
  CONSTRAINT `fk_solrec_asignado` FOREIGN KEY (`asignado_a`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_solrec_usuario`  FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Criterios de la plantilla (se copian a `solicitudes_inventario_detalle` en cada
-- generación). Sin FK a `referencia_id` porque es polimórfico según `tipo_criterio`
-- (mismo criterio que ya usa `inventarios_detalle.item_id` en schema.sql).
CREATE TABLE IF NOT EXISTS `solicitudes_recurrentes_detalle` (
  `id`                     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `solicitud_recurrente_id` INT UNSIGNED NOT NULL,
  `tipo_criterio`          ENUM('categoria','producto','materia_prima','insumo','ingrediente_generico') NOT NULL,
  `referencia_id`          INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_solrecdet_plantilla` (`solicitud_recurrente_id`),
  CONSTRAINT `fk_solrecdet_plantilla` FOREIGN KEY (`solicitud_recurrente_id`) REFERENCES `solicitudes_recurrentes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Ampliar `solicitudes` ────────────────────────────────────────────────────
ALTER TABLE `solicitudes` MODIFY `tipo` ENUM('producto','materia_prima','tarea','inventario') NOT NULL;

ALTER TABLE `solicitudes`
  ADD COLUMN IF NOT EXISTS `asignado_a` INT UNSIGNED NULL DEFAULT NULL
    COMMENT 'Solo tipo=inventario: NULL = abierta a cualquiera' AFTER `usuario_id`;
ALTER TABLE `solicitudes`
  ADD CONSTRAINT `fk_sol_asignado` FOREIGN KEY (`asignado_a`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;
-- Nota: si da error porque ya existe la columna/constraint, ignorar estos ALTER.

ALTER TABLE `solicitudes`
  ADD COLUMN IF NOT EXISTS `recurrente_id` INT UNSIGNED NULL DEFAULT NULL
    COMMENT 'Plantilla que generó esta solicitud (si aplica)' AFTER `asignado_a`;
ALTER TABLE `solicitudes`
  ADD CONSTRAINT `fk_sol_recurrente` FOREIGN KEY (`recurrente_id`) REFERENCES `solicitudes_recurrentes` (`id`) ON DELETE SET NULL;
-- Nota: si da error porque ya existe la columna/constraint, ignorar estos ALTER.

-- `item_id`/`item_nombre` siguen NOT NULL/DEFAULT NULL como ya estaban: para
-- tipo=inventario, item_id queda NULL e item_nombre se arma automáticamente
-- (ej. "Categoría: Helados, Insumo: Servilletas") — no hace falta tocar esas columnas.

-- Criterios de una solicitud de inventario puntual (o ya generada desde una plantilla).
CREATE TABLE IF NOT EXISTS `solicitudes_inventario_detalle` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `solicitud_id`  INT UNSIGNED NOT NULL,
  `tipo_criterio` ENUM('categoria','producto','materia_prima','insumo','ingrediente_generico') NOT NULL,
  `referencia_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_soldet_solicitud` (`solicitud_id`),
  CONSTRAINT `fk_soldet_solicitud` FOREIGN KEY (`solicitud_id`) REFERENCES `solicitudes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
