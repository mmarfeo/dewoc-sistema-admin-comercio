-- Configuración del Portal de Clientes Mayoristas (ver PortalConfigModel) — mismo patrón
-- clave/valor que gemini_config, para no tener que armar una columna nueva por cada
-- setting. Por ahora un solo valor: el criterio de stock que decide cuándo un producto o
-- un gusto/opción se muestra deshabilitado ("sin stock") en /portal.
-- Ejecutar una sola vez desde phpMyAdmin.

CREATE TABLE IF NOT EXISTS `portal_config` (
  `clave` VARCHAR(50)  NOT NULL,
  `valor` VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Valor por defecto = comportamiento actual (corte por stock_minimo, no por stock en cero).
INSERT IGNORE INTO `portal_config` (`clave`, `valor`) VALUES ('criterio_stock', 'stock_minimo');
