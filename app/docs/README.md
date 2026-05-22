## Auth (prisijungimas / paskyra)

### `POST /api/login`

- **Ką daro**: prisijungia el. paštu + slaptažodžiu ir grąžina Sanctum tokeną (arba 2FA challenge, jei reikia kodo).
- **Web puslapis**: `/login`
- **Endpoint**: `POST /api/login`

### `POST /api/two-factor/challenge`

- **Ką daro**: patvirtina 6 skaitmenų 2FA kodą ir grąžina galutinį tokeną.
- **Web puslapis**: `/two-factor-challenge`
- **Endpoint**: `POST /api/two-factor/challenge`

### `POST /api/register`

- **Ką daro**: užregistruoja naują pareiškėją el. paštu arba užbaigia VIISP registraciją.
- **Web puslapis**: `/register`
- **Endpoint**: `POST /api/register`

### `POST /api/logout`

- **Ką daro**: atšaukia dabartinį Sanctum tokeną.
- **Web puslapis**: `POST /logout` (header'io meniu)
- **Endpoint**: `POST /api/logout`

### `GET /api/me`

- **Ką daro**: grąžina prisijungusio vartotojo profilį, roles, teises, VIISP statusą ir push registraciją.
- **Web puslapis**: nėra atskiro (Inertia perduoda globaliai)
- **Endpoint**: `GET /api/me`

### `DELETE /api/me`

- **Ką daro**: archyvuoja dabartinio vartotojo paskyrą.
- **Web puslapis**: `/settings` (paskyros ištrynimo modalas)
- **Endpoint**: `DELETE /api/me`

### `POST /api/me/push-device`

- **Ką daro**: užregistruoja Firebase push device tokeną mobile aplikacijai.
- **Web puslapis**: nėra (tik mobile)
- **Endpoint**: `POST /api/me/push-device`

### `POST /api/me/push-test`

- **Ką daro**: išsiunčia test push pranešimą dabartiniam vartotojui.
- **Web puslapis**: nėra (tik debug)
- **Endpoint**: `POST /api/me/push-test`

---

## VIISP (el. valdžios vartai)

### `POST /api/viisp/init`

- **Ką daro**: paruošia VIISP autentifikaciją ir grąžina nukreipimo URL.
- **Web puslapis**: `GET /viisp/redirect`
- **Endpoint**: `POST /api/viisp/init`

### `GET /api/viisp/callback`

- **Ką daro**: priima VIISP callback po vartotojo autentifikacijos.
- **Web puslapis**: `GET /viisp/callback`
- **Endpoint**: `GET /api/viisp/callback`

### `POST /api/viisp/exchange`

- **Ką daro**: keičia VIISP callback kodą į Sanctum tokeną (naujam vartotojui grąžina `registration_required`).
- **Web puslapis**: `/viisp/register/...` (formos srautas)
- **Endpoint**: `POST /api/viisp/exchange`

---

## Settings (paskyros nustatymai)

### `PUT /api/settings/password`

- **Ką daro**: pakeičia arba sukuria slaptažodį (VIISP pirmąkart - be `current_password`).
- **Web puslapis**: `/settings/change-password`
- **Endpoint**: `PUT /api/settings/password`

### `PATCH /api/setting/email_notifications`

- **Ką daro**: įjungia/išjungia el. pašto pranešimus apie savo ir stebimas problemas.
- **Web puslapis**: `/settings` (Privatumo blokas)
- **Endpoint**: `PATCH /api/setting/email_notifications`

### `PATCH /api/settings/two-factor`

- **Ką daro**: vidinis vartotojas išjungia/įjungia 2FA sau (galima tik kai admin globaliai įjungė; pareiškėjui — 422).
- **Web puslapis**: `/settings` (Privatumo bloko 2FA toggle)
- **Endpoint**: `PATCH /api/settings/two-factor`

### `PATCH /api/settings/profile`

- **Ką daro**: atnaujina prisijungusio vartotojo profilį (vardas, pavardė, el. paštas); VIISP vartotojui name/surname ignoruojami.
- **Web puslapis**: `/settings` (Profilio blokas) — `PATCH /settings/profile`
- **Endpoint**: `PATCH /api/settings/profile`

---

## Info (vieši duomenys be auth)

### `GET /api/info/statistics`

- **Ką daro**: grąžina viešos statistikos JSON (problemų skaičiai pagal kategorijas, statusus, vietą).
- **Web puslapis**: `/statistika`
- **Endpoint**: `GET /api/info/statistics`

### `GET /api/info/map`

- **Ką daro**: grąžina viešus žemėlapio markerius (tik `Nagrinėjama` + `Išspręsta`).
- **Web puslapis**: `/reports`
- **Endpoint**: `GET /api/info/map`

### `GET /api/content/{type}`

- **Ką daro**: grąžina paskelbtą turinį (`faq`, `privacy`, `cookies`, `contacts`, `help`, `terms`).
- **Web puslapis**: `/faq`, `/privacy`, `/slapukai`, `/contacts`, `/help`, `/taisykles`
- **Endpoint**: `GET /api/content/{type}`

### `GET /api/info/file-upload-settings`

- **Ką daro**: grąžina failų įkėlimo apribojimus naršyklei ir mobiliajai programėlei (max dydis, kiekiai, leidžiami formatai, `accept_attr`, aprašymo ilgis).
- **Web puslapis**: `GET /file-upload-settings`
- **Endpoint**: `GET /api/info/file-upload-settings`
- **Atsakymo struktūra**:

```json
{
  "web": {
    "allow_photo": true,
    "allow_video": true,
    "max_photo_size_kb": 5120,
    "max_video_size_kb": 51200,
    "max_photo_files_per_problem": 5,
    "max_video_files_per_problem": 1,
    "max_video_duration_seconds": 60,
    "description_max_chars": 65535,
    "photo_formats": [
      { "label": "JPEG", "mime": "image/jpeg", "extensions": ["jpg", "jpeg"] },
      { "label": "PNG", "mime": "image/png", "extensions": ["png"] }
    ],
    "video_formats": [
      { "label": "MP4", "mime": "video/mp4", "extensions": ["mp4"] },
      { "label": "WebM", "mime": "video/webm", "extensions": ["webm"] },
      { "label": "MOV", "mime": "video/quicktime", "extensions": ["mov"] }
    ],
    "accept_attr": "image/jpeg,image/png,video/mp4,video/webm,video/quicktime"
  },
  "mobile": {
    "allow_photo": true,
    "allow_video": true,
    "max_photo_size_kb": 5120,
    "max_video_size_kb": 51200,
    "max_photo_files_per_problem": 5,
    "max_video_files_per_problem": 1,
    "max_video_duration_seconds": 60,
    "description_max_chars": 65535,
    "photo_formats": [
      { "label": "JPEG", "mime": "image/jpeg", "extensions": ["jpg", "jpeg"] },
      { "label": "PNG", "mime": "image/png", "extensions": ["png"] }
    ],
    "video_formats": [
      { "label": "MP4", "mime": "video/mp4", "extensions": ["mp4"] },
      { "label": "WebM", "mime": "video/webm", "extensions": ["webm"] },
      { "label": "MOV", "mime": "video/quicktime", "extensions": ["mov"] }
    ],
    "accept_attr": "image/jpeg,image/png,video/mp4,video/webm,video/quicktime"
  }
}
```

---

## Problems (vieša detalė)

### `GET /api/problems`

- **Ką daro**: grąžina viešų problemų sąrašą (be filtravimo pagal rolę).
- **Web puslapis**: `/reports` (žemėlapio sąrašo dalis)
- **Endpoint**: `GET /api/problems`

### `GET /api/problems/{problem}`

- **Ką daro**: grąžina vienos viešos problemos detalę su priedais.
- **Web puslapis**: `/reports/{problem}`
- **Endpoint**: `GET /api/problems/{problem}`

---

## Reporter (Pareiškėjas - prisijungęs)

### `POST /api/problems`

- **Ką daro**: sukuria naują problemą (Tipas, Pobūdis, aprašymas, adresas, koordinatės, failai).
- **Web puslapis**: `/reporter/report`
- **Endpoint**: `POST /api/problems`

### `GET /api/reporter/problems`

- **Ką daro**: grąžina pareiškėjo savo problemų sąrašą su filtrais (paieška, kategorija, datos).
- **Web puslapis**: `/reporter/sarasas`
- **Endpoint**: `GET /api/reporter/problems`

### `GET /api/reporter/statistics`

- **Ką daro**: grąžina pareiškėjo asmeninę statistiką (mėnesiniai grafikai, top problemos).
- **Web puslapis**: `/reporter/statistics`
- **Endpoint**: `GET /api/reporter/statistics`

### `GET /api/reporter/map`

- **Ką daro**: grąžina pareiškėjo žemėlapį - viešas + savo problemas (`only_mine=1` filtras tik savo).
- **Web puslapis**: `/dashboard` (reporter-only vartotojui)
- **Endpoint**: `GET /api/reporter/map`

### `GET /api/reporter/problems/{problem}`

- **Ką daro**: grąžina vienos pareiškėjo problemos detalę su priedais ir merge kontekstu.
- **Web puslapis**: `/reports/{problem}` arba `/problems/{problem}` (`Reporter/ProblemShow.vue`)
- **Endpoint**: `GET /api/reporter/problems/{problem}`

### `GET /api/reporter/report-meta`

- **Ką daro**: grąžina meta duomenis problemos kūrimo formai (Tipo + Pobūdžio klasifikatoriai, upload kanalas).
- **Web puslapis**: `/reporter/report` (perduodama per Inertia props)
- **Endpoint**: `GET /api/reporter/report-meta`

### `POST /api/reporter/wizard-dismissed`

- **Ką daro**: pažymi, kad pareiškėjas atmetė pradžios wizard'ą.
- **Web puslapis**: `/reporter/report` (wizard modalas)
- **Endpoint**: `POST /api/reporter/wizard-dismissed`

### `POST /api/reporter/report-terms-accepted`

- **Ką daro**: pažymi, kad pareiškėjas sutiko su pranešimo taisyklėmis.
- **Web puslapis**: `/reporter/report` (taisyklių modalas)
- **Endpoint**: `POST /api/reporter/report-terms-accepted`

### `POST /api/problems/{problem}/watch`

- **Ką daro**: įjungia/išjungia problemos stebėjimą („Stebėti eigą").
- **Web puslapis**: `/dashboard`, `/reports/{problem}`
- **Endpoint**: `POST /api/problems/{problem}/watch`

### `POST /api/problems/{problem}/attachments`

- **Ką daro**: prideda foto/video failus prie pareiškėjo savo problemos.
- **Web puslapis**: `/reports/{problem}` (Reporter/ProblemShow.vue)
- **Endpoint**: `POST /api/problems/{problem}/attachments`

### `DELETE /api/problems/{problem}/attachments/{attachment}`

- **Ką daro**: ištrina pareiškėjo savo problemos priedą.
- **Web puslapis**: `/reports/{problem}` (Reporter/ProblemShow.vue)
- **Endpoint**: `DELETE /api/problems/{problem}/attachments/{attachment}`

### `GET /api/problems/{problem}/review-progress`

- **Ką daro**: grąžina viešus nagrinėjimo eigos įrašus (tai, ką staff pažymėjo „Vieša").
- **Web puslapis**: `/problems/{problem}/review-progress` (reporter matymas)
- **Endpoint**: `GET /api/problems/{problem}/review-progress`

---

## Internal (vidiniai vartotojai - staff, admin)

### `GET /api/internal/institutions`

- **Ką daro**: grąžina priskyrimui prieinamų įstaigų sąrašą su kontaktais.
- **Web puslapis**: `/problems/assign` (Assign.vue)
- **Endpoint**: `GET /api/internal/institutions`

### `GET /api/internal/problems`

- **Ką daro**: grąžina staff problemų sąrašą su filtrais, sort'u, paginacija (pagal rolės scope).
- **Web puslapis**: `/dashboard` (staff/admin matymas)
- **Endpoint**: `GET /api/internal/problems`

### `PUT|POST /api/internal/problems/{problem}`

- **Ką daro**: redaguoja problemos info ir tuo pačiu requestu prideda naujus failus.
- **Web puslapis**: `/problems/{problem}` (info update + atskiras attachment call)
- **Endpoint**: `PUT|POST /api/internal/problems/{problem}`

### `DELETE /api/internal/problems/{problem}`

- **Ką daro**: archyvuoja problemą su visa merge grupe.
- **Web puslapis**: `/dashboard`, `/problems/{problem}`
- **Endpoint**: `DELETE /api/internal/problems/{problem}`

### `POST /api/internal/problems/{problem}/attachments`

- **Ką daro**: prideda failus prie problemos (be info update'o), tik staff.
- **Web puslapis**: `/problems/{problem}` (Show.vue)
- **Endpoint**: `POST /api/internal/problems/{problem}/attachments`

### `DELETE /api/internal/problems/{problem}/attachments/{attachment}`

- **Ką daro**: ištrina problemos priedą, tik staff.
- **Web puslapis**: `/problems/{problem}` (Show.vue)
- **Endpoint**: `DELETE /api/internal/problems/{problem}/attachments/{attachment}`

### `PATCH /api/internal/problems/{problem}/status`

- **Ką daro**: keičia problemos ir visos merge grupės statusą.
- **Web puslapis**: `/problems/{problem}` (Show.vue)
- **Endpoint**: `PATCH /api/internal/problems/{problem}/status`

### `PATCH /api/internal/problems/{problem}/publish`

- **Ką daro**: viešina/nuima problemą nuo viešumo (Viešai toggle).
- **Web puslapis**: `/dashboard`, `/problems/{problem}`
- **Endpoint**: `PATCH /api/internal/problems/{problem}/publish`

### `POST /api/internal/problems/{problem}/merge`

- **Ką daro**: sujungia problemą su panašia (duplikatu).
- **Web puslapis**: `/problems/{problem}/merge`
- **Endpoint**: `POST /api/internal/problems/{problem}/merge`

### `PATCH /api/internal/problems/{problem}/unmerge`

- **Ką daro**: atskiria sujungtą problemą iš grupės.
- **Web puslapis**: `/problems/{problem}/merge`
- **Endpoint**: `PATCH /api/internal/problems/{problem}/unmerge`

### `POST /api/internal/problems/assign`

- **Ką daro**: bulk priskiria pažymėtas problemas įstaigai (su el. laišku ir/ar GIS).
- **Web puslapis**: `/problems/assign`
- **Endpoint**: `POST /api/internal/problems/assign`

### `GET /api/internal/problems/{problem}/assign-institution-options`

- **Ką daro**: grąžina priskyrimo formos parinktis (kategorijos, įstaigos, automatizacijos rekomendacijos).
- **Web puslapis**: `/problems/{problem}/assign-institution`
- **Endpoint**: `GET /api/internal/problems/{problem}/assign-institution-options`

### `POST /api/internal/problems/{problem}/assign-institution`

- **Ką daro**: priskiria vienai problemai įstaigą.
- **Web puslapis**: `/problems/{problem}/assign-institution`
- **Endpoint**: `POST /api/internal/problems/{problem}/assign-institution`

### `POST /api/internal/problems/{problem}/reassign-institution`

- **Ką daro**: keičia jau priskirtos problemos įstaigą.
- **Web puslapis**: `/problems/{problem}/assign-institution`
- **Endpoint**: `POST /api/internal/problems/{problem}/reassign-institution`

### `PATCH /api/internal/problems/{problem}/return`

- **Ką daro**: grąžina problemą iš įstaigos atgal į admin eilę (statusas → `Grąžinta nagrinėjimui`).
- **Web puslapis**: `/problems/{problem}/assign-institution`
- **Endpoint**: `PATCH /api/internal/problems/{problem}/return`

### `GET /api/internal/problems/{problem}/review-progress`

- **Ką daro**: grąžina VISUS eigos įrašus (vieši + vidiniai), tik staff.
- **Web puslapis**: `/problems/{problem}/review-progress` (staff matymas)
- **Endpoint**: `GET /api/internal/problems/{problem}/review-progress`

### `POST /api/internal/problems/{problem}/review-progress`

- **Ką daro**: prideda naują eigos įrašą (komentaras, statusas, atsakingas, viešumas, priedai).
- **Web puslapis**: `/problems/{problem}/review-progress` (Add Entry modalas)
- **Endpoint**: `POST /api/internal/problems/{problem}/review-progress`

### `PUT|POST /api/internal/problems/{problem}/review-progress/{entry}`

- **Ką daro**: redaguoja eigos įrašą (Admin visus, staff tik savo); POST multipart leidžia pridėti naujus failus.
- **Web puslapis**: `/problems/{problem}/review-progress` (Edit Entry modalas)
- **Endpoint**: `PUT|POST /api/internal/problems/{problem}/review-progress/{entry}`

### `DELETE /api/internal/problems/{problem}/review-progress/attachments/{attachment}`

- **Ką daro**: ištrina vieną failą iš eigos įrašo (Admin visus, staff tik savo).
- **Web puslapis**: `/problems/{problem}/review-progress`
- **Endpoint**: `DELETE /api/internal/problems/{problem}/review-progress/attachments/{attachment}`

### `POST /api/internal/problems/bulk-update-status`

- **Ką daro**: bulk keičia pažymėtų problemų statusą.
- **Web puslapis**: `/dashboard` (bulk actions)
- **Endpoint**: `POST /api/internal/problems/bulk-update-status`

### `POST /api/internal/problems/bulk-destroy`

- **Ką daro**: bulk archyvuoja pažymėtas problemas.
- **Web puslapis**: `/dashboard` (bulk actions)
- **Endpoint**: `POST /api/internal/problems/bulk-destroy`

### `POST /api/internal/problems/bulk-update-publish`

- **Ką daro**: bulk įjungia/išjungia pažymėtų problemų viešumą.
- **Web puslapis**: `/dashboard` (bulk actions)
- **Endpoint**: `POST /api/internal/problems/bulk-update-publish`

---

## TwoFactor (System Admin 2FA valdymas)

### `GET /api/internal/two-factor/users`

- **Ką daro**: grąžina staff vartotojų sąrašą su 2FA statusu.
- **Web puslapis**: `/admin?tab=2fa`
- **Endpoint**: `GET /api/internal/two-factor/users`

### `POST /api/internal/two-factor/users/{user}`

- **Ką daro**: vienam staff vartotojui įjungia/išjungia 2FA arba reset'ina confirmation.
- **Web puslapis**: `/admin?tab=2fa`
- **Endpoint**: `POST /api/internal/two-factor/users/{user}`

### `POST /api/internal/two-factor/global`

- **Ką daro**: globaliai įjungia/išjungia 2FA reikalavimą visam staff.
- **Web puslapis**: `/admin?tab=2fa`
- **Endpoint**: `POST /api/internal/two-factor/global`

### `POST /api/internal/two-factor/disable-all-staff`

- **Ką daro**: visiems staff'ui išjungia 2FA (admin įrankis).
- **Web puslapis**: `/admin?tab=2fa`
- **Endpoint**: `POST /api/internal/two-factor/disable-all-staff`
