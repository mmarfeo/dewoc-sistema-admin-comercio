<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 fw-bold">Backup de Base de Datos</h1>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h5 class="card-title fw-bold mb-1">Descarga SQL completa</h5>
                <p class="text-muted small mb-3">
                    Genera y descarga un archivo <code>.sql</code> con la estructura y los datos de todas las tablas.
                    El dump se produce en PHP, sin llamadas al sistema (<code>exec</code>/<code>shell_exec</code>).
                </p>

                <ul class="list-unstyled small text-muted mb-4">
                    <li>✔ Incluye CREATE TABLE + INSERT INTO</li>
                    <li>✔ Compatible con MySQL 5.7+ / 8.0+</li>
                    <li>✔ Nombre del archivo con fecha y hora</li>
                    <li>✔ La descarga queda registrada en el log de auditoría</li>
                </ul>

                <form method="post" action="<?= url('admin/backup/download') ?>">
                    <?= Csrf::field() ?>
                    <button type="submit" class="btn btn-primary" id="btnBackup" onclick="this.disabled=true; this.innerHTML='⏳ Generando...'; this.form.submit();">
                        ⬇ Descargar backup
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100 bg-warning-subtle border-warning-subtle">
            <div class="card-body">
                <h5 class="card-title fw-bold text-warning-emphasis mb-1">Recomendaciones</h5>
                <ul class="small text-warning-emphasis mb-0 ps-3">
                    <li>Realizá backups periódicos y guardálos en un lugar seguro fuera del servidor.</li>
                    <li>Verificá el archivo descargado importándolo en un entorno de prueba.</li>
                    <li>Para bases de datos grandes (&gt; 50 MB), considerá usar <code>mysqldump</code> por línea de comandos.</li>
                    <li>No compartás el archivo de backup: contiene datos sensibles de clientes y transacciones.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
