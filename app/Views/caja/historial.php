<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold">Historial de Turnos</h1>
    <a href="<?= url('caja') ?>" class="btn btn-outline-secondary btn-sm">← Volver a caja</a>
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
                        <th>Estado</th>
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
                        <td class="text-end small"><?= $t['monto_cierre_declarado'] !== null ? moneda($t['monto_cierre_declarado']) : '—' ?></td>
                        <td class="text-end small"><?= $t['total_calculado'] > 0 ? moneda($t['total_calculado']) : '—' ?></td>
                        <td class="text-end small <?= ($t['diferencia'] ?? 0) < 0 ? 'text-danger fw-bold' : 'text-success' ?>">
                            <?= $t['diferencia'] !== null ? (($t['diferencia'] >= 0 ? '+':'').moneda($t['diferencia'])) : '—' ?>
                        </td>
                        <td><span class="badge <?= $t['estado']==='abierto' ? 'bg-success-soft text-success' : 'bg-secondary-soft text-secondary' ?>"><?= ucfirst($t['estado']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!$turnos): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">Sin turnos registrados</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>$(document).ready(()=>$('#tblTurnos').DataTable({order:[[0,'desc']]}));</script>
