# EVIDENCIA CRUD — Sublima POS

> Tabla requisito → funcionalidad → archivo → evidencia.
> Verificado en las Fases 1–11 (batería de pruebas funcionales sobre
> producción, sin datos destructivos). Rutas según `index.php`.

| Requisito | CREATE | READ | UPDATE | DELETE | Funcionalidad (ruta) | Archivos | Evidencia |
|---|---|---|---|---|---|---|---|
| Usuarios | ✅ | ✅ | ✅ | ✅ (baja lógica) | `crear_usuario_ajax`, `cambiar_estado_usuario_ajax`, `usuarios` | `controllers/UsuarioController.php`, `views/usuarios.php` | Batería F11: A4 (crear cajero), A5 (estado) |
| Productos/Inventario | ✅ | ✅ | ✅ | ✅ (baja lógica) | `crear_producto_ajax` (vía inventario), `buscar_productos_ajax`, `ajuste_stock_ajax`, `inventario` | `controllers/InventarioController.php`, `helpers/InventarioService.php`, `views/inventario.php` | Batería F11: A1–A3 (crear, listar, editar), C5 (venta usa stock) |
| Clientes | ✅ | ✅ | ✅ | ✅ (baja lógica) | `clientes` | `controllers/ClienteController.php`, `views/clientes.php` | Batería F11: creación y listado de clientes |
| Proveedores | ✅ | ✅ | ✅ | ✅ (baja lógica) | `proveedores` | `controllers/ProveedorController.php`, `views/proveedores.php` | Batería F11: G1 |
| Compras | ✅ | ✅ | ✅ (confirmar) | ✅ (anular) | `crear_compra_ajax`, `confirmar_compra_ajax`, `detalle_compra_ajax`, `compras` | `controllers/CompraController.php`, `helpers/CompraService.php`, `views/compras.php` | Batería F11: G2–G4 (borrador→confirmada→stock/Kardex) |
| Ventas (POS) | ✅ | ✅ | ✅ (cambio) | ✅ (anulación admin) | `cobrar_ajax`, `buscar_codigo_ajax`, `pos` | `controllers/VentaController.php`, `views/pos.php` | Batería F11: C5–C6 (ventas efectivo/tarjeta) |
| Caja | ✅ | ✅ | ✅ | ✅ (cierre) | `caja_estado_ajax`, `caja_movimiento_ajax`, `pos` | `controllers/CajaController.php`, `views/pos.php` | Batería F11: C2–C14 (apertura, movimientos, arqueo, cierre) |
| Devoluciones | ✅ | ✅ | ✅ | ✅ (estado) | `crear_devolucion_ajax`, `detalle_devolucion_ajax`, `venta_devolucion_datos_ajax`, `devoluciones` | `controllers/DevolucionController.php`, `helpers/DevolucionService.php`, `views/devoluciones.php` | Batería F11: C11 (devolución parcial) |
| Kardex | ✅ (registro) | ✅ | — | — (histórico) | `movimientos_kardex_ajax`, `reporte_inventario_ajax` | `helpers/InventarioService.php`, `controllers/ReporteController.php` | Batería F11: H3 (movimientos entrada/salida) |
| Reportes | — | ✅ | — | — | `reporte_*_ajax` (ventas, productos, inventario, compras, devoluciones, caja), `dashboard_datos_ajax` | `controllers/ReporteController.php`, `views/reportes.php`, `views/dashboard.php` | Batería F11: E1–E6 |
| Auditoría | ✅ (registro) | ✅ | — | — (histórico) | `auditoria` | `controllers/AuditoriaController.php`, `views/auditoria.php` | Auditoría 11.1: registros presentes |

**Convenciones:** DELETE = baja lógica (`activo=0`, estado) para preservar
integridad referencial; las operaciones destructivas físicas no se usan en
producción.
