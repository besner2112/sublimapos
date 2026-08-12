// ==========================================================================
// Funciones JavaScript de Utilidad General
// ==========================================

/**
 * Formatea un valor numérico a formato de moneda en Lempiras (ej: L. 1,250.00).
 *
 * @param {number|string} value
 * @returns {string}
 */
function formatMoney(value) {
    const number = parseFloat(value);
    return isNaN(number) ? 'L. 0.00' : 'L. ' + number.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

/**
 * Muestra alertas estilo Toast en la esquina superior derecha.
 *
 * @param {string} title Título de la notificación
 * @param {string} message Mensaje descriptivo
 * @param {string} type Tipo: 'success' | 'danger' | 'warning' | 'info'
 */
function showToast(title, message, type = 'info') {
    // Buscar o instanciar el contenedor de toasts
    let container = document.querySelector('.toast-container-custom');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container-custom';
        document.body.appendChild(container);
    }

    // Crear la notificación
    const toast = document.createElement('div');
    toast.className = 'toast-custom';
    
    // Asignar color de borde según el tipo
    switch(type) {
        case 'success':
            toast.style.borderLeftColor = 'var(--success-green)';
            break;
        case 'danger':
            toast.style.borderLeftColor = 'var(--danger-red)';
            break;
        case 'warning':
            toast.style.borderLeftColor = 'var(--accent-yellow)';
            break;
        default:
            toast.style.borderLeftColor = 'var(--accent-blue)';
            break;
    }

    toast.innerHTML = `
        <div style="flex-grow: 1;">
            <div style="font-weight: 700; font-size: 0.95rem; margin-bottom: 3px;">${title}</div>
            <div style="font-size: 0.85rem; color: var(--text-secondary);">${message}</div>
        </div>
        <button type="button" style="background:none; border:none; color:var(--text-secondary); font-size:1.2rem; cursor:pointer; line-height:1;" onclick="this.parentElement.remove()">&times;</button>
    `;

    container.appendChild(toast);

    // Auto eliminar en 4.5 segundos
    setTimeout(() => {
        if (toast.parentNode) {
            toast.remove();
        }
    }, 4500);
}

// Inicializaciones generales
document.addEventListener('DOMContentLoaded', () => {
    // Prevenir reenvío doble de formularios tradicionales de POST
    const forms = document.querySelectorAll('form:not(.ajax-form)');
    forms.forEach(form => {
        form.addEventListener('submit', () => {
            const btn = form.querySelector('button[type="submit"]');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Procesando...`;
            }
        });
    });
});

// ==========================================================================
// FASE 10 — Modal global de confirmación (sustituye confirm() del navegador)
// ==========================================================================

const confirmCallbacks = { fn: null };

/**
 * Muestra un modal de confirmación reutilizable (accesible, sin confirm nativo).
 * @param {string} mensaje HTML del cuerpo del modal
 * @param {Function} onConfirm Función a ejecutar si el usuario acepta
 * @param {object} opts { titulo, boton, danger }
 */
function showConfirm(mensaje, onConfirm, opts) {
    opts = opts || {};
    const modalEl = document.getElementById('modalConfirmacionGlobal');
    if (!modalEl) {
        // Respaldo seguro sin depender del modal
        if (window.confirm(String(mensaje).replace(/<[^>]*>/g, ''))) onConfirm();
        return;
    }
    const titulo = opts.titulo || 'Confirmar acción';
    const boton = opts.boton || 'Sí, continuar';
    const body = document.getElementById('modalConfirmacionGlobalBody');
    const okBtn = document.getElementById('modalConfirmacionGlobalOK');
    const titleEl = document.getElementById('modalConfirmacionGlobalLabel');

    titleEl.innerHTML = `<i class="bi bi-question-circle text-cyan me-2"></i> ${titulo}`;
    body.innerHTML = mensaje;
    okBtn.innerHTML = boton;
    okBtn.className = 'btn px-4 ' + (opts.danger ? 'btn-danger text-white' : 'btn-cyan');
    confirmCallbacks.fn = onConfirm;

    const modal = new bootstrap.Modal(modalEl, { backdrop: 'static' });
    modal.show();
    okBtn.onclick = function () {
        modal.hide();
        if (typeof confirmCallbacks.fn === 'function') {
            const fn = confirmCallbacks.fn;
            confirmCallbacks.fn = null;
            fn();
        }
    };
    modalEl.addEventListener('hidden.bs.modal', function () {
        confirmCallbacks.fn = null;
    }, { once: true });
}
