-- ============================================================
--  ABM del "help desk" del Chat IA (ver docs/tareas-pendientes.md, sección
--  Help Desk). Antes AyudaModel::TEMAS era un array PHP fijo — pasa a vivir acá
--  para que el admin pueda editar/agregar temas sin necesitar un deploy.
--  `modulo` y `tour_id` siguen atados a código (PermisosModel::MODULOS y
--  assets/js/tour.js respectivamente) — el ABM los valida con selects, no
--  texto libre, para no dejar referencias rotas.
--  Ejecutar una vez sobre la base de datos existente.
-- ============================================================

CREATE TABLE IF NOT EXISTS `ayuda_temas` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tema`       VARCHAR(60)  NOT NULL COMMENT 'Clave estable que usa Gemini al llamar mostrar_guia_ayuda(tema)',
  `modulo`     VARCHAR(60)  NOT NULL COMMENT 'Clave de PermisosModel::MODULOS, se valida con canView()',
  `titulo`     VARCHAR(150) NOT NULL,
  `url`        VARCHAR(150) NOT NULL COMMENT 'Ruta relativa, ej. ventas/pos',
  `tour_id`    VARCHAR(60)  NULL COMMENT 'Clave de TOURS en assets/js/tour.js, o NULL si no tiene tour visual',
  `manual`     TEXT NOT NULL COMMENT 'Pasos que se inyectan en el system prompt de Gemini',
  `activo`     TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ayuda_temas_tema` (`tema`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `ayuda_temas` (`tema`, `modulo`, `titulo`, `url`, `tour_id`, `manual`) VALUES
('pos_facturar', 'ventas_pos', 'Cómo facturar en el POS', 'ventas/pos', 'pos_facturar',
 'Para facturar una venta desde el Punto de Venta (POS):\n1. Andá a Ventas > POS.\n2. Buscá el producto por nombre o código en el buscador de arriba y tocalo para agregarlo al carrito.\n3. El carrito se va armando a la derecha con cada ítem, cantidad y subtotal — podés ajustar cantidades ahí mismo.\n4. Cuando el carrito esté completo, tocá el botón verde "Cobrar".\n5. Elegí el método de pago y confirmá — el sistema descuenta el stock y genera el comprobante automáticamente.'),

('caja_abrir_turno', 'caja', 'Cómo abrir turno de caja', 'caja', 'caja_abrir_turno',
 'Para abrir un turno de caja:\n1. Andá a Caja y Turnos.\n2. Si no hay un turno abierto, vas a ver el formulario "Abrir turno".\n3. Cargá el monto inicial en efectivo con el que arrancás el turno.\n4. Tocá el botón "Abrir turno" — a partir de ahí ya podés vender y registrar movimientos de caja.'),

('caja_cerrar_turno', 'caja', 'Cómo cerrar turno de caja', 'caja', 'caja_cerrar_turno',
 'Para cerrar el turno de caja (cierre ciego, sin ver el total esperado del sistema):\n1. Andá a Caja y Turnos, con el turno abierto.\n2. Tocá el botón "Cerrar turno" — se abre un modal.\n3. Contá el efectivo real y cargá los montos declarados por cada método de pago.\n4. Confirmá el cierre. El sistema compara lo declarado contra lo que registró durante el turno y muestra si hubo diferencia.'),

('stock_ajustar', 'stock', 'Cómo cargar o ajustar stock', 'stock', 'stock_ajustar',
 'Para ajustar el stock de un producto, materia prima o insumo:\n1. Andá a Control de Stock.\n2. Buscá el ítem en la tabla.\n3. Tocá el ícono "Ajustar stock" (±) en su fila.\n4. Cargá la cantidad a sumar o restar y el motivo del ajuste, y confirmá.\nSi en cambio lo que necesitás es dar de alta stock inicial de una compra a proveedor, esa carga se hace desde Facturas de Compra, no desde acá.'),

('productos_crear', 'productos', 'Cómo dar de alta un producto', 'productos', NULL,
 'Para crear un producto nuevo:\n1. Andá a Productos.\n2. Tocá el botón de "Nuevo producto".\n3. Completá nombre, categoría, precio de venta y demás datos del formulario.\n4. Guardá — ya queda disponible para venderse desde el POS.'),

('clientes_crear', 'clientes', 'Cómo dar de alta un cliente', 'clientes', NULL,
 'Para crear un cliente nuevo:\n1. Andá a Clientes.\n2. Tocá el botón de "Nuevo cliente".\n3. Completá nombre/razón social, documento y condición frente al IVA.\n4. Guardá — ya podés asociarlo a una venta.'),

('compras_registrar', 'compras', 'Cómo registrar una factura de compra', 'compras', NULL,
 'Para registrar una compra a un proveedor:\n1. Andá a Facturas de Compra.\n2. Tocá "Nueva compra", elegí el proveedor (o creá uno nuevo) y cargá los datos del comprobante.\n3. Agregá cada ítem comprado con cantidad y precio — esto actualiza el stock automáticamente al guardar.\nTambién podés sacarle una foto o subir el PDF de la factura en el Chat IA y pedirle que la registre por vos.'),

('reportes_generar', 'reportes_ia', 'Cómo generar un reporte', 'reportes-ia', NULL,
 'Para generar un reporte:\n1. La forma más simple es pedírselo directamente acá, al Chat IA (ej. "generame un reporte de ventas de este mes") — queda guardado en Reportes IA con tabla en pantalla y descarga en Excel.\n2. También podés entrar directo a Reportes IA para ver los reportes ya generados anteriormente.');
