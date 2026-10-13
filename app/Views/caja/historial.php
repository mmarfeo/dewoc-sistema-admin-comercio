<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold">Historial de Turnos</h1>
    <div class="d-flex gap-2">
        <?php if (canEdit('caja')): ?>
        <a href="<?= url('caja/diferencias') ?>" class="btn btn-outline-danger btn-sm">Diferencias por usuario →</a>
        <?php endif; ?>
        <a href="<?= url('caja') ?>" class="btn btn-outline-secondary btn-sm">← Volver a caja</a>
    </div>
</div>

<?php if (!empty($turnosAbiertos)): ?>
<!--
  Cajas abiertas ahora mismo. Los montos son el cierre ESPERADO de cada turno (monto
  inicial + ingresos − egresos), así que solo se muestran a quien NO cierra a ciegas: el
  cierre ciego existe para que el cajero declare lo que contó sin saber antes cuánto
  tendría que haber (ver CajaModel::cerrar()). A quien cierra a ciegas se le muestra la
  caja y la hora de apertura, pero no los montos.
-->
<div class="card border-0 mb-4">
    <div class="card-header border-0 bg-transparent d-flex align-items-center justify-content-between flex-wrap gap-2">
        <span class="fw-semibold">
            Cajas abiertas ahora
            <span class="badge bg-success-soft text-success ms-1"><?= count($turnosAbiertos) ?></span>
        </span>
        <?php if ($cierraCiego): ?>
        <span class="small text-muted">Los montos no se muestran porque tu cierre de caja es a ciegas.</span>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>#</th><th>Cajero</th><th>Abierta desde</th><th>Hace</th>
                        <th class="text-end">Monto inicial</th>
                        <?php if (!$cierraCiego): ?>
                        <th class="text-end">Efectivo esperado</th>
                        <th class="text-end">Cobrado electrónico</th>
                        <th class="text-center">Egresos</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($turnosAbiertos as $ta):
                        // "Hace" se calcula contra la hora de PHP (Argentina), igual que el
                        // resto del sistema: el servidor de MySQL puede estar en otro huso.
                        $minutos = max(0, (int) round((time() - strtotime($ta['apertura_at'])) / 60));
                        $hace    = $minutos < 60
                            ? $minutos . ' min'
                            : intdiv($minutos, 60) . ' h ' . ($minutos % 60) . ' min';
                    ?>
                    <tr>
                        <td class="small fw-semibold"><?= (int)$ta['numero'] ?></td>
                        <td class="small"><?= e($ta['usuario_nombre'] ?? '—') ?></td>
                        <td class="small"><?= fechaHora($ta['apertura_at']) ?></td>
                        <td class="small text-muted"><?= e($hace) ?></td>
                        <td class="text-end small"><?= moneda((float)$ta['monto_inicial']) ?></td>
                        <?php if (!$cierraCiego): ?>
                        <td class="text-end small fw-semibold"><?= moneda((float)$ta['efectivo_esperado']) ?></td>
                        <td class="text-end small"><?= moneda((float)$ta['electronico']) ?></td>
                        <td class="text-center small text-muted">
                            <?php $esp = $ta['esperado']; ?>
                            <span class="<?= (float)$esp['total_egresos'] > 0 ? 'text-danger' : '' ?>"
                                  title="Retiros y pagos hechos desde esta caja durante el turno">
                                <?= (float)$esp['total_egresos'] > 0 ? '− ' . moneda((float)$esp['total_egresos']) : '—' ?>
                            </span>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if (!$cierraCiego): ?>
    <div class="card-footer border-0 bg-transparent small text-muted">
        <strong>Efectivo esperado</strong> es lo que debería haber en el cajón: monto inicial + cobros
        en efectivo − egresos. <strong>Cobrado electrónico</strong> (Mercado Pago, débito, crédito,
        transferencia) no está en la caja física. Las ventas anuladas no se cuentan.
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Filtro por estado: los turnos abiertos son lo único accionable de esta pantalla -->
<ul class="nav nav-pills mb-3">
    <?php foreach (['' => 'Todos', 'abierto' => 'Abiertos', 'cerrado' => 'Cerrados'] as $valor => $label): ?>
    <li class="nav-item">
        <a class="nav-link <?= $estadoFiltro === $valor ? 'active' : '' ?>"
           href="<?= url('caja/historial' . ($valor !== '' ? '?estado=' . $valor : '')) ?>">
            <?= e($label) ?>
            <?php if ($valor === 'abierto' && !empty($turnosAbiertos)): ?>
            <span class="badge bg-light text-dark ms-1"><?= count($turnosAbiertos) ?></span>
            <?php endif; ?>
        </a>
    </li>
    <?php endforeach; ?>
</ul>

<div class="card border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="tblTurnos" class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th><th>Usuario</th><th>Apertura</th><th>Cierre</th>
                        <th class="text-end">Monto inicial</th><th class="text-end">Declarado</th>
                        <th class="text-end" title="Cerrados: el calculado del cierre. Abiertos: el efectivo esperado hasta ahora">Calculado</th><th class="text-end">Diferencia</th>
                        <th>Estado</th><th>Ticket</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($turnos as $t): ?>
                    <tr>
                        <td><?= $t['numero'] ?></td>
                        <td class="small"><?= e($t['usuario_nombre'] ?? '—') ?></td>
                        <td class="small"><?= fechaHora($t['apertura_at']) ?></td>
                        <td class="small"><?= $t['cierre_at'] ? fechaHora($t['cierre_at']) : '—' ?></td>
                        <td class="text-end small"><?= moneda($t['monto_inicial']) ?></td>
                        <?php if ($cierraCiego): ?>
                        <td class="text-end small">—</td>
                        <td class="text-end small">—</td>
                        <td class="text-end small">—</td>
                        <?php else: ?>
                        <td class="text-end small"><?= $t['monto_cierre_declarado'] !== null ? moneda($t['monto_cierre_declarado']) : '—' ?></td>
                        <td class="text-end small">
                            <?php if ($t['estado'] === 'abierto'): ?>
                            <?php
                            // Un turno abierto todavía no tiene monto_cierre_calculado (se
                            // escribe al cerrar), así que acá iba un guion. Se muestra el
                            // efectivo esperado acumulado, que es el dato útil mientras opera.
                            $abierto = null;
                            foreach ($turnosAbiertos as $ta) {
                                if ((int)$ta['id'] === (int)$t['id']) { $abierto = $ta; break; }
                            }
                            ?>
                            <?php if ($abierto): ?>
                            <span class="text-success" title="Efectivo esperado en caja, acumulado hasta ahora">
                                <?= moneda((float)$abierto['efectivo_esperado']) ?>
                            </span>
                            <?php else: ?>—<?php endif; ?>
                            <?php else: ?>
                            <?= $t['total_calculado'] > 0 ? moneda($t['total_calculado']) : '—' ?>
                            <?php endif; ?>
                        </td>
                        <td class="text-end small <?= ($t['diferencia'] ?? 0) < 0 ? 'text-danger fw-bold' : 'text-success' ?>">
                            <?= $t['diferencia'] !== null ? (($t['diferencia'] >= 0 ? '+':'').moneda($t['diferencia'])) : '—' ?>
                        </td>
                        <?php endif; ?>
                        <td><span class="badge <?= $t['estado']==='abierto' ? 'bg-success-soft text-success' : 'bg-secondary-soft text-secondary' ?>"><?= ucfirst($t['estado']) ?></span></td>
                        <td>
                            <?php if ($t['estado'] === 'cerrado'): ?>
                            <a href="<?= url('caja/ticket?id='.$t['id']) ?>" target="_blank" class="btn btn-outline-secondary btn-sm py-0">🖨</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>
// order: [] deja el orden que trae el servidor, que ya pone los turnos abiertos primero
// (ver CajaModel::historial). Con order [[0,'desc']] DataTables reordenaba por número y los
// abiertos volvían a mezclarse entre los cerrados.
$(document).ready(() => $('#tblTurnos').DataTable({
    order: [],
    language: { emptyTable: '<?= $estadoFiltro === 'abierto' ? 'No hay ninguna caja abierta en este momento' : 'Sin turnos registrados' ?>' }
}));
</script>
