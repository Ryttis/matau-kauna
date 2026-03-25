# OpenAPI / Swagger (API mode)

`openapi.yaml` aprašo šios savaitės **JSON API** (`/api/*`), ne web (`/login`, `/reports`) puslapius.

## Scope

- auth: `POST /api/login`, `POST /api/register`, `GET /api/me`, `POST /api/logout`
- problems: `GET /api/problems`, `GET /api/problems/{problem}`, `POST /api/problems`
- info: `GET /api/info/statistics`, `GET /api/info/file-upload-settings`

## Paleidimas

```bash
docker compose up -d
docker compose restart kauno_problemos_nginx swagger
```

Swagger UI: **http://localhost/swagger/**

## Kaip testuoti per Swagger

1. Atidaryk `POST /api/login` ir gausi `token`.
2. Swagger viršuje spausk **Authorize**.
3. Įvesk: `Bearer <token>` ir patvirtink.
4. Testuok protected endpointus (`/api/me`, `POST /api/problems`, `/api/info/file-upload-settings`).

## Pastabos

- Šita schema skirta API integracijai, todėl atsakymai yra JSON.
- Jei nori sukurti vartotoją per API, naudok `POST /api/register`.
