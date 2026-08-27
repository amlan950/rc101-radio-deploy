<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ConcertController;
use App\Http\Controllers\ContestController;
use App\Http\Controllers\Admin\AdController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CommunityEventController;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

use App\Models\Ad;
use App\Models\UploadedNews;
// Include debug routes if they exist
if (file_exists(__DIR__ . '/debug-ads.php')) {
    require __DIR__ . '/debug-ads.php';
}
if (file_exists(__DIR__ . '/debug-location.php')) {
    require __DIR__ . '/debug-location.php';
}

// Homepage route
Route::get('/', function () {

    // ---------------------------
    // EXISTING ADS LOGIC (UNCHANGED)
    // ---------------------------
    $activeAds = collect();

    try {
        $activeAds = Ad::where('is_active', true)
            ->orderBy('display_order')
            ->get();
    } catch (\Exception $e) {
        \Log::error('Error fetching active ads: ' . $e->getMessage());
    }

    // ---------------------------
    // ✅ NEW: FETCH UPLOADED NEWS
    // ---------------------------
    $uploadedNews = collect();

    try {
        $uploadedNews = UploadedNews::active()
            ->ordered()
            ->get();
    } catch (\Exception $e) {
        \Log::error('Error fetching uploaded news: ' . $e->getMessage());
    }

    // ---------------------------
    // EXISTING DEBUG INFO (UNCHANGED)
    // ---------------------------
    $debugInfo = [
        'active_ads_count' => $activeAds->count(),
        'ads' => $activeAds->map(function ($ad) {
            try {
                $path = $ad->image_path ?? null;
                $cleanPath = $path ? ltrim($path, '/') : '';
                $cleanPath = str_replace('storage/', '', $cleanPath);
                $fullPath = $cleanPath ? storage_path('app/public/' . $cleanPath) : null;

                $fileExists = $fullPath && file_exists($fullPath);

                return [
                    'id' => $ad->id,
                    'title' => $ad->title ?? 'No Title',
                    'original_path' => $path,
                    'clean_path' => $cleanPath,
                    'full_path' => $fullPath,
                    'url' => $ad->image_url,
                    'file_exists' => $fileExists,
                    'is_readable' => $fileExists && is_readable($fullPath),
                    'file_size' => $fileExists ? filesize($fullPath) : 0,
                    'mime_type' => $fileExists ? mime_content_type($fullPath) : null,
                ];
            } catch (\Exception $e) {
                return [
                    'id' => $ad->id ?? null,
                    'error' => $e->getMessage()
                ];
            }
        })
    ];

    // ---------------------------
    // RETURN VIEW WITH NEWS DATA
    // ---------------------------
    return view('index', [
        'activeAds' => $activeAds,
        'debugInfo' => $debugInfo,
        'uploadedNews' => $uploadedNews, // 👈 IMPORTANT
    ]);

})->name('home');


// Ad display route
Route::get('/ads/{ad}', function (Ad $ad) {
    if (!$ad->is_active) {
        abort(404);
    }

    return view('ads.show', [
        'ad' => $ad,
        'imageUrl' => $ad->image_url,
        'title' => $ad->title,
        'description' => $ad->description
    ]);
})->name('ads.show');

// Promotional Event Pages
Route::get('/events/meet-and-greet', function () {
    return view('events.meet-and-greet');
})->name('events.meet-and-greet');

Route::get('/events/studio-tour', function () {
    return view('events.studio-tour');
})->name('events.studio-tour');

Route::get('/events/charity-event', function () {
    return view('events.charity-event');
})->name('events.charity-event');


Route::get('/contest', function () {
    try {
        // Debug: Log current time for verification
        \Log::info('Current time: ' . now());

        // Get the first active contest with its images, ordered by display_order
        $contest = \App\Models\Contest::where('is_active', 1)
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->with([
                'images' => function ($query) {
                    $query->orderBy('display_order', 'asc');
                }
            ])
            ->first();

        // Debug: Log the query results
        \Log::info('Active contest query result: ' . ($contest ? 'Found contest ID ' . $contest->id : 'No active contests found'));

        if (!$contest) {
            // If no active contest, show a message instead of showing an old contest
            return view('contest', [
                'contest' => null,
                'hasImages' => false
            ]);
        }

        // Process images to ensure they have proper URLs
        $contest->images->each(function ($image) {
            $image->url = asset('storage/' . ltrim($image->image_path, '/'));
            $image->storage_path = storage_path('app/public/' . ltrim($image->image_path, '/'));
            $image->exists = file_exists($image->storage_path);
        });

        return view('contest', [
            'contest' => $contest,
            'hasImages' => $contest->images->isNotEmpty()
        ]);

    } catch (\Exception $e) {
        \Log::error('Contest display error: ' . $e->getMessage());
        return response()->view('errors.contest-error', [
            'message' => 'An error occurred while loading the contest.',
            'error' => config('app.debug') ? $e->getMessage() : null
        ], 500);
    }
})->name('contest');

// Admin routes
Route::prefix('admin')->name('admin.')->group(function () {
    // Authentication routes (no middleware required)
    Route::get('/login', [App\Http\Controllers\Admin\AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [App\Http\Controllers\Admin\AuthController::class, 'login'])->name('login.submit');
    Route::get('/register', [App\Http\Controllers\Admin\AuthController::class, 'showRegistrationForm'])->name('register');
    Route::post('/register', [App\Http\Controllers\Admin\AuthController::class, 'register'])->name('register.submit');
    Route::post('/logout', [App\Http\Controllers\Admin\AuthController::class, 'logout'])->name('logout');

    // Protected admin routes (require authentication)
    // Server-side metadata proxy with optimized timeouts and caching
    Route::get('/api/stream-metadata', function () {
        // Set a maximum execution time of 5 seconds for this request
        set_time_limit(5);

        // Try to get cached metadata first (5 second cache)
        $cacheKey = 'stream_metadata_' . md5(config('app.stream_url'));
        if (cache()->has($cacheKey)) {
            return response()->json(cache()->get($cacheKey));
        }

        $streamUrl = config('app.stream_url', 'https://streams.radiomast.io/rc101');

        // Simple fallback response
        $fallbackResponse = [
            'success' => true,
            'cached' => false,
            'is_fallback' => true
        ];

        // Only try the most common endpoints
        $endpoints = ['/status-json.xsl', '/currentsong', '/7.html'];

        $client = new \GuzzleHttp\Client([
            'timeout' => 1.5, // 1.5 seconds total timeout
            'connect_timeout' => 0.5, // 0.5 seconds to connect
            'http_errors' => false,
            'verify' => false, // Only for development
            'headers' => [
                'User-Agent' => 'JamminRadio/1.0',
                'Accept' => 'application/json, text/plain, */*',
                'Connection' => 'close',
                'Accept-Encoding' => 'gzip, deflate'
            ]
        ]);

        // Try each endpoint until we get valid metadata
        foreach ($endpoints as $endpoint) {
            try {
                $url = rtrim($streamUrl, '/') . $endpoint;

                // Skip if we're about to hit the time limit
                if (microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'] > 3) {
                    \Log::warning("Skipping remaining endpoints due to time limit");
                    break;
                }

                try {
                    $response = $client->get($url, [
                        'timeout' => 1.5,
                        'headers' => [
                            'Referer' => url('/'),
                            'Origin' => url('/')
                        ]
                    ]);
                } catch (\Exception $e) {
                    \Log::warning("Error connecting to {$url}: " . $e->getMessage());
                    continue;
                }

                $statusCode = $response->getStatusCode();
                $contentType = $response->getHeaderLine('Content-Type');
                $body = (string) $response->getBody();

                // Skip if we didn't get a successful response
                if ($statusCode < 200 || $statusCode >= 400) {
                    \Log::debug("Skipping endpoint {$endpoint} - Status: {$statusCode}");
                    continue;
                }

                // Try to parse the response
                $data = null;
                $isJson = str_contains($contentType, 'json') ||
                    (empty($contentType) && (str_starts_with(trim($body), '{') || str_starts_with(trim($body), '[')));

                if ($isJson) {
                    $data = json_decode($body, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $data = $body; // Fallback to raw body if not valid JSON
                    }
                } else {
                    $data = $body;
                }

                // Parse the data to extract metadata
                $metadata = [
                    'title' => null,
                    'artist' => null,
                    'song' => null,
                    'raw' => $data,
                    'endpoint' => $endpoint
                ];

                // Handle different response formats
                if (is_array($data)) {
                    // Icecast JSON format
                    if (isset($data['icestats']['source'])) {
                        $source = $data['icestats']['source'];
                        $metadata['title'] = $source['title'] ?? $source['server_name'] ?? null;
                        $metadata['artist'] = $source['server_name'] ?? null;
                        $metadata['song'] = $source['title'] ?? null;
                    }
                    // Shoutcast JSON format
                    elseif (isset($data['shoutcast']['source'])) {
                        $source = $data['shoutcast']['source'];
                        $metadata['title'] = $source['title'] ?? $source['server_name'] ?? null;
                        $metadata['artist'] = $source['server_name'] ?? null;
                        $metadata['song'] = $source['title'] ?? null;
                    }
                    // Direct metadata
                    elseif (isset($data['title']) || isset($data['artist']) || isset($data['song'])) {
                        $metadata['title'] = $data['title'] ?? ($data['artist'] && $data['song'] ? $data['artist'] . ' - ' . $data['song'] : null);
                        $metadata['artist'] = $data['artist'] ?? null;
                        $metadata['song'] = $data['song'] ?? null;
                    }
                }
                // Handle SHOUTcast 7.html format (CSV)
                elseif (is_string($data) && str_contains($data, ',')) {
                    $parts = explode(',', $data);
                    if (count($parts) >= 7) {
                        $metadata['title'] = trim($parts[6]);
                        $metadata['song'] = trim($parts[6]);
                    }
                }
                // Handle plain text response
                elseif (is_string($data) && !empty(trim($data))) {
                    $metadata['title'] = trim($data);
                    $metadata['song'] = trim($data);
                }

                // If we got any metadata, cache and return it
                if ($metadata['title'] || $metadata['artist'] || $metadata['song']) {
                    $title = $metadata['title'] ?? ($metadata['artist'] ? $metadata['artist'] . ' - ' . ($metadata['song'] ?? 'Live Stream') : 'Live Stream');

                    $responseData = [
                        'success' => true,
                        'title' => $title,
                        'artist' => $metadata['artist'] ?? 'Jammin Radio',
                        'song' => $metadata['song'] ?? 'Live Stream',
                        'endpoint' => $endpoint,
                        'cached' => false,
                        'is_fallback' => false
                    ];

                    // Cache for 5 seconds
                    cache()->put($cacheKey, $responseData, now()->addSeconds(5));

                    return response()->json($responseData);
                }

            } catch (\Exception $e) {
                \Log::error("Error fetching metadata from {$endpoint}: " . $e->getMessage());
                continue; // Try next endpoint
            }
        }

        // If we get here, all endpoints failed
        $responseTime = number_format(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 3);

        // Cache the fallback for 5 seconds to prevent constant retries
        cache()->put($cacheKey, $fallbackResponse, now()->addSeconds(5));

        // Return the fallback response
        return response()->json($fallbackResponse);
    })->name('api.stream.metadata');

    // Protected admin routes (require authentication)
    Route::middleware(['admin.auth'])->group(function () {
        // Admin dashboard route
        Route::get('/', [App\Http\Controllers\AdminController::class, 'index'])->name('index');



        Route::get('/apis', [AdminController::class, 'apis'])->name('apis');
        Route::post('/apis', [AdminController::class, 'storeApi'])->name('apis.store');
        Route::patch('/apis/{id}/toggle', [AdminController::class, 'toggleApi'])->name('apis.toggle');

        // Ad Management Routes
        Route::resource('ads', 'App\Http\Controllers\Admin\AdController')->except(['show']);
        Route::post('ads/{ad}/toggle-status', 'App\Http\Controllers\Admin\AdController@toggleStatus')->name('ads.toggle-status');
        Route::delete('/apis/{id}', [AdminController::class, 'deleteApi'])->name('apis.destroy');

        // Contest management routes
        Route::resource('contests', ContestController::class);
        Route::get('contests/{contest}/upload', [ContestController::class, 'showUploadForm'])->name('contests.upload');
        Route::post('contests/{contest}/upload', [ContestController::class, 'uploadImages'])->name('contests.upload.store');
        Route::delete('contest-images/{image}', [ContestController::class, 'deleteImage'])->name('contest-images.destroy');

        // Footer management routes
        Route::get('footer', [\App\Http\Controllers\Admin\FooterController::class, 'index'])->name('footer.index');
        Route::put('footer', [\App\Http\Controllers\Admin\FooterController::class, 'update'])->name('footer.update');

        // Uploaded news management routes
        Route::resource('uploaded-news', \App\Http\Controllers\Admin\UploadedNewsController::class)->except(['show']);
        Route::post('uploaded-news/{uploaded_news}/toggle-status', [\App\Http\Controllers\Admin\UploadedNewsController::class, 'toggleStatus'])->name('uploaded-news.toggle-status');

        // Alternative route name for backward compatibility
        Route::get('uploaded_news', [\App\Http\Controllers\Admin\UploadedNewsController::class, 'index'])->name('uploaded_news.index');

        // Community Events management routes
        Route::resource('community-events', CommunityEventController::class);
        Route::post('community-events/{community_event}/toggle-status', [CommunityEventController::class, 'toggleStatus'])->name('community-events.toggle-status');
    }); // End of auth middleware group
}); // End of admin route group

Route::get('/listen', function () {
    return view('listen');
})->name('listen');


Route::get('/api/news', [NewsController::class, 'getNews'])->name('api.news');
Route::get('/api/uploaded-news', [NewsController::class, 'getUploadedNews'])->name('api.uploaded-news');
Route::get('/api/news/pop-culture', [NewsController::class, 'getPopCultureNews'])->name('api.news.pop-culture');
Route::get('/api/news/entertainment-headlines', [NewsController::class, 'getEntertainmentHeadlines'])->name('api.news.entertainment-headlines');
Route::get('/api/community-events', [CommunityEventController::class, 'getCommunityEvents'])->name('api.community-events');
Route::post('/api/bluetooth/status', function () {
    return response()->json(['status' => 'received']); });

// Debug route for uploaded news
Route::get('/debug/uploaded-news', function () {
    $allNews = \App\Models\UploadedNews::all();
    $activeNews = \App\Models\UploadedNews::active()->get();

    return response()->json([
        'total_count' => $allNews->count(),
        'active_count' => $activeNews->count(),
        'all_news' => $allNews->map(function ($news) {
            return [
                'id' => $news->id,
                'title' => $news->title,
                'is_active' => $news->is_active,
                'published_at' => $news->published_at,
                'display_order' => $news->display_order,
                'image_path' => $news->image_path,
            ];
        }),
        'active_news' => $activeNews->map(function ($news) {
            return [
                'id' => $news->id,
                'title' => $news->title,
                'is_active' => $news->is_active,
                'published_at' => $news->published_at,
                'display_order' => $news->display_order,
            ];
        })
    ]);
});

// Test route for debugging thumbnails
Route::get('/api/test-thumbnail/{artist}/{track}', function ($artist, $track) {
    $artist = urldecode($artist);
    $track = urldecode($track);

    // Test Last.fm API directly
    $response = Http::timeout(10)
        ->get("https://ws.audioscrobbler.com/2.0/", [
            'method' => 'track.getInfo',
            'api_key' => 'dec847bd55ec76fc4a2a2ce99ee801f2',
            'artist' => $artist,
            'track' => $track,
            'format' => 'json'
        ]);

    $lastFmData = $response->successful() ? $response->json() : null;

    // Test our controller method
    $controller = new App\Http\Controllers\NewsController();
    $method = new ReflectionMethod($controller, 'getAlbumArtwork');
    $method->setAccessible(true);
    $result = $method->invoke($controller, $artist, $track);

    return response()->json([
        'artist' => $artist,
        'track' => $track,
        'lastfm_raw_response' => $lastFmData,
        'final_thumbnail' => $result,
        'success' => !is_null($result)
    ]);
});

// Direct Last.fm API test
Route::get('/api/test-lastfm/{artist}/{track}', function ($artist, $track) {
    $artist = urldecode($artist);
    $track = urldecode($track);

    $response = Http::timeout(10)
        ->get("https://ws.audioscrobbler.com/2.0/", [
            'method' => 'track.getInfo',
            'api_key' => 'dec847bd55ec76fc4a2a2ce99ee801f2',
            'artist' => $artist,
            'track' => $track,
            'format' => 'json'
        ]);

    return response()->json([
        'url' => $response->effectiveUri(),
        'status' => $response->status(),
        'data' => $response->json()
    ]);
});

Route::get('/api/concerts', [ConcertController::class, 'getConcerts'])->name('api.concerts');
Route::get('/api/concerts/nearby', [ConcertController::class, 'getNearbyPopConcerts'])->name('api.concerts.nearby');
Route::get('/api/concerts/homepage', [ConcertController::class, 'getHomepageConcerts'])->name('api.concerts.homepage');
Route::get('/api/concerts/location', [ConcertController::class, 'getConcertsByLocation'])->name('api.concerts.location');
Route::get('/api/concerts/trending', [ConcertController::class, 'getTrendingArtistsConcerts'])->name('api.concerts.trending');

// Pop concerts page
Route::get('/pop-concerts', function () {
    return view('concerts.pop-concerts');
})->name('pop-concerts');

// Test concerts page
Route::get('/test-concerts', function () {
    return view('test-concerts');
})->name('test-concerts');






// CSRF token endpoint for admin.html
Route::get('/csrf-token', function () {
    return response()->json(['csrf_token' => csrf_token()]);
});

Route::get('/proxy/nowplaying', function () {
    $response = \Illuminate\Support\Facades\Http::get('https://streams.radiomast.io/api/nowplaying/0');
    return response($response->body(), $response->status())
        ->header('Content-Type', 'application/json');
});



// Debug Email Route



Route::post('/api/contact/send', [ContactController::class, 'send'])->name('contact.send');

// Debug route to verify HTTPS API email sending
Route::get('/debug-https-email', function () {
    try {
        $adminEmail = env('ADMIN_EMAIL');

        if (!$adminEmail) {
            return response()->json([
                'success' => false,
                'message' => 'ADMIN_EMAIL is not set in the .env file'
            ], 500);
        }

        // Direct Resend API call (HTTPS)
        // This confirms we are NOT using SMTP
        $result = \Resend\Laravel\Facades\Resend::emails()->send([
            'from' => config('mail.from.address', 'noreply@jammin92.com'),
            'to' => $adminEmail,
            'subject' => 'HTTPS API Email Test',
            'html' => '<h1>HTTPS API Works!</h1><p>This email was sent directly via the Resend HTTPS API, bypassing SMTP ports.</p>',
        ]);

        return response()->json([
            'success' => true,
            'method' => 'HTTPS API (Resend)',
            'message' => 'Email sent successfully via HTTPS API',
            'api_response' => $result
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to send email via HTTPS API',
            'error' => $e->getMessage()
        ], 500);
    }
});


// iTunes Search API proxy — avoids browser CORS issues
Route::get('/proxy/itunes-search', function () {
    $term = request('term', '');
    $extraParams = request('extra', '');

    if (empty($term)) {
        return response()->json(['error' => 'No search term provided'], 400);
    }

    // Build the upstream iTunes Search URL
    $itunesBaseUrl = env('ITUNES_API_URL', 'https://itunes.apple.com');
    $itunesUrl = $itunesBaseUrl . '/search?term=' . rawurlencode($term)
        . '&media=music&entity=song&limit=10'
        . ($extraParams ? '&' . ltrim($extraParams, '&') : '');

    // Cache for 60 seconds to avoid hammering iTunes
    $cacheKey = 'itunes_search_' . md5($itunesUrl);
    if (cache()->has($cacheKey)) {
        return response()->json(cache()->get($cacheKey))
            ->header('X-Cache', 'HIT')
            ->header('Access-Control-Allow-Origin', '*');
    }

    try {
        $response = \Illuminate\Support\Facades\Http::timeout(8)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; JamminRadio/1.0)'])
            ->get($itunesUrl);

        if ($response->successful()) {
            $data = $response->json();
            cache()->put($cacheKey, $data, now()->addSeconds(60));
            return response()->json($data)
                ->header('Cache-Control', 'public, max-age=60')
                ->header('Access-Control-Allow-Origin', '*');
        }

        return response()->json(['error' => 'iTunes returned ' . $response->status()], 502);

    } catch (\Exception $e) {
        \Log::warning('iTunes search proxy failed: ' . $e->getMessage());
        return response()->json(['error' => $e->getMessage()], 502);
    }
});

Route::get('/proxy/artwork', function () {
    $url = request('url');

    if (empty($url)) {
        return response('No URL provided', 400);
    }

    // Validate it's an iTunes/Apple URL only
    $allowedDomains = [
        'mzstatic.com',           // covers ALL *.mzstatic.com subdomains automatically
        'itunes.apple.com',       // covers *.itunes.apple.com
        'apple-itunes.apple.com', // sibling — must stay explicit
    ];
    $urlHost = parse_url($url, PHP_URL_HOST);
    $isAllowed = collect($allowedDomains)->contains(fn($domain) => str_ends_with($urlHost, $domain));

    if (!$isAllowed) {
        return response('URL not allowed', 403);
    }

    // Try Laravel Http facade first
    try {
        $response = \Illuminate\Support\Facades\Http::timeout(10)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0'])
            ->get($url);

        if ($response->successful() && strlen($response->body()) > 1000) {
            return response($response->body(), 200)
                ->header('Content-Type', 'image/jpeg')
                ->header('Cache-Control', 'public, max-age=3600')
                ->header('Access-Control-Allow-Origin', '*');
        }
    } catch (\Exception $e) {
        // Fall through to cURL
    }

    // Fallback: cURL (more reliable)
    try {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_USERAGENT => 'Mozilla/5.0',
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $imageData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && !empty($imageData)) {
            return response($imageData, 200)
                ->header('Content-Type', 'image/jpeg')
                ->header('Cache-Control', 'public, max-age=3600')
                ->header('Access-Control-Allow-Origin', '*');
        }

        return response('Could not fetch image', 500);

    } catch (\Exception $e) {
        return response('Error: ' . $e->getMessage(), 500);
    }
});