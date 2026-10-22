# isuDev Reviews: plan

> Wspólne zasady dla wszystkich pluginów isuDev (model, Paddle, licencje, API, konto) są w skillu `isudev-plugins` (`~/Desktop/agents/skills/isudev-plugins/`). Ten plik opisuje szczegóły pluginu Reviews i historię decyzji.

Nowoczesny następca „WP Google Review Slider”: recenzje Google i Facebook zapisywane lokalnie we własnych tabelach, wyświetlane blokami Gutenberga, monetyzowane przez szablony Pro i auto-sync.

## 1. Wnioski z referencyjnego pluginu

- Ich **„darmowy crawler” i tak idzie przez ich serwer** (`crawl.ljapps.com` → DataForSEO, z fallbackiem na `ocean.ljapps.com`). Plugin WP sam nic nie scrapuje.
- Google Maps renderuje się w JS, a z IP hostingów jest blokowane. Scraper zaszyty w pluginie psułby się u klientów bez możliwości szybkiej poprawki (każdy fix = release + aktualizacja na wszystkich stronach), a do tego narusza wytyczne WP.org.
- **Decyzja:** całe scrapowanie i integracje z API żyją **w naszym middleware**. Plugin rozmawia tylko z jednym, stabilnym kontraktem JSON. Gdy Google coś zmieni, poprawiamy serwer, a pluginów nie trzeba aktualizować.
- Mają 2 tabele (`wpfb_reviews` i `wpfb_post_templates`) z ~40 kolumnami varchar i `hide = 'yes'`. U nas jest czysty schemat, indeksy i typy liczbowe.

## 2. Architektura

```
┌──────────── WordPress (plugin isudev-reviews) ────────────┐        ┌──── Middleware (PHP) ────┐
│ Admin: Recenzje · Źródła · Szablony · Ustawienia          │        │ /v1/license              │
│ Sync_Service ── Provider (interface)                      │ HTTPS  │ /v1/reviews/fetch        │
│   ├─ Google_Places_Provider (własny klucz, 5 recenzji) ───┼──────► │   ├─ Google: SerpApi/DFS │
│   └─ Cloud_Provider (nasze API: free 20 / pro) ───────────┼──────► │   └─ Facebook: Graph API │
│ Tabele: {prefix}isurev_sources, {prefix}isurev_reviews    │        │ Licencje, limity, OAuth  │
│ Cron (Pro): auto-sync co N godzin                         │        └──────────────────────────┘
│ Blok isudev-reviews/reviews + rejestr szablonów           │                   ▲ (później)
└───────────────────────────────────────────────────────────┘        Panel klienta + Stripe
```

### Tryby pobierania

| Tryb | Gdzie działa | Limit | Koszt dla nas |
| --- | --- | --- | --- |
| **Szybki import (free)** | middleware → scraper/DataForSEO/SerpApi | 20 najnowszych, tylko Google, ręcznie, rate limit per domena | ~0,0x $ / import |
| **Google Places API (free)** | bezpośrednio z WP, klucz klienta | 5 recenzji (twardy limit Google) | 0 |
| **Pro (licencja)** | middleware | pełna historia, Google + Facebook, auto-sync, inkrementalny (`since`) | abonament pokrywa koszt |

Facebook jest **tylko Pro**, bo Graph API (`/{page-id}/ratings`) wymaga aplikacji Meta po App Review z uprawnieniem `pages_read_user_content`. Aplikację utrzymujemy my w middleware. Klient klika „Połącz Facebook”, przechodzi OAuth na naszym serwerze, a token strony zostaje u nas: plugin nigdy go nie widzi.

### Kontrakt API (plugin ⇄ middleware)

`POST /reviews/v1/fetch`, nagłówki `X-Isurev-Site: https://klient.pl`, `X-Isurev-Site-Token: …` (wymagany, także w free) i `Authorization: Bearer <license>` (opcjonalny, bez niego obowiązuje tier free).

```json
// request
{ "platform": "google", "external_id": "ChIJ…", "limit": 20, "since": "2026-09-01T00:00:00Z", "language": "pl" }
// response
{
  "tier": "free",
  "source": { "name": "PC Fix", "url": "https://maps.google.com/…", "rating": 4.9, "total": 132 },
  "reviews": [{
    "id": "…", "author": { "name": "", "url": "", "avatar": "" },
    "rating": 5, "recommendation": null, "text": "…", "language": "pl",
    "published_at": "2026-09-30T10:00:00Z", "url": "…",
    "reply": { "text": "…", "published_at": "…" }
  }],
  "next_since": "2026-10-15T00:00:00Z"
}
```

`GET /v1/licenses/check?product=reviews` → `{ "tier": "pro", "status": "active", "features": […], "expires_at": "…", "sites_used": 1, "sites_max": 2 }`. Licencja jest przypisana do produktu, a pierwsze użycie na domenie zajmuje miejsce (`activation_limit`, gdy plan jest pełny).

Błędy mają format `{ "error": { "code": "rate_limited", "message": "…" } }` i właściwy status HTTP.

## 3. Plugin WordPress

### Dane: własne tabele, bez CPT

`{prefix}isurev_sources`: jedno źródło to jedna wizytówka Google lub strona FB.
`id, platform, provider, external_id, name, url, rating_avg DECIMAL(3,2), rating_count, sync_interval, last_synced_at, last_status, last_error, created_at, updated_at`, `UNIQUE(platform, external_id)`.

`{prefix}isurev_reviews`:
`id, source_id, platform, external_id, author_name, author_url, author_avatar, rating TINYINT NULL, recommendation, content, language, review_url, reply_content, published_at, is_hidden, is_pinned, created_at, updated_at`, `UNIQUE(source_id, external_id)`, `KEY(source_id, is_hidden, published_at)`.

- Upsert przez `INSERT … ON DUPLICATE KEY UPDATE`: sync aktualizuje treść, ocenę i odpowiedź, ale **nie rusza** `is_hidden`/`is_pinned` (decyzje admina przetrwają sync).
- Migracje: `dbDelta()` + opcja `isurev_db_version`, sprawdzana na `plugins_loaded`.
- Średnia i liczba ocen pochodzą ze źródła (Google podaje prawdziwe `total`, a nie tylko liczbę pobranych recenzji).

### Warstwy

| Klasa | Rola |
| --- | --- |
| `Schema` | tabele, migracje, odinstalowanie |
| `Source_Repository`, `Review_Repository` | jedyne miejsca z SQL (`$wpdb->prepare`) |
| `Providers\Provider` (interface) | `fetch( array $source, array $args ): Fetch_Result` |
| `Providers\Cloud_Provider`, `Providers\Google_Places_Provider` | implementacje; filtr `isurev_providers` pozwala dodać kolejne (Trustpilot, Booking…) |
| `Sync_Service` | provider → normalizacja → upsert → meta źródła → status/błąd |
| `License` | tier i funkcje z middleware, cache 12 h, `License::has( 'auto_sync' )` |
| `Cron` | `isurev_cron_tick` co godzinę; synchronizuje źródła, którym minął interwał (tylko Pro) |
| `Templates` | rejestr szablonów (`templates/<slug>/template.json` + `card.php` + `style.css`), filtr `isurev_templates` |
| `Renderer` | wspólny render dla bloku, podglądu w adminie i (później) shortcode'u |
| `Rest_Controller` | `isudev-reviews/v1/sources`, `/templates`, `/sources/{id}/sync` dla edytora i admina |
| `Admin\*` | strony: Recenzje (WP_List_Table), Źródła, Szablony, Ustawienia |

### Blok `isudev-reviews/reviews`

- apiVersion 3, dynamiczny (`render.php`), działa w iframe'owym edytorze z WP 7.1. Style szablonów trafiają do iframe przez `enqueue_block_assets`.
- Atrybuty: `sourceIds[]`, `template` (puste = domyślny z ustawień), `layout` (`grid`|`slider`), `limit`, `minRating`, `textLength`, `showSummary`.
- Edytor: `ServerSideRender`, więc podgląd prawdziwych recenzji (lepiej niż podsumowanie ustawień w `isudev/google-reviews`), plus wybór źródeł i szablonu w sidebarze. Szablony Pro bez licencji są widoczne, ale zablokowane (upsell).
- Front: HTML zgodny z page cache, karuzela na CSS scroll-snap z ~60 liniami JS bez bibliotek (zasada isuDev: zero bibliotek UI na froncie). „Czytaj więcej” działa na `<details>`.
- Później: bloki `rating-badge` (gwiazdki + liczba ocen) i `reviews-header` (jak `isudev/google-reviews-header`), JSON-LD `AggregateRating` (uwaga: Google nie pokazuje gwiazdek dla self-serving reviews LocalBusiness, więc dajemy to tylko jako opcję).

### Monetyzacja w pluginie

- Funkcje Pro: szablony Pro, Facebook, auto-sync, >20 recenzji, wiele źródeł Google (free: 1 źródło).
- Gating zawsze na podstawie `License::has()`, a twardo pilnowane po stronie middleware (plugin to GPL i i tak da się go „odblokować”, więc wartość musi siedzieć w serwerze).
- Szablony Pro mogą żyć w pluginie (prościej) albo jako paczka pobierana po aktywacji licencji (trudniej je skopiować). Start: w pluginie.

## 4. Middleware → później system

**Faza 1 (teraz, POC):** jeden plik PHP z routerem, adapterami (`mock`, `serpapi` dla Google, `facebook` dla Graph API) i licencjami w JSON-ie. Do lokalnych testów pluginu.

**Domeny (decyzja 2026-10-17):** marketing w podkatalogu strony agencji, aplikacje na subdomenach.

- `isudev.com/plugins/<slug>/`: landing, cennik i Paddle Checkout (overlay), CPT „Plugin” w WP agencji.
- `isudev.com/docs/<slug>/`: dokumentacja.
- `accounts.isudev.com`: panel klienta i admin (`/admin`); stage `stg-accounts.isudev.com`.
- `papi.isudev.com`: API dla pluginów (domyślne `ISUREV_API_URL`); stage `stg-papi.isudev.com`. Zmiana z api./account. 2026-10-19.
- Maile transakcyjne z osobnej subdomeny (np. `mail.isudev.com` przez Resend lub Postmark), żeby nie ruszać reputacji Google Workspace na `isudev.com`.

**Płatności (decyzja 2026-10-17):** Paddle jako Merchant of Record (VAT OSS, faktury dla klientów i chargebacki po ich stronie, więc bez KSeF per klient). Webhooki Paddle są jedynym źródłem prawdy o subskrypcji, a licencje, aktywacje i limity trzymamy u siebie w middleware. Zarządzanie płatnością odbywa się w portalu Paddle. WooCommerce i Freemius odrzucone (podwójne źródło prawdy, prowizja, SDK w pluginie).

**Faza 2 (system):**
- Hosting: SEO Host (PHP + MySQL) jak reszta isuDev albo Cloudflare Workers + D1. Rekomendacja: **PHP + MySQL na SEO Host** dla spójności, z kolejką zadań (import Pro dużych wizytówek trwa, DataForSEO jest asynchroniczne: `task_post` → `task_get`).
- Tabele: `customers`, `licenses` (klucz, plan, limit aktywacji, status), `activations` (licencja ↔ domena), `sources` (z tokenami FB, szyfrowane), `fetch_log` (koszty, limity).
- Panel klienta: logowanie (magic link), licencje i aktywacje, faktury, anulowanie i zmiana planu przez **Stripe Customer Portal** (nie budujemy własnego UI płatności), webhooki Stripe → status licencji.
- Dostawca danych Google: **DataForSEO** (najtańszy przy skali, asynchroniczny) albo SerpApi (prostszy, droższy). Adapter za interfejsem, więc wymienialny.
- Aktualizacje pluginu Pro: `plugin-update-checker` (jak `isudev/consent`) lub WP.org dla wersji free.

### API: struktura i bezpieczeństwo (decyzja 2026-10-17)

- **Jeden host `papi.isudev.com`, ścieżki per produkt:** wspólne `/v1/sites/*`, `/v1/licenses/*`, `/v1/account/*` i produktowe `/<slug>/v1/*`. Produkt da się później przenieść na osobny serwer regułą proxy, bez zmiany adresów w pluginach.
- **Nikt nie korzysta anonimowo, także w free.** Plugin rejestruje stronę (`POST /v1/sites/register` z jednorazowym nonce), a API wywołuje zwrotnie `/?rest_route=/isudev-<slug>/v1/site-verify` na tej domenie. Dopiero wtedy wydaje token strony (w bazie trzymamy tylko hash). Callback ma stałą ścieżkę, nie podąża za przekierowaniami i woła tylko publiczne IP, co chroni przed SSRF.
- **Limity** (log `requests`): IP 120/min; rejestracja 10/h na IP; free 5 importów na stronę na dobę i 30 na IP na dobę (farmy domen); Pro 100/doba na stronę (auto-sync to kilka). Limity liczą tylko udane wywołania, więc odrzucone requesty nie przedłużają blokady.
- **Log każdego requestu:** produkt, trasa, status, kod błędu, czas, IP, host, tier, odcisk licencji (nie klucz) i liczba płatnych wywołań upstream (koszt SerpApi i Graph).
- **Operator:** `php middleware/bin/admin.php usage|errors|sites|block <host>|unblock <host>`. Docelowo te same zapytania jako zakładka w `accounts.isudev.com/admin`.
- **Sekrety per produkt:** `config('SERPAPI_KEY', 'reviews')` sprawdza kolejno env `REVIEWS_SERPAPI_KEY`, `config.local.php['reviews']`, a potem wartość globalną.
- **Gotowe narzędzia zamiast własnych:** przed API stoi Cloudflare (WAF, DDoS, bot management, rate limiting na brzegu, `TRUST_CF=1` dla prawdziwego IP), błędy aplikacji idą do Sentry, a logi serwera do Better Stack lub Axiom (alerty). Własny jest tylko log `requests`, bo to dane biznesowe: limity, koszty i rozliczenia.

## 5. Ryzyka

- **Regulamin Google:** Places API zabrania trwałego cache'owania treści poza `place_id`. Referencyjny plugin i tak zapisuje recenzje. Wybór źródła danych i treść regulaminu to decyzja biznesowa: warto ją podjąć świadomie.
- **App Review Meta** dla `pages_read_user_content` trwa tygodnie, więc trzeba to zacząć wcześnie.
- **RODO:** zapisujemy imiona i awatary autorów. Awatary docelowo pobieramy lokalnie (bez hotlinkowania do Google, co jest też szybsze), a do dokumentacji dochodzi informacja dla polityki prywatności (`wp_add_privacy_policy_content`).

## 6. POC: stan i uruchomienie

```
isudev-reviews/   plugin WP (PHP 8.2+, WP 6.9+, blok apiVersion 3)
middleware/       API POC (PHP, bez zależności)
```

Uruchomienie lokalne:

```bash
ALLOW_PRIVATE_HOSTS=1 php -S 127.0.0.1:8787 -t middleware/public
```

```bash
cd isudev-reviews && nvm use && npm i && npm run build && composer install
```

Potem w WP: Reviews › Settings › Cloud API URL = `http://127.0.0.1:8787`. Demo-licencja Pro jest w `middleware/data/licenses.json`. Prawdziwe dane: `middleware/config.local.php` (wzór w `config.local.example.php`), sekcja `reviews`: `ADAPTER => live`, `SERPAPI_KEY`, `FB_PAGE_TOKEN`. Bez `ALLOW_PRIVATE_HOSTS=1` lokalne strony (`*.local`) nie przejdą weryfikacji domeny.

Zweryfikowane w WP 7.1 (Playground): free import 20 recenzji, deduplikacja przy ponownym syncu, przejście free → Pro z pełnym backfillem, a potem sync inkrementalny, lista z ukrywaniem, galeria szablonów z podglądem, blok w iframe'owym edytorze (SSR i sidebar), front grid i karuzela, mobile 390px. Lint: phpcs (WPCS), ESLint, Stylelint.

Nie testowane: prawdziwe SerpApi i Facebook Graph (brak kluczy), WP-Cron tick, Google Places provider (wymaga klucza).

## 7. Roadmapa

1. **POC (to repo):** tabele, providery Cloud + Places, sync ręczny, lista recenzji z ukrywaniem, wybór szablonu, blok z szablonem `default` + zablokowanym `spotlight` (Pro), cron, middleware-mock.
2. **MVP pluginu:** lokalne awatary, pin/sortowanie, filtry (min. ocena, tylko z tekstem, słowa wykluczone), badge + header, i18n pl_PL, testy PHPUnit dla `Sync_Service`/repozytoriów, Plugin Check.
3. **Middleware v1:** DataForSEO + kolejka, licencje w DB, rate limiting, Stripe, panel klienta.
4. **Facebook:** aplikacja Meta, OAuth flow, App Review.
5. **Pro szablony:** 4–6 wariantów (masonry, spotlight, minimal, dark card, „Google-like”), edytor wyglądu przez `theme.json`/zmienne CSS zamiast pól z kolorami.
