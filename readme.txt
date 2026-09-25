=== Zerox AI Recommendations ===
Requires at least: 6.0
Requires PHP: 7.4
WC requires at least: 7.0
Stable tag: 1.5.0
License: GPL-2.0-or-later

Sistema de recomendacion de repuestos compatibles y complementarios para ZEROXMOTORS (WooCommerce),
con base de compatibilidad verificada, motor de ranking, captura de leads, analitica propia,
integracion GA4 e interpretacion de busquedas con IA.

== Caracteristicas ==
* Base de vehiculos/motores administrable (marca, modelo, version, cilindrada, codigo de motor).
* Compatibilidades producto-motor con buscador AJAX de WooCommerce y verificacion manual obligatoria.
* Motor de recomendaciones por reglas con ranking configurable.
* Carrusel "Complementa tu motor" en la ficha de producto, sin librerias externas.
* Captura progresiva de leads con consentimiento registrado (Ley 29733).
* Embudo completo con atribucion exacta de ventas.
* Analitica propia con exportacion CSV compatible con Excel.
* Eventos anonimos hacia GA4, con filtrado de datos personales.
* IA en cascada: alias, coincidencia de catalogo y, solo si hace falta, modelo generativo.

== Regla central ==
Si compatibilidad_verificada != 1, el producto NO se recomienda.
La IA interpreta lo que busca el cliente; la compatibilidad la confirma siempre la base de ZEROXMOTORS.

== Instalacion ==
1. Plugins > Anadir nuevo > Subir plugin.
2. Activar (requiere WooCommerce).
3. Revisar ZEROX AI > Dashboard: las 5 tablas y el diagnostico deben figurar correctos.
4. Cargar motores en ZEROX AI > Motores / Vehiculos.
5. Cargar compatibilidades, marcando como tipo "Motor" el producto que representa al motor.

== Seguridad ==
* Nonces en toda escritura del panel y del formulario de leads.
* current_user_can( 'manage_woocommerce' ) en cada accion administrativa.
* Prepared statements en todas las consultas con entrada de usuario.
* Escapado de salida en todas las vistas.
* Rate limiting por IP diferenciado para lectura y escritura.
* Honeypot antispam y limite de leads por sesion.
* API key de IA solo server-side; se recomienda definirla en wp-config.php.
* Sin datos personales en eventos ni en GA4.
