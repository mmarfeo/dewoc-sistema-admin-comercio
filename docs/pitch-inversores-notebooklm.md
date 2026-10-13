# Dewoc — Sistema de gestión con IA para comercios
### Guion fuente para generar diapositivas de inversores (NotebookLM)

> Documento fuente pensado para pegarse en NotebookLM y generar una presentación de
> diapositivas. Resume el proyecto en 5 bloques: qué es, beneficios, diferenciación,
> por qué lo usarían, y limitaciones actuales. Todo en un tono honesto: incluye lo
> bueno y lo que falta madurar, tal como está el producto hoy.

---

## 1. Qué es Dewoc

Dewoc es un sistema de gestión (ERP) para comercios pequeños y medianos de Argentina
— hoy validado en un caso real de producción: una heladería/repostería ("Serendipia").
Cubre el ciclo completo del negocio en un solo sistema: compras → stock → producción
(recetas y costeo) → venta (punto de venta) → caja → cobros y pagos → reportes →
ecommerce → nómina de empleados.

Lo que lo distingue de un ERP tradicional es que incorpora un **Chat con Inteligencia
Artificial que no solo responde preguntas, sino que ejecuta acciones reales** sobre el
sistema: se le puede pedir "cargá esta factura" adjuntando una foto, "ajustá el stock
de harina a 20 kilos" o "¿cómo vengo este mes?", y el asistente interpreta el pedido,
lo traduce en una acción concreta (alta de compra, ajuste de stock, consulta con
datos reales) y —para cualquier acción que modifique datos— pide confirmación
explícita antes de ejecutarla.

Es un producto funcional y en uso hoy, no un prototipo: versión 1.0.0, con cerca de
50 módulos operativos y una batería de más de 290 tests automatizados.

---

## 2. El problema que resuelve

Los comercios chicos y medianos en Argentina suelen gestionar su negocio con una
mezcla de Excel, cuadernos, WhatsApp y, como mucho, un sistema de punto de venta
suelto. Esto genera tres problemas recurrentes:

- **Información fragmentada**: la venta se registra en un lugar, el stock en otro, la
  contabilidad en otro, y nada se cruza automáticamente. Los errores de stock y de
  costos son moneda corriente.
- **Barrera de entrada alta**: los ERPs tradicionales del mercado (tipo Tango,
  Bejerman, Contabilium) están pensados para empresas con estructura administrativa,
  requieren capacitación formal y tienen curvas de aprendizaje largas — inviables
  para un comercio con dos o tres personas a cargo de todo.
- **Los sistemas de punto de venta simples (tipo Fudo y similares) resuelven solo la
  venta**, no el resto del ciclo: compras, producción, proveedores, empleados,
  facturación electrónica.

El dueño de un comercio chico no tiene tiempo, personal ni conocimiento técnico para
"aprender un ERP". Necesita algo que se le adapte a él, no al revés.

---

## 3. La solución: todo integrado, y una IA que actúa

**Cobertura funcional de punta a punta, en un solo sistema:**
- Compras y facturas de proveedores, con IVA discriminado y reversión automática de
  stock ante anulaciones.
- Producción con recetas, cálculo de merma y costeo automático.
- Punto de venta (POS) pantalla completa, con ticket térmico, comanda de cocina y
  pago dividido.
- Caja y turnos, con arqueo y diferencias.
- Stock en tiempo real, con inventario físico y ajustes.
- Clientes y proveedores con cuenta corriente.
- Empleados: legajos, asistencia y liquidación de nómina.
- Reportes con gráficos y exportación a Excel/PDF.
- Tienda online integrada con Mercado Pago.
- **Facturación electrónica AFIP nativa** (CAE, comprobantes A/B/C), sin depender de
  un proveedor externo de facturación.

**Un chat como forma alternativa de operar todo lo anterior**: en vez de navegar
menús y formularios para tareas de gestión, el usuario le habla al sistema. El motor
ya soporta más de dos docenas de acciones reales vía function-calling (dar de alta un
cliente, registrar una compra a partir de una foto de factura, ajustar stock,
registrar un pago, generar un reporte), con un patrón de "tarjeta de confirmación"
para cualquier acción irreversible — el asistente propone, el usuario aprueba.

**Funciona sin conexión donde más importa**: es una PWA instalable, con el punto de
venta capaz de seguir vendiendo sin internet y sincronizar después — crítico para
comercios con conectividad inestable.

**Seguridad de nivel profesional desde la base**: contraseñas con bcrypt, protección
CSRF y XSS, consultas parametrizadas en toda la aplicación, bloqueo de login por
intentos, auditoría de acciones sensibles y permisos granulares por módulo y por
usuario.

---

## 4. Diferenciación frente a otros productos

| Frente a... | Diferencia de Dewoc |
|---|---|
| ERPs tradicionales (Tango, Bejerman, Contabilium) | Sin curva de aprendizaje: se opera hablando, no llenando formularios. Costo de infraestructura bajo (no requiere licencias pesadas ni servidores dedicados). |
| POS simples (Fudo y similares) | Cubre todo el ciclo del negocio — compras, producción, proveedores, empleados — no solo la venta. |
| Chatbots "con IA" de otros sistemas | El chat no es un buscador de preguntas frecuentes: **ejecuta acciones reales y verificables** sobre los datos del negocio, con confirmación humana antes de cualquier cambio. |
| Soluciones genéricas importadas | Nace y se prueba en un comercio real argentino: factura electrónica AFIP nativa, moneda y huso horario locales, flujo de costos y mermas pensado para gastronomía/producción — no una adaptación de un producto genérico. |

La ventaja competitiva central es que el motor conversacional-transaccional (la parte
más difícil de construir de un "asistente que actúa", no solo que responde) ya está
construido, probado y funcionando en producción — no es una promesa de roadmap.

---

## 5. Por qué lo usarían (propuesta de valor para el cliente)

- **Ahorra horas de trabajo administrativo por semana**: cargar una compra fotografiando
  la factura en vez de tipearla a mano; preguntar "¿cómo vengo este mes?" en vez de
  armar una planilla.
- **Reduce errores costosos**: al tener compras, stock, producción y ventas conectados
  en un solo sistema, se eliminan los desvíos que aparecen cuando cada área se lleva
  por separado.
- **Barrera de entrada mínima**: cualquier persona que sepa usar WhatsApp puede operar
  el chat; no hace falta capacitación formal en un ERP.
- **Costo de propiedad bajo**: stack simple (PHP + MySQL, sin frameworks pesados),
  corre en hosting estándar, sin licencias de terceros costosas.
- **Sigue vendiendo sin internet**: el punto de venta offline evita la peor situación
  posible para un comercio — no poder cobrar por un corte de conexión.
- **Cumple con AFIP desde el día 1**, sin integrar un servicio externo de facturación.

---

## 6. Estado actual del producto (madurez técnica)

- Versión 1.0.0, en uso productivo real (no es un prototipo ni una demo).
- Cerca de 50 controladores, 55 modelos y ~90 migraciones de base de datos que documentan
  la evolución funcional del sistema.
- Más de 290 tests automatizados, incluyendo suite propia de seguridad (CSRF, XSS,
  inyección SQL, cabeceras HTTP).
- Integraciones reales y en funcionamiento: AFIP (facturación electrónica), Mercado
  Pago (cobros), IA generativa (chat con function-calling), notificaciones push.
- Arquitectura simple y liviana a propósito: sin frameworks pesados, lo que reduce el
  costo de infraestructura y facilita el mantenimiento con equipos chicos.

---

## 7. Lo que falta madurar (riesgos y limitaciones actuales)

Para ser honestos sobre el punto de partida:

- **Hoy es single-tenant**: cada cliente necesita su propia instalación y base de
  datos independiente. Todavía no es un SaaS multiempresa — aunque ya existe un plan
  de arquitectura concreto y detallado para convertirlo en uno (ver sección 8).
- **Desarrollado hasta ahora por un equipo muy chico** (un desarrollador). Escalar el
  producto y salir a vender requiere sumar equipo (desarrollo, comercial, soporte).
- **Deuda técnica típica de un proyecto que creció rápido resolviendo necesidades reales**:
  no tiene autoloader de clases, las migraciones de base de datos se aplican a mano
  (no hay un runner automatizado ni tabla de versión de esquema), y existen secretos
  por defecto en la configuración que hay que rotar manualmente en cada instalación
  nueva.
- **No tiene todavía una API REST formal** para integraciones máquina-a-máquina con
  otros sistemas (hoy la comunicación es vía sesión web autenticada).
- **El cumplimiento normativo de alojar datos fiscales de múltiples comercios en una
  infraestructura compartida** (requisito para el modelo SaaS) todavía no fue
  validado con un contador o abogado especializado — es un paso pendiente antes de
  escalar a muchos clientes.
- **Historia de versionado en git muy corta** (recién se formalizó el control de
  versiones del proyecto): la trazabilidad histórica del desarrollo previo es
  limitada.

Ninguno de estos puntos es un problema de concepto o de validación de producto — son,
en su mayoría, trabajo de ingeniería conocido y acotado (arquitectura multiempresa,
equipo, cumplimiento normativo), no incógnitas de si el producto funciona: **ya
funciona, en producción, hoy.**

---

## 8. La oportunidad: de producto a plataforma SaaS

Ya existe una propuesta de arquitectura completa y con decisiones tomadas para
transformar Dewoc en un SaaS multiempresa "chat-first":

- **Aislamiento de datos por empresa** dentro de una única base compartida (decisión
  ya tomada, pendiente de implementación).
- **Multiusuario por empresa desde el primer día** (no arranca limitado a un solo
  usuario, a diferencia del modelo actual).
- **Planes por módulos activables** (Chat / Negocio con AFIP / Producción-Mayorista /
  Ecommerce), sobre una única base de código — sin bifurcar el producto por cliente.
- **Alta de cuenta 100% self-service**: sin instaladores ni archivos de configuración
  manuales, a diferencia del modelo actual de instalación por cliente.
- El motor de IA conversacional — el activo más valioso y más difícil de replicar del
  proyecto actual — se reutiliza como base de referencia, diseñado desde el día 1
  para escalar a múltiples empresas de forma segura (cada conversación atada de forma
  inviolable a los datos de su propia empresa).

**Mercado objetivo**: miles de comercios pymes argentinos (y, a mediano plazo, de la
región) que hoy no tienen un sistema de gestión adecuado a su tamaño, bajo un modelo
de suscripción recurrente.

---

## 9. Resumen para cierre de la presentación

Dewoc no es una idea en etapa de validación: es un sistema de gestión completo,
probado en producción real, con una funcionalidad de IA conversacional que ya
ejecuta acciones reales sobre el negocio — no un chatbot de vidriera. El camino a
producto SaaS escalable ya está diseñado en detalle; lo que falta es el equipo y el
capital para ejecutarlo: migrar a arquitectura multiempresa, validar el cumplimiento
normativo, y salir a vender.
