-- Acciones de aceptar/rechazar/aplazar/finalizar para solicitudes de inventario (tipo=inventario)
-- desde el cartel de ventas/pos — mismo flujo que ya existe para Tareas (tareas_asignadas),
-- ver docs/tareas-pendientes.md #2. Ejecutar una sola vez desde phpMyAdmin.

ALTER TABLE `solicitudes`
  MODIFY `estado` ENUM('pendiente','en_proceso','resuelta','rechazada') NOT NULL DEFAULT 'pendiente',
  ADD COLUMN `aceptado_por`   INT UNSIGNED NULL COMMENT 'Quién aceptó (pasó a en_proceso) — solo tipo=inventario' AFTER `usuario_id`,
  ADD COLUMN `aceptado_at`    DATETIME     NULL AFTER `aceptado_por`,
  ADD COLUMN `motivo_rechazo` TEXT         NULL COMMENT 'Motivo cuando estado=rechazada' AFTER `resuelto_at`,
  ADD COLUMN `aplazado_hasta` DATETIME     NULL COMMENT 'No mostrar en el cartel del POS hasta esta hora (no cambia el estado)' AFTER `motivo_rechazo`,
  ADD CONSTRAINT `fk_sol_aceptado_por` FOREIGN KEY (`aceptado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;
