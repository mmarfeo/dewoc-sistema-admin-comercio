<?php
require_once BASE_PATH . '/app/Models/Model.php';

class WebauthnCredentialModel extends Model
{
    protected string $table = 'webauthn_credentials';

    public function crear(int $usuarioId, string $credentialId, string $publicKey, ?string $deviceName): int
    {
        return $this->insert([
            'usuario_id'    => $usuarioId,
            'credential_id' => $credentialId,
            'public_key'    => $publicKey,
            'device_name'   => $deviceName,
        ]);
    }

    public function listarPorUsuario(int $usuarioId): array
    {
        return $this->queryAll(
            'SELECT id, device_name, created_at, last_used_at
             FROM webauthn_credentials
             WHERE usuario_id = :uid
             ORDER BY created_at DESC',
            ['uid' => $usuarioId]
        );
    }

    public function obtenerPorCredentialId(string $credentialId): array|false
    {
        return $this->queryOne(
            'SELECT * FROM webauthn_credentials WHERE credential_id = :cid',
            ['cid' => $credentialId]
        );
    }

    public function actualizarUso(int $id, int $signCount): void
    {
        $this->query(
            'UPDATE webauthn_credentials SET sign_count = :sc, last_used_at = NOW() WHERE id = :id',
            ['sc' => $signCount, 'id' => $id]
        );
    }

    /** Borra una credencial, solo si pertenece al usuario dueño (para que nadie borre dispositivos ajenos) */
    public function eliminar(int $id, int $usuarioId): void
    {
        $this->query(
            'DELETE FROM webauthn_credentials WHERE id = :id AND usuario_id = :uid',
            ['id' => $id, 'uid' => $usuarioId]
        );
    }
}
