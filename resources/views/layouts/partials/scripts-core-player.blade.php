    <script src="{{ asset('js/smart-radio-player.js') }}"></script>
    <script>
        const RadioAudio = (() => {
            let _el = null;
            return {
                get() {
                    if (_el && document.contains(_el)) return _el;
                    _el = document.getElementById('live-audio');
                    return _el;
                },
                paused() {
                    const a = this.get();
                    return !a || a.paused;
                },
                play() {
                    return this.get()?.play();
                },
                pause() {
                    this.get()?.pause();
                },
                setSrc(src) {
                    const a = this.get();
                    if (a) a.src = src;
                },
                load() {
                    this.get()?.load();
                },
                on(evt, fn) {
                    this.get()?.addEventListener(evt, fn);
                },
                getVolume() {
                    return this.get()?.volume ?? 1;
                },
            };
        })();
    </script>
    <script>
        // Cross-tab audio persistence functionality
        class AudioPersistenceManager {
            constructor() {
                this.storageKey = skey('radio_audio_state');
                this.tabId = this.generateTabId();
                this.isActiveTab = true;
                this.init();
            }

            generateTabId() {
                return 'tab_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
            }

            init() {
                // Save current tab info
                localStorage.setItem(this.storageKey + '_current_tab', this.tabId);

                // Listen for storage events (cross-tab communication — volume/metadata sync only)
                window.addEventListener('storage', (e) => {
                    if (e.key === this.storageKey) {
                        this.handleAudioStateChange(JSON.parse(e.newValue));
                    } else if (e.key === this.storageKey + '_current_tab') {
                        this.handleTabChange(e.newValue);
                    }
                });

                // ── Visibility & focus ──────────────────────────────────────────────────────
                // SmartRadioPlayer owns the BT/CarPlay reconnect logic via its own
                // visibilitychange listener.  AudioPersistenceManager only updates the
                // isActiveTab flag and syncs metadata — it does NOT trigger reconnects to
                // prevent a two-player race that causes stuttering.
                document.addEventListener('visibilitychange', () => {
                    if (document.hidden) {
                        this.isActiveTab = false;
                    } else {
                        this.isActiveTab = true;
                        localStorage.setItem(this.storageKey + '_current_tab', this.tabId);
                        // Volume / metadata sync only — reconnect is delegated to SmartRadioPlayer.
                        this._syncVolumeOnly();
                    }
                });

                // Handle window focus (tab brought back to front)
                window.addEventListener('focus', () => {
                    this.isActiveTab = true;
                    localStorage.setItem(this.storageKey + '_current_tab', this.tabId);
                    this._syncVolumeOnly();
                });

                // Handle window blur
                window.addEventListener('blur', () => {
                    this.isActiveTab = false;
                });

                // Cleanup on page unload — write sentinel so we can detect tab-close vs BT disconnect
                window.addEventListener('beforeunload', () => {
                    localStorage.setItem(skey('tab_closed_at'), Date.now().toString());
                    this.saveAudioState();
                });
            }

            handleAudioStateChange(state) {
                if (!this.isActiveTab) return;

                const audio = RadioAudio.get();
                if (!audio) return;

                // ── Volume sync only ──────────────────────────────────────────────────────
                // BT reconnect/auto-resume is now fully owned by SmartRadioPlayer.
                // AudioPersistenceManager only syncs volume so it does not race
                // with SmartRadioPlayer's reconnect and cause double stream loads.
                if (state.volume !== undefined) {
                    audio.volume = state.volume;
                }

                // Update UI icons to match actual audio state
                this.updatePlayPauseIcons(!audio.paused);
            }

            /** Sync volume from localStorage without triggering a reconnect. */
            _syncVolumeOnly() {
                try {
                    const saved = JSON.parse(localStorage.getItem(this.storageKey) || '{}');
                    const audio = RadioAudio.get();
                    if (audio && saved.volume !== undefined) {
                        audio.volume = saved.volume;
                    }
                } catch (e) {}
            }

            handleTabChange(activeTabId) {
                this.isActiveTab = (activeTabId === this.tabId);
            }

            saveAudioState() {
                const audio = RadioAudio.get();
                if (!audio) return;

                const state = {
                    isPlaying: !audio.paused,
                    volume: audio.volume,
                    currentTime: audio.currentTime,
                    timestamp: Date.now()
                };

                localStorage.setItem(this.storageKey, JSON.stringify(state));
            }

            syncAudioState() {
                const savedState = localStorage.getItem(this.storageKey);
                if (savedState) {
                    this.handleAudioStateChange(JSON.parse(savedState));
                }
            }

            updatePlayPauseIcons(isPlaying) {
                const navbarPlayIcon = document.getElementById('navbar-play-icon');
                const miniPlayIcon = document.getElementById('mini-play-icon');
                const playIcon = document.getElementById('play-icon');

                if (navbarPlayIcon) {
                    navbarPlayIcon.className = isPlaying ? "fas fa-pause me-1" : "fas fa-play me-1";
                }
                if (miniPlayIcon) {
                    miniPlayIcon.className = isPlaying ? "fas fa-pause" : "fas fa-play";
                }
                if (playIcon) {
                    playIcon.className = isPlaying ? "fas fa-pause" : "fas fa-play";
                }

                togglePlayerDisplay(isPlaying); // ADD THIS LINE
            }
        }

        // Initialize audio persistence manager
        const audioPersistenceManager = new AudioPersistenceManager();

        // ── SmartRadioPlayer — Bluetooth / CarPlay reconnect, stall detection, phone-call pause ──
        // We bind it to the EXISTING live-audio element so there is only one audio instance.
        // userPaused / systemPaused remain the single source of truth for user intent.
        let _smartPlayer = null;
        document.addEventListener('DOMContentLoaded', function () {
            const _streamUrlEl = document.getElementById('live-audio');
            const _streamSrc   = _streamUrlEl?.dataset?.streamUrl
                              || _streamUrlEl?.querySelector('source')?.getAttribute('src')
                              || _streamUrlEl?.src
                              || '';

            if (_streamSrc && typeof SmartRadioPlayer !== 'undefined') {
                if (window._smartPlayer) {
                    console.info('[SmartRadioPlayer] Already initialized, skipping.');
                    return;
                }
                _smartPlayer = new SmartRadioPlayer({
                    streamUrl:      _streamSrc,
                    audioElementId: 'live-audio',   // reuse existing element
                    reconnectDelay: 800,
                    stallTimeout:   3000,
                    onStateChange: function (state) {
                        // ── Sync UI when SmartRadioPlayer updates state ──────────────
                        if (state === 'playing' || state === 'interruptionEnd') {
                            // 'interruptionEnd' = auto-resumed after phone call / BT reconnect
                            // 'playing'         = fresh user-initiated play
                            if (state === 'playing' && !systemPaused) {
                                // Only clear userPaused on a fresh user play, not on system auto-resume
                                userPaused = false;
                            }
                            systemPaused = false;
                            audioPersistenceManager.updatePlayPauseIcons(true);
                            togglePlayerDisplay(true);
                            const sp = document.getElementById('sticky-player');
                            if (sp) sp.style.display = 'flex';
                            try { savePlayingIntent(true); } catch(e) {}
                            audioPersistenceManager.saveAudioState();
                        } else if (state === 'paused') {
                            audioPersistenceManager.updatePlayPauseIcons(false);
                            togglePlayerDisplay(false);
                        } else if (state === 'interrupted') {
                            // System-initiated pause (BT disconnect / phone call)
                            // SmartRadioPlayer will auto-reconnect; keep userPaused = false
                            systemPaused = true;
                            audioPersistenceManager.updatePlayPauseIcons(false);
                            togglePlayerDisplay(false);
                        } else if (state === 'blocked') {
                            audioPersistenceManager.updatePlayPauseIcons(false);
                            togglePlayerDisplay(false);
                        }
                    },
                });

                // MediaSession action handlers are registered below in initAudio()
                // and in scripts-metadata.blade.php — they delegate to _smartPlayer.
                _smartPlayer.init();

                // Expose globally for scripts-metadata.blade.php
                window._smartPlayer = _smartPlayer;

                console.info('[SmartRadioPlayer] Wired to live-audio element.');
            }
        });
        // Function to toggle between song info and "Press Play To Listen"
        // Add this new function
        function togglePlayerDisplay(isPlaying) {
            const songInfo = document.querySelector('.player-song');
            const playText = document.getElementById('play-to-listen-text');

            if (isPlaying) {
                // Show song info, hide "Press Play To Listen"
                if (songInfo) songInfo.style.display = 'block';
                if (playText) playText.style.display = 'none';
            } else {
                // Show "Press Play To Listen", hide song info
                if (songInfo) songInfo.style.display = 'none';
                if (playText) playText.style.display = 'block';
            }
        }

        // Returns the live stream URL with a cache-busting timestamp appended.
        // This ensures every play event connects fresh to the live stream (not cached audio).
        function getStreamUrl() {
            const audio = RadioAudio.get();
            const base = audio?.dataset?.streamUrl ||
                         audio?.querySelector?.('source')?.getAttribute('src') ||
                         audio?.src || '';
            if (!base) return '';
            const sep = base.includes('?') ? '&' : '?';
            return base.split('?')[0] + sep + '_=' + Date.now();
        }

        // ── Re-entrancy guard — prevents double/triple toggle from nested click listeners.
        // Structure: mini-play-icon (click) → bubbles to mini-play-btn (click + stopPropagation).
        // Without this guard, one physical click fires toggleLiveAudio() twice, which
        // toggles play→pause→play (net: no change) or pause→play→pause (net: no change).
        let _toggleBusy = false;
        function toggleLiveAudio() {
            if (_toggleBusy) { console.log('[toggleLiveAudio] debounced (re-entrant call ignored)'); return; }
            _toggleBusy = true;
            setTimeout(() => { _toggleBusy = false; }, 350);
            const stickyPlayer = document.getElementById('sticky-player');

            if (!RadioAudio.get()) {
                console.error('Audio element not found');
                return;
            }

            if (RadioAudio.paused()) {
                // ── User pressed Play ─────────────────────────────────────────────────
                userPaused   = false;
                systemPaused = false;
                savePlayingIntent(true);
                // Keep _smartPlayer's userPaused in sync so it won't abort reconnects
                if (window._smartPlayer) { window._smartPlayer.setUserPaused(false); window._smartPlayer.isPlaying = true; }
                // Clear tab-closed sentinel
                try { localStorage.removeItem(skey('tab_closed_at')); } catch(e) {}

                if (window._smartPlayer) {
                    // resume() always restarts from the live edge to guarantee
                    // no stale buffered audio plays. The silent-handoff technique
                    // inside _startStream() preserves the MediaSession notification.
                    window._smartPlayer.resume();
                } else {
                    // Fallback: no SmartRadioPlayer — simple play, no src reset
                    RadioAudio.play()?.then(() => {
                        togglePlayerDisplay(true);
                        if (stickyPlayer) stickyPlayer.style.display = 'flex';
                        audioPersistenceManager.saveAudioState();
                    }).catch(error => {
                        console.error('Error playing audio:', error);
                    });
                }
            } else {
                // ── User pressed Pause ────────────────────────────────────────────────
                userPaused   = true;
                systemPaused = false;
                savePlayingIntent(false);
                // Keep _smartPlayer in sync — this cancels any in-flight reconnect timer
                if (window._smartPlayer) { window._smartPlayer.setUserPaused(true); }
                RadioAudio.pause();
                console.log('Audio playback paused by user');
                audioPersistenceManager.saveAudioState();
                togglePlayerDisplay(false);
            }
        }
        // Mobile Menu Toggle
        const navbarToggler = document.querySelector('.navbar-toggler');
        const navbarNav = document.querySelector('#navbarNav');

        // Player Elements
        const heroListenBtn = document.getElementById('hero-listen-btn');



        // Hero Listen button
        if (heroListenBtn) {
            heroListenBtn.addEventListener('click', () => {
                toggleLiveAudio();
            });
        }


        // Promo slider functionality
        const indicators = document.querySelectorAll('.indicator');
        const slides = [
            document.getElementById('slide1'),
            document.getElementById('slide2'),
            document.getElementById('slide3')
        ];

        let currentSlide = 0;

        function showSlide(index) {
            // Update indicators
            indicators.forEach((indicator, i) => {
                if (i === index) {
                    indicator.classList.add('active');
                    slides[i].style.transform = 'translateY(0)';
                } else {
                    indicator.classList.remove('active');
                    slides[i].style.transform = 'translateY(100%)';
                }
            });

            currentSlide = index;
        }

        // Set up indicator clicks
        indicators.forEach((indicator, index) => {
            indicator.addEventListener('click', () => {
                showSlide(index);
            });
        });

        // Auto-rotate slides
        setInterval(() => {
            const nextSlide = (currentSlide + 1) % slides.length;
            showSlide(nextSlide);
        }, 5000);

        // Add smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                if (href === '#') return; // Ignore empty anchors to avoid querySelector crash
                e.preventDefault();

                try {
                    const target = document.querySelector(href);
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth'
                        });
                    }
                } catch (err) {}
            });
        });

        // Track if user has manually paused the stream
        // Track if user has manually paused the stream
        let userPaused = false;
        let systemPaused = false;
        let playAttemptInProgress = false;
        let audioInitialized = false;

        function savePlayingIntent(shouldPlay) {
            try {
                localStorage.setItem(skey('playing_intent'), shouldPlay ? '1' : '0');
                localStorage.setItem(skey('playing_intent_time'), Date.now().toString());
                localStorage.setItem(skey('was_playing'), shouldPlay ? '1' : '0');
            } catch (e) {}
        }

        function getPlayingIntent() {
            try {
                const intent = localStorage.getItem(skey('playing_intent'));
                const time = parseInt(localStorage.getItem(skey('playing_intent_time')) || '0');
                if (Date.now() - time < 30 * 60 * 1000) {
                    return intent === '1';
                }
            } catch (e) {}
            return false;
        }


        // ─────────────────────────────────────────────────────────────────────────────

        // Initialize audio element
        function initAudio() {
            const liveAudio = document.getElementById('live-audio');
            if (!liveAudio) {
                console.error('Audio element not found!');
                return null;
            }

            // Set up error handling
            liveAudio.onerror = function() {
                console.error('Audio error:', liveAudio.error);
                if (!userPaused) {
                    console.log('🔄 Attempting recovery after audio error...');
                    setTimeout(() => {
                        if (!userPaused) {
                            if (window._smartPlayer) {
                                // Delegate to SmartRadioPlayer — it will cache-bust and
                                // reload without touching MediaSession metadata.
                                window._smartPlayer._startStream();
                            } else {
                                // Fallback: cache-busted URL so we never replay a stale buffer.
                                const freshUrl = getStreamUrl();
                                if (freshUrl) {
                                    // Removed audio.pause(); and removeAttribute('src') so the MediaSession
                                    // doesn't immediately get torn down by the browser before the new src loads.
                                    liveAudio.src = freshUrl;
                                }
                                liveAudio.play().catch(e => console.error('Error recovery failed:', e));
                            }
                        }
                    }, 1000);
                } else {
                    const miniPlayIcon = document.getElementById('mini-play-icon');
                    if (miniPlayIcon) {
                        miniPlayIcon.classList.remove('fa-pause');
                        miniPlayIcon.classList.add('fa-play');
                    }
                    playAttemptInProgress = false;
                }
            };

            // Update play/pause button state when playback state changes
            liveAudio.onplay = function() {
                console.log('Audio playback started');
                const miniPlayIcon = document.getElementById('mini-play-icon');
                if (miniPlayIcon) {
                    miniPlayIcon.classList.remove('fa-play');
                    miniPlayIcon.classList.add('fa-pause');
                }
                playAttemptInProgress = false;
            };

            liveAudio.addEventListener('pause', function() {
                // Use getElementById directly — variables from DOMContentLoaded are not in scope here
                const navbarPlayIconEl = document.getElementById('navbar-play-icon');
                const playIconEl      = document.getElementById('play-icon');
                const miniPlayIconEl  = document.getElementById('mini-play-icon');

                if (navbarPlayIconEl) navbarPlayIconEl.className = 'fas fa-play me-1';
                if (playIconEl)       playIconEl.className       = 'fas fa-play';
                if (miniPlayIconEl)   miniPlayIconEl.className   = 'fas fa-play';

                // 🚗 KEY: Only save isPlaying:false when USER paused.
                // On system pauses (BT disconnect / phone call), preserve isPlaying:true
                // so that SmartRadioPlayer can auto-resume when the connection returns.
                if (userPaused) {
                    audioPersistenceManager.saveAudioState();
                } else {
                    // System pause — preserve the intent flag in localStorage
                    const _a = RadioAudio.get();
                    if (_a) {
                        try {
                            const prevState = JSON.parse(localStorage.getItem(skey('radio_audio_state')) || '{}');
                            localStorage.setItem(skey('radio_audio_state'), JSON.stringify({
                                ...prevState,
                                isPlaying: true, // preserve intent
                                volume:    _a.volume,
                                timestamp: Date.now()
                            }));
                        } catch(e) {}
                    }
                }

                togglePlayerDisplay(false);

                if ('mediaSession' in navigator) {
                    navigator.mediaSession.playbackState = 'paused';
                }

                // Track SYSTEM pause (car disconnect, screen lock, phone call)
                // SmartRadioPlayer's own 'pause' event listener handles the reconnect
                // scheduling. We just set the flag here for other guards.
                if (!userPaused) {
                    systemPaused = true;
                    console.log('🚗 System pause detected — SmartRadioPlayer will auto-reconnect.');
                    // Ensure SmartRadioPlayer intent flags are correct
                    if (window._smartPlayer) {
                        window._smartPlayer.isPlaying  = true;
                        window._smartPlayer.userPaused = false;
                    }
                }
            });

            // ── Hardware media controls (headphone button, steering wheel, lock screen) ──
            // These behave exactly like the sticky-player Play/Pause buttons so the
            // user gets a consistent experience regardless of which control they use.
            //
            // Phone-call / BT-disconnect guard:
            //   The audio element's 'pause' event fires BEFORE the MediaSession 'pause'
            //   action, so SmartRadioPlayer has already set _currentState = 'interrupted'
            //   by the time our handler runs. We check that flag synchronously —
            //   no debounce needed.
            if ('mediaSession' in navigator) {

                // PLAY — same as tapping Play on the sticky player
                try {
                    navigator.mediaSession.setActionHandler('play', function() {
                        console.log('🎧 MediaSession: play');
                        if (RadioAudio.paused()) {
                            toggleLiveAudio();
                        }
                    });
                } catch (e) {}

                // PAUSE — same as tapping Pause on the sticky player
                // Skip if SmartRadioPlayer flagged this as a system-initiated pause
                // (phone call / BT disconnect) — it will auto-reconnect on its own.
                try {
                    navigator.mediaSession.setActionHandler('pause', function() {
                        console.log('🎧 MediaSession: pause');
                        const sp = window._smartPlayer;
                        if (sp && sp._currentState === 'interrupted') {
                            // OS-initiated pause — SmartRadioPlayer handles reconnect.
                            console.log('🎧 System pause (interrupted) — skipping user-pause logic.');
                            return;
                        }
                        if (!RadioAudio.paused()) {
                            toggleLiveAudio();
                        }
                    });
                } catch (e) {}

                // STOP — fired by some Android head-units when a phone call ENDS
                try {
                    navigator.mediaSession.setActionHandler('stop', function() {
                        console.log('🎧 MediaSession: stop (call ended) — auto-resuming if not user-paused');
                        if (!userPaused && window._smartPlayer) {
                            systemPaused = false;
                            window._smartPlayer.setUserPaused(false);
                            window._smartPlayer.isPlaying = true;
                            window._smartPlayer._startStream();
                        }
                    });
                } catch (e) {}
            }

            // NOTE: 'interruptionEnd' state handling has been moved into the
            // SmartRadioPlayer constructor's onStateChange callback above, which is
            // guaranteed to run at the correct time and avoids a closure race.

            return liveAudio;
        }

        // Initialize audio player when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {

            // Close mobile navbar when a nav link is clicked (except dropdown toggles like Share)
            const mobileNavLinks = document.querySelectorAll('#navbarNav .nav-link:not(.dropdown-toggle)');

            mobileNavLinks.forEach(link => {
                link.addEventListener('click', () => {
                    // Only close if navbar is open (mobile view)
                    if (navbarNav.classList.contains('show')) {
                        navbarToggler.click();
                    }
                });
            });

            // Smooth scrolling for navigation links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function(e) {
                    const href = this.getAttribute('href');
                    if (href === '#') return; // Ignore empty anchors to avoid querySelector crash
                    e.preventDefault();

                    try {
                        const target = document.querySelector(href);
                        if (target) {
                            target.scrollIntoView({
                                behavior: 'smooth',
                                block: 'start'
                            });
                        }
                    } catch (err) {
                        // Suppress invalid selector errors
                    }
                });
            });

            // Initialize audio
            const liveAudio = initAudio();
            if (liveAudio) {
                audioInitialized = true;

                // Set up mini player controls
                const miniPlayBtn = document.getElementById('mini-play-btn');
                if (miniPlayBtn) {
                    // touchend fires immediately on mobile (no 300ms delay) and is
                    // reliable on both iOS Safari and Android Chrome.
                    // preventDefault() stops the follow-up synthetic click from double-toggling.
                    miniPlayBtn.addEventListener('touchend', function(e) {
                        e.preventDefault(); // Suppress the delayed synthetic click
                        e.stopPropagation();
                        toggleLiveAudio();
                    }, { passive: false });

                    // click is kept as a fallback for desktop browsers
                    miniPlayBtn.addEventListener('click', function(e) {
                        e.stopPropagation();
                        toggleLiveAudio();
                    });
                }

                // Also allow clicking anywhere in the player info area
                const playerInfo = document.querySelector('.player-info');
                if (playerInfo) {
                    playerInfo.addEventListener('click', function() {
                        toggleLiveAudio();
                    });
                }
            }

            // Set up other player controls
            const navbarPlayIcon = document.getElementById('navbar-play-icon');
            const miniPlayIcon = document.getElementById('mini-play-icon');
            const prevBtn = document.getElementById('prev-btn');
            const nextBtn = document.getElementById('next-btn');
            const minimizeBtn = document.getElementById('minimize-btn');
            const stickyPlayer = document.getElementById('sticky-player');

            // Ensure sticky player is visible
            if (stickyPlayer) {
                stickyPlayer.style.display = 'flex';
            }

            // Update all play/pause icons when audio state changes
            if (liveAudio) {


                liveAudio.addEventListener('play', function() {
                    const playIcon = document.getElementById('play-icon');

                    if (navbarPlayIcon) navbarPlayIcon.className = "fas fa-pause me-1";
                    if (playIcon) playIcon.className = "fas fa-pause";
                    if (miniPlayIcon) miniPlayIcon.className = "fas fa-pause";
                    audioPersistenceManager.saveAudioState();
                    togglePlayerDisplay(true);

                    if ('mediaSession' in navigator) {
                        navigator.mediaSession.playbackState = 'playing';

                        // Re-assert stored metadata so lock screen is correct.
                        // _startStream() already does this, but this catches edge cases
                        // where play fires from non-SmartRadioPlayer code paths.
                        if (window._storedMediaMetadata && typeof window._commitMediaSession === 'function') {
                            window._commitMediaSession(window._storedMediaMetadata, true);
                        }
                    }
                    if (typeof setupIOSAudio === 'function') setupIOSAudio();

                });

                // Duplicate pause listener removed. Handled in initAudio.

                liveAudio.addEventListener('volumechange', function() {
                    audioPersistenceManager.saveAudioState();
                });
            }

            // Polling fallback removed

            // Connect sticky player buttons to live audio
            // NOTE: 'playBtn' did not exist in this scope — wired via miniPlayBtn above.
            // This block is intentionally left as a no-op to avoid a ReferenceError.
            // All play/pause clicks are handled via miniPlayBtn, playerInfo, and navbarPlayBtn.

            // miniPlayIcon is INSIDE miniPlayBtn which already has a click handler
            // that calls toggleLiveAudio() with stopPropagation.
            // A separate click listener on the icon would cause a double-toggle
            // (icon fires → bubbles to btn fires = two calls = net no-op).
            // Do NOT add a click listener to miniPlayIcon directly.

            // Previous and Next buttons (for live radio, these could skip to different streams or be disabled)
            if (prevBtn) {
                prevBtn.addEventListener('click', function() {
                    // For live radio, you might want to disable this or implement station switching
                    console.log('Previous button clicked - Live radio stream');
                });
            }

            if (nextBtn) {
                nextBtn.addEventListener('click', function() {
                    // For live radio, you might want to disable this or implement station switching
                    console.log('Next button clicked - Live radio stream');
                });
            }

            // Minimize/Expand sticky player
            if (minimizeBtn && stickyPlayer) {
                let isMinimized = false;
                minimizeBtn.addEventListener('click', function() {
                    isMinimized = !isMinimized;
                    if (isMinimized) {
                        stickyPlayer.classList.add('player-minimized');
                        minimizeBtn.innerHTML = '<i class="fas fa-chevron-up"></i>';
                        minimizeBtn.setAttribute('title', 'Expand');
                    } else {
                        stickyPlayer.classList.remove('player-minimized');
                        minimizeBtn.innerHTML = '<i class="fas fa-chevron-down"></i>';
                        minimizeBtn.setAttribute('title', 'Minimize');
                    }
                });
            }
        });

        // Test function for manual API testing
        window.testNewsAPI = function() {
            console.log('Testing News API...');
            const loadingElement = document.getElementById('news-loading');
            if (loadingElement) {
                loadingElement.style.display = 'block';
            }

            fetch('/api/news')
                .then(response => {
                    console.log('API Response Status:', response.status);
                    return response.text();
                })
                .then(text => {
                    console.log('Raw API Response:', text);
                    try {
                        const news = JSON.parse(text);
                        console.log('Parsed News:', news);
                        alert('API Test Successful! Check console for details.');
                        displayNews(news);
                    } catch (e) {
                        console.error('JSON Parse Error:', e);
                        alert('API returned data but JSON parsing failed. Check console.');
                    }
                })
                .catch(error => {
                    console.error('API Test Error:', error);
                    alert('API Test Failed! Check console for details.');
                })
                .finally(() => {
                    const loadingElement = document.getElementById('news-loading');
                    if (loadingElement) {
                        loadingElement.style.display = 'none';
                    }
                });
        };

        // Load news articles automatically - only if we're on a page with news container
        if (document.getElementById('news-container')) {
            console.log('News container found, loading news...');
            setTimeout(() => {
                loadNews();
                loadBillboard(); // Load Billboard data alongside news
            }, 2000); // Wait 2 seconds for page to fully load
        } else {
            console.log('News container not found on this page');
        }

        // Billboard refresh button
        const refreshBillboardBtn = document.getElementById('refresh-billboard');
        if (refreshBillboardBtn) {
            refreshBillboardBtn.addEventListener('click', function() {
                loadBillboard(true); // Force refresh
            });
        }

        function loadNews() {
            console.log('Loading news...');

            // Use XMLHttpRequest as fallback for better compatibility
            const xhr = new XMLHttpRequest();
            xhr.open('GET', '/api/news', true);
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    console.log('XHR Response status:', xhr.status);
                    if (xhr.status === 200) {
                        try {
                            const news = JSON.parse(xhr.responseText);
                            console.log('News loaded:', news);
                            displayNews(news);
                        } catch (e) {
                            console.error('JSON parse error:', e);
                            displayFallbackNews();
                        }
                    } else {
                        console.error('XHR Error:', xhr.status, xhr.statusText);
                        displayFallbackNews();
                    }
                }
            };
            xhr.send();
        }

        function displayNews(newsArticles) {
            console.log('Displaying news articles:', newsArticles);
            const newsContainer = document.getElementById('news-container');
            const loadingElement = document.getElementById('news-loading');

            console.log('News container found:', !!newsContainer);
            console.log('Loading element found:', !!loadingElement);

            if (!newsContainer) {
                console.error('News container not found!');
                return;
            }

            // Remove loading spinner
            if (loadingElement) {
                loadingElement.style.display = 'none';
                console.log('Loading spinner hidden');
            }

            // Clear existing content
            newsContainer.innerHTML = '';

            // Display news articles
            if (newsArticles && newsArticles.length > 0) {
                newsArticles.forEach((article, index) => {
                    const newsCard = createNewsCard(article, index);
                    newsContainer.appendChild(newsCard);
                });
                console.log(`Displayed ${newsArticles.length} news articles`);

                // Add a success message
                const successDiv = document.createElement('div');
                successDiv.className = 'col-12 text-center mt-3';
                successDiv.innerHTML = '<div class="alert alert-success">✅ News loaded successfully!</div>';
                newsContainer.appendChild(successDiv);
            } else {
                console.log('No news articles to display');
                const noNewsDiv = document.createElement('div');
                noNewsDiv.className = 'col-12 text-center';
                noNewsDiv.innerHTML = '<div class="alert alert-warning">⚠️ No news articles available at the moment.</div>';
                newsContainer.appendChild(noNewsDiv);
            }
        }

        function createNewsCard(article, index) {
            const col = document.createElement('div');
            col.className = 'col-md-4 mb-4';

            // Use urlToImage from NewsAPI as main thumbnail
            const imageUrl = article.urlToImage;
            const defaultImage = '/hero.jpg'; // Using existing hero image as placeholder

            col.innerHTML = `
                <div class="card h-100 shadow-sm">
                    <div class="position-relative" style="height: 192px; overflow: hidden;">
                        <img
                            src="${imageUrl || defaultImage}"
                            alt="${article.title || 'News article'}"
                            class="w-100 h-100"
                            style="object-fit: cover; border-radius: 0.375rem 0.375rem 0 0;"
                            onerror="this.src='${defaultImage}'"
                        >
                    </div>
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title">${article.title || 'Untitled'}</h5>
                        <p class="card-text flex-grow-1">${article.description || 'No description available.'}</p>
                        <div class="mt-auto">
                            <small class="text-muted d-block mb-2">
                                Source: ${article.source?.name || article.source || 'Unknown'}
                            </small>
                            <a href="${article.url || '#'}" target="_blank" class="btn btn-primary btn-sm">
                                Read More
                            </a>
                        </div>
                    </div>
                </div>
            `;

            return col;
        }

        function displayFallbackNews() {
            const fallbackNews = [{
                    title: 'New Album Release',
                    description: 'Top artist announces new album dropping next month with exclusive tour dates.',
                    url: '#',
                    image: null,
                    source: 'Music News'
                },
                {
                    title: 'Interview with Rising Star',
                    description: 'Exclusive interview with this month\'s breakout artist on their journey to success.',
                    url: '#',
                    image: null,
                    source: 'Celebrity News'
                },
                {
                    title: 'Music Awards 2023',
                    description: 'Complete list of winners and highlights from this year\'s prestigious music awards.',
                    url: '#',
                    image: null,
                    source: 'Awards News'
                }
            ];

            displayNews(fallbackNews);
        }

        // Billboard Functions
        function loadBillboard(forceRefresh = false) {
            console.log('Loading Billboard Top 40...');
            const billboardLoading = document.getElementById('billboard-loading');
            const billboardContainer = document.getElementById('billboard-container');

            if (billboardLoading) {
                billboardLoading.style.display = 'block';
            }
            if (billboardContainer) {
                billboardContainer.style.display = 'none';
            }

            const url = forceRefresh ? '/api/billboard?refresh=1' : '/api/billboard';

            fetch(url)
                .then(response => {
                    console.log('Billboard API Response status:', response.status);
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Billboard data loaded:', data);
                    displayBillboard(data);
                })
                .catch(error => {
                    console.error('Billboard API Error:', error);
                    displayFallbackBillboard();
                })
                .finally(() => {
                    if (billboardLoading) {
                        billboardLoading.style.display = 'none';
                    }
                });
        }

        function displayBillboard(songs) {
            const billboardContainer = document.getElementById('billboard-container');

            if (!billboardContainer) {
                console.error('Billboard container not found!');
                return;
            }

            billboardContainer.innerHTML = '';
            billboardContainer.style.display = 'block';

            if (!songs || songs.length === 0) {
                displayFallbackBillboard();
                return;
            }

            // Create scrollable container
            const scrollContainer = document.createElement('div');
            scrollContainer.className = 'billboard-container-scroll col-12';

            songs.forEach((song, index) => {
                const songItem = createBillboardSongItem(song, index);
                scrollContainer.appendChild(songItem);
            });

            billboardContainer.appendChild(scrollContainer);

            // Add update timestamp
            const timestampDiv = document.createElement('div');
            timestampDiv.className = 'col-12 text-center mt-3';
            timestampDiv.innerHTML =
                `<small class="text-muted"><i class="fas fa-clock"></i> Updated: ${new Date().toLocaleString()}</small>`;
            billboardContainer.appendChild(timestampDiv);
        }

        function createBillboardSongItem(song, index) {
            const songDiv = document.createElement('div');
            songDiv.className = 'billboard-song-item d-flex align-items-center';

            // Create thumbnail element
            const thumbnailElement = song.image ?
                `<img src="${song.image}" alt="${song.title}" class="billboard-thumbnail" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                 <div class="billboard-thumbnail-placeholder" style="display: none;">
                     <i class="fas fa-music"></i>
                 </div>` :
                `<div class="billboard-thumbnail-placeholder">
                     <i class="fas fa-music"></i>
                 </div>`;

            songDiv.innerHTML = `
                <div class="billboard-position me-3">
                    ${song.position}
                </div>
                ${thumbnailElement}
                <div class="flex-grow-1">
                    <div class="billboard-song-title">${song.title}</div>
                    <div class="billboard-artist">${song.artist}</div>
                    <div class="billboard-stats">
                        <span class="me-3"><i class="fas fa-calendar-week"></i> ${song.weeks} weeks</span>
                        <span><i class="fas fa-trophy"></i> Peak: #${song.peak}</span>
                    </div>
                </div>
                <div class="text-end">
                    <i class="fas fa-music text-primary"></i>
                </div>
            `;

            // Add click event for future functionality (could link to Spotify, Apple Music, etc.)
            songDiv.addEventListener('click', function() {
                console.log(`Clicked on: ${song.title} by ${song.artist}`);
                // Future: Could open streaming service or show more details
            });

            return songDiv;
        }

        function displayFallbackBillboard() {
            // No hardcoded fallback data - show empty state
            const billboardContainer = document.getElementById('billboard-container');
            if (billboardContainer) {
                billboardContainer.innerHTML =
                    '<p class="text-center text-gray-500 py-4">Billboard Hot 100 data temporarily unavailable. Please check API configuration.</p>';
            }
        }

        // ── Web Bluetooth API Tracker (BLE Audio Interaction) ──
        class WebBluetoothAudioTracker {
            constructor() {
                this.device = null;
            }

            async connect() {
                if (!navigator.bluetooth) {
                    console.warn("Web Bluetooth API is not supported in this browser.");
                    alert("Web Bluetooth is not supported in this browser. Please use Chrome/Edge on a secure context (HTTPS).");
                    return;
                }

                try {
                    console.log("[WebBluetooth] Requesting device...");
                    // Using acceptAllDevices because specific BLE audio profiles vary
                    this.device = await navigator.bluetooth.requestDevice({
                        acceptAllDevices: true,
                        optionalServices: ['battery_service'] // standard service for broad compatibility check
                    });

                    console.log("[WebBluetooth] Connecting to GATT Server...");
                    const server = await this.device.gatt.connect();

                    console.log("[WebBluetooth] Device connected:", this.device.name);
                    this.onConnected();

                    // Listen for disconnection
                    this.device.addEventListener('gattserverdisconnected', this.onDisconnected.bind(this));

                } catch (error) {
                    console.error("[WebBluetooth] Connection failed:", error);
                }
            }

            onConnected() {
                console.log("[WebBluetooth] BLE device connected — triggering route reconnect...");
                if (window._smartPlayer) {
                    // Use route-reconnect path — resumes only if we were waiting for reconnect
                    window._smartPlayer.handleRouteConnected();
                    // If handleRouteConnected didn't resume (we weren't interrupted), try explicit resume
                    if (window._smartPlayer.isPlaying === false && !window._smartPlayer.userPaused) {
                        const audio = RadioAudio.get();
                        if (audio && audio.paused) {
                            window._smartPlayer.resume();
                        }
                    }
                } else {
                    const audio = RadioAudio.get();
                    if (audio && audio.paused) {
                        audio.play().catch(e => console.error("Play failed:", e));
                    }
                }
                this.notifyServer('connected', this.device ? this.device.name : 'Unknown');
            }

            onDisconnected(event) {
                console.log("[WebBluetooth] BLE device disconnected — requesting system pause...");
                if (window._smartPlayer) {
                    // Use route-disconnect path — sets systemPaused, NOT userPaused.
                    // This allows auto-resume when the device reconnects.
                    window._smartPlayer.handleRouteDisconnected();
                } else {
                    const audio = RadioAudio.get();
                    if (audio && !audio.paused) {
                        audio.pause();
                    }
                }
                const deviceName = event.target ? event.target.name : 'Unknown';
                this.notifyServer('disconnected', deviceName);
                this.device = null;
            }

            notifyServer(status, deviceName) {
                // AJAX Fetch to server
                const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

                fetch('/api/bluetooth/status', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        status: status,
                        device: deviceName,
                        timestamp: new Date().toISOString()
                    })
                }).then(response => {
                    console.log(`[WebBluetooth] Server notified: ${status} (status: ${response.status})`);
                }).catch(err => {
                    console.error("[WebBluetooth] Failed to notify server:", err);
                });
            }
            
            disconnect() {
                 if (!this.device) {
                     return;
                 }
                 console.log("[WebBluetooth] Manually disconnecting...");
                 if (this.device.gatt.connected) {
                     this.device.gatt.disconnect();
                 }
            }
        }

        window.btTracker = new WebBluetoothAudioTracker();
        
        // Example UI binding: If you add <button id="bt-connect-btn">Connect BT</button> anywhere
        document.addEventListener('DOMContentLoaded', () => {
             const btn = document.getElementById('bt-connect-btn');
             if (btn) {
                 btn.addEventListener('click', () => {
                     window.btTracker.connect();
                 });
             }
        });
    </script>
