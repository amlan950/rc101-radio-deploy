<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
        <script>
        if (window.location.hash) {
            history.scrollRestoration = 'manual';
            window.scrollTo(0, 0);
        }
    </script>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta charset="UTF-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @php
        header('Content-Type: text/html; charset=utf-8');
    @endphp
    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicon.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/favicon.png') }}">
    <link rel="shortcut icon" href="{{ asset('images/favicon.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/favicon.png') }}">

    <!-- DNS Preconnect hints (speed up external resource loading) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Righteous&display=swap"
        rel="stylesheet">
    <!-- In your main layout file (app.blade.php or similar) -->
    <link href="{{ asset('css/theme.css') }}?v={{ time() }}" rel="stylesheet">
    <!-- <link rel="stylesheet" href="{{ asset('css/app.css') }}"> -->


    @php
        use App\Models\Footer;

        $footer = Footer::getFooter();
        $seo    = $footer->seo_settings ?? [];

        // ── Load Slogan (Tagline) from configuration file ──
        $sloganFile = config('app.slogan_file_path', 'config/slogan.txt');
        $sloganPath = public_path($sloganFile);
        $tagline = (file_exists($sloganPath)) ? trim(file_get_contents($sloganPath)) : 'YOUR HIT MUSIC STATION';

        // ── Fallback values — always non-empty, even without Footer SEO config ──
        // Aim for 50-60 chars for title, 110-160 chars for description (Google/FB guidelines)
        $appName = config('app.name');

        // A child view's @section('title', ...) wins when set; otherwise fall
        // back to the admin-configured SEO title, then the App Name + tagline.
        $pageTitleSection = trim($__env->yieldContent('title'));
        $ogTitle = $pageTitleSection !== ''
            ? $pageTitleSection
            : (!empty($seo['title'])
                ? $seo['title']
                : $appName . ' — ' . $tagline);

        $ogDescription = !empty($seo['description'])
            ? $seo['description']
            : 'Listen to ' . $appName . ' live online — ' . strtolower($tagline) . '. Stream the hottest songs, latest pop hits, and non-stop music 24/7.';

        $ogUrl      = url()->current();   // correct on every page, not just '/'
        $ogSiteName = $appName;

        // ── Social sharing image (1200×630) ──
        // Priority: (1) config() from .env (requested boss), (2) Admin-uploaded SEO image, (3) default artwork
        if (!empty(config('app.social_share_image'))) {
            $ogImagePath = config('app.social_share_image');
        } elseif (!empty($seo['image'])) {
            $ogImagePath = $seo['image'];
        } else {
            $ogImagePath = config('app.default_artwork_path', 'images/alexaicon.jpg');
        }

        // Build a fully-qualified absolute URL using asset() so it always
        // reflects the actual public base URL (not config('app.url') which
        // can be stale / set to localhost).
        if (str_starts_with($ogImagePath, 'http')) {
            $ogImage = $ogImagePath;
        } else {
            $ogImage = asset(ltrim($ogImagePath, '/'));
        }

        // WhatsApp, Facebook, iMessage crawlers reject plain HTTP images.
        // Force HTTPS on the og:image URL so previews work on all platforms.
        $ogImage = preg_replace('/^http:\/\//i', 'https://', $ogImage);

        // Detect image type for og:image:type tag
        $ogImageExt  = strtolower(pathinfo($ogImagePath, PATHINFO_EXTENSION));
        $ogImageType = match($ogImageExt) {
            'png'  => 'image/png',
            'webp' => 'image/webp',
            'gif'  => 'image/gif',
            default => 'image/jpeg',
        };
    @endphp

    {{-- ── Page Title ── --}}
    <title>{{ $ogTitle }}</title>

    {{-- ── Standard SEO meta ── --}}
    <meta name="description" content="{{ $ogDescription }}">

    {{-- ── Open Graph (Facebook / WhatsApp / iMessage / LinkedIn) ── --}}
    {{-- All tags are always emitted so link previews work even without admin SEO config --}}
    <meta property="og:type"             content="website">
    <meta property="og:site_name"        content="{{ $ogSiteName }}">
    <meta property="og:title"            content="{{ $ogTitle }}">
    <meta property="og:description"      content="{{ $ogDescription }}">
    <meta property="og:url"              content="{{ $ogUrl }}">
    <meta property="og:locale"           content="en_US">
    @if(env('FB_APP_ID'))
    <meta property="fb:app_id"           content="{{ env('FB_APP_ID') }}">
    @endif
    {{-- og:image:secure_url is required by Facebook for HTTPS pages to display the image --}}
    <meta property="og:image"            content="{{ $ogImage }}">
    <meta property="og:image:secure_url" content="{{ $ogImage }}">
    <meta property="og:image:type"       content="{{ $ogImageType }}">
    <meta property="og:image:width"      content="1200">
    <meta property="og:image:height"     content="630">
    <meta property="og:image:alt"        content="{{ $ogTitle }}">

    {{-- ── Twitter / X Card ── --}}
    <meta name="twitter:card"        content="summary_large_image">
    <meta name="twitter:title"       content="{{ $ogTitle }}">
    <meta name="twitter:description" content="{{ $ogDescription }}">
    <meta name="twitter:image"       content="{{ $ogImage }}">
    <meta name="twitter:image:alt"   content="{{ $ogTitle }}">

    <style>
        body {
            font-family: 'Poppins', sans-serif;
            color: var(--text-color);
            /*background: linear-gradient(135deg, #FFD700 0%, #FFD700 40%, #1E3C72 60%, #1E3C72 100%);*/
            background-attachment: fixed;
            overflow-x: hidden;
            margin: 0;
            line-height: 1.6;
            min-height: 100vh;
            /* Smooth scrolling */
            scroll-behavior: smooth;
        }

        .brand-font {
            font-family: 'Righteous', cursive;
        }

        .frosted-glass {
            background-color: rgba(255, 255, 255, 0.15);
            /* Slightly transparent white */
            backdrop-filter: blur(10px);
            /* The main blur effect */
            border: 1px solid rgba(255, 255, 255, 0.2);
            /* Subtle border to enhance the effect */
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        /* Promo Slider */
        .promo-slider {
            height: 60px;
            overflow: hidden;
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            position: relative;
        }

        .slide-container {
            position: absolute;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.8s ease;
            color: var(--accent-blue);
            font-weight: 600;
            font-size: 1.2rem;
            text-align: center;
            padding: 0 20px;
        }

        .slide-container a {
            color: var(--accent-blue);
            text-decoration: none;
            border-bottom: 1px dashed var(--accent-blue);
            transition: all 0.3s ease;
        }

        .slide-container a:hover {
            color: var(--warm-white);
            border-bottom: 1px solid var(--warm-white);
        }

        .slide-indicators {
            position: absolute;
            bottom: 10px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 8px;
        }

        .indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.4);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .indicator.active {
            background-color: var(--accent-blue);
            transform: scale(1.1);
        }

        /* Header Styles */
        .navbar {
            padding: 15px 0;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }

        .navbar-brand .logo-icon {
            width: 50px;
            height: 50px;
            background: var(--primary-blue);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--warm-white);
            font-size: 24px;
            font-weight: bold;
        }

        .navbar-brand .logo-text {
            font-size: 28px;
            font-weight: 800;
            color: var(--primary-blue);
            margin-left: 10px;
        }

        .nav-link {
            font-weight: 600;
            font-size: 18px;
            color: var(--dark-gray);
            transition: all 0.3s ease;
            position: relative;
            margin: 0 10px;
        }

        .nav-link:hover {
            color: var(--primary-blue);
        }

        .nav-link::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 3px;
            background-color: var(--accent-blue);
            transition: all 0.3s ease;
        }

        .nav-link:hover::after {
            width: 100%;
        }

        /* Listen Live Button */
        .listen-live-btn {
            background: linear-gradient(90deg, #FFD700 0%, #FFA500 100%);
            color: #1E3C72;
            font-weight: 600;
            border: 2px solid #1E3C72;
            border: none;
            padding: 8px 20px;
            border-radius: 30px;
            font-weight: 700;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 15px rgba(0, 51, 255, 0.3);
            transition: all 0.3s ease;
            animation: pulse 2s infinite;
        }

        .listen-live-btn:hover {
            transform: translateY(-3px) scale(1.03);
            box-shadow: 0 8px 25px rgba(30, 60, 114, 0.4);
            background: linear-gradient(90deg, #FFD700 0%, #FF8C00 100%);
            color: #1E3C72;
        }

        /* Download App Button */
        .download-app-btn {
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: 600;
            border: none;
            padding: 8px 20px;
            border-radius: 30px;
            font-weight: 700;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            transition: all 0.3s ease;
        }

        .download-app-btn:hover {
            transform: translateY(-3px) scale(1.03);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.6);
            background: linear-gradient(90deg, #5a67d8 0%, #6b46c1 100%);
            color: white;
            text-decoration: none;
        }

        /* Hero Section */
        .hero {
            position: relative;
            padding: 100px 0;
            color: var(--warm-white);
            overflow: hidden;
            background: linear-gradient(135deg, rgba(44, 62, 80, 0.90) 0%, rgba(60, 8, 230, 0.63) 100%),
                url('https://images.unsplash.com/photo-1507838153414-b4b713384a76?ixlib=rb-4.0.3&auto=format&fit=crop&w=1950&q=80') no-repeat center center / cover;
        }

        .hero::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="%23ffffff" fill-opacity="0.1" d="M0,128L48,133.3C96,139,192,149,288,160C384,171,480,181,576,165.3C672,149,768,107,864,112C960,117,1056,171,1152,186.7C1248,203,1344,181,1392,170.7L1440,160L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>');
            background-size: cover;
            background-position: bottom;
            opacity: 0.2;
        }

        .hero h1 {
            font-size: 3.5rem;
            margin-bottom: 20px;
            line-height: 1.2;
            font-weight: 700;
        }

        .hero p {
            font-size: 1.4rem;
            margin-bottom: 40px;
            opacity: 0.9;
        }

        /* Content Sections */

        .section-header {
            text-align: center;
            margin-bottom: 50px;
        }

        .section-header h2 {
            font-size: 2.5rem;
            color: var(--primary-blue);
            margin-bottom: 15px;
            font-weight: 700;
        }

        .section-bg {
            background: rgba(255, 255, 255, 0.6);
            backdrop-filter: blur(5px);
        }

        .card {
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(44, 62, 80, 0.06);
            transition: all 0.3s ease;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
        }

        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 16px rgba(44, 62, 80, 0.12);
            border-color: var(--accent-blue);
        }

        .card-img {
            height: 200px;
            background: linear-gradient(45deg, var(--primary-blue), var(--secondary-blue));
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--warm-white);
            font-size: 3rem;
            overflow: hidden;
            position: relative;
        }

        .card-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .card:hover .card-img img {
            transform: scale(1.05);
        }

        .spinner-border {
            width: 3rem;
            height: 3rem;
        }

        .card-content {
            padding: 25px;
        }

        .card h3 {
            font-size: 1.4rem;
            margin-bottom: 15px;
            color: var(--primary-blue);
            font-weight: 600;
        }

        .btn {
            display: inline-block;
            background: var(--primary-blue);
            color: var(--warm-white);
            text-decoration: none;
            padding: 12px 25px;
            border-radius: 30px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: 2px solid var(--primary-blue);
        }

        .btn:hover {
            background: var(--secondary-blue);
            color: var(--warm-white);
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(44, 62, 80, 0.2);
        }

        .btn-accent {
            background: var(--accent-blue);
            color: var(--warm-white);
            border-color: var(--accent-blue);
        }

        .btn-accent:hover {
            background: var(--accent-teal);
            color: var(--warm-white);
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(22, 160, 133, 0.2);
        }

        /* Bootstrap Button Overrides */
        .btn-primary {
            background-color: var(--primary-blue) !important;
            border-color: var(--primary-blue) !important;
            color: var(--warm-white) !important;
        }

        .btn-primary:hover,
        .btn-primary:focus,
        .btn-primary:active {
            background-color: var(--secondary-blue) !important;
            border-color: var(--secondary-blue) !important;
            color: var(--warm-white) !important;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(44, 62, 80, 0.2) !important;
        }

        .btn-outline-secondary {
            color: var(--medium-gray) !important;
            border-color: var(--border-color) !important;
        }

        .btn-outline-secondary:hover {
            background-color: var(--medium-gray) !important;
            border-color: var(--medium-gray) !important;
            color: var(--warm-white) !important;
        }

        #play-to-listen-text {
            color: white;
            font-size: 1rem;
            font-weight: 600;
            text-align: left;
        }

        /* Form Controls */
        .form-control {
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 12px 16px;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(5px);
        }

        .form-control:focus {
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.15);
            outline: none;
        }

        /* Alert Styles */
        .alert-warning {
            background-color: rgba(230, 126, 34, 0.1);
            border-color: var(--accent-orange);
            color: var(--text-color);
            border-radius: 6px;
        }

        /* News Scroll Container */
        .news-scroll-container {
            max-height: 600px;
            overflow-y: auto;
            padding: 20px;
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 4px 16px rgba(44, 62, 80, 0.1);
            position: relative;
        }

        /* Scroll fade effect at bottom */
        .news-scroll-container::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 30px;
            background: linear-gradient(transparent, rgba(255, 255, 255, 0.9));
            pointer-events: none;
            border-radius: 0 0 15px 15px;
        }

        /* Custom Scrollbar */
        .news-scroll-container::-webkit-scrollbar {
            width: 8px;
        }

        .news-scroll-container::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 4px;
        }

        .news-scroll-container::-webkit-scrollbar-thumb {
            background: var(--accent-blue);
            border-radius: 4px;
            transition: background 0.3s ease;
        }

        .news-scroll-container::-webkit-scrollbar-thumb:hover {
            background: var(--primary-blue);
        }

        /* Billboard Card Styles */
        .billboard-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(248, 249, 250, 0.95) 100%);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(52, 152, 219, 0.2);
            box-shadow: 0 8px 25px rgba(44, 62, 80, 0.1);
        }

        .billboard-card .card-header {
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            color: var(--warm-white);
            border-bottom: none;
        }

        .billboard-song-item {
            background: rgba(255, 255, 255, 0.8);
            border: 1px solid rgba(52, 152, 219, 0.1);
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 8px;
            transition: all 0.3s ease;
            cursor: pointer;
            min-height: 80px;
        }

        .billboard-song-item:hover {
            background: rgba(52, 152, 219, 0.1);
            border-color: var(--accent-blue);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.15);
        }

        .billboard-position {
            background: var(--accent-blue);
            color: var(--warm-white);
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.9rem;
        }

        .billboard-song-title {
            font-weight: 600;
            color: var(--primary-blue);
            margin-bottom: 2px;
        }

        .billboard-artist {
            color: var(--medium-gray);
            font-size: 0.9rem;
        }

        .billboard-stats {
            font-size: 0.8rem;
            color: var(--medium-gray);
        }

        .billboard-container-scroll {
            max-height: 400px;
            overflow-y: auto;
            padding-right: 10px;
        }

        .billboard-container-scroll::-webkit-scrollbar {
            width: 6px;
        }

        .billboard-container-scroll::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 3px;
        }

        .billboard-container-scroll::-webkit-scrollbar-thumb {
            background: var(--accent-blue);
            border-radius: 3px;
        }

        .billboard-thumbnail {
            width: 60px;
            height: 60px;
            border-radius: 6px;
            object-fit: cover;
            margin-right: 15px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            transition: all 0.3s ease;
        }

        .billboard-song-item:hover .billboard-thumbnail {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
        }

        .billboard-thumbnail-placeholder {
            width: 60px;
            height: 60px;
            border-radius: 6px;
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-teal));
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--warm-white);
            font-size: 1.5rem;
            margin-right: 15px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        /* News Cards in Scroll Container */
        .news-scroll-container .card {
            margin-bottom: 0;
            transition: all 0.3s ease;
        }

        .news-scroll-container .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(44, 62, 80, 0.15);
        }

        /* Contact Form */
        .contact-form {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .form-control {
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }

        .form-control:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 0.25rem rgba(0, 51, 255, 0.1);
        }

        /* Footer */
        footer {
            background: var(--dark-gray);
            color: var(--light-gray);
            padding: 60px 0 calc(var(--player-height) + 30px);
        }

        .footer-column h3 {
            color: var(--warm-white);
            font-size: 1rem;
            margin-bottom: 25px;
            position: relative;
            padding-bottom: 10px;
        }

        .footer-column h3::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 3px;
            background: var(--accent-blue);
        }

        .footer-links li {
            margin-bottom: 12px;
        }

        .footer-links a {
            color: var(--light-gray);
            text-decoration: none;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .footer-links a:hover {
            color: var(--accent-blue);
            transform: translateX(3px);
        }

        .social-links {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .social-links a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            color: var(--warm-white);
            transition: all 0.3s ease;
            font-size: 18px;
        }

        .social-links a:hover {
            background: var(--primary-blue);
            transform: translateY(-5px);
        }

        .copyright {
            text-align: center;
            padding-top: 30px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            margin-top: 40px;
            font-size: 0.9rem;
            opacity: 0.7;
        }

        /* Animation Elements */
        .pulse {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.05);
            }

            100% {
                transform: scale(1);
            }
        }

        .floating {
            animation: floating 3s ease-in-out infinite;
        }

        @keyframes floating {
            0% {
                transform: translateY(0px);
            }

            50% {
                transform: translateY(-15px);
            }

            100% {
                transform: translateY(0px);
            }
        }

        /* Sticky Player */
        .sticky-player {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            height: var(--player-height);
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            color: white;
            display: flex;
            align-items: center;
            padding: 0 20px;
            z-index: 1000;
            box-shadow: 0 -4px 15px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
            transform: translateY(0);
        }

        .player-minimized {
            transform: translateY(calc(var(--player-height) - 20px));
        }

        .player-controls {
            display: flex;
            align-items: center;
            gap: 15px;
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
        }

        .player-info {
            flex-grow: 1;
            display: flex;
            align-items: center;
            gap: 15px;
            min-width: 0;
            /* Allow text truncation */
            overflow: hidden;
            /* Mobile touch: cursor:pointer required for iOS Safari click events on divs */
            cursor: pointer;
            touch-action: manipulation;
            -webkit-tap-highlight-color: transparent;
            user-select: none;
            -webkit-user-select: none;
        }

        .mini-player-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--accent-blue);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            flex-shrink: 0;
            font-size: 16px;
            /* ── Mobile touch fixes ────────────────────────────────
               iOS Safari only fires click on non-interactive elements (div, span)
               when they have cursor:pointer. Without it, tapping does nothing.
               touch-action:manipulation removes the 300ms tap delay on Android. */
            cursor: pointer;
            touch-action: manipulation;
            -webkit-tap-highlight-color: transparent;
            user-select: none;
            -webkit-user-select: none;
        }

        .player-title {
            font-weight: 600;
            font-size: 1rem;
            color: black;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .player-song {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.9);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        #current-title {
            font-weight: bold;
            text-transform: uppercase;
            color: white;
            font-size: 0.9rem;
        }

        #current-artist {
            font-style: italic;
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.85rem;
        }

        .player-buttons {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .player-btn {
            background: transparent;
            border: none;
            color: white;
            font-size: 18px;
            cursor: pointer;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .player-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .minimize-btn {
            background: var(--accent-yellow);
            color: var(--primary-blue);
        }

        .minimize-btn:hover {
            background: var(--accent-yellow-alt);
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .hero {
                padding: 60px 0;
            }

            .hero h1 {
                font-size: 2.5rem;
            }

            .hero p {
                font-size: 1.1rem;
            }

            .player-title,
            .player-song {
                font-size: 0.85rem;
            }

            .player-buttons {
                gap: 8px;
            }

            .player-btn {
                width: 35px;
                height: 35px;
                font-size: 16px;
            }
        }

        /* App Download Modal Styling */
        #appDownloadModal .modal-content {
            border: none;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            overflow: hidden;
        }

        #appDownloadModal .modal-header {
            background: linear-gradient(135deg, #FFD700 0%, #FFA500 50%, #1E3C72 100%);
            color: white;
            padding: 20px 25px;
            border-bottom: none;
        }

        #appDownloadModal .modal-title {
            font-weight: 700;
            font-size: 1.3rem;
        }

        #appDownloadModal .btn-close {
            filter: brightness(0) invert(1);
            opacity: 1;
            background-color: rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            transition: all 0.3s ease;
        }

        #appDownloadModal .btn-close:hover {
            opacity: 1;
            background-color: rgba(255, 255, 255, 0.5);
            transform: scale(1.1);
        }

        #appDownloadModal .btn-close:focus {
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.3);
            opacity: 1;
        }

        #appDownloadModal .custom-close-btn {
            position: absolute;
            top: 15px;
            right: 15px;
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.9);
            border: none;
            border-radius: 50%;
            color: #333;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            z-index: 10;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        #appDownloadModal .custom-close-btn:hover {
            background: rgba(255, 255, 255, 1);
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
        }

        #appDownloadModal .custom-close-btn:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.5);
        }

        #appDownloadModal .app-icon {
            width: 160px;
            height: 160px;
            margin: 0 auto;
            background: transparent;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: none;
        }

        #appDownloadModal .app-icon i {
            color: white;
        }

        #appDownloadModal .modal-body h4 {
            color: var(--primary-blue);
            font-weight: 700;
            margin-bottom: 10px;
        }

        #appDownloadModal .modal-body p {
            color: var(--medium-gray);
            font-size: 0.95rem;
        }

        #appDownloadModal .btn-primary {
            background: linear-gradient(135deg, #1E3C72 0%, #2c3e50 50%, #3498db 100%);
            border: none;
            border-radius: 15px;
            padding: 12px 30px;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
        }

        #appDownloadModal .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(52, 152, 219, 0.4);
            background: linear-gradient(135deg, #1A2F5A 0%, #243447 50%, #2980b9 100%);
        }

        #appDownloadModal .btn-outline-primary {
            border: 2px solid #FFD700;
            color: #1E3C72;
            border-radius: 15px;
            padding: 15px 20px;
            font-weight: 600;
            font-size: 1rem;
            background: transparent;
            transition: all 0.3s ease;
        }

        #appDownloadModal .btn-outline-primary:hover {
            background: linear-gradient(135deg, #FFD700 0%, #FF8C00 50%, #FF6347 100%);
            border-color: transparent;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(255, 140, 0, 0.3);
        }

        #appDownloadModal .btn-success {
            background: linear-gradient(135deg, #FFD700 0%, #FFA500 100%);
            border: none;
            border-radius: 15px;
            padding: 15px 20px;
            font-weight: 600;
            font-size: 1rem;
            box-shadow: 0 5px 20px rgba(255, 215, 0, 0.3);
            color: #1E3C72;
            transition: all 0.3s ease;
        }

        #appDownloadModal .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(255, 215, 0, 0.5);
            background: linear-gradient(135deg, #FFC700 0%, #FF8C00 100%);
        }

        /* Responsive modal */
        @media (max-width: 576px) {
            #appDownloadModal .modal-dialog {
                margin: 20px;
            }

            #appDownloadModal .modal-content {
                border-radius: 15px;
            }

            #appDownloadModal .modal-header {
                padding: 15px 20px;
            }

            #appDownloadModal .modal-body {
                padding: 25px 20px;
            }

            #appDownloadModal .app-icon {
                width: 60px;
                height: 60px;
            }

            #appDownloadModal .app-icon i {
                font-size: 2rem;
            }
        }

        /* Floating Request Button Styles */
        .floating-request-btn {
            position: fixed;
            bottom: 90px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 9999;
        }

        .request-btn {
            background: linear-gradient(135deg, #FF416C, #FF4B2B);
            border: none;
            border-radius: 50px;
            color: white;
            padding: 14px 20px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 6px 20px rgba(255, 65, 108, 0.5);
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s ease;
            animation: request-pulse 1.5s infinite;
            text-transform: uppercase;
            letter-spacing: 1px;
            position: relative;
            overflow: hidden;
        }

        .request-btn:before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: 0.5s;
        }

        .request-btn:hover:before {
            left: 100%;
        }

        .request-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(255, 107, 107, 0.6);
            color: white;
        }

        .request-btn i {
            font-size: 15px;
        }

        @media(max-width: 390px) {
            .request-btn i {
                font-size: 12px;
            }

            .request-btn {
                padding: 12px 18px;
                font-size: 12px;
            }
        }

        @keyframes request-pulse {
            0% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(255, 65, 108, 0.7);
            }

            70% {
                transform: scale(1.05);
                box-shadow: 0 0 0 15px rgba(255, 65, 108, 0);
            }

            100% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(255, 65, 108, 0);
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/htmx.org@1.9.12/dist/htmx.min.js"></script>
    <script>
        document.addEventListener("htmx:afterSettle", function(evt) {
            if (evt.detail.target.tagName && evt.detail.target.tagName.toLowerCase() === 'main') {
                window.document.dispatchEvent(new Event("DOMContentLoaded", {
                    bubbles: true,
                    cancelable: true
                }));
            }
        });
    </script>

    {{-- ── JSON-LD Structured Data (RadioStation + WebSite) — Google Rich Results ── --}}
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@graph": [
        {
          "@type": "RadioStation",
          "name": "{{ $ogSiteName }}",
          "description": "{{ $ogDescription }}",
          "url": "{{ config('app.url') }}",
          "logo": {
            "@type": "ImageObject",
            "url": "{{ $ogImage }}"
          },
          "image": "{{ $ogImage }}",
          "broadcastAffiliateOf": {
            "@type": "Organization",
            "name": "{{ $ogSiteName }}"
          }
        },
        {
          "@type": "WebSite",
          "name": "{{ $ogSiteName }}",
          "url": "{{ config('app.url') }}"
        }
      ]
    }
    </script>

    {{-- Page-specific / component styles pushed via @push('styles') — e.g. index.blade.php's
         banner slider CSS, the contest modal, admin ad forms. Placed last so these can
         override the base theme CSS loaded earlier in <head>. Without this @stack call,
         every @push('styles') block anywhere in the app is silently discarded. --}}
    @stack('styles')
</head>

<body hx-boost="true" hx-target="main" hx-select="main" hx-swap="outerHTML">

    <x-app-banner /> {{-- Mobile iPhone/Android "get the app" suggestion --}}

    @include('partials.header') {{-- Shared header --}}

    <main>
        @yield('content') {{-- Page-specific content --}}
    </main>

    @include('partials.footer') {{-- Shared footer --}}
    <!-- Bootstrap JS with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Per-station storage namespace — keeps localStorage/sessionStorage keys and the
        // BroadcastChannel channel name isolated between different station deployments
        // of this same codebase (and origin), derived from the configurable App Name
        // instead of a hardcoded station name. Must be defined before the player/
        // metadata scripts below, which key all cross-tab state off window.skey().
        (function () {
            var appName = @json(config('app.name'));
            var slug = (appName || 'radio').toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '') || 'radio';
            window._appStorageNamespace = slug + '_';
            window.skey = function (key) { return window._appStorageNamespace + key; };
        })();
    </script>

    @include('layouts.partials.scripts-core-player')

    @include('layouts.partials.scripts-metadata')

    @include('layouts.partials.scripts-app-modals')




    <!-- Stack Scripts -->
    @stack('scripts')

    <!-- Contest Modal Component -->

    @if (!request()->routeIs('events.*'))
        <!-- Floating Request Button -->
        <div class="floating-request-btn">
            <button class="request-btn" id="openRequestModalGlobal">
                <i class="fas fa-headphones"></i> Request a Song
            </button>
        </div>
    @endif

    <!-- Scroll To Top Button -->
    <button id="scroll-to-top" title="Back to top" aria-label="Scroll to top">
        <i class="fas fa-chevron-up"></i>
    </button>

    <style>
        #scroll-to-top {
            position: fixed;
            bottom: calc(var(--player-height, 80px) + 16px);
            right: 20px;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            color: #fff;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            box-shadow: 0 4px 14px rgba(30, 60, 114, 0.45);
            z-index: 1050;
            opacity: 0;
            visibility: hidden;
            transform: translateY(12px);
            transition: opacity 0.3s ease, transform 0.3s ease, visibility 0.3s ease;
        }
        #scroll-to-top.visible {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        #scroll-to-top:hover {
            background: linear-gradient(135deg, var(--secondary-blue), var(--accent-blue));
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(30, 60, 114, 0.55);
        }
    </style>
    <script>
        (function () {
            var btn = document.getElementById('scroll-to-top');
            if (!btn) return;
            window.addEventListener('scroll', function () {
                btn.classList.toggle('visible', window.scrollY > 300);
            }, { passive: true });
            btn.addEventListener('click', function () {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        })();
    </script>

</body>

</html>
