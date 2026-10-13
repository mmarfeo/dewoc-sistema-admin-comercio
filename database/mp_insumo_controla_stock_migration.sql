-- Extiende a materias primas e insumos el mismo flag "controla_stock" que ya existe
-- en productos: permite marcar ítems que no necesitan control de stock (ej. algo
-- administrativo de bajo valor que no se quiere estar contando). No tiene relación
-- con el consumo de ingredientes por venta directa (ver mp_insumo_controla_stock no
-- afecta eso) — ese usa la receta asociada al PRODUCTO, no este flag.
-- Los registros existentes quedan en 1 (comportamiento actual, sin cambios).
-- Ejecutar una sola vez desde phpMyAdmin.

ALTER TABLE `materias_primas`
  ADD COLUMN `controla_stock`
    TINYINT(1) NOT NULL DEFAULT 1
    COMMENT '0 = no se muestra en Control de Stock ni se marca bajo mínimo'
    AFTER `stock_actual`;

ALTER TABLE `insumos`
  ADD COLUMN `controla_stock`
    TINYINT(1) NOT NULL DEFAULT 1
    COMMENT '0 = no se muestra en Control de Stock ni se marca bajo mínimo'
    AFTER `stock_actual`;
