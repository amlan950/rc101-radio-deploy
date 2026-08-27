@extends('layouts.app')

{{-- Title is intentionally not overridden here — the layout builds it from the
     configurable App Name + admin SEO settings, so no station name is hardcoded. --}}

@push('styles')
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">

    <style>
    /* Remove scrolling behavior from news section */
    .news-container {
        max-height: none !important;
        overflow: visible !important;
        overflow-y: visible !important;
        overflow-x: visible !important;
    }

    /* Ensure news items display properly without scroll */
    #news-container {
        max-height: none !important;
        overflow: visible !important;
    }

    /* Remove any scroll-related styling */
    .news-scroll-container {
        max-height: none !important;
        overflow: visible !important;
        overflow-y: visible !important;
    }

    /* Concert Card Styles */
    .concert-card {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        border: none;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .concert-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 15px rgba(0, 0, 0, 0.2);
    }

    .concert-card .card-img-top {
        border-bottom: 3px solid #0d6efd;
    }

    .concert-card .card-title {
        color: #2c3e50;
        font-weight: 600;
        font-size: 1.1rem;
        margin-bottom: 0.5rem;
    }

    .concert-card .card-text {
        color: #e74c3c;
        font-weight: 500;
        margin-bottom: 1rem;
    }

    .concert-card .btn-primary {
        background-color: #0d6efd;
        border-color: #0d6efd;
        border-radius: 8px;
        padding: 0.5rem 1rem;
        font-weight: 500;
        transition: all 0.3s ease;
    }

    .concert-card .btn-primary:hover {
        background-color: #0b5ed7;
        border-color: #0a58ca;
        transform: translateY(-2px);
    }

    .concert-card .text-muted {
        font-size: 0.875rem;
        line-height: 1.4;
    }

    .concert-card .text-muted i {
        width: 16px;
        margin-right: 6px;
        color: #6c757d;
    }

    /* Location Status Styles */
    .location-status .alert {
        border-radius: 12px;
        border: none;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    /* Manual Search Styles */
    #homepage-manual-search {
        background-color: #f8f9fa;
        padding: 2rem;
        border-radius: 12px;
        border: 1px solid #e9ecef;
    }

    #homepage-manual-search .input-group {
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    #homepage-manual-search .form-control {
        border: none;
        border-right: 1px solid #e9ecef;
    }

    #homepage-manual-search .form-control:focus {
        box-shadow: none;
    }

    #homepage-manual-search .btn-primary {
        border: none;
        border-radius: 0 8px 8px 0;
        padding: 0.5rem 1.5rem;
    }

    /* Location Search Button */
    .location-search-btn {
        border-radius: 0 8px 8px 0;
        padding: 0.5rem 1.5rem;
    }

    /* Trending Concert Card Styles */
    .trending-concert-card {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        position: relative;
    }

    .trending-concert-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 15px rgba(0, 0, 0, 0.2);
    }

    .trending-concert-card .card-img-top {
        border-bottom: 3px solid #0d6efd;
        height: 160px !important;
        object-fit: cover;
    }

    .trending-concert-card .card-body {
        padding: 1rem !important;
    }

    .trending-concert-card .card-title {
        font-size: 0.9rem;
        font-weight: 700;
        color: #2c3e50;
        margin-bottom: 0.5rem !important;
        line-height: 1.3;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .trending-concert-card .text-primary {
        font-size: 0.8rem;
        font-weight: 600;
    }

    .trending-concert-card .text-muted {
        font-size: 0.75rem;
        line-height: 1.4;
    }

    .trending-concert-card .btn-sm {
        font-size: 0.75rem;
        padding: 0.375rem 0.75rem;
    }

    .trending-concert-card .badge {
        font-size: 0.65rem;
        padding: 0.25rem 0.5rem;
    }

    /* Top Banner Slider — height matches the Entertainment News card images
       (.news-card-image: 200px desktop / 150px mobile) so the two sections read
       as one consistent layout. */
    .ads-section {
        position: relative;
        overflow: hidden;
    }

    /* Reorder visually — DOM order (indicators, inner, controls) is fixed so
       Bootstrap can find the indicators as a descendant of the carousel root,
       but on screen the image comes first, then the arrow bar, then the dots. */
    #adsCarousel {
        display: flex;
        flex-direction: column;
    }

    #adsCarousel .carousel-inner {
        order: 1;
        height: 200px;
        border-radius: 0.5rem;
        overflow: hidden;
    }

    #adsCarousel .carousel-controls-bar {
        order: 2;
    }

    #adsCarousel .carousel-indicators {
        order: 3;
    }

    #adsCarousel .carousel-item {
        height: 200px;
        transition: transform 0.6s ease-in-out, opacity 0.5s ease-in-out;
    }

    #adsCarousel .carousel-item > a,
    #adsCarousel .carousel-item > .carousel-banner-noclick {
        display: block;
        width: 100%;
        height: 100%;
    }

    #adsCarousel .carousel-banner-noclick {
        cursor: default;
    }

    #adsCarousel .carousel-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    }

    @media (max-width: 768px) {
        #adsCarousel .carousel-inner,
        #adsCarousel .carousel-item {
            height: 150px;
        }
    }

    /* Controls live in a bar under the image, not overlaid on top of it. */
    #adsCarousel .carousel-controls-bar {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 1.5rem;
        padding-top: 0.75rem;
    }

    #adsCarousel .carousel-control-prev,
    #adsCarousel .carousel-control-next {
        position: static;
        width: auto;
        opacity: 1;
    }

    #adsCarousel .carousel-control-prev-icon,
    #adsCarousel .carousel-control-next-icon {
        width: 2rem;
        height: 2rem;
        background-size: 1.25rem 1.25rem;
        background-color: rgba(0, 0, 0, 0.5);
        border-radius: 50%;
        padding: 0.5rem;
    }

    #adsCarousel .carousel-indicators {
        position: static;
        margin: 0;
    }

    #adsCarousel .carousel-indicators [data-bs-target] {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        margin: 0 5px;
        background-color: rgba(0, 0, 0, 0.3);
        border: none;
        opacity: 1;
    }

    #adsCarousel .carousel-indicators .active {
        background-color: #0d6efd;
    }

    #city-suggestions .list-group-item {
        cursor: pointer;
        padding: 10px 15px;
    }

    #city-suggestions .list-group-item:hover {
        background-color: #f1f1f1;
    }

    .player-song {
        font-size: 0.9rem;
        color: rgba(255, 255, 255, 0.9);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        line-height: 1.2;
    }

    .player-song div {
        display: block;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .player-song div {
        display: block;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    /* Mobile adjustments for Concert Cards */
    @media (max-width: 767px) {
        .concert-card .card-img-top {
            height: 120px !important; /* Smaller image height for 2-column layout */
        }
        .concert-card .card-body {
            padding: 0.5rem !important; /* Tighter padding for mobile */
        }
        .concert-card .card-title {
            font-size: 0.85rem;
            margin-bottom: 0.25rem;
            line-height: 1.2;
            height: 2.4em; /* Match line-clamp height roughly */
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }
        .concert-card .card-text {
            font-size: 0.75rem;
            margin-bottom: 0.25rem;
        }
        .concert-card .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.7rem;
            width: 100% !important;
        }
        .trending-concert-card .card-img-top {
            height: 100px !important;
        }
        .trending-concert-card .card-body {
            padding: 0.5rem !important;
        }
        .trending-concert-card .card-title {
            font-size: 0.75rem;
        }

        /* Event Card adjustments */
        .event-card .card-img {
            height: 120px !important;
        }
        .event-card .card-img i {
            font-size: 2rem !important;
        }
        .event-card .card-content h3 {
            font-size: 0.9rem !important;
        }
        .event-card .card-content p {
            font-size: 0.75rem !important;
            -webkit-line-clamp: 2;
            display: -webkit-box;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .event-card .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.7rem;
            width: 100% !important;
        }
    }
    </style>
@endpush

@section('content')
                                    <!-- Top Anchor for navigation -->
                                    <div id="top"></div>

                                    <!-- Top Banner Slider -->
                                    <section class="ads-section py-5" style="background-color: #f8f9fa;">
                                        <div class="container">
                                            <div class="row justify-content-center">
                                                <div class="col-12 col-lg-10">
                                                    <div id="adsCarousel" class="carousel slide" data-bs-ride="carousel"
                                                        data-bs-interval="5000" data-bs-touch="true" style="border: none !important;">

                                                        {{-- Indicators must live inside the carousel root (required for Bootstrap
                                                             to find them and keep the active dot in sync). They render first in
                                                             the DOM but are visually reordered (via CSS `order`, below) to sit
                                                             under the image, below the prev/next arrow bar. --}}
                                                        @if($activeAds->count() > 1)
                                                            <div class="carousel-indicators">
                                                                @foreach($activeAds as $index => $ad)
                                                                    <button type="button" data-bs-target="#adsCarousel" data-bs-slide-to="{{ $index }}"
                                                                        class="{{ $index === 0 ? 'active' : '' }}"
                                                                        aria-current="{{ $index === 0 ? 'true' : 'false' }}"
                                                                        aria-label="Slide {{ $index + 1 }}"></button>
                                                                @endforeach
                                                            </div>
                                                        @endif

                                                        <!-- Carousel items -->
                                                        <div class="carousel-inner rounded-3 w-100">
                                                            @if($activeAds->isNotEmpty())
                                                                @foreach($activeAds as $index => $ad)
                                                                    <div class="carousel-item {{ $index === 0 ? 'active' : '' }}" data-bs-interval="5000">
                                                                        @if($ad->image_url)
                                                                            @if(!empty($ad->target_url))
                                                                                {{-- Banner with a destination: behaves like an ad, opens the link --}}
                                                                                <a href="{{ $ad->target_url }}" target="_blank" rel="noopener noreferrer" aria-label="{{ $ad->title }}">
                                                                                    <img src="{{ $ad->image_url }}" alt="{{ $ad->title }}"
                                                                                        onerror="console.error('Failed to load image:', this.src)">
                                                                                </a>
                                                                            @else
                                                                                {{-- Banner without a destination: non-clickable, never navigates or reloads the page --}}
                                                                                <div class="carousel-banner-noclick" role="img" aria-label="{{ $ad->title }}">
                                                                                    <img src="{{ $ad->image_url }}" alt="{{ $ad->title }}"
                                                                                        onerror="console.error('Failed to load image:', this.src)">
                                                                                </div>
                                                                            @endif
                                                                        @endif
                                                                    </div>
                                                                @endforeach
                                                            @else
                                                                <!-- Fallback content if no ads are available -->
                                                                <div class="carousel-item active">
                                                                    <div class="text-center p-5 bg-light rounded d-flex flex-column justify-content-center h-100">
                                                                        <div class="mb-3">
                                                                            <i class="fas fa-ad fa-3x text-muted mb-2"></i>
                                                                            <h4 class="text-muted mb-2">Advertisement Space</h4>
                                                                            <p class="text-muted mb-0">No active advertisements at the moment</p>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            @endif
                                                        </div>

                                                        <!-- Controls: placed in a bar under the image, not overlaid on top of it -->
                                                        @if($activeAds->count() > 1)
                                                            <div class="carousel-controls-bar">
                                                                <button class="carousel-control-prev" type="button" data-bs-target="#adsCarousel"
                                                                    data-bs-slide="prev">
                                                                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                                                    <span class="visually-hidden">Previous</span>
                                                                </button>
                                                                <button class="carousel-control-next" type="button" data-bs-target="#adsCarousel"
                                                                    data-bs-slide="next">
                                                                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                                                    <span class="visually-hidden">Next</span>
                                                                </button>
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </section>



                                    <!-- Pop Culture News Section -->
                                    <section class="section" id="pop-culture-news">
                                        <x-pop-culture-news :news="$uploadedNews" />

                                    </section>

                                    <section class="section" id="contest-section">
                                        <x-contest-modal />
                                    </section>
                                    <!-- Concerts Section -->
                                    <section class="section section-bg" id="concerts" style="margin-top: 75px;">
                                        <div class="container">
                                            <div class="section-header">
                                                <h3 class="display-5" style="color: black;font-weight:none;">
                                                    <i class="fas fa-music me-2 text-warning"></i>
                                                    Concerts Nearby
                                                </h3>
                                                <p class="lead">Discover concerts happening in your area, including shows by trending artists like Taylor Swift, Ed Sheeran, and more</p>
                                            </div>

                                            <!-- Location Search -->
                                            <div class="row mb-4">
                            <div class="col-md-8 mx-auto">
                                <div class="position-relative">

                                    <div class="input-group">
                                        <span class="input-group-text bg-white border-end-0" style="height: 56px;">
                                            <i class="fas fa-map-marker-alt text-primary"></i>
                                        </span>

                                        <input type="text"
                                               class="form-control border-start-0"
                                               id="location-search-input"
                                               placeholder="Type City Name"
                                               style="margin-bottom: 0px;"
                                               autocomplete="off">

                                        <button class="btn btn-outline-secondary"
                                                type="button"
                                                id="use-my-location-btn"
                                                style="height: 56px;border:none;"
                                                title="Use my current location">
                                            <i class="fas fa-crosshairs"></i>
                                        </button>

                                        <button class="btn btn-primary"
                                                type="button"
                                                id="location-search-btn"
                                                style="height: 56px;">
                                            <i class="fas fa-search me-2"></i>Search
                                        </button>
                                    </div>

                                    <!-- ✅ CITY DROPDOWN -->
                                    <div id="city-suggestions"
                                        class="list-group shadow w-100"
                                        style="position:absolute; top:100%; display:none;
                                                max-height:250px; overflow-y:auto; z-index:1050;">
                                    </div>


                                </div>

                                <div class="form-text text-center mt-2">
                                    <small>Enter any city name or click <i class="fas fa-crosshairs"></i> to use your current
                                        location</small>
                                </div>
                            </div>

                                            </div>

                                            <!-- Concerts Container -->
                                            <div id="homepage-concerts-container">
                                                <!-- Loading placeholder -->
                                                <div class="col-12 text-center" id="homepage-concerts-loading">
                                                    <div class="spinner-border text-primary" role="status">
                                                        <span class="visually-hidden">Loading concerts...</span>
                                                    </div>
                                                    <p class="mt-2">Loading concerts...</p>
                                                </div>

                                                <!-- Concerts will be loaded here -->
                                                <div id="homepage-concerts-grid" class="row g-4" style="display: none;"></div>

                                                <!-- No concerts message -->
                                                <div class="col-12 text-center" id="homepage-no-concerts" style="display: none;">
                                                    <div class="alert alert-warning">
                                                        <i class="fas fa-music fa-2x mb-3 text-warning"></i>
                                                        <h4>No Concerts Found</h4>
                                                        <p class="mb-0">No concerts found at the moment. Please check back later!</p>
                                                    </div>
                                                </div>

                                                <!-- Error message -->
                                                <div class="col-12 text-center" id="homepage-concerts-error" style="display: none;">
                                                    <div class="alert alert-danger">
                                                        <i class="fas fa-exclamation-triangle fa-2x mb-3 text-danger"></i>
                                                        <h4>Unable to Load Concerts</h4>
                                                        <p class="mb-0" id="homepage-error-message">Please try again later.</p>
                                                    </div>
                                                </div>
                                            </div>
{{--

    <!-- Trending Artists Subsection -->
                                            <div class="mt-5 pt-4 border-top border-light">
                                                <div class="section-header mb-4">
                                                    <h3 class="brand-font text-primary">🔥 Trending Artists</h3>
                                                    <p class="lead">Catch the biggest names in music on tour</p>
                                                </div>
                                                <div id="trending-artists-container" class="mt-4">
                                                <!-- Trending Artists Loading -->
                                                    <div class="col-12 text-center" id="trending-artists-loading">
                                                        <div class="spinner-border text-primary" role="status">
                                                            <span class="visually-hidden">Loading trending artists...</span>
                                                        </div>
                                                        <p class="mt-2">Loading trending artists...</p>
                                                    </div>
                                                </div>

                                                <!-- Trending Artists Grid -->
                                                <div class="row g-4" id="trending-artists-grid" style="display: none;"></div>

                                                <!-- No trending artists message -->
                                                <div class="col-12 text-center" id="trending-no-artists" style="display: none;">
                                                    <div class="alert alert-info">
                                                        <i class="fas fa-music fa-2x mb-3 text-info"></i>
                                                        <h4>No Trending Artists Found</h4>
                                                        <p class="mb-0">No trending artists currently have scheduled concerts. Check back later for
                                                            updates!</p>
                                                    </div>
                                                </div>

                                                <!-- Trending artists error message -->
                                                <div class="col-12 text-center" id="trending-artists-error" style="display: none;">
                                                    <div class="alert alert-danger">
                                                        <i class="fas fa-exclamation-triangle fa-2x mb-3 text-danger"></i>
                                                        <h4>Unable to Load Trending Artists</h4>
                                                        <p class="mb-0">We're having trouble loading trending artists. Please try again later.</p>
                                                    </div>
                                                </div>
                                            </div>
    --}}
                                            
                                        </div>
                                    </section>

                                    <!-- Events Section -->
                                    <section class="section" id="events" style="margin-top: 75px;">
                                        <div class="container">
                                            <div class="section-header">
                                                <h3 class="display-5">
                                                    <i class="fas fa-users me-2 text-warning"></i>
                                                    Community Events
                                                </h3>

                                                <p class="lead">Join us for these exciting events happening in your community</p>
                                            </div>

                                            <!-- Events Container -->
                                            <div id="community-events-container">
                                                <!-- Loading placeholder -->
                                                <div class="col-12 text-center" id="community-events-loading">
                                                    <div class="spinner-border text-primary" role="status">
                                                        <span class="visually-hidden">Loading events...</span>
                                                    </div>
                                                    <p class="mt-2">Loading events...</p>
                                                </div>

                                                <!-- Events will be loaded here -->
                                                <div id="community-events-grid" class="row g-4" style="display: none;"></div>

                                                <!-- No events message -->
                                                <!-- <div class="col-12 text-center" id="community-no-events" style="display: none;">
                                                    <div class="alert alert-info">
                                                        <i class="fas fa-calendar-alt fa-2x mb-3 text-info"></i>
                                                        <h4>No Events Scheduled</h4>
                                                        <p class="mb-0">Check back soon for upcoming community events!</p>
                                                    </div>
                                                </div> -->

                                                <!-- Error message -->
                                                <div class="col-12 text-center" id="community-events-error" style="display: none;">
                                                    <div class="alert alert-danger">
                                                        <i class="fas fa-exclamation-triangle fa-2x mb-3 text-danger"></i>
                                                        <h4>Unable to Load Events</h4>
                                                        <p class="mb-0">Please try again later.</p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row g-4">
                                                <div class="col-12 col-md-4">
                                                    <div class="card h-100 event-card">
                                                        <div class="card-img">
                                                            <i class="fas fa-users"></i>
                                                        </div>
                                                        <div class="card-content d-flex flex-column h-100">
                                                            <h3>Meet & Greet</h3>
                                                            <p>Meet your favorite radio hosts and win exclusive merchandise at our monthly event.</p>
                                                            <a href="{{ route('events.meet-and-greet') }}" class="btn btn-sm btn-outline-primary mt-auto d-block mx-auto">Learn More</a>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-12 col-md-4">
                                                    <div class="card h-100 event-card">
                                                        <div class="card-img">
                                                            <i class="fas fa-broadcast-tower"></i>
                                                        </div>
                                                        <div class="card-content d-flex flex-column h-100">
                                                            <h3>Studio Tour</h3>
                                                            <p>Get a behind-the-scenes look at our broadcasting facilities and see how radio magic happens.
                                                            </p>
                                                            <a href="{{ route('events.studio-tour') }}" class="btn btn-sm btn-outline-primary mt-auto d-block mx-auto">Learn More</a>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-12 col-md-4">
                                                    <div class="card h-100 event-card">
                                                        <div class="card-img">
                                                            <i class="fas fa-calendar-check"></i>
                                                        </div>
                                                        <div class="card-content d-flex flex-column h-100">
                                                            <h3>Charity Event</h3>
                                                            <p>Join us for our annual charity event supporting music education in local schools.</p>
                                                            <a href="{{ route('events.charity-event') }}" class="btn btn-sm btn-outline-primary mt-auto d-block mx-auto">Learn More</a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </section>

                                    <!-- Contact Section -->
                                    <section class="section section-bg" id="contact" style="margin-top: 75px;">
                                        <div class="container">
                                            <div class="section-header">
                                                <h3 class="display-5">
                                                    <i class="fas fa-envelope me-2 text-warning"></i>
                                                    Contact Us
                                                </h3>
                                                <p class="lead">We'd love to hear from you! Send us a message and we'll get back to you soon.</p>
                                            </div>

                                            <div class="row justify-content-center">
                                                <div class="col-lg-8">
                                                    <form id="contact-form" class="contact-form" method="POST" action="{{ route('contact.send') }}" hx-boost="false">
                                                        @csrf

                                                        <!-- Message box -->
                                                        <div id="contact-form-message" class="alert d-none"></div>

                                                        <div class="mb-3">
                                                            <label for="name" class="form-label">Your Name</label>
                                                            <input type="text" class="form-control" id="name" name="name" placeholder="Enter your name" required>
                                                        </div>

                                                        <div class="mb-3">
                                                            <label for="email" class="form-label">Email Address</label>
                                                            <input type="email" class="form-control" id="email" name="email" placeholder="Enter your email" required>
                                                        </div>

                                                        <div class="mb-3">
                                                            <label for="subject" class="form-label">Subject</label>
                                                            <input type="text" class="form-control" id="subject" name="subject" placeholder="Enter subject" required>
                                                        </div>

                                                        <div class="mb-3">
                                                            <label for="message" class="form-label">Message</label>
                                                            <textarea class="form-control" id="message" name="message" rows="5"
                                                                placeholder="Enter your message" minlength="10" required></textarea>
                                                        </div>

                                                        <button type="submit" class="btn w-100">
                                                            <i class="fas fa-paper-plane me-2"></i> Send Message
                                                        </button>
                                                    </form>
                                                    <!-- Thank You Message (Hidden by default) -->
                                                    <div id="contact-success" class="alert alert-success text-center d-none">
                                                        <h4 class="mb-2">Thank you! 🎉</h4>
                                                        <p>Your message has been sent successfully. We’ll get back to you soon.</p>
                                                    </div>

                                                </div>

                                            </div>
                                        </div>
                                        </div>
                                    </section>


                        <script>
                        document.addEventListener('DOMContentLoaded', function () {
                            const successBox = document.getElementById('contact-success');
                            const form = document.getElementById('contact-form');
                            const messageBox = document.getElementById('contact-form-message');
                            const submitBtn = form.querySelector('button[type="submit"]');

                            if (!form || !submitBtn) return;

                            const originalBtnHtml = submitBtn.innerHTML;

                            form.addEventListener('submit', function (e) {
                                e.preventDefault();

                                // Reset message
                                messageBox.className = 'alert d-none';
                                messageBox.innerHTML = '';

                                // Disable button + show loading
                                submitBtn.disabled = true;
                                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Sending...';

                                const formData = new FormData(form);

                                fetch(form.action, {
                                    method: 'POST',
                                    headers: {
                                        'X-Requested-With': 'XMLHttpRequest',
                                        'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value
                                    },
                                    body: formData
                                })
                                .then(async response => {
                                    const data = await response.json();
                                    if (!response.ok) throw data;
                                    return data;
                                })
                                .then(data => {
                                    // Reset form so user can send again if they want
                                    form.reset();

                                    // Show thank-you message
                                    successBox.classList.remove('d-none');

                                    // Hide the message after a few seconds
                                    setTimeout(() => {
                                        successBox.classList.add('d-none');
                                    }, 8000);
                                })


                                .catch(error => {
                                    if (error.errors) {
                                        showMessage(Object.values(error.errors).flat().join('<br>'), 'danger');
                                    } else {
                                        showMessage('Something went wrong. Please try again.', 'danger');
                                    }
                                })
                                .finally(() => {
                                    // Restore button
                                    submitBtn.disabled = false;
                                    submitBtn.innerHTML = originalBtnHtml;
                                });
                            });

                            function showMessage(message, type) {
                                messageBox.className = `alert alert-${type}`;
                                messageBox.innerHTML = message;
                                messageBox.classList.remove('d-none');

                                setTimeout(() => {
                                    messageBox.classList.add('d-none');
                                }, 15000);
                            }
                        });
                        </script>




                        <script>
                        document.addEventListener('DOMContentLoaded', function () {
                            const searchBtn = document.getElementById('location-search-btn');

                            if (!searchBtn) return;

                            const iconHtml = '<i class="fas fa-search me-2"></i>';
                            const fullHtml = '<i class="fas fa-search me-2"></i>Search';

                            function updateSearchButton() {
                                if (window.innerWidth < 576) {
                                    searchBtn.innerHTML = iconHtml;
                                } else {
                                    searchBtn.innerHTML = fullHtml;
                                }
                            }

                            // Run on load
                            updateSearchButton();

                            // Run on resize
                            window.addEventListener('resize', updateSearchButton);
                        });

                        console.log('Script started');

                        // Global variable for concerts
                        let allConcerts = [];
                        let visibleConcertsCount = 8;

                        // Load homepage concerts
                        function loadHomepageConcerts() {
                            console.log('Loading homepage concerts...');

                            // Main concerts elements
                            const mainLoading = document.getElementById('homepage-concerts-loading');
                            const mainGrid = document.getElementById('homepage-concerts-grid');
                            const mainNoConcerts = document.getElementById('homepage-no-concerts');
                            const mainError = document.getElementById('homepage-concerts-error');
                            const loadMoreContainer = document.getElementById('concerts-load-more-container');

                            // Trending artists elements
                            const trendingLoading = document.getElementById('trending-artists-loading');
                            const trendingGrid = document.getElementById('trending-artists-grid');
                            const trendingNoArtists = document.getElementById('trending-no-artists');
                            const trendingError = document.getElementById('trending-artists-error');

                            // Show loading states
                            if (mainLoading) mainLoading.style.display = 'block';
                            if (mainGrid) mainGrid.style.display = 'none';
                            if (mainNoConcerts) mainNoConcerts.style.display = 'none';
                            if (mainError) mainError.style.display = 'none';
                            if (loadMoreContainer) loadMoreContainer.style.display = 'none';

                            if (trendingLoading) trendingLoading.style.display = 'block';
                            if (trendingGrid) trendingGrid.style.display = 'none';
                            if (trendingNoArtists) trendingNoArtists.style.display = 'none';
                            if (trendingError) trendingError.style.display = 'none';

                            // Fetch regular concerts only
                            fetch('/api/concerts/homepage')
                                .then(response => response.json())
                                .then(data => {
                                    console.log('Homepage concerts response:', data);

                                    // Hide loading states
                                    if (mainLoading) mainLoading.style.display = 'none';

                                    const homepageData = data;
                                    
                                    // Handle main concerts
                                    // CRITICAL: Validate that API returned successfully AND has concerts
                                    if (homepageData.success === true && Array.isArray(homepageData.concerts) && homepageData.concerts.length > 0) {
                                        // Filter out trending artists from main concerts
                                        const trendingArtists = [
                                            'Taylor Swift', 'Ed Sheeran', 'Harry Styles', 'Olivia Rodrigo',
                                            'The Weeknd', 'Billie Eilish', 'Dua Lipa', 'Bruno Mars',
                                            'Ariana Grande', 'Justin Bieber'
                                        ];

                                        const regularConcerts = homepageData.concerts.filter(concert => {
                                            const artistName = concert.artist || concert.name;
                                            return !trendingArtists.some(artist =>
                                                artistName.toLowerCase().includes(artist.toLowerCase())
                                            );
                                        });

                                        // Check if we have any concerts AFTER filtering
                                        if (regularConcerts.length > 0) {
                                            // Store all concerts
                                            allConcerts = regularConcerts;
                                            visibleConcertsCount = 8;
                                            
                                            // Clear grid and show it
                                            if (mainGrid) {
                                                mainGrid.innerHTML = '';
                                                mainGrid.style.display = 'flex';
                                            }

                                            // Hide empty state
                                            if (mainNoConcerts) mainNoConcerts.style.display = 'none';
                                            
                                            // Render all concerts at once
                                            renderConcertsBatch();
                                            
                                        } else {
                                            // No concerts after filtering - show empty state
                                            if (mainGrid) mainGrid.style.display = 'none';
                                            if (mainNoConcerts) mainNoConcerts.style.display = 'block';
                                            if (loadMoreContainer) loadMoreContainer.style.display = 'none';
                                        }
                                    } else {
                                        // API returned no concerts or failed - show empty state
                                        if (mainGrid) mainGrid.style.display = 'none';
                                        if (mainNoConcerts) mainNoConcerts.style.display = 'block';
                                        if (loadMoreContainer) loadMoreContainer.style.display = 'none';
                                    }


                                })
                                .catch(err => {
                                    console.error('Error:', err);

                                    // Hide loading states
                                    if (mainLoading) mainLoading.style.display = 'none';
                                    if (trendingLoading) trendingLoading.style.display = 'none';

                                    // Hide grids
                                    if (mainGrid) mainGrid.style.display = 'none';
                                    if (loadMoreContainer) loadMoreContainer.style.display = 'none';

                                    // Show error states (NOT empty states - this is an error)
                                    if (mainError) mainError.style.display = 'block';
                                })
.finally(() => {
    if (typeof window.notifyAsyncLoadComplete === 'function') {
        window.notifyAsyncLoadComplete();
    }
});
                        }

                        function renderConcertsBatch() {
                            const grid = document.getElementById('homepage-concerts-grid');
                            
                            if (!grid) return;

                            // Render concerts
                            if (allConcerts.length === 0) {
                                return;
                            }

                            let html = '';
                            const concertsToShow = allConcerts.slice(0, visibleConcertsCount);
                            
                            concertsToShow.forEach(concert => {
                                html += '<div class="col-12 col-md-4 col-lg-4 animate__animated animate__fadeIn">';
                                html += '<div class="card h-100 concert-card">';
                                html += '<img src="' + (concert.image || 'https://via.placeholder.com/300x200') +
                                    '" class="card-img-top" alt="' + concert.name + '" style="height: 200px; object-fit: cover;">';
                                html += '<div class="card-body d-flex flex-column">';
                                html += '<h5 class="card-title">' + concert.name + '</h5>';
                                html += '<p class="card-text"><strong>' + (concert.artist || concert.name) + '</strong></p>';
                                html += '<p class="card-text">';
                                html += '<i class="fas fa-calendar"></i> ' + (concert.date || 'Date TBD') + '<br>';
                                html += '<i class="fas fa-map-marker-alt"></i> ' + (concert.venue?.name || 'Venue') + ', ' + (concert
                                    .venue?.city || 'City');
                                html += '</p>';
                                html += '<a href="' + (concert.url || '#') +
                                    '" target="_blank" class="btn btn-sm btn-outline-primary mt-auto d-block mx-auto" style="width: fit-content;">Get Tickets</a>';
                                html += '</div>';
                                html += '</div>';
                                html += '</div>';
                            });

                            // Add to grid
                            grid.innerHTML = html;

                            // Check load more button
                            let loadMoreContainer = document.getElementById('concerts-load-more-container');
                            if (!loadMoreContainer) {
                                loadMoreContainer = document.createElement('div');
                                loadMoreContainer.id = 'concerts-load-more-container';
                                loadMoreContainer.className = 'col-12 text-center mt-4';
                                loadMoreContainer.innerHTML = '<button id="load-more-concerts-btn" class="btn btn-outline-primary px-4 py-2" onclick="loadMoreConcerts()">Load More Concerts</button>';
                                grid.insertAdjacentElement('afterend', loadMoreContainer);
                            }
                            
                            if (allConcerts.length > visibleConcertsCount) {
                                loadMoreContainer.style.display = 'block';
                            } else {
                                loadMoreContainer.style.display = 'none';
                            }
                        }

                        function loadMoreConcerts() {
                            visibleConcertsCount += 8;
                            renderConcertsBatch();
                        }

                        function displayConcerts(concerts) {
                            // Legacy function kept for compatibility if needed, but logic is moved to renderConcertsBatch
                            console.warn('displayConcerts is deprecated, use renderConcertsBatch instead');
                        }

                        function displayTrendingArtists(concertsByArtist) {
                            console.log('Displaying trending artists:', Object.keys(concertsByArtist));

                            const grid = document.getElementById('trending-artists-grid');
                            const noArtists = document.getElementById('trending-no-artists');

                            if (!grid) return;

                            // Flatten all concerts from all artists
                            let allConcerts = [];
                            Object.values(concertsByArtist).forEach(artistConcerts => {
                                allConcerts = allConcerts.concat(artistConcerts);
                            });

                            // Sort concerts by date
                            allConcerts.sort((a, b) => {
                                const dateA = new Date(a.date + ' ' + (a.time || '00:00'));
                                const dateB = new Date(b.date + ' ' + (b.time || '00:00'));
                                return dateA - dateB;
                            });

                            // Limit to 12 concerts for compact display
                            const limitedConcerts = allConcerts.slice(0, 12);

                            let html = '';
                            limitedConcerts.forEach(concert => {
                                html += '<div class="col-12 col-md-6 col-lg-4">';
                                html += '<div class="card h-100 border-0 shadow-sm trending-concert-card">';

                                // Add trending badge
                                html += '<div class="position-absolute top-0 end-0 m-2 z-1">';
                                html += '<span class="badge bg-primary">Trending</span>';
                                html += '</div>';

                                html += '<img src="' + (concert.image || 'https://via.placeholder.com/300x200') +
                                    '" class="card-img-top" alt="' + concert.name + '" style="height: 160px; object-fit: cover;">';
                                html += '<div class="card-body p-3 d-flex flex-column">';
                                html += '<h6 class="card-title mb-2 fw-bold">' + concert.name + '</h6>';
                                html += '<p class="card-text small text-primary mb-2"><strong>' + (concert.artist || concert.name) +
                                    '</strong></p>';
                                html += '<p class="card-text small text-muted mb-3">';
                                html += '<i class="fas fa-calendar me-1"></i> ' + (concert.date || 'Date TBD') + '<br>';
                                html += '<i class="fas fa-map-marker-alt me-1"></i> ' + (concert.venue?.name || 'Venue') + ', ' + (
                                    concert.venue?.city || 'City');
                                html += '</p>';
                                html += '<a href="' + concert.url +
                                    '" target="_blank" class="btn btn-sm btn-primary w-100 mt-auto">Get Tickets</a>';
                                html += '</div>';
                                html += '</div>';
                                html += '</div>';
                            });

                            grid.innerHTML = html;
                            grid.style.display = 'flex';

                            if (noArtists) noArtists.style.display = 'none';
                        }

                        document.addEventListener('DOMContentLoaded', function() {
                            console.log('Page loaded, starting concert loader');

                            // Load concerts after a short delay
                            // setTimeout(loadHomepageConcerts, 1000); 
                            // Using loadConcerts() instead of loadHomepageConcerts() to avoid duplicates if both exist
                            // But wait, loadConcerts is for the search page usually.
                            // Let's stick to loadHomepageConcerts for the homepage sections.
                            setTimeout(loadHomepageConcerts, 1000);

                            // Location search functionality
                            const locationSearchInput = document.getElementById('location-search-input');
                            const locationSearchBtn = document.getElementById('location-search-btn');
                            const useMyLocationBtn = document.getElementById('use-my-location-btn');

                            if (locationSearchInput && locationSearchBtn) {
                                // Search on button click
                                locationSearchBtn.addEventListener('click', function() {
                                    performLocationSearch();
                                });

                                // Search on Enter key press
                                locationSearchInput.addEventListener('keypress', function(e) {
                                    if (e.key === 'Enter') {
                                        performLocationSearch();
                                    }
                                });
                            }

                            // Use My Location button functionality
                            if (useMyLocationBtn) {
                                useMyLocationBtn.addEventListener('click', function() {
                                    getUserLocation();
                                });
                            }

                            function getUserLocation() {
                                if (!navigator.geolocation) {
                                    alert('Geolocation is not supported by this browser. Please enter a city name manually.');
                                    return;
                                }

                                // Show loading state on the button
                                const originalHtml = useMyLocationBtn.innerHTML;
                                useMyLocationBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                                useMyLocationBtn.disabled = true;

                                navigator.geolocation.getCurrentPosition(
                                    // Success callback
                                    function(position) {
                                        const latitude = position.coords.latitude;
                                        const longitude = position.coords.longitude;

                                        console.log('Got user location:', latitude, longitude);

                                        // Reverse geocode to get city name
                                        reverseGeocode(latitude, longitude)
                                            .then(cityName => {
                                                if (cityName) {
                                                    locationSearchInput.value = cityName;
                                                    // Automatically trigger search
                                                    performLocationSearch();
                                                } else {
                                                    alert('Unable to determine your city name. Please enter it manually.');
                                                }
                                            })
                                            .catch(error => {
                                                console.error('Reverse geocoding error:', error);
                                                alert('Unable to determine your location. Please enter a city name manually.');
                                            })
                                            .finally(() => {
                                                // Restore button state
                                                useMyLocationBtn.innerHTML = originalHtml;
                                                useMyLocationBtn.disabled = false;
                                            });
                                    },
                                    // Error callback
                                    function(error) {
                                        console.error('Geolocation error:', error);
                                        let errorMessage = 'Unable to get your location. ';

                                        switch (error.code) {
                                            case error.PERMISSION_DENIED:
                                                errorMessage += 'Please allow location access and try again.';
                                                break;
                                            case error.POSITION_UNAVAILABLE:
                                                errorMessage += 'Location information is unavailable.';
                                                break;
                                            case error.TIMEOUT:
                                                errorMessage += 'Location request timed out.';
                                                break;
                                            default:
                                                errorMessage += 'Please enter a city name manually.';
                                                break;
                                        }

                                        alert(errorMessage);

                                        // Restore button state
                                        useMyLocationBtn.innerHTML = originalHtml;
                                        useMyLocationBtn.disabled = false;

                                        // Fallback to IP-based location
                                        getIPLocation();
                                    },
                                    // Options
                                    {
                                        enableHighAccuracy: true,
                                        timeout: 20000,
                                        maximumAge: 600000 // 5 minutes
                                    }
                                );
                            }

                            function reverseGeocode(latitude, longitude) {
                                return new Promise((resolve, reject) => {
                                    const url =
                                        `https://nominatim.openstreetmap.org/reverse?format=json&lat=${latitude}&lon=${longitude}&zoom=10`;

                                    fetch(url, {
                                            headers: {
                                                'User-Agent': 'Jammin Concert App/1.0'
                                            }
                                        })
                                        .then(response => response.json())
                                        .then(data => {
                                            if (data && data.address) {
                                                // Try to get city name from different possible fields
                                                const city = data.address.city ||
                                                    data.address.town ||
                                                    data.address.village ||
                                                    data.address.municipality ||
                                                    data.address.county ||
                                                    data.address.state;

                                                if (city) {
                                                    resolve(city);
                                                } else {
                                                    reject(new Error('No city found in reverse geocoding response'));
                                                }
                                            } else {
                                                reject(new Error('Invalid reverse geocoding response'));
                                            }
                                        })
                                        .catch(error => {
                                            reject(error);
                                        });
                                });
                            }

                            function getIPLocation() {
                                // Try IP-based location as fallback
                                fetch('/debug/location')
                                    .then(response => response.json())
                                    .then(data => {
                                        if (data.ip_location && data.ip_location.city) {
                                            locationSearchInput.value = data.ip_location.city;
                                            // Automatically trigger search
                                            performLocationSearch();
                                        }
                                    })
                                    .catch(error => {
                                        console.error('IP location fallback failed:', error);
                                    });
                            }

                            function performLocationSearch() {
                                const location = locationSearchInput.value.trim();

                                if (!location) {
                                    alert('Please enter a city name');
                                    return;
                                }

                                console.log('Searching concerts for location:', location);

                                // Show loading state
                                const loading = document.getElementById('homepage-concerts-loading');
                                const grid = document.getElementById('homepage-concerts-grid');
                                const error = document.getElementById('homepage-concerts-error');
                                const noConcerts = document.getElementById('homepage-no-concerts');
                                const loadMoreContainer = document.getElementById('concerts-load-more-container');

                                if (loading) loading.style.display = 'block';
                                if (grid) grid.style.display = 'none';
                                if (error) error.style.display = 'none';
                                if (noConcerts) noConcerts.style.display = 'none';
                                if (loadMoreContainer) loadMoreContainer.style.display = 'none';

                                // Update loading text
                                if (loading) {
                                    const loadingText = loading.querySelector('p');
                                    if (loadingText) {
                                        loadingText.textContent = `Searching concerts in ${location}...`;
                                    }
                                }

                                // Fetch concerts for the location
                                fetch(`/api/concerts/location?location=${encodeURIComponent(location)}`)
                                    .then(response => response.json())
                                    .then(data => {
                                        console.log('Location search response:', data);

                                        if (loading) loading.style.display = 'none';

                                        if (data.success && data.concerts && data.concerts.length > 0) {
                                            // Store all concerts and initialize pagination
                                            allConcerts = data.concerts;
                                            visibleConcertsCount = 8;

                                            // Clear grid and show it
                                            if (grid) {
                                                grid.innerHTML = '';
                                                grid.style.display = 'flex';
                                            }

                                            // Render first batch
                                            renderConcertsBatch();

                                            // Update section title to show location
                                            const sectionTitle = document.querySelector('#concerts .section-header h3');
                                            if (sectionTitle) {
                                                sectionTitle.textContent = `Concerts in ${data.location}`;
                                            }
                                        } else {
                                            // Show no concerts message with location context
                                            if (noConcerts) {
                                                const noConcertsTitle = noConcerts.querySelector('h4');
                                                const noConcertsText = noConcerts.querySelector('p');
                                                if (noConcertsTitle) {
                                                    noConcertsTitle.textContent = `No Concerts Found in ${location}`;
                                                }
                                                if (noConcertsText) {
                                                    noConcertsText.textContent =
                                                        `No concerts found in ${location}. Try a different city or check back later!`;
                                                }
                                                noConcerts.style.display = 'block';
                                            }
                                        }
                                    })
                                    .catch(err => {
                                        console.error('Location search error:', err);
                                        if (loading) loading.style.display = 'none';
                                        if (error) {
                                            const errorTitle = error.querySelector('h4');
                                            const errorText = error.querySelector('p');
                                            if (errorTitle) {
                                                errorTitle.textContent = 'Search Error';
                                            }
                                            if (errorText) {
                                                errorText.textContent =
                                                    `Unable to search concerts in ${location}. Please try again later.`;
                                            }
                                            error.style.display = 'block';
                                        }
                                    });
                            }

                            // Load homepage concerts when page is ready
                            document.addEventListener('DOMContentLoaded', function() {
                                // Load homepage concerts after a short delay
                                setTimeout(loadHomepageConcerts, 1000);
                            });
                        });
                        </script>
                    <script>
                    const usCities = [
                        "New York",
                        "Los Angeles",
                        "Chicago",
                        "Houston",
                        "Phoenix",
                        "Philadelphia",
                        "San Antonio",
                        "San Diego",
                        "San Jose",
                        "Dallas",
                        "Austin",
                        "Seattle",
                        "Denver",
                        "Boston",
                        "Miami",
                        "Orlando",
                        "Atlanta",
                        "Nashville",
                        "Las Vegas",
                        "San Francisco"
                    ];
                    </script>
<script>
// City suggestions functionality
const input = document.getElementById("location-search-input");
const suggestionsBox = document.getElementById("city-suggestions");

input.addEventListener("input", function () {
    const value = this.value.toLowerCase().trim();
    suggestionsBox.innerHTML = "";

    if (value.length < 2) {
        suggestionsBox.style.display = "none";
        return;
    }

    const filteredCities = usCities.filter(city =>
        city.toLowerCase().startsWith(value)
    );

    if (filteredCities.length === 0) {
        suggestionsBox.style.display = "none";
        return;
    }

    filteredCities.forEach(city => {
        const item = document.createElement("div");
        item.className = "list-group-item list-group-item-action";
        item.textContent = city;

        item.addEventListener("click", () => {
            input.value = city;
            suggestionsBox.style.display = "none";
            
            // Auto trigger search
            const searchBtn = document.getElementById('location-search-btn');
            if (searchBtn) searchBtn.click();
        });

        suggestionsBox.appendChild(item);
    });

    suggestionsBox.style.display = "block";
});

// Close suggestions when clicking outside
document.addEventListener("click", function(e) {
    if (!e.target.closest(".position-relative")) {
        suggestionsBox.style.display = "none";
    }
});

// Load Community Events
function loadCommunityEvents() {
    console.log('Loading community events...');

    const loading = document.getElementById('community-events-loading');
    const grid = document.getElementById('community-events-grid');
    const noEvents = document.getElementById('community-no-events');
    const error = document.getElementById('community-events-error');

    // Show loading state
    if (loading) loading.style.display = 'block';
    if (grid) grid.style.display = 'none';
    if (noEvents) noEvents.style.display = 'none';
    if (error) error.style.display = 'none';

    // Fetch community events from API
    fetch('/api/community-events')
        .then(response => {
            if (!response.ok) {
                throw new Error('Server error: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            // If the server returned a JSON error object, throw to trigger .catch()
            if (data && data.error) {
                throw new Error(data.error);
            }
            return data;
        })
        .then(events => {
            console.log('Community events response:', events);

            // Hide loading
            if (loading) loading.style.display = 'none';

            if (Array.isArray(events) && events.length > 0) {
                // Display events
                if (grid) {
                    grid.innerHTML = events.map(event => {
                        // Format date
                        const date = new Date(event.event_date);
                        const formattedDate = date.toLocaleDateString('en-US', {
                            weekday: 'long',
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric',
                            hour: 'numeric',
                            minute: '2-digit'
                        });

                        return `
                        <div class="col-12 col-md-4">
                            <div class="card h-100 event-card">
                                ${event.image ? `
                                    <div class="card-img" style="background-image: url('${event.image}'); background-size: cover; background-position: center; height: 200px; border-radius: 10px 10px 0 0;"></div>
                                ` : `
                                    <div class="card-img" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); height: 200px; border-radius: 10px 10px 0 0; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-calendar-alt" style="font-size: 3rem; color: white;"></i>
                                    </div>
                                `}
                                <div class="card-content p-3 d-flex flex-column h-100">
                                    <h3>${event.title}</h3>
                                    <p class="mb-1"><i class="fas fa-calendar me-2 text-primary"></i>${formattedDate}</p>
                                    <p class="mb-2"><i class="fas fa-map-marker-alt me-2 text-danger"></i>${event.location}</p>
                                    <p class="mb-3 text-muted small flex-grow-1">${event.description}</p>
                                    
                                    ${event.source_url ? `
                                        <div class="mt-auto pt-3 border-top">
                                            <a href="${event.source_url}" target="_blank" class="btn btn-outline-primary w-100">
                                                <i class="fas fa-external-link-alt me-2"></i>View Event Details
                                            </a>
                                        </div>
                                    ` : ''}
                                </div>
                            </div>
                        </div>
                    `}).join('');
                    grid.style.display = 'flex';
                }
                // Ensure empty state is hidden
                if (noEvents) noEvents.style.display = 'none';
            } else {
                // Show no events message
                if (noEvents) noEvents.style.display = 'block';
                // Ensure grid is hidden
                if (grid) grid.style.display = 'none';
            }
        })
        .catch(err => {
            console.error('Error loading community events:', err);
            // Hide loading
            if (loading) loading.style.display = 'none';
            // Show error message
            if (error) error.style.display = 'block';
            // Ensure others are hidden
            if (grid) grid.style.display = 'none';
            if (noEvents) noEvents.style.display = 'none';
        })
.finally(() => {
    if (typeof window.notifyAsyncLoadComplete === 'function') {
        window.notifyAsyncLoadComplete();
    }
});
}

// Load events on page load
document.addEventListener('DOMContentLoaded', function() {
    loadCommunityEvents();
});
</script>
@endsection