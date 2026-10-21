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

**Contexto.** El 20 de octubre de 2026 el equipo de seguridad de PHP preanunció una release
para el **21 de octubre de 2026 a las 14:00 UTC**. Corrige dos vulnerabilidades críticas, ambas
con CVSS v4.0 9.2. Los investigadores las identifican como CVE-2026-109618 y CVE-2026-109619
(«php2shell», ejecución remota de código en el núcleo de PHP). Los detalles se filtraron antes
de la release y esa misma mañana ya se informó de los primeros ataques contra sitios PHP.

**Estado al escribir esta entrada (21-10-2026, 13:35 UTC).** El parche aún no está publicado.
La imagen se construye desde `php:8.4-fpm`, que ahora mismo resuelve a **PHP 8.4.26
(24-09-2026), así que no está parcheada**. En Docker Hub solo existe `8.4.27RC1`.

**Decisión.**
- En cuanto salga la versión corregida, se fija la versión exacta en `docker/php/Dockerfile`
  (en lugar de la etiqueta flotante `8.4`), para que una imagen cacheada no siga en silencio con
  la versión vulnerable.
- CI comprueba la versión de PHP de la imagen contra ese mínimo y falla si es inferior.

**Por qué importa aquí.** La aplicación es un formulario público que acepta texto libre,
justo el tipo de superficie que atacan estas vulnerabilidades. Fijar la versión del parche
deja explícito el nivel de seguridad y permite revisarlo en un diff, en lugar de depender de
cuándo se descargó la etiqueta por última vez.
