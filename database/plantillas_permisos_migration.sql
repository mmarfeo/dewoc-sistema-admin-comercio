-- ============================================================
--  Plantillas de permisos ("Permission Sets" estilo Salesforce) — ver plan de la
--  conversación que armó esto. Desacopla "conjunto de permisos" de "rol": un Rol pasa a
--  tener una plantilla POR DEFECTO (heredada por todos sus usuarios), y cada usuario
--  puede además tener una plantilla PUNTUAL propia que manda por sobre la de su rol —
--  más las excepciones sueltas de siempre en `permisos_usuario` (ahora con vencimiento
--  opcional) por encima de todo.
--
--  Jerarquía de resolución (ver UsuarioModel::getPermisosEfectivos()):
--    1. Plantilla puntual del usuario (usuarios.plantilla_permisos_id)
--    2. Plantilla por defecto de su rol (roles.plantilla_permisos_id_default)
--    3. Matriz vieja por rol_id (tabla `permisos`) — solo como red de seguridad, no
--       debería usarse nunca después de esta migración.
--    + excepciones puntuales de permisos_usuario (otorgan o quitan, con vencimiento
--      opcional) por encima de cualquiera de los 3 niveles de arriba.
--
--  Ejecutar una sola vez desde phpMyAdmin. Es seguro re-ejecutar: los CREATE usan
--  IF NOT EXISTS y los INSERT de backfill solo insertan roles que todavía no tengan
--  su plantilla creada (evita duplicar si se corre dos veces).
-- ============================================================

-- ── 1. Esquema ──────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `plantillas_permisos` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`      VARCHAR(100) NOT NULL,
  `descripcion` VARCHAR(255) DEFAULT NULL,
  `activo`      TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_plantilla_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `plantilla_permisos_detalle` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `plantilla_id` INT UNSIGNED NOT NULL,
  `modulo`       VARCHAR(50)  NOT NULL,
  `ver`          TINYINT(1)   NOT NULL DEFAULT 0,
  `editar`       TINYINT(1)   NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ppd` (`plantilla_id`, `modulo`),
  CONSTRAINT `fk_ppd_plantilla` FOREIGN KEY (`plantilla_id`) REFERENCES `plantillas_permisos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Columnas nuevas (protegidas contra doble ejecución vía procedimiento — ADD COLUMN no
-- soporta IF NOT EXISTS en todas las versiones de MySQL/MariaDB de hosting compartido).
SET @col_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'plantilla_permisos_id'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `usuarios` ADD COLUMN `plantilla_permisos_id` INT UNSIGNED DEFAULT NULL AFTER `rol_id`,
     ADD CONSTRAINT `fk_usuario_plantilla` FOREIGN KEY (`plantilla_permisos_id`) REFERENCES `plantillas_permisos` (`id`)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'roles' AND COLUMN_NAME = 'plantilla_permisos_id_default'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `roles` ADD COLUMN `plantilla_permisos_id_default` INT UNSIGNED DEFAULT NULL,
     ADD CONSTRAINT `fk_rol_plantilla` FOREIGN KEY (`plantilla_permisos_id_default`) REFERENCES `plantillas_permisos` (`id`)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'permisos_usuario' AND COLUMN_NAME = 'vence_en'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `permisos_usuario` ADD COLUMN `vence_en` DATETIME DEFAULT NULL AFTER `editar`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 2. Backfill: una plantilla por rol existente, copia exacta de su matriz actual ──
-- Corte limpio sin período de transición: cada usuario sigue viendo EXACTAMENTE los
-- mismos módulos que antes, porque la plantilla por defecto de su rol es un espejo 1:1
-- de lo que ya tenía en `permisos` — pero la fuente de verdad ya pasa a ser el sistema
-- de plantillas desde acá. usuarios.plantilla_permisos_id queda NULL (heredan de su rol).

INSERT INTO `plantillas_permisos` (`nombre`, `descripcion`)
SELECT DISTINCT r.nombre, CONCAT('Migrada automáticamente desde el rol "', r.nombre, '"')
FROM `roles` r
JOIN `permisos` p ON p.rol_id = r.id
WHERE NOT EXISTS (SELECT 1 FROM `plantillas_permisos` pl WHERE pl.nombre = r.nombre);

INSERT INTO `plantilla_permisos_detalle` (`plantilla_id`, `modulo`, `ver`, `editar`)
SELECT pl.id, p.modulo, p.ver, p.editar
FROM `permisos` p
JOIN `roles` r  ON r.id = p.rol_id
JOIN `plantillas_permisos` pl ON pl.nombre = r.nombre
WHERE NOT EXISTS (
  SELECT 1 FROM `plantilla_permisos_detalle` d WHERE d.plantilla_id = pl.id AND d.modulo = p.modulo
);

UPDATE `roles` r
JOIN `plantillas_permisos` pl ON pl.nombre = r.nombre
SET r.plantilla_permisos_id_default = pl.id
WHERE r.plantilla_permisos_id_default IS NULL
  AND EXISTS (SELECT 1 FROM `permisos` p WHERE p.rol_id = r.id);
