# CHANGELOG — SUBLIMA POS

Todas las fechas en formato ISO (YYYY-MM-DD). Formato basado en Keep a Changelog.

## [1.3.0] — 2026-08-12 — Fase Final: Cierre de requisitos AWS

### Añadido / Documentado

- `README.md`: secciones "Variables de entorno" y "Estructura del proyecto";
  estado final con `FRONTEND_S3 = PENDIENTE DE MIGRACIÓN`.
- `MATRIZ_CUMPLIMIENTO_AWS.md`: fila explícita "Frontend S3/Amplify =
  ⚠️ no implementado" y "HTTPS = PENDIENTE".
- `docs/DIAGRAMA_ARQUITECTURA.md`: arquitectura actual vs. arquitectura
  objetivo S3 (con métricas de reingeniería: 44 rutas, 124 `$_SESSION`,
  63 fetch, 385 tags PHP).
- `AUDITORIA_PROGRESO.md`: sección FASE FINAL con clasificación de los 16
  requisitos (COMPLETADO / PENDIENTE / REQUIERE CONSOLA AWS).

### Verificado

- UFW 22/80/443 activo; `configtest` Syntax OK; Apache active.
- 16 rutas sensibles 403/404; assets 200; `/` 302.
- Backups intactos (600 root:root, no servibles por HTTP).
- `error.log` sin errores críticos; BD 17 tablas/21 FK (solo lectura).

### Sin cambios

- Sin modificaciones de código de aplicación ni de base de datos.

## [1.2.0] — 2026-08-12 — Fase 12-13: Documentación y pruebas finales

### Añadido

- `README.md` profesional (proyecto, tecnologías, arquitectura, seguridad,
  instalación local/AWS, script SQL).
- `MATRIZ_CUMPLIMIENTO_AWS.md` (rúbrica técnica, estado ✅/⚠️).
- `CRUD_EVIDENCIA.md` (evidencia CREATE/READ/UPDATE/DELETE por entidad).
- `docs/DIAGRAMA_ARQUITECTURA.md` y `docs/HTTPS.md`.
- `.gitignore` ampliado (credenciales, backups, logs, f11_*, claves).

### Verificado

- Pruebas finales: HTTP 302/200, login autenticado + `dashboard_datos_ajax`
  200 (RDS end-to-end), rutas internas 403, assets 200, `php -l` 0 errores,
  `error.log` sin errores.

## [1.1.0] — 2026-08-12 — Fase 11.2: Hardening AWS

### Cambiado

- Apache: `Options Indexes` desactivado en `/var/www/` (apache2.conf).
- Nueva conf `hardening.conf` (a2enconf): deniega `/database`, `/helpers`,
  `/conexion`, `/docs`, `/tests`, `/scripts`, `/backups`, `.git`, dotfiles,
  y archivos `.sql`, `.md`, `.log`, `.bak`, `.ini`, `.sh`, `.pem`, `.key`,
  `.env`, `.tmp`.
- UFW activado: inbound 22/80/443 únicamente (`default deny incoming`).
- `/etc/apache2/envvars` → 640 root:root.
- `/var/backups/f11/*` → 600 root:root.
- Template HTTPS `sites-available/sublima-ssl.conf` (deshabilitado).

### Verificado

- App completa tras los cambios (login, POS, dashboard con datos RDS).
- Rutas internas y archivos sensibles → 403/404; assets → 200.

## [1.0.0] — 2026-08-11 — Fase 11: Despliegue a AWS EC2 + RDS

### Desplegado

- Publicación en producción de las Fases 1-10: AWS EC2 `3.145.23.40`
  (Ubuntu 26.04, Apache 2.4.66, PHP 8.5.4 mod_php) + MySQL 8.4.9 en RDS
  (`sublimation_db`). 42 archivos con staging (`/var/www/html.f11_new`),
  swap y rollback instantáneo (`/var/www/html.f11_old`).
- Migración de esquema en RDS: 8 tablas nuevas (`compras`,
  `detalle_compras`, `devoluciones`, `detalle_devoluciones`,
  `intentos_login`, `movimientos_caja`, `movimientos_inventario`,
  `proveedores`) + 5 columnas en `cajas_sesiones` (`monto_ventas_efectivo`,
  `monto_ingresos`, `monto_retiros`, `monto_devoluciones`,
  `efectivo_esperado`). Datos legacy preservados (17 tablas totales).
- Backups previos verificados (BD + código, md5) en `/var/backups/f11/` y
  copia local; rollback probado (directorio `f11_old`).

### Cambiado

- `helpers/security.php`: `require_csrf()` responde **403** en vez de 419
  (Apache reescribe códigos no estándar a 500 en esta infraestructura).
- `index.php`: rechazo CSRF de `cobrar_ajax` responde **403** por la misma
  razón.
- `conexion/db.php`: sin contraseña por defecto en código (fallback `''`); en
  producción `DB_PASS` se lee de `/etc/apache2/envvars`. Sin credenciales en
  repositorio; `config.local.php` solo local.

### Infraestructura (EC2)

- Instalado `php8.5-mbstring` (requisito de runtime; faltaba y rompía todos
  los POSTs con `mb_strlen()`/`mb_substr()`).

### Corregido / Asegurado

- 44/44 pruebas HTTP contra producción (autenticación, roles, POS completo,
  IDOR, CSRF, SQLi, compras, reportes, dashboard, páginas).
- Verificación de restauración: conteos y stocks idénticos al snapshot
  pre-pruebas (ventas=1, stocks 29/149/4/15/0).

### Pendiente

- HTTPS (sin dominio conocido; requiere decisión del cliente).
- Revisión de Security Groups en consola AWS (RDS 3306 solo para la EC2).
- Contraseña del usuario `cajero` difiere del seed (`cajero123`); se
  recomienda restablecerla si el cliente la desconoce.

## [0.9.0] — 2026-08-11 — Fase 10: UI/UX y consistencia visual

### Añadido

- Capa visual Fase 10 en `assets/css/style.css` (tema claro cálido sin cambiar
  tokens existentes):
  - Encabezados de modal oscuros premium: `.modal-header-premium` (degradado
    navy `#1c2733→#131b26`) y `.modal-header-danger` (rojo oscuro) para que
    títulos `text-white` y botones `btn-close-white` sean legibles.
  - Reglas de contraste: `.card .bg-dark .text-white` y
    `.table-custom .text-white` pasan a `--text-primary` (el tema convierte los
    fondos oscuros en claros pero el texto blanco quedaba ilegible).
  - Navegación: `.nav-section-label` (grupos Operación/Administración),
    `.nav-link.active`, `.user-chip`, `.rol-badge`.
  - Login premium: `.login-wrapper`, `.login-card`, `.login-logo`.
  - POS: `.cart-total-section` (panel de totales oscuro con `#total-display`
    1.65rem), `.pos-producto`, `.pos-productos-scroll`, `.pos-caja-badge`.
  - Componentes: `.stat-box`, `.dif-efectivo-preview`, badges de estado
    (`badge-borrador`/`badge-confirmada`/`badge-anulada`) y de Kardex
    (`kardex-badge-in`/`kardex-badge-out`), `.empty-state`, toasts tipados.
  - Accesibilidad y responsive: `.skip-link`, `:focus-visible`, media queries
    (1199/991/767/575) y estilos de impresión.
- `views/includes/header.php`: navegación agrupada por rol — "Operación" (POS,
  Inventario, Clientes, Devoluciones) para todos; "Administración" (Dashboard,
  Reportes, Compras, Proveedores, Auditoría, Usuarios) solo Administrador;
  cajero recibe "Mi panel"; `aria-current="page"` en el enlace activo;
  skip-link "Saltar al contenido principal" + `<main id="app-main">`.
- `views/includes/footer.php`: modal global `#modalConfirmacionGlobal`
  (header premium, cuerpo dinámico, botón OK) + cierre de `<main>`.
- `assets/js/main.js`: `showConfirm(mensaje, onConfirm, opts)` — confirmaciones
  visuales con botón personalizable (danger/success) y respaldo a
  `window.confirm` si el modal no está disponible.
- `views/compras.php`: confirmación de compra con el modal global (mensaje con
  preview del Kardex `ENTRADA_COMPRA`) en lugar de `confirm()` nativo; badges
  de estado BORRADOR/CONFIRMADA/ANULADA; alertas → toasts.
- `views/proveedores.php`: columna "Estado" con badges Activo/Inactivo; el
  listado usa `obtenerProveedores(false)` para mostrar también inactivos
  (filas atenuadas).
- `views/inventario.php`: buscador client-side `#inv-filtro` (form GET `q`) con
  aviso `#inv-filtro-aviso` cuando no hay coincidencias (disponible para ambos
  roles); badges de Kardex de entrada/salida.
- `views/pos.php`: grid de productos `.pos-productos-scroll` con tarjetas
  `.pos-producto` operables por teclado; badge de saldo en vivo
  `.pos-caja-badge`; modal de cierre con `.modal-header-danger`; preview del
  arqueo con badge EXACTO/SOBRANTE/FALTANTE.
- `views/dashboard.php`: chips de "Mi Turno de Caja" con `.stat-box`.

### Cambiado

- Moneda del POS: `$` → `L.` (Lempira) en totales, carrito y badge de saldo.
- Tablas oscuras `table-dark` → `table table-custom` en Dashboard y Reportes
  (unificación del tema claro); ejes de Chart.js `#adb5bd` → `#59524c`.
- Color por defecto de KPIs/tarjetas resumen: `text-white` → `text-primary`.
- Devoluciones: alertas nativas → toasts; info de la venta en `text-secondary`.

### Corregido

- **Bug (contraste)**: títulos de TODOS los modales eran blancos sobre
  cabeceras claras → cabeceras oscuras premium/danger.
- **Bug (contraste)**: `text-white` sobre `.bg-dark` convertido a claro por el
  tema (tarjetas, celdas de tablas) → reglas de contraste CSS.
- **Bug (contraste)**: KPIs del dashboard y tarjetas de reportes en blanco
  sobre blanco.
- **Bug (UX)**: dos enlaces llamados "Reportes" en el nav (dashboard y
  "Reportes F8") → etiquetas "Dashboard"/"Reportes".
- **Bug (UX)**: proveedores sin estado visible → columna Estado con badges.
- **Bug (menor)**: `text-white` residual en clientes, auditoría, devoluciones,
  inventario y tarjetas de producto del POS.

### Verificado

- Batería HTTP (cliente cURL PHP `f10_cliente.php` vs `php -S 127.0.0.1:8090`):
  **63/63** (evidencia `f10_run_final.txt`): logins, 10 vistas admin + 5 cajero
  HTTP 200, 5 rutas admin para cajero → 403, AJAX JSON válidos
  (`dashboard_datos_ajax`, `buscar_productos_ajax`, `movimientos_kardex_ajax`),
  CSRF → 419, 11 marcas visuales en `style.css` + `showConfirm` en `main.js`,
  nav agrupado/aria-current/skip-link, grid POS + badge `L. 100.00`, chips
  `stat-box` (cajero), tablas claras sin `table-dark`, badges de compras y
  proveedores, filtro de inventario, sin `confirm()` de compra, y estado final
  de BD en seed (ventas/cajas/compras/devoluciones/proveedores/movimientos_caja
  = 0; 4 `INVENTARIO_INICIAL`; stocks 30/150/4/15/0; p5 `Agotado`).
- `php -l` sin errores en todos los archivos PHP (raíz y `views/`).
- MIGRACIONES: ninguna (cambios solo de presentación).

### Pendiente

- Anulación de compra en la UI (estado ANULADA existe en BD; badge verificado
  con registro directo).
- Fase 11: despliegue AWS/HTTPS, rotación de credenciales de `db.php`,
  retirar credenciales seed del login.

## [0.8.0] — 2026-08-11 — Fase 9: Dashboard administrativo con gráficas

### Añadido

- Dashboard consolidado (`views/dashboard.php`): KPIs de hoy (ventas, anuladas,
  efectivo, tarjeta, stock bajo/agotados), ventas por día, métodos de pago, top
  productos, actividad reciente y alertas de stock.
- Presets de período (Hoy/Ayer/Últimos 7 días/Mes) + personalizado; gráficas
  Chart.js desde CDN con respaldo a tablas sin librerías.
- Panel "Mi actividad" para el rol Cajero (sus ventas y su turno de caja).
- Endpoint `dashboard_datos_ajax` con indicadores exactos calculados contra BD.

### Corregido

- **Bug (menor)**: la vista del estado de caja refería la clave `saldo` en
  lugar de `saldo_efectivo` (contrato real de `obtenerEstadoCaja`).
- Errores de sintaxis `??` y variable sin uso en el panel del cajero.

### Verificado

- Batería HTTP (cliente `f9_cliente.php`): **61/61** (evidencia
  `f9_run_final.txt`): acceso por rol, indicadores exactos contra BD, series,
  presets/personalizado, validaciones 400, SQLi, IDOR, permisos, consistencia
  con MySQL y regresión Fases 1-8; BD final en estado seed.

### Pendiente

- Fase 10: UI/UX — COMPLETADA en 0.9.0.

## [0.7.0] — 2026-08-11 — Fase 8: Reportes y Consultas Operativas

### Añadido

- `controllers/ReporteController.php` (SOLO LECTURA, 100% prepared statements):
  - `reporteVentas()`: filas con anuladas (trazabilidad) + totales monetarios SOLO
    de Completadas (efectivo/tarjeta desglosados).
  - `reporteProductos()`: vendido/devuelto/neto por producto; devoluciones
    vinculadas por venta+producto mediante subconsulta agregada (sin doble conteo).
  - `reporteInventario()`: Kardex completo con stock anterior/nuevo y referencia.
  - `reporteCompras()`: totales SOLO de CONFIRMADAS; borradores como contador.
  - `reporteDevoluciones()`: cabeceras con monto de la venta original.
  - `reporteCaja()`: arqueo PERSISTIDO del cierre (nunca recalcula); resumen solo
    de turnos Cerrados (diferencias positivas/negativas).
  - `resumenDashboard()`: ventas/compras/devoluciones/caja/diferencias/top 5/
    movimientos/stock global; sin fechas → período de hoy.
  - Validación central: fechas `Y-m-d` con `checkdate` (ambas o ninguna; rango
    invertido → 400), IDs enteros, listas blancas (método/estado/tipo).
- Rutas GET autenticadas `reporte_*_ajax` ×7 con `requireRole('Administrador')`
  (cajero → 403, anónimo → login sin datos; validación fallida → HTTP 400).
- Vista `views/reportes.php` (solo Administrador): pestañas por reporte, filtros,
  totales por tab, tabla "Resumen Integrado" del período y descarga CSV.
- Navegación "Reportes" (solo Administrador).

### Corregido

- **Bug (nuevo)**: `reporteProductos` duplicaba cantidades devueltas (sin vínculo
  venta↔devolución): devuelto 2 en lugar de 1 → subconsulta por venta+producto.
- **Bug (nuevo)**: fechas inválidas (`abc/def`, `2026-13-45`) se ignoraban como
  "sin filtro" → ahora HTTP 400 con mensaje claro.
- **Bug (nuevo)**: `resumenDashboard` refería la columna inexistente `cantidad` en
  `devoluciones` (error fatal) → JOIN a `detalle_devoluciones`.
- **Bug (menor)**: top productos del dashboard sin `producto_id` → añadido.

### Verificado

- Batería HTTP (cliente cURL PHP vs `php -S 127.0.0.1:8090`): **58/58**.
  Acceso (admin 200 / cajero 403 / anónimo sin datos), los 7 reportes con totales
  exactos (ventas 345.00 ef 225.00 tar 120.00; compras 145.00; devoluciones 75.00;
  caja dif +25.00/−10.00; dashboard completo), filtros por fecha/vendedor/cliente/
  método/estado/producto/proveedor/documento/tipo, validaciones → 400, SQLi
  rechazado/sin efecto, datasets de 0 filas, consistencia reporte↔BD (ventas,
  Kardex, movimientos_caja, stocks 28/155/4/14/0) y regresión mínima Fases 1-7.
- `php -l` sin errores en `ReporteController.php` e `index.php`.
- BD directa en estado seed limpio: ventas=0, cajas=0, compras=0, devoluciones=0,
  movimientos_caja=0, solo 4 `INVENTARIO_INICIAL`, stocks 30/150/4/15/0.

### Pendiente

- Paginación real en los listados de reportes (LIMIT alto fijo actualmente).
- Fase 9: Dashboard administrativo con gráficas — COMPLETADA en 0.8.0.

## [0.6.0] — 2026-08-11 — Fase 7: Caja Ampliada

### Añadido

- `database/migrations/004_caja_ampliada.sql` (idempotente, MariaDB 10.4
  `ADD COLUMN IF NOT EXISTS`): `cajas_sesiones` ampliada con `monto_ventas_efectivo`,
  `monto_ingresos`, `monto_retiros`, `monto_devoluciones` y `efectivo_esperado`.
- `CajaController` (ampliado):
  - `abrirCaja()` registra el movimiento `APERTURA` en la misma transacción.
  - `registrarIngresoRetiro()` (nuevo): INGRESO/RETIRO transaccionales con
    `SELECT ... FOR UPDATE` sobre la sesión, motivo obligatorio, retiro ≤ saldo,
    IDOR y auditoría.
  - `obtenerEstadoCaja()` (nuevo): función ÚNICA de cálculo del saldo
    (apertura + ventas efectivo + ingresos − retiros − devoluciones) usada por
    la vista, los retiros y el cierre.
  - `cerrarCaja()` (reescrito): bloquea la sesión FOR UPDATE, calcula todas las
    partidas del arqueo ampliado, las persiste, registra el movimiento `CIERRE`
    y cierra el turno en UNA transacción.
  - `listarMovimientosCaja()` (nuevo): historial del turno.
- `VentaController::procesarVenta()`: la venta en efectivo genera EXACTAMENTE un
  movimiento `INGRESO_VENTA` (ref VENTA) en la misma transacción; Tarjeta y
  Transferencia no generan movimiento (no entra efectivo a la gaveta).
- Rutas AJAX: `caja_estado_ajax` (GET: estado + historial del turno del usuario) y
  `caja_movimiento_ajax` (POST JSON + CSRF → 419; opera SIEMPRE el turno del
  usuario autenticado, nunca recibe caja ajena).
- POS ampliado sin rediseño (`views/pos.php`): badge de saldo en vivo, modal
  "Movimientos" (ingreso/retiro con motivo), modal "Arqueo & Cierre" con partidas
  en vivo y preview de la diferencia mientras se digita el contado, historial de
  movimientos del turno.

### Reglas implementadas

- Un usuario no puede tener dos cajas abiertas; caja cerrada no recibe movimientos;
  retiro nunca supera el efectivo disponible; ingreso/retiro exigen motivo;
  cierre calcula apertura + ventas efectivo + ingresos + retiros + devoluciones +
  esperado + contado + diferencia y NO puede repetirse; trazabilidad completa
  (usuario/fecha/caja/referencia); permisos ADMIN/CAJERO; CSRF obligatorio;
  ventas y devoluciones históricas intactas (no se eliminan ni modifican).

### Verificado

- **48/48 pruebas HTTP** (evidencia `f7_run2.txt`, cliente `f7_cliente.php`):
  apertura + `APERTURA`, doble apertura rechazada, `INGRESO_VENTA` único por venta
  en efectivo (Tarjeta: 0), ingreso/retiro correctos, retiro > saldo rechazado,
  motivo obligatorio, `EGRESO_DEVOLUCION` único, caja cerrada rechaza movimientos,
  cierre correcto con diferencia positiva (+25.00) y negativa (−10.00), cálculo
  exacto del efectivo esperado (625.00 y 175.00), cierre duplicado rechazado,
  permisos (cajero nunca ve/opera caja ajena; rutas admin → 403), CSRF → 419,
  rollback sin rastros, regresión de venta/devolución/Kardex.
- `php -l` sin errores (9 archivos: `index.php`, `CajaController.php`,
  `VentaController.php`, `pos.php`, `header.php`, `DevolucionController.php`,
  `DevolucionService.php`, `devoluciones.php`, `InventarioService.php`).
- BD directa en estado seed limpio: ventas=0, cajas_sesiones=0, movimientos_caja=0,
  compras=0, proveedores=0, devoluciones=0, detalle_devoluciones=0, solo 4
  `INVENTARIO_INICIAL`, stocks 30/150/4/15/0.

### Pendiente

- Fase 8: Reportes (por fecha, vendedor, producto, caja) — COMPLETADA en 0.7.0.

## [0.5.0] — 2026-08-11 — Fase 6: Devoluciones

### Añadido

- `database/migrations/003_devoluciones.sql` (idempotente): tablas `devoluciones`
  (venta original, caja, usuario, `num_devolucion`, motivo, `monto_total`,
  `metodo_pago_venta`, estado `Completada`), `detalle_devoluciones` (cantidad y
  `precio_unitario` ORIGINAL de la venta) y `movimientos_caja` (`EGRESO_DEVOLUCION`,
  trazabilidad del dinero devuelto por turno; el arqueo existente no cambia).
- `helpers/DevolucionService.php`: `procesarDevolucion()` transaccional con
  `SELECT ... FOR UPDATE` sobre la venta, validaciones de existencia/pertenencia/
  cantidades (completa o parcial), Kardex `DEVOLUCION_VENTA` por línea vía
  `InventarioService`, `movimientos_caja` EGRESO por el total y COMMIT/ROLLBACK.
  Más `obtenerVentaParaDevolucion()`, `listarDevoluciones()`,
  `obtenerDevolucionConDetalles()`.
- `controllers/DevolucionController.php`: caja activa obligatoria (misma regla que
  ventas), IDOR (cajero solo sus ventas → 403; admin todas), auditoría.
- Rutas: vista `devoluciones` (Cajero y Administrador) y AJAX
  `venta_devolucion_datos_ajax` (404/403), `crear_devolucion_ajax` (POST JSON +
  CSRF → 419), `detalle_devolucion_ajax`.
- Vista `views/devoluciones.php`: buscar venta por ID, líneas con Vendido/Devuelto/
  Disponible, cantidades a devolver con máximo, motivo, total en vivo, listado y
  modal de detalle.
- Navegación "Devoluciones" para todos los roles.

### Reglas implementadas

- Completa/parcial y multi-producto; nunca más de lo vendido (disponible = vendido −
  devuelto previo por venta+producto); venta debe existir y estar `Completada`
  (anulada → rechazada); producto debe pertenecer a la venta; monto devuelto al
  precio ORIGINAL del detalle (ISV incluido); venta original NUNCA se modifica;
  stock reintegrado en la misma transacción; dinero devuelto como EGRESO en el
  turno activo; auditoría.

### Corregido

- **Bug (nuevo)**: `SQLSTATE[42S22]` columna `c.numero_identidad` inexistente en el
  JOIN a `clientes` de la consulta de bloqueo de la venta → se eliminó el JOIN
  (dato no usado). (`helpers/DevolucionService.php`)

### Verificado

- Batería HTTP (cliente cURL PHP vs `php -S 127.0.0.1:8090`): **47/47**.
  Devolución completa, parcial, múltiples productos, exceso de cantidad rechazado,
  venta inexistente/anulada rechazada, producto ajeno rechazado, CSRF 419, cajero
  403 en venta ajena y OK en su propia venta (egreso en SU caja), stocks exactos
  (27/150/4/15/0), Kardex `DEVOLUCION_VENTA` con stock_anterior/nuevo exactos,
  sumas de caja exactas por turno, ROLLBACK total (item válido + ajeno → sin
  registros, stock intacto), cantidades 0/negativas y sin productos rechazadas,
  devolución sin caja abierta rechazada.
- Regresión completa: venta (SALIDA_VENTA), anulación (AJUSTE_ENTRADA + estado
  Anulada) y Kardex intactos.
- `php -l` sin errores en los 8 PHP involucrados.
- BD tras pruebas: seed limpio verificado por consulta directa (0 ventas/cajas/
  compras/devoluciones/movimientos_caja, solo 4 `INVENTARIO_INICIAL`,
  stocks 30/150/4/15/0).

### Pendiente

- Devoluciones a proveedor (`DEVOLUCION_COMPRA` reservado en el ENUM del Kardex,
  sin uso) — fase futura.
- Fase 7: Caja ampliada — COMPLETADA en 0.6.0 (ingresos/retiros/movimientos,
  arqueo ampliado persistido, reportes de caja en 0.7.0).

## [0.4.0] — 2026-08-11 — Fase 5: Proveedores y Compras

### Añadido

- `database/migrations/002_compras.sql`: tablas `proveedores` (CRUD con baja
  lógica `activo=0`), `compras` (estados BORRADOR/CONFIRMADA/ANULADA, totales)
  y `detalle_compras` (cantidad, costo_unitario, subtotal). FKs e índices.
  Idempotente.
- `helpers/CompraService.php`: `crearBorrador()` (no toca stock),
  `confirmarCompra()` (transaccional, idempotente, bloquea con `FOR UPDATE`),
  `listarCompras()`, `obtenerCompraConDetalles()`.
- `controllers/ProveedorController.php`: CRUD proveedores con validación
  backend y auditoría.
- `controllers/CompraController.php`: borrador, confirmación, listado, detalle.
- Vistas `views/proveedores.php` y `views/compras.php` (patrón visual existente):
  búsqueda, modales, filas dinámicas de productos con cálculo de subtotal/
  impuesto(16%)/total en vivo, guardar BORRADOR y confirmar vía AJAX con CSRF.
- Rutas `proveedores`, `compras` (solo Administrador, 403 backend para cajeros)
  y AJAX `buscar_proveedores_ajax`, `crear_compra_ajax`, `confirmar_compra_ajax`,
  `detalle_compra_ajax` (POST con `require_csrf()` → 419).
- Navegación "Compras" y "Proveedores" (solo Administrador).

### Cambiado

- Al confirmar una compra: un movimiento `ENTRADA_COMPRA` por detalle con
  referencia `COMPRA`/ID, `costo_unitario` del detalle y usuario que confirma;
  recalcula totales desde la BD; todo en una única transacción con COMMIT.
- `views/includes/header.php` (enlaces de navegación).

### Corregido

- **Bug (nuevo)**: `buscarProveedores` lanzaba `HY093 Invalid parameter number`
  (placeholder `:q` repetido con `EMULATE_PREPARES=false`). Corregido con
  placeholders únicos `:q1..:q4`.

### Verificado

- Batería HTTP (cliente cURL PHP vs `php -S 127.0.0.1:8090`): **52/52**.
  Proveedores (crear/editar/desactivar/buscar, cajero 403, CSRF), compras
  (borrador, totales, confirmación, stock 10→15 patrón verificado con
  30→35 y 15→18, Kardex exacto `ENTRADA_COMPRA|5|30|35|COMPRA|1|1`),
  doble confirmación rechazada sin duplicar stock/Kardex, validaciones
  (cantidad 0/negativa, costo negativo, producto/proveedor inexistentes,
  proveedor inactivo, sin detalles), SQL injection en documento segura,
  ATOMICIDAD con ROLLBACK completo (3 productos, fallo en el 2º: compra
  BORRADOR, stocks y Kardex intactos).
- Regresión completa (venta, anulación, ajustes, Kardex, roles, POS).
- `php -l` sin errores en los 7 archivos PHP de la fase.
- BD tras pruebas: seed limpio (4 INVENTARIO_INICIAL, stocks 30/150/4/15/0,
  0 ventas, 0 cajas, 0 compras, 0 proveedores de prueba).

### Pendiente

- Anulación de compra (requiere tipos de reversión en el ENUM del Kardex;
  hoy las compras confirmadas no pueden anularse — se reporta para fase
  futura de devoluciones).
- Edición de borradores guardados; costo promedio ponderado.

## [0.3.0] — 2026-08-11 — Fase 4: Kardex y control seguro del stock

### Añadido

- `database/migrations/001_kardex.sql`: tabla `movimientos_inventario`
  (tipo, cantidad, stock anterior/nuevo, referencia, usuario, observaciones)
  + 4 movimientos `INVENTARIO_INICIAL` con los stocks seed. Idempotente.
- `helpers/InventarioService.php`: `aplicarMovimiento()` (transaccional:
  valida producto, normaliza signo, `SELECT ... FOR UPDATE`, stock no
  negativo, respeta `Descontinuado`, inserta Kardex en la misma transacción)
  y `obtenerMovimientos()` (pagina y une usuario).
- Ajuste de inventario (solo Administrador): entrada/salida con motivo
  obligatorio, token CSRF (`X-CSRF-Token`), registro Kardex.
- Lectura Kardex AJAX (`movimientos_kardex_ajax`, GET) + modal de
  movimientos en Inventario.
- UI Inventario: campo stock read-only con botón "Ajustar"; alta de
  producto registra `INVENTARIO_INICIAL` automáticamente.

### Cambiado

- Venta: registra `SALIDA_VENTA` (ref `VENTA`) en la misma transacción;
  rechazo de stock insuficiente con mensaje (disponible/solicitado).
- Anulación: registra `AJUSTE_ENTRADA` (ref `VENTA_ANULADA`) y YA NO
  fuerza `disponibilidad='Disponible'` (respeta `Descontinuado`).

### Corregido

- **Bug B3**: producto `Descontinuado` volvía a `Disponible` al anular
  una venta. Ahora respeta el estado previo (verificado con venta de prod2).
- **Bug B4**: `editarProducto` ya NO modifica el stock; responde aviso
  "El stock NO fue modificado. Solo puede cambiarse mediante Ajuste de
  Inventario" y el stock real queda intacto (sin Kardex nuevo).
- **Bug (nuevo)**: `crearProducto` aplicaba el stock inicial dos veces
  (INSERT con stock + movimiento Kardex). Ahora inserta en 0 y el Kardex
  registra `INVENTARIO_INICIAL` una sola vez.
- **Bug (nuevo)**: `obtenerMovimientos` lanzaba `HY093 Invalid parameter
  number` (mezcla de `bindValue` con `execute` de array). Corregido con
  parámetros unificados.

### Verificado

- Batería HTTP (cliente cURL PHP vs `php -S 127.0.0.1:8090`): **36/36**.
- Atomicidad: PASS (error forzado tras un movimiento válido → ROLLBACK
  total, stock y Kardex intactos).
- `php -l` sin errores en los archivos modificados.
- BD tras pruebas: solo 4 `INVENTARIO_INICIAL`, stocks seed, ventas=0,
  cajas=0, p2 `Disponible`.

## [0.2.0] — 2026-08-10 — Fase 3: CSRF, sesiones y autorización

### Corregido

- **CSRF (V3)**: token centralizado (`helpers/security.php`) en todos los
  formularios y AJAX. POST sin token → HTTP 419.
- **Fijación de sesión (V4)**: `session_regenerate_id(true)` tras login.
- **Logout CSRF por GET (V5)**: logout solo por POST con token; GET → 405.
- **IDOR ventas (V6)**: el cajero solo ve ventas de su caja; ajenas → 403.
- **IDOR cierre de caja (V7)**: solo cajas propias; ajenas → 403.
- **Fuerza bruta (V10)**: tabla `intentos_login` + bloqueo de 15 min tras
  5 intentos fallidos por IP+usuario.
- **Cookies de sesión (V12)**: HttpOnly, SameSite Lax, strict mode; Secure
  solo bajo HTTPS.

### Añadido

- `helpers/security.php`: `csrf_token()`, `verify_csrf_token()`, `require_csrf()`.
- `database/migrations/001_intentos_login.sql`: migración de rate-limit.

### Seguridad (pendiente)

- V1 (credenciales AWS en `db.php`) → Fase 12. V8 (XSS) y V13 (ISV) → fases
  de UI/configuración. V9 (credenciales seed en login) → retirar en producción.

### Verificado

- 30/30 pruebas HTTP (servidor embebido): CSRF, sesión regenerada, logout,
  caja, roles (403/200), IDOR (403/404), rate-limit y cobro AJAX.
- BD limpia tras pruebas (0 registros de prueba) y seed intacto.

## [0.1.0] — 2026-08-10 — Fase 2: Preparar entorno local

### Corregido

- **Hashes seed inválidos** en `schema.sql`: regenerados para que
  `password_verify()` devuelva TRUE con las credenciales de prueba documentadas
  (`admin123`, `cajero123`). Verificado con PHP y contra la BD.

### Añadido

- BD local nueva **`sublimation_db`** creada a partir de `schema.sql`
  (8 tablas + seed: usuarios, categorías, productos, clientes).
- `backups/sublimacion_db_legacy_20260810.sql`: respaldo lógico de la BD legacy
  `sublimacion_db` (no se modifica ni elimina).
- `config.local.php`: credenciales locales por variables de entorno
  (127.0.0.1 / sublimation_db / root). Excluido de repos vía `.gitignore`.
- `.gitignore`: protege `config.local.php` y `backups/`.

### Cambiado

- `conexion/db.php`: carga opcional de `config.local.php` y nueva función
  `envDb()` (distingue variable de entorno "no definida" de "vacía").
  El mecanismo original de fallback a AWS se conserva intacto.

### Seguridad (sin resolver todavía)

- Credenciales reales de AWS siguen como fallback en `db.php` — pendiente
  rotación y retiro al desplegar (Fase 12).

### Verificado

- Conexión PHP→MySQL local, login admin y cajero (CLI y HTTP), rechazo de
  credenciales incorrectas, 403 por rol en dashboard, 8/8 tablas presentes.

## [0.0.0] — 2026-08-10

### Inicio del proyecto de mejora (copia local)

Estado inicial del repositorio local: sistema SUBLIMA POS existente (PHP nativo,
MySQL, Bootstrap 5) que opera en AWS. Se inicia el ciclo AUDITAR → CORREGIR →
MEJORAR → AMPLIAR → PROBAR → DOCUMENTAR → PREPARAR PARA AWS sobre la copia local.

### Añadido

- `AUDITORIA_PROGRESO.md`: informe completo de la Fase 1 (auditoría) con
  vulnerabilidades (V1–V14), bugs (B1–B10), mapa funcional, BD, roles, plan de
  implementación y elementos no verificados.
- `CHANGELOG.md`: este archivo.

### Hallazgos críticos (Fase 1 — sin corrección aún)

- Credenciales reales de AWS RDS hardcodeadas en `conexion/db.php` (V1) → pendiente
  rotación + variables de entorno.
- Hashes seed de `schema.sql` no coinciden con las contraseñas documentadas
  `admin123` / `cajero123` (verificado con `password_verify()`).
- Ausencia total de CSRF en formularios y endpoints AJAX.
- Sin `session_regenerate_id` tras el login (fijación de sesión).
- La BD local `sublimacion_db` es un esquema legacy incompatible con el código actual;
  el código espera el esquema nuevo `sublimation_db` (definido en `schema.sql`).

### Pendiente

- Fase 9: Dashboard administrativo con gráficas — COMPLETADA en 0.8.0.
- Fase 10: UI/UX (paginación, ticket imprimible, ISV centralizado) — COMPLETADA
  en 0.9.0 (UI/UX); quedan del listado original: paginación real y ticket
  imprimible para fase futura.
- Fases 11-13: AWS/HTTPS, README/CHANGELOG final, pruebas finales.
