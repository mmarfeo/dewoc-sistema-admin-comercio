<?php
require_once BASE_PATH . '/app/Models/Model.php';

class ConfiguracionSeguridadModel extends Model
{
    protected string $table = 'configuracion_seguridad';

    public function obtener(): array
    {
        return $this->queryOne("SELECT * FROM configuracion_seguridad WHERE id = 1")
            ?: ['id' => 1, 'webauthn_habilitado' => 0];
    }

    public function guardarWebauthnHabilitado(bool $on): void
    {
        $valor = $on ? 1 : 0;
        $this->query(
            "INSERT INTO configuracion_seguridad (id, webauthn_habilitado) VALUES (1, :v)
             ON DUPLICATE KEY UPDATE webauthn_habilitado = :v2",
            [':v' => $valor, ':v2' => $valor]
        );
    }
}
