<?php

namespace Epaisay\Analytics\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GeolocationService
{
    private $apiProviders = [
        'ipapi' => 'http://ip-api.com/json/{ip}?fields=status,message,country,countryCode,region,regionName,city,zip,lat,lon,timezone,isp,org,as,query',
        'ipapi_co' => 'https://ipapi.co/{ip}/json/',
        'ipwhois' => 'https://ipwhois.app/json/{ip}',
        'ipapi_com' => 'http://ipapi.com/ip_api.php?ip={ip}',
        'freeipapi' => 'https://freeipapi.com/api/json/{ip}',
    ];

    /**
     * Get geolocation data for an IP address
     */
    public function getLocationData(string $ip): ?array
    {
        if ($this->isPrivateIp($ip)) {
            return $this->getDevelopmentLocationData($ip);
        }

        if (!$this->isValidIp($ip)) {
            Log::warning("Invalid IP format: {$ip}");
            return $this->getDefaultLocationData();
        }

        $cacheKey = "geolocation:{$ip}";
        $cachedData = Cache::get($cacheKey);
        
        if ($cachedData) {
            return $cachedData;
        }

        $locationData = $this->tryProviders($ip);

        if ($locationData && $this->isValidLocationData($locationData)) {
            Cache::put($cacheKey, $locationData, now()->addDays(config('analytics.geolocation.cache_ttl', 30)));
        } else {
            $locationData = $this->getDefaultLocationData();
            Log::warning("No geolocation data found for IP: {$ip}, using default");
        }

        return $locationData;
    }

    /**
     * Get development location data for private IPs
     */
    private function getDevelopmentLocationData(string $ip): array
    {
        $mockLocations = [
            '127.0.0.1' => [
                'country' => 'Localhost',
                'country_code' => 'LH',
                'region' => 'Development',
                'region_name' => 'Development Server',
                'city' => 'Local Machine',
                'zip' => '00000',
                'lat' => 40.7128,
                'lon' => -74.0060,
                'timezone' => 'America/New_York',
                'isp' => 'Local Development',
                'org' => 'Development Environment',
                'as_name' => 'AS0 - Local Development',
            ],
            '192.168.' => [
                'country' => 'Local Network',
                'country_code' => 'LN',
                'region' => 'Private Network',
                'region_name' => 'Private Network',
                'city' => 'Local Network',
                'zip' => '00000',
                'lat' => 34.0522,
                'lon' => -118.2437,
                'timezone' => 'America/Los_Angeles',
                'isp' => 'Local Network',
                'org' => 'Private Network',
                'as_name' => 'AS0 - Private Network',
            ],
            '10.' => [
                'country' => 'Corporate Network',
                'country_code' => 'CN',
                'region' => 'Corporate',
                'region_name' => 'Corporate Network',
                'city' => 'Office Network',
                'zip' => '00000',
                'lat' => 37.7749,
                'lon' => -122.4194,
                'timezone' => 'America/Los_Angeles',
                'isp' => 'Corporate Network',
                'org' => 'Corporate Environment',
                'as_name' => 'AS0 - Corporate Network',
            ],
            '172.' => [
                'country' => 'Docker Network',
                'country_code' => 'DN',
                'region' => 'Container',
                'region_name' => 'Container Network',
                'city' => 'Docker Network',
                'zip' => '00000',
                'lat' => 47.6062,
                'lon' => -122.3321,
                'timezone' => 'America/Los_Angeles',
                'isp' => 'Docker Network',
                'org' => 'Container Environment',
                'as_name' => 'AS0 - Container Network',
            ]
        ];

        foreach ($mockLocations as $prefix => $location) {
            if (strpos($ip, $prefix) === 0) {
                return $location;
            }
        }

        return [
            'country' => 'Development',
            'country_code' => 'DV',
            'region' => 'Development',
            'region_name' => 'Development Environment',
            'city' => 'Development Server',
            'zip' => '00000',
            'lat' => 51.5074,
            'lon' => -0.1278,
            'timezone' => 'Europe/London',
            'isp' => 'Development ISP',
            'org' => 'Development Organization',
            'as_name' => 'AS0 - Development',
        ];
    }

    /**
     * Try different geolocation providers with fallback
     */
    private function tryProviders(string $ip): ?array
    {
        $providers = array_keys($this->apiProviders);
        shuffle($providers);

        foreach ($providers as $provider) {
            try {
                $response = $this->makeRequest($provider, $ip);
                
                if ($response && $this->isValidResponse($response, $provider)) {
                    $formattedData = $this->formatResponse($provider, $response);
                    if ($this->isValidLocationData($formattedData)) {
                        return $formattedData;
                    }
                }
            } catch (\Exception $e) {
                Log::warning("Geolocation provider {$provider} failed for IP {$ip}: " . $e->getMessage());
                continue;
            }
        }

        return null;
    }

    /**
     * Make request to geolocation provider
     */
    private function makeRequest(string $provider, string $ip): ?array
    {
        $url = str_replace('{ip}', $ip, $this->apiProviders[$provider]);
        
        try {
            $response = Http::timeout(10)
                ->retry(3, 1000)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                    'Accept' => 'application/json',
                ])
                ->get($url);

            if ($response->successful()) {
                return $response->json();
            } else {
                Log::warning("Geolocation provider {$provider} returned status: " . $response->status());
            }
        } catch (\Exception $e) {
            Log::error("HTTP request failed for provider {$provider}: " . $e->getMessage());
        }

        return null;
    }

    private function isValidResponse(array $response, string $provider): bool
    {
        switch ($provider) {
            case 'ipapi':
                return isset($response['status']) && $response['status'] === 'success';
            case 'ipapi_co':
                return !isset($response['error']);
            case 'ipwhois':
                return isset($response['success']) && $response['success'] === true;
            case 'ipapi_com':
                return isset($response['country_code']);
            case 'freeipapi':
                return !isset($response['error']);
            default:
                return isset($response['country']) || isset($response['country_name']);
        }
    }

    private function isValidLocationData(array $locationData): bool
    {
        return !empty($locationData['country']) && !empty($locationData['country_code']);
    }

    private function formatResponse(string $provider, array $response): array
    {
        switch ($provider) {
            case 'ipapi':
                return [
                    'country' => $response['country'] ?? null,
                    'country_code' => $response['countryCode'] ?? null,
                    'region' => $response['region'] ?? null,
                    'region_name' => $response['regionName'] ?? null,
                    'city' => $response['city'] ?? null,
                    'zip' => $response['zip'] ?? null,
                    'lat' => $response['lat'] ?? null,
                    'lon' => $response['lon'] ?? null,
                    'timezone' => $response['timezone'] ?? null,
                    'isp' => $response['isp'] ?? null,
                    'org' => $response['org'] ?? null,
                    'as_name' => $response['as'] ?? null,
                ];

            case 'ipapi_co':
                return [
                    'country' => $response['country_name'] ?? null,
                    'country_code' => $response['country_code'] ?? null,
                    'region' => $response['region_code'] ?? null,
                    'region_name' => $response['region'] ?? null,
                    'city' => $response['city'] ?? null,
                    'zip' => $response['postal'] ?? null,
                    'lat' => $response['latitude'] ?? null,
                    'lon' => $response['longitude'] ?? null,
                    'timezone' => $response['timezone'] ?? null,
                    'isp' => $response['org'] ?? null,
                    'org' => $response['org'] ?? null,
                    'as_name' => $response['asn'] ?? null,
                ];

            case 'ipwhois':
                return [
                    'country' => $response['country'] ?? null,
                    'country_code' => $response['country_code'] ?? null,
                    'region' => $response['region'] ?? null,
                    'region_name' => $response['region'] ?? null,
                    'city' => $response['city'] ?? null,
                    'zip' => $response['postal'] ?? null,
                    'lat' => $response['latitude'] ?? null,
                    'lon' => $response['longitude'] ?? null,
                    'timezone' => $response['timezone'] ?? null,
                    'isp' => $response['isp'] ?? null,
                    'org' => $response['org'] ?? null,
                    'as_name' => $response['asn'] ?? null,
                ];

            case 'ipapi_com':
                return [
                    'country' => $response['country_name'] ?? null,
                    'country_code' => $response['country_code'] ?? null,
                    'region' => $response['region_code'] ?? null,
                    'region_name' => $response['region_name'] ?? null,
                    'city' => $response['city'] ?? null,
                    'zip' => $response['zip'] ?? null,
                    'lat' => $response['latitude'] ?? null,
                    'lon' => $response['longitude'] ?? null,
                    'timezone' => $response['timezone'] ?? null,
                    'isp' => $response['isp'] ?? null,
                    'org' => $response['org'] ?? null,
                    'as_name' => $response['as'] ?? null,
                ];

            case 'freeipapi':
                return [
                    'country' => $response['countryName'] ?? null,
                    'country_code' => $response['countryCode'] ?? null,
                    'region' => $response['regionName'] ?? null,
                    'region_name' => $response['regionName'] ?? null,
                    'city' => $response['cityName'] ?? null,
                    'zip' => $response['zipCode'] ?? null,
                    'lat' => $response['latitude'] ?? null,
                    'lon' => $response['longitude'] ?? null,
                    'timezone' => $response['timeZone'] ?? null,
                    'isp' => $response['isp'] ?? null,
                    'org' => $response['org'] ?? null,
                    'as_name' => null,
                ];

            default:
                return $this->getDefaultLocationData();
        }
    }

    private function getDefaultLocationData(): array
    {
        return [
            'country' => 'Unknown',
            'country_code' => 'XX',
            'region' => null,
            'region_name' => null,
            'city' => null,
            'zip' => null,
            'lat' => null,
            'lon' => null,
            'timezone' => null,
            'isp' => null,
            'org' => null,
            'as_name' => null,
        ];
    }

    public function isPrivateIp(string $ip): bool
    {
        return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    public function isValidIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    public function getCountryFlag(string $countryCode): string
    {
        if (empty($countryCode) || strlen($countryCode) !== 2 || $countryCode === 'XX') {
            return '🏴';
        }

        $countryCode = strtoupper($countryCode);
        $flag = '';

        foreach (str_split($countryCode) as $char) {
            $flag .= mb_chr(ord($char) + 127397);
        }

        return $flag;
    }

    public function getBatchLocationData(array $ips): array
    {
        $results = [];
        
        foreach ($ips as $ip) {
            $results[$ip] = $this->getLocationData($ip);
        }

        return $results;
    }

    public function testService(): array
    {
        $testIp = request()->ip() ?? '8.8.8.8';
        $result = $this->getLocationData($testIp);
        
        return [
            'test_ip' => $testIp,
            'result' => $result,
            'providers' => array_keys($this->apiProviders),
        ];
    }

    public function clearCache(string $ip): bool
    {
        return Cache::forget("geolocation:{$ip}");
    }

    public function getServiceStatus(): array
    {
        $testResult = $this->testService();
        
        return [
            'status' => !empty($testResult['result']['country']) && $testResult['result']['country'] !== 'Unknown' ? 'operational' : 'degraded',
            'test_ip' => $testResult['test_ip'],
            'country_found' => $testResult['result']['country'] ?? 'Unknown',
            'available_providers' => count($this->apiProviders),
            'cache_enabled' => true,
        ];
    }

    public function forceGeolocation(string $ip): array
    {
        if (!$this->isValidIp($ip)) {
            return $this->getDefaultLocationData();
        }

        $cacheKey = "geolocation:forced:{$ip}";
        $cachedData = Cache::get($cacheKey);
        
        if ($cachedData) {
            return $cachedData;
        }

        $locationData = $this->tryProviders($ip);

        if ($locationData && $this->isValidLocationData($locationData)) {
            Cache::put($cacheKey, $locationData, now()->addDays(config('analytics.geolocation.cache_ttl', 30)));
        } else {
            $locationData = $this->getDefaultLocationData();
        }

        return $locationData;
    }
}