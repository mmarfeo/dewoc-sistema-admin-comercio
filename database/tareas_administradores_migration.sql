-- Tareas asignadas a "Administradores" (además de usuario puntual / turno abierto) +
-- visibilidad restringida por usuario admin puntual (no todos los admins tienen por qué
-- ver las tareas generales) — ver app/Controllers/TareasController.php.
-- Ejecutar una sola vez desde phpMyAdmin.

ALTER TABLE `tareas_asignadas`
  MODIFY COLUMN `modo_destino` ENUM('usuario_especifico','turno_abierto','administradores') NOT NULL;

-- Flag explícito por usuario, independiente del rol: a propósito NO se resuelve con el
-- "admin ve todo" que ya usa can()/canView() en el resto del sistema (ese short-circuit
-- es a nivel de rol, no permite diferenciar un admin de otro) — acá el objetivo es
-- justamente que dos usuarios con rol admin puedan tener una respuesta distinta.
-- Default 0: ningún usuario (ni siquiera los admins existentes) ve tareas generales hasta
-- que se lo tildes a mano en su ficha.
ALTER TABLE `usuarios`
  ADD COLUMN `ve_tareas_generales` TINYINT(1) NOT NULL DEFAULT 0
    COMMENT 'Puede ver/accionar tareas asignadas a "Administradores" (independiente del rol)'
    AFTER `pantalla_inicio`;
