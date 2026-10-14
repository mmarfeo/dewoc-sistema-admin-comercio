-- Atender los pedidos de MenuApp desde el POS (ver docs/integracion-menuapp-checklist.md,
-- etapa 9, tarea 9.1, decisiones D14 a D17).
--
-- IMPORTANTE: los pedidos y los mensajes VIVEN EN MENUAPP. Esto es una copia local para
-- poder mostrar la alerta y la lista en el POS sin depender de que MenuApp responda en el
-- momento. Cada novedad (3.8) actualiza la copia; cuando el POS actúa (aceptar, rechazar,
-- proponer, escribir), se lo pide a MenuApp — nunca se decide acá.
--
-- Idempotente: se puede volver a correr sin romper nada. Ejecutar desde phpMyAdmin.

-- ── Copia local de los pedidos ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `menuapp_pedidos` (
  `id`                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `referencia`              VARCHAR(40)  NOT NULL COMMENT 'La misma que usa ventas.referencia_externa',
  `codigo`                  VARCHAR(20)  DEFAULT NULL COMMENT 'Código corto que ve el cliente (ej. A7K2)',
  -- El cuerpo completo de 3.7 tal como lo manda MenuApp, actualizado con cada novedad. Se
  -- guarda entero y no desarmado en columnas a propósito: MenuApp es el dueño del pedido y
  -- puede agregarle campos sin que haya que migrar la base de cada instalación de Dewoc.
  `datos`                   JSON         DEFAULT NULL,
  `estado`                  VARCHAR(40)  NOT NULL DEFAULT 'esperando_aprobacion',
  `estado_texto`            VARCHAR(120) DEFAULT NULL COMMENT 'El texto que MenuApp quiere que se muestre',
  `requiere_accion`         TINYINT(1)   NOT NULL DEFAULT 1
    COMMENT '1 = el POS tiene que aceptar, rechazar o proponer. Al pasar a 0 se saca la alerta',
  `repartidor`              VARCHAR(120) DEFAULT NULL COMMENT 'Nombre; habilita la conversación con el repartidor',
  `vence_en`                DATETIME     DEFAULT NULL
    COMMENT 'Pasado este momento el POS deja de mostrar la alerta, aunque no llegue la novedad',
  `atendido_por_usuario_id` INT UNSIGNED DEFAULT NULL COMMENT 'Quién lo atendió desde la caja',
  `created_at`              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  -- Hace idempotente a POST /pedidos: el mismo pedido puede llegar dos veces (reintento de
  -- MenuApp) y no se duplica la alerta.
  UNIQUE KEY `uq_map_referencia` (`referencia`),
  KEY `idx_map_accion` (`requiere_accion`, `vence_en`),
  KEY `idx_map_estado` (`estado`),
  KEY `idx_map_creado` (`created_at`),
  KEY `fk_map_usuario` (`atendido_por_usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Contador de mensajes sin leer ───────────────────────────────────────────
-- Solo el CONTADOR: los mensajes se leen de MenuApp (GET pedidos/{ref}/mensajes). Guardarlos
-- acá sería tener dos copias de la misma conversación desincronizándose.
CREATE TABLE IF NOT EXISTS `menuapp_mensajes_no_leidos` (
  `referencia`   VARCHAR(40) NOT NULL,
  `conversacion` ENUM('cliente','repartidor') NOT NULL,
  `cantidad`     INT UNSIGNED NOT NULL DEFAULT 0,
  `ultimo_en`    DATETIME     DEFAULT NULL COMMENT 'Para ordenar el aviso por lo más reciente',
  PRIMARY KEY (`referencia`, `conversacion`),
  KEY `idx_mnl_cantidad` (`cantidad`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── URL base de la API de MenuApp (tarea 9.3) ───────────────────────────────
-- Dewoc pasa a ser CLIENTE de MenuApp para aceptar, rechazar, proponer y escribir (3.9).
-- Configurable porque en desarrollo apunta a otro host; el default es el de producción.
ALTER TABLE `integracion_menuapp`
  ADD COLUMN IF NOT EXISTS `api_base_url` VARCHAR(255) NULL
    COMMENT 'Base de la API de MenuApp para Dewoc (3.9). NULL = https://menuapp.dewoc.com/api/dewoc/v1/'
    AFTER `webhook_secret`;

-- Nota: a diferencia de los ADD COLUMN, este ALTER no es idempotente en todas las versiones
-- de MariaDB/MySQL. Si al volver a correr la migración da "Duplicate foreign key constraint
-- name", ignorarlo: ya está aplicado.
ALTER TABLE `menuapp_pedidos`
  ADD CONSTRAINT `fk_map_usuario`
    FOREIGN KEY (`atendido_por_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;
