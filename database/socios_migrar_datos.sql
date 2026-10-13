-- Migra los pagos ya cargados con `nombre_socio` de texto libre a la FK `socio_id`.
-- Ejecutar DESPUÉS de socios_migration.sql. Se puede correr más de una vez sin
-- problema (solo toca filas con socio_id todavía sin asignar).
--
-- OJO: esto crea un socio nuevo por cada nombre distinto que encuentre en pagos
-- viejos (ej. si hay "Juan Pérez" y "juan perez" cargados como texto en pagos
-- distintos, va a matchear ambos contra el MISMO socio nuevo "Juan Pérez", pero si
-- hay nombres realmente distintos entre sí van a crear socios separados). Conviene
-- revisar la tabla `socios` después de correrlo y fusionar/renombrar a mano si
-- hace falta, antes de empezar a usar el ABM para cargar pagos nuevos.

-- 1) Crear un socio por cada nombre distinto que todavía no exista en la tabla
INSERT INTO socios (nombre, activo, eliminado)
SELECT DISTINCT TRIM(pp.nombre_socio), 1, 0
FROM pagos_proveedores pp
WHERE pp.origen_pago = 'socio'
  AND pp.socio_id IS NULL
  AND pp.nombre_socio IS NOT NULL AND TRIM(pp.nombre_socio) <> ''
  AND NOT EXISTS (
    SELECT 1 FROM socios s
    WHERE LOWER(TRIM(s.nombre)) = LOWER(TRIM(pp.nombre_socio)) COLLATE utf8mb4_unicode_ci
  );

-- 2) Vincular los pagos existentes a su socio (case-insensitive)
-- COLLATE explícito: por si `socios` y `pagos_proveedores` terminan con collations
-- distintos (ver nota en socios_migration.sql), esta comparación no se rompe.
UPDATE pagos_proveedores pp
JOIN socios s ON LOWER(TRIM(s.nombre)) = LOWER(TRIM(pp.nombre_socio)) COLLATE utf8mb4_unicode_ci
SET pp.socio_id = s.id
WHERE pp.origen_pago = 'socio'
  AND pp.socio_id IS NULL
  AND pp.nombre_socio IS NOT NULL AND TRIM(pp.nombre_socio) <> '';
