---
title: "Planilla de gastos compartidos: cómo armarla"
description: "Cómo armar una planilla de gastos compartidos en Excel o Google Sheets, con columnas y fórmulas listas, y cuándo deja de alcanzar."
published: "2026-10-17"
author: "Martina Olivera"
image: "/images/blog/planilla-gastos-compartidos.png"
---

Una planilla es la primera herramienta que casi todos usan para compartir gastos, y con razón: es gratis, todos saben usarla y se adapta a cualquier grupo. Esta guía te muestra cómo armar una que funcione en Excel o Google Sheets, con las columnas y fórmulas justas, y te cuenta con honestidad en qué momento empieza a fallar.

## Las columnas que necesitas

Una planilla de gastos compartidos funciona mejor como una lista de movimientos, uno por fila, que como una tabla por meses. Así puedes filtrar, sumar y agregar filas sin rediseñar nada.

| Fecha | Descripción | Tipo | Monto | Moneda | Tipo de cambio | Pagó | Etiqueta |
|---|---|---|---|---|---|---|---|
| 2026-10-01 | Alquiler | Gasto | 25000 | UYU | 40,10 | Ana | depto |
| 2026-10-02 | Súper | Gasto | 3200 | UYU | 40,15 | Leo | comida |
| 2026-10-03 | Aporte octubre | Ingreso | 300 | USD | 1 | Leo | fondo |

- **Tipo:** "Ingreso" o "Gasto". Un aporte al fondo común es un ingreso; una compra es un gasto.
- **Moneda y tipo de cambio:** aunque hoy uses una sola moneda, estas dos columnas te ahorran rehacer la planilla el día que aparezca otra. El tipo de cambio es cuántas unidades de la moneda compra un dólar ese día.
- **Pagó:** quién puso la plata o pagó el gasto.
- **Etiqueta:** para qué fue. Te permite ver cuánto se va en "comida" o en "viaje" sin crear una hoja por tema.

## Las fórmulas justas

Con los movimientos en las columnas A a H, estas fórmulas cubren lo que casi todos los grupos quieren saber (en Google Sheets en español; en Excel en inglés, `SUMAR.SI.CONJUNTO` es `SUMIFS`):

**Saldo en una moneda**, por ejemplo pesos uruguayos:

```
=SUMAR.SI.CONJUNTO(D:D; E:E; "UYU"; C:C; "Ingreso") - SUMAR.SI.CONJUNTO(D:D; E:E; "UYU"; C:C; "Gasto")
```

Repite la fórmula para cada moneda. No sumes pesos y dólares en una sola celda: ese número no existe en ningún lado.

**Cuánto pagó cada persona**, en una moneda:

```
=SUMAR.SI.CONJUNTO(D:D; G:G; "Ana"; E:E; "UYU"; C:C; "Gasto")
```

**Cuánto se fue en una etiqueta**:

```
=SUMAR.SI.CONJUNTO(D:D; H:H; "comida"; C:C; "Gasto")
```

**Un gasto en dólares, al tipo de cambio de su día**: si necesitas un total aproximado en una moneda, agrega una columna con `=D2/F2` para cada fila de pesos. Así cada movimiento usa la cotización que tenía cuando ocurrió, no la de hoy.

## Reglas para que la planilla no se desarme

- **Una fila por movimiento, siempre.** Nada de celdas con "varios" o sumas hechas a mano.
- **Fechas en formato de fecha**, no como texto, para poder filtrar por mes.
- **Listas desplegables** en Tipo, Moneda y Pagó (Datos → Validación de datos), para que nadie escriba "gasto", "Gastos" y "GASTO".
- **No borres filas viejas.** Si algo estuvo mal, corrígelo o agrega un movimiento que lo compense.

## Cuándo una planilla se queda corta

Para dos personas, una moneda y gastos simples, una planilla bien armada alcanza. Los problemas aparecen cuando el grupo crece o el dinero se complica:

- **Varias personas editando.** Alguien borra una fórmula, otra persona pega encima de una fila, y nadie sabe qué cambió ni quién.
- **Varias monedas.** Cada fórmula se multiplica por moneda, y el tipo de cambio de cada día hay que buscarlo y escribirlo a mano.
- **Registrar desde el celular.** Anotar un gasto en la vereda, en una planilla, es incómodo, así que se deja para después, y después no se anota.
- **Saber quién registró qué.** Una planilla guarda el historial de versiones, pero no te dice de un vistazo quién cargó cada movimiento.

Si reconoces varios de estos, probablemente ya no necesitas una planilla mejor sino otra herramienta.

## Preguntas frecuentes

### ¿Cómo divido los gastos en proporción a los ingresos?

Agrega una fila de parámetros con el porcentaje de cada persona (por ejemplo, 60 % y 40 %) y multiplica el total de gastos por ese porcentaje. Así ves cuánto debería aportar cada uno.

### ¿Pueden editar varias personas a la vez?

En Google Sheets sí, y es su mayor ventaja sobre Excel. Protege las columnas con fórmulas (Datos → Hojas y rangos protegidos) para que nadie las borre por accidente.

### ¿Cómo manejo pesos y dólares en la misma planilla?

Con las columnas Moneda y Tipo de cambio de esta guía, y un saldo por moneda. Si tu grupo usa dos monedas todo el tiempo, lee [cómo llevar el dinero compartido en pesos y dólares](/es/blog/dinero-compartido-en-dos-monedas). Y si juntan plata por adelantado, [cómo llevar un fondo común](/es/blog/fondo-comun).

## Cuando la planilla ya no alcanza: Contamos

[Contamos](/es) es un libro compartido que hace lo mismo que la planilla de arriba, pero sin fórmulas que mantener:

- cada movimiento tiene fecha, cuenta, [categoría y etiquetas](/es/docs/tags), y muestra quién lo registró;
- cada cuenta tiene [un saldo por moneda](/es/docs/accounts-and-currencies), sin mezclar pesos y dólares;
- cada movimiento guarda [el tipo de cambio de su día](/es/docs/exchange-rates) automáticamente, y puedes escribir el que realmente te dieron;
- y si algún día quieres volver a una planilla, puedes [exportar todo a CSV](/es/docs/transactions-and-transfers), con las mismas columnas de esta guía.

Empezar es gratis, y la [guía rápida](/es/docs/quickstart) te lleva a tu primer movimiento en pocos minutos.
