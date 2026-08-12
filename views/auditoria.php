<?php
// ==========================================
// Vista del Módulo de Auditoría (Solo Admin)
// ==========================================

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/AuditoriaController.php';

// Reforzar validación de Rol Administrador (Seguridad Máxima)
AuthController::requireRole('Administrador');

$logs = AuditoriaController::obtenerLogs();
?>

<div class="card-premium">
    <div class="card-header-premium">
        <div>
            <i class="bi bi-shield-check text-cyan me-2"></i>Consola de Auditoría y Seguridad del Sistema
        </div>
        <span class="badge bg-dark border border-color text-cyan fw-bold">Últimos 500 eventos</span>
    </div>

    <!-- Buscador Integrado Frontend para logs -->
    <div class="p-3 bg-dark border-bottom border-color">
        <div class="row g-2">
            <div class="col-md-4">
                <input type="text" class="form-control form-control-custom" id="audit-filter-term" placeholder="Filtrar por usuario, acción o error..." onkeyup="filterAuditLogs()">
            </div>
            <div class="col-md-3">
                <select class="form-select form-control-custom" id="audit-filter-module" onchange="filterAuditLogs()">
                    <option value="">Todos los Módulos</option>
                    <option value="Autenticación">Autenticación</option>
                    <option value="POS / Caja">POS / Caja</option>
                    <option value="POS / Ventas">POS / Ventas</option>
                    <option value="Inventario">Inventario</option>
                    <option value="Clientes">Clientes</option>
                    <option value="Auditoría y Seguridad">Auditoría y Seguridad</option>
                </select>
            </div>
            <div class="col-md-5 text-end text-secondary d-flex align-items-center justify-content-end">
                <small><i class="bi bi-info-circle me-1"></i> La información de esta consola es de sólo lectura y no se puede borrar.</small>
            </div>
        </div>
    </div>

    <div class="p-3 table-responsive" style="max-height: calc(100vh - 280px); overflow-y: auto;">
        <table class="table table-custom table-hover m-0" id="audit-logs-table">
            <thead>
                <tr>
                    <th scope="col" style="width: 15%;">Fecha y Hora</th>
                    <th scope="col" style="width: 15%;">Usuario (Rol)</th>
                    <th scope="col" style="width: 15%;">Acción</th>
                    <th scope="col" style="width: 15%;">Módulo</th>
                    <th scope="col" style="width: 30%;">Detalles</th>
                    <th scope="col" style="width: 10%;" class="text-end">IP Dirección</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr class="audit-row">
                        <td colspan="6" class="text-center text-secondary py-5">
                            Ninguna acción auditada.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr class="audit-row" data-modulo="<?php echo htmlspecialchars($log['modulo']); ?>">
                            <td style="font-size: 0.85rem;" class="font-monospace text-secondary">
                                <?php echo date("d/m/Y H:i:s", strtotime($log['fecha'])); ?>
                            </td>
                            <td>
                                <span class="fw-bold <?php echo ($log['rol_usuario'] === 'Administrador') ? 'text-cyan' : ''; ?>">
                                    <?php echo htmlspecialchars($log['nombre_usuario']); ?>
                                </span>
                                <small class="text-secondary d-block" style="font-size:0.75rem;">(<?php echo htmlspecialchars($log['rol_usuario']); ?>)</small>
                            </td>
                            <td>
                                <span class="badge border border-secondary text-secondary">
                                    <?php echo htmlspecialchars($log['accion']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="fw-semibold">{<?php echo htmlspecialchars($log['modulo']); ?>}</span>
                            </td>
                            <td style="font-size: 0.88rem; max-width: 300px; overflow-wrap: break-word;" class="text-secondary align-middle filter-text">
                                <?php echo htmlspecialchars($log['detalles']); ?>
                            </td>
                            <td class="text-end font-monospace text-secondary" style="font-size:0.85rem;">
                                <?php echo htmlspecialchars($log['ip_address']); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    /**
     * Filtra dinámicamente las filas de logs según el término de búsqueda y el módulo seleccionado.
     */
    function filterAuditLogs() {
        const term = document.getElementById('audit-filter-term').value.toLowerCase();
        const selectedModule = document.getElementById('audit-filter-module').value;
        const rows = document.querySelectorAll('#audit-logs-table tbody .audit-row');

        rows.forEach(row => {
            if (row.cells.length < 6) return; // Si es fila de vacio

            const rowText = row.innerText.toLowerCase();
            const rowModule = row.getAttribute('data-modulo');

            const matchesText = rowText.includes(term);
            const matchesModule = selectedModule === '' || rowModule === selectedModule;

            if (matchesText && matchesModule) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
