---
title: "Cómo controlar tus gastos con ChatGPT o Claude"
description: "Un chat no es un registro. Cómo usar ChatGPT o Claude para controlar tus gastos, anotarlos y consultarlos sin perder nada."
published: "2026-11-07"
author: "Martina Olivera"
image: "/images/blog/llevar-gastos-con-chatgpt-o-claude.png"
translations: {"en": "track-expenses-with-chatgpt-or-claude"}
---

Cada vez más gente prueba a llevar sus gastos hablando con un asistente de IA: "gasté 1.200 pesos en el súper", "¿cuánto llevo gastado en comida este mes?". La idea es buenísima, porque contarle un gasto a un chat es mucho más rápido que abrir una planilla. Pero si lo haces solo dentro de una conversación, tarde o temprano los números no cierran.

Esta guía explica por qué, y cómo usar ChatGPT o Claude para tus gastos de una forma que sí funcione.

## Lo que un asistente hace muy bien

- **Entender lo que dices como lo dices.** "Pagué la luz, 2.300" alcanza; no tienes que llenar un formulario.
- **Responder preguntas.** "¿En qué gastamos más en septiembre?", "¿cuánto se fue en el viaje?".
- **Resumir y comparar.** Un mes contra otro, una categoría contra otra, en lenguaje simple.

## Por qué un chat no es un registro

El problema no es el asistente, sino dónde quedan guardados los datos:

- **La conversación no es una base de datos.** En chats largos, el asistente puede perder de vista gastos que le contaste hace semanas, o sumarlos mal. Y si empiezas un chat nuevo, empiezas de cero.
- **Es solo tuyo.** Si compartes gastos con tu pareja o con amigos, cada uno tiene su propio chat con su propia versión de los números.
- **No se puede revisar.** No hay una lista de movimientos que puedas filtrar, corregir o exportar; hay mensajes.
- **Las cuentas las hace el modelo.** Sumar decenas de montos de memoria no es su fuerte. Un número mal sumado en un chat no se nota hasta que ya es tarde.

## La forma que funciona: el asistente habla, el registro guarda

La solución es separar las dos cosas. El asistente se ocupa de entender lo que dices; los datos viven en un registro de verdad, con saldos calculados por un programa y no por el modelo.

Eso es lo que permite MCP (Model Context Protocol), el estándar con el que ChatGPT, Claude y otros asistentes se conectan a aplicaciones. Con una aplicación conectada por MCP:

- cuando dices "gasté 1.200 en el súper", el asistente **registra un movimiento** en la aplicación, no en la conversación;
- cuando preguntas "¿cuánto llevamos gastado en comida?", el asistente **consulta la aplicación**, que hace la cuenta exacta;
- mañana, en otro chat o desde otro asistente, los datos siguen ahí.

## ¿Es seguro?

Algunas precauciones valen para cualquier uso de IA con tu dinero:

- **Nunca le des tus claves del banco** ni datos de tarjetas a un asistente.
- Prefiere aplicaciones que **no necesitan conectarse a tu banco**: tú decides qué se registra.
- Revisa **qué puede hacer** el asistente una vez conectado y cómo desconectarlo.

## Ideas de pedidos que funcionan

Una vez conectado, puedes probar con cosas como:

- "Registra 1.200 pesos de súper en la cuenta de débito, con la etiqueta comida."
- "¿Cuánto gastamos en total en octubre, por categoría?"
- "Pagamos 80 dólares de cena en el viaje, del pozo."
- "Compara lo que gastamos en comida en septiembre y en octubre."

## Preguntas frecuentes

### ¿ChatGPT se acuerda de mis gastos?

No de forma confiable. La memoria guarda algunos datos entre chats, pero no es un registro: no puedes listar, corregir ni exportar lo que recuerda, y no suma con exactitud.

### ¿Es seguro compartir mis finanzas con un asistente de IA?

Comparte montos y descripciones, nunca claves del banco ni números de tarjeta. Una aplicación conectada por MCP solo ve lo que está registrado en ella, y puedes desconectarla cuando quieras.

### ¿Puede leer mi cuenta del banco?

No a través de Contamos, que no se conecta a bancos. Los movimientos los registras tú o el asistente. Si viajas en grupo, mira [cómo organizar la plata de un viaje en grupo](/es/blog/viaje-en-grupo-varias-monedas); y si manejas dos monedas, [cómo llevar el dinero compartido en pesos y dólares](/es/blog/dinero-compartido-en-dos-monedas).

## Cómo hacerlo con Contamos

[Contamos](/es) es un libro compartido para cualquier grupo que comparte dinero, y tiene un servidor MCP para conectar [Claude o ChatGPT](/es/docs/ai-assistants):

- el asistente puede leer saldos, movimientos y el reporte mensual, y registrar ingresos, gastos y transferencias;
- actúa como tú, con tu rol en cada equipo, y todo lo que registra lo ven los demás miembros, con [quién lo registró](/es/docs/members-and-roles);
- los saldos se llevan [por moneda](/es/docs/accounts-and-currencies), y el asistente nunca inventa un tipo de cambio: si no hay cotización para una fecha, te la pregunta;
- antes de cambiar o borrar algo, te pide confirmación, y puedes desconectarlo cuando quieras desde la app.

Contamos no se conecta a tu banco ni mueve dinero: registra lo que tú o tu asistente le cuentan. Funciona en todos los planes, y la [guía de asistentes de IA](/es/docs/ai-assistants) te lleva paso a paso para conectarlo.
