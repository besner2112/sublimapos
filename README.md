# Sublima POS — Sistema de Gestión de Inventario y Ventas

Sistema web transaccional para la gestión de artículos de sublimación:
**ventas (POS), inventario, compras, proveedores, clientes, devoluciones,
caja, reportes, Kardex y auditoría**, desplegado en **Amazon Web Services**
con arquitectura de 3 capas.

- **Producción (AWS):** `http://3.145.23.40`
- **Stack:** Apache 2.4 + PHP 8.5 (nativo, MVC) + MySQL 8.4 (Amazon RDS)

---

## Tecnologías

| Capa | Tecnología |
|---|---|
| Presentación | HTML5, CSS3, JavaScript, Bootstrap 5, Bootstrap Icons |
| Lógica | PHP 8.5 (nativo, MVC, PDO), Apache 2.4.66 |
| Datos | MySQL 8.4 (Amazon RDS), PDO prepared statements |
| Infraestructura | AWS EC2, AWS RDS, VPC, Security Groups, UFW |

---

## Arquitectura (3 capas)

```
            Usuario
               |
      HTTP (80) / HTTPS (443 pendiente)
               |
               v
   +---------------------------+
   |  CAPA PRESENTACIÓN        |   HTML / CSS / JS
   |  Servida por EC2+Apache   |   (views/ + assets/)
   +---------------------------+
               |
               v
   +---------------------------+
   |  CAPA LÓGICA              |   EC2 i-0b311e81cd6aac437
   |  Apache + PHP 8.5 (MVC)   |   controllers/, helpers/
   +---------------------------+
               |
         TCP 3306 (privado)
               v
   +---------------------------+
   |  CAPA DATOS               |   RDS apolo.c1soywykm4vn
   |  MySQL 8.4 sublimation_db |   .us-east-2.rds.amazonaws.com
   +---------------------------+
```

**Distribución real (verificada, no inventada):**

- **Presentación:** `views/` + `assets/` (HTML/CSS/JS/Bootstrap), separada
  lógicamente, servida por Apache/PHP en EC2. No se migra a S3/Amplify
  porque la aplicación usa **sesiones PHP, CSRF y renderizado server-side**;
  separar el frontend exigiría convertir toda la lógica en API REST (riesgo
  alto de romper producción, sin beneficio funcional). La capa de
  presentación cumple la rúbrica (HTML/CSS/JS). Documentado en
  `docs/DIAGRAMA_ARQUITECTURA.md` y `MATRIZ_CUMPLIMIENTO_AWS.md`.
- **Lógica:** EC2 (`t3.micro`, us-east-2, VPC `vpc-0a2fc4ba0a0a77eb7`,
  subnet `subnet-0f4d1541b680f97d2`), Apache 2.4.66, PHP 8.5.4.
- **Datos:** RDS MySQL 8.4.9 (`sublimation_db`), conectado solo desde EC2
  por `TCP 3306`; **no accesible desde Internet** (verificado: puerto 3306
  filtrado externamente).

---

## Funcionalidades

- **Login seguro:** sesión PHP, CSRF, hash bcrypt, bloqueo de fuerza
  bruta (`intentos_login`).
- **Roles:** `Administrador` y `Cajero` con permisos diferenciados.
- **POS:** ventas por código/búsqueda, carrito, efectivo/tarjeta, cambio,
  apertura/cierre de caja, arqueo, ingresos/retiros, devoluciones.
- **Inventario:** productos, categorías, stock, **Kardex**
  (`movimientos_inventario`), alertas de stock crítico.
- **Proveedores y Compras:** CRUD; compra en borrador y confirmación que
  actualiza stock + Kardex (transaccional).
- **Clientes:** CRUD.
- **Dashboard:** indicadores por rol (global administrador / propio cajero).
- **Reportes:** ventas, productos, inventario/Kardex, caja, devoluciones.
- **Auditoría:** registro de eventos (`auditoria_logs`) y accesos denegados.

---

## Seguridad

- **SQL Injection:** PDO con prepared statements en toda la aplicación
  (verificado).
- **CSRF:** token por sesión en formularios y cabecera `X-CSRF-Token` en
  AJAX.
- **Roles y permisos:** `requireRole()` en rutas y vistas; cajero bloqueado
  en módulos administrativos (HTTP 403).
- **IDOR:** ventas y devoluciones validadas contra el usuario de sesión.
- **Sesiones:** `HttpOnly`, `SameSite=Lax`.
- **Credenciales:** variables de entorno del servidor
  (`/etc/apache2/envvars`, modo 640); `conexion/db.php` usa `envDb()`
  sin contraseñas en el código; `config.local.php` solo local (ignorado
  por Git).
- **Red (AWS):** Security Groups + UFW (solo 22/80/443); RDS sin acceso
  público (3306 filtrado).
- **Apache (Fase 11.2):** sin `Indexes` (no hay listado de directorios),
  bloqueados `/database`, `/helpers`, `/conexion`, `/docs`, archivos
  `.sql`/`.md`, dotfiles (`.env`, `.git`). Los assets CSS/JS no se ven
  afectados.
- **HTTPS:** pendiente de dominio propio (ver `docs/HTTPS.md`).

---

## Instalación local (XAMPP)

1. Copiar el proyecto a `C:\xampp\htdocs\SISTEMA_DE_VENTAS`.
2. Crear la base de datos en phpMyAdmin/MySQL ejecutando **`schema.sql`**
   y después las migraciones en orden:
   `database/migrations/001_intentos_login.sql`,
   `database/migrations/001_kardex.sql`,
   `database/migrations/002_compras.sql`,
   `database/migrations/003_devoluciones.sql`,
   `database/migrations/004_caja_ampliada.sql`.
3. Crear `config.local.php` (solo local, no se sube):

   ```php
   <?php
   putenv('DB_HOST=127.0.0.1');
   putenv('DB_NAME=sublimation_db');
   putenv('DB_USER=root');
   putenv('DB_PASS='); // XAMPP por defecto: vacia
   ```

4. Iniciar Apache/MySQL en XAMPP y abrir
   `http://localhost/SISTEMA_DE_VENTAS`.
5. Usuarios iniciales (seed en `schema.sql`): `admin` / `admin123`,
   `cajero` / `cajero123`.

---

## Instalación AWS (EC2 + RDS)

1. **EC2:** instancia Ubuntu (t3.micro, us-east-2) con Apache y PHP 8.5:

   ```bash
   sudo apt update && sudo apt install -y apache2 php libapache2-mod-php php-mysql
   ```

2. **RDS:** instancia MySQL 8.4 (`sublimation_db`), Security Group que
   permita `TCP 3306` **solo desde el SG de la EC2** (nunca `0.0.0.0/0`),
   *Publicly Accessible = No*.
3. **Código:** copiar el proyecto a `/var/www/html` con propietario
   `www-data:www-data` (directorios 755, archivos 644).
4. **Variables de entorno** (en el servidor, nunca en el repo) —
   `/etc/apache2/envvars` (modo 640):

   ```bash
   export DB_HOST=<endpoint-rds>
   export DB_NAME=sublimation_db
   export DB_USER=<usuario-rds>
   export DB_PASS=<password-rds>
   ```

   `sudo systemctl restart apache2`
5. **Base de datos:** ejecutar `schema.sql` + migraciones en RDS.
6. **Firewall:** `sudo ufw allow 22/tcp && sudo ufw allow 80/tcp && sudo ufw allow 443/tcp && sudo ufw --force enable`
7. Abrir `http://<IP-publica>/`.

> ⚠️ Nunca versionar credenciales: variables de entorno + `.gitignore`.

---

## Script de base de datos (reconstrucción desde cero)

| Orden | Archivo | Contenido |
|---|---|---|
| 1 | `schema.sql` | Esquema inicial completo (tablas, claves, índices, seeds) |
| 2 | `database/migrations/001_intentos_login.sql` | Protección fuerza bruta |
| 3 | `database/migrations/001_kardex.sql` | Kardex de inventario |
| 4 | `database/migrations/002_compras.sql` | Módulo de compras |
| 5 | `database/migrations/003_devoluciones.sql` | Devoluciones |
| 6 | `database/migrations/004_caja_ampliada.sql` | Caja ampliada |

Ejecutar en ese orden sobre un MySQL vacío para reconstruir la BD completa.

---

## Pruebas (evidencia)

- Batería funcional Fase 11 sobre producción: login, roles, POS, compras,
  devoluciones, caja, reportes, dashboard, IDOR, CSRF, SQLi — aprobadas.
- Auditoría 11.1: infraestructura, credenciales, permisos, bots, backups.
- Hardening 11.2: listado de directorios desactivado, rutas internas
  bloqueadas, UFW activo, permisos de secretos, verificación end-to-end.
- Detalle completo: `AUDITORIA_PROGRESO.md`, `CHANGELOG.md`,
  `CRUD_EVIDENCIA.md`, `MATRIZ_CUMPLIMIENTO_AWS.md`.

---

## Documentación del proyecto

| Archivo | Contenido |
|---|---|
| `README.md` | Este documento |
| `AUDITORIA_PROGRESO.md` | Memoria técnica por fases (Fase 1 → 13) |
| `CHANGELOG.md` | Historial de versiones |
| `CRUD_EVIDENCIA.md` | Evidencia CREATE/READ/UPDATE/DELETE por entidad |
| `MATRIZ_CUMPLIMIENTO_AWS.md` | Rúbrica: cumplimiento vs requisitos AWS |
| `docs/DIAGRAMA_ARQUITECTURA.md` | Diagrama de arquitectura (datos para Draw.io) |
| `docs/HTTPS.md` | Requisitos y pasos para habilitar HTTPS |

---

## Variables de entorno

| Variable | Dónde se define | Uso |
|---|---|---|
| `DB_HOST` | `envvars` (producción) / `config.local.php` (local) | Endpoint RDS o `127.0.0.1` |
| `DB_NAME` | ídem | `sublimation_db` |
| `DB_USER` | ídem | Usuario MySQL |
| `DB_PASS` | `envvars` (producción) / `config.local.php` (local) | Contraseña — **nunca** en código ni en el repo |

En producción las variables se exportan en `/etc/apache2/envvars` (modo 640
root:root, no legible por el usuario web). `conexion/db.php` las lee con
`envDb()` y usa valores por defecto solo para host/nombre/usuario (sin
contraseña por defecto). `config.local.php` está en `.gitignore`.

## Estructura del proyecto

```text
SISTEMA_DE_VENTAS/
├── index.php              # Front controller / enrutador (rutas + AJAX)
├── conexion/
│   └── db.php             # PDO + envDb() (sin credenciales en código)
├── controllers/           # Lógica por módulo (Auth, Venta, Inventario,
│                          #   Compra, Devolucion, Caja, Usuario, Cliente,
│                          #   Proveedor, Reporte, Auditoria)
├── helpers/               # Servicios (Compra, Devolucion, Inventario,
│                          #   security.php: CSRF, roles, saneado)
├── views/                 # Presentación (HTML + PHP embebido)
│   ├── includes/          # Header/sidebar/footer
│   ├── login.php, pos.php, inventario.php, clientes.php,
│   │   proveedores.php, compras.php, devoluciones.php,
│   │   dashboard.php, reportes.php, usuarios.php, auditoria.php
├── assets/                # CSS + JS (frontend estático)
├── database/
│   └── migrations/        # Migraciones SQL (001 → 004)
├── schema.sql             # Esquema inicial completo
├── config.local.php       # SOLO LOCAL (ignorado por Git)
├── docs/                  # Diagrama de arquitectura, HTTPS
├── README.md / CHANGELOG.md / AUDITORIA_PROGRESO.md
├── CRUD_EVIDENCIA.md / MATRIZ_CUMPLIMIENTO_AWS.md
└── .gitignore
```

## Estado actual (Fase final AWS)

- ✅ Despliegue AWS funcional (EC2 + RDS), UFW activo (22/80/443), hardening
  Apache (sin listados, rutas internas bloqueadas), permisos de secretos
  (envvars 640, backups 600), documentación y matriz de cumplimiento.
- ⚠️ Pendientes que dependen de la **consola AWS / dominio** (no ejecutables
  desde el servidor):
  1. Restringir SSH (22) a la IP administrativa en el Security Group de EC2.
  2. Confirmar RDS *Publicly Accessible = No* y SG RDS con 3306 solo desde
     el SG de EC2.
  3. **HTTPS**: requiere dominio propio con DNS A → `3.145.23.40`
     (template Apache listo en `sites-available/sublima-ssl.conf`).
  4. Conectar repositorio remoto (GitHub/GitLab) — estructura Git
     preparada, sin URL remota configurada.
- ⚠️ **Frontend S3/Amplify: NO implementado** (decisión documentada en
  `docs/DIAGRAMA_ARQUITECTURA.md`): la capa de presentación actual
  (HTML/CSS/JS) se sirve desde EC2+Apache; migrarla a S3 requiere convertir
  el backend en API REST completa (reingeniería importante). Ver
  `FRONTEND_S3 = PENDIENTE DE MIGRACIÓN`.

---

*Proyecto académico — despliegue AWS EC2 + RDS. Sin credenciales en el repo.*
