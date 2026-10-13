-- Fotos del menú online desde la Galería (ver docs/integracion-menuapp-checklist.md,
-- etapa 8, tarea 8.1). Permite que un empleado saque hasta 3 fotos candidatas por
-- producto desde el celular, las mejore con IA y que el encargado elija la oficial.
-- Idempotente: se puede volver a correr sin romper nada. Ejecutar desde phpMyAdmin.

-- Hasta ahora las fotos de galeria_archivos no estaban asociadas a nada: no había forma
-- de saber a qué producto correspondía cada una. Con producto_id + estado_menu, cada foto
-- del menú nace ya ligada a su producto y con su rol en el circuito.
ALTER TABLE `galeria_archivos`
  ADD COLUMN IF NOT EXISTS `producto_id` INT UNSIGNED NULL
    COMMENT 'Producto al que pertenece esta foto. NULL = foto suelta de la Galería (ej. para redes)'
    AFTER `usuario_id`,

  -- NULL a propósito y no un default: una foto de la Galería que no tiene nada que ver con
  -- el menú (una story, una promo para Instagram) no entra en este circuito.
  ADD COLUMN IF NOT EXISTS `estado_menu`
    ENUM('candidata','oficial','anterior','descartada') NULL
    COMMENT 'candidata = propuesta · oficial = la que ve el cliente · anterior = la oficial previa (permite volver) · descartada = se puede limpiar a los 30 días'
    AFTER `producto_id`,

  ADD COLUMN IF NOT EXISTS `mejorada_ia` TINYINT(1) NOT NULL DEFAULT 0
    COMMENT '1 = salió de "Mejorar con IA" sobre otra foto (ver origen_id)',

  ADD COLUMN IF NOT EXISTS `origen_id` INT UNSIGNED NULL
    COMMENT 'Foto de la que salió esta versión mejorada con IA';

-- ON DELETE SET NULL: si se elimina un producto, sus fotos no se borran — quedan como
-- fotos sueltas de la Galería, que es el mismo criterio de fk_producto_categoria.
-- Nota: a diferencia de los ADD COLUMN de arriba, este ALTER no es idempotente en todas
-- las versiones de MariaDB/MySQL. Si al volver a correr la migración da
-- "Duplicate foreign key constraint name", ignorarlo: significa que ya está aplicado.
-- Mismo criterio que database/opciones_producto_migration.sql.
ALTER TABLE `galeria_archivos`
  ADD CONSTRAINT `fk_ga_producto`
    FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE SET NULL;

-- origen_id apunta a la misma tabla. Sin FK a propósito: si se purga la foto original de
-- una mejorada, la mejorada tiene que sobrevivir, y un ON DELETE SET NULL sobre la propia
-- tabla complica el borrado en lote del cron de limpieza.
ALTER TABLE `galeria_archivos`
  ADD KEY IF NOT EXISTS `idx_ga_producto_estado` (`producto_id`, `estado_menu`),
  ADD KEY IF NOT EXISTS `idx_ga_estado_menu` (`estado_menu`),
  ADD KEY IF NOT EXISTS `idx_ga_origen` (`origen_id`);
