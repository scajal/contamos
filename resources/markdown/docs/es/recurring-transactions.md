---
title: "Transacciones recurrentes"
description: "Reglas que registran un ingreso o un gasto según su propio calendario: cómo crear una, cuándo corre y cómo pausarla, cambiarla o eliminarla."
pro: true
---

Una regla recurrente registra el mismo ingreso o gasto según un calendario: el alquiler cada mes, un sueldo cada dos semanas, una suscripción cada año. Cada vez que le toca, la regla registra un movimiento igual que si lo hubiera ingresado un miembro. Las transacciones recurrentes necesitan [Pro](/es#pricing).

## Crear una regla

La forma más rápida es desde el formulario de movimientos. Al registrar un ingreso o un gasto, abre **Repetir**, elige cada cuánto en **Cada**, por ejemplo cada 1 mes o cada 2 semanas, y si quieres cuándo **Termina**. El movimiento que estás registrando es el primero, y la regla registra los siguientes desde ahí. Si su fecha es futura, la regla espera a que llegue.

También puedes abrir **Recurrentes** en la barra lateral y elegir **Nueva regla**. Una regla tiene:

- el **Tipo**, la **Cuenta**, el **Monto**, la **Moneda**, la **Categoría**, la **Descripción** y las **Etiquetas** del movimiento que registra;
- cada cuánto se repite: cada tantos días, semanas, meses o años;
- **Empieza**: la primera fecha, hoy o después, que también fija el día del mes o de la semana en que cae;
- la hora del día, leída en tu zona horaria;
- **Termina**: opcional; déjalo vacío para repetir siempre.

Una regla mensual que empieza el 31 cae el último día de los meses más cortos, y vuelve al 31 cuando el mes lo tiene.

## Cuándo se registran los movimientos

Contamos revisa las reglas cada hora y registra cada movimiento que ya venció, con la fecha en que vencía. Cada uno actualiza el saldo, conserva sus etiquetas, toma el tipo de cambio de su fecha y aparece en el registro de actividad como cualquier otro movimiento.

La página **Recurrentes** lista las reglas por su próxima fecha, con una frase que describe cada calendario.

## Cuando una regla falla

Una regla no puede registrar un movimiento cuando su cuenta se desactivó, cuando su moneda ya no está en la cuenta o cuando no hay cotización para la fecha. La regla queda entonces marcada como **Falló** con el motivo, y vuelve a intentar cada hora hasta que se resuelve la causa.

## Pausar, cambiar y eliminar

Desde el menú de una regla:

- **Pausar** la detiene. **Reanudar** la vuelve a empezar desde su próxima fecha después de hoy; las fechas que se perdió mientras estaba pausada se saltean, no se registran después.
- **Editar** cambia lo que registra o su calendario. Los cambios aplican desde la próxima vez, y los movimientos ya registrados quedan como están.
- **Eliminar** borra la regla. Los movimientos que ya registró quedan en el libro.

## En el plan gratis

Un equipo en el plan gratis ve la página **Recurrentes** bloqueada, y **Repetir** en el formulario de movimientos pide pasarse a Pro. Las reglas creadas mientras el equipo estaba en Pro se conservan, pero no registran nada hasta que el equipo vuelve a Pro. Las fechas que pasaron mientras tanto se saltean, así que volver a Pro nunca llena el libro de movimientos atrasados.
