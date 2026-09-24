---
title: "Tipos de cambio"
description: "Cómo cada movimiento guarda el tipo de cambio de su día, de dónde sale la cotización y cuándo y cómo escribir la tuya."
pro: false
---

En Contamos el dinero se queda en la moneda en que se registró. Los saldos y los reportes nunca se convierten a una sola moneda, así que un equipo que vive en pesos y en dólares ve cuántos pesos y cuántos dólares tiene. Lo que suman los tipos de cambio es un registro de cuánto valía cada movimiento el día en que ocurrió.

## Un tipo de cambio por movimiento

Cada movimiento guarda su propio tipo de cambio, incluidos cada lado de una transferencia y cada saldo inicial. El tipo de cambio se escribe como cuánto de la moneda compra un dólar, por ejemplo **1 dólar = 40,12 UYU**.

Como el tipo de cambio se guarda con el movimiento, nunca cambia después, aunque el mercado se mueva. El detalle del movimiento muestra su tipo de cambio y su valor aproximado en dólares, y la exportación a CSV incluye el tipo de cambio de cada movimiento.

## De dónde sale la cotización

Contamos lleva un catálogo de cotizaciones de las monedas que admite, que se actualiza solo. Cuando registras un movimiento sin tocar el tipo de cambio, toma la última cotización del catálogo a la fecha y hora del movimiento.

La opción **Tipo de cambio** del formulario muestra la cotización que se va a usar y la fecha de la que viene.

## Escribir tu propio tipo de cambio

La cotización que realmente conseguiste muchas veces no es la publicada: una tarjeta, una casa de cambio o un amigo te dan la suya. Abre **Tipo de cambio** y escríbela, y se guarda esa en lugar de la del catálogo.

- Un tipo de cambio que escribiste se mantiene si cambias la fecha del movimiento.
- Uno que no escribiste sigue a la fecha: elige otro día y toma la cotización de ese día.
- Editar un movimiento conserva el tipo de cambio con el que se guardó.

## Cuando no hay cotización

Si el catálogo no tiene cotización para una moneda en la fecha que elegiste, la opción **Tipo de cambio** se abre sola y te pide una, porque un movimiento no se puede guardar sin ella. Lo mismo pasa con un saldo inicial.
