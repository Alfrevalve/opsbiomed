# Simulacion de release y rollback local

Fecha: 2026-09-20. Artefactos aislados en `C:\laragon\www\ops-biomed-release-rehearsal\`; no se sobrescribio el proyecto principal.

## Releases

- RELEASE-A: archivo de `git archive HEAD`, commit `10a43743`; Composer instaló 125 paquetes, package discovery correcto, `npm ci` instalo 94 paquetes y `npm run build` correcto.
- RELEASE-B: copia separada de A con `public/release-marker.txt` inocuo. `php artisan test --compact`: 245 tests / 1,536 assertions.
- B se selecciono mediante `active-release.txt`; HTTP al proceso B devolvio marcador correcto, `/up` 200 y `/login` 200. Probe completo: 638 ms.

## Rollback

Se simulo health check fallido contra puerto local cerrado, se selecciono RELEASE-A y se verifico marcador A, login y dashboard autenticado con HTTP 200. Se conservaron DB y archivos centinela; hash storage `88067bebfd5db50402a74704394d24551632ced9c10425b09cf1f77ad8ddcadb`. Tiempo observado desde deteccion hasta dashboard A: 12.864 s.

## Limitaciones

- En Windows se uso un archivo selector y puertos `artisan serve`; no se probo symlink atomico, Apache/Laragon, reinicio de servicios, permisos Linux, opcache ni comportamiento de un vhost.
- Las releases A/B documentan el ensayo histórico sobre `10a43743`. El release candidate actual `3499aac7` ya consolida las 38 migraciones y pasó instalación limpia, Composer package discovery, `npm ci`, Vite y la suite de 277/1,785 tests. El rollback de hosting real continúa pendiente.
- La prueba confirma el procedimiento simulado local, no rollback del servidor remoto ni RTO de hosting.
