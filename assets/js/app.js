/* ── Dewoc — App JS ─────────────────────────────────────────── */

// ── DataTables: config por defecto ──────────────────────────
$.extend(true, $.fn.dataTable.defaults, {
    language: {
        url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-AR.json'
    },
    pageLength: 25,
    lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'Todos']],
    responsive: true,
    stateSave: true,
    fixedHeader: true,
    scrollX: true,
    dom: "<'row mb-2'<'col-sm-12 col-md-6 d-flex gap-2'B><'col-sm-12 col-md-6'f>>" +
         "<'row'<'col-sm-12'tr>>" +
         "<'row mt-2'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
    buttons: [
        {
            extend: 'colvis',
            text: '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg> Columnas',
            className: 'btn btn-sm btn-outline-secondary',
            // La columna de acciones (última) no se puede ocultar
            columns: ':not(:last-child)'
        },
        {
            extend: 'excelHtml5',
            text: '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg> Excel',
            className: 'btn btn-sm btn-outline-success',
            exportOptions: { columns: ':not(:last-child)' }
        },
        {
            extend: 'pdfHtml5',
            text: '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg> PDF',
            className: 'btn btn-sm btn-outline-danger',
            exportOptions: { columns: ':not(:last-child)' },
            orientation: 'landscape',
            pageSize: 'A4'
        }
    ]
});

// ── Toggle dark/light theme ──────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {

    const btn  = document.getElementById('btnThemeToggle');
    const html = document.documentElement;

    const savedTheme = localStorage.getItem('dewoc-theme') || 'light';
    html.setAttribute('data-theme', savedTheme);

    if (btn) {
        btn.addEventListener('click', () => {
            const current = html.getAttribute('data-theme');
            const next    = current === 'light' ? 'dark' : 'light';
            html.setAttribute('data-theme', next);
            localStorage.setItem('dewoc-theme', next);
        });
    }

    // ── Sidebar mobile toggle ────────────────────────────────
    document.querySelectorAll('[data-bs-target="#sidebarCollapse"]').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('mainNavbar')?.classList.toggle('show');
        });
    });

    // Cerrar sidebar al hacer clic fuera en mobile
    document.addEventListener('click', e => {
        const nav = document.getElementById('mainNavbar');
        if (nav && nav.classList.contains('show') && !nav.contains(e.target)) {
            nav.classList.remove('show');
        }
    });

    // ── Confirmar eliminación ────────────────────────────────
    document.querySelectorAll('[data-confirm]').forEach(el => {
        el.addEventListener('click', e => {
            if (!confirm(el.dataset.confirm || '¿Confirmar acción?')) {
                e.preventDefault();
            }
        });
    });

    // ── Auto-cerrar alertas ──────────────────────────────────
    setTimeout(() => {
        document.querySelectorAll('.alert.alert-dismissible').forEach(a => {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(a);
            if (bsAlert) bsAlert.close();
        });
    }, 5000);

});

// ── Helper: mostrar toast ────────────────────────────────────
function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container') || createToastContainer();
    const id        = 'toast-' + Date.now();
    const html      = `
        <div id="${id}" class="toast align-items-center text-bg-${type} border-0" role="alert" data-bs-autohide="true" data-bs-delay="4000">
            <div class="d-flex">
                <div class="toast-body fw-semibold">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>`;
    container.insertAdjacentHTML('beforeend', html);
    const toastEl = document.getElementById(id);
    bootstrap.Toast.getOrCreateInstance(toastEl).show();
    toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
}

function createToastContainer() {
    const div = document.createElement('div');
    div.id = 'toast-container';
    div.className = 'toast-container position-fixed bottom-0 end-0 p-3';
    div.style.zIndex = '9999';
    document.body.appendChild(div);
    return div;
}

// ── Helper: formato moneda ───────────────────────────────────
function formatMoneda(valor) {
    return '$ ' + parseFloat(valor || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
