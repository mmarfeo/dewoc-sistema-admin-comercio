<?php
/**
 * Cron: renueva sola la licencia de los módulos que se cobran aparte (ver
 * docs/dewoc-cobros-checklist.md, decisión C22 y etapa 1B).
 *
 * Si el módulo ya se contrató y la licencia está por vencer (7 días antes) o venció, se la pide
 * al servidor de licencias y la carga. Nunca contrata por primera vez: eso lo hace el admin con
 * "Contratar" en Integraciones → Dewoc Cobros. La pantalla de Integraciones hace lo mismo al
 * abrirse; este cron cubre a los comercios que no entran ahí.
 *
 * Programar en hPanel > Avanzado > Cron Jobs, una vez por día:
 *   - PHP directo (recomendado): php /home/USUARIO/public_html/cron/licencias_renovar.php
 *   - Por URL: https://tudominio.com/cron/licencias_renovar.php?token=EL_CRON_SECRET_DEL_.env
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../app/Models/Model.php';
require_once __DIR__ . '/../app/Models/LicenciaModel.php';

// ── Seguridad: solo CLI, o HTTP con el token correcto ───────────────────────
$esCli = PHP_SAPI === 'cli';
if (!$esCli) {
    $token = $_GET['token'] ?? '';
    if (!CRON_SECRET || !hash_equals(CRON_SECRET, (string)$token)) {
        http_response_code(403);
        exit('Forbidden');
    }
}

function salida(string $msg): void
{
    echo $msg . "\n";
    logApp('[cron licencias_renovar] ' . $msg);
}

foreach (['dewoc_cobros'] as $modulo) {
    try {
        $r = (new LicenciaModel())->renovarSiHaceFalta($modulo);
        if ($r === null)  salida("{$modulo}: no hace falta renovar.");
        elseif ($r['ok']) salida("{$modulo}: {$r['mensaje']}");
        else              salida("{$modulo}: no se pudo renovar — {$r['mensaje']}");
    } catch (Throwable $e) {
        salida("{$modulo}: error — " . $e->getMessage());
    }
}
