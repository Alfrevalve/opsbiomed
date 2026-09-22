# Linea base de rendimiento local

Fecha: 2026-09-20. PHP 8.3.33, MySQL 8.4.3 local, datos sinteticos del piloto, `artisan serve`, solicitudes autenticadas y calentadas. Son muestras simples de una ejecucion, no percentiles ni carga concurrente.

| Ruta | HTTP | Tiempo observado |
|---|---:|---:|
| `/dashboard/ops` | 200 | 467 ms |
| `/inventory` | 200 | 435 ms |
| `/inventory/coverage` | 200 | 442 ms |
| `/inventory/forecast` | 200 | 494 ms |
| `/schedule` | 200 | 896 ms |
| `/alerts` | 200 | 396 ms |
| `/reports` | 200 | 292 ms |
| `/cases/1/close` | 200 | 546 ms |

Release-B `/up`, `/login` y lectura de marcador respondieron en 638 ms en conjunto. Rollback selector local hasta dashboard A: 12.864 s.

## Interpretacion

- Medicion valida solo como referencia local del servidor PHP development; no representa Apache, hardware de hosting ni carga real.
- No se obtuvo medicion de importacion de 500 filas, export CSV, POST de cierre, consultas SQL por endpoint ni memoria. No se aplicaron optimizaciones por falta de medicion.
- No se declara ausencia de N+1. Antes del piloto real, perfilar queries con volumen representativo, ejecutar pruebas concurrentes de carga y registrar cantidad de filas y memoria.
