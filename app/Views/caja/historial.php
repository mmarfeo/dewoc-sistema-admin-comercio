<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold">Historial de Turnos</h1>
    <div class="d-flex gap-2">
        <?php if (canEdit('caja')): ?>
        <a href="<?= url('caja/diferencias') ?>" class="btn btn-outline-danger btn-sm">Diferencias por usuario →</a>
        <?php endif; ?>
        <a href="<?= url('caja') ?>" class="btn btn-outline-secondary btn-sm">← Volver a caja</a>
    </div>
</div>

<div class="card border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="tblTurnos" class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th><th>Usuario</th><th>Apertura</th><th>Cierre</th>
                        <th class="text-end">Monto inicial</th><th class="text-end">Declarado</th>
                        <th class="text-end">Calculado</th><th class="text-end">Diferencia</th>
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
                        <td class="text-end small"><?= $t['total_calculado'] > 0 ? moneda($t['total_calculado']) : '—' ?></td>
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
<script>$(document).ready(()=>$('#tblTurnos').DataTable({order:[[0,'desc']],language:{emptyTable:'Sin turnos registrados'}}));</script>
