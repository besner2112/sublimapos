    </main> <!-- Cerrar main -->

    <!-- Bootstrap 5 Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Token CSRF global para solicitudes AJAX (Fase 3) -->
    <script>
        window.CSRF_TOKEN = '<?php echo csrf_token(); ?>';
    </script>
    
    <!-- Scripts Compartidos de Utilidad -->
    <script src="assets/js/main.js"></script>

    <!-- Modal global de confirmación (Fase 10) -->
    <div class="modal fade" id="modalConfirmacionGlobal" tabindex="-1" aria-labelledby="modalConfirmacionGlobalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modal-content-premium">
                <div class="modal-header modal-header-premium">
                    <h5 class="modal-title fw-bold" id="modalConfirmacionGlobalLabel"><i class="bi bi-question-circle text-cyan me-2"></i> Confirmar acción</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body p-4" id="modalConfirmacionGlobalBody"></div>
                <div class="modal-footer modal-footer-premium">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-cyan px-4" id="modalConfirmacionGlobalOK">Sí, continuar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Script Adicional Condicionado al POS -->
    <?php if (isset($_GET['route']) && $_GET['route'] == 'pos'): ?>
        <script src="assets/js/pos.js"></script>
    <?php endif; ?>

</body>
</html>
