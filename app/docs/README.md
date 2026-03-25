# Dokumentacija: OpenAPI (Swagger)

## Kas čia yra

- **`openapi.yaml`** — OpenAPI 3.0 aprašas visiems Laravel maršrutams (Inertia/HTML ir JSON atsakymai).
- Failas laikomas čia: `app/docs/openapi.yaml` (nuo repozitorijos šaknies: `kauno-problemos/app/docs/`).

## Kaip atsidaryti Swagger UI (rekomenduojama)

Naudok integraciją per pagrindinį **docker-compose** (nginx ant 80 porto, proxy į Swagger konteinerį).

1. Repozitorijos šaknyje (`kauno-problemos/`, kur yra `docker-compose.yml`):

```bash
docker compose up -d
docker compose restart kauno_problemos_nginx swagger
```

2. Naršyklėje: **http://localhost/swagger/**

Swagger ir API yra tame pačiame origin (`http://localhost`), todėl „Try it out“ nėra blokuojamas dėl CORS.

Po `openapi.yaml` pakeitimų pakanka perkrauti Swagger puslapį; jei spec neatsinaujina, paleisk `docker compose restart swagger`.

## Alternatyva: tik Swagger atskirai (8081)

Jei nori tik dokumentacijos be pilno compose:

```bash
cd app
docker run --rm -p 8081:8080 \
  -e SWAGGER_JSON=/tmp/openapi.yaml \
  -v "$(pwd)/docs/openapi.yaml:/tmp/openapi.yaml:ro" \
  swaggerapi/swagger-ui
```

Tada: **http://localhost:8081**. API užklausos eina į kitą origin nei UI — reikia CORS; projekte tai dengia `app/config/cors.php` (localhost / 127.0.0.1 ant 8081). Patikimiau naudoti `/swagger/` variantą aukščiau.

## Prisijungę endpointai

Daliai maršrutų reikia Laravel sesijos. Tame pačiame naršyklės profilyje atidaryk aplikaciją (**http://localhost**), prisijunk, tada **http://localhost/swagger/** ir bandyk „Execute“.

## Atitikimas kodui

Spec turėtų sutapti su `php artisan route:list`. Po didelių maršrutų pakeitimų verta atnaujinti `openapi.yaml`.
