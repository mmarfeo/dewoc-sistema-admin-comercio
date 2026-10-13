# IA (Chat / Galería / Redes Sociales) — Tareas pendientes

> Registro de temas que quedaron identificados durante el desarrollo del Chat IA,
> la Galería con generación de imagen/video, y lo que falta para publicar ese
> contenido en redes sociales. No son bugs — son decisiones/trabajo a futuro.

Leyenda: ✅ Hecho · ⬜ Pendiente

---

## Costo real de Gemini (vs. estimado) ⬜
Hoy "Uso Gemini" calcula el costo de forma **teórica**: tokens/segundos logueados ×
precio cargado a mano en la config. Sirve para tener una idea, pero no es el número
exacto que Google factura (ver diferencia real detectada: $1.60 en la facturación de
Google vs. $0 mostrado en la app, por una falla de logging ya corregida — aun
corregida, seguirá siendo una estimación, no el dato real).

Para tener el **costo exacto** habría dos caminos:
- ⬜ **BigQuery Billing Export** (recomendado si se necesita precisión): habilitar en
  Google Cloud → Billing → Billing export, que vuelca cada línea de costo real a una
  tabla de BigQuery; consultarla desde nuestro backend con la cuenta de servicio que
  ya existe (`ia-image-videodewoc@...`). Contras: no trae datos históricos de antes
  de activarlo, y requiere sumar la librería/cliente de BigQuery al proyecto.
- ⬜ **Cloud Monitoring API**: más liviano, da conteo real de llamadas por API (no el
  costo en USD exacto) — serviría para validar que nuestro conteo interno coincide
  con lo que Google ve, sin tanta config.

Decisión pendiente: no se va a implementar por ahora, queda anotado para retomar si
hace falta precisión real (ej. para facturarle el gasto a alguien, o si el estimado
empieza a desviarse mucho de la factura real).

---

## Publicar en redes sociales desde la Galería ⬜
Objetivo: desde la Galería, con pocos clicks, conectar cuentas de redes sociales y
subir directamente la imagen/video generado, eligiendo a cuál(es) publicar.

Ya se había diseñado en un plan anterior (ver conversación) con este alcance:

- ⬜ **Conexión de cuentas** — OAuth de Meta (cubre Instagram + Facebook con el mismo
  login) vía una app propia creada en [developers.facebook.com](https://developers.facebook.com).
  Requiere de nuestro lado: `META_APP_ID`, `META_APP_SECRET`, URL de redirect
  configurada, y nuevas tablas (`redes_sociales_cuentas` con tokens cifrados en
  reposo — no hay precedente de esto en el proyecto, hay que sumarlo).
- ⬜ **WhatsApp Business** — Cloud API, autenticación distinta (token de sistema
  generado a mano en Meta Business Suite, no es un login OAuth como Instagram/Facebook).
- ⬜ **Selector de redes al publicar** — UI en la Galería: elegir el archivo generado,
  tildar a qué cuentas conectadas publicarlo, caption, publicar ya o programar.
- ⬜ **Programación** — tabla `redes_sociales_posts` (estado, fecha programada) + un
  script en `cron/` (mismo patrón que `cron/reporte_semanal.php`) que dispare los
  posts programados, ya que el proyecto no tiene cola de trabajos real.

**Antes de empezar a programar esto, hace falta de tu lado:**
1. Crear la app en Meta for Developers (developers.facebook.com) y decidir qué
   permisos pedir (`instagram_content_publish`, `pages_manage_posts`, etc.).
2. Tené en cuenta que Meta exige **App Review** para poder publicar en cuentas que no
   sean las tuyas propias de prueba — puede tardar días/semanas, conviene arrancarlo
   con anticipación si se quiere usar en producción con clientes reales.
3. Confirmar qué redes son prioridad (¿todas desde el arranque, o Instagram primero?).

---

## Otros temas abiertos de esta sesión ⬜
- ⬜ **NVIDIA como proveedor de imagen/video**: los endpoints armados (`ai.api.nvidia.com/v1/genai/...`)
  dieron 404 — Qwen-Image resultó ser en verdad un modelo de Together AI (de pago,
  ~$0.0058/imagen) y Wan 2.1 no está disponible como endpoint alojado por NVIDIA. Por
  ahora el selector de proveedor en la Galería quedó con Google como default; NVIDIA
  sigue como opción pero no funciona hasta encontrar un modelo realmente alojado por
  NVIDIA (no un partner) con endpoint verificado.
- ⬜ **Modelo del Chat IA**: quedó pendiente confirmar si conviene pasar `GEMINI_MODEL`
  de `gemini-flash-lite-latest` a `gemini-2.5-flash` (mejor siguiendo instrucciones
  complejas de function-calling, a costa de algo más de costo/menos cuota gratis).
