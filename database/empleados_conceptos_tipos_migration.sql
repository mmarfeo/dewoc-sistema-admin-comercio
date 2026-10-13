-- Catálogo editable de "tipos de concepto" para Empleados (antes era un ENUM fijo
-- viatico/premio/bono/otro, hardcodeado en 5 archivos). Con esto el usuario puede dar
-- de alta sus propios tipos desde la pantalla (ej. Aguinaldo, Vacaciones, Adelanto),
-- sin tocar código. Ejecutar una sola vez desde phpMyAdmin.

CREATE TABLE IF NOT EXISTS `empleados_conceptos_tipos` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `codigo`     VARCHAR(30)  NOT NULL COMMENT 'Clave estable interna (no se edita) — la que se guarda en empleados_conceptos.tipo',
  `nombre`     VARCHAR(60)  NOT NULL,
  `color`      VARCHAR(7)   NOT NULL DEFAULT '#6c757d',
  `orden`      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `eliminado`  TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ect_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `empleados_conceptos_tipos` (`codigo`, `nombre`, `color`, `orden`) VALUES
  ('viatico',   'Viático',   '#0d6efd', 1),
  ('premio',    'Premio',    '#198754', 2),
  ('bono',      'Bono',      '#6f42c1', 3),
  ('aguinaldo', 'Aguinaldo', '#fd7e14', 4),
  ('otro',      'Otro',      '#6c757d', 9);

-- Antes eran ENUM('viatico','premio','bono','otro') — se abren a VARCHAR para aceptar
-- cualquier código que el usuario cree en el catálogo de arriba. Los valores ya
-- guardados (viatico/premio/bono/otro) siguen siendo válidos sin ninguna migración
-- de datos, porque coinciden con los `codigo` sembrados arriba.
ALTER TABLE `empleados_conceptos`  MODIFY COLUMN `tipo`          VARCHAR(30) NOT NULL DEFAULT 'otro';
ALTER TABLE `gastos_recurrentes`   MODIFY COLUMN `concepto_tipo` VARCHAR(30) NULL DEFAULT 'viatico';
