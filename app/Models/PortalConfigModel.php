<?php
require_once BASE_PATH . '/app/Models/Model.php';

/**
 * Configuración del Portal de Clientes Mayoristas — clave/valor genérico, mismo patrón que
 * GeminiUsageModel::getConfig()/setConfig(). Por ahora solo 'criterio_stock', que decide
 * cuándo un producto o una opción/gusto se muestra deshabilitado ("sin stock") en /portal:
 *   - 'stock_minimo' (default): se corta apenas stock_actual <= stock_minimo — más
 *     conservador, nunca deja pedir algo que ya está en zona de reposición.
 *   - 'stock_cero': se corta recién con stock_actual <= 0 — mismo criterio que usa el
 *     cajero en el POS.
 * Usado por PortalController::opcionesProducto() (gustos) y portal/catalogo.php (productos,
 * vía CRITERIO_STOCK inyectado al JS).
 */
class PortalConfigModel extends Model
{
    public function getConfig(): array
    {
        try {
            $rows = $this->db->query("SELECT clave, valor FROM portal_config")->fetchAll(\PDO::FETCH_KEY_PAIR);
        } catch (\Throwable $e) {
            $rows = [];
        }
        return array_merge([
            'criterio_stock' => 'stock_minimo',
        ], $rows ?: []);
    }

    public function setConfig(string $clave, string $valor): void
    {
        $this->db->prepare(
            "INSERT INTO portal_config (clave, valor) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE valor = VALUES(valor)"
        )->execute([$clave, $valor]);
    }
}
