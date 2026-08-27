    <script>
        // Load concerts on page load
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, initializing concert search...');
            // loadConcerts(); // Disabled to prevent interference with homepage concerts

            // Add event listeners to buttons using IDs
            const searchBtn = document.getElementById('searchConcertsBtn');
            const cityInput = document.getElementById('cityInput');

            console.log('Found elements:', {
                searchBtn,
                cityInput
            });

            if (searchBtn) {
                console.log('✅ Adding click listener to search button');
                searchBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    console.log('🖱️ Search button clicked via event listener');
                    searchConcertsByCity();
                });

                // Also test direct onclick
                searchBtn.onclick = function(e) {
                    e.preventDefault();
                    console.log('🖱️ Search button clicked via onclick');
                    searchConcertsByCity();
                };
            } else {
                console.error('❌ Search button not found');
            }



            // Allow Enter key in city input
            if (cityInput) {
                cityInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        console.log('Enter key pressed in city input');
                        searchConcertsByCity();
                    }
                });
            } else {
                console.error('City input not found');
            }

            // Make functions globally accessible for debugging
            window.searchConcertsByCity = searchConcertsByCity;

            console.log('🌍 Functions made globally accessible');
        });

        // Search concerts by city
        function searchConcertsByCity() {
            console.log('🔍 searchConcertsByCity() called');
            const city = document.getElementById('cityInput').value.trim();
            console.log('City input value:', city);

            // Clear any previous location data
            sessionStorage.removeItem('userLocation');

            if (city) {
                console.log('Loading concerts for city:', city);
                loadConcerts(city);
            } else {
                console.log('Loading default concerts');
                loadConcerts();
            }
        }



        // Load concerts from API
        function loadConcerts(city = null, latitude = null, longitude = null) {
            console.log('🎵 loadConcerts called with:', {
                city,
                latitude,
                longitude
            });

            try {
                showConcertsLoading();

                let url = '/api/concerts';
                let params = new URLSearchParams();

                if (city) {
                    params.append('city', city);
                    console.log('Loading concerts for city:', city);
                }
                if (latitude && longitude) {
                    params.append('latitude', latitude);
                    params.append('longitude', longitude);
                    console.log('Loading concerts near location:', {
                        latitude,
                        longitude
                    });
                }

                if (params.toString()) {
                    url += '?' + params.toString();
                }

                console.log('🌐 Fetching concerts from:', url);

                fetch(url)
                    .then(response => {
                        console.log('📡 Response received:', response.status, response.statusText);
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log('✅ Concerts loaded successfully:', data);

                        if (data.error) {
                            throw new Error(data.message || 'API returned an error');
                        }

                        displayConcerts(data, city, latitude, longitude);
                    })
                    .catch(error => {
                        console.error('❌ Error loading concerts:', error);

                        // Clear container first
                        const container = document.getElementById('concerts-container');
                        if (container) {
                            container.innerHTML = '';

                            // Show user-friendly error message
                            const errorDiv = document.createElement('div');
                            errorDiv.className = 'col-12';
                            errorDiv.innerHTML = `
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <strong>Unable to load live concert data.</strong>
                                    Error: ${error.message}. Showing sample concerts instead.
                                </div>
                            `;
                            container.appendChild(errorDiv);
                        }

                        displayFallbackConcerts();
                    })
                    .finally(() => {
                        hideConcertsLoading();
                    });
            } catch (error) {
                console.error('❌ Critical error in loadConcerts:', error);
                hideConcertsLoading();
                displayFallbackConcerts();
            }
        }

        // Display concerts in the UI
        function displayConcerts(concerts, city = null, latitude = null, longitude = null) {
            const container = document.getElementById('concerts-container');
            container.innerHTML = '';

            // Add location info header
            if (city || (latitude && longitude)) {
                const locationHeader = document.createElement('div');
                locationHeader.className = 'col-12 mb-3';

                let locationText = '';
                if (city) {
                    locationText = `Concerts in ${city}`;
                } else if (latitude && longitude) {
                    locationText = `Concerts near your location (sorted by distance)`;
                }

                locationHeader.innerHTML = `
                    <div class="alert alert-info">
                        <i class="fas fa-map-marker-alt"></i>
                        <strong>${locationText}</strong>
                        ${concerts && concerts.length > 0 ? ` - Found ${concerts.length} events` : ''}
                    </div>
                `;
                container.appendChild(locationHeader);
            }

            if (!concerts || concerts.length === 0) {
                displayFallbackConcerts();
                return;
            }

            concerts.forEach((concert, index) => {
                const concertCard = createConcertCard(concert, index);
                container.appendChild(concertCard);
            });
        }

        // Create individual concert card
        function createConcertCard(concert, index) {
            const col = document.createElement('div');
            col.className = 'col-md-4 mb-4';

            const icons = ['fas fa-guitar', 'fas fa-drum', 'fas fa-microphone', 'fas fa-music', 'fas fa-headphones',
                'fas fa-compact-disc'
            ];
            const icon = icons[index % icons.length];

            const formatDate = (dateStr, timeStr) => {
                if (!dateStr) return 'Date TBA';

                const date = new Date(dateStr + (timeStr ? 'T' + timeStr : ''));
                const options = {
                    weekday: 'short',
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                    hour: timeStr ? 'numeric' : undefined,
                    minute: timeStr ? '2-digit' : undefined
                };

                return date.toLocaleDateString('en-US', options);
            };

            const venueText = concert.venue ?
                `${concert.venue.name}${concert.venue.city ? ', ' + concert.venue.city : ''}` :
                'Venue TBA';

            col.innerHTML = `
                <div class="card h-100 shadow-sm">
                    ${concert.image ?
                        `<div class="position-relative" style="height: 192px; overflow: hidden;">
                                                <img src="${concert.image}" alt="${concert.name}"
                                                     class="w-100 h-100" style="object-fit: cover; border-radius: 0.375rem 0.375rem 0 0;"
                                                     onerror="this.parentElement.innerHTML='<div class=\\'card-img\\' style=\\'height: 192px; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);\\' ><i class=\\'${icon} text-white\\'></i></div>'">
                                             </div>` :
                        `<div class="card-img" style="height: 192px;"><i class="${icon}"></i></div>`
                    }
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title">${concert.name}</h5>
                        <div class="mb-2">
                            <small class="text-muted">
                                <i class="fas fa-calendar-alt"></i> ${formatDate(concert.date, concert.time)}
                            </small>
                        </div>
                        <div class="mb-2">
                            <small class="text-muted">
                                <i class="fas fa-map-marker-alt"></i> ${venueText}
                            </small>
                        </div>
                        ${concert.genre ?
                            `<div class="mb-2">
                                                    <span class="badge bg-secondary">${concert.genre}</span>
                                                </div>` : ''
                        }
                        ${concert.price_range ?
                            `<div class="mb-2">
                                                    <small class="text-success">
                                                        <i class="fas fa-ticket-alt"></i> ${concert.price_range}
                                                    </small>
                                                </div>` : ''
                        }
                        <div class="mt-auto">
                            <a href="${concert.ticket_url || '#'}" target="_blank"
                               class="btn btn-primary btn-sm w-100">
                                <i class="fas fa-ticket-alt"></i> Get Tickets
                            </a>
                            <small class="text-muted d-block mt-1 text-center">
                                via ${concert.source || 'Ticketmaster'}
                            </small>
                        </div>
                    </div>
                </div>
            `;

            return col;
        }

        // Show loading state
        function showConcertsLoading() {
            const loadingElement = document.getElementById('concerts-loading');
            if (loadingElement) {
                loadingElement.style.display = 'block';
                console.log('✅ Loading state shown');
            } else {
                console.error('❌ Loading element not found');
            }
        }

        // Hide loading state
        function hideConcertsLoading() {
            const loadingElement = document.getElementById('concerts-loading');
            if (loadingElement) {
                loadingElement.style.display = 'none';
                console.log('✅ Loading state hidden');
            } else {
                console.error('❌ Loading element not found');
            }
        }

        // Display ticket booking website links when no concerts found
        function displayFallbackConcerts() {
            const container = document.getElementById('concerts-container');
            container.innerHTML = '';

            const fallbackDiv = document.createElement('div');
            fallbackDiv.className = 'col-12 text-center';
            fallbackDiv.innerHTML = `
                <div class="card p-5">
                    <div class="mb-4">
                        <i class="fas fa-search fa-3x text-muted mb-3"></i>
                        <h4>No concerts found in your area</h4>
                        <p class="text-muted">You might find your search results in these popular ticket booking websites:</p>
                    </div>

                    <div class="row g-3 justify-content-center">
                        <div class="col-md-4">
                            <a href="https://www.ticketmaster.com" target="_blank" class="btn btn-outline-primary btn-lg w-100 d-flex align-items-center justify-content-center">
                                <i class="fas fa-ticket-alt me-2"></i>
                                Ticketmaster
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="https://www.stubhub.com" target="_blank" class="btn btn-outline-primary btn-lg w-100 d-flex align-items-center justify-content-center">
                                <i class="fas fa-music me-2"></i>
                                StubHub
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="https://www.vivid-seats.com" target="_blank" class="btn btn-outline-primary btn-lg w-100 d-flex align-items-center justify-content-center">
                                <i class="fas fa-calendar-alt me-2"></i>
                                Vivid Seats
                            </a>
                        </div>
                    </div>

                    <div class="mt-4">
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            These are popular USA-based concert ticket booking platforms
                        </small>
                    </div>
                </div>
            `;

            container.appendChild(fallbackDiv);
        }

        // Smart App Detection and Deep Linking
        document.addEventListener('DOMContentLoaded', function() {
            // App configuration - Update these with your actual app details
            const appConfig = {
                ios: {
                    scheme: 'jamminradio://', // Your iOS app scheme
                    storeUrl: 'https://apps.apple.com/app/idYOUR_IOS_APP_ID', // Your iOS App Store URL
                    packageName: null
                },
                android: {
                    scheme: 'com.jamminradio.app', // Your Android app package name
                    storeUrl: 'https://play.google.com/store/apps/details?id=com.jamminradio.app', // Your Play Store URL
                    packageName: 'com.jamminradio.app'
                }
            };

            setupIOSAudio();


            // Get device type
            function getDeviceType() {
                const userAgent = navigator.userAgent.toLowerCase();

                if (/iphone|ipad|ipod/.test(userAgent)) {
                    return 'ios';
                } else if (/android/.test(userAgent)) {
                    return 'android';
                }
                return 'unknown';
            }

            // Try to open app via deep link
            function openApp(deepLink, fallbackUrl) {
                console.log('Attempting to open app with deep link:', deepLink);

                // Try to open the app
                window.location.href = deepLink;

                // Set a timeout to redirect to app store if app doesn't open
                setTimeout(function() {
                    console.log('App not detected, redirecting to app store');
                    window.location.href = fallbackUrl;
                }, 2000); // 2 second delay
            }

            // Open App button click handler
            // NOTE: #openAppBtn/#downloadAppBtn belonged to a modal that lived in the old
            // monolithic layout (layouts/app.blade.php.old) and was never carried over when
            // the layout was split into partials — the mobile "open/download the app"
            // suggestion is now handled by <x-app-banner /> instead. Guarded with a null
            // check so this dead-markup reference can't throw on every page load.
            const openAppBtn = document.getElementById('openAppBtn');
            if (openAppBtn) {
                openAppBtn.addEventListener('click', function() {
                    const deviceType = getDeviceType();
                    const config = appConfig[deviceType];

                    if (deviceType === 'unknown' || !config) {
                        alert('Please use a mobile device to open the app');
                        return;
                    }

                    if (deviceType === 'ios') {
                        openApp(config.scheme, config.storeUrl);
                    } else if (deviceType === 'android') {
                        const deepLink =
                            `intent://${config.scheme}/#Intent;scheme=${config.scheme};package=${config.packageName};end;`;
                        openApp(deepLink, config.storeUrl);
                    }
                });
            }

            // Download App button click handler
            const downloadAppBtn = document.getElementById('downloadAppBtn');
            if (downloadAppBtn) {
                downloadAppBtn.addEventListener('click', function() {
                    const deviceType = getDeviceType();
                    const config = appConfig[deviceType];

                    if (deviceType === 'unknown' || !config) {
                        // If device type is unknown, show both options
                        const choice = confirm(
                            'Are you using iOS or Android?\n\nClick OK for iOS, Cancel for Android');
                        if (choice) {
                            window.open(appConfig.ios.storeUrl, '_blank');
                        } else {
                            window.open(appConfig.android.storeUrl, '_blank');
                        }
                        return;
                    }

                    // Open the appropriate app store
                    window.open(config.storeUrl, '_blank');
                });
            }

            // Log device detection for debugging
            console.log('Device type detected:', getDeviceType());
        });
    </script>

    @if (!request()->routeIs('events.*'))
        <!-- Sticky Player -->
        <div class="sticky-player" id="sticky-player" hx-preserve="true">
            <div class="player-controls">
                <div class="player-info" style="cursor: pointer;">
                    <div class="mini-player-icon" id="mini-play-btn">
                        <i class="fas fa-play" id="mini-play-icon"></i>
                    </div>
                    <div>
                        <div class="player-title" style=" font-size: 1.25rem;">
                            <span class="text-warning">{{ config('app.name') }}</span> <span class="text-warning"
                                style="font-size: inherit;">Live Stream</span>
                        </div>
                        <div class="player-song" id="mini-song" style="display: none;">
                            <div id="current-title"></div>
                            <div id="current-artist">Loading...</div>
                        </div>
                        <div class="play-to-listen" id="play-to-listen-text" style="display: block;">
                            Press Play To Listen
                        </div>
                    </div>
                </div>
                <!-- <div class="player-buttons">
                <button class="player-btn minimize-btn" id="minimize-btn" title="Minimize">
                    <i class="fas fa-chevron-down"></i>
                </button>
            </div> -->
                <!-- Hidden audio element -->
                <audio id="live-audio" preload="metadata" style="display: none;" crossorigin="anonymous" playsinline
                    webkit-playsinline x-webkit-airplay="allow" data-title="Live Radio"
                    data-artist="{{ config('app.name') }}" data-stream-url="{{ config('app.stream_url') }}">
                    <source src="{{ config('app.stream_url') }}" type="audio/mpeg">
                    Your browser does not support the audio element.
                </audio>

            </div>
        </div>
    @endif

    <!-- App Download Modal -->
    <div class="modal fade" id="appDownloadModal" tabindex="-1" aria-labelledby="appDownloadModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <h5 class="modal-title" id="appDownloadModalLabel">
                        <i class="fas fa-mobile-alt me-2"></i>Get jammin radio App
                    </h5>
                    <button type="button" class="custom-close-btn" data-bs-dismiss="modal" aria-label="Close">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body text-center">
                    <div class="app-icon mb-3">
                        <img src="{{ asset('Landscape_Logo.png') }}" alt="jammin radio Logo" class="img-fluid"
                            style="max-width: 160px; height: auto; background: transparent;">
                    </div>
                    <p class="text-muted mb-4">Listen to your favorite music anytime, anywhere</p>

                    <p class="mb-3 fw-bold">Choose your platform:</p>

                    <div class="d-grid gap-3">
                        <button class="btn btn-success btn-lg" id="openAppBtn">
                            <i class="fas fa-external-link-alt me-2"></i>Open App
                        </button>
                        <button class="btn btn-primary btn-lg" id="iosAppBtn">
                            <i class="fab fa-apple me-2"></i>Download for iOS
                        </button>
                        <button class="btn btn-outline-primary btn-lg" id="androidAppBtn">
                            <i class="fab fa-google-play me-2"></i>Download for Android
                        </button>
                    </div>

                    <div class="mt-4">
                        <small class="text-muted">
                            Available on App Store and Google Play Store
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // App Download Modal Functionality
        document.addEventListener('DOMContentLoaded', function() {
            const openAppBtn = document.getElementById('openAppBtn');
            const iosAppBtn = document.getElementById('iosAppBtn');
            const androidAppBtn = document.getElementById('androidAppBtn');

            // App configuration - UPDATE THESE WITH YOUR ACTUAL APP DETAILS
            const appConfig = {
                ios: {
                    scheme: 'jamminradio://', // Your iOS app scheme
                    storeUrl: 'https://apps.apple.com/app/idYOUR_IOS_APP_ID', // Your iOS App Store URL
                    packageName: null
                },
                android: {
                    scheme: 'com.jamminradio.app', // Your Android app package name
                    storeUrl: 'https://play.google.com/store/apps/details?id=com.jamminradio.app', // Your Play Store URL
                    packageName: 'com.jamminradio.app'
                }
            };


            function getDeviceType() {
                const userAgent = navigator.userAgent.toLowerCase();

                if (/iphone|ipad|ipod/.test(userAgent)) {
                    return 'ios';
                } else if (/android/.test(userAgent)) {
                    return 'android';
                } else {
                    return 'desktop';
                }
            }

            // Function to open app or redirect to store
            function openAppOrStore(platform) {
                const config = appConfig[platform];

                if (platform === 'ios') {
                    // iOS deep linking
                    const startTime = Date.now();

                    // Try to open the app
                    window.location.href = config.scheme;

                    // If app is not installed, redirect to App Store after 2 seconds
                    setTimeout(function() {
                        if (Date.now() - startTime < 2000) {
                            window.location.href = config.storeUrl;
                        }
                    }, 2000);

                } else if (platform === 'android') {
                    // Android deep linking
                    const intentUrl =
                        `intent://${config.scheme}#Intent;scheme=${config.scheme};package=${config.packageName};end;`;

                    // Try to open the app
                    window.location.href = intentUrl;

                    // If app is not installed, redirect to Play Store after 2 seconds
                    setTimeout(function() {
                        window.location.href = config.storeUrl;
                    }, 2000);
                }
            }

            // Open App button click handler - automatically detects device
            if (openAppBtn) {
                openAppBtn.addEventListener('click', function() {
                    const deviceType = getDeviceType();

                    if (deviceType === 'desktop') {
                        // On desktop, show a message or redirect to a download page
                        alert(
                            'Please visit this page on your mobile device to open the app, or choose your platform below to download.'
                        );
                    } else {
                        // On mobile, try to open the app for the detected platform
                        openAppOrStore(deviceType);
                    }
                });
            }

            // iOS button click handler
            if (iosAppBtn) {
                iosAppBtn.addEventListener('click', function() {
                    openAppOrStore('ios');
                });
            }

            // Android button click handler
            if (androidAppBtn) {
                androidAppBtn.addEventListener('click', function() {
                    openAppOrStore('android');
                });
            }

            // Optional: Auto-highlight the user's platform and update Open App button
            const deviceType = getDeviceType();
            if (deviceType === 'ios') {
                if (openAppBtn) {
                    openAppBtn.innerHTML = '<i class="fas fa-external-link-alt me-2"></i>Open on iOS';
                }
                if (iosAppBtn) {
                    iosAppBtn.classList.add('btn-lg');
                    iosAppBtn.innerHTML = '<i class="fab fa-apple me-2"></i>Download for iOS';
                }
            } else if (deviceType === 'android') {
                if (openAppBtn) {
                    openAppBtn.innerHTML = '<i class="fas fa-external-link-alt me-2"></i>Open on Android';
                }
                if (androidAppBtn) {
                    androidAppBtn.classList.add('btn-lg');
                    androidAppBtn.innerHTML = '<i class="fab fa-google-play me-2"></i>Download for Android';
                }
            } else {
                // On desktop, hide or disable the Open App button
                if (openAppBtn) {
                    openAppBtn.innerHTML = '<i class="fas fa-mobile-alt me-2"></i>Open on Mobile';
                    openAppBtn.title = 'Visit on mobile to open app';
                }
            }
        });

        // Contest Modal Functionality
        console.log('Contest Modal JavaScript loading...');

        // Wait for DOM to be fully loaded
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, checking contest modal elements...');

            // Check if contest modal HTML is present in the DOM
            const contestModalHtml = document.querySelector('#contestModal');
            if (!contestModalHtml) {
                console.error('CONTEST MODAL HTML NOT FOUND! The modal component may not be loaded.');
                // Let's check what's actually in the body
                console.log('Body HTML contains contest modal:', document.body.innerHTML.includes('contestModal'));
                console.log('Body HTML contains contest-nav-link:', document.body.innerHTML.includes(
                    'contest-nav-link'));
                return;
            }

            const contestModal = document.getElementById('contestModal');
            const contestNavLink = document.getElementById('contest-nav-link');

            console.log('Contest modal elements found:', {
                modal: !!contestModal,
                link: !!contestNavLink,
                modalId: contestModal ? contestModal.id : 'not found',
                linkId: contestNavLink ? contestNavLink.id : 'not found'
            });

            if (contestModal && contestNavLink) {
                console.log('Setting up contest modal event handlers...');

                // Direct click handler for contest navigation link
                contestNavLink.addEventListener('click', function(e) {
                    e.preventDefault();
                    console.log('CONTEST LINK CLICKED! Attempting to open modal...');

                    // Try to show modal using Bootstrap API
                    try {
                        if (typeof bootstrap !== 'undefined') {
                            console.log('Bootstrap is available, using Bootstrap Modal API');
                            const modal = new bootstrap.Modal(contestModal);
                            modal.show();
                            console.log('Contest modal opened via Bootstrap');
                        } else {
                            console.error('Bootstrap is not defined!');
                            // Fallback: manually show modal
                            contestModal.classList.add('show');
                            contestModal.style.display = 'block';
                            document.body.classList.add('modal-open');

                            // Create backdrop if it doesn't exist
                            let backdrop = document.querySelector('.modal-backdrop');
                            if (!backdrop) {
                                backdrop = document.createElement('div');
                                backdrop.className = 'modal-backdrop fade show';
                                document.body.appendChild(backdrop);
                                console.log('Created modal backdrop manually');
                            }
                            console.log('Contest modal opened manually (fallback)');
                        }
                    } catch (error) {
                        console.error('Error opening contest modal:', error);
                        // Fallback: manually show modal
                        contestModal.classList.add('show');
                        contestModal.style.display = 'block';
                        document.body.classList.add('modal-open');

                        // Create backdrop if it doesn't exist
                        let backdrop = document.querySelector('.modal-backdrop');
                        if (!backdrop) {
                            backdrop = document.createElement('div');
                            backdrop.className = 'modal-backdrop fade show';
                            document.body.appendChild(backdrop);
                        }
                        console.log('Contest modal opened via error fallback');
                    }
                });

                console.log('Contest modal event handlers set up successfully');

                // Handle modal show event
                contestModal.addEventListener('show.bs.modal', function() {
                    console.log('Contest modal show event triggered');
                    // Ensure audio continues playing when modal opens
                    const liveAudio = document.getElementById('live-audio');
                    if (liveAudio && !liveAudio.paused) {
                        console.log('Contest modal opening - audio will continue playing');
                    }

                    // Add modal-open class to body for blur effect
                    document.body.classList.add('modal-open');
                });

                // Handle modal hidden event
                contestModal.addEventListener('hidden.bs.modal', function() {
                    console.log('Contest modal hidden event triggered');
                    // Remove modal-open class from body
                    document.body.classList.remove('modal-open');
                    document.body.style.overflow = '';
                    document.body.style.overflowX = 'hidden';
                    document.body.style.paddingRight = '';


                    // Remove backdrop
                    const backdrop = document.querySelector('.modal-backdrop');
                    if (backdrop) {
                        backdrop.remove();
                    }

                    // Restore audio state if needed
                    const liveAudio = document.getElementById('live-audio');
                    if (liveAudio) {
                        console.log('Contest modal closed - audio state maintained');
                    }
                });

                // Handle close button clicks
                const closeButtons = contestModal.querySelectorAll('[data-bs-dismiss="modal"], .btn-close');
                console.log('Found close buttons:', closeButtons.length);
                closeButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        console.log('Close button clicked');
                        try {
                            const modal = bootstrap.Modal.getInstance(contestModal);
                            if (modal) {
                                modal.hide();
                            } else {
                                // Fallback: manually hide modal
                                contestModal.classList.remove('show');
                                contestModal.style.display = 'none';
                                document.body.classList.remove('modal-open');
                                document.body.style.overflow = '';
                                document.body.style.overflowX = 'hidden';
                                document.body.style.paddingRight = '';

                                const backdrop = document.querySelector('.modal-backdrop');
                                if (backdrop) {
                                    backdrop.remove();
                                }
                            }
                        } catch (error) {
                            console.error('Error closing contest modal:', error);
                            // Fallback: manually hide modal
                            contestModal.classList.remove('show');
                            contestModal.style.display = 'none';
                            document.body.classList.remove('modal-open');
                            document.body.style.overflow = '';
                            document.body.style.overflowX = 'hidden';
                            document.body.style.paddingRight = '';

                            const backdrop = document.querySelector('.modal-backdrop');
                            if (backdrop) {
                                backdrop.remove();
                            }
                        }
                    });
                });
            } else {
                console.error('Contest modal or navigation link not found:', {
                    modal: !!contestModal,
                    link: !!contestNavLink
                });
            }
        });

        // Handle direct contest page access - redirect to modal if on homepage
        function checkContestAccess() {
            // Check if we're on the homepage and contest parameter is present
            if (window.location.pathname === '/' || window.location.pathname === '/home') {
                const urlParams = new URLSearchParams(window.location.search);
                if (urlParams.get('contest') === 'true') {
                    // Open contest modal
                    const contestModal = new bootstrap.Modal(document.getElementById('contestModal'));
                    if (contestModal) {
                        contestModal.show();
                        // Remove contest parameter from URL without reloading
                        const newUrl = window.location.pathname + (window.location.search ? window.location.search.replace(
                            /\?contest=true&?|&contest=true/, '') : '');
                        window.history.replaceState({}, document.title, newUrl);
                    }
                }
            }
        }

        // Check contest access on page load
        checkContestAccess();

        // Request Button Functionality (Direct Link)
        document.addEventListener('DOMContentLoaded', function() {
            const openModalBtn = document.getElementById('openRequestModalGlobal');

            if (openModalBtn) {
                openModalBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const iframeUrl =
                        "{{ env('IFRAME_URL', 'https://jammin92.com/website/request/request.php') }}";
                    window.open(iframeUrl, '_blank');
                });
            }
        });
    </script>
