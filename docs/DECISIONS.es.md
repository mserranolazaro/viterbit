# Registro de decisiones

[🇬🇧 English](DECISIONS.md) · 🇪🇸 Español

Cada entrada recoge una decisión, las alternativas valoradas y por qué se descartaron.
La versión en inglés es la canónica; esta se actualiza en el mismo commit.

---

## ADR-001 · Stack y entorno local autocontenido

**Decisión.** PHP 8.4, Symfony 8.1, Symfony Messenger sobre RabbitMQ, MongoDB, PHPUnit y Twig.
Todo corre en Docker Compose: `php` (FPM), `caddy` (servidor web), `worker` (consumidor de
Messenger), `mongo` y `rabbitmq`. `make init` construye las imágenes, instala dependencias,
prepara los recursos y arranca el worker al final, cuando el código que ejecuta ya está instalado.

**Por qué.** Es el stack con el que trabaja el equipo, así que el ejercicio enseña cómo
trabajaría en su código real y no en un stack elegido para ahorrar esfuerzo. Con un solo
comando y sin más requisitos que Docker, revisarlo es fácil.

**Alternativas descartadas.**
- *FrankenPHP o `symfony serve`*: menos contenedores, pero PHP-FPM detrás de un servidor web es
  el despliegue más habitual en producción y separa con claridad el proceso web del worker.
- *Un worker lanzado a mano* (`docker exec … messenger:consume`): es fácil olvidarlo, y el flujo
  asíncrono es el núcleo del ejercicio. Por eso el worker es un servicio más, con
  `restart: unless-stopped`.

**Notas.**
- Los puertos del host evitan los valores por defecto (web `8080`, UI de RabbitMQ `15673`,
  Mongo `27018`) para poder convivir con otros proyectos locales. AMQP no se publica al host.
- El contenedor corre con uid 1000; en CI se da permiso de escritura al checkout para ese usuario.

## ADR-002 · Symfony 8.1 en lugar de 8.0

**Decisión.** Usar la rama `8.1.*`.

**Por qué.** Symfony 8.0 ya no tiene mantenimiento y Composer se niega a instalarlo: todas las
versiones 8.0.x de `symfony/http-foundation` y `symfony/yaml` están afectadas por avisos de
seguridad publicados que solo se corrigen en 8.1.x. Un ejemplo es CVE-2026-108082, un ReDoS en
`HeaderUtils::split()` alcanzable a través de la cabecera `Cache-Control` de la petición.
Ignorar los avisos para seguir en 8.0 sería un mal compromiso en una aplicación expuesta a la web.

**Alternativas descartadas.** Symfony 7.4 LTS: tiene más soporte, pero supondría usar una
versión mayor más antigua que la del equipo sin ganar nada funcional en un PoC.

## ADR-003 · MongoDB 7.0 en el entorno local

**Decisión.** Fijar `mongo:7.0` en `compose.yaml`.

**Por qué.** MongoDB 8.0, 8.3 y 9.0 se niegan a arrancar en kernels Linux desde la 6.19 hasta
la 7.0.13 (una incompatibilidad de TCMalloc con rseq; ver SERVER-121912 y SERVER-125742). El
Docker Desktop actual trae uno de esos kernels (aquí `7.0.12-linuxkit`), así que `make init`
fallaría en la máquina del revisor. La aplicación no usa nada que necesite la 8.x: CRUD,
índices compuestos y búsquedas con regex anclada están todos en la 7.0, que tiene soporte
hasta 2027.

**Cuándo revisarlo.** Cuando Docker Desktop traiga un kernel 7.0.14 o superior, se sube la
imagen a `mongo:8` o posterior; no hace falta tocar código.

## ADR-004 · Seguimiento de releases de seguridad de PHP (php2shell, 21-10-2026)

**Contexto.** El 20 de octubre de 2026 el equipo de seguridad de PHP preanunció una release para
el día 21 que corrige dos vulnerabilidades críticas del núcleo. Los detalles se filtraron antes
(«php2shell») y esa misma mañana ya se informó de los primeros ataques. La release salió el día
21, antes de las 14:00 UTC anunciadas:

| Aviso | CVE | Qué es | CVSS v4 |
|---|---|---|---|
| GHSA-w8hw-w9fx-j79w | CVE-2026-109618 | RCE por corrupción en OOM: el `longjmp()` del error de memoria agotada deja memoria de la petición a medio inicializar. Se alcanza sin autenticación mediante el **parseo multipart** (muchos campos de fichero vacíos, cabeceras de parte sin límite). | 9.2 |
| GHSA-6wmf-4rv5-p3rp | CVE-2026-109619 | RCE por estado global obsoleto: un error fatal en un worker persistente deja estado global apuntando a memoria ya liberada de la petición (use-after-free). Una de las vías es el **progreso de subida de sesión**. | 9.2 |

Corregidas en **8.2.35, 8.3.36, 8.4.27 y 8.5.12**. Actualizar es la única solución completa.

**Estado (21-10-2026, 13:45 UTC).** PHP 8.4.27 está publicado en php.net, pero la imagen
oficial `php:8.4.27-fpm` todavía no está en Docker Hub. La imagen se sigue construyendo desde
`php:8.4-fpm`, que resuelve a **8.4.26 (vulnerable)**.

**Decisión.**
1. *Ya, como defensa en profundidad:* `docker/php/conf.d/security.ini` apaga lo que la
   aplicación no necesita. El CV se pega como texto y la subida de ficheros está fuera de
   alcance, así que se desactiva o se limita lo siguiente: `file_uploads=Off`,
   `max_file_uploads=0`, `post_max_size=1M`, `session.upload_progress.enabled=0` y
   `expose_php=Off`. Así se reduce la superficie multipart y de progreso de subida por la que
   pasan los dos CVE. Es una mitigación, no la solución.
2. *En cuanto salga la imagen:* fijar `FROM php:8.4.27-fpm` (una versión de parche exacta en
   lugar de la etiqueta flotante `8.4`, para que una imagen cacheada no siga en silencio con la
   versión vulnerable) y hacer que CI falle si el PHP de la imagen es anterior.

**Por qué importa aquí.** La aplicación es un formulario público que acepta texto libre:
justo la superficie sin autenticación y de parseo de peticiones a la que apuntan estos fallos.
Apagar lo que no se usa cuesta poco y sigue siendo útil después de actualizar.

## ADR-005 · Un único bounded context, `Recruitment`, y el nombre `JobApplication`

**Decisión.** Un solo bounded context, `Recruitment`, contiene los puestos, las candidaturas y
su enriquecimiento IA. `SharedKernel` guarda las piezas base (raíz de agregado, evento de
dominio, ids UUID). Las capas siguen la organización del equipo: `Domain/Write` (agregados,
value objects, eventos, excepciones, puertos de repositorio, servicios de dominio),
`Domain/Read` (modelo de lectura y su puerto), `Application` e `Infrastructure`.

El agregado se llama **`JobApplication`**, no `Application`.

**Por qué.**
- Aquí todo comparte un mismo lenguaje (un candidato se postula a un puesto y recibe un score)
  y cambia por los mismos motivos. Partirlo añadiría eventos de integración y capas de
  traducción a un PoC sin quitar ningún acoplamiento.
- La IA es un *puerto* de este contexto (`CvAnalyzer`), no un contexto propio: aquí no tiene
  modelo ni lenguaje propios, es una capacidad que usa el dominio.
- «Application» ya es el nombre de una capa. `Application/Command/Application/SubmitApplication`
  es ambiguo cada vez que se lee; además, `JobApplication` es el término que usan los recruiters.

**Alternativas descartadas.**
- *Un contexto `Enrichment` (o `Screening`) aparte*: tendría sentido si el cribado tuviera
  reglas, modelos o equipo propios. La estructura deja sitio para extraerlo.
- *Un contexto `Position`*: en un ATS completo, los puestos pertenecen a otro contexto
  (requisiciones, publicación). Aquí son datos de semilla de solo lectura.

## ADR-006 · Un dominio puro: invariantes, una sola máquina de estados y el tiempo inyectado

**Decisión.**
- El dominio no tiene código de framework ni de persistencia. No lleva atributos de mapeo ni
  servicios de Symfony, y queda excluido del autowiring. La única dependencia externa es
  `symfony/uid`, para generar los ids UUID v7.
- Los value objects imponen las invariantes. `Email`, `Candidate`, `CvText` (no vacío, como
  mucho 20.000 caracteres, guardado tal cual), `Score` (0–100) y `CvAnalysis` no pueden existir
  en un estado inválido.
- Las transiciones de estado permitidas están en un solo sitio,
  `JobApplicationStatus::canTransitionTo()`: `received → enriching → enriched | failed`, más
  `received → failed` para las candidaturas que ni siquiera pueden empezar (por ejemplo, si
  su puesto ya no existe). Los estados finales no admiten ninguna transición.
- `startEnrichment()` sobre una candidatura que ya está en `enriching` no hace nada. Los
  mensajes se entregan al menos una vez, así que un reintento tiene que ser inocuo.
- El agregado nunca lee el reloj: cada método recibe `$now`. Así los tests son deterministas
  sin mockear el tiempo, y los handlers pasan `ClockInterface::now()`.
- Los ids son UUID v7 generados por el dominio: no hace falta ir a la base de datos y se
  ordenan por fecha de creación.
- `reconstitute()` reconstruye un agregado desde el almacenamiento sin registrar eventos;
  `submit()` y las transiciones sí los registran.

**Por qué.** Las reglas por las que preguntará el CTO (qué estados hay y cuál puede seguir a
cuál) se pueden leer y probar en dos ficheros pequeños, sin arrancar nada. Los tests cubren la
tabla completa de transiciones.

**Alternativas descartadas.**
- *Atributos de mapeo de Doctrine en las clases del dominio* (como en los servicios MySQL del
  equipo): es pragmático, pero acopla el modelo a una librería de persistencia. Con MongoDB el
  mapeo es lo bastante pequeño para escribirlo a mano en infraestructura (ver el ADR de
  persistencia).
- *El estado como string, con `if` en cada método*: las reglas acaban repartidas por el agregado.
- *Una librería de máquina de estados (symfony/workflow)*: demasiado para cuatro estados y
  cinco transiciones.

## ADR-007 · Eventos ligeros y una proyección que relee el agregado

**Decisión.** Los eventos de dominio llevan el id del agregado y los datos que han cambiado
(el id del puesto al enviarse, el score al enriquecerse, el motivo al fallar), no el agregado
entero. El modelo de lectura lo construye un proyector que, ante **cualquier** evento, carga el
agregado actual y su puesto y **reemplaza** la vista completa (un upsert).

**Por qué.**
- *Idempotente*: la entrega es al menos una vez, y aplicar el mismo evento dos veces escribe
  la misma vista.
- *Independiente del orden*: los eventos se consumen de forma asíncrona, así que
  `JobApplicationWasEnriched` puede procesarse antes que `JobApplicationWasSubmitted`. Releer
  siempre escribe el estado más reciente.
- *Reconstruible*: todo el modelo de lectura se puede regenerar desde el de escritura con un
  comando de consola, tras un bug o un cambio de esquema.
- *Contratos estables*: los eventos pequeños cambian menos que los agregados.

**Contrapartida.** El proyector lee el modelo de escritura, así que queda acoplado a él. Es
aceptable dentro de un mismo bounded context. Los eventos que salieran hacia otros servicios
serían eventos de integración, con payload completo y versionado.

**Alternativas descartadas.** *Aplicar cada evento como un delta sobre la vista* (`$set status`,
`$set score`): es el estilo habitual con event sourcing, pero necesita comprobar versiones
para aguantar mensajes desordenados o duplicados. Más código y más formas de equivocarse, sin
ganar nada aquí.

## ADR-008 · Casos de uso: comandos, queries y el flujo de enriquecimiento

**Decisión.** Siguiendo las convenciones del equipo, cada caso de uso es un mensaje más un
handler (`Application/{Command,Query}/<Agregado>/<CasoDeUso>/`) en `command.bus` o `query.bus`.
Los suscriptores de eventos viven en `Application/Event`, en `event.bus`.

```
SubmitJobApplication ──▶ JobApplicationWasSubmitted ──┬─▶ RequestEnrichmentOnJobApplicationSubmitted
                                                     │      └─▶ EnrichJobApplication (cola propia, reintentos)
                                                     └─▶ ProjectJobApplicationViewOnDomainEvent
EnrichJobApplication ──▶ EnrichmentWasStarted y después WasEnriched ──▶ proyección
tras el último reintento ──▶ MarkEnrichmentAsFailed ──▶ EnrichmentHasFailed ──▶ proyección
```

- **Cada handler de comando** carga, modifica, guarda y después publica los eventos que ha
  sacado del agregado. `SubmitJobApplication` devuelve el id nuevo, como los handlers de
  creación del equipo.
- **El evento se convierte en un comando.** El evento cuenta un hecho y puede tener varios
  suscriptores (la proyección, la petición de enriquecimiento, mañana un email de
  confirmación). El comando expresa una intención: tiene su propia cola y política de
  reintentos, y se puede reenviar a mano para una sola candidatura sin reproducir el evento
  para todos los suscriptores.
- **El enriquecimiento guarda `enriching` antes de llamar a la IA**, para que la UI muestre el
  progreso mientras corre el análisis y para que, si algo se cae a mitad de la llamada, quede un
  estado visible en lugar de un fallo silencioso.
- **Los fallos se clasifican:**
  - `CvAnalysisFailedException` (proveedor caído, salida mal formada o que no cumple el
    esquema) es *transitorio*. Se propaga y Messenger lo reintenta; cuando se agotan los
    reintentos, `MarkEnrichmentAsFailed` registra el fallo definitivo.
  - Una candidatura que no existe es *irrecuperable*:
    `UnrecoverableMessageHandlingException`, sin reintentos.
  - Una candidatura cuyo puesto ya no existe es un *resultado de negocio*: se marca como
    fallida en el momento, con el motivo.
- **Idempotencia:** una candidatura que ya está en un estado final se ignora, y arrancar un
  enriquecimiento que ya está en curso no hace nada. Los mensajes duplicados o reintentados no
  cambian nada.
- **Las queries de candidaturas devuelven el modelo de lectura tal cual.** `JobApplicationView`
  ya es plano y tiene la forma que necesitan las páginas, así que un DTO de respuesta lo
  copiaría campo a campo. Las queries de puestos sí convierten el agregado en DTOs de respuesta,
  para que el modelo de escritura nunca llegue a la UI.
- **Los filtros son tolerantes.** Los estados desconocidos, los ids mal formados y las páginas
  fuera de rango se ignoran en lugar de dar error: una URL compartida o editada a mano sigue
  mostrando un listado.

**Alternativas descartadas.**
- *Llamar a la IA dentro de la petición de envío*: el candidato esperaría a una dependencia lenta
  e inestable, y si fallara se perdería la candidatura o tendría que volver a enviarla.
- *Que el handler de enriquecimiento capture sus propios fallos y los marque como fallidos*: no
  puede saber si habrá otro reintento. Solo el worker sabe cuándo se han agotado.

**Nota sobre los tests.** Los handlers se prueban de forma unitaria con dobles de PHPUnit de los
puertos, como en los servicios del equipo: mocks cuando la interacción es lo que se comprueba y
stubs en el resto. Un publisher espía registra los eventos publicados y `MockClock` hace que el
tiempo sea determinista.

**Estado entre fases.** Los puertos todavía no tienen adaptadores (llegan con la fase de
infraestructura), así que en este punto el contenedor no compila y se ha quitado el test de
arranque del kernel. Su función la asumen los tests de integración y funcionales.

## ADR-009 · Solo MongoDB: una colección para escribir y otra para leer

**Decisión.** Todo vive en MongoDB, en tres colecciones:

| Colección | Modelo | Forma |
|---|---|---|
| `positions` | escritura | un documento por puesto, con los requisitos embebidos |
| `job_applications` | escritura | un documento por agregado, con el enriquecimiento embebido |
| `job_application_views` | lectura | plano, con el título del puesto copiado, un `searchText` en minúsculas e índices para el listado |

**Por qué.**
- CQRS separa *modelos*, no *motores*. El modelo de escritura lo dan las invariantes y el de
  lectura lo da la página del listado. Dos colecciones enseñan esa separación igual de bien que
  dos bases de datos.
- Una candidatura es un documento natural: el candidato, el CV y el enriquecimiento se leen y se
  escriben siempre juntos. No hacen falta joins.
- Un solo motor supone un contenedor, un driver y ninguna migración de esquema.

**Alternativas descartadas.**
- *MySQL para el modelo de escritura (Doctrine ORM, como en los servicios del equipo) y MongoDB
  para las vistas*: es un diseño válido de persistencia políglota, y el que mejor conozco. En un
  PoC duplica la infraestructura (dos motores, dos librerías de mapeo, migraciones) sin cambiar
  la arquitectura. Con una razón real, como informes relacionales o restricciones fuertes entre
  agregados, es un cambio que se queda en los adaptadores de repositorio.
- *Consultar directamente la colección de escritura para el listado*: es más simple, pero
  entonces el modelo de escritura cargaría con cosas del listado (el título del puesto, campos
  de búsqueda, índices del listado) y el CQRS se quedaría en el nombre.

## ADR-010 · El driver oficial de MongoDB en lugar de Doctrine ODM

**Decisión.** Usar `mongodb/mongodb` (la librería oficial) con mappers escritos a mano en cada
repositorio. Los documentos vuelven como arrays planos (`typeMap`) y cada guardado sustituye el
documento entero con `replaceOne(..., upsert)`. Los puertos se enlazan con sus adaptadores de
forma explícita en `config/services.yaml`.

**Por qué.**
- El dominio sigue sin atributos de mapeo (ADR-006). El mapeo son unas 150 líneas para tres
  colecciones, y es explícito: lo que se guarda es exactamente lo que se lee.
- Los agregados se guardan enteros, así que el unit of work y el control de cambios del ODM no
  aportan nada, y los proxies y la carga diferida solo ocultarían qué consultas se ejecutan.
- Menos piezas que explicar y que actualizar.

**Contrapartidas y detalles.**
- Las fechas BSON tienen precisión de milisegundos y no tienen zona horaria. Se guardan en UTC,
  y en el listado los empates dentro del mismo milisegundo se resuelven con el id UUID v7, que
  está ordenado por tiempo.
- No hay bloqueo optimista. Todas las transiciones son idempotentes y solo el worker modifica
  una candidatura después del envío, así que aquí no es realista tener escritores concurrentes.
  Si los hubiera, bastaría con añadir un campo `version` al filtro del `replaceOne`.

**Alternativas descartadas.** *Doctrine MongoDB ODM* (5.6, compatible con Symfony 8): se parece
más al ORM que usa el equipo, pero obligaría a poner atributos en el dominio o a usar mapeos en
XML. Es más maquinaria de la que necesita el problema.

## ADR-011 · Topología de mensajería y publicación de eventos

**Decisión.**
- Tres buses: `command.bus`, `query.bus` y `event.bus`. El último admite mensajes sin handlers,
  porque un evento puede no tener todavía suscriptores.
- `DomainEvent` (la interfaz) se enruta al transport `events`, y `EnrichJobApplicationCommand`
  a `enrichment`. Cada uno tiene su propio exchange fanout y su cola en RabbitMQ. Todo lo demás
  es síncrono.
- El worker consume `events enrichment` en ese orden, para que las proyecciones no queden
  bloqueadas detrás de llamadas lentas a la IA.
- Los handlers publican *después* de guardar, a través del puerto `DomainEventPublisher`
  (adaptador: `MessengerDomainEventPublisher`).
- Los mensajes usan el serializador PHP por defecto de Messenger: estas colas son privadas de
  esta aplicación.

**Contrapartida: doble escritura.** Guardar en MongoDB y después publicar en RabbitMQ son dos
escrituras sin una transacción que las englobe. Si el proceso muere entre ambas, la candidatura
queda guardada pero su evento se pierde: se queda en `received` y sin vista. Se acepta en un
PoC. La solución es el **outbox transaccional**: guardar los eventos en el mismo documento o
transacción que el agregado y que un relay los publique. Mientras tanto, `app:views:rebuild`
repara el modelo de lectura y reenviar `EnrichJobApplicationCommand` repara el enriquecimiento.

**Alternativas descartadas.**
- *Un serializador JSON con esquemas de eventos versionados*: es imprescindible en cuanto otros
  servicios consuman estos eventos, no antes.
- *Un solo transport para todo*: una llamada a la IA lenta o fallida retrasaría todas las
  proyecciones que tuviera detrás.

## ADR-012 · Reintentos y fallo definitivo

**Decisión.**
- `enrichment` tiene 3 intentos en total (reintentos a 1 s y después a 3 s). `events` tiene 4
  intentos, empezando en 0,5 s y doblando.
- Cuando falla el último intento de un enriquecimiento, el worker lanza
  `WorkerMessageFailedEvent` con `willRetry() === false`. Un listener,
  `MarkEnrichmentAsFailedOnFinalFailure`, despacha entonces `MarkEnrichmentAsFailed` con el
  número de intentos y el motivo. La candidatura pasa a `failed` y la UI explica por qué.
- El mensaje también se guarda en la cola `failed` (el failure transport de Messenger), donde se
  puede inspeccionar con `messenger:failed:show`.

**Por qué.** Solo el worker sabe si va a haber otro intento. Un handler que capturara su propia
excepción tendría que adivinarlo, y o se rendiría demasiado pronto o nunca marcaría el fallo.

**Límite conocido.** `messenger:failed:retry` sobre una candidatura que ya está en `failed` no
cambia nada, porque los estados finales se respetan. Volver a enriquecer una candidatura fallida
sería un caso de uso explícito (`failed → enriching`, por ejemplo desde un botón de
«reintentar»). Queda apuntado como trabajo futuro.

**Verificado de punta a punta** contra el RabbitMQ y el worker reales. Un CV con el marcador de
fallo pasa a `enriching`, recibe 3 intentos en unos 4 s y termina en `failed` con «Enrichment
failed after 3 attempt(s): The LLM answer does not match the schema…». Después, el mensaje
queda en `ats.failed`.

## ADR-013 · La IA detrás de un puerto, simulada como si fuera un LLM real

**Decisión.** `CvAnalyzer` (puerto de dominio) lo implementa `PromptBasedCvAnalyzer`
(infraestructura), que:

1. rellena el prompt versionado `resources/prompts/cv_analysis.v1.md` con el puesto, sus
   requisitos y el CV;
2. lo envía mediante `LlmClient::complete(string): string`, el contrato mínimo que cumple
   cualquier proveedor;
3. quita las vallas de código Markdown (los modelos las añaden aunque se les diga que no),
   decodifica el JSON y lo valida contra `cv_analysis.v1.schema.json` (JSON Schema 2020-12,
   `opis/json-schema`);
4. devuelve un `CvAnalysis` etiquetado con la versión del prompt, o lanza
   `CvAnalysisFailedException`. Esa excepción es transitoria, así que el enriquecimiento se
   reintenta (ADR-012).

`SimulatedLlmClient` es el único `LlmClient`. Es **determinista** y lee **solo el prompt
renderizado**, como haría un modelo real:
- El *score* es la proporción ponderada de requisitos que aparecen en el CV: `[must]` pesa 3 y
  `[nice]` pesa 1, con lo que sale `round(100 × encontrados / total)`. La coincidencia es por
  palabra completa y sin distinguir mayúsculas, así que «Go» no coincide con «Google» pero «C++»
  sí coincide.
- El *resumen* es la primera línea del CV, qué requisitos cumple y cuáles le faltan, más los
  años de experiencia si el CV los menciona.
- Un CV que contenga `[simulate-llm-failure]` recibe una respuesta que no cumple el esquema.
  Así es fácil enseñar en la demo los reintentos y el fallo.

**Por qué.**
- El modelo se trata como entrada no confiable. El contrato es el esquema, no las buenas
  intenciones del prompt. Un modelo real puede devolver prosa, tipos incorrectos o campos de más,
  y todo eso se rechaza y se reintenta.
- *Prompts versionados*: cada score guardado registra qué prompt lo produjo. Cambiar el prompt
  supone añadir `cv_analysis.v2.md` y su esquema, nunca editar un fichero al que apuntan
  resultados ya guardados.
- *Inyección de prompt*: el prompt indica al modelo que trate el CV como datos, y la simulación
  lee el CV hasta la última etiqueta de cierre. Un CV no puede cerrar su propia sección ni
  inventarse requisitos, y hay un test que lo comprueba.
- Pasar a producción supone escribir una clase (un `LlmClient` HTTP para el proveedor elegido) y
  cambiar un alias. El prompt, el esquema, la validación, los reintentos y los tests no cambian.

**Alternativas descartadas.**
- *Un `CvAnalyzer` falso que devuelva un score aleatorio o fijo*: se salta justo las partes que
  fallan en producción (el prompt, el parseo y la validación), y los resultados aleatorios hacen
  que los tests y las demos no sean fiables.
- *Validar con código PHP en lugar de JSON Schema*: el esquema es un contrato independiente del
  lenguaje, versionado junto al prompt, que se puede pasar a los proveedores que admiten salida
  estructurada.

## ADR-014 · Cómo probar un sistema asíncrono

**Decisión.**
- *Unitarios*: el dominio, los casos de uso con dobles de los puertos y los adaptadores de IA.
- *Integración*: el MongoDB real (base de datos `ats_test`, que se borra antes de cada test) a
  través del contenedor. En los tests, los transports asíncronos son `in-memory://`, y
  `IntegrationTestCase::runWorker()` ejecuta un **`Worker` de Messenger real** sobre ellos hasta
  vaciarlos. Ese worker tiene la estrategia de reintentos configurada y los mismos listeners de
  eventos que producción.
- Los reintentos en test mantienen el *número* de intentos de producción, pero sin *retardo*. El
  transport en memoria respeta los retardos, así que si no el worker se quedaría inactivo antes
  de que tocara el reintento.

**Por qué.** Un test puede comprobar lo que es cierto *entre* un paso y otro, que es donde viven
los bugs asíncronos. Tras el envío, el evento está en cola y todavía no hay vista. Tras pasar el
worker, la vista está enriquecida. Un enriquecimiento que falla deja exactamente 3 intentos y 1
mensaje en el failure transport. Un comando reenviado no cambia nada.

**Alternativas descartadas.** *Transports `sync://` en los tests*, el plan inicial: es más
simple, pero un enriquecimiento fallido saltaría hasta la llamada de envío (sin reintentos y sin
el listener de fallo definitivo), y los tests cubrirían un flujo que nunca se da en producción.

**Consecuencia para la UI.** El modelo de lectura tiene consistencia eventual: durante unos
milisegundos después del envío todavía no hay vista. Las páginas tienen que contemplarlo (fase de UI).
