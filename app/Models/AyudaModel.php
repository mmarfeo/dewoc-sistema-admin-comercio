<?php
require_once BASE_PATH . '/app/Models/Model.php';

/**
 * Temas del "help desk" del Chat IA (ver docs/tareas-pendientes.md, sección Help
 * Desk) — ABM en app/Controllers/AyudaTemasController.php, tabla `ayuda_temas`.
 * Cada tema alimenta dos cosas:
 *   - `manual`: se inyecta en el system prompt de Gemini para que responda las
 *     preguntas de "¿cómo hago X?" con estos pasos.
 *   - `modulo`/`url`/`tour_id`: los usa ChatController al armar la tarjeta de
 *     ayuda (botones "Ir ahora" / "Tour guiado") una vez que el modelo llama a
 *     la función mostrar_guia_ayuda(tema).
 * `modulo` y `tour_id` NO son texto libre en el formulario: `modulo` tiene que
 * ser una clave de PermisosModel::MODULOS, `tour_id` una clave de TOUR_IDS de
 * acá abajo (que a su vez tiene que existir como entrada en TOURS dentro de
 * assets/js/tour.js) — si no, el botón correspondiente quedaría roto.
 */
class AyudaModel extends Model
{
    protected string $table = 'ayuda_temas';

    /** Tours visuales ya armados en assets/js/tour.js — agregar acá SOLO junto con su entrada en TOURS. */
    const TOUR_IDS = [
        'pos_facturar'      => 'Facturar en el POS',
        'caja_abrir_turno'  => 'Abrir turno de caja',
        'caja_cerrar_turno' => 'Cerrar turno de caja',
        'stock_ajustar'     => 'Ajustar stock',
    ];

    public function listar(): array
    {
        return $this->findAll('titulo');
    }

    /** Temas activos, indexados por su clave `tema` — lo que consume ChatController. */
    public function activos(): array
    {
        $filas = $this->findWhere(['activo' => 1], 'titulo');
        $porTema = [];
        foreach ($filas as $fila) $porTema[$fila['tema']] = $fila;
        return $porTema;
    }

    /** Un tema activo por su clave (no por id) — usado al resolver mostrar_guia_ayuda(tema). */
    public function findPorTema(string $tema): array|false
    {
        return $this->queryOne("SELECT * FROM {$this->table} WHERE tema = ? AND activo = 1", [$tema]);
    }

    public function activar(int $id): void
    {
        $this->update($id, ['activo' => 1]);
    }

    public function desactivar(int $id): void
    {
        $this->update($id, ['activo' => 0]);
    }
}
