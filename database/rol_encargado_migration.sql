-- Agrega el rol "Encargado" (supervisor operativo, entre cajero/vendedor y admin).
-- Ejecutar una sola vez desde phpMyAdmin.
-- Los permisos por módulo se configuran después desde Usuarios > Permisos
-- (hasta que se configuren explícitamente, el rol tiene acceso total por defecto).

INSERT IGNORE INTO `roles` (`nombre`, `descripcion`) VALUES
('encargado', 'Supervisor operativo: ventas, stock, compras y empleados');
