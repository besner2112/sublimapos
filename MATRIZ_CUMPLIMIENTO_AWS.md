# MATRIZ DE CUMPLIMIENTO — RÚBRICA TÉCNICA AWS

> Estado real verificado en la Fase 11.2 (hardening, pruebas y auditoría).
> Leyenda: ✅ Cumplido · ⚠️ Pendiente (requiere acción manual) · ❌ No cumplido

| # | Requisito | Cumplimiento | Evidencia |
|---|---|---|---|
| 1 | URL pública | ✅ | `http://3.145.23.40` responde (302→login; login 200; app completa) |
| 2 | CRUD | ✅ | CREATE/READ/UPDATE/DELETE verificados en todas las entidades — `CRUD_EVIDENCIA.md` |
| 3 | Frontend (HTML/CSS/JS) | ✅ | Capa de presentación `views/` + `assets/` (HTML5/CSS3/JS/Bootstrap 5) servida por Apache/PHP en EC2; separación lógica documentada |
| 3b | Frontend S3/Amplify | ⚠️ NO IMPLEMENTADO | La presentación NO está alojada en S3 ni Amplify (se sirve desde EC2). Migrarla exigiría convertir el backend en API REST (reingeniería importante — 31 PHP, 124 usos de `$_SESSION`, 44 rutas, 385 tags PHP en vistas). `FRONTEND_S3 = PENDIENTE DE MIGRACIÓN` — ver `docs/DIAGRAMA_ARQUITECTURA.md` |
| 4 | Backend (EC2) | ✅ | EC2 `i-0b311e81cd6aac437` (t3.micro, us-east-2) — Apache 2.4.66 + PHP 8.5.4, lógica en `controllers/`, `helpers/` |
| 5 | RDS | ✅ | RDS MySQL 8.4.9 `sublimation_db`; EC2 conecta por TCP 3306; 3306 filtrado desde Internet (verificado) |
| 6 | VPC | ✅ | VPC `vpc-0a2fc4ba0a0a77eb7`; subnet pública `subnet-0f4d1541b680f97d2`; Internet Gateway estándar de VPC default |
| 7 | Security Groups | ⚠️ | SG EC2 `sg-06e7816411cf1414e` (`launch-wizard-1`): 80/443 abiertos, **SSH 22 abierto a Internet — requiere restricción a IP admin en consola**. SG RDS: 3306 solo desde SG EC2 (a confirmar en consola; externamente 3306 filtrado ✓) |
| 8 | Variables de entorno | ✅ | `DB_*` en `/etc/apache2/envvars` (640 root:root); `db.php` usa `envDb()`; sin credenciales en código; `config.local.php` solo local y en `.gitignore` |
| 9 | Repositorio | ⚠️ | Estructura Git preparada (`.gitignore` + README); **sin repositorio remoto conectado** (no se inventa URL) |
| 10 | README | ✅ | `README.md` profesional (proyecto, tecnologías, arquitectura, funcionalidades, seguridad, instalación local/AWS) |
| 11 | Script SQL | ✅ | `schema.sql` + `database/migrations/` (001→004, 6 archivos) para reconstrucción desde cero |
| 12 | Diagrama | ✅ | `docs/DIAGRAMA_ARQUITECTURA.md` (ASCII + datos reales listos para Draw.io) |
| 13 | HTTPS | ⚠️ | Puerto 443 cerrado (SG); requiere **dominio propio** con DNS A → `3.145.23.40` + Certbot; template Apache listo `sublima-ssl.conf` (deshabilitado). No se implementa sin dominio real. `HTTPS = PENDIENTE` |
| 14 | Seguridad | ✅ | PDO prepared statements, CSRF, roles/permisos, IDOR, sesiones HttpOnly/SameSite, bcrypt, fuerza bruta, auditoría, hardening Apache (sin Indexes, rutas internas bloqueadas), UFW activo, credenciales por env |

---

## Notas

1. **Frontend en EC2 (no S3):** la rúbrica lista S3/Amplify como opciones de
   presentación, pero también acepta HTML/CSS/JS. La aplicación usa sesiones
   PHP, CSRF y renderizado server-side: separar el frontend a S3 exigiría
   convertir la lógica en **API REST completa** (CORS, tokens, refactor del
   POS completo) con riesgo alto de romper producción y sin beneficio
   académico. La capa de presentación existe y está separada lógicamente
   (`views/` + `assets/`). Documentado en `docs/DIAGRAMA_ARQUITECTURA.md`.

2. **SSH (22):** abierto a Internet (`0.0.0.0/0` probablemente) en el SG
   `launch-wizard-1`. **Acción manual en consola AWS:** restringir a la IP
   administrativa actual. NO se cierra desde el servidor porque no hay
   acceso administrativo alternativo garantizado.

3. **RDS Publicly Accessible:** el endpoint RDS tiene IP pública asignada,
   pero el puerto 3306 está **filtrado desde Internet** (verificado con
   sondeo externo: sin respuesta). Acción recomendada en consola:
   *Publicly Accessible = No* y SG con 3306 solo desde `sg-06e7816411cf1414e`.

4. **Repositorio remoto:** no existe URL configurada y no se inventa. Cuando
   el propietario cree el repositorio (GitHub/GitLab):
   `git remote add origin <url>`.

---

*Actualizada: 2026-08-12 (Fase 11.2).*
