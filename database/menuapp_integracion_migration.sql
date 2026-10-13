-- Integración con MenuApp (ver docs/integracion-menuapp-checklist.md, tarea 0.1).
-- Dewoc queda como fuente de verdad del catálogo y del stock; MenuApp consume la API
-- /api/menuapp/v1/* y le devuelve los pedidos aceptados como ventas.
-- Idempotente: se puede volver a correr sin romper nada. Ejecutar desde phpMyAdmin.

-- ── Precio online por producto (D3) ──────────────────────────────────────────
-- NULL = usar precio_venta. Se carga con el MISMO criterio de IVA que precio_venta
-- (ver configuracion_fiscal.precios_incluyen_iva más abajo y core/PrecioHelper.php).
ALTER TABLE `productos`
  ADD COLUMN IF NOT EXISTS `precio_online` DECIMAL(12,2) NULL
    COMMENT 'Precio para el menú online (MenuApp). NULL = usar precio_venta'
    AFTER `precio_venta`;

-- ── "Los precios de venta cargados incluyen IVA" (D9) ────────────────────────
-- NULL = todavía no configurado: PrecioHelper mantiene el comportamiento histórico
-- (Responsable Inscripto = NO incluyen y se suma al vender; cualquier otra condición
-- fiscal = ya incluyen). Es independiente de NEGOCIO_CONDICION_IVA, que define si el
-- comprobante DISCRIMINA IVA, no si el precio cargado ya lo trae.
ALTER TABLE `configuracion_fiscal`
  ADD COLUMN IF NOT EXISTS `precios_incluyen_iva` TINYINT(1) NULL
    COMMENT '1 = los precios cargados ya traen IVA · 0 = se suma al vender · NULL = sin configurar (comportamiento histórico)';

-- ── Ventas con origen externo ────────────────────────────────────────────────
ALTER TABLE `ventas`
  ADD COLUMN IF NOT EXISTS `origen` VARCHAR(20) NOT NULL DEFAULT 'pos'
    COMMENT 'pos | portal | menuapp',
  ADD COLUMN IF NOT EXISTS `referencia_externa` VARCHAR(40) NULL
    COMMENT 'Referencia del pedido en el sistema de origen (MenuApp). Garantiza la idempotencia de POST /ventas',
  ADD COLUMN IF NOT EXISTS `cobro_pendiente` TINYINT(1) NOT NULL DEFAULT 0
    COMMENT '1 = venta cobrada en mano (efectivo/transferencia) todavía no imputada a una caja';

-- UNIQUE sobre referencia_externa: dos POST /ventas con la misma referencia no pueden
-- generar dos ventas. Las ventas del POS la dejan en NULL y MySQL no cuenta los NULL
-- como duplicados, así que no las afecta.
ALTER TABLE `ventas`
  ADD UNIQUE KEY IF NOT EXISTS `uq_venta_referencia_externa` (`referencia_externa`);

ALTER TABLE `ventas`
  ADD KEY IF NOT EXISTS `idx_venta_origen` (`origen`),
  ADD KEY IF NOT EXISTS `idx_venta_cobro_pendiente` (`cobro_pendiente`);

-- ── Configuración de la integración (una fila, id = 1) ───────────────────────
-- Del token se guarda SOLO el hash; el valor en claro se muestra una única vez al
-- generarlo (ver IntegracionesController::generarToken()).
CREATE TABLE IF NOT EXISTS `integracion_menuapp` (
  `id`                TINYINT UNSIGNED NOT NULL,
  `activa`            TINYINT(1)    NOT NULL DEFAULT 0,
  `token_hash`        VARCHAR(255)  DEFAULT NULL COMMENT 'SHA-256 del token Bearer. Nunca el token en claro',
  `token_ultimos4`    VARCHAR(8)    DEFAULT NULL,
  `token_generado_at` DATETIME      DEFAULT NULL,
  `webhook_url`       VARCHAR(255)  DEFAULT NULL COMMENT 'https://menuapp.dewoc.com/webhooks/dewoc/{slug}',
  `webhook_secret`    VARCHAR(255)  DEFAULT NULL COMMENT 'Lo genera MenuApp; firma HMAC-SHA256 de los avisos',
  `menu_url`          VARCHAR(255)  DEFAULT NULL COMMENT 'Menú público del comercio en MenuApp (redirección de /tienda, etapa 5)',
  `producto_envio_id` INT UNSIGNED  DEFAULT NULL COMMENT 'Producto "Envío a domicilio" con el que se factura el costo de envío',
  `usuario_id`        INT UNSIGNED  DEFAULT NULL COMMENT 'Usuario técnico "MenuApp (sistema)" usado en ventas.usuario_id',
  `ultimo_acceso_at`  DATETIME      DEFAULT NULL,
  `ultimo_error`      TEXT          DEFAULT NULL,
  `created_at`        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_menuapp_usuario` (`usuario_id`),
  KEY `fk_menuapp_producto_envio` (`producto_envio_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `integracion_menuapp` (`id`, `activa`) VALUES (1, 0);

-- ── Rate limit de la API (tarea 3, "Límite: 120 req/min por token") ──────────
-- Contador por ventana de un minuto. Se limpia solo: cada chequeo borra las ventanas
-- viejas, así la tabla nunca crece más de un puñado de filas.
CREATE TABLE IF NOT EXISTS `integracion_menuapp_rate` (
  `ventana`  CHAR(16)         NOT NULL COMMENT 'YYYY-mm-dd HH:ii truncado al minuto',
  `requests` INT UNSIGNED     NOT NULL DEFAULT 0,
  PRIMARY KEY (`ventana`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Etapa 6 · Identidad de la instalación (tarea 6.1, decisión D11) ─────────
-- UUID único y fijo de ESTA instalación de Dewoc. Se genera una sola vez (al primer uso)
-- y no se regenera nunca, ni siquiera al regenerar el token: MenuApp lo usa para que una
-- misma instalación no quede conectada a dos comercios distintos.
ALTER TABLE `integracion_menuapp`
  ADD COLUMN IF NOT EXISTS `instalacion_id` CHAR(36) NULL
    COMMENT 'UUID v4 fijo de esta instalación. Se genera una sola vez y NUNCA se regenera'
    AFTER `id`;

ALTER TABLE `integracion_menuapp`
  ADD UNIQUE KEY IF NOT EXISTS `uq_menuapp_instalacion` (`instalacion_id`);
