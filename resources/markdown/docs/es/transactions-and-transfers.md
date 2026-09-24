---
title: "Movimientos y transferencias"
description: "Cómo registrar ingresos y gastos, mover dinero entre cuentas, editar o eliminar lo registrado, filtrar la lista y exportarla a CSV."
pro: false
---

Un movimiento es dinero que entra a una cuenta o sale de ella. Una transferencia es dinero que pasa entre dos cuentas del mismo equipo. Entre los dos, son todo lo que registra el libro.

## Ingresos y gastos

Abre el menú **+** del encabezado y elige **Nuevo ingreso** o **Nuevo gasto**, o usa el menú **Acciones** de la página **Movimientos**. Cada movimiento tiene:

- **Monto**, siempre positivo: el tipo dice si el dinero entró o salió.
- **Cuenta** y moneda. El selector de cuenta solo aparece cuando el equipo tiene más de una cuenta activa, y la moneda tiene que ser una de las de la cuenta.
- **Categoría**, obligatoria. Las categorías son una lista fija, una para ingresos y otra para gastos, como **Comida**, **Vivienda** o **Sueldo**.
- La fecha y la hora, hoy por defecto.
- **Notas**, una descripción libre.
- **Etiquetas**, todas las que quieras. Consulta [Etiquetas](/es/docs/tags).
- **Tipo de cambio**, la cotización del día. Consulta [Tipos de cambio](/es/docs/exchange-rates).

El saldo de la cuenta se actualiza apenas guardas, y el aviso de confirmación ofrece **Deshacer**.

Con Pro, la opción **Repetir** convierte el movimiento en una regla que lo vuelve a registrar según un calendario. Consulta [Transacciones recurrentes](/es/docs/recurring-transactions).

## Transferencias

Una transferencia mueve dinero entre dos cuentas del mismo equipo, así que no es ni ingreso ni gasto. Elige **Transferencia** en el menú **+** y completa los dos lados:

- **Origen** y **Destino**: una cuenta y una moneda en cada lado. Las dos cuentas tienen que ser distintas.
- **Monto enviado** y **Monto recibido**. Con la misma moneda en los dos lados, el monto recibido sigue al enviado hasta que lo cambias.
- Si quieres, una **Comisión de origen** y una **Comisión de destino**: lo que cobró el banco o la red de cada lado.

Las monedas de los dos lados pueden ser distintas. Pasar dólares de una cuenta bancaria a pesos en efectivo es una sola transferencia: escribes lo que salió de una cuenta y lo que llegó a la otra, y cada lado guarda su propia cotización.

Una transferencia registra un movimiento saliente en la cuenta de origen y uno entrante en la de destino. Cada comisión se registra como un gasto en su lado, descrito como comisión de transferencia. Las notas y las etiquetas aplican a toda la transferencia. En la página **Mensual**, las transferencias aparecen como dinero que salió y que entró, nunca como ingreso o gasto.

Un movimiento que es parte de una transferencia se edita o se elimina desde la transferencia, no por separado.

## Editar y eliminar

Cada fila de la página **Movimientos** tiene un menú con **Ver**, **Editar** y **Eliminar**. Eliminar un movimiento revierte su efecto en el saldo y no se puede deshacer. Eliminar una transferencia borra sus dos lados y sus comisiones.

Cuando editas un movimiento, conserva el tipo de cambio con el que se registró, aunque cambies su fecha.

## Encontrar movimientos

La página **Movimientos** lista todos los movimientos del equipo, del más nuevo al más viejo. Busca por ID o descripción, o abre **Más filtros** para acotar la lista por tipo, categoría, cuenta, moneda, etiqueta o fecha.

## Exportar a CSV

Elige **Exportar CSV** en el menú **Acciones** para descargar los movimientos que muestra la lista, con los filtros que tengas puestos. El archivo tiene la fecha, el tipo, la categoría, el monto, la moneda, la descripción, la cuenta, las etiquetas, quién lo registró y su tipo de cambio.

## Historial en el plan gratis

En el plan gratis, la lista, la exportación, el panel y el reporte mensual muestran los últimos 6 meses, contando el actual. Los movimientos más viejos no se borran y siguen contando en todos los saldos; Pro muestra todo tu historial. Consulta [Planes y límites](/es/docs/plans-and-limits).
