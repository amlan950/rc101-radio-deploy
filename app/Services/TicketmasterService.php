<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TicketmasterService
{
    protected $apiKey;
    protected $baseUrl;

    public function __construct()
    {
        $this->apiKey = \App\Models\Api::getValue('ticketmaster_api') ?: env('TICKETMASTER_API_KEY');
        $this->baseUrl = env('TICKETMASTER_BASE_URL', 'https://app.ticketmaster.com/discovery/v2');
    }

    /**
     * Search for Pop concerts near a specific location
     * 
     * @param float $latitude
     * @param float $longitude
     * @param int $radius Radius in miles (default: 50)
     * @param int $limit Number of results to return (default: 20)
     * @return array
     */
    public function searchPopConcerts($latitude, $longitude, $radius = 50, $limit = 20)
    {
        try {
            // Check if API key is available
            if (!$this->apiKey) {
                Log::info('Ticketmaster API key not configured in TicketmasterService');
                return [
                    'success' => false,
                    'error' => 'Ticketmaster API key not configured',
                    'concerts' => []
                ];
            }

            // Get current date for filtering future concerts
            $currentDate = now()->format('Y-m-d');

            // Try to fetch from Ticketmaster API
            $response = Http::withoutVerifying()->get($this->baseUrl . '/events.json', [
                'apikey' => $this->apiKey,
                'classificationName' => 'Pop',
                'latlong' => "{$latitude},{$longitude}",
                'radius' => $radius,
                'unit' => 'miles',
                'size' => $limit,
                'sort' => 'date,asc',
                'startDateTime' => $currentDate . 'T00:00:00Z'
            ]);

            if (!$response->successful()) {
                Log::error('Ticketmaster API error: ' . $response->body());
                return [
                    'success' => false,
                    'error' => 'Failed to fetch concerts from Ticketmaster API',
                    'concerts' => []
                ];
            }

            $data = $response->json();

            if (!isset($data['_embedded']['events'])) {
                return [
                    'success' => true,
                    'concerts' => [],
                    'message' => 'No Pop concerts found in your area'
                ];
            }

            $concerts = $this->formatConcerts($data['_embedded']['events']);

            return [
                'success' => true,
                'concerts' => $concerts,
                'total' => count($concerts)
            ];

        } catch (\Exception $e) {
            Log::error('Ticketmaster service error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to fetch concerts: ' . $e->getMessage(),
                'concerts' => []
            ];
        }
    }

    /**
     * Search for Pop concerts across the entire United States
     * 
     * @param int $limit Number of results to return (default: 20)
     * @return array
     */
    public function searchUSPopConcerts($limit = 20)
    {
        try {
            // Check if API key is available
            if (!$this->apiKey) {
                Log::info('Ticketmaster API key not configured in TicketmasterService');
                return [
                    'success' => false,
                    'error' => 'Ticketmaster API key not configured',
                    'concerts' => []
                ];
            }

            // Get current date and end date (6 months from now) for wider date range
            $currentDate = now()->format('Y-m-d');
            $endDate = now()->addMonths(6)->format('Y-m-d');

            // Try to fetch from Ticketmaster API - search by country code
            $response = Http::withoutVerifying()->get($this->baseUrl . '/events.json', [
                'apikey' => $this->apiKey,
                'classificationName' => 'Pop',
                'countryCode' => 'US',
                'size' => $limit,
                'sort' => 'date,asc',
                'startDateTime' => $currentDate . 'T00:00:00Z',
                'endDateTime' => $endDate . 'T23:59:59Z'
            ]);

            if (!$response->successful()) {
                Log::error('Ticketmaster API error (US search): ' . $response->body());
                return [
                    'success' => false,
                    'error' => 'Failed to fetch concerts from Ticketmaster API',
                    'concerts' => []
                ];
            }

            $data = $response->json();

            if (!isset($data['_embedded']['events'])) {
                return [
                    'success' => true,
                    'concerts' => [],
                    'message' => 'No Pop concerts found in the US'
                ];
            }

            $concerts = $this->formatConcerts($data['_embedded']['events']);

            // Filter to show only Pop concerts
            $popConcerts = array_filter($concerts, function ($concert) {
                return $concert['genre'] === 'Pop';
            });

            return [
                'success' => true,
                'concerts' => array_values($popConcerts), // Re-index array
                'total' => count($popConcerts)
            ];

        } catch (\Exception $e) {
            Log::error('Ticketmaster service error (US search): ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to fetch concerts: ' . $e->getMessage(),
                'concerts' => []
            ];
        }
    }

    /**
     * Search for Pop concerts by location name (city)
     * Uses OpenStreetMap Nominatim API for geocoding
     * 
     * @param string $location City name
     * @param int $limit Number of results to return
     * @return array
     */
    public function searchConcertsByLocation($location, $limit = 20)
    {
        try {
            // Check if API key is available
            if (!$this->apiKey) {
                Log::info('Ticketmaster API key not configured in TicketmasterService');
                return [
                    'success' => false,
                    'error' => 'Ticketmaster API key not configured',
                    'concerts' => []
                ];
            }

            // First, get coordinates for the location using OpenStreetMap Nominatim
            $geocodeUrl = "https://nominatim.openstreetmap.org/search";
            $geocodeParams = [
                'q' => $location,
                'format' => 'json',
                'limit' => 1,
                'countrycodes' => 'us'
            ];

            $geocodeResponse = Http::withoutVerifying()
                ->withHeaders(['User-Agent' => 'Jammin Concert App/1.0'])
                ->get($geocodeUrl, $geocodeParams);

            if (!$geocodeResponse->successful() || empty($geocodeResponse->json())) {
                return [
                    'success' => false,
                    'error' => 'Location not found. Please try a different city name.',
                    'concerts' => []
                ];
            }

            $geocodeData = $geocodeResponse->json()[0];
            $latitude = (float) $geocodeData['lat'];
            $longitude = (float) $geocodeData['lon'];
            $cityName = $geocodeData['display_name'];

            // Get current date and end date (6 months from now) for wider date range
            $currentDate = now()->format('Y-m-d');
            $endDate = now()->addMonths(6)->format('Y-m-d');

            // Search for concerts near the coordinates
            $response = Http::withoutVerifying()->get($this->baseUrl . '/events.json', [
                'apikey' => $this->apiKey,
                'classificationName' => 'Pop',
                'latlong' => $latitude . ',' . $longitude,
                'radius' => '50', // 50 mile radius
                'unit' => 'miles',
                'size' => $limit,
                'sort' => 'date,asc',
                'startDateTime' => $currentDate . 'T00:00:00Z',
                'endDateTime' => $endDate . 'T23:59:59Z'
            ]);

            if (!$response->successful()) {
                Log::error('Ticketmaster API error (location search): ' . $response->body());
                return [
                    'success' => false,
                    'error' => 'Failed to fetch concerts from Ticketmaster API',
                    'concerts' => []
                ];
            }

            $data = $response->json();

            if (!isset($data['_embedded']['events']) || empty($data['_embedded']['events'])) {
                return [
                    'success' => true,
                    'concerts' => [],
                    'location' => $cityName
                ];
            }

            // Format concerts
            $concerts = $this->formatConcerts($data['_embedded']['events']);

            // Filter to show only Pop concerts
            $popConcerts = array_filter($concerts, function ($concert) {
                return $concert['genre'] === 'Pop';
            });

            return [
                'success' => true,
                'concerts' => array_values($popConcerts),
                'location' => $cityName,
                'total' => count($popConcerts)
            ];

        } catch (\Exception $e) {
            Log::error('Ticketmaster service error (location search): ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to fetch concerts: ' . $e->getMessage(),
                'concerts' => []
            ];
        }
    }

    /**
     * Get concerts for trending/popular artists
     * Searches for concerts by well-known popular artists
     * 
     * @param int $limit Number of concerts to return per artist
     * @return array
     */
    public function getTrendingArtistsConcerts($limit = 3)
    {
        try {
            // List of trending/popular Pop artists to search for
            $trendingArtists = [
                'Taylor Swift',
                'Ed Sheeran',
                'Harry Styles',
                'Olivia Rodrigo',
                'The Weeknd',
                'Billie Eilish',
                'Dua Lipa',
                'Bruno Mars',
                'Ariana Grande',
                'Justin Bieber'
            ];

            $allConcerts = [];
            $foundArtists = [];

            // Get current date and end date (6 months from now)
            $currentDate = now()->format('Y-m-d');
            $endDate = now()->addMonths(6)->format('Y-m-d');

            foreach ($trendingArtists as $artist) {
                // Search for concerts by this artist
                $response = Http::withoutVerifying()->get($this->baseUrl . '/events.json', [
                    'apikey' => $this->apiKey,
                    'keyword' => $artist,
                    'classificationName' => 'Pop',
                    'countryCode' => 'US',
                    'size' => $limit,
                    'sort' => 'date,asc',
                    'startDateTime' => $currentDate . 'T00:00:00Z',
                    'endDateTime' => $endDate . 'T23:59:59Z'
                ]);

                if ($response->successful() && isset($response->json()['_embedded']['events'])) {
                    $events = $response->json()['_embedded']['events'];
                    $formattedConcerts = $this->formatConcerts($events);

                    // Filter to ensure we only get concerts for this specific artist
                    $artistConcerts = array_filter($formattedConcerts, function ($concert) use ($artist) {
                        return stripos($concert['artist'], $artist) !== false ||
                            stripos($concert['name'], $artist) !== false;
                    });

                    if (!empty($artistConcerts)) {
                        $allConcerts = array_merge($allConcerts, array_values($artistConcerts));
                        $foundArtists[] = $artist;
                    }
                }

                // Stop if we have enough artists with concerts
                if (count($foundArtists) >= 5) {
                    break;
                }
            }

            // Sort all concerts by date
            usort($allConcerts, function ($a, $b) {
                return strtotime($a['date'] . ' ' . ($a['time'] ?? '00:00')) -
                    strtotime($b['date'] . ' ' . ($b['time'] ?? '00:00'));
            });

            // Group concerts by artist for better display
            $groupedConcerts = [];
            foreach ($allConcerts as $concert) {
                $artistName = $concert['artist'];
                if (!isset($groupedConcerts[$artistName])) {
                    $groupedConcerts[$artistName] = [];
                }
                $groupedConcerts[$artistName][] = $concert;
            }

            return [
                'success' => true,
                'concerts' => $groupedConcerts,
                'total_artists' => count($groupedConcerts),
                'total_concerts' => count($allConcerts)
            ];

        } catch (\Exception $e) {
            Log::error('Ticketmaster service error (trending artists): ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to fetch trending artists concerts: ' . $e->getMessage(),
                'concerts' => []
            ];
        }
    }

    /**
     * Format concert data for frontend display
     * 
     * @param array $events
     * @return array
     */
    private function formatConcerts($events)
    {
        $formattedConcerts = [];

        foreach ($events as $event) {
            // Only process events with venue information
            if (!isset($event['_embedded']['venues'][0])) {
                continue;
            }

            $venue = $event['_embedded']['venues'][0];
            $concert = [
                'id' => $event['id'],
                'name' => $event['name'],
                'artist' => $this->extractArtistName($event),
                'date' => $this->formatDate($event['dates']['start']['localDate'] ?? null),
                'time' => $event['dates']['start']['localTime'] ?? null,
                'venue' => [
                    'name' => $venue['name'],
                    'city' => $venue['city']['name'] ?? null,
                    'state' => $venue['state']['stateCode'] ?? null,
                    'country' => $venue['country']['countryCode'] ?? null
                ],
                'url' => $event['url'],
                'image' => $this->getEventImage($event),
                'genre' => $this->extractGenre($event),
                'price_range' => $this->extractPriceRange($event)
            ];

            $formattedConcerts[] = $concert;
        }

        return $formattedConcerts;
    }

    /**
     * Extract artist name from event data
     * 
     * @param array $event
     * @return string
     */
    private function extractArtistName($event)
    {
        // Try to get artist from attractions
        if (isset($event['_embedded']['attractions'][0]['name'])) {
            return $event['_embedded']['attractions'][0]['name'];
        }

        // Fallback to event name
        return $event['name'];
    }

    /**
     * Format date for display
     * 
     * @param string|null $date
     * @return string|null
     */
    private function formatDate($date)
    {
        if (!$date)
            return null;

        try {
            $dateTime = new \DateTime($date);
            return $dateTime->format('F j, Y');
        } catch (\Exception $e) {
            return $date;
        }
    }

    /**
     * Get event image URL
     * 
     * @param array $event
     * @return string|null
     */
    private function getEventImage($event)
    {
        if (isset($event['images'][0]['url'])) {
            return $event['images'][0]['url'];
        }

        return null;
    }

    /**
     * Extract genre information
     * 
     * @param array $event
     * @return string|null
     */
    private function extractGenre($event)
    {
        if (isset($event['classifications'][0]['genre']['name'])) {
            return $event['classifications'][0]['genre']['name'];
        }

        return null;
    }

    /**
     * Extract price range
     * 
     * @param array $event
     * @return string|null
     */
    private function extractPriceRange($event)
    {
        if (isset($event['priceRanges'][0])) {
            $priceRange = $event['priceRanges'][0];
            $currency = $priceRange['currency'] ?? 'USD';
            $min = $priceRange['min'] ?? null;
            $max = $priceRange['max'] ?? null;

            if ($min && $max) {
                return $currency . ' ' . $min . ' - ' . $max;
            } elseif ($min) {
                return 'From ' . $currency . ' ' . $min;
            }
        }

        return null;
    }


}