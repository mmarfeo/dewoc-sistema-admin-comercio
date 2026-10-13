-- Pantalla de inicio configurable por usuario: hasta ahora todos entraban siempre a
-- /chat después de loguearse. Ahora el admin puede elegir, por usuario, a qué módulo
-- lo lleva el login (Chat IA queda como una opción más, no el destino obligatorio).
-- Ejecutar una sola vez desde phpMyAdmin.

ALTER TABLE `usuarios`
  ADD COLUMN `pantalla_inicio` VARCHAR(30) NULL DEFAULT NULL AFTER `rol_id`;
-- Nota: si da error porque ya existe la columna, ignorar este ALTER.
-- NULL = sin configurar → se usa Chat IA (comportamiento actual, retrocompatible).
