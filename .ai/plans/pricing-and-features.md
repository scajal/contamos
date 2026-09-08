# Modelo de precios y roadmap de funcionalidades

Estado: propuesta, sin implementar. Conversación del 2026-09-07.

## Diagnóstico del modelo actual

Hoy `Plan` (Free, Pro) y `Feature` viven en `app/Modules/Billing/Enums`. Las tres
features existentes son cuotas (cuentas, miembros, transacciones al mes) con límite en
gratis y sin límite en Pro. No hay ninguna funcionalidad exclusiva de un plan, así que
Pro es "lo mismo pero sin techo".

Problemas:

- El límite de 100 transacciones/mes castiga el hábito que queremos crear. Quien
  registra todo es el mejor candidato a Pro y le cortamos antes de que vea el valor.
- Con 1 miembro en gratis no se puede probar la promesa de la marca ("grupo que
  comparte dinero") antes de pagar.
- Sin nada exclusivo, el único argumento de venta de Pro es "quitar molestias".

## Principio

El gratis vende el hábito (registrar y ver el presente). Pro vende el entendimiento
(analizar, planificar, anticipar, conectar). Los límites quedan como red de seguridad
de costes, no como palanca de venta.

## Reparto propuesto

| Capacidad | Gratis | Pro |
| --- | --- | --- |
| Transacciones | Ilimitadas | Ilimitadas |
| Miembros por grupo | 2 | Ilimitados |
| Cuentas | 3 | Ilimitadas |
| Grupos | 1 | Varios |
| Reportes | Resumen del mes en curso | Histórico, comparativas, exportar CSV/PDF |
| Presupuestos | 1 presupuesto mensual simple | Ilimitados + proyectos (viaje, boda, mudanza) |
| Alertas | 1 alerta | Ilimitadas, por categoría, miembro o proyecto |
| Servidor MCP | No | Sí, con rate limit |
| Transacciones recurrentes | No | Sí |
| Adjuntos (tickets, facturas) | No | Sí |
| Monedas del catálogo por equipo | Hasta 2 | Ilimitadas |
| Monedas custom del equipo | No | Sí, con cotización manual |
| Cotización automática (live) | Solo moneda principal | Todas las del catálogo |

Razones clave:

- Transacciones ilimitadas en gratis: coste marginal casi cero y alimentan todo lo demás.
- 2 miembros en gratis: la pareja es el caso núcleo. Pro se vende cuando el grupo crece
  o se quieren varios grupos.
- Una muestra de cada feature Pro en gratis (1 presupuesto, 1 alerta). "Quiero otra"
  es mejor momento de upgrade que un muro.
- Los reportes limitan el alcance de la vista, nunca los datos. No borrar ni esconder
  historial en gratis.
- MCP solo en Pro: power user, coste real por petición, buen gancho para nicho técnico.
- 2 monedas en gratis: "cobro en una y gasto en otra" es demasiado común en LatAm y
  España. La cotización live es el coste real y se limita por plan.

## Planes futuros

No añadir un tercer plan hasta tener señal de uso. Candidatos:

1. **Plus** entre gratis y Pro, si mucha gente quiere solo presupuestos o reportes.
   Riesgo: canibaliza Pro.
2. **Familia / Grupos** por encima de Pro, si el crecimiento viene de grupos grandes:
   varios grupos, roles (quién ve qué), precio por grupo. Más probable dado el
   posicionamiento.

## Implementación del modelo de planes

- Separar en `Feature` dos tipos: **puertas** (booleano por plan: reportes, MCP,
  adjuntos) y **cuotas** (entero o null por plan, lo que ya existe).
- `Plan::features()` debe devolver la lista real de cada plan (hoy devuelve la misma
  para todos) y `enabled()` consultar esa lista antes de mirar cuotas.
- Guardar el plan con fecha para respetar condiciones antiguas a quien ya paga cuando
  cambien límites o precios.
- Cuotas en config o enum; puertas en el enum (decisión de producto, no de entorno).

## Monedas por equipo

Son dos features distintas:

1. **Elegir monedas del catálogo global.** Cada equipo activa las que usa. Completar
   antes el catálogo con ISO 4217 (es un seed, no una feature).
2. **Crear monedas propias del equipo.** Puntos, millas, fondos en unidades, monedas
   que no están en el catálogo. Pertenecen al equipo y nadie más las ve.

El catálogo ya tiene `CurrencyType::Virtual` y `QuoteSource::ManualOrScheduled`, así
que el modelo conceptual está; falta el dueño.

Modelo de datos:

- `team_id` nullable en `currencies`. Null = catálogo global, con valor = custom del
  equipo. Mismo modelo, cotizaciones y conversión para ambas.
- Pivote `currency_team` para "activadas por el equipo", con marca de moneda principal.
- El `code` deja de ser único global y pasa a único por equipo. Revisar el unique actual.
- Las custom nacen con `quote_source` manual y `type` virtual o fiat según elija el
  usuario. Nunca live.
- Global scope por equipo en las consultas de moneda, igual que cuentas y transacciones.

Decisión pendiente antes de construir: cómo se convierte una moneda custom a la
principal del equipo. Sin cotización no entra en totales ni reportes (mostrar aparte).
Con cotización manual, decidir quién la actualiza y cada cuánto. Condiciona reportes y
presupuestos.
