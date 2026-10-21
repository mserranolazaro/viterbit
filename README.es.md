# ATS PoC: postular, enriquecer con IA, consultar

[🇬🇧 English](README.md) · 🇪🇸 Español

Un Applicant Tracking System mínimo, hecho para la [prueba técnica de Viterbit](https://github.com/viterbit/tech-exercise).
Los candidatos se postulan a un puesto pegando su CV como texto. Después, una IA simulada y
asíncrona resume el CV y lo puntúa frente al puesto, y los recruiters consultan las candidaturas
con filtros y búsqueda en vivo.

**Stack:** PHP 8.4 · Symfony 8.1 · Symfony Messenger + RabbitMQ · MongoDB · Twig + Tailwind v4 ·
JS vanilla · PHPUnit · Docker Compose.

---

## Cómo arrancarlo

Solo hacen falta Docker (con Compose) y `make`.

```bash
make init
```

Construye las imágenes, levanta todos los servicios (web, **dos workers de Messenger**, MongoDB
y RabbitMQ), instala dependencias, crea los índices, carga **4 puestos de ejemplo** y compila el
CSS. CI ejecuta este mismo comando en cada push.

| | |
|---|---|
| Aplicación | http://localhost:8080 |
| UI de RabbitMQ | http://localhost:15673 (guest / guest) |
| MongoDB | `localhost:27018` |

Después:

```bash
make demo      # envía 7 candidaturas de ejemplo y se ve en directo cómo se enriquecen
make test      # tests unitarios, de integración y funcionales (contra MongoDB real)
make coverage  # lo mismo con cobertura; falla por debajo del 90 % (HTML en var/coverage/html)
make acceptance # tests de aceptación en un Chrome real contra el stack en marcha
make qa        # php-cs-fixer, phpcs (PSR-12), PHPStan nivel 8
make security  # falla si PHP es anterior a la última release de seguridad
make logs      # sigue los logs de los workers
make help      # todos los comandos
```

### Recorrido de dos minutos

1. **Postularse:** abre un puesto, rellena el formulario y pega un CV. Llegas a tu candidatura,
   que muestra *Received*, después *Analysing CV* y por último *Enriched*, con un resumen y un
   score. La página se actualiza sola.
2. **Consultar:** entra en *Applications*. El listado sale de más reciente a más antigua, los
   filtros (estado, puesto) y la búsqueda (nombre o email) se aplican mientras escribes, la URL
   se puede compartir y cada candidatura muestra su score y su estado.
3. **Ver un fallo:** pon `[simulate-llm-failure]` en cualquier parte de un CV. El LLM simulado
   responde con una salida inválida, el enriquecimiento se intenta 3 veces y la candidatura
   termina en *Failed*, marcada para revisión manual.

## Arquitectura

DDD con organización hexagonal y CQRS sobre Symfony Messenger, en un único bounded context
(`Recruitment`) más un `SharedKernel`.

```mermaid
flowchart LR
    subgraph UI["Web (Twig + JS vanilla)"]
        form["Formulario de candidatura"]
        pages["Listado y detalle<br/>(filtros en vivo, polling)"]
    end

    subgraph App["Capa de aplicación"]
        submit["SubmitJobApplication"]
        enrich["EnrichJobApplication"]
        failed["MarkEnrichmentAsFailed"]
        project["ProjectJobApplicationView"]
        queries["Queries de búsqueda y detalle"]
    end

    subgraph Domain["Dominio"]
        agg["Agregado JobApplication<br/>received → enriching → enriched | failed"]
        port(["Puerto CvAnalyzer"])
    end

    subgraph Infra["Infraestructura"]
        write[("Mongo<br/>job_applications")]
        read[("Mongo<br/>job_application_views")]
        mq{{"RabbitMQ<br/>ats.events · ats.enrichment"}}
        ai["PromptBasedCvAnalyzer<br/>prompt versionado + JSON Schema<br/>→ SimulatedLlmClient"]
    end

    form -- "command.bus (síncrono)" --> submit --> agg --> write
    submit -- "JobApplicationWasSubmitted" --> mq
    mq -- "worker-events" --> project
    mq -- "worker-enrichment" --> enrich
    enrich --> agg
    enrich --> port -.-> ai
    enrich -- "EnrichmentWasStarted, WasEnriched" --> mq
    mq -. "reintentos agotados" .-> failed --> agg
    project -- "relee el agregado, upsert si es más nuevo (versión)" --> read
    pages -- "query.bus" --> queries --> read
```

- **Escritura:** los comandos modifican el agregado `JobApplication`, que registra eventos de
  dominio. El agregado se guarda en `job_applications` y sus eventos se publican en RabbitMQ.
- **Parte asíncrona:** dos workers, uno por cola. Un envío se convierte en un comando
  `EnrichJobApplication`, que va a su propia cola y tiene sus propios reintentos, así que una
  llamada lenta a la IA nunca retrasa las proyecciones. Cada evento refresca el modelo de
  lectura, y una protección por versión impide que una proyección antigua sobrescriba otra más
  reciente.
- **Lectura:** el listado y el detalle consultan `job_application_views`, una colección plana
  indexada para el listado.
- **IA:** el dominio solo conoce el puerto `CvAnalyzer`. El adaptador rellena un prompt
  versionado, llama a un `LlmClient` y valida la respuesta contra un JSON Schema. El cliente es
  una simulación determinista, porque el ejercicio prohíbe llamar a APIs reales.

```
src/
├── SharedKernel/            raíz de agregado, eventos de dominio, ids UUID v7, pegamento Mongo/Messenger/HTTP
└── Recruitment/
    ├── Domain/Write/        agregados, value objects, eventos, excepciones, puertos
    ├── Domain/Read/         modelo de lectura y su puerto
    ├── Application/         comandos, queries, suscriptores de eventos, proyector
    └── Infrastructure/      repositorios Mongo, listener de Messenger, adaptador de IA, HTTP, consola
```

## Decisiones clave

Todas las decisiones, con las alternativas descartadas, están en **[docs/DECISIONS.es.md](docs/DECISIONS.es.md)**.
Las que más dan para hablar:

| Decisión | Por qué, en una línea | ADR |
|---|---|---|
| Un único bounded context; el agregado se llama `JobApplication` | Un solo lenguaje y un solo motivo de cambio; «Application» ya es el nombre de una capa | 005 |
| Dominio puro, con la máquina de estados en un enum | Las reglas se leen y se prueban sin arrancar nada | 006 |
| Solo MongoDB, con colecciones de escritura y de lectura separadas | CQRS separa modelos, no motores; un motor menos en un PoC | 009 |
| Driver oficial en lugar de Doctrine ODM | Mapeo explícito y sin atributos de persistencia en el dominio | 010 |
| Evento → comando para el enriquecimiento | El hecho puede tener muchos suscriptores; la intención tiene su propia cola y sus reintentos | 008 |
| Proyección que relee el agregado, protegida por su versión | Idempotente, independiente del orden incluso con workers en paralelo, y reconstruible (`app:views:rebuild`) | 007 |
| Un worker por cola | Una llamada lenta a la IA nunca frena las proyecciones; cada lado escala por su cuenta | 011 |
| El fallo definitivo lo decide el worker (`WorkerMessageFailedEvent`) | Solo el worker sabe cuándo se han agotado los reintentos | 012 |
| El LLM se trata como entrada no confiable (prompt versionado, JSON Schema, reintentos) | Cambiar a un modelo real es una clase; nada más cambia | 013 |
| Los tests ejecutan un worker real de Messenger sobre transports en memoria | Comprueban el estado entre pasos asíncronos, que es donde están los bugs | 014 |
| Fragmentos HTML y polling en lugar de una SPA | Un solo renderizador, sin cadena de build, funciona sin JS | 015 |
| Seguridad y privacidad: límite de peticiones, CSP con nonce, consentimiento explícito, mensajes solo con ids | Un formulario público que recibe CVs: abuso, XSS y RGPD son los riesgos reales | 016 |
| Runtime parcheado: Symfony 8.1, PHP fijado en 8.4.27, control de CVE en CI | La 8.0 no tiene parches; php2shell (CVE-2026-109618/9) salió durante el ejercicio | 002, 004 |
| Una caída del broker no pierde nada: el publisher lo registra y un reconciliador programado lo reconduce | Postularse sigue funcionando con RabbitMQ caído; las candidaturas atascadas se recuperan solas | 011 |

## Tests

**184 tests** en la suite por defecto (`make test`), más **6 tests de aceptación** en un navegador real (`make acceptance`):
- *Unitarios:* el dominio, los casos de uso con dobles de los puertos, el adaptador de IA y los
  listeners.
- *Integración:* MongoDB real, el flujo asíncrono completo con un worker real de Messenger y los
  comandos de consola.
- *Funcionales:* los flujos HTTP, con CSRF incluido.
- *Aceptación:* un Chrome real contra el stack en marcha (workers, RabbitMQ y JavaScript).

**Cobertura: 99,2 % de líneas y 96,5 % de métodos.** CI falla por debajo del 90 %.

Una pasada de QA intentó romper la app a propósito (abuso por HTTP, entradas extremas, el
navegador, la caída de RabbitMQ). Los hallazgos, sus arreglos y los tests que los vigilan están
en **[docs/QA.es.md](docs/QA.es.md)**.

| Criterio de aceptación | Lo cubre |
|---|---|
| Al enviar se crea un registro con `appliedAt` y el estado por defecto | `SubmitJobApplicationHandlerTest`, `ApplyToAPositionTest`, aceptación `ApplyAndGetScreenedTest` |
| El enriquecimiento asíncrono añade resumen y score | `EnrichmentFlowTest`, aceptación `ApplyAndGetScreenedTest` (el score aparece sin recargar) |
| Reintentos, fallo definitivo y recuperación tras una caída del broker | `EnrichmentFlowTest`, `ReconcileJobApplicationsHandlerTest`, `MessengerDomainEventPublisherTest` |
| El listado va de más reciente a más antigua, con filtros y búsqueda en tiempo real | `MongoJobApplicationViewRepositoryTest`, `BrowseApplicationsTest`, aceptación `BrowseApplicationsLiveTest` |
| El detalle muestra todos los datos, enriquecimiento incluido | `ApplyToAPositionTest`, aceptación `ApplyAndGetScreenedTest` |
| Consentimiento, límite de peticiones, duplicados y cabeceras de seguridad | `ApplyToAPositionTest`, `SecurityHeadersTest`, `SubmitJobApplicationHandlerTest` |

## Con más tiempo

- **Outbox transaccional**, para que guardar en MongoDB y publicar en RabbitMQ no puedan quedar desincronizados (ADR-011).
- **Push en lugar de polling** (Mercure o SSE) para las actualizaciones de estado (ADR-015).
- **Reintentar un enriquecimiento fallido** desde la UI, como caso de uso explícito `failed → enriching` (ADR-012).
- **Un cliente de LLM real** detrás de `LlmClient`, con timeouts, límite de peticiones y control
  de coste, y la salida estructurada del proveedor alimentada con el mismo JSON Schema.
- **Autenticación y roles**, separando la zona de candidatos de la de recruiters.
- **Conservación y derecho de supresión** (RGPD): borrar o anonimizar los datos de un candidato cuando lo pida y al vencer un plazo de conservación (ADR-016).
- **Bloqueo optimista en el modelo de escritura**, cuando más de un escritor pueda tocar una candidatura. La versión ya existe (ADR-010).
- **Observabilidad:** logs estructurados con ids de correlación entre HTTP, eventos y enriquecimiento, y métricas de profundidad de cola, latencia de la IA y tasa de fallos.
- **Búsqueda a escala:** un índice de texto o Atlas Search en lugar de una regex sin anclar.
- **Eventos de integración en JSON y versionados** si otros servicios empiezan a consumir estos eventos.

## Cómo he usado la IA

Lo he construido con **Claude Code** como pair programmer, en fases cortas: plan, mi revisión y
mi OK, implementación y un resumen al final. Las fases fueron dominio, aplicación,
infraestructura, UI y documentación.

- **Planificación:** escribí el encargo (stack, arquitectura, proceso). El asistente leyó el
  ejercicio y propuso un plan, y yo tomé las decisiones que le dan forma: solo MongoDB en lugar
  de MySQL más MongoDB, Tailwind sobre mis propios design tokens y documentación bilingüe.
- **Implementación:** el asistente escribió el código siguiendo las convenciones de mis propios
  servicios Symfony, así que el resultado se lee como código que mantengo. Revisé cada fase antes
  de pasar a la siguiente.
- **Verificación:** no se aceptó nada por fe. Los tests corren contra MongoDB real, el flujo
  asíncrono se probó de punta a punta contra RabbitMQ real y la UI se revisó en el navegador en
  modo claro, oscuro y móvil. Así salieron y se corrigieron varios problemas:
  - MongoDB 8 no arranca con los kernels actuales de Docker Desktop;
  - Symfony 8.0 está bloqueado por avisos de seguridad;
  - en los tests, los transports en memoria aplican los retardos de reintento y se vacían entre peticiones.
- **Seguridad:** durante el ejercicio salió una release crítica de seguridad de PHP (php2shell).
  El asistente investigó los avisos, comprobó que la imagen base seguía siendo vulnerable y añadió
  la mitigación y la salvaguarda que se describen en el ADR-004.
- **Revisión:** antes de entregar, pedí una revisión de todo el código con ojos de CTO y después
  arreglé todo lo que encontró, cada punto con un test cuando tenía sentido:
  - el CI fallaba con un clon limpio;
  - una afirmación documentada sobre la prioridad de los workers no era cierta;
  - las proyecciones podían retroceder con workers en paralelo;
  - faltaban el límite de peticiones y el consentimiento en un formulario público;
  - no había CSP;
  - el polling generaba ruido de accesibilidad;
  - y pulidos menores.
- **QA:** una pasada de QA intentó romper la app (abuso por HTTP, entradas extremas, el navegador,
  la caída del broker) y encontró 10 fallos; el peor, un 500 y una candidatura huérfana con
  RabbitMQ caído. Se arreglaron los 10 con tests de regresión, y se añadieron una suite de
  aceptación en navegador y un control de cobertura (docs/QA.es.md).
- **Documentación:** `docs/DECISIONS.es.md` se escribió a la vez que el código, una entrada por decisión.
