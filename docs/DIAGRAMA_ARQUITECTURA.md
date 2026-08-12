# DIAGRAMA DE ARQUITECTURA — Sublima POS (Datos para Draw.io)

> Arquitectura **REAL** verificada (Fase 11.2, 2026-08-12).
> Frontend servido por EC2+Apache (no S3) — decisión documentada al final.

---

## 1. Vista general (ASCII)

```
                    INTERNET
                       |
                 HTTP / HTTPS (443 pendiente)
                       |
                       v
        +-------------------------------------+
        |          AWS VPC                     |
        |  vpc-0a2fc4ba0a0a77eb7 (default)    |
        |                                     |
        |  +---------------------------+     |
        |  | SUBNET PÚBLICA             |     |
        |  | subnet-0f4d1541b680f97d2   |     |
        |  |                           |     |
        |  | +-----------------------+ |     |
        |  | | EC2 t3.micro          | |     |
        |  | | i-0b311e81cd6aac437   | |     |
        |  | | us-east-2             | |     |
        |  | | Apache 2.4 + PHP 8.5  | |     |
        |  | | +-------------------+ | |     |
        |  | | | PRESENTACIÓN      | | |     |
        |  | | | views+assets      | | |     |
        |  | | | (HTML/CSS/JS)     | | |     |
        |  | | +-------------------+ | |     |
        |  | | | LÓGICA (MVC)      | | |     |
        |  | | | controllers/      | | |     |
        |  | | +-------------------+ | |     |
        |  | +-----------------------+ |     |
        |  |   | TCP 3306               |     |
        |  +---|-----------------------+     |
        |      |                             |
        |      v                             |
        |  +-----------------------+         |
        |  | RDS MySQL 8.4          |         |
        |  | apolo.c1soywykm4vn...  |         |
        |  | sublimation_db         |         |
        |  | SG RDS: 3306 SOLO      |         |
        |  | desde SG EC2           |         |
        |  +-----------------------+         |
        +-------------------------------------+
```

---

## 2. Componentes y datos reales

| Componente | Valor real | Notas |
|---|---|---|
| Usuario | Navegador | Accede por `http://3.145.23.40` |
| EC2 | `i-0b311e81cd6aac437`, `t3.micro`, us-east-2 | Ubuntu 26.04 |
| Apache | 2.4.66 (Ubuntu) | Docroot `/var/www/html`; sin `Indexes`; conf `hardening.conf` |
| PHP | 8.5.4 (mod_php) | MVC nativo; PDO |
| RDS | MySQL 8.4.9 | BD `sublimation_db`, endpoint `apolo.c1soywykm4vn.us-east-2.rds.amazonaws.com` |
| VPC | `vpc-0a2fc4ba0a0a77eb7` | VPC default de la cuenta |
| Subnet | `subnet-0f4d1541b680f97d2` | Pública (con IGW estándar) |
| SG EC2 | `sg-06e7816411cf1414e` (`launch-wizard-1`) | 22 SSH (restringir a IP admin), 80, 443 — en consola |
| SG RDS | (creado con la instancia RDS) | 3306 solo desde `sg-06e7816411cf1414e` — confirmar en consola |
| Internet Gateway | IGW de la VPC default | Salida de la EC2 |
| Firewall OS | UFW activo | Inbound: 22/80/443 únicamente |

## 3. Flujo de datos

1. Usuario → `HTTP GET/POST` → EC2 (SG: 80; UFW: 80).
2. Apache/PHP (capa lógica) renderiza vistas (capa presentación) y procesa
   las rutas MVC (`index.php?route=...`).
3. PHP → PDO → `TCP 3306` (outbound permitido) → RDS.
4. RDS responde; PHP genera HTML/JSON → usuario.

## 4. Puertos

| Puerto | Origen → Destino | Estado |
|---|---|---|
| 80 | Internet → EC2 | ABIERTO (SG + UFW) |
| 443 | Internet → EC2 | CERRADO (SG) — pendiente dominio |
| 22 | IP admin → EC2 | ABIERTO a Internet — RESTRINGIR en consola |
| 3306 | EC2 → RDS | Solo interno (filtrado externamente) ✓ |

## 5. Decisiones de arquitectura

- **Frontend NO en S3/Amplify:** la app usa sesiones PHP, CSRF y renderizado
  server-side. Migrar el frontend a S3 exigiría convertir la lógica en API
  REST (CORS, tokens, refactor total del POS), riesgo alto de romper
  producción sin beneficio académico (la rúbrica acepta HTML/CSS/JS como
  presentación). La capa de presentación está separada lógicamente
  (`views/` + `assets/`).
- **Diagrama alternativo (solo si el docente exige S3):** S3 (bucket
  estático con `views/assets`) + CloudFront + API REST en EC2
  (`/api/*`) + CORS + Auth por token. Costo alto de reingeniería; no se
  implementa sin autorización.

### Arquitectura ACTUAL (funcional en producción)

```
Usuario
   │  HTTP 80
   ▼
EC2 / Apache / PHP  (presentación + lógica)
   │  TCP 3306 (privado)
   ▼
RDS MySQL
```

### Arquitectura OBJETIVO (S3 — requiere reingeniería)

```
Usuario
   │  HTTPS
   ▼
S3 (Static Website) / CloudFront   ← HTML/CSS/JS estáticos
   │  HTTPS (API REST, CORS)
   ▼
EC2 / Apache / PHP (API REST /api/*)   ← sesiones→tokens, JSON
   │  TCP 3306 (privado)
   ▼
RDS MySQL
```

La segunda opción exige **separar el frontend PHP del backend**: convertir
las 44 rutas `index.php?route=...` en endpoints JSON, reemplazar las 124
lecturas de `$_SESSION` por autenticación por token (JWT/session API),
adaptar 63 llamadas `fetch()` para CORS, migrar los 19 formularios POST a
consumo AJAX/JSON y reconstruir las 11 vistas (385 tags PHP) en HTML
estático con JS. Reingeniería importante: **no se realiza en esta fase**.

## 6. Instrucciones Draw.io

1. Abrir [draw.io](https://app.diagrams.net) → "Insertar" → "Diagrama
   avanzado" o dibujar manualmente.
2. Cajas: `Internet`, `AWS VPC` (contenedor), `Subnet pública`, `EC2`,
   `RDS`, `Internet Gateway`, `Security Group EC2`, `Security Group RDS`.
3. Flechas: Usuario→EC2 (`HTTP 80`), EC2→RDS (`TCP 3306`), IGW→Internet.
4. Iconos de la librería AWS (draw.io tiene shapes de AWS) para EC2, RDS,
   VPC, IGW, SG.
