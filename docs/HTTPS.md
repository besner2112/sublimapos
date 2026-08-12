# HTTPS — ESTADO Y REQUISITOS (Fase 11.2)

## Estado actual

- Puerto 443: **cerrado** (Security Group de EC2 no lo permite).
- Puerto 80: funcionando (`http://3.145.23.40`).
- No existe dominio propio.

## Qué se necesita para HTTPS correcto

1. **Un dominio propio** (ej. `tienda-midominio.com`).
2. **DNS:** registro `A` del dominio → `3.145.23.40`.
3. **Consola AWS:** abrir el puerto `443` en el SG `sg-06e7816411cf1414e`.
4. **Certificado Let's Encrypt** (gratuito y válido):

   ```bash
   sudo apt install -y certbot python3-certbot-apache
   sudo certbot --apache -d <dominio-real>
   ```

   Certbot configura automáticamente el VirtualHost 443 y la redirección
   HTTP→HTTPS.
5. **Template listo:** `/etc/apache2/sites-available/sublima-ssl.conf`
   (creado, **deshabilitado a propósito**): sustituir `TIENDA.EJEMPLO.COM`
   por el dominio real, habilitar (`a2ensite sublima-ssl`) y recargar.

## Por qué NO se implementa ahora

- No se debe inventar un dominio.
- Let's Encrypt **no emite** certificados para IPs públicas ni para
  hostnames `*.compute.amazonaws.com` (el hostname AWS de esta instancia
  no es apto para ACME sin control DNS propio).
- Un certificado autofirmado generaría avisos de seguridad en el navegador
  (HTTPS "incorrecto"), por lo que no se instala.

## Orden de activación (cuando exista el dominio)

1. Crear dominio + DNS A → `3.145.23.40` (TTL bajo).
2. Abrir 443 en el SG (consola AWS).
3. `sudo a2enmod ssl && sudo systemctl reload apache2`
4. `sudo certbot --apache -d <dominio>`
5. Probar: `https://<dominio>/` + redirección automática desde http.
6. Renovación automática la gestiona Certbot (`certbot renew --dry-run`).
