# Informe de QA

[🇬🇧 English](QA.md) · 🇪🇸 Español

Una pasada exploratoria que intentó romper la aplicación a propósito antes de la entrega. Cada
problema encontrado se corrigió, y cada arreglo viene con un test que falla sin él.

## Cómo se probó

- **Abuso por HTTP:** peticiones mal formadas, enormes y maliciosas con `curl`:
  - campos de query y de formulario enviados como arrays, y operadores NoSQL (`q[$ne]=1`);
  - páginas ilegibles o fuera de rango, y metacaracteres de regex;
  - UTF-8 inválido, bytes nulos y entradas de 5.000 caracteres;
  - path traversal, métodos no admitidos y una cabecera `Host` falsa;
  - un POST que supera el límite de tamaño y un token CSRF falsificado.
- **Límites del formulario, con un token CSRF real:**
  - longitudes máximas, emoji y texto de derecha a izquierda;
  - caracteres de ancho cero, caracteres de control y saltos de línea `\r\n`;
  - formatos de email y teléfono, valores del consentimiento y envíos duplicados.
- **Navegador:** escribir deprisa, volver atrás después de filtrar, fallo de red, doble clic y un
  nombre muy largo sin espacios en una pantalla de 375 px.
- **Caída de infraestructura:** RabbitMQ parado en mitad de una candidatura.

## Hallazgos y arreglos

| # | Gravedad | Qué pasaba | Arreglo | Lo vigila |
|---|---|---|---|---|
| 1 | Alta | Con RabbitMQ caído, postularse devolvía un **500**, pero la candidatura se guardaba huérfana (sin vista ni enriquecimiento), y reintentar creaba un duplicado. | El publisher registra la caída del broker en lugar de hacer fallar un cambio que ya está guardado. Un **reconciliador** (Symfony Scheduler, cada minuto) vuelve a proyectar y a enriquecer las candidaturas que se quedaron a medias. Comprobado en vivo: se recuperó solo en unos 2 minutos. | `MessengerDomainEventPublisherTest`, `ReconcileJobApplicationsHandlerTest`, `EnrichmentFlowTest` |
| 2 | Alta | Un mismo candidato podía postularse dos veces al mismo puesto (doble clic, recarga, reintento). | Una candidatura por email y puesto: comprobación en el caso de uso más un **índice único** para las carreras, un mensaje claro en el formulario, y el botón deshabilitado tras el primer clic. | `SubmitJobApplicationHandlerTest`, `MongoJobApplicationRepositoryTest`, `ApplyToAPositionTest`, aceptación `ADoubleClick…` |
| 3 | Media | Un POST de más de 1 MB devolvía un **500**: PHP escribía su aviso de tamaño dentro de la respuesta. | Caddy rechaza los cuerpos de más de 1 MB con un **413** antes de que lleguen a PHP. PHP nunca escribe avisos en las respuestas (`display_errors=Off`). | Comprobado con `curl` contra el stack |
| 4 | Media | Un CV en el límite de 20.000 caracteres se **rechazaba**: los navegadores cuentan un salto de línea como un carácter para `maxlength`, pero lo envían como `\r\n` (dos). | Los saltos de línea se normalizan a `\n` antes de validar y de guardar. | `CvTextTest`, `TextTest`, `ApplyToAPositionTest` |
| 5 | Media | Se aceptaba un nombre hecho solo de espacios de ancho cero, lo que daba un candidato invisible. | Los nombres pierden los caracteres invisibles y de control y se colapsan los espacios; el CV y las notas pierden los caracteres de control. | `TextTest`, `CandidateTest`, `ApplyToAPositionTest` |
| 6 | Media | En el móvil, un nombre o un resumen muy largo sin espacios ensanchaba la página hasta 3.170 px. | `overflow-wrap: anywhere` en la página y `minmax(0, 1fr)` en las rejillas que crecían con su contenido. | Comprobado en el navegador a 375 px |
| 7 | Media | Si fallaba un refresco (red), la URL mostraba los filtros nuevos pero la lista seguía con los resultados antiguos, sin ningún aviso. | Un mensaje visible de «no se pudo actualizar» con un botón **Reintentar**. Una petición abortada ya no borra el estado de carga de la más reciente. | Aceptación `AListThatCannotRefresh…` |
| 8 | Baja | `?page=abc`, páginas desmesuradas o parámetros en forma de array respondían **400**, aunque se supone que los filtros mal formados se ignoran. | Todos los filtros se leen de forma tolerante; lo que no se puede leer se ignora. | `BrowseApplicationsTest` (data provider) |
| 9 | Baja | Una página más allá del final decía «No applications yet» cuando sí había candidaturas. | Ahora dice que la página está más allá del final y enlaza a la primera. | `BrowseApplicationsTest` |
| 10 | Baja | Los CVs guardaban caracteres de control (`\x01`, secuencias ANSI). | Se eliminan al normalizar (los tabuladores y los saltos de línea se conservan). | `TextTest` |

## Lo que aguantó

- **XSS:** un nombre como `<img src=x onerror=alert(1)>` se escapa en todos los sitios donde
  aparece: el título, la barra superior, el `h1`, un atributo `data-`, el listado y los
  fragmentos. Una CSP con nonce añade una segunda barrera.
- **Inyección:** los operadores NoSQL y los metacaracteres de regex se buscan de forma literal, y
  los ids se validan como UUID antes de llegar a la base de datos.
- **Rutas:** el path traversal da 404, los métodos no admitidos dan 405 y no se puede acceder a `.env`.
- **Controles de seguridad:**
  - un token CSRF falsificado se rechaza;
  - el límite de peticiones responde 429 tras 5 candidaturas;
  - una cabecera `Host` falsa no se cuela en enlaces ni redirecciones, que son relativos.
- **Comportamiento en el navegador:**
  - volver atrás después de filtrar recupera la página completa (`Vary: X-Requested-With`);
  - escribir deprisa envía una sola petición (debounce), y las respuestas antiguas se descartan
    (`AbortController`).

## Suites de tests

| Suite | Qué ejecuta | Número |
|---|---|---|
| Unitarios | dominio, casos de uso con dobles de los puertos, adaptador de IA y listeners | |
| Integración | MongoDB real, un worker real de Messenger sobre transports en memoria y los comandos de consola | 184 en total (`make test`) |
| Funcionales | flujos HTTP a través del kernel, con CSRF incluido | |
| Aceptación | un Chrome real (Selenium) contra el stack en marcha: workers, RabbitMQ y JavaScript | 6 (`make acceptance`) |

**Cobertura** (suite por defecto): **99,2 % de líneas y 96,5 % de métodos**. `make coverage` y CI
fallan por debajo del 90 %.

**Sin cubrir a propósito:** dos ramas defensivas que relanzan errores de escritura inesperados de
MongoDB, y un catch de rechazos del dominio que la validación del formulario ya impide.
