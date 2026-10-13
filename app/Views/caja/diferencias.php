<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold">Diferencias de Caja por Usuario</h1>
    <a href="<?= url('caja/historial') ?>" class="btn btn-outline-secondary btn-sm">← Historial</a>
</div>

<!-- Filtros -->
<div class="card border-0 mb-4">
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-sm-4">
                <label class="form-label fw-semibold">Desde</label>
                <input type="date" name="desde" class="form-control" value="<?= e($desde) ?>">
            </div>
            <div class="col-sm-4">
                <label class="form-label fw-semibold">Hasta</label>
                <input type="date" name="hasta" class="form-control" value="<?= e($hasta) ?>">
            </div>
            <div class="col-sm-4">
                <button type="submit" class="btn btn-primary w-100">Filtrar</button>
            </div>
        </form>
    </div>
</div>

<div class="card border-0">
    <div class="card-header border-0 bg-transparent fw-semibold d-flex justify-content-between">
        <span>Acumulado por usuario</span>
        <span class="badge bg-secondary"><?= count($filas) ?> usuarios</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="tblDiferencias" class="table table-hover table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Usuario</th>
                        <th class="text-end">Cierres</th>
                        <th class="text-end">Con diferencia</th>
                        <th class="text-end">Cierres ciegos</th>
                        <th class="text-end">Diferencia efectivo</th>
                        <th class="text-end">Diferencia MP</th>
                        <th class="text-end">Impacto absoluto</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($filas as $f): ?>
                    <?php $difEf = (float)$f['diferencia_efectivo_total']; $difMp = (float)$f['diferencia_mp_total']; ?>
                    <tr>
                        <td class="fw-semibold"><?= e($f['usuario_nombre']) ?></td>
                        <td class="text-end"><?= (int)$f['total_cierres'] ?></td>
                        <td class="text-end"><?= (int)$f['cierres_con_diferencia'] ?></td>
                        <td class="text-end"><?= (int)$f['cierres_ciegos'] ?></td>
                        <td class="text-end <?= $difEf < 0 ? 'text-danger fw-bold' : 'text-success' ?>"><?= ($difEf >= 0 ? '+' : '') . moneda($difEf) ?></td>
                        <td class="text-end <?= $difMp < 0 ? 'text-danger fw-bold' : 'text-success' ?>"><?= ($difMp >= 0 ? '+' : '') . moneda($difMp) ?></td>
                        <td class="text-end fw-bold"><?= moneda((float)$f['impacto_absoluto_total']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php /*
                      Sin filas NO se dibuja una fila con colspan: DataTables cuenta las
                      celdas del tbody para validar la tabla y un colspan le da 1 columna
                      contra las 7 del thead, lo que dispara "Incorrect column count" y deja
                      la tabla sin inicializar (se pierden el orden y el buscador). El cartel
                      de tabla vacía lo pone DataTables con language.emptyTable, abajo.
                    */ ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>$(document).ready(()=>$('#tblDiferencias').DataTable({order:[[6,'desc']],language:{emptyTable:'Sin cierres en el período seleccionado.'}}));</script>
