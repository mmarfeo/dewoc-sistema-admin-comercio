# Dewoc — Sistema de Administración de Comercios

Sistema web de gestión integral para comercios pequeños y medianos de Argentina.
Desarrollado en PHP + MySQL puro con arquitectura MVC sin frameworks externos.

## Stack tecnológico

- **Backend**: PHP 8.0+ (MVC sin frameworks)
- **Base de datos**: MySQL 8.0+ (PDO con prepared statements)
- **Frontend**: Dashly 1.6.0 (Bootstrap 5), DataTables 1.13+, Chart.js
- **PWA**: manifest.json + Service Worker (cache-first / network-first)
- **Pagos**: Mercado Pago Checkout Pro
- **IA**: Anthropic Claude API (Chat IA + MCP MySQL)

## Módulos incluidos

| Módulo | Descripción |
|--------|-------------|
| Auth | Login/logout, bloqueo por intentos, perfil |
| ABM Maestros | Categorías, Productos, Materias Primas, Insumos, Proveedores, Clientes |
| Facturas de Compra | Carga con detalle, IVA discriminado, anulación con reversión de stock |
| Recetas de Producción | Ingredientes, merma%, costo estimado, órdenes de producción |
| Ventas / POS | Punto de venta fullscreen, ticket 80mm, comanda de cocina |
| Control de Stock | Ajustes manuales, inventario físico, reporte por período |
| Caja y Turnos | Apertura/cierre, movimientos manuales, historial |
| Ecommerce | Catálogo público, carrito localStorage, Mercado Pago Checkout Pro, webhook IPN |
| Reportes | Ventas, Compras, Stock, Caja — con Chart.js y exportación Excel/PDF |
| Chat IA | Preguntas en lenguaje natural → SQL → resultados vía Claude API |
| Usuarios | ABM con roles (admin/cajero/depósito/vendedor) |
| PWA | Instalable en desktop y mobile |

## Requisitos

- XAMPP (PHP 8.0+ y MySQL 8.0+)
- PHP extensions: `pdo_mysql`, `gd`, `curl`, `json`, `mbstring`

## Instalación

1. Clonar o copiar en `c:/xampp/htdocs/01-Dewoc-Sist-Admin-Comercio/`
2. Crear la base de datos en MySQL:
   ```sql
   CREATE DATABASE u374453216_erp_comercio CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
3. Importar el schema y datos iniciales:
   ```
   mysql -u root u374453216_erp_comercio < database/schema.sql
   mysql -u root u374453216_erp_comercio < database/seed.sql
   ```
   O bien usar el instalador web: `http://localhost/01-Dewoc-Sist-Admin-Comercio/install.php`

4. Configurar `config/config.php` según el entorno:
   - `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`
   - `MP_ACCESS_TOKEN` y `MP_PUBLIC_KEY` (Mercado Pago)
   - `CLAUDE_API_KEY` (Chat IA con Claude)
   - `BASE_URL`

5. Verificar que Apache tiene `mod_rewrite` habilitado.

## Credenciales por defecto

| Usuario | Contraseña |
|---------|-----------|
| admin@dewoc.com | admin1234 |

**Cambiar la contraseña inmediatamente en producción.**

## Estructura de directorios

```
/
├── app/
│   ├── Controllers/      # Controladores MVC
│   ├── Models/           # Modelos con PDO
│   └── Views/            # Vistas PHP con layout
├── assets/               # CSS, JS, imágenes del tema
├── config/
│   └── config.php        # Configuración centralizada
├── core/                 # Router, Session, Csrf, Database
├── database/
│   ├── schema.sql        # DDL de todas las tablas
│   └── seed.sql          # Datos iniciales
├── docs/
│   └── CHECKLIST.md      # Estado de funcionalidades
├── storage/logs/         # Logs de errores (excluido de git)
├── tests/                # Suite de tests propia (sin dependencias)
│   ├── run.php           # Runner de tests CLI
│   ├── security/         # Tests de seguridad (CSRF, XSS, SQLi, headers)
│   └── unit/             # Tests unitarios de modelos
├── uploads/              # Archivos subidos (excluido de git)
├── .htaccess             # Reescritura de URLs y cabeceras HTTP
├── index.php             # Front controller + definición de rutas
└── manifest.json         # PWA manifest
```

## Ejecutar tests

```bash
php tests/run.php
```

Los tests no requieren PHPUnit ni dependencias externas.

## Seguridad implementada

- Prepared statements PDO en todas las queries (sin SQL concatenado con input)
- CSRF token en todos los formularios POST
- XSS prevention con `htmlspecialchars(ENT_QUOTES)` en todas las vistas
- Contraseñas con bcrypt cost 12
- Bloqueo de login tras 5 intentos (15 minutos)
- Cabeceras HTTP de seguridad via `.htaccess` (CSP, X-Frame-Options, etc.)
- Soft deletes (`eliminado = 1`) — nunca DELETE físico

## Licencia

Uso privado. No redistribuir sin autorización.
