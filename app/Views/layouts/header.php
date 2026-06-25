<?php $user = Session::user(); ?>
<div class="navbar-header d-flex align-items-center justify-content-between px-4 py-3 border-bottom bg-white">

    <!-- Título de página -->
    <div class="d-flex align-items-center">
        <button class="btn btn-link text-body d-lg-none me-2 p-0" data-bs-toggle="collapse" data-bs-target="#sidebarCollapse">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('dashboard') ?>">Inicio</a></li>
                <?php if (!empty($breadcrumb)): ?>
                    <?php foreach ($breadcrumb as $label => $href): ?>
                        <?php if ($href): ?>
                            <li class="breadcrumb-item"><a href="<?= url($href) ?>"><?= e($label) ?></a></li>
                        <?php else: ?>
                            <li class="breadcrumb-item active"><?= e($label) ?></li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ol>
        </nav>
    </div>

    <!-- Acciones derecha -->
    <div class="d-flex align-items-center gap-3">

        <!-- Toggle dark/light -->
        <button class="btn btn-link text-body p-0" id="btnThemeToggle" title="Cambiar tema">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        </button>

        <!-- Notificaciones (futuro) -->
        <div class="dropdown">
            <button class="btn btn-link text-body p-0 position-relative" data-bs-toggle="dropdown">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:.6rem">!</span>
            </button>
            <div class="dropdown-menu dropdown-menu-end p-3" style="min-width:280px">
                <h6 class="dropdown-header px-0">Notificaciones</h6>
                <div class="text-center text-body-secondary py-3 small">Sin notificaciones pendientes</div>
            </div>
        </div>

        <!-- Usuario -->
        <div class="dropdown">
            <button class="btn btn-link text-body p-0 d-flex align-items-center gap-2" data-bs-toggle="dropdown">
                <div class="avatar avatar-sm">
                    <?php if (!empty($user['foto'])): ?>
                        <img src="<?= asset('uploads/usuarios/' . e($user['foto'])) ?>" class="avatar-img rounded-circle" alt="">
                    <?php else: ?>
                        <span class="avatar-title rounded-circle bg-primary text-white fw-bold">
                            <?= strtoupper(substr($user['nombre'] ?? 'U', 0, 1)) ?>
                        </span>
                    <?php endif; ?>
                </div>
                <span class="d-none d-md-inline fw-semibold small"><?= e($user['nombre'] ?? '') ?></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><h6 class="dropdown-header"><?= e($user['nombre'] ?? '') ?></h6></li>
                <li><span class="dropdown-item-text text-muted small"><?= e($user['email'] ?? '') ?></span></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="<?= url('usuarios/perfil') ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="me-2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    Mi Perfil
                </a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="<?= url('auth/logout') ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="me-2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    Cerrar sesión
                </a></li>
            </ul>
        </div>

    </div>
</div>

<!-- Flash messages -->
<?php $flash = Session::flash('mensaje'); ?>
<?php if ($flash): ?>
<div class="container-fluid pt-3">
    <div class="alert alert-<?= e($flash['tipo'] ?? 'info') ?> alert-dismissible fade show" role="alert">
        <?= e($flash['texto']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
</div>
<?php endif; ?>
