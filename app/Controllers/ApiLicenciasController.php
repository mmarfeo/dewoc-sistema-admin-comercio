<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/LicenciaClienteModel.php';
require_once BASE_PATH . '/core/ServidorLicencias.php';

/**
 * API del servidor de licencias automáticas (ver core/ServidorLicencias.php). La llaman los
 * demás Dewoc, servidor a servidor, sin sesión ni CSRF.
 *
 *   POST /api/licencias/v1/solicitar
 *   { "instalacion_id": "…", "modulo": "dewoc_cobros", "comercio": "Heladería X", "url": "https://…" }
 *
 *   200 { "licencia": "DWC1.…" }
 *   403 { "error": "baja", "mensaje": "…" }     el comercio está dado de baja
 *   404 { "error": "no_es_servidor" }           esta instalación no emite licencias
 *   422 { "error": "instalacion_invalida" | "modulo_invalido" | "comercio_invalido" | "json_invalido" }
 *
 * No hay autenticación a propósito: cualquier Dewoc puede pedir una prueba (producto mínimo,
 * ver la decisión C22 del checklist). La licencia sale atada a la instalación que la pidió.
 */
class ApiLicenciasController extends Controller
{
    public function solicitar(): void
    {
        if (!ServidorLicencias::habilitado()) $this->json(['error' => 'no_es_servidor'], 404);

        $datos = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($datos)) $this->json(['error' => 'json_invalido'], 422);

        $r = (new LicenciaClienteModel())->atender(
            (string)($datos['instalacion_id'] ?? ''),
            (string)($datos['modulo'] ?? ''),
            (string)($datos['comercio'] ?? ''),
            isset($datos['url']) ? (string)$datos['url'] : null
        );

        if ($r['ok']) $this->json(['licencia' => $r['licencia']]);
        $this->json(['error' => $r['codigo'], 'mensaje' => $r['mensaje'] ?? null], $r['codigo'] === 'baja' ? 403 : 422);
    }
}
