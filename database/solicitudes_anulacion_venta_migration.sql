-- Solicitudes de anulación de venta: un cajero (no-admin) ya no puede anular ventas
-- directamente (VentasController::anular() ahora exige rol admin) — en su lugar pide la
-- anulación con un motivo obligatorio, se notifica al admin por push, y el admin la
-- aprueba o rechaza (puede hacerlo remoto, sin estar en el local).
-- Ejecutar una sola vez desde phpMyAdmin.

CREATE TABLE IF NOT EXISTS `solicitudes_anulacion_venta` (
  `id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `venta_id`             INT UNSIGNED NOT NULL,
  `usuario_solicita_id`  INT UNSIGNED NOT NULL,
  `motivo`               VARCHAR(100) NOT NULL COMMENT 'Opción elegida del combo (o "Otro")',
  `motivo_detalle`       TEXT         DEFAULT NULL COMMENT 'Texto libre — obligatorio si motivo="Otro"',
  `estado`               ENUM('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente',
  `usuario_resuelve_id`  INT UNSIGNED DEFAULT NULL,
  `resuelto_at`          DATETIME     DEFAULT NULL,
  `motivo_rechazo`       TEXT         DEFAULT NULL,
  `created_at`           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sav_venta`  (`venta_id`),
  KEY `idx_sav_estado` (`estado`),
  CONSTRAINT `fk_sav_venta`    FOREIGN KEY (`venta_id`)            REFERENCES `ventas`   (`id`),
  CONSTRAINT `fk_sav_solicita` FOREIGN KEY (`usuario_solicita_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_sav_resuelve` FOREIGN KEY (`usuario_resuelve_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
