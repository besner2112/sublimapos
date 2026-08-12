# AUDITORÍA PROGRESO — SUBLIMA POS

> Memoria técnica del proyecto. Se actualiza al cierre de cada fase.
> Última actualización: 2026-08-12 — Fase Final (Cierre de requisitos AWS) registrada.

---

## FASE FINAL — CIERRE DE REQUISITOS AWS (2026-08-12) — REGISTRO

### Análisis de requisitos (sin cambios de arquitectura)

| Requisito | Clasificación |
|---|---|
| URL pública | COMPLETADO (`http://3.145.23.40`, 302→login) |
| CRUD (documentación) | COMPLETADO (`CRUD_EVIDENCIA.md`) |
| Backend EC2 | COMPLETADO |
| RDS | COMPLETADO (3306 filtrado desde Internet, verificado) |
| Variables de entorno | COMPLETADO (`DB_PASS` en `envvars` 640; `envDb()`; sin credenciales en código) |
| Archivos sensibles | COMPLETADO (403/404 en 16/17 rutas; assets 200) |
| Firewall UFW | COMPLETADO (22/80/443 inbound; 3306 no expuesto) |
| Backups | COMPLETADO (`/var/backups/f11/` intactos, 600 root:root, no servibles por HTTP — 403) |
| README / documentación | COMPLETADO (secciones requeridas verificadas + estructura del proyecto y variables de entorno añadidas) |
| Script SQL | COMPLETADO (`schema.sql` + migraciones 001–004) |
| Diagrama | COMPLETADO (arquitectura actual + objetivo S3 documentadas) |
| Matriz de cumplimiento | COMPLETADO (fila Frontend S3/Amplify = ⚠️ no implementado) |
| Security Group EC2 (SSH 22) | REQUIERE ACCIÓN MANUAL EN CONSOLA AWS (documentado: puerto 22/TCP, origen `0.0.0.0/0`, riesgo alto, recomendación: restringir a IP admin) |
| RDS Publicly Accessible | REQUIERE VERIFICACIÓN MANUAL EN CONSOLA AWS (sin AWS CLI/IAM en la instancia; externamente 3306 filtrado) |
| HTTPS | PENDIENTE (sin dominio propio; procedimiento documentado en `docs/HTTPS.md`; template `sublima-ssl.conf` listo) |
| Frontend S3/Amplify | PENDIENTE DE MIGRACIÓN — `FRONTEND_S3 = PENDIENTE DE MIGRACIÓN` |
| Repositorio remoto | PENDIENTE (sin URL; no se inventa) |

### Análisis de viabilidad Frontend S3 (métricas reales)

- 31 archivos PHP; **44 rutas** `index.php?route=...`; **124 usos** de
  `$_SESSION`; **19 formularios POST**; **63 llamadas fetch()**; **385 tags
  PHP** de renderizado en `views/`; **67 referencias** CSRF.
- Migrar a S3 requiere: API REST JSON (44 endpoints), autenticación por
  token (sesiones→JWT), CORS (63 fetch), formularios→AJAX y vistas→HTML
  estático. **Reingeniería importante, no se realiza.** El sistema actual
  permanece intacto.

### Verificaciones (solo lectura / no destructivas)

- UFW activo 22/80/443; `apache2ctl configtest` = Syntax OK; Apache active.
- 16 rutas sensibles → 403/404; `assets/css/style.css` → 200; `/` → 302.
- Backups presentes (2 archivos + 2 .err) con permisos 600.
- `error.log` (500 líneas): 0 `Fatal error`/`Parse error`/`PDOException`/
  `SQLSTATE`/HTTP 500.
- BD: 17 tablas, 21 FK, conectividad OK (solo lectura).

### Archivos actualizados en esta fase

`README.md`, `MATRIZ_CUMPLIMIENTO_AWS.md`, `docs/DIAGRAMA_ARQUITECTURA.md`,
`AUDITORIA_PROGRESO.md`, `CHANGELOG.md` (v1.3.0). Sin cambios de código ni BD.

---

## FASE 13 — PRUEBAS FINALES (2026-08-12) — REGISTRO

### Pruebas ejecutadas (no destructivas)

| # | Prueba | Resultado |
|---|---|---|
| 1 | HTTP `/` | 302 → login ✅ |
| 2 | HTTP `login` (GET) | 200 (página login con CSRF) ✅ |
| 3 | Login POST `admin` + sesión | 302 (cookie establecida) ✅ |
| 4 | `dashboard_datos_ajax` autenticado | 200 JSON `{"success":true,...}` → **RDS end-to-end OK** ✅ |
| 5 | Assets CSS | `assets/css/style.css` 200 (19 KB) ✅ |
| 6 | Assets JS | `assets/js/pos.js`, `main.js` 200 ✅ |
| 7 | PDO RDS (script servidor) | Conexión OK, 17 tablas ✅ (con envvars cargadas como root) |
| 8 | Rutas internas (`/database/`, `/helpers/`, `/conexion/`, `/docs/`) | 403 ✅ |
| 9 | Archivos sensibles (`/schema.sql`, `*.sql`, `*.md`, `/.env`, `/.git/config`) | 403/404 ✅ |
| 10 | `/assets/` (listado) | 403 (sin Indexes) ✅ |
| 11 | `php -l` | 0 errores (local; ver nota) |
| 12 | `error.log` | Sin `Fatal error`, `SQLSTATE`, `PDOException`, `500` (revisión post-cambios) ✅ |
| 13 | Integridad BD | 17 tablas, sin cambios sobre datos reales (solo lectura) ✅ |

### Nota php -l
`php -l` ejecutado sobre los archivos PHP del proyecto local (mismo código
desplegado). En servidor, los `.php` se validaron por compilación real en
cada prueba HTTP (sin errores de sintaxis).

---

## FASE 12 — DOCUMENTACIÓN Y ENTREGABLES (2026-08-12) — REGISTRO

### Entregables creados/actualizados

| Archivo | Contenido |
|---|---|
| `README.md` | Proyecto, tecnologías, arquitectura 3 capas, funcionalidades, seguridad, instalación local y AWS, script SQL, estado |
| `.gitignore` | Ampliado: credenciales, backups, logs, evidencias f11_*, claves, temporales, IDE |
| `MATRIZ_CUMPLIMIENTO_AWS.md` | Rúbrica técnica AWS (15 requisitos, ✅/⚠️, evidencia) |
| `CRUD_EVIDENCIA.md` | Tabla requisito → funcionalidad → archivo → evidencia (11 entidades) |
| `docs/DIAGRAMA_ARQUITECTURA.md` | Diagrama real (ASCII + datos VPC/EC2/RDS/SG/IGW + instrucciones Draw.io) |
| `docs/HTTPS.md` | Requisitos y pasos para habilitar HTTPS con dominio real |
| `AUDITORIA_PROGRESO.md` | Fases 11.2, 12 y 13 (esta sección) |
| `CHANGELOG.md` | v1.1.0 (hardening) y v1.2.0 (documentación) |

### Repositorio Git

- Estructura preparada: `.gitignore` + `README.md` + documentación.
- **Pendiente:** conectar repositorio remoto (GitHub/GitLab). No se inventa URL.

---

## FASE 11.2 — HARDENING AWS (2026-08-12) — REGISTRO

### Objetivo

Cerrar los hallazgos de la auditoría 11.1 (acceso web a archivos internos,
listado de directorios, permisos de secretos, firewall) **sin tocar datos
reales ni romper la aplicación**, y documentar los pendientes que requieren
consola AWS.

### Hallazgos verificados al inicio

| # | Hallazgo | Estado verificado |
|---|---|---|
| 1 | RDS *Publicly Accessible* | 3306 **filtrado desde Internet** (sondeo externo sin respuesta); endpoint con IP pública asignada; EC2 conecta OK |
| 2 | SG EC2 SSH 22 | **ABIERTO a Internet** (`0.0.0.0/0` probable) — requiere consola |
| 3 | Directory listing | `/database/`, `/database/migrations/`, `/helpers/`, `/assets/` listaban; `/schema.sql`, migraciones y `.md` descargables |
| 4 | Permisos | `/etc/apache2/envvars` 644 root:root; backups 644 |
| 5 | HTTPS | 443 cerrado; sin dominio |
| 6 | Instancia sin rol IAM | IMDS devuelve 404 → sin API de AWS desde la instancia (cambios SG/RDS = manuales en consola) |

### Cambios realizados

| # | Cambio | Comando | Verificación |
|---|---|---|---|
| H1 | Desactivar `Indexes` (docroot) | `sed '171s/Options Indexes FollowSymLinks/Options FollowSymLinks/' apache2.conf` | `/assets/` → 403 (sin listado) |
| H2 | Conf de hardening Apache | `/etc/apache2/conf-available/hardening.conf` + `a2enconf` (DirectoryMatch `database\|helpers\|conexion\|docs\|tests\|scripts\|backups` y `.git`; FilesMatch `.sql\|.md\|.log\|.bak\|.ini\|.sh\|.pem\|.key\|.env\|.tmp`; dotfiles) | Todas las rutas internas → 403; CSS/JS → 200 |
| H3 | Firewall UFW | `ufw allow 22,80,443/tcp` + `default deny incoming` + `--force enable` (con temporizador de seguridad reversible) | Activo; SSH/HTTP/RDS OK tras activación |
| H4 | Permisos secretos | `chmod 640 /etc/apache2/envvars`; `chmod 600 /var/backups/f11/*` | Apache reinicia y app funciona (RDS end-to-end 200) |
| H5 | Template HTTPS (deshabilitado) | `sites-available/sublima-ssl.conf` (sin `a2ensite`) | No afecta al servicio |

### Pendientes que requieren consola AWS / dominio (acción manual del propietario)

1. **SG EC2 (`sg-06e7816411cf1414e`)**: restringir SSH 22 a la IP
   administrativa actual (no cerrar sin acceso alternativo).
2. **RDS**: confirmar *Publicly Accessible = No* y SG RDS con 3306 solo
   desde `sg-06e7816411cf1414e`.
3. **HTTPS**: dominio propio + DNS A → `3.145.23.40` + abrir 443 + Certbot
   (template Apache listo).
4. **Repositorio remoto**: conectar GitHub/GitLab cuando exista.

---

## FASE 11 — DESPLIEGUE A AWS (EC2 + RDS) (2026-08-11) — REGISTRO

### Objetivo

Publicar la versión estable de las Fases 1-10 en producción (EC2 `3.145.23.40`,
Apache 2.4.66 + PHP 8.5.4 mod_php, MySQL 8.4.9 en RDS `sublimation_db`) sin
perder datos ni romper lo existente.

### Despliegue

| Paso | Detalle | Estado |
|---|---|---|
| Inspección | EC2 Ubuntu 26.04, Apache 2.4.66, PHP 8.5.4, disco 6.7G (55%), RAM 908Mi; HTTPS NO activo (443 sin listener, sin dominio); escaneos de bots en error.log (34.168.251.237: `/etc/passwd`, `.env`, `phpinfo.php`) documentados | HECHO |
| Backups | BD `sublimation_db_20260811_203744.sql.gz` (md5 `c191df1f32eadf6c8bd941716e7f97df`, 9 CREATE TABLE) + código `www_html_20260811_203744.tar.gz` (72 archivos, md5 `4883a399e02898bf86bab07844a85723`) en `/var/backups/f11/`; descargados a local y md5 verificados | HECHO |
| Migración | 8 tablas nuevas (`compras`, `detalle_compras`, `devoluciones`, `detalle_devoluciones`, `intentos_login`, `movimientos_caja`, `movimientos_inventario`, `proveedores`) + 5 columnas en `cajas_sesiones` (`monto_ventas_efectivo`, `monto_ingresos`, `monto_retiros`, `monto_devoluciones`, `efectivo_esperado`); 17 tablas total; datos legacy preservados (`cierres_caja` intacta) | HECHO |
| Swap | `/var/www/html` ← código Fases 1-10 (42 archivos, staging `/var/www/html.f11_new`); versión previa en `/var/www/html.f11_old` (rollback instantáneo); permisos www-data:www-data 755/644; `php -l` OK; sin credenciales en código (grep de usuario/contraseña = 0; `DB_PASS` solo en `/etc/apache2/envvars`; `config.local.php` nunca se sube) | HECHO |
| Verificación post-swap | HTTP 302→login, login 200, CSS/JS 200, PDO RDS OK | HECHO |

### Hallazgos corregidos durante la batería de pruebas

1. **`php8.5-mbstring` no estaba instalado** → `Fatal error: Call to undefined
   function mb_strlen()` rompía TODOS los POSTs de formularios (proveedores,
   usuarios, clientes, compras, devoluciones, caja). Verificado en
   `/var/log/apache2/error.log`. **Fix:** `apt-get install -y php8.5-mbstring`
   + `systemctl restart apache2` (requisito de runtime, no de código).
2. **Apache reescribe códigos HTTP no estándar a 500**: prueba empírica en la
   instancia: `http_response_code(419|499|418|425)` → `HTTP/1.1 500`; 429/409
   pasan. El rechazo CSRF con 419 se reportaba como 500 (engañoso aunque
   bloqueante). **Fix (Fase 11):** `http_response_code(403)` estándar en
   `helpers/security.php` (`require_csrf`) e `index.php` (`cobrar_ajax`);
   desplegado en local y producción.
3. **Password del usuario `cajero` en producción no coincide con el seed
   `cajero123`** (verificado con `password_verify`; `admin/admin123` SÍ
   coincide). No se tocó la cuenta real; las pruebas de rol Cajero se hicieron
   con un usuario temporal creado por la propia app (`crear_usuario_ajax`) y
   eliminado al final.

### Batería de pruebas (HTTP contra AWS)

**44/44 PRUEBAS APROBADAS** (evidencia `f11_tests.txt`):

- Autenticación: raíz 302→login; login con token CSRF; admin/admin123 OK;
  password incorrecta rechazada; logout GET→405; logout POST+CSRF invalida
  sesión; seed cajero/cajero123 no aplica en prod (hallazgo).
- Roles: cajero bloqueado 403 en 5 módulos admin (compras, proveedores,
  reportes, auditoría, usuarios).
- POS completo (cajero temporal): apertura L.300, rechazo de segunda apertura,
  venta efectivo 2xP2 (L.90), venta tarjeta 1xP4 (L.120), saldos verificados
  (390 → 410 con ingreso/retiro → 365 con devolución), cierre con arqueo exacto
  y rechazo de cierre duplicado.
- IDOR: cajero NO ve/devuelve venta ajena (403); admin SÍ (200).
- CSRF: `cobrar_ajax` y `caja_movimiento_ajax` sin token → 403 con mensaje.
- SQLi: comillas y UNION en búsquedas → JSON válido (prepared statements).
- Compras (admin): crear proveedor, compra BORRADOR, confirmación (stock +5,
  kardex ENTRADA), reconfirmación rechazada (idempotencia).
- Reportes: ventas (3 filas), caja (incluye turno legacy), kardex con
  4 movimientos (2 entradas + 2 salidas), devoluciones, productos.
- Dashboard admin/cajero OK; 10 páginas admin HTTP 200.

### Restauración post-pruebas (VERIFICADO)

`f11_cleanup.php` (RDS, borra SOLO lo creado por la batería vía
`f11_ids.json` + snapshot) y `f11_snapshot.php`:

- Conteos idénticos al snapshot pre-pruebas: ventas=1, detalle_ventas=2,
  cajas_sesiones=3, movimientos_caja=0, movimientos_inventario=0,
  auditoria_logs=68, intentos_login=1, proveedores=0, compras=0,
  devoluciones=0, usuarios=5, clientes=3, productos=5.
- Stocks restaurados: 29 / 149 / 4 / 15 / 0 (productos 1-5).
- Usuario temporal creado y eliminado; cuentas reales intactas.

### Pendientes documentados

- HTTPS: NO activo (sin dominio conocido). Requiere decisión del cliente
  (dominio + certificado, o TLS por IP). No se configuró ni se inventó dominio.
- Security Groups AWS: revisar en consola que RDS (3306) solo acepte tráfico
  del SG de la EC2.
- Restablecer la contraseña del usuario `cajero` si el cliente no la conoce
  (el seed no aplica).
- Considerar bloqueo de bots/escaneos (34.168.251.237 y similares).

---

## FASE 10 — MEJORA UI/UX Y CONSISTENCIA VISUAL (2026-08-11) — REGISTRO

### Cambios realizados

| # | Cambio | Archivo / Recurso | Estado |
|---|---|---|---|
| F10-1 | Capa visual "Fase 10" al final de `style.css`: encabezados de modal oscuros premium (`.modal-header-premium` degradado navy + `.modal-header-danger` rojo oscuro) para que títulos `text-white` y `btn-close-white` sean legibles; reglas de contraste para el tema claro (`.card .bg-dark .text-white`, `.table-custom .text-white`); navegación con `.nav-section-label` (Operación/Administración), `.nav-link.active`, `.user-chip`, `.rol-badge`; login premium (`.login-wrapper`, `.login-card`, `.login-logo`); POS (`.cart-total-section` oscuro con TOTAL grande, `.pos-producto`, `.pos-productos-scroll`, `.pos-caja-badge`); `.stat-box`; `.dif-efectivo-preview`; badges de estado (`badge-borrador/confirmada/anulada`) y Kardex (`kardex-badge-in/out`); `.empty-state`; `.skip-link`; `:focus-visible`; toasts tipados; media queries responsive (1199/991/767/575) y print | `assets/css/style.css` | VERIFICADO |
| F10-2 | Navegación agrupada y accesible: grupos "Operación" (POS, Inventario, Clientes, Devoluciones) y "Administración" (Dashboard, Reportes, Compras, Proveedores, Auditoría, Usuarios, solo admin); cajero ve "Mi panel" (Dashboard propio); etiquetas corregidas (dos enlaces llamados "Reportes" → "Dashboard"/"Reportes"); `aria-current="page"` en el enlace activo; skip-link "Saltar al contenido principal" + `<main id="app-main">` | `views/includes/header.php` | VERIFICADO |
| F10-3 | Modal global de confirmación `#modalConfirmacionGlobal` (header premium, body dinámico, botón OK) y cierre de `<main>` | `views/includes/footer.php` | VERIFICADO |
| F10-4 | `showConfirm(mensaje, onConfirm, opts)` en `main.js`: confirmaciones visuales con botón custom (danger/success), respaldo a `window.confirm` si el modal no existe | `assets/js/main.js` | VERIFICADO |
| F10-5 | Dashboard: tablas `table table-dark` → `table table-custom` (tema claro unificado), color de ejes de gráficas `#adb5bd` → `#59524c`, color por defecto de KPIs `text-white` → `text-primary`, chips de "Mi Turno de Caja" con `.stat-box` | `views/dashboard.php` | VERIFICADO |
| F10-6 | POS: símbolo de moneda `L.` (Lempira), grid de productos `.pos-productos-scroll` con tarjetas `.pos-producto` accesibles por teclado (role=button/tabindex/Enter), badge de saldo en vivo `.pos-caja-badge`, modal de cierre con header `.modal-header-danger`, arqueo con preview en vivo y badge EXACTO/SOBRANTE/FALTANTE | `views/pos.php` | VERIFICADO |
| F10-7 | Inventario: buscador client-side `#inv-filtro` (form GET `q`) con filas `.inv-fila`, función `filtrarInventario()` y aviso `#inv-filtro-aviso` (visible para ambos roles), badges de Kardex de entrada/salida, modal de baja con header `.modal-header-danger` | `views/inventario.php` | VERIFICADO |
| F10-8 | Compras: confirmación nativa `confirm()` reemplazada por el modal global `showConfirm` con preview de Kardex `ENTRADA_COMPRA`; badges de estado BORRADOR/CONFIRMADA/ANULADA; alertas nativas → toasts; totales sin `text-white` ilegible | `views/compras.php` | VERIFICADO |
| F10-9 | Proveedores: nueva columna "Estado" con badges Activo/Inactivo; el listado usa `obtenerProveedores(false)` para mostrar también inactivos (filas atenuadas); eliminado `text-white` residual del modal de baja | `views/proveedores.php` | VERIFICADO |
| F10-10 | Contraste corregido en el resto de vistas: devoluciones (totales de la venta en `text-secondary`, alerts → toasts), clientes (ficha y modal de artículos), auditoría (rol/módulo sin `text-white`), reportes (tablas claras + color por defecto `text-primary`), POS (nombre de producto en tarjetas) | `views/devoluciones.php`, `views/clientes.php`, `views/auditoria.php`, `views/reportes.php`, `views/pos.php` | VERIFICADO |

### Reglas de negocio y decisiones de diseño

- **Sin cambios de lógica**: ninguna ruta, parámetro, contrato JSON, regla de permisos ni estructura de BD fue modificada; solo presentación.
- **Consistencia visual**: un solo tema claro cálido (tokens CSS existentes `--bg-system`/`--bg-card`/`--bg-header`); las tablas oscuras de dashboard/reportes se unificaron a `table-custom`.
- **Contraste garantizado**: los encabezados de modal son ahora oscuros (navy/rojo) para que los títulos blancos y botones de cierre sean legibles; donde el tema claro convierte `.bg-dark` en claro, el texto blanco pasa a `--text-primary` por CSS.
- **Accesibilidad**: skip-link, `aria-current="page"`, foco visible (`:focus-visible`), tarjetas de producto operables por teclado y etiquetas ARIA.
- **Confirmaciones visuales**: acciones destructivas o con efectos (confirmar compra) usan el modal global con CSRF y mensaje descriptivo; ya no se usa `confirm()` del navegador para operaciones del módulo.
- **Responsive**: grid de productos 2 columnas (móvil) → 4 (desktop), nav colapsable, contenedores fluidos.

### Pruebas realizadas (Fase 10)

Batería HTTP contra `php -S 127.0.0.1:8090` (cliente cURL PHP `f10_cliente.php`):
**63/63 PRUEBAS EXITOSAS** (evidencia `f10_run_final.txt`):

- Acceso y roles: login anónimo (vista premium), login admin/cajero, 10 vistas admin HTTP 200, 5 vistas cajero HTTP 200, 5 rutas admin para cajero → 403.
- AJAX: `dashboard_datos_ajax`, `buscar_productos_ajax`, `movimientos_kardex_ajax` responden JSON válido; POST sin CSRF → 419.
- Capa visual: CSS servido con las 11 marcas nuevas (`modal-header-premium/danger`, `stat-box`, `cart-total-section`, `pos-producto`, `skip-link`, badges, `nav-section-label`, media queries, `focus-visible`); `main.js` con `showConfirm`.
- Nav: secciones Operación/Administración (admin), "Mi panel" sin Administración (cajero), `aria-current`, skip-link + `#app-main`, label "Dashboard" sin "Reportes F8".
- UI con datos reales (apertura de caja admin y cajero, proveedor activo/inactivo, compras BORRADOR/CONFIRMADA/ANULADA): grid POS + badge de saldo `L. 100.00`, botones con `L.`, chips `stat-box` del cajero, tablas claras sin `table-dark`, badges de compras y proveedores, sin `confirm()` de compra, filtro de inventario + aviso, badges de Kardex, sin `text-white` residual en auditoría.
- Limpieza final: BD en estado seed (ventas/cajas/compras/devoluciones/proveedores/movimientos_caja = 0; 4 `INVENTARIO_INICIAL`; stocks 30/150/4/15/0; p5 `Agotado`).

### Errores corregidos

| ID | Hallazgo | Corrección |
|---|---|---|
| E10-1 | (Bug real de contraste) Títulos y botones de cierre de TODOS los modales eran `text-white`/`btn-close-white` sobre cabeceras claras `--bg-header` → ilegibles | Cabeceras de modal oscuras (navy premium y rojo para destructivas) en `style.css` |
| E10-2 | (Bug real de contraste) `.card .bg-dark` quedaba claro por el tema (beige) pero el texto `text-white` interior seguía blanco → ilegible | Regla de contraste: texto oscuro sobre fondos convertidos a claro |
| E10-3 | (Bug real de contraste) KPIs del dashboard y tarjetas resumen de reportes usaban `text-white` por defecto sobre tarjetas blancas | Default `text-primary` en `card()`/KPI (`dashboard.php`, `reportes.php`) |
| E10-4 | (Bug real de contraste) Ejes/leyendas de Chart.js `#adb5bd` casi invisibles sobre blanco | Color `#59524c` (texto secundario del sistema) |
| E10-5 | (Bug real de UX) Dos enlaces del nav llamados "Reportes" (dashboard etiquetado como Reportes + "Reportes F8") | Etiquetas corregidas: "Dashboard" y "Reportes"; nav agrupado por rol |
| E10-6 | (Bug real de UX) POS usaba `$` en lugar de `L.` (Lempira) | Icono/etiqueta `L.` en totales, carrito y badges |
| E10-7 | (Bug real de UX) Proveedores no mostraban el estado Activo/Inactivo | Columna Estado con badges; listado con inactivos (atenuados) |
| E10-8 | (Bug menor) `text-white` residual en clientes (ficha), auditoría (módulo/rol), devoluciones (info venta), inventario (baja) y tarjetas de producto del POS | Eliminado/ajustado a `text-primary`/`text-secondary` |
| E10-9 | (Batería, no bug) Las marcas visuales dependían de datos (caja abierta, compras/proveedores existentes); con BD seed el POS muestra "Apertura de caja" y las tablas vacías | El cliente de pruebas abre caja y crea datos de prueba antes de aseverar, y resetea al final |
| E10-10 | (Batería, no bug) `dashboard_datos_ajax` sin caja abierta devuelve arreglo sin `stat-box` (panel "Mi Turno" es por rol cajero) | Aserción de `stat-box` contra el dashboard del cajero con su turno abierto |

### Notas

- MIGRACIONES: ninguna (solo presentación; `style.css`, `main.js`, vistas y cabeceras).
- `php -l` sin errores en todos los PHP del proyecto (raíz + `views/`).
- Estados de compra ANULADA: no hay acción de anulación en la UI (pendiente de fase futura); el badge se verificó con registro directo.
- Evidencia: `f10_run_final.txt` (63/63).

---

## FASE 9 — DASHBOARD ADMINISTRATIVO CON GRÁFICAS (2026-08-11) — REGISTRO

### Cambios realizados

| # | Cambio | Archivo / Recurso | Estado |
|---|---|---|---|
| F9-1 | Dashboard renovado: panel administrativo (KPIs de hoy, ventas por día, métodos de pago, top productos, stock bajo/agotados, actividad reciente, alertas) con presets de período (Hoy/Ayer/Últimos 7 días/Mes/Personalizado) y gráficas Chart.js desde CDN con respaldo a tablas sin librería | `views/dashboard.php` | VERIFICADO |
| F9-2 | Panel "Mi actividad" para el rol Cajero (sus ventas y su turno de caja) y endpoint `dashboard_datos_ajax` con indicadores exactos calculados contra la BD | `views/dashboard.php`, `index.php` | VERIFICADO |
| F9-3 | Ajuste de contraste: el estado de la caja del usuario usa `saldo_efectivo` (contrato real de `obtenerEstadoCaja`) | `views/dashboard.php` | VERIFICADO |

### Pruebas realizadas (Fase 9)

Batería HTTP (cliente cURL PHP `f9_cliente.php`): **61/61 PRUEBAS EXITOSAS** (evidencia `f9_run_final.txt`): acceso por rol (admin 200 / cajero panel propio / anónimo sin datos), indicadores exactos contra BD, series de ventas por día y método, top productos, inventario agotado/bajo stock, caja, actividad, presets de período y fechas personalizadas, validaciones (400), SQLi, IDOR por parámetro, permiso de anulación, consistencia con MySQL y regresión mínima de Fases 1-8; BD final en estado seed.

### Errores corregidos

| ID | Hallazgo | Corrección |
|---|---|---|
| E9-1 | (Batería) Errores de sintaxis `??` y variable `$slc` sin uso en el panel del cajero | Corregido/simplificado (suite 61/61 verde) |
| E9-2 | (Batería) La vista del estado de caja refería la clave `saldo` en lugar de `saldo_efectivo` | Uso del contrato real de `obtenerEstadoCaja` |

### Notas

- MIGRACIONES: ninguna. `php -l` sin errores.

---

## FASE 8 — REPORTES Y CONSULTAS OPERATIVAS (2026-08-11) — REGISTRO

### Cambios realizados

| # | Cambio | Archivo / Recurso | Estado |
|---|---|---|---|
| F8-1 | `ReporteController` (NUEVO, SOLO LECTURA): 7 reportes — ventas, productos vendidos, inventario/Kardex, compras, devoluciones, caja (arqueos persistidos) y resumen administrativo (dashboard); validación central (fechas `Y-m-d` con `checkdate` + rango, IDs enteros, listas blancas para método/estado/tipo de movimiento) y consultas 100% prepared statements (SQLi imposible); totales calculados SOLO sobre registros válidos (Completadas / CONFIRMADAS / Cerradas) | `controllers/ReporteController.php` (NUEVO) | VERIFICADO |
| F8-2 | Rutas GET autenticadas `reporte_*_ajax` (7) con `AuthController::requireRole('Administrador')`: el cajero → 403, el anónimo → página de login (nunca datos); errores de validación → HTTP 400 con mensaje claro | `index.php` | VERIFICADO |
| F8-3 | Vista `views/reportes.php` (NUEVA): pestañas por reporte (Ventas, Productos, Inventario, Compras, Devoluciones, Caja, Resumen Integrado), filtros (fechas, vendedor, cliente, método, estado, producto, proveedor, documento, tipo de movimiento), resumen de totales por tab, tabla consolidada del período y descarga CSV | `views/reportes.php` (NUEVO) | VERIFICADO |
| F8-4 | Navegación "Reportes" (solo Administrador) | `views/includes/header.php` | VERIFICADO |

### Reglas de negocio y decisiones de diseño

- Los reportes son SOLO LECTURA: no tocan tablas transaccionales; reutilizan los movimientos de Kardex, `movimientos_caja` y el ARQUEO PERSISTIDO del cierre (nunca se recalcula el dinero).
- Fechas: ambas o ninguna (400 si falta una); formato estricto `YYYY-MM-DD` verificado con `checkdate` (400 si inválida); rango invertido → 400; el filtro del día incluye 00:00:00–23:59:59 (América/Tegucigalpa).
- Ventas: las filas INCLUYEN anuladas (trazabilidad); los totales monetarios SOLO de Completadas; anuladas cuentan como contador y sus totales van en 0.00.
- Productos: las devoluciones se vinculan por venta+producto (subconsulta agregada) para no duplicar cantidades; ventas anuladas excluidas.
- Kardex: todos los tipos de movimiento; la trazabilidad incluye movimientos de ventas anuladas (referencia `VENTA`).
- Compras: totales monetarios SOLO de CONFIRMADAS; los borradores suman como contador aparte.
- Caja: filas con el arqueo persistido del cierre; el resumen numérico solo de turnos Cerrados.
- Dashboard: sin fechas → período de hoy por defecto; el inventario global muestra la referencia actual (no el período).

### Pruebas realizadas (Fase 8)

Batería HTTP completa contra `php -S 127.0.0.1:8090` (cliente cURL PHP
`f8_cliente.php`): **58/58 PRUEBAS EXITOSAS** (evidencia `f8_run_final.txt`):

- Acceso: ADMIN ve Reportes (200); CAJERO vista y endpoints → 403; anónimo → página de
  login (sin datos).
- Ventas: 4 filas + totales solo Completadas (total 345.00, efectivo 225.00, tarjeta
  120.00, anuladas 1); filtros por fecha (todo el día), vendedor (cajero → solo su V4),
  método (Tarjeta → solo V2), estado (Anulada → totales 0.00), cliente.
- Validaciones: rango sin resultados (0 filas), fechas inválidas (`abc/def`,
  `2026-13-45`) → 400, rango invertido → 400, método/estado/tipo de movimiento
  inexistentes → 400, SQLi en documento (literal, tabla intacta) y en fechas (400).
- Productos: p1 vendida 3 / devuelta 1 / neta 2 / generado 150.00 (devolución NO
  duplicada); p4 neta 1 / 120.00; filtros por producto y vendedor.
- Kardex: total de movimientos = trazabilidad completa; SALIDA_VENTA 4 (incluye V3
  anulada), ENTRADA_COMPRA 1 (stock_anterior/nuevo 150→155), DEVOLUCION_VENTA 1
  (ref DEVOLUCION), filtro producto=2 (INICIAL + COMPRA); tipo inexistente → 400.
- Compras: 2 filas + totales solo CONFIRMADA (145.00, borradores 1); filtros estado/
  proveedor/documento (coincidencia parcial).
- Devoluciones: 1 devolución / 1 unidad / 75.00; filtros producto y venta.
- Caja: 2 turnos Cerrados con arqueos PERSISTIDOS exactos (apertura 500, ventas_ef
  150, ingresos 100, retiros 50, devoluciones 75, esperado 625, contado 650, dif +25;
  cajero esperado 175 / contado 165 / dif −10); resumen exacto (ventas_ef 225.00,
  ingresos 100.00, retiros 50.00, devoluciones 75.00, dif +25.00/−10.00); filtros
  estado/usuario.
- Dashboard: indicadores exactos (ventas 3/345.00, compras 1/145.00, devoluciones
  1/75.00, ingresos 100.00, retiros 50.00, dif 25.00/−10.00, top productos con
  producto_id, movimientos inventario, unidades totales 201.00); sin fechas usa el
  período de hoy.
- Consistencia reporte↔BD: V1 en ventas+Kardex+1 `INGRESO_VENTA`; DEV1 en
  devoluciones+Kardex+1 `EGRESO_DEVOLUCION`; C1 en compras+Kardex; stocks finales
  exactos (28/155/4/14/0).
- Regresión mínima Fases 1-7: POS 200, cajero→admin 403, devolución sin CSRF 419,
  caja_estado_ajax sin turno responde correcto.
- Limpieza final: ventas=0, cajas=0, compras=0, devoluciones=0, mov_caja=0, solo 4
  `INVENTARIO_INICIAL`, stocks seed 30/150/4/15/0 y p5 `Agotado` (verificado además
  por consulta directa a MySQL).

### Errores corregidos

| ID | Hallazgo | Corrección |
|---|---|---|
| E8-1 | (Bug real) `reporteProductos` unía `detalle_devoluciones` SIN vínculo con la venta → cantidades devueltas duplicadas (2 en lugar de 1) y montos inflados | JOIN a subconsulta agregada por venta+producto: devuelta 1, neta 2, generado 150.00 exactos |
| E8-2 | (Bug real) `validarRangoFechas` trataba fechas inválidas (`abc/def`, `2026-13-45`) como "sin filtro" → HTTP 200 | Distingue "no enviada" de "inválida"; formato incorrecto → HTTP 400 |
| E8-3 | (Bug real) `resumenDashboard`: SQL refería la columna `cantidad` inexistente en `devoluciones` → error fatal en el endpoint | JOIN a `detalle_devoluciones` (COUNT DISTINCT + SUM de unidades/monto) |
| E8-4 | (Bug menor) Top productos del dashboard no exponía `producto_id` | Añadido `p.id AS producto_id` a la consulta |
| E8-5 | (Batería, no bug) El anónimo recibe la página de login sin datos; la aserción esperaba el literal 'route=login' que no existe en ese HTML | Aserción ajustada: verifica "Acceso al Sistema" + ausencia de JSON con datos |
| E8-6 | (Batería, no bug) R23/R26 comparaban contra nombres de usuario fijos ('Administrador'/'Cajero') que no son los reales | Aserciones contra los nombres reales de BD ('Administrador POS' / 'Cajero Turno A') |

### Notas

- Sin paginación en los listados (LIMIT alto fijo) — pendiente de la fase de UI/UX.
- Tablas propias sin librerías externas; filtros vía GET y descarga CSV.
- El resumen del dashboard usa información ya cubierta por los 6 reportes (única
  fuente de verdad: las tablas transaccionales).

---

## FASE 7 — CAJA AMPLIADA (2026-08-11) — REGISTRO

### Cambios realizados

| # | Cambio | Archivo / Recurso | Estado |
|---|---|---|---|
| F7-1 | Migración idempotente `004_caja_ampliada.sql`: `ALTER TABLE cajas_sesiones ADD COLUMN IF NOT EXISTS` (MaríaDB 10.4): `monto_ventas_efectivo`, `monto_ingresos`, `monto_retiros`, `monto_devoluciones`, `efectivo_esperado` — persisten las partidas del arqueo ampliado requerido | `database/migrations/004_caja_ampliada.sql` (NUEVO, APLICADA) | VERIFICADO |
| F7-2 | `CajaController` ampliado: `abrirCaja()` ahora inserta el movimiento `APERTURA` en la MISMA transacción; nuevo `registrarIngresoRetiro()` (INGRESO/RETIRO transaccional con `SELECT ... FOR UPDATE` sobre la sesión, validación estado Abierta, IDOR, retiro ≤ saldo, motivo obligatorio); nuevo `obtenerEstadoCaja()` — función ÚNICA de cálculo del saldo (apertura + ventas en efectivo + ingresos − retiros − devoluciones) usada por vista, retiros y cierre; `cerrarCaja()` reescrito (bloquea sesión FOR UPDATE, calcula todas las partidas, persiste arqueo ampliado, inserta movimiento `CIERRE` y marca `Cerrada` en UNA transacción); nuevo `listarMovimientosCaja()` | `controllers/CajaController.php` | VERIFICADO |
| F7-3 | `VentaController::procesarVenta()`: inserta UN movimiento `INGRESO_VENTA` (ref VENTA/venta_id) en la misma transacción SOLO cuando el método es Efectivo (Tarjeta/Transferencia no incrementan la gaveta → no generan movimiento; garantiza "una sola vez" y no duplica el cálculo del cierre, que lee `ventas`) | `controllers/VentaController.php` | VERIFICADO |
| F7-4 | Rutas AJAX: `caja_estado_ajax` (GET, sesión activa del usuario → estado + historial) y `caja_movimiento_ajax` (POST JSON + `require_csrf` → 419; nunca recibe `caja_sesion_id` del cliente → opera SIEMPRE el turno activo del usuario autenticado) | `index.php` | VERIFICADO |
| F7-5 | POS ampliado sin rediseño: badge "Saldo" en vivo en el encabezado del carrito, botón/Modal "Movimientos" (ingreso/retiro con motivo obligatorio), Modal "Arqueo & Cierre" con resumen de partidas en vivo (apertura, ventas efectivo, ingresos, retiros, devoluciones, esperado), preview de la diferencia mientras se digita el contado y tablas con el historial de movimientos del turno; JS de refresco por `caja_estado_ajax` | `views/pos.php` | VERIFICADO |

### Reglas de negocio implementadas

- Movimientos soportados: `APERTURA`, `INGRESO_VENTA` (venta en efectivo), `INGRESO`,
  `RETIRO`, `EGRESO_DEVOLUCION` (Fase 6) y `CIERRE` (trazabilidad, monto 0).
- Saldo/efectivo disponible SIEMPRE con la misma fórmula (una sola fuente de verdad):
  `apertura + ventas en efectivo + ingresos − retiros − devoluciones`. Los movimientos
  APERTURA / INGRESO_VENTA / CIERRE NO se suman (evita doble conteo con `ventas`).
- Una venta en efectivo genera EXACTAMENTE un `INGRESO_VENTA` (misma transacción);
  ventas con Tarjeta/Transferencia no generan movimiento de caja.
- Un usuario NO puede tener dos cajas abiertas simultáneamente (regla 5).
- Una caja `Cerrada` no puede recibir nuevos movimientos: toda operación exige turno
  activo del usuario + `estado='Abierta'` bajo bloqueo FOR UPDATE (regla 6).
- Un retiro no puede superar el efectivo disponible (regla 7) — rechazado con monto
  exacto del saldo en el mensaje; ROLLBACK total (sin movimiento residual).
- Ingresos y retiros EXIGEN motivo/observación (mínimo 3 caracteres) (regla 8).
- El cierre calcula y PERSISTE: monto inicial, ventas en efectivo, ingresos, retiros,
  devoluciones en efectivo, efectivo esperado, contados y diferencia total
  (regla 9); el registro queda en `movimientos_caja` (CIERRE) y en `cajas_sesiones`.
- El cierre NO puede repetirse: solo sesiones `Abierta` son cerrables, bajo
  `SELECT ... FOR UPDATE`, todo en UNA transacción (regla 10).
- Trazabilidad: usuario, fecha, caja y referencia en CADA movimiento (regla 11).
- Permisos ADMIN/CAJERO: endpoint y métodos SIEMPRE operan el turno del usuario
  autenticado; IDOR en cerrarCaja (cajero solo su turno); el cajero jamás ve ni
  opera la caja de otro (regla 12).
- CSRF obligatorio en `caja_movimiento_ajax` (regla 13); cierre/apertura con
  `verify_csrf_token()` clásico.
- Ventas y devoluciones históricas intactas (no se eliminan ni modifican; reglas 14/15)
  — verificado por regresión.

### Pruebas realizadas (Fase 7)

Batería HTTP completa contra `php -S 127.0.0.1:8090` (cliente cURL PHP
`f7_cliente.php`): **48/48 PRUEBAS EXITOSAS** (evidencia `f7_run2.txt`):

- Apertura correcta admin (L. 500) + movimiento `APERTURA|500.00` en la misma transacción.
- Segunda apertura del mismo usuario RECHAZADA (regla 5).
- Venta E1 efectivo (prod1 ×2 = L. 150): EXACTAMENTE un `INGRESO_VENTA|150.00|VENTA|E1`;
  Kardex `SALIDA_VENTA|-2|30|28`. Venta E2 con Tarjeta (L. 120): 0 movimientos de caja.
- Ingreso L. 100 correcto (saldo 650→750) con motivo en observaciones.
- Retiro L. 50 correcto (saldo 750→700).
- Retiro L. 99,999 > disponible L. 700 RECHAZADO con ROLLBACK (sin movimiento residual).
- Monto 0 / negativo / motivo corto / motivo vacío / tipo inválido: TODOS rechazados
  (regla 8), sin rastro en BD.
- Devolución de E1 (L. 75): EXACTAMENTE un `EGRESO_DEVOLUCION`; Kardex
  `DEVOLUCION_VENTA|1|28|29`.
- Estado en vivo `caja_estado_ajax`: apertura=500, ventas_ef=150, ingresos=100,
  retiros=50, devoluciones=75, saldo=625.00 — cálculo EXACTO.
- CSRF: sin token e inválido → HTTP 419 sin efecto en BD.
- CIERRE admin: contado 650 / esperado 625 → diferencia POSITIVA +25.00; arqueo
  ampliado PERSISTIDO exacto en `cajas_sesiones` (ventas 270, ventas_ef 150,
  ingresos 100, retiros 50, devoluciones 75, esperado 625, diferencia 25);
  movimiento `CIERRE` registrado; historial completo del turno
  (APERTURA, INGRESO_VENTA, INGRESO, RETIRO, EGRESO_DEVOLUCION, CIERRE).
- CIERRE duplicado NO se repite (1 turno, 1 CIERRE, diferencia intacta; regla 10).
- Turno CAJERO: apertura L. 100, venta L. 75, cierre contado 165 / esperado 175 →
  diferencia NEGATIVA −10.00 persistida; historial (APERTURA, INGRESO_VENTA, CIERRE).
- Caja cerrada RECHAZA ingreso/retiro (regla 6); el cajero sin turno no ve ni opera
  la caja del admin; ruta admin → 403.
- ROLLBACK total verificado por conteos de movimientos (9 = 6 admin + 3 cajero).
- Regresión venta (INTACTA), regresión devolución (1 solo EGRESO_DEVOLUCION),
  regresión Kardex (SALIDA_VENTA y DEVOLUCION_VENTA exactos), stocks finales
  p1=28 p4=14 → luego RESET a seed.
- Limpieza final: ventas=0, cajas=0, mov_caja=0, compras=0, proveedores=0,
  devoluciones=0, detalle_devoluciones=0, solo 4 `INVENTARIO_INICIAL`,
  stocks seed 30/150/4/15/0 (VERIFICADO también por consulta directa a MySQL).

### Errores corregidos

| ID | Hallazgo | Corrección |
|---|---|---|
| E7-1 | (Batería, no bug) T10b comparaba el conteo de movimientos antes de la devolución | Se captura el conteo en el punto exacto de la prueba; el 419 ya no dejaba efecto |
| E7-2 | (Batería, no bug) T15 esperaba el literal "cerrada" pero la caja cerrada devuelve "No tienes un turno de caja abierto" | La expectativa acepta ambos mensajes (comportamiento del sistema correcto) |

### Notas

- `movimientos_caja.tipo` es VARCHAR(30) desde Fase 6 → los nuevos tipos
  (APERTURA/INGRESO_VENTA/INGRESO/RETIRO/CIERRE) NO requirieron ALTER de la tabla
  de movimientos; solo se amplió `cajas_sesiones` (migración 004).
- El arqueo de tarjeta mantiene la lógica previa (`tarjeta_esperada = ventas Tarjeta`);
  la transferencia ya estaba incluida en `monto_ventas_calculado`.
- El movimiento `CIERRE` queda con monto 0 (marcador del arqueo); no altera el saldo.
- `DEVOLUCION_COMPRA` (devolución a proveedor) sigue pendiente de fases futuras.

---

## FASE 6 — DEVOLUCIONES (2026-08-11) — REGISTRO

### Cambios realizados

| # | Cambio | Archivo / Recurso | Estado |
|---|---|---|---|
| F6-1 | Migración idempotente `003_devoluciones.sql`: tablas `devoluciones` (cabecera con monto, método de pago de la venta original, estados 'Completada'), `detalle_devoluciones` (precios ORIGINALES de la venta), `movimientos_caja` (trazabilidad del dinero: `EGRESO_DEVOLUCION`; el arqueo actual NO cambia) | `database/migrations/003_devoluciones.sql` (NUEVO) | VERIFICADO |
| F6-2 | `DevolucionService` (NUEVO): `procesarDevolucion()` — única transacción: `SELECT ... FOR UPDATE` sobre la venta; valida venta Completada (no anulada), turno de caja abierto, pertenencia del producto a la venta y cantidad ≤ (vendido − ya devuelto); inserta cabecera+detalle; Kardex `DEVOLUCION_VENTA` por línea vía `InventarioService` (recalcula disponibilidad, nunca toca Descontinuado); `movimientos_caja` EGRESO por el total; COMMIT o ROLLBACK total. Lecturas: `obtenerVentaParaDevolucion()` (con saldos devuelto/disponible), `listarDevoluciones()`, `obtenerDevolucionConDetalles()` | `helpers/DevolucionService.php` (NUEVO) | VERIFICADO |
| F6-3 | `DevolucionController` (NUEVO): valida turno de caja activo del usuario (misma regla que ventas), IDOR (cajero solo sus ventas, admin todas), auditoría por devolución | `controllers/DevolucionController.php` (NUEVO) | VERIFICADO |
| F6-4 | Rutas: vista `devoluciones` (Cajero y Administrador — operación de POS) + AJAX `venta_devolucion_datos_ajax` (404/403), `crear_devolucion_ajax` (POST JSON + `require_csrf` → 419), `detalle_devolucion_ajax` | `index.php` | VERIFICADO |
| F6-5 | Vista `devoluciones.php` (NUEVO): buscador de venta por ID, tabla con Vendido/Devuelto/Disponible, cantidades a devolver (máx disponible), motivo, total en vivo, listado y modal de detalle — mismo patrón visual existente | `views/devoluciones.php` (NUEVO) | VERIFICADO |
| F6-6 | Navegación "Devoluciones" para todos los roles | `views/includes/header.php` | VERIFICADO |

### Reglas de negocio implementadas

- Devolución COMPLETA y PARCIAL (una o varias líneas, uno o varios productos en la
  misma operación).
- La venta original DEBE existir y estar `Completada`. Anulada o inexistente → rechazada.
- No se puede devolver más de lo vendido: disponible = vendido − ya devuelto por
  venta+producto (se acumulan devoluciones previas).
- El producto DEBE pertenecer a la venta (validado contra `detalle_ventas`).
- El monto devuelto usa el `precio_unitario` ORIGINAL del detalle de la venta (ISV
  incluido), nunca el precio actual del catálogo.
- El historial de la venta original NO se modifica (cabecera y detalle intactos).
- Stock reintegrado vía `InventarioService` (`DEVOLUCION_VENTA`, entrada positiva,
  ref `DEVOLUCION` + id de devolución) en la misma transacción.
- Dinero devuelto registrado en `movimientos_caja` (tipo `EGRESO_DEVOLUCION`,
  referencia a la devolución, en el turno de caja ACTIVO del usuario).
- Permisos: Cajero y Administrador (operación de POS); el cajero solo puede operar
  sobre SUS ventas (IDOR → 403) y requiere turno de caja abierto.
- CSRF: `require_csrf()` en el endpoint POST (419 sin token válido).
- Auditoría en cada devolución.

### Pruebas realizadas (Fase 6)

Batería HTTP completa contra `php -S 127.0.0.1:8090` (cliente cURL PHP
`f6_cliente.php`): **47/47 PRUEBAS EXITOSAS** (evidencia `f6_run2.txt`):

- Devolución COMPLETA (prod4 ×5): monto exacto (precio de venta seed × cantidad),
  stock 10→15, Kardex `DEVOLUCION_VENTA|5|10|15|DEVOLUCION|devN`, caja
  `EGRESO_DEVOLUCION|monto|DEVOLUCION|devN`, venta original intacta
  (Completada + 2 detalles).
- Devolución PARCIAL (prod1 ×1 de 3): stock 27→28, Kardex `|1|27|28`.
- MÚLTIPLES productos en una sola devolución (1+1): 2 Kardex, monto suma exacta.
- Cantidad superior a la vendida (99 > disponible 1): rechazada, sin efecto en BD.
- Venta inexistente (99999) y venta ANULADA: rechazadas ("no existe o ya fue anulada").
- Producto no perteneciente a la venta: rechazado.
- CSRF: endpoint sin token y con token inválido → HTTP 419.
- Permisos: cajero viendo venta ajena → 403; cajero con su caja → devuelve su propia
  venta OK y el egreso queda en SU caja.
- Stocks acumulados exactos (27/150/4/15/0); Kardex de 5 DEVOLUCION_VENTA totales
  con stock_anterior/nuevo exactos; caja sumas exactas por turno (admin/cajero).
- ROLLBACK total: devolución con item válido + item ajeno → rechazada; sin nueva
  devolución, sin Kardex, sin movimientos de caja, stock intacto.
- Cantidad 0, negativa y sin productos: rechazadas. Devolución sin caja abierta: rechazada.
- REGRESIÓN: venta (SALIDA_VENTA 15→13), anulación (AJUSTE_ENTRADA 13→15),
  Kardex de ambos idénticos a Fase 4/5.
- Limpieza final: ventas=0, cajas=0, compras=0, devoluciones=0, detalle_devoluciones=0,
  movimientos_caja=0, solo 4 `INVENTARIO_INICIAL`, stocks seed 30/150/4/15/0
  (verificado además por consulta directa a MySQL).

### Errores encontrados y corregidos (Fase 6)

| # | Error | Corrección |
|---|---|---|
| E6-1 | `SQLSTATE[42S22] Column not found: 'c.numero_identidad'` en la consulta de la venta (la columna real de `clientes` es `identificacion`) | Se eliminó el JOIN a `clientes` de la consulta de bloqueo (dato no usado por el servicio) — `helpers/DevolucionService.php` |

### Notas

- El ENUM del Kardex ya contemplaba `DEVOLUCION_VENTA` desde la migración 001
  (Fase 4), así que NO se alteró el esquema de `movimientos_inventario`.
- `movimientos_caja` es trazabilidad (EGRESO_DEVOLUCION en esta fase); el cálculo de
  arqueo existente no se modificó: la diferencia de cierre absorbe el dinero
  devuelto y el reporte (Fase de Caja ampliada) tendrá el detalle.
- No se implementa devolución a proveedor (`DEVOLUCION_COMPRA` sigue reservado).

---

## FASE 5 — PROVEEDORES Y COMPRAS (2026-08-11) — REGISTRO

### Cambios realizados

| # | Cambio | Archivo / Recurso | Estado |
|---|---|---|---|
| F5-1 | Migración idempotente `002_compras.sql`: tablas `proveedores`, `compras` (estados BORRADOR/CONFIRMADA/ANULADA), `detalle_compras` con FKs e índices | `database/migrations/002_compras.sql` (NUEVO) | VERIFICADO |
| F5-2 | `CompraService` nuevo: `crearBorrador()` (no toca stock), `confirmarCompra()` transaccional (reutiliza `InventarioService::aplicarMovimiento()` → stock + Kardex `ENTRADA_COMPRA` atómicos, idempotente, bloqueo `FOR UPDATE`), `listarCompras()`, `obtenerCompraConDetalles()` | `helpers/CompraService.php` (NUEVO) | VERIFICADO |
| F5-3 | `ProveedorController` nuevo: CRUD con baja lógica (`activo=0`), validación backend (nombre obligatorio, correo válido, RTN seguro), auditoría en cada operación | `controllers/ProveedorController.php` (NUEVO) | VERIFICADO |
| F5-4 | `CompraController` nuevo: crea borradores, confirma compras, listado y detalle + auditoría | `controllers/CompraController.php` (NUEVO) | VERIFICADO |
| F5-5 | Rutas: `proveedores`, `compras` (ambas `requireRole('Administrador')`) + AJAX `buscar_proveedores_ajax`, `crear_compra_ajax`, `confirmar_compra_ajax`, `detalle_compra_ajax` (con `require_csrf()` en los POST) | `index.php` | VERIFICADO |
| F5-6 | Vistas `proveedores.php` (tabla + búsqueda GET + modales crear/editar/desactivar con CSRF) y `compras.php` (modal nueva compra con filas dinámicas: producto/cantidad/costo/subtotal, totales calculados, guardar BORRADOR y confirmar vía AJAX + modal detalle) | `views/proveedores.php`, `views/compras.php` (NUEVOS) | VERIFICADO |
| F5-7 | Navegación "Compras" y "Proveedores" (solo Administrador) | `views/includes/header.php` | VERIFICADO |
| F5-8 | Bug HY093 corregido en `buscarProveedores`: placeholder `:q` repetido 4 veces en un `execute` con `EMULATE_PREPARES=false` → `:q1..:q4` (mismo patrón que Fase 4) | `controllers/ProveedorController.php` | VERIFICADO |

### Reglas de negocio implementadas

- BORRADOR NO toca stock; CONFIRMADA aplica stock + 1 Kardex `ENTRADA_COMPRA` por detalle
  (ref `COMPRA` / ID compra, `costo_unitario` del detalle, usuario que confirma).
- Doble confirmación rechazada ("ya fue confirmada"); sin duplicar stock ni Kardex. Idempotente.
- Confirmación en UNA transacción: `SELECT ... FOR UPDATE` sobre la compra + validaciones
  por detalle (producto existe/activo/no descontinuado) + `aplicarMovimiento()` +
  recálculo de totales desde BD + COMMIT. Cualquier fallo → ROLLBACK total.
- Validaciones: cantidad > 0, costo >= 0, producto existente, proveedor existente y activo,
  compra con >= 1 detalle, proveedor activo al confirmar.
- Impuesto de compra = 16% (misma tasa ISV del sistema); precios de venta NO se modifican;
  costo promedio NO implementado (fase futura).
- Proveedores: nunca se eliminan físicamente; baja lógica si tienen compras.
- Anulación de compra: NO implementada (requiere tipos nuevos de Kardex de reversión +
  reglas de stock disponible) → se reporta PENDIENTE para fase futura.

### Pruebas realizadas (Fase 5)

Batería HTTP completa contra `php -S 127.0.0.1:8090` (cliente cURL PHP `f5_cliente.php`):
**52/52 PRUEBAS EXITOSAS** (evidencia `f5_run2.txt`):

- Proveedores: crear/editar/desactivar (baja lógica verificado en BD), búsqueda AJAX (1
  resultado exacto), nombre vacío rechazado, cajero → 403 (vista proveedores, compras y
  `crear_compra_ajax`), CSRF inválido → el POST no ejecuta la acción + AJAX sin CSRF → 419.
- Compras: BORRADOR con 2 productos (subtotal 310.00 / impuesto 49.60 / total 359.60),
  detalles exactos en BD, sin tocar stock; confirmación → CONFIRMADA, stock 30→35 y 15→18;
  Kardex exacto `ENTRADA_COMPRA|5|30|35|COMPRA|1|1` y `|3|15|18|COMPRA|1|1`;
  stock_final = stock_inicial + cantidad comprada.
- Doble confirmación: rechazada, stock intacto, Kardex sin duplicados.
- Validaciones: cantidad 0/negativa, costo negativo, producto inexistente, proveedor
  inexistente/inactivo, sin detalles → todas rechazadas con mensaje claro.
- SQL injection en `numero_documento` (`FAC'; DROP TABLE compras; --`): guardada como dato
  literal seguro; tabla intacta (prepared statements).
- ATOMICIDAD: borrador de 3 productos; el 2º producto descontinuado antes de confirmar →
  confirmación falla → ROLLBACK total: compra sigue BORRADOR, stocks prod2/prod1 intactos,
  sin Kardex parcial.
- Limpieza final: ventas=0, cajas=0, compras=0, proveedores TEST=0, solo 4
  `INVENTARIO_INICIAL`, stocks seed (30/150/4/15/0). (VERIFICADO también por consulta
  directa a MySQL.)

### Regresión (VERIFICADO)

Apertura de caja, venta 2x prod1 con Kardex `SALIDA_VENTA`, anulación con `VENTA_ANULADA`,
ajuste de stock +5, vista inventario 200, Kardex AJAX, cajero → dashboard 403, cajero → POS
200: TODO funcionando (R1–R10).

### Sintaxis

`php -l` SIN ERRORES en: `index.php`, `helpers/CompraService.php`,
`controllers/ProveedorController.php`, `controllers/CompraController.php`,
`views/proveedores.php`, `views/compras.php`, `views/includes/header.php`.

### Pendientes (documentados, NO implementados por alcance de fase)

- Anulación de compra (requiere tipos de reversión en el ENUM del Kardex + validación de
  stock disponible; si genera stock negativo debe rechazarse).
- Edición de borradores guardados (la UI permite modificar cantidad/eliminar filas ANTES de
  guardar; el borrador guardado no es editable).
- Costo promedio ponderado y actualización de `precio_compra` del producto.
- Devoluciones a proveedor (`DEVOLUCION_COMPRA` existe en el ENUM, sin uso).

---

## FASE 4 — KARDEX Y CONTROL SEGURO DEL STOCK (2026-08-11) — REGISTRO

### Cambios realizados

| # | Cambio | Archivo / Recurso | Estado |
|---|---|---|---|
| F4-1 | Migración Kardex idempotente (tabla `movimientos_inventario` + 4 movimientos `INVENTARIO_INICIAL` de los stocks seed) | `database/migrations/001_kardex.sql` (NUEVO) | VERIFICADO |
| F4-2 | `InventarioService` nuevo: `aplicarMovimiento()` transaccional (valida producto, normaliza signo, `SELECT ... FOR UPDATE`, stock no negativo, no recalcula `disponibilidad` si es `Descontinuado`, inserta Kardex en la misma transacción) y `obtenerMovimientos()` (lectura con usuario, página/limite) | `helpers/InventarioService.php` (NUEVO) | VERIFICADO |
| F4-3 | Venta registra Kardex `SALIDA_VENTA` (referencia `VENTA`) en la misma transacción | `controllers/VentaController.php` | VERIFICADO |
| F4-4 | Anulación registra Kardex `AJUSTE_ENTRADA` (referencia `VENTA_ANULADA`) y YA NO fuerza `disponibilidad='Disponible'` (bug B3 corregido: respeta `Descontinuado`) | `controllers/VentaController.php` | VERIFICADO |
| F4-5 | `editarProducto` ya NO modifica stock (regla "el stock no se edita directo"): responde `success=true` + `warning` "El stock NO fue modificado..." | `controllers/InventarioController.php` | VERIFICADO |
| F4-6 | `crearProducto` inserta stock en `0` y registra `INVENTARIO_INICIAL` vía Kardex (bug de doble aplicación de stock corregido) | `controllers/InventarioController.php` | VERIFICADO |
| F4-7 | Rutas AJAX: `ajuste_stock_ajax` (POST, `requireRole('Administrador')` + CSRF, header `X-CSRF-Token`) y `movimientos_kardex_ajax` (GET, `requireLogin`) | `index.php` | VERIFICADO |
| F4-8 | `obtenerMovimientos` corregido: mezclar `bindValue(':lim')` con `execute([':pid'])` lanzaba `HY093` en PDO MySQL (bug real) | `helpers/InventarioService.php` | VERIFICADO |
| F4-9 | UI Inventario: campo stock read-only con botón "Ajustar" (abre el modal de ajuste), modal Ajuste de Stock (entrada/salida + motivo obligatorio), modal Kardex (carga AJAX, signos +/−), mensaje warning en la respuesta del POST | `views/inventario.php`, `assets/js/pos.js` | VERIFICADO |
| F4-10 | Venta con stock insuficiente rechazada ANTES de tocar stock (mensaje con disponible/solicitado) | `controllers/VentaController.php` | VERIFICADO |

### Pruebas realizadas (Fase 4)

Batería HTTP completa contra `php -S 127.0.0.1:8090` con cliente cURL de PHP
(`f4_cliente.php`): **36/36 PRUEBAS EXITOSAS** (run10, `f4_run10.txt`):

| Prueba | Resultado |
|---|---|
| Reset de BD (estado seed) | VERIFICADO (ventas=0, cajas=0) |
| Apertura de caja admin (setup) | VERIFICADO (caja=Abierta\|1) |
| Venta 2x prod1 (30→28) + Kardex `SALIDA_VENTA\|-2\|30\|28\|VENTA` | VERIFICADO |
| Venta insuficiente rechazada (prod3: 4, pide 5) — sin Kardex nuevo | VERIFICADO |
| Anulación (stock 30 restaurado, estado Anulada) + `AJUSTE_ENTRADA\|2\|28\|30\|VENTA_ANULADA` | VERIFICADO |
| Bug descontinuados: venta prod2 → baja `Descontinuado` → anulación: stock 150 restaurado y disponibilidad SIGUE `Descontinuado` | VERIFICADO |
| Ajuste entrada +5 (prod4 15→20) y salida −3 (20→17) con Kardex correcto | VERIFICADO |
| Ajuste salida sin stock rechazado (prod5: 0) — sin Kardex | VERIFICADO |
| Ajuste sin motivo rechazado ("obligatorio, mínimo 3 caracteres") | VERIFICADO |
| Cajero → `ajuste_stock_ajax` = HTTP 403 (rol) | VERIFICADO |
| Ajuste sin token CSRF = HTTP 419 | VERIFICADO |
| Edición de producto con `stock=999`: aviso "El stock NO fue modificado", stock intacto, SIN Kardex nuevo | VERIFICADO |
| Alta de producto con stock inicial=10: stock=10 y Kardex `INVENTARIO_INICIAL\|10\|0\|10` (doble aplicación corregida) | VERIFICADO |
| Lectura Kardex AJAX prod1 (≥3 movimientos, JSON) | VERIFICADO |
| Limpieza final: solo 4 `INVENTARIO_INICIAL`, stocks seed restaurados, p2 `Disponible` | VERIFICADO |

**Atomicidad** (`f4_atomicidad.php`): PASS — movimiento válido + error forzado posterior en la
misma transacción → ROLLBACK total (stock y Kardex IGUALES antes/después).

**Sintaxis**: `php -l` SIN ERRORES en `index.php`, `InventarioController.php`,
`InventarioService.php`, `VentaController.php`, `views/inventario.php`, `conexion/db.php`.

**Estado de la BD tras las pruebas (VERIFICADO)**: 4 movimientos `INVENTARIO_INICIAL`;
stocks seed (30/150/4/15/0); p5 `Agotado`; ventas=0; cajas=0.

### Notas

- La anulación usa `AJUSTE_ENTRADA` + `referencia_tipo='VENTA_ANULADA'` (reversión
  administrativa). `DEVOLUCION_VENTA` queda reservada a una fase futura de devoluciones.
- Historial anterior a esta fase NO se reconstruyó (solo 4 `INVENTARIO_INICIAL`).
- El "misterio" de pruebas HTTP anteriores era del cliente (Invoke-WebRequest de PS 5.1 con
  keep-alive vs `php -S` single-threaded); resuelto usando cURL de PHP con `Connection: close`.
- `editarProducto` conserva el valor de `stock` recibido SOLO para validación/feedback; el
  stock real queda intacto.
- Cliente de pruebas: `C:\Users\Usuario\AppData\Local\Temp\opencode\f4_cliente.php`
  (no forma parte del proyecto).

---

## FASE 2 — PREPARAR ENTORNO LOCAL (2026-08-10) — REGISTRO

### Cambios realizados

| # | Cambio | Archivo / Recurso | Estado |
|---|---|---|---|
| F2-1 | Respaldo lógico de la BD legacy local | `backups/sublimacion_db_legacy_20260810.sql` (12,188 bytes) | VERIFICADO |
| F2-2 | Hashes seed regenerados (`admin123` / `cajero123`); los anteriores NO verificaban con `password_verify()` | `schema.sql:126-127` | VERIFICADO |
| F2-3 | BD local nueva `sublimation_db` creada con `schema.sql` (8 tablas + seed) | BD local MariaDB | VERIFICADO |
| F2-4 | Configuración local separada de credenciales (variables de entorno) | `config.local.php` (NUEVO) | VERIFICADO |
| F2-5 | `db.php`: carga `config.local.php` si existe + `envDb()` distingue "no definida" de "vacía" | `conexion/db.php:15-40` | VERIFICADO |
| F2-6 | Protección de archivos sensibles para futuros repos | `.gitignore` (NUEVO): `config.local.php`, `backups/` | VERIFICADO |

La BD legacy `sublimacion_db` NO fue modificada ni eliminada (solo respaldada). Las
credenciales reales de AWS siguen intactas como fallback (NO rotadas — pendiente de
autorización para la fase AWS). No se tocó AWS ni RDS.

### Pruebas realizadas (Fase 2)

| Prueba | Resultado |
|---|---|
| `php -l` en archivos modificados (`db.php`, `config.local.php`) | SIN ERRORES |
| Conexión PHP→MySQL local (config local cargada) | VERIFICADO: conecta como `root@localhost`, host `127.0.0.1`, BD `sublimation_db` |
| Tablas requeridas por el código | 8/8 presentes (sin faltantes) |
| Codificación UTF-8 del seed | VERIFICADO: `Taza Mágica Negra 11oz` correcto |
| `password_verify('admin123')` contra hash en BD | TRUE |
| `password_verify('cajero123')` contra hash en BD | TRUE |
| Login `admin/admin123` (AuthController, CLI) | OK — rol `Administrador` en sesión |
| Login `cajero/cajero123` (AuthController, CLI) | OK — rol `Cajero` en sesión |
| Login con contraseña incorrecta | RECHAZADO correctamente |
| Login con usuario inexistente | RECHAZADO correctamente |
| HTTP: GET `route=login` | 200, formulario renderizado |
| HTTP: POST login admin → sesión | Redirección a `route=pos`; `GET route=pos` con sesión = 200 y muestra "Apertura de Caja Obligatoria" |
| HTTP: POST login cajero → sesión | Redirección a `route=pos` |
| HTTP: GET `route=dashboard` como cajero | 403 (acceso denegado por rol) — correcto |

### Notas

- El warning de sesión en la prueba CLI es un artefacto del modo consola (headers ya
  enviados), no un error del sistema; el flujo web completo funciona (validado por HTTP).
- `config.local.php` debe permanecer EXCLUIDO de AWS y de repositorios.
- Pendiente Fase 3: CSRF, `session_regenerate_id`, SameSite, IDOR, rate-limit, `.htaccess`.

---

## A. ESTADO GENERAL

| Aspecto | Estado |
|---|---|
| Ruta local del proyecto | `C:\xampp\htdocs\SISTEMA_DE_VENTAS` (VERIFICADO) |
| Ruta producción (AWS) | `/var/www/html` (documentado, NO verificado localmente) |
| PHP local | 8.2.12 CLI (ZTS) (VERIFICADO) |
| MySQL local | MariaDB 10.4.32 en `C:\xampp\mysql\bin` (VERIFICADO) |
| Sintaxis PHP de todos los archivos | 17/17 archivos OK (`php -l`) (VERIFICADO) |
| BD local | `sublimacion_db` — esquema LEGACY incompatible con el código (VERIFICADO) |
| BD objetivo (AWS RDS) | `sublimation_db` — esquema nuevo del `schema.sql` (NO VERIFICADO) |
| Framework | PHP nativo + Bootstrap 5 (CDN) — se mantiene |
| MVC | Rutas en `index.php` + controladores + vistas (estructura propia, no MVC puro) |

**Conclusión:** el código está funcionalmente bien escrito (PDO, prepared statements,
transacciones con `FOR UPDATE`, auditoría), pero existen **vulnerabilidades de seguridad
prioritarias** (CSRF, sesión fija, credenciales reales en código) y **la copia local NO
puede ejecutarse tal cual** porque la BD local es un esquema anterior al que el código espera.

---

## B. ESTRUCTURA REAL

```
C:\xampp\htdocs\SISTEMA_DE_VENTAS
├── index.php                     → Enrutador central + endpoints AJAX
├── schema.sql                    → Esquema NUEVO (target AWS RDS)
├── assets/
│   ├── css/style.css             → Estilos propios (dark theme)
│   └── js/main.js, pos.js        → Utilidades + lógica POS
├── conexion/db.php               → PDO singleton (credenciales por defecto AWS RDS)
├── controllers/
│   ├── AuthController.php        → Login/logout/roles/sesión
│   ├── AuditoriaController.php   → Logs de auditoría
│   ├── CajaController.php        → Apertura/cierre/arqueo/historial
│   ├── ClienteController.php     → CRUD clientes + historial compras
│   ├── CompraController.php      → Compras (borrador/confirmación) — Fase 5
│   ├── DevolucionController.php  → Devoluciones — Fase 6
│   ├── InventarioController.php  → Productos + categorías
│   ├── ProveedorController.php   → Proveedores — Fase 5
│   ├── ReporteController.php     → 7 reportes + resumen dashboard — Fase 8
│   ├── UsuarioController.php     → CRUD usuarios (admin)
│   └── VentaController.php       → Procesar/anular venta, consultas
└── views/
    ├── login.php, pos.php, dashboard.php, inventario.php,
    │   clientes.php, usuarios.php, auditoria.php, reportes.php,
    │   devoluciones.php, compras.php, proveedores.php
    └── includes/header.php, footer.php
```

NO existen: `.htaccess`, `.env`, `composer.json`, `database/migrations/`, `README.md`,
`CHANGELOG.md`, `AUDITORIA_PROGRESO.md` (los dos últimos se crean en esta fase).
(VERIFICADO)

---

## C. MÓDULOS

| Módulo | Estado | Problemas | Prioridad |
|---|---|---|---|
| Autenticación | Funcional en código | Sin `session_regenerate_id`; sin rate-limit; credenciales seed inválidas | ALTA |
| POS / Venta | Funcional en código | Sin CSRF en cobro; ticket solo informativo (sin impresión) | ALTA |
| Ventas / Anulación | Funcional | Sin CSRF; reintegra `disponibilidad='Disponible'` sin importar estado previo | MEDIA |
| Inventario | Funcional | Sin Kardex; stock editable directamente; sin UI editar/eliminar categorías | ALTA |
| Caja | Funcional | Sin ingresos/retiros intermedios; `cerrarCaja` sin validación de propiedad (defensa en profundidad) | MEDIA |
| Clientes | Funcional | Sin CSRF | MEDIA |
| Usuarios | Funcional | Sin CSRF; sin editar contraseña propia | MEDIA |
| Auditoría | Funcional | Falta registro de valor anterior/nuevo en cambios | BAJA |
| Dashboard | Básico | Solo 4 KPIs + 2 tablas; sin gráficas ni filtros ni reportes reales | MEDIA |
| Compras / Proveedores | ✔ COMPLETADOS (Fase 5) | CRUD proveedores (baja lógica), compra BORRADOR/CONFIRMADA con Kardex; anulación de compra y costo promedio pendientes | COMPLETADA |
| Devoluciones | ✔ COMPLETADAS (Fase 6) | Parcial y completa; pendiente devolución a proveedor | COMPLETADA |
| Kardex | ✔ Funcional (Fase 4) | Tipos: INVENTARIO_INICIAL, SALIDA_VENTA, AJUSTE_ENTRADA/SALIDA, VENTA_ANULADA, ENTRADA_COMPRA, DEVOLUCION_VENTA | COMPLETADA |
| Reportes | ✔ COMPLETADOS (Fase 8) | 7 reportes + resumen dashboard con filtros y CSV; pendiente paginación | COMPLETADA |
| Ticket | Parcial | Solo toast informativo, sin vista de ticket ni impresión | BAJA |

---

## D. BASE DE DATOS

### D.1 Esquema objetivo (`schema.sql`, nuevo — target AWS RDS)

Tablas: `usuarios`, `cajas_sesiones`, `categorias`, `productos`, `clientes`, `ventas`,
`detalle_ventas`, `auditoria_logs`.

- **usuarios** → roles `Administrador`/`Cajero`, `password_hash`, `activo`.
- **cajas_sesiones** → FK `usuario_id`, monto apertura/cierre, `estado` Abierta/Cerrada.
- **productos** → FK `categoria_id`, `codigo_barras` UNIQUE, `precio_compra`, `precio_venta`, `stock`, `stock_minimo`, `disponibilidad`.
- **ventas** → FKs `caja_sesion_id`, `usuario_id`, `cliente_id`; `num_factura` UNIQUE; `metodo_pago` Efectivo/Tarjeta/Transferencia; `estado` Completada/Anulada.
- **detalle_ventas** → FK `venta_id` (CASCADE), `producto_id`, cantidad, precio_unitario, subtotal.
- **auditoria_logs** → FK `usuario_id` (SET NULL), ip, fecha.

### D.2 Esquema legacy local (`sublimacion_db`, MariaDB) — NO COMPATIBLE con el código actual

Tablas: `usuarios`, `ventas`, `detalle_ventas`, `productos`, `movimientos_inventario`, `auditoria`, `intentos_login`.

Diferencias críticas:
- `usuarios.rol` = enum `('admin','cajero')` (minúsculas) vs `'Administrador'/'Cajero'` → `requireRole('Administrador')` siempre negaría al admin local.
- `productos` usa `sku`, `precio`, `costo`; NO tiene `categoria_id`, `codigo_barras`, `disponibilidad` → las consultas del código fallan.
- `ventas` usa `fecha`, `monto_recibido`; NO tiene `caja_sesion_id`, `num_factura`, `monto_pagado`.
- NO existen `cajas_sesiones`, `categorias`, `clientes`, `auditoria_logs`.
- La tabla `movimientos_inventario` (Kardex) y `intentos_login` existen SOLO en este esquema legacy.
- 3 usuarios: `admin`, `cajero`, `besner` (rol admin). 0 productos. 0 ventas.

### D.3 Veredicto

- El código actual **solo funciona** contra el esquema nuevo (`sublimation_db` de `schema.sql`).
- La BD de AWS RDS **NO está verificada** (NO conecté a producción; regla del proyecto).
- Para pruebas locales será necesario crear la BD nueva localmente (Fase 2, con autorización explícita), **sin tocar** la legacy.

---

## E. SEGURIDAD — VULNERABILIDADES DETECTADAS

| # | Vulnerabilidad | Severidad | Archivo | Detalle |
|---|---|---|---|---|
| V1 | Credenciales reales de AWS RDS hardcodeadas como fallback | **CRÍTICA** | `conexion/db.php:15-18` | Host/usuario/contraseña de RDS en el código. Debe rotarse la credencial en AWS y usar SOLO variables de entorno. |
| V2 | Contraseñas seed inválidas (no verifican `admin123`/`cajero123`) | **CRÍTICA** | `schema.sql:126-127` | `password_verify()` devuelve FALSE para ambos hashes (VERIFICADO con PHP). Con este esquema nadie puede iniciar sesión con las credenciales documentadas. |
| V3 | Ausencia total de protección CSRF | **ALTA** | Todos los POST: `login.php`, `pos.php` (apertura/cierre), `clientes.php`, `inventario.php`, `usuarios.php`, `dashboard.php` (anulación), AJAX `cobrar_ajax`, `crear_usuario_ajax`, `cambiar_estado_usuario_ajax` | Cualquier sitio malicioso puede forzar acciones (anular ventas, crear usuarios, cobrar, cerrar caja) con la sesión de una víctima. |
| V4 | Sin `session_regenerate_id(true)` tras login (fijación de sesión) | **ALTA** | `AuthController.php:44-49` | Un atacante que fije un ID de sesión previo conserva la sesión tras el login. |
| V5 | Logout por GET sin token (logout CSRF) | BAJA | `index.php` / `header.php:137` | Un atacante puede cerrar la sesión de la víctima. |
| V6 | IDOR en `ver_productos_venta_ajax` | MEDIA | `index.php:46-52` | Cualquier usuario autenticado (cajero) puede leer el detalle de CUALQUIER venta por ID, sin validar que sea suya o de su caja. |
| V7 | `CajaController::cerrarCaja()` no valida que la sesión pertenezca al usuario actual | MEDIA | `CajaController.php:123` | El flujo de la UI lo mitiga (usa la caja propia), pero el método no aplica defensa en profundidad: un POST manipulado a `accion_caja=cierre` + ID ajeno cierra la caja de otro usuario. |
| V8 | XSS por patrón `onclick='... json_encode(...)'` e `innerHTML` con datos del servidor | MEDIA | `views/pos.php:137`, `views/inventario.php:187`, `views/clientes.php:230`, `pos.js:96,164`, `main.js:52` | Mitigado de facto porque los nombres se almacenan con `htmlspecialchars()` al insertar (codificación en INPUT, patrón frágil). Riesgo si algún registro histórico o importación no pasa por ese filtro. |
| V9 | Credenciales de prueba visibles en el login | MEDIA | `views/login.php:79-89` | En producción publica usuarios y contraseñas al público. |
| V10 | Sin rate-limit / bloqueo por intentos en login | MEDIA | `AuthController.php` | Permite fuerza bruta (la BD legacy tiene `intentos_login`, pero el código actual no lo usa). |
| V11 | Sin `.htaccess` (listado de directorios / headers de seguridad) | MEDIA | raíz del proyecto | Riesgo de exposición de `schema.sql`, `conexion/`, logs. |
| V12 | Cookies de sesión sin `SameSite` ni `Secure`; sin `session.use_strict_mode` | MEDIA | `AuthController.php:10-15` | `HttpOnly` sí configurado; `Secure` aplicará cuando haya HTTPS en AWS. |
| V13 | ISV 16% hardcodeado en 3 lugares (PHP, JS, vistas) | MEDIA | `VentaController.php:105`, `pos.js:316`, `views/pos.php:208` | Si el ISV cambia, se rompe el desglose; sin configuración centralizada. |
| V14 | Errores de usuario expuestos: mensajes PDO ocultos, pero `error_log` registra detalles | BAJA | varios | Aceptable: no se muestran al cliente. |

### Aspectos SEGUROS ya presentes (VERIFICADO)

- 100% de las consultas usan **prepared statements** (PDO, `EMULATE_PREPARES=false`). No se encontró SQL Injection.
- Precios/cantidades de la venta se toman de la BD, nunca del cliente (sin manipulación de precios).
- `SELECT ... FOR UPDATE` en venta y anulación (evita carreras de stock).
- Transacciones PDO con rollback en venta y anulación.
- `password_hash()` / `password_verify()` para credenciales.
- Roles validados en backend (`requireRole`) en todas las rutas administrativas.
- Salidas de datos mayoritariamente con `htmlspecialchars()`.
- Errores PDO ocultos al usuario (mensaje genérico + `error_log`).
- Baja lógica de productos/categorías (integridad de historial).

---

## F. BUGS

| # | BUG | CAUSA | IMPACTO | ARCHIVOS | SOLUCIÓN (propuesta) | PRUEBA | RESULTADO |
|---|---|---|---|---|---|---|---|
| B1 | Login con credenciales documentadas falla | Hashes seed no corresponden a `admin123`/`cajero123` | Usuario no puede entrar con las credenciales del README/schema | `schema.sql:126-127` | Regenerar hashes correctos en el seed | `password_verify()` con PHP 8.2 | **CONFIRMADO** (FALSE/FALSE) |
| B2 | El sistema no funciona contra la BD local legacy | Esquema local (`sublimacion_db`) no tiene `cajas_sesiones`, `categorias`, `clientes`, etc. | Error SQL en cada módulo al probar localmente | `CajaController`, `InventarioController`, `AuthController` vs `sublimacion_db` | Crear BD nueva local `sublimation_db` con `schema.sql` (Fase 2, previa autorización) | `SHOW TABLES` local | **CONFIRMADO** |
| B3 | `anularVenta` fija `disponibilidad='Disponible'` siempre | UPDATE incondicional | Un producto Descontinuado vuelve a estar Disponible tras anulación | `VentaController.php:238` | Ajustar según estado previo/regla Kardex | Anulación de venta de prod2 `Descontinuado` (P4) | **CORREGIDO** (Fase 4): respeta el estado previo |
| B4 | En editar producto, el stock se modifica directamente sin registro de movimiento | Falta módulo Kardex | No hay trazabilidad del porqué del cambio; viola la regla "el stock no se edita directo" | `InventarioController.php:302-326` | Fase 4: convertir el ajuste de stock en movimiento Kardex `AJUSTE_*` | Edición con `stock=999` (P11) | **CORREGIDO** (Fase 4): stock intacto + warning; ajustes vía Kardex |
| B5 | `buscarProductos` aplica `htmlspecialchars` al término de búsqueda | Sanitización en input inadecuada | Búsquedas con `&`/`<` devuelven resultados erróneos; los datos almacenados quedan doble-codificados | `InventarioController.php:177` | Eliminar codificación en input; codificar solo en salida | — | Detectado (sin ejecutar) |
| B6 | Categorías: no existe UI para editar/eliminar aunque el controlador sí los implementa | Funcionalidad incompleta | Gestión limitada de categorías | `views/inventario.php` | Añadir botones de editar/desactivar categoría | — | Detectado (sin ejecutar) |
| B7 | Sin paginación en listados (productos, clientes, ventas, logs) | Diseño | Degrada con datos grandes | vistas varias | Paginación backend (Fase 9/10) | — | Detectado (sin ejecutar) |
| B8 | Inconsistencia de moneda en UI: `$0.00` inicial vs `L.` en JS | Hardcode | Cosmético pero confuso | `views/pos.php:206,210,214`, `pos.js` | Unificar símbolo L. | — | Detectado (sin ejecutar) |
| B9 | Cálculo de impuesto con float (`/ 1.16`) | Precisión flotante | Desfases de centavos en facturas de muchos ítems | `VentaController.php:105-106` | Redondeo explícito con `round(..., 2)` | — | Detectado (sin ejecutar) |
| B10 | Un administrador puede desactivar al ÚLTIMO administrador restante | Falta validación | Sistema sin admin | `UsuarioController.php:115` | Bloquear desactivar si es el único admin | — | Detectado (sin ejecutar) |

---

## G. INVENTARIO — ESTADO ACTUAL

- Stock viven en `productos.stock` y TODO cambio pasa por `InventarioService::aplicarMovimiento()`
  (transacción activa + Kardex). `editarProducto` NO toca el stock (regla cumplida, Fase 4).
- Kardex por producto (`movimientos_inventario`): INVENTARIO_INICIAL, SALIDA_VENTA (ventas),
  AJUSTE_ENTRADA/AJUSTE_SALIDA (ajustes y anulaciones con referencia VENTA_ANULADA).
- Ajustes: solo Administrador, con motivo obligatorio, token CSRF; entrada/salida con
  validación de stock no negativo.
- Alta de producto con stock inicial registra `INVENTARIO_INICIAL` automáticamente.
- Pendiente: compras, devoluciones de compra (tipos de Kardex nuevos), paginación (Fase 9/10).

## H. VENTAS — ESTADO ACTUAL

- Venta completa: transacción + `FOR UPDATE` + validación de stock + descuento + IVA 16% desglosado + `monto_pagado`/cambio + `num_factura` `SUB-YYYYMMDDHHMMSS-NN`.
- Métodos: Efectivo, Tarjeta, Transferencia (en Tarjeta/Transferencia se fija `monto_pagado = total`).
- Anulación (solo admin, desde Dashboard) con reintegro de stock en transacción.
- Falta: ticket imprimible, devoluciones parciales, motivo de anulación.
- El `cliente_id` por defecto es `1` (Público General).

## I. CAJA — ESTADO ACTUAL

- Apertura por usuario (fondo inicial), venta requiere caja abierta en sesión PHP + verificación en BD.
- Cierre: calcula efectivo/tarjeta/transferencia esperados, diferencia, y libera la sesión PHP.
- Falta: ingresos/retiros intermedios, movimientos por caja, arqueo en vivo.

## J. ROLES — PERMISOS ACTUALES

| Permiso | Administrador | Cajero |
|---|---|---|
| POS / vender | ✔ (con caja abierta) | ✔ (con caja abierta) |
| Apertura/Cierre de caja propia | ✔ | ✔ |
| Inventario (ver) | ✔ | ✔ |
| Inventario (crear/editar/baja) | ✔ | ✖ |
| Clientes (CRUD) | ✔ | ✔ (permite crear/editar por diseño) |
| Dashboard / anular ventas | ✔ | ✖ |
| Auditoría | ✔ | ✖ |
| Usuarios | ✔ | ✖ |

Validación de rol: SIEMPRE en backend (`requireRole`) en rutas y AJAX admin. (VERIFICADO)

## K. FUNCIONALIDADES FALTANTES

Devoluciones (parcial/total): COMPLETADAS (Fase 6). Reportes (7 + dashboard): COMPLETADOS (Fase 8).
Pendientes: anulación de compras, costo promedio ponderado, devoluciones a proveedor,
dashboard con gráficas, ticket imprimible, paginación, `.htaccess`,
configuración centralizada del ISV.

## L. RIESGOS

1. **Credenciales RDS en el repositorio** (V1): si este código se sube a un repo público, la BD de producción queda expuesta. Rotar credencial inmediatamente.
2. **Hashes seed rotos** (V2): bloquea la prueba inicial local.
3. **Sin CSRF** (V3): riesgo operativo real en producción si no se corrige antes del despliegue.
4. **Copia local no ejecutable** contra la BD legacy sin crear la BD nueva.
5. Discrepancia de nombres: `sublimation_db` (nuevo) vs `sublimacion_db` (legacy) — NO confundirlas al desplegar.

## M. PLAN DE IMPLEMENTACIÓN (orden obligatorio)

1. **Fase 1 — Auditoría** (COMPLETADA)
2. Fase 2 — Backups + credenciales (rotar RDS, env-only, crear BD local nueva `sublimation_db`, hashes seed corregidos) — COMPLETADA 2026-08-10
3. ~~Fase 3 — CSRF + sesiones + autorización~~ (COMPLETADA 2026-08-10; `.htaccess` y cookies Secure → Fase 12)
4. ~~Fase 4 — Kardex~~ (COMPLETADA 2026-08-11; 36/36 pruebas)
5. ~~Fase 5 — Compras + Proveedores~~ (COMPLETADA 2026-08-11; 52/52 pruebas)
6. ~~Fase 6 — Devoluciones~~ (COMPLETADA 2026-08-11; 47/47 pruebas)
7. ~~Fase 7 — Caja ampliada~~ (COMPLETADA 2026-08-11; 48/48 pruebas)
8. ~~Fase 8 — Reportes~~ (COMPLETADA 2026-08-11; 58/58 pruebas)
9. Fase 9 — Dashboard (gráficas sin librerías externas)
10. Fase 10 — UI/UX
11. Fase 11 — AWS + HTTPS (EC2/RDS, cookies Secure)
12. Fase 12 — README + CHANGELOG
13. Fase 13 — Pruebas finales

## N. ELEMENTOS NO VERIFICADOS

- **Esquema real de AWS RDS** (`sublimation_db` en producción): NO VERIFICADO — no se conectó a AWS por regla del proyecto.
- **Configuración EC2/RDS** (grupos de seguridad, HTTPS, Apache): NO VERIFICADO.
- **Ejecución funcional del POS** (venta/caja completas): NO PUDE VERIFICAR — la BD local legacy no es compatible con el código; MOTIVO: esquema distinto; ALTERNATIVA: crear BD nueva local en Fase 2 y probar.
- **Login real con las credenciales** del schema: VERIFICADO que falla (hashes inválidos).
- **Apache local**: NO VERIFICADO (no se usó; XAMPP Apache disponible).

---

## O. FASE 3 — CSRF, SESIONES Y AUTORIZACIÓN (COMPLETADA — 2026-08-10)

### Vulnerabilidades corregidas

| ID | Hallazgo | Corrección |
|---|---|---|
| V3 | Ausencia total de CSRF | Token CSRF centralizado en `helpers/security.php` (`csrf_token()`, `verify_csrf_token()`, `require_csrf()`), inyectado como hidden en todos los formularios y `window.CSRF_TOKEN` para AJAX (`footer.php`). Rechazo con HTTP 419. |
| V4 | Fijación de sesión | `session_regenerate_id(true)` inmediatamente después del login válido (`AuthController.php:71`). |
| V5 | Logout CSRF por GET | Logout solo por POST con token CSRF (`header.php`, `index.php`). GET → 405. |
| V6 | IDOR en `ver_productos_venta_ajax` | El cajero solo puede ver ventas de su caja; el resto: 403. Venta inexistente: 404. |
| V7 | `cerrarCaja()` sin defensa en profundidad | Solo permite cerrar cajas propias del usuario autenticado. Caja ajena: 403. |
| V10 | Sin rate-limit en login | Tabla `intentos_login` (migración `database/migrations/001_intentos_login.sql`) + bloqueo de 15 min tras 5 intentos fallidos por IP+usuario (`AuthController.php`). |
| V12 | Cookies sin SameSite/strict mode | `session.cookie_httponly=1`, `use_only_cookies=1`, `use_strict_mode=1`, `cookie_samesite=Lax` y `Secure` solo bajo HTTPS (condicional, `AuthController.php:11-23`). |

### Pendientes (de Fase 1) que NO aplican a esta fase

- **V1** (credenciales AWS en `db.php`): rotación + retiro → Fase 12 (despliegue). Sin cambios.
- **V8** (XSS por patrón `onclick`/`innerHTML`): mitigado de facto; endurecimiento real → Fase 11 (UI).
- **V9** (credenciales de prueba visibles en `login.php`): se mantienen en local para facilitar pruebas; retirar en producción (Fase 12).
- **V11** (`.htaccess`): se hará junto con la configuración de Apache local → Fase 12.
- **V13** (ISV 16% hardcodeado): configuración centralizada → Fase 9/11.
- **V14** (errores en `error_log`): aceptable, sin cambio.

### Archivos modificados

- Nuevos: `helpers/security.php`, `database/migrations/001_intentos_login.sql`.
- Modificados: `index.php`, `controllers/AuthController.php`, `controllers/CajaController.php`,
  `views/login.php`, `views/pos.php`, `views/clientes.php`, `views/inventario.php`,
  `views/usuarios.php`, `views/dashboard.php`, `views/includes/header.php`,
  `views/includes/footer.php`, `assets/js/pos.js`.

### Verificación (pruebas HTTP con servidor embebido PHP)

30/30 pruebas exitosas, entre ellas:

- Login: sin token CSRF rechazado; credenciales incorrectas/inexistentes/usuario desactivado rechazados.
- Sesión: ID regenerado tras login (cookie nueva).
- Logout: POST con token OK; token inválido → 419; GET → 405.
- Caja: apertura/cierre sin token rechazados; con token OK.
- CSRF en edición de producto y anulación de venta: sin token rechazados.
- Roles: cajero → dashboard/usuarios/auditoría = 403; inventario y POS = 200; crear producto = 403.
- IDOR ventas: cajero ve venta de admin = 403; venta propia = 200; admin ve cualquier venta = 200; venta inexistente = 404.
- Rate-limit: bloqueo tras 5 intentos fallidos ("Demasiados intentos").
- IDOR caja: cajero no cierra caja ajena; admin sí.
- Cobro AJAX sin token = 419.

### Estado de la BD tras las pruebas (VERIFICADO)

- `cajas_sesiones`, `ventas`, `detalle_ventas`, `intentos_login` = 0 registros (datos de prueba limpiados).
- Usuarios: `admin` (activo) y `cajero` (reactivado tras la prueba) intactos.
- Productos/categorías/clientes: seed intacto (stocks originales).
