<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Models\Api;
use App\Services\TicketmasterService;
use App\Services\LocationService;

class ConcertController extends Controller
{
    private $ticketmasterBaseUrl;
    private $eventbriteBaseUrl;

    public function __construct()
    {
        // Initialize $baseUrl from .env at runtime
        $this->ticketmasterBaseUrl = env('TICKETMASTER_BASE_URL');
        $this->eventbriteBaseUrl = env('EVENTBRITE_BASE_URL');
    }

    /**
     * Get nearby Pop concerts based on user location
     */
    public function getNearbyPopConcerts(Request $request)
    {
        try {
            $latitude = $request->input('latitude');
            $longitude = $request->input('longitude');
            $radius = $request->input('radius', 50); // Default 50 miles
            $classification = $request->input('classification', 'Pop');

            // If no coordinates provided, try to get from IP
            if (!$latitude || !$longitude) {
                $locationService = new LocationService();
                $ipLocation = $locationService->getLocationFromIP();

                if ($ipLocation) {
                    $latitude = $ipLocation['latitude'];
                    $longitude = $ipLocation['longitude'];

                    \Log::info('Location detected from IP', [
                        'ip' => $locationService->getClientIP(),
                        'location' => $ipLocation
                    ]);
                } else {
                    return response()->json([
                        'success' => false,
                        'error' => 'Could not determine location. Please provide latitude and longitude or enable location services.',
                        'requires_location' => true
                    ], 400);
                }
            }

            // Create cache key
            $cacheKey = 'pop_concerts_' . round($latitude, 2) . '_' . round($longitude, 2) . '_' . $radius;

            // Check cache first (15 minutes for location-based data)
            $cachedConcerts = Cache::get($cacheKey);
            if ($cachedConcerts) {
                return response()->json($cachedConcerts);
            }

            // Use TicketmasterService to fetch Pop concerts
            $ticketmasterService = new TicketmasterService();
            $result = $ticketmasterService->searchPopConcerts($latitude, $longitude, $radius);

            // Add location information to the response
            $result['location'] = [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'radius' => $radius,
                'detected_from' => $request->input('latitude') ? 'user_input' : 'ip_geolocation'
            ];

            // Cache successful results
            if ($result['success']) {
                Cache::put($cacheKey, $result, 900); // 15 minutes
            }

            return response()->json($result);

        } catch (\Exception $e) {
            \Log::error('Pop Concerts API Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch Pop concerts'
            ], 500);
        }
    }

    /**
     * Get concerts based on city or user location
     */
    public function getConcerts(Request $request)
    {
        try {
            $city = $request->input('city');
            $latitude = $request->input('latitude');
            $longitude = $request->input('longitude');

            \Log::info('Concert API called', [
                'city' => $city,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'all_params' => $request->all()
            ]);

            // Create cache key based on location
            $cacheKey = $city ?
                'concerts_city_' . strtolower(str_replace(' ', '_', $city)) :
                'concerts_location_' . $latitude . '_' . $longitude;

            // Check cache first (30 minutes)
            $cachedConcerts = Cache::get($cacheKey);
            if ($cachedConcerts) {
                \Log::info('Returning cached concerts', ['cache_key' => $cacheKey]);
                return response()->json($cachedConcerts);
            }

            // Try Ticketmaster first
            $concerts = $this->fetchFromTicketmaster($city, $latitude, $longitude);

            // If no results, try Eventbrite
            if (empty($concerts)) {
                \Log::info('No Ticketmaster results, trying Eventbrite');
                $concerts = $this->fetchFromEventbrite($city, $latitude, $longitude);
            }

            // Cache results for 30 minutes
            Cache::put($cacheKey, $concerts, 1800);

            return response()->json($concerts);

        } catch (\Exception $e) {
            \Log::error('Concert Controller Error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to fetch concerts',
                'message' => 'Please try again later'
            ], 500);
        }
    }

    /**
     * Fetch concerts from Ticketmaster API
     */
    private function fetchFromTicketmaster($city = null, $latitude = null, $longitude = null)
    {
        try {
            $apiKey = Api::getValue('ticketmaster_api') ?: env('TICKETMASTER_API_KEY');

            if (!$apiKey) {
                \Log::info('Ticketmaster API key not found, using fallback concerts');
                return $this->getFallbackConcerts($city);
            }

            $params = [
                'apikey' => $apiKey,
                'classificationName' => 'Music',
                'size' => 20,
                'sort' => 'date,asc'
            ];

            // Add location parameters
            if ($city) {
                $params['city'] = $city;
                \Log::info('Searching concerts by city', ['city' => $city]);
            } elseif ($latitude && $longitude) {
                $params['latlong'] = $latitude . ',' . $longitude;
                $params['radius'] = '25'; // 25 miles radius for more precise results
                $params['unit'] = 'miles';
                $params['sort'] = 'distance,asc'; // Sort by distance when using location
                \Log::info('Searching concerts by location', ['lat' => $latitude, 'lng' => $longitude]);
            } else {
                // Default to major cities if no location provided
                $params['city'] = 'New York,Los Angeles,Chicago,Houston,Phoenix,Miami,Seattle,Denver';
                \Log::info('Searching concerts in major cities');
            }

            \Log::info('Ticketmaster API Request', $params);

            $fullUrl = $this->ticketmasterBaseUrl . '/events.json';
            \Log::info('Making Ticketmaster API request', [
                'url' => $fullUrl,
                'params' => $params
            ]);

            $response = Http::timeout(30)
                ->withOptions(['verify' => false]) // Disable SSL verification for development
                ->get($fullUrl, $params);

            \Log::info('Ticketmaster API response', [
                'status' => $response->status(),
                'headers' => $response->headers(),
                'body_preview' => substr($response->body(), 0, 500)
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $events = $data['_embedded']['events'] ?? [];

                \Log::info('Ticketmaster API success', [
                    'total_results' => $data['page']['totalElements'] ?? 0,
                    'events_count' => count($events),
                    'first_event' => $events[0]['name'] ?? 'No events'
                ]);

                return $this->formatTicketmasterData($events);
            } else {
                \Log::error('Ticketmaster API Error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'url' => $fullUrl,
                    'params' => $params
                ]);
                return [];
            }

        } catch (\Exception $e) {
            \Log::error('Ticketmaster fetch error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Format Ticketmaster API data
     */
    private function formatTicketmasterData($events)
    {
        $formattedEvents = [];

        foreach ($events as $event) {
            $venue = $event['_embedded']['venues'][0] ?? null;
            $date = $event['dates']['start'] ?? null;

            $formattedEvents[] = [
                'id' => $event['id'] ?? null,
                'name' => $event['name'] ?? 'Unknown Event',
                'date' => $date['localDate'] ?? null,
                'time' => $date['localTime'] ?? null,
                'datetime' => isset($date['dateTime']) ? $date['dateTime'] : null,
                'venue' => [
                    'name' => $venue['name'] ?? 'Unknown Venue',
                    'address' => $venue['address']['line1'] ?? '',
                    'city' => $venue['city']['name'] ?? '',
                    'state' => $venue['state']['stateCode'] ?? '',
                    'country' => $venue['country']['countryCode'] ?? ''
                ],
                'price_range' => $this->extractPriceRange($event),
                'ticket_url' => $event['url'] ?? null,
                'image' => $this->extractImage($event),
                'genre' => $this->extractGenre($event),
                'source' => 'Ticketmaster'
            ];
        }

        return $formattedEvents;
    }

    /**
     * Fetch concerts from Eventbrite API (fallback)
     */
    private function fetchFromEventbrite($city = null, $latitude = null, $longitude = null)
    {
        try {
            $apiKey = Api::getValue('eventbrite_api') ?: env('EVENTBRITE_API_KEY');

            if (!$apiKey) {
                \Log::info('Eventbrite API key not found, using fallback data');
                return $this->getFallbackConcerts($city);
            }

            // Eventbrite API implementation would go here
            // For now, return fallback data
            return $this->getFallbackConcerts($city);

        } catch (\Exception $e) {
            \Log::error('Eventbrite fetch error: ' . $e->getMessage());
            return $this->getFallbackConcerts($city);
        }
    }

    /**
     * Extract price range from Ticketmaster event
     */
    private function extractPriceRange($event)
    {
        $priceRanges = $event['priceRanges'] ?? [];
        if (empty($priceRanges)) {
            return null;
        }

        $range = $priceRanges[0];
        $min = $range['min'] ?? null;
        $max = $range['max'] ?? null;
        $currency = $range['currency'] ?? 'USD';

        if ($min && $max && $min != $max) {
            return "$currency $min - $max";
        } elseif ($min) {
            return "$currency $min";
        }

        return null;
    }

    /**
     * Extract image from Ticketmaster event
     */
    private function extractImage($event)
    {
        $images = $event['images'] ?? [];

        // Find the best quality image
        foreach ($images as $image) {
            if ($image['width'] >= 640) {
                return $image['url'];
            }
        }

        // Fallback to first image
        return $images[0]['url'] ?? null;
    }

    /**
     * Extract genre from Ticketmaster event
     */
    private function extractGenre($event)
    {
        $classifications = $event['classifications'] ?? [];

        if (!empty($classifications)) {
            $genre = $classifications[0]['genre']['name'] ?? null;
            $subGenre = $classifications[0]['subGenre']['name'] ?? null;

            return $subGenre ?: $genre;
        }

        return 'Music';
    }

    /**
     * Get Pop concerts for homepage from across the entire United States
     * Shows concerts from all US cities, not just one location
     */
    public function getHomepageConcerts()
    {
        try {
            $limit = 200; // Show more concerts for future ticket booking

            // Create cache key for US-wide concerts
            $cacheKey = 'homepage_concerts_us';

            // Check cache first (15 minutes)
            $cachedConcerts = Cache::get($cacheKey);
            if ($cachedConcerts) {
                return response()->json([
                    'success' => true,
                    'concerts' => $cachedConcerts,
                    'location' => 'United States'
                ]);
            }

            // Use TicketmasterService to fetch US-wide concerts
            $ticketmasterService = new TicketmasterService();
            $result = $ticketmasterService->searchUSPopConcerts($limit);

            if ($result['success']) {
                // Cache results for 15 minutes
                Cache::put($cacheKey, $result['concerts'], 900);

                return response()->json([
                    'success' => true,
                    'concerts' => $result['concerts'],
                    'location' => 'United States'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => $result['error'] ?? 'Failed to fetch concerts',
                    'concerts' => []
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Homepage concerts error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch concerts: ' . $e->getMessage(),
                'concerts' => []
            ]);
        }
    }

    /**
     * Get Pop concerts by location name (city search)
     */
    public function getConcertsByLocation(Request $request)
    {
        try {
            $location = $request->input('location');

            if (!$location || trim($location) === '') {
                return response()->json([
                    'success' => false,
                    'error' => 'Location is required',
                    'concerts' => []
                ]);
            }

            // Create cache key for location search
            $cacheKey = 'location_concerts_' . strtolower(trim($location));

            // Check cache first (15 minutes)
            $cachedConcerts = Cache::get($cacheKey);
            if ($cachedConcerts) {
                return response()->json([
                    'success' => true,
                    'concerts' => $cachedConcerts,
                    'location' => trim($location)
                ]);
            }

            $limit = 50; // Limit for location-based search

            // Use TicketmasterService to fetch concerts by location
            $ticketmasterService = new TicketmasterService();
            $result = $ticketmasterService->searchConcertsByLocation($location, $limit);

            if ($result['success']) {
                // Cache results for 15 minutes
                Cache::put($cacheKey, $result['concerts'], 900);

                return response()->json([
                    'success' => true,
                    'concerts' => $result['concerts'],
                    'location' => $result['location'] ?? trim($location)
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => $result['error'] ?? 'Failed to fetch concerts for this location',
                    'concerts' => []
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Location concerts error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch concerts: ' . $e->getMessage(),
                'concerts' => []
            ]);
        }
    }

    /**
     * Get fallback concerts when API key is missing or no results found
     */
    private function getFallbackConcerts($city = null)
    {
        \Log::info('Returning fallback concerts with Ticketmaster booking links');
        
        // Return sample concert data that encourages users to check Ticketmaster
        return [
            [
                'id' => 'sample-1',
                'name' => 'Find Concerts Near You',
                'date' => date('F j, Y', strtotime('+30 days')),
                'time' => 'Various Times',
                'datetime' => date('Y-m-d\TH:i:s\Z', strtotime('+30 days')),
                'venue' => [
                    'name' => 'Local Venues',
                    'address' => '',
                    'city' => $city ?? 'Your City',
                    'state' => '',
                    'country' => 'US'
                ],
                'price_range' => 'Varies',
                'ticket_url' => 'https://www.ticketmaster.com/discover/concerts',
                'image' => asset('hero.jpg'),
                'genre' => 'Pop',
                'source' => 'Ticketmaster'
            ],
            [
                'id' => 'sample-2',
                'name' => 'Browse Top Artists',
                'date' => date('F j, Y', strtotime('+45 days')),
                'time' => 'Various Times',
                'datetime' => date('Y-m-d\TH:i:s\Z', strtotime('+45 days')),
                'venue' => [
                    'name' => 'Multiple Locations',
                    'address' => '',
                    'city' => $city ?? 'Your City',
                    'state' => '',
                    'country' => 'US'
                ],
                'price_range' => 'Check Ticketmaster',
                'ticket_url' => 'https://www.ticketmaster.com/browse/concerts',
                'image' => asset('hero.jpg'),
                'genre' => 'Pop',
                'source' => 'Ticketmaster'
            ],
            [
                'id' => 'sample-3',
                'name' => 'Explore Live Events',
                'date' => date('F j, Y', strtotime('+60 days')),
                'time' => 'Various Times',
                'datetime' => date('Y-m-d\TH:i:s\Z', strtotime('+60 days')),
                'venue' => [
                    'name' => 'Nationwide Venues',
                    'address' => '',
                    'city' => $city ?? 'Your City',
                    'state' => '',
                    'country' => 'US'
                ],
                'price_range' => 'See Details',
                'ticket_url' => 'https://www.ticketmaster.com/',
                'image' => asset('hero.jpg'),
                'genre' => 'Pop',
                'source' => 'Ticketmaster'
            ]
        ];
    }

    /**
     * Get concerts for trending/popular artists
     * Returns concerts for well-known Pop artists like Taylor Swift, Ed Sheeran, etc.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getTrendingArtistsConcerts(Request $request)
    {
        try {
            $limit = $request->input('limit', 3); // Default 3 concerts per artist

            // Create cache key
            $cacheKey = 'trending_artists_concerts_' . $limit;

            // Check cache first (15 minutes)
            $cachedConcerts = Cache::get($cacheKey);
            if ($cachedConcerts) {
                return response()->json([
                    'success' => true,
                    'concerts' => $cachedConcerts,
                    'source' => 'cache'
                ]);
            }

            // Use TicketmasterService to fetch trending artists concerts
            $ticketmasterService = new TicketmasterService();
            $result = $ticketmasterService->getTrendingArtistsConcerts($limit);

            if ($result['success']) {
                // Cache results for 15 minutes
                Cache::put($cacheKey, $result['concerts'], 900);

                return response()->json([
                    'success' => true,
                    'concerts' => $result['concerts'],
                    'total_artists' => $result['total_artists'],
                    'total_concerts' => $result['total_concerts'],
                    'source' => 'api'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => $result['error'] ?? 'Failed to fetch trending artists concerts',
                    'concerts' => []
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error in getTrendingArtistsConcerts: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'An error occurred while fetching trending artists concerts',
                'concerts' => []
            ]);
        }
    }
}