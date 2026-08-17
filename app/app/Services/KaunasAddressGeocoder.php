<?php

namespace App\Services;

use App\Exceptions\GeocoderUnavailableException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Serverinis Kauno adresų geokoderis.
 *
 * Taisyklės tyčia atkartoja resources/js/composables/useKaunasMapLocationPicker.js,
 * kad naršyklė ir serveris niekada negalėtų nesutarti dėl to, kas yra galiojantis adresas.
 */
class KaunasAddressGeocoder
{
    /**
     * Atsarginės reikšmės, jei config/kaunas.php dar neperskaitytas
     * (pvz. serveryje liko senas config:cache).
     */
    private const DEFAULT_BOUNDS = [
        'min_lat' => 54.75,
        'min_lng' => 23.65,
        'max_lat' => 55.05,
        'max_lng' => 24.15,
    ];

    private const DEFAULT_MAX_LENGTH = 255;

    private const DEFAULT_MIN_LENGTH = 5;

    private const DEFAULT_DISTRICT_MARKER = 'rajono savivaldyb';

    /**
     * @return array{min_lat: float, min_lng: float, max_lat: float, max_lng: float}
     */
    public function bounds(): array
    {
        $bounds = config('kaunas.bounds');
        $bounds = is_array($bounds) ? array_merge(self::DEFAULT_BOUNDS, $bounds) : self::DEFAULT_BOUNDS;

        return array_map(fn ($value) => (float) $value, $bounds);
    }

    public function maxAddressLength(): int
    {
        return (int) (config('kaunas.address.max_length') ?: self::DEFAULT_MAX_LENGTH);
    }

    public function minAddressLength(): int
    {
        return (int) (config('kaunas.address.min_length') ?: self::DEFAULT_MIN_LENGTH);
    }

    /**
     * @return array{address: string, display_name: string, lat: float, lng: float}|null
     *
     * @throws GeocoderUnavailableException
     */
    public function search(string $query): ?array
    {
        $query = $this->normalize($query);
        if ($query === '') {
            return null;
        }

        $rows = $this->request('search', [
            'q' => $query.', Kaunas',
            'limit' => 5,
            'viewbox' => $this->viewbox(),
            'bounded' => 0,
        ]);

        foreach ($rows as $row) {
            if (! is_array($row) || ! isset($row['lat'], $row['lon'])) {
                continue;
            }

            $lat = (float) $row['lat'];
            $lng = (float) $row['lon'];

            if ($this->isInKaunasBounds($lat, $lng) && $this->isKaunasCityAddress($row['display_name'] ?? null)) {
                return $this->candidate($row, $lat, $lng);
            }
        }

        return null;
    }

    /**
     * @return array{address: string, display_name: string, lat: float, lng: float}|null
     *
     * @throws GeocoderUnavailableException
     */
    public function reverse(float $lat, float $lng): ?array
    {
        $row = $this->request('reverse', [
            'lat' => $lat,
            'lon' => $lng,
        ]);

        if (! isset($row['display_name']) && ! isset($row['address'])) {
            return null;
        }

        return $this->candidate($row, $lat, $lng);
    }

    public function isInKaunasBounds(float $lat, float $lng): bool
    {
        $bounds = $this->bounds();

        return $lat >= $bounds['min_lat']
            && $lat <= $bounds['max_lat']
            && $lng >= $bounds['min_lng']
            && $lng <= $bounds['max_lng'];
    }

    /**
     * Kauno rajono savivaldybė patenka į miesto koordinačių stačiakampį,
     * todėl ją atskiria tik geokoderio grąžintas pavadinimas.
     */
    public function isKaunasCityAddress(?string $displayName): bool
    {
        $marker = (string) (config('kaunas.geocoder.district_marker') ?: self::DEFAULT_DISTRICT_MARKER);

        return ! str_contains(mb_strtolower((string) $displayName), $marker);
    }

    /**
     * Struktūrinė patikra be tinklo: atmeta tokius įrašus kaip "LAUMĖNŲ,SODYBŲ".
     * Tikras adresas turi arba tarpą (gatvė + rajonas / miestas), arba namo numerį.
     */
    public function looksLikeAddress(string $address): bool
    {
        $address = $this->normalize($address);

        if (mb_strlen($address) < $this->minAddressLength()) {
            return false;
        }

        if (! preg_match('/\p{L}/u', $address)) {
            return false;
        }

        return preg_match('/\s/u', $address) === 1 || preg_match('/\d/u', $address) === 1;
    }

    public function normalize(string $address): string
    {
        $address = preg_replace('/\s+/u', ' ', trim($address)) ?? '';

        return mb_substr($address, 0, $this->maxAddressLength());
    }

    /**
     * resources/js/composables/useKaunasMapLocationPicker.js formatReverseAddress() atitikmuo.
     */
    public function formatAddress(array $row): string
    {
        $address = is_array($row['address'] ?? null) ? $row['address'] : [];

        $road = $this->firstFilled($address, ['road', 'pedestrian', 'footway', 'cycleway', 'path', 'square']);
        $house = ($road !== '' && ($address['house_number'] ?? '') !== '') ? ' '.$address['house_number'] : '';
        $area = $this->firstFilled($address, ['suburb', 'neighbourhood', 'city_district', 'quarter', 'residential']);
        $city = $this->firstFilled($address, ['city', 'town', 'municipality']) ?: 'Kaunas';

        $parts = [];
        if ($road !== '') {
            $parts[] = $road.$house;
        }
        if ($area !== '') {
            $parts[] = $area;
        }
        $parts[] = $city;

        return implode(', ', $parts);
    }

    /**
     * @return array{address: string, display_name: string, lat: float, lng: float}
     */
    private function candidate(array $row, float $lat, float $lng): array
    {
        $formatted = $this->formatAddress($row);

        return [
            'address' => $this->normalize($formatted !== '' ? $formatted : (string) ($row['display_name'] ?? '')),
            'display_name' => (string) ($row['display_name'] ?? ''),
            'lat' => $lat,
            'lng' => $lng,
        ];
    }

    private function firstFilled(array $address, array $keys): string
    {
        foreach ($keys as $key) {
            $value = trim((string) ($address[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * Nominatim tvarka: minLon,maxLat,maxLon,minLat.
     */
    private function viewbox(): string
    {
        $bounds = $this->bounds();

        return implode(',', [
            $bounds['min_lng'],
            $bounds['max_lat'],
            $bounds['max_lng'],
            $bounds['min_lat'],
        ]);
    }

    /**
     * @throws GeocoderUnavailableException
     */
    private function request(string $path, array $query): array
    {
        $config = array_merge([
            'base_url' => 'https://nominatim.openstreetmap.org',
            'user_agent' => 'MatauKaune/1.0 (+https://matau.kaunas.lt)',
            'timeout' => 6,
            'cache_ttl' => 604800,
        ], is_array(config('kaunas.geocoder')) ? config('kaunas.geocoder') : []);

        $query = array_merge($query, ['format' => 'json', 'addressdetails' => 1]);
        $cacheKey = 'kaunas.geocoder.'.$path.'.'.md5(json_encode($query));

        $cached = $this->cacheGet($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        try {
            $response = Http::timeout((int) $config['timeout'])
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Accept-Language' => 'lt',
                    'User-Agent' => (string) $config['user_agent'],
                ])
                ->get(rtrim((string) $config['base_url'], '/').'/'.$path, $query);
        } catch (Throwable $exception) {
            throw new GeocoderUnavailableException('Adresų paslauga nepasiekiama.', 0, $exception);
        }

        if (! $response->successful()) {
            throw new GeocoderUnavailableException('Adresų paslauga grąžino klaidą.');
        }

        $data = $response->json();
        if (! is_array($data)) {
            throw new GeocoderUnavailableException('Netinkamas adresų paslaugos atsakymas.');
        }

        $this->cachePut($cacheKey, $data, (int) $config['cache_ttl']);

        return $data;
    }

    /**
     * Kešas čia tik optimizacija – neveikianti saugykla neturi griauti pateikimo.
     */
    private function cacheGet(string $key): mixed
    {
        try {
            return Cache::get($key);
        } catch (Throwable) {
            return null;
        }
    }

    private function cachePut(string $key, array $value, int $ttl): void
    {
        try {
            Cache::put($key, $value, $ttl);
        } catch (Throwable) {
            // Tyliai praleidžiama: adresas jau gautas, kešavimas nebūtinas.
        }
    }
}
