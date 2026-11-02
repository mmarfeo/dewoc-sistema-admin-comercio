<?php
require_once BASE_PATH . '/app/Models/Model.php';

/**
 * Paquetes de Android permitidos en Dewoc Cobros (C3) y configuración del módulo
 * (`cobros_config`, fila única id = 1). Ver docs/dewoc-cobros-checklist.md, tarea 1.1.
 *
 * El celular solo reenvía las notificaciones de los paquetes activos de esta lista. La
 * recibe en GET /estado y vuelve a pedirla cuando cambia `paquetes_version`.
 */
class CobroPaqueteModel extends Model
{
    protected string $table = 'cobros_paquetes';

    /** Paquetes activos, en el formato que espera la app: [{paquete, nombre}]. */
    public function permitidos(): array
    {
        return array_map(
            fn($f) => ['paquete' => $f['paquete'], 'nombre' => $f['nombre_app']],
            $this->queryAll("SELECT paquete, nombre_app FROM cobros_paquetes WHERE activo = 1 ORDER BY nombre_app")
        );
    }

    /** Fila del paquete (activo o no), o null si no está en la lista. */
    public function porPaquete(string $paquete): ?array
    {
        return $this->queryOne("SELECT * FROM cobros_paquetes WHERE paquete = :p", [':p' => $paquete]) ?: null;
    }

    public function config(): array
    {
        return $this->queryOne("SELECT * FROM cobros_config WHERE id = 1") ?: [
            'id' => 1, 'paquetes_version' => 1,
            'app_version_codigo' => null, 'app_version' => null, 'app_url' => null,
        ];
    }

    /**
     * Última versión publicada del APK para GET /estado (sección 3.4 del plan), o null si
     * no hay una cargada. La app solo muestra el botón con una URL https://.
     */
    public function versionApp(): ?array
    {
        $c = $this->config();
        if (empty($c['app_version_codigo']) || empty($c['app_url'])) return null;
        return [
            'version_codigo' => (int)$c['app_version_codigo'],
            'version'        => (string)($c['app_version'] ?? ''),
            'url'            => (string)$c['app_url'],
        ];
    }
}
