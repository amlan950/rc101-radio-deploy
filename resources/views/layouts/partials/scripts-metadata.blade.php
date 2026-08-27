<script>
    // Global error handler
    window.onerror = function (message, source, lineno, colno, error) {
        console.error('Global error:', {
            message,
            source,
            lineno,
            colno,
            error
        });
        return true; // Prevent default error handling
    };

    // Stream Slogan
    let stationSlogan = @json(file_exists(public_path(env('SLOGAN_FILE_PATH', 'config/slogan.txt')))
        ? trim(file_get_contents(public_path(env('SLOGAN_FILE_PATH', 'config/slogan.txt'))))
    : '');
    const BASE_URL = window.location.origin;

    // ── Dynamic album title: "AppName - Slogan" ──────────────────────────────────
    const _appName = @json(config('app.name'));
    function getAlbumTitle() {
        return stationSlogan ? (_appName + ' - ' + stationSlogan) : _appName;
    }

    // ── Readable duration formatter: "3 min 35 sec" ──────────────────────────────
    function formatDurationReadable(seconds) {
        if (typeof seconds !== 'number' || !isFinite(seconds) || seconds <= 0) return '';
        const mins = Math.floor(seconds / 60);
        const secs = Math.floor(seconds % 60);
        return mins + ' min ' + secs + ' sec';
    }

    // ── Song timing state (SSE-provided, interpolated via timeupdate) ────────────
    window._currentSongDuration = 0;
    window._currentSongElapsed = 0;
    window._songTimingTimestamp = 0;  // Date.now() when SSE last sent timing

    const _defaultSrc = '{{ asset(env('DEFAULT_ARTWORK_PATH', 'images/alexaicon.jpg')) }}';
    const _defaultAbsoluteSrc = _defaultSrc.startsWith('http') ?
        _defaultSrc :
        window.location.origin + _defaultSrc;



    const _tabSessionId = (function () {
        let id = sessionStorage.getItem(skey('tab_id'));
        if (!id) {
            id = 'tab_' + Date.now() + '_' + Math.random().toString(36).slice(2, 8);
            sessionStorage.setItem(skey('tab_id'), id);
        }
        return id;
    })();

    // Track whether this tab is the "active" media owner
    // (became visible/focused most recently OR is currently playing audio → it owns the MediaSession)
    let _isActiveMediaTab = !document.hidden;

    // BroadcastChannel: tabs tell each other when they become the media owner
    let _metaChannel = null;
    try {
        _metaChannel = new BroadcastChannel(skey('media_owner'));
        _metaChannel.onmessage = function (e) {
            if (e.data && e.data.type === 'claim_owner' && e.data.tabId !== _tabSessionId) {
                // Another tab claimed ownership — this tab steps back
                // HOWEVER: if THIS tab is currently playing audio, it should NOT step back
                // because it's the one driving the CarPlay display.
                const _audio = (typeof RadioAudio !== 'undefined') ? RadioAudio.get() : null;
                if (_audio && !_audio.paused) {
                    console.log('📻 Another tab tried to claim owner, but THIS tab is playing. Ignoring.');
                    _claimMediaOwnership(); // Re-claim immediately
                    return;
                }

                _isActiveMediaTab = false;
                console.log('📻 Another tab claimed MediaSession ownership:', e.data.tabId);
            }
        };
    } catch (e) {
        // BroadcastChannel not supported — degrade gracefully
        console.warn('⚠️ BroadcastChannel not supported — multi-tab sync disabled');
    }

    function _claimMediaOwnership() {
        _isActiveMediaTab = true;
        localStorage.setItem(skey('active_tab'), _tabSessionId);
        if (_metaChannel) {
            _metaChannel.postMessage({ type: 'claim_owner', tabId: _tabSessionId });
        }
        console.log('🏷️ Tab claimed MediaSession ownership:', _tabSessionId);
    }

    // Claim ownership immediately if this tab starts visible
    if (!document.hidden) { _claimMediaOwnership(); }

    // ────────────────────────────────────────────────────────────────────────────

    function buildDefaultArtwork() {
        const base = _defaultAbsoluteSrc;
        return [
            { src: base, sizes: '2048x2048', type: 'image/jpeg' },
            { src: base, sizes: '1024x1024', type: 'image/jpeg' }
        ];
    }

    // Pre-build IMMEDIATELY — available to every code path without waiting for DOM
    window._defaultArtwork = buildDefaultArtwork();
    console.log('🖼️ Default artwork pre-built at startup:', window._defaultArtwork.length, 'entries');

    // ── SINGLE-POINT-OF-TRUTH: Debounced MediaSession metadata commit ──────────
    // ALL code paths that want to update MediaSession metadata MUST go through
    // this function. It coalesces rapid successive calls into ONE write so that
    // Bluetooth/car displays see a single metadata change per song, preventing
    // the artwork flash-then-disappear behaviour.
    window._pendingMediaMetadata = null;
    window._mediaMetadataTimer = null;
    window._mediaMetadataDebounceMs = 350; // enough to coalesce immediate+async

    /**
     * Queue a MediaSession metadata update. If called multiple times within
     * the debounce window, only the LAST call's data is committed.
     *
     * @param {Object} metadataObj  - {title, artist, album, artwork}
     * @param {boolean} immediate   - skip debounce (use for final iTunes result)
     */
    window._commitMediaSession = function (metadataObj, immediate) {
        if (!('mediaSession' in navigator)) return;
        if (!metadataObj) return;

        // Always capture the latest data
        window._pendingMediaMetadata = metadataObj;

        // Sync playback state eagerly (cheap, no flash risk).
        // IMPORTANT: After setting from audio.paused, we also check the SmartPlayer
        // userPaused flag — this prevents Bluetooth/car stacks from interpreting a
        // metadata update (new song SSE) as a signal to resume playback.
        const _a = (typeof RadioAudio !== 'undefined') ? RadioAudio.get() : null;
        if (_a) {
            const _spUserPaused = window._smartPlayer ? window._smartPlayer.userPaused : false;
            const _sysPaused = (typeof systemPaused !== 'undefined') ? systemPaused : false;
            const _isEffectivelyPaused = _spUserPaused || _sysPaused;
            navigator.mediaSession.playbackState = _isEffectivelyPaused ? 'paused' : 'playing';
        }

        if (immediate) {
            // Flush NOW — this is the final/authoritative update (e.g. iTunes artwork arrived)
            if (window._mediaMetadataTimer) {
                clearTimeout(window._mediaMetadataTimer);
                window._mediaMetadataTimer = null;
            }
            _flushMediaSession();
            // Re-enforce paused state AFTER flush so BT/car displays don't see 'playing'
            if (_a) {
                const _spUserPaused2 = window._smartPlayer ? window._smartPlayer.userPaused : false;
                const _sysPaused2 = (typeof systemPaused !== 'undefined') ? systemPaused : false;
                if (_spUserPaused2 || _sysPaused2) {
                    navigator.mediaSession.playbackState = 'paused';
                } else {
                    navigator.mediaSession.playbackState = 'playing';
                }
            }
            return;
        }

        // Debounce: wait for rapid successive calls to settle
        if (window._mediaMetadataTimer) {
            clearTimeout(window._mediaMetadataTimer);
        }
        window._mediaMetadataTimer = setTimeout(_flushMediaSession, window._mediaMetadataDebounceMs);
    };

    function _flushMediaSession() {
        window._mediaMetadataTimer = null;
        const obj = window._pendingMediaMetadata;
        if (!obj) return;

        // Mutate existing MediaMetadata if it exists, to stop Android from tearing down and recreating the notification
        if (navigator.mediaSession.metadata) {
            navigator.mediaSession.metadata.title = obj.title;
            navigator.mediaSession.metadata.artist = obj.artist;
            navigator.mediaSession.metadata.album = obj.album;
            navigator.mediaSession.metadata.artwork = obj.artwork;
        } else {
            navigator.mediaSession.metadata = new MediaMetadata(obj);
        }
        
        window._storedMediaMetadata = obj;
        window._storedArtworkSrc = (obj.artwork && obj.artwork[0]) ? obj.artwork[0].src : null;

        console.log('🎵 [_flushMediaSession] COMMITTED metadata:', obj.title, '-', obj.artist,
            '| artwork entries:', (obj.artwork || []).length);
        // Re-enforce paused state AFTER flush so BT/car displays don't auto-resume
        const _a = (typeof RadioAudio !== 'undefined') ? RadioAudio.get() : null;
        if (_a) {
            const _spUserPaused = window._smartPlayer ? window._smartPlayer.userPaused : false;
            const _sysPaused = (typeof systemPaused !== 'undefined') ? systemPaused : false;
            
            // Do NOT rely purely on _a.paused here. If the stream is buffering or doing 
            // a live-edge reconnect, _a.paused is momentarily true, which incorrectly 
            // flashes the mobile lock screen to Paused. Rely on user/system intent.
            if (_spUserPaused || _sysPaused) {
                console.log('⏸️ [_flushMediaSession] Re-enforcing paused state after debounced flush');
                navigator.mediaSession.playbackState = 'paused';
            } else {
                navigator.mediaSession.playbackState = 'playing';
            }
        }

    }

    // ── iOS MediaSession Artwork Handling ──────────────────────────────────────────
    // Store the resized 1024x1024 data URL in localStorage for iOS lock screen.
    // IMPORTANT: This function ONLY caches artwork to localStorage.
    // It does NOT touch navigator.mediaSession — that is handled exclusively
    // by _commitMediaSession to prevent duplicate updates that cause car display flash.
    window.storeArtworkForIOS = function (src, isDefault = false) {
        return new Promise((resolve) => {
            if (!src) return resolve(null);

            // Always store the original HTTPS URL — iOS MediaSession requires real URLs, not data: URIs
            if (!isDefault && src.startsWith('http')) {
                localStorage.setItem(skey('ios_artwork_src'), src);
                console.log('🖼️ Stored original artwork URL for iOS MediaSession:', src);
            }

            const img = new Image();
            img.crossOrigin = 'Anonymous';
            img.onload = function () {
                const canvas = document.createElement('canvas');
                canvas.width = 1024;
                canvas.height = 1024;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, 1024, 1024);

                try {
                    const dataUrl = canvas.toDataURL('image/jpeg', 0.92);
                    if (isDefault) {
                        localStorage.setItem(skey('ios_default_artwork'), dataUrl);
                        console.log('🖼️ Stored default 1024x1024 artwork for iOS');
                    } else {
                        localStorage.setItem(skey('ios_current_artwork'), dataUrl);
                        console.log('🖼️ Stored new song 1024x1024 artwork for iOS');
                    }
                    console.log('🖼️ iOS artwork cached to localStorage (returning base64 promise)');
                    resolve(dataUrl);
                } catch (e) {
                    console.warn('⚠️ Could not store iOS artwork to localStorage', e);
                    resolve(null);
                }
            };
            img.onerror = function () {
                console.warn('⚠️ Failed to load image for iOS artwork resizing:', src);
                resolve(null);
            };
            img.src = src;
        });
    };

    // Immediately store default artwork for iOS
    if (_defaultAbsoluteSrc) {
        window.storeArtworkForIOS(_defaultAbsoluteSrc, true);
    }
    // ─────────────────────────────────────────────────────────────────────────────




    // updateArtwork — NO LONGER writes to MediaSession directly.
    // Instead it routes through _commitMediaSession so that all artwork
    // updates are debounced with the rest of the metadata, preventing
    // the artwork flash on Bluetooth/car displays.
    function updateArtwork(artworkArray) {
        if (!('mediaSession' in navigator)) {
            console.warn('⚠️ updateArtwork: MediaSession API not supported');
            return;
        }

        let artwork = [];
        if (artworkArray && artworkArray.length > 0) {
            artwork = artworkArray.filter(a => !a.src.startsWith('data:'));
        } else {
            if (window._defaultArtwork && window._defaultArtwork.length > 0) {
                artwork.push(...window._defaultArtwork);
            }
        }

        if (artwork.length === 0) return;

        // Preserve existing title / artist / album — only update artwork
        const existing = window._storedMediaMetadata || {};
        const stName = _appName || "";

        const metadataObj = {
            title: existing.title || 'Live Stream',
            artist: existing.artist || stName,
            album: getAlbumTitle(),
            artwork: artwork
        };

        // Route through debounced commit — NOT direct MediaSession write
        console.log('🖼️ updateArtwork: queuing', artwork.length, 'entries via _commitMediaSession');
        window._commitMediaSession(metadataObj, false);
    }
    // ─────────────────────────────────────────────────────────────────────────────

    function fetchSlogan() {
        fetch(`${BASE_URL}/{{ env('SLOGAN_FILE_PATH', 'config/slogan.txt') }}?t=${new Date().getTime()}`)
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.text();
            })
            .then(text => {
                stationSlogan = text.trim();
                console.log('📝 Fetched station slogan:', stationSlogan);
            })
            .catch(error => {
                console.error('❌ Error fetching slogan:', error);
                stationSlogan = '';
            });
    }

    // Fetch slogan immediately on load
    fetchSlogan();

    function setupIOSAudio() {
        const audio = RadioAudio.get();
        if (!audio) return;

        // iOS requires specific setup for external device metadata.
        // IMPORTANT: On loadedmetadata, we only RE-ASSERT the already-committed
        // metadata via _commitMediaSession. We do NOT create a new MediaMetadata
        // from scratch, which would cause artwork flash on car displays.
        audio.addEventListener('loadedmetadata', function () {
            console.log('iOS: Audio metadata loaded');

            if ('mediaSession' in navigator && window._storedMediaMetadata) {
                // Re-assert the existing metadata (the debouncer handles dedup)
                window._commitMediaSession(window._storedMediaMetadata, true);
                console.log('iOS: Re-asserted existing MediaSession metadata via _commitMediaSession');
            }
        });
    }

    // Refresh slogan every 60 seconds

    // Metadata persistence system
    let currentSongMetadata = {
        artist: 'Now Playing',
        title: 'Live Stream',
        timestamp: 0
    };

    // Function to persist metadata only if it's valid and newer
    function persistMetadata(artist, title) {
        let now = Date.now();

        // Only update if we have valid metadata
        if (artist && title &&
            artist !== 'Now Playing' &&
            title !== 'Live Stream' &&
            artist !== 'Loading...' &&
            title !== 'Loading metadata...' &&
            artist !== 'Error loading stream' &&
            title !== 'Error loading metadata') {

            // Update the persisted metadata in localStorage for cross-page persistence
            if (artist == 'Jammin Radio') {
                artist = `{{ config('app.name') }}`;
            }
            const metadata = {
                artist: artist,
                title: title,
                timestamp: now
            };

            try {
                localStorage.setItem(skey('current_song_metadata'), JSON.stringify(metadata));
                console.log('💾 Persisted metadata to localStorage:', metadata);
                return true;
            } catch (e) {
                console.error('❌ Failed to persist metadata to localStorage:', e);
                return false;
            }
        }

        return false;
    }

    // Function to get the current persisted metadata
    function getPersistedMetadata() {
        console.log('📖 getPersistedMetadata called');
        try {
            const stored = localStorage.getItem(skey('current_song_metadata'));
            console.log('📦 Raw stored data:', stored);
            if (stored) {
                const metadata = JSON.parse(stored);
                console.log('📋 Parsed metadata:', metadata);
                // Check if metadata is recent (within last 10 minutes)
                const now = Date.now();
                const age = now - metadata.timestamp;
                console.log('⏱️ Metadata age:', age, 'ms (limit: 600000ms)');
                if (age < 600000) { // 10 minutes
                    console.log('📖 Retrieved metadata from localStorage:', metadata);
                    return metadata;
                } else {
                    console.log('⏰ Persisted metadata is too old, clearing');
                    localStorage.removeItem(skey('current_song_metadata'));
                }
            } else {
                console.log('📭 No stored metadata found in localStorage');
            }
        } catch (e) {
            console.error('❌ Failed to get persisted metadata from localStorage:', e);
        }

        // Return empty metadata if nothing valid found
        return {
            artist: '',
            title: '',
            timestamp: 0
        };
    }

    // Function to restore persisted metadata to display
    function restorePersistedMetadata() {
        console.log('🔄 restorePersistedMetadata called');
        const metadata = getPersistedMetadata();
        console.log('📖 Retrieved metadata:', metadata);
        if (metadata.artist && metadata.title && metadata.artist !== '' && metadata.title !== '') {
            console.log('🔄 Restoring persisted metadata:', metadata);

            const artistElement = document.getElementById('current-artist');
            const titleElement = document.getElementById('current-title');

            if (artistElement && titleElement) {
                // Clean up persisted metadata to remove time patterns
                let cleanArtist = metadata.artist;
                let cleanTitle = metadata.title;

                // Remove time patterns from title (like 02:35, 2.57, etc.)
                cleanTitle = cleanTitle.replace(/\s*[0-9]{1,2}[:.][0-9]{2}\s*$/, '').trim();
                cleanTitle = cleanTitle.replace(/\s*[0-9]{1,2}[:.][0-9]{2}\s*[0-9]{1,2}[:.][0-9]{2}\s*$/, '')
                    .trim(); // For duration like 02:35:45

                // Remove trailing dashes and separators from title
                cleanTitle = cleanTitle.replace(/[\s\-\–\—\|•:]+$/, '').trim();

                // Add prefixes to the display
                artistElement.textContent = cleanArtist;
                titleElement.textContent = cleanTitle;

                // Also update document title with proper format
                try {
                    const appName = @json(config('app.name'));
                    const stationName = appName || '';
                    if (cleanTitle && cleanArtist && cleanTitle !== 'Live Stream' && cleanTitle !== 'Unknown Song') {
                        document.title = `${cleanTitle} - ${cleanArtist} | ${stationName}`;
                        console.log('📌 [restorePersistedMetadata] Restored document title:', document.title);
                    } else {
                        document.title = stationName;
                    }
                } catch (e) {
                    console.warn('⚠️ Could not update document title:', e);
                }

                return {
                    artist: cleanArtist,
                    title: cleanTitle
                };
            }
        }
        return null;
    }


    document.addEventListener('DOMContentLoaded', function () {
        if (window._metadataInitialized) {
            console.info('Metadata system already initialized, skipping.');
            return;
        }
        window._metadataInitialized = true;
        console.log('DOM fully loaded, initializing player...');

        // Clear tab-closed flag from any previous navigation away from the page so
        // BT reconnect auto-resume works correctly on this page load.
        localStorage.removeItem(skey('tab_closed_at'));

        try {
            // Make sure sticky player is visible on page load
            const stickyPlayer = document.getElementById('sticky-player');
            const playBtn = document.getElementById('play-btn');
            const playIcon = document.getElementById('play-icon');
            if (stickyPlayer) {
                stickyPlayer.style.display = 'flex';
            }

            const audio = document.getElementById("mini-player-audio")
            // Ensure stream URL is properly escaped for JavaScript
            const streamUrl =
                "{{ addslashes(env('STREAM_URL', 'https://de1.api.radio-browser.info/json/stations/topvote/100')) }}";

            console.log('Stream URL:', streamUrl);

            // ── Playback-intent state ─────────────────────────────────
            // userPaused  = true only when the USER explicitly pressed Pause.
            let userPaused = false;

            // List of CORS proxies to try
            const corsProxies = [
                'https://api.allorigins.win/raw?url=',
                'https://corsproxy.io/?' + encodeURIComponent,
                '' // Try direct as last resort
            ];

            // Common metadata endpoints to try
            const metadataEndpoints = [
                '/status-json.xsl',
                '/status-json',
                '/status.xsl',
                '/status',
                '/7.html'
            ];

            let currentProxyIndex = 0;
            let currentEndpointIndex = 0;
            let metadataInterval;
            let retryCount = 0;
            const maxRetries = 3;
            const miniPlayIcon = document.getElementById('mini-play-icon');
            let lastMetadata = '';

            // Fallback metadata for when we can't fetch from the stream
            const fallbackMetadata = [{
                artist: '{{ config('app.name') }}',
                title: 'Playing the best music'
            },
            {
                artist: 'Live Radio',
                title: 'Streaming now'
            },
            {
                artist: '{{ config('app.name') }}',
                title: 'Tune in for great music'
            }
            ];
            let fallbackIndex = 0;

            // ── Song-position helper ──────────────────────────────────────────────────────
            // Forwards elapsed/duration from the stream metadata to the Media Session API
            // so iPhone lock screen widgets and car head units can display the progress bar.
            window.updatePositionState = function (elapsed, duration) {
                if (!('mediaSession' in navigator)) return;
                if (typeof duration !== 'number' || duration <= 0) return;

                // Store for timeupdate interpolation
                window._currentSongDuration = duration;
                window._currentSongElapsed = (typeof elapsed === 'number' && elapsed >= 0) ? elapsed : 0;
                window._songTimingTimestamp = Date.now();

                try {
                    const pos = Math.min(window._currentSongElapsed, duration);
                    const audio = RadioAudio.get();
                    const rate = audio ? (audio.playbackRate || 1) : 1;
                    navigator.mediaSession.setPositionState({
                        duration: duration,
                        playbackRate: rate,
                        position: pos
                    });
                    console.log(`⏱️ setPositionState: ${pos.toFixed(1)}s / ${duration.toFixed(1)}s (${formatDurationReadable(duration)}) rate=${rate}`);

                    // Update readable duration display if element exists
                    const durationEl = document.getElementById('song-duration-display');
                    if (durationEl) {
                        durationEl.textContent = formatDurationReadable(duration);
                    }
                    const remainingEl = document.getElementById('song-remaining-display');
                    if (remainingEl) {
                        const remaining = Math.max(0, duration - pos);
                        remainingEl.textContent = '-' + formatDurationReadable(remaining);
                    }
                } catch (e) {
                    // setPositionState not supported on older browsers — silently ignore
                }
            };

            // ── Continuous position updates via timeupdate ────────────────────────────
            function getLiveMediaPosition() {
                const duration = Number(window._currentSongDuration);
                if (!Number.isFinite(duration) || duration <= 0) return null;

                const lastElapsed = Math.max(0, Number(window._currentSongElapsed) || 0);
                const lastTimestamp = Number(window._songTimingTimestamp) || Date.now();
                const elapsedSinceUpdate = Math.max(0, (Date.now() - lastTimestamp) / 1000);
                const position = Math.min(lastElapsed + elapsedSinceUpdate, Math.max(0, duration - 0.25));
                const audio = (typeof RadioAudio !== 'undefined') ? RadioAudio.get() : null;

                return {
                    duration: duration,
                    playbackRate: audio ? (audio.playbackRate || 1) : 1,
                    position: position
                };
            }

            window.reassertLiveMediaPosition = function (reason = 'live-media') {
                if (!('mediaSession' in navigator)) return;

                const state = getLiveMediaPosition();
                if (!state) return;

                try {
                    navigator.mediaSession.setPositionState(state);
                    console.log(`[LiveMediaSession] Position reasserted after ${reason}: ${state.position.toFixed(1)}s / ${state.duration.toFixed(1)}s`);
                } catch (e) {
                    // setPositionState is not available on every browser.
                }
            };

            window.guardLiveStreamSeek = function (reason = 'unknown', opts = {}) {
                console.log(`[LiveMediaSession] Ignored seek action: ${reason}`);
                window.reassertLiveMediaPosition(reason);

                if (opts.resetLiveEdge && window._smartPlayer && typeof window._smartPlayer.jumpToLiveEdge === 'function') {
                    window._smartPlayer.jumpToLiveEdge(reason);
                }
            };

            window.configureLiveMediaSessionActions = function () {
                if (!('mediaSession' in navigator)) return;

                try {
                    navigator.mediaSession.setActionHandler('seekto', () => {
                        window.guardLiveStreamSeek('mediaSession:seekto');
                    });
                } catch (e) { }

                ['seekbackward', 'seekforward', 'previoustrack', 'nexttrack'].forEach((action) => {
                    try {
                        navigator.mediaSession.setActionHandler(action, null);
                    } catch (e) {
                        try {
                            navigator.mediaSession.setActionHandler(action, () => {
                                window.guardLiveStreamSeek(`mediaSession:${action}`);
                            });
                        } catch (ignore) { }
                    }
                });

                window.reassertLiveMediaPosition('configure-actions');
            };
            window.configureLiveMediaSessionActions();

            // The audio element fires 'timeupdate' ~4x/sec, which we use to interpolate
            // the SSE-provided elapsed time and keep external devices (car, lock screen)
            // showing a smoothly advancing progress bar in real time.
            (function _setupTimeupdateSync() {
                function _bindTimeupdate() {
                    const audio = RadioAudio.get();
                    if (!audio) {
                        // Audio element not ready yet — retry shortly
                        setTimeout(_bindTimeupdate, 500);
                        return;
                    }
                    if (audio._timeupdateSyncBound) return; // already bound
                    audio._timeupdateSyncBound = true;

                    audio.addEventListener('timeupdate', function () {
                        if (!('mediaSession' in navigator)) return;
                        const duration = window._currentSongDuration;
                        if (!duration || duration <= 0) return;

                        // Interpolate elapsed from last SSE timestamp
                        const now = Date.now();
                        const elapsed = window._currentSongElapsed + ((now - window._songTimingTimestamp) / 1000);
                        const currentTime = Math.min(Math.max(0, elapsed), duration);
                        const playbackRate = audio.playbackRate || 1;

                        try {
                            navigator.mediaSession.setPositionState({
                                duration: duration,
                                playbackRate: playbackRate,
                                position: currentTime
                            });
                        } catch (e) { /* silently ignore */ }

                        // Update on-screen time displays
                        const durationEl = document.getElementById('song-duration-display');
                        if (durationEl) durationEl.textContent = formatDurationReadable(duration);

                        const remainingEl = document.getElementById('song-remaining-display');
                        if (remainingEl) {
                            const remaining = Math.max(0, duration - currentTime);
                            remainingEl.textContent = '-' + formatDurationReadable(remaining);
                        }
                    });
                    console.log('🔗 timeupdate → setPositionState sync bound to audio element');
                }
                // Bind immediately if possible, otherwise wait for DOM
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', _bindTimeupdate);
                } else {
                    _bindTimeupdate();
                }
            })();

            // Function to update the player with song information
            window.updateSongInfo = function (info) {
                if (!info) {
                    console.warn('⚠️ No song info provided to updateSongInfo');
                    return;
                }

                console.log('🔄 Updating song info:', info);

                const artistElement = document.getElementById('current-artist');
                const titleElement = document.getElementById('current-title');

                if (!artistElement || !titleElement) {
                    console.error('❌ Could not find artist or title elements in the DOM');
                    return;
                }

                // Default values
                let artist = 'Now Playing';
                let title = 'Live Stream';

                // Check if info is an object with artist and title properties
                if (typeof info === 'object' && info !== null) {
                    if (info.artist || info.song) {
                        artist = info.artist || 'Unknown Artist';
                        title = info.song || info.title || 'Unknown Song';
                    } else if (info.title) {
                        // If only title is present, try to split it
                        const titleStr = info.title;
                        const separators = [' - ', ' – ', ' — ', ' • ', ' | ', ':'];
                        for (const sep of separators) {
                            if (titleStr.includes(sep)) {
                                const parts = titleStr.split(sep).map(part => part.trim());
                                if (parts.length >= 2) {
                                    artist = parts[0];
                                    title = parts.slice(1).join(sep);
                                    break;
                                }
                            }
                        }
                        if (title === 'Live Stream') {
                            title = titleStr;
                        }
                    }
                } else {
                    // Handle string input (legacy format)
                    const infoStr = String(info).trim();
                    if (!infoStr) {
                        console.warn('⚠️ Empty song info received');
                    } else {
                        const separators = [' - ', ' – ', ' — ', ' • ', ' | ', ':'];
                        let separatorUsed = false;

                        for (const sep of separators) {
                            if (infoStr.includes(sep)) {
                                const parts = infoStr.split(sep).map(part => part.trim());
                                if (parts.length >= 2) {
                                    artist = parts[0];
                                    title = parts.slice(1).join(sep);
                                    separatorUsed = true;
                                    break;
                                }
                            }
                        }

                        if (!separatorUsed) {
                            title = infoStr;
                        }
                    }
                }
                if (!artist || !title) return;

                // Check for blacklisted phrases (server status text)
                const lowerArtist = artist.toLowerCase();
                const lowerTitle = title.toLowerCase();
                const blacklist = [
                    'mount point',
                    'stream title',
                    'stream description',
                    'content type',
                ];

                // if (blacklist.some(phrase => lowerArtist.includes(phrase) || lowerTitle.includes(phrase))) {
                //     console.warn('⚠️ Ignoring metadata containing server status text:', { artist, title });
                //     return;
                // }

                // Helper function to strip HTML tags
                const stripHtml = (html) => {
                    if (!html) return '';
                    const tmp = document.createElement("DIV");
                    tmp.innerHTML = html;
                    return tmp.textContent || tmp.innerText || "";
                };

                // Clean up the extracted values
                artist = stripHtml(artist);
                title = stripHtml(title);

                // Further clean up special characters and whitespace
                artist = artist.replace(/&amp;/g, '&').replace(/&lt;/g, '<').replace(/&gt;/g, '>').trim();
                title = title.replace(/&amp;/g, '&').replace(/&lt;/g, '<').replace(/&gt;/g, '>').trim();

                artist = artist.replace(/^[^\w\s]*|[^\w\s]*$/g, '').trim() || 'Unknown Artist';
                title = title.replace(/^[^\w\s]*|[^\w\s]*$/g, '').trim() || 'Unknown Song';

                // Remove time patterns from title (like 02:35, 2.57, etc.)
                title = title.replace(/\s*[0-9]{1,2}[:.][0-9]{2}\s*$/, '').trim();
                title = title.replace(/\s*[0-9]{1,2}[:.][0-9]{2}\s*[0-9]{1,2}[:.][0-9]{2}\s*$/, '')
                    .trim(); // For duration like 02:35:45

                // Remove trailing dashes and separators from title
                title = title.replace(/[\s\-\–\—\|•:]+$/, '').trim();

                // If the title is empty but we have an artist, swap them
                if (!title && artist && artist !== 'Now Playing') {
                    title = artist;
                    artist = 'Now Playing';
                }

                // Fallback if we still don't have a valid title
                if (!title) {
                    title = 'Live Stream';
                }

                console.log('📝 Extracted metadata:', {
                    artist,
                    title
                });

                // Try to persist the metadata if it's valid
                console.log('🔄 Attempting to persist metadata:', {
                    artist,
                    title
                });
                const wasPersisted = persistMetadata(artist, title);
                console.log('📝 Persistence result:', wasPersisted);

                // If this is invalid metadata (loading, error, etc.), try to restore persisted metadata
                if (!wasPersisted) {
                    console.log('🔄 Received invalid metadata, attempting to restore persisted data...');
                    const restored = restorePersistedMetadata();
                    if (restored) {
                        console.log(
                            '✅ Successfully restored persisted metadata, continuing to update Media Session'
                        );
                        // Override the current local artist/title with restored metadata so fetch proceeds
                        artist = restored.artist;
                        title = restored.title;
                        // Intentionally NOT returning here so the rest of the function runs
                    }
                }

                // Check if current display has valid metadata that shouldn't be overridden
                const currentArtist = artistElement.textContent;
                const currentTitle = titleElement.textContent;

                // Detect if the song has actually changed or if this is just a redundant polling event
                const songHasChanged = (currentArtist !== (artist || 'Now Playing')) || (currentTitle !==
                    title);

                // Don't override valid metadata with generic data
                const isGeneric = artist === 'jammin radio' ||
                    title === 'jammin radio' ||
                    artist === 'Live Stream' ||
                    title === 'Live Stream' ||
                    artist === 'Loading...' ||
                    title === 'Loading metadata...' ||
                    artist === 'Player not available' ||
                    title === 'Player not available' ||
                    artist === 'Now Playing' && title === 'Live Stream' ||
                    artist === 'Unknown Artist' ||
                    title === 'Unknown Song';

                // Check if current display has valid metadata (not loading states)
                const hasValidMetadata = currentArtist !== 'Loading...' &&
                    currentTitle !== 'Loading metadata...' &&
                    currentArtist !== 'Now Playing' &&
                    currentTitle !== 'Live Stream' &&
                    currentArtist !== 'Unknown Artist' &&
                    currentTitle !== 'Unknown Song' &&
                    currentArtist.trim() !== '' &&
                    currentTitle.trim() !== '';

                // If we have valid current metadata and incoming is generic, skip update
                if (isGeneric && hasValidMetadata &&
                    currentArtist !== 'jammin radio' &&
                    currentTitle !== 'Live Stream' &&
                    currentArtist !== 'Loading...' &&
                    currentTitle !== 'Loading metadata...' &&
                    currentArtist !== 'Now Playing' &&
                    currentTitle !== 'Live Stream' &&
                    currentArtist !== 'Unknown Artist' &&
                    currentTitle !== 'Unknown Song') {
                    console.log('⚠️ Skipping update - generic metadata would override valid display:', {
                        currentArtist,
                        currentTitle,
                        incomingArtist: artist,
                        incomingTitle: title
                    });
                    return;
                }

                // Only update the DOM if we didn't restore metadata (i.e., we have valid new metadata)
                try {
                    // Add prefixes to the display
                    artistElement.textContent = (artist || 'Now Playing');
                    titleElement.textContent = title;
                    console.log('✅ Updated DOM with song info');
                } catch (e) {
                    console.error('❌ Error updating DOM elements:', e);
                    return;
                }

                const _stored = window._storedMediaMetadata;
                const _mediaSessionIsStale = !_stored ||
                    _stored.title !== (title || 'Live Stream') ||
                    _stored.artist !== (artist || '');

                // CarPlay Fix: If we are hidden but playing, we MUST NOT skip this update.
                const _audioForState = (typeof RadioAudio !== 'undefined') ? RadioAudio.get() : null;
                const _isActuallyPlaying = _audioForState && !_audioForState.paused;
                const _forceUpdate = _isActuallyPlaying && document.hidden;

                if (!songHasChanged && !_mediaSessionIsStale && !_forceUpdate) {
                    return; // Silently skip — both DOM and MediaSession already correct
                }

                // ── Tab ownership ────────────────────────────────────────────────────────
                // Only the active/visible tab (or a hidden tab that is currently PLAYING) drives the MediaSession.
                const _audioOwnership = (typeof RadioAudio !== 'undefined') ? RadioAudio.get() : null;
                const _playingNow = _audioOwnership && !_audioOwnership.paused;

                if (!_isActiveMediaTab && !_playingNow) {
                    console.log('📻 This tab is not the active media owner and is not playing — skipping MediaSession update');
                    return;
                }
                // Claim ownership when we are the one pushing an update
                _claimMediaOwnership();
                // Update the Media Session
                try {

                    if ('mediaSession' in navigator) {
                        // Helper to generate artwork array with all required sizes
                        const getArtworkArray = (src, imageType = 'image/jpeg') => {
                            return [
                                // 1024x1024 is the primary stored size for lock screen display
                                {
                                    src: src,
                                    sizes: '1024x1024',
                                    type: imageType
                                }
                            ];
                        };

                        // Default station logo branding — defined globally at script-load time
                        // (see buildDefaultArtwork / window._defaultArtwork above)

                        // updateSessionMetadata — routes through _commitMediaSession
                        // instead of writing to navigator.mediaSession.metadata directly.
                        // The `immediate` flag controls whether the debouncer flushes now
                        // (used for the final iTunes result) or coalesces (used for interim updates).
                        //
                        // IMPORTANT: document.title is updated HERE (not earlier) so that
                        // text and artwork change atomically on the car/BT display.
                        const updateSessionMetadata = (artwork, immediate = false) => {
                            const processedArtwork = [];

                            if (Array.isArray(artwork)) {
                                for (const item of artwork) {
                                    if (!item.src) continue;
                                    // Force JPEG for OS compatibility
                                    const jpegSrc = item.src
                                        .replace(/\.webp(\?.*)?$/, '.jpeg$1')
                                        .replace(/\.png(\?.*)?$/, '.jpeg$1');
                                    processedArtwork.push({
                                        src: jpegSrc,
                                        sizes: item.sizes || '512x512',
                                        type: 'image/jpeg'
                                    });
                                }
                            }

                            // If no artwork was passed in (iTunes search found nothing or failed),
                            // always use the station default — NEVER re-use a stale previous song art.
                            if (processedArtwork.length === 0) {
                                if (window._defaultArtwork && window._defaultArtwork.length > 0) {
                                    processedArtwork.push(...window._defaultArtwork);

                                    // Inject base64 default art to prevent OS download delay
                                    try {
                                        const cachedDefault = localStorage.getItem(skey('ios_default_artwork'));
                                        if (cachedDefault) {
                                            processedArtwork.unshift({
                                                src: cachedDefault,
                                                sizes: '1024x1024',
                                                type: 'image/jpeg'
                                            });
                                        }
                                    } catch (e) { }
                                }
                            }

                            const metadataObj = {
                                title: title || 'Live Stream',
                                artist: artist || stationName,
                                album: getAlbumTitle(),
                                artwork: processedArtwork.length > 0 ? processedArtwork : (artwork || [])
                            };

                            console.log('🎵 updateSessionMetadata queuing:', metadataObj.title, '-', metadataObj.artist,
                                '| artwork sources:', processedArtwork.length, '| immediate:', immediate);

                            // Update document.title atomically with MediaSession commit
                            // so car/BT displays see text + artwork change together.
                            try {
                                const _stName = @json(config('app.name')) || '';
                                const _t = metadataObj.title;
                                const _a = metadataObj.artist;
                                const _isGenericTitle = !_t || _t === 'Live Stream' || _t === 'Unknown Song' || _t === 'Loading metadata...' || _t === 'Loading...';
                                if (_t && !_isGenericTitle) {
                                    document.title = `${_t} - ${_a} | ${_stName}`;
                                } else {
                                    document.title = _stName;
                                }
                                console.log('📌 Document title updated atomically with MediaSession:', document.title);
                            } catch (e) {
                                console.warn('⚠️ Could not update document title:', e);
                            }

                            // Route through debounced single-point-of-truth
                            window._commitMediaSession(metadataObj, immediate);
                        };

                        // 1. Try to use provided artwork first
                        if (typeof info === 'object' && info !== null && (info.artwork || info.cover)) {
                            let artUrl = info.artwork || info.cover;
                            // Check if it's not the default one
                            if (!String(artUrl).includes(
                                '{{ env('DEFAULT_ARTWORK_PATH', 'images/alexaicon.jpg') }}'.split('/')
                                    .pop())) {
                                let finalArtwork = [];
                                if (Array.isArray(artUrl)) {
                                    finalArtwork = artUrl;
                                } else if (typeof artUrl === 'string' && artUrl.startsWith('http')) {
                                    finalArtwork = [{
                                        src: artUrl,
                                        sizes: '512x512',
                                        type: 'image/jpeg'
                                    }];
                                }

                                if (finalArtwork.length > 0) {
                                    // Clear old artwork and store new
                                    localStorage.removeItem(skey('ios_current_artwork'));
                                    window.storeArtworkForIOS(finalArtwork[0].src);

                                    // Single update with artwork — immediate flush since we have final art
                                    updateSessionMetadata(finalArtwork, true);
                                    // NOTE: Do NOT also call updateArtwork — that would be a duplicate
                                    return; // Done
                                }
                            }
                        }

                        // ── Immediate MediaSession write with default artwork ──────────
                        // Push title/artist/album to MediaSession instantly so lock screen
                        // and car displays update without waiting for the iTunes artwork
                        // search.  When iTunes resolves, artwork will be updated seamlessly
                        // via MediaMetadata mutation (no flicker on Android/iOS).
                        if (songHasChanged) {
                            // Persist title+artist to localStorage for cross-tab/page sync
                            localStorage.setItem(skey('current_title'), title || '');
                            localStorage.setItem(skey('current_artist'), artist || '');
                            localStorage.setItem(skey('active_tab'), _tabSessionId);
                            console.log('📋 Song changed — pushing title/artist immediately, iTunes artwork will follow');
                            updateSessionMetadata([], true);
                        }

                        // ── Clear stale artwork cache for new song ────────────────────
                        if (songHasChanged || _mediaSessionIsStale) {
                            localStorage.removeItem(skey('ios_current_artwork'));
                            window._storedArtworkSrc = null;
                            console.log('🧹 Cleared stale artwork cache for new song:', title);
                        }

                        // ── Document title update is DEFERRED ─────────────────────────────
                        // document.title is now updated inside updateSessionMetadata()
                        // so it changes atomically with the MediaSession metadata commit.
                        // This prevents car/BT displays from picking up a title change
                        // (showing new text) before artwork has been resolved.

                        // ── iTunes artwork search ───────────────────────────────────────────
                        // NOTE: We do NOT wipe existing artwork here.
                        // The existing artwork (from ios_current_artwork or previous song)

                        // stays visible while the async iTunes search runs.
                        // Default station artwork is ONLY applied at the two end-paths below:
                        //   • All 5 strategies exhausted with no match
                        //   • Unexpected fetch error
                        // This prevents the default logo from flashing on every song change.


                        if (artist && title && artist !== 'Now Playing' && title !== 'Live Stream') {


                            (async () => {

                                const cleanSearchTerm = (str) => {
                                    if (!str) return '';
                                    return str
                                        .replace(/\s*(?:feat\.?|ft\.?|f\.\/|featuring|with|x|&)\s+.*$/gi, '')
                                        .replace(/[\(\[\{].*?[\)\]\}]/g, '')
                                        .replace(/\s+-.*$/, '')
                                        .replace(/^[^a-zA-Z0-9]+|[^a-zA-Z0-9]+$/g, '')
                                        .replace(/\s{2,}/g, ' ')
                                        .trim();
                                };

                                function getHighResArtwork(result) {
                                    const url = result.artworkUrl100 || result.artworkUrl60 || result.artworkUrl30;
                                    if (!url) return null;
                                    const makeUrl = (size) => url.replace(/\b\d{2,4}x\d{2,4}bb\b/, `${size}x${size}bb`);
                                    return { jpg2048: makeUrl(2048), jpg1024: makeUrl(1024) };
                                }

                                const normalize = (str) => {
                                    if (!str) return '';
                                    const noDiacritics = (str.normalize) ?
                                        str.normalize('NFD').replace(/\p{Diacritic}/gu, '') : str;
                                    return noDiacritics
                                        .toLowerCase()
                                        .replace(/[''`]/g, '')
                                        .replace(/[^a-z0-9\s]/g, ' ')
                                        .replace(/\s+/g, ' ')
                                        .trim();
                                };

                                const _artistStopwords = new Set([
                                    'the', 'a', 'an', 'of', 'in', 'on', 'at', 'to', 'by', 'and', 'or', 'ft', 'feat'
                                ]);

                                const artistWords = (str) => new Set(
                                    normalize(str).split(/\s+/).filter(w => w.length > 1 && !_artistStopwords.has(w))
                                );

                                const wordOverlap = (a, b) => {
                                    const wa = new Set(a.split(/\s+/).filter(w => w.length > 1));
                                    const wb = new Set(b.split(/\s+/).filter(w => w.length > 1));
                                    if (wa.size === 0 || wb.size === 0) return 0;
                                    let matches = 0;
                                    wa.forEach(w => { if (wb.has(w)) matches++; });
                                    return matches / Math.max(wa.size, wb.size);
                                };

                                const artistMatches = (resultArtistName, knownArtist) => {
                                    const knownWords = artistWords(knownArtist);
                                    if (knownWords.size === 0) {
                                        return normalize(resultArtistName).includes(normalize(knownArtist));
                                    }
                                    const resultNorm = normalize(resultArtistName);
                                    if (knownWords.size === 1) {
                                        const knownWord = [...knownWords][0];
                                        if (resultNorm.includes(knownWord)) return true;
                                    }
                                    const resultWords = artistWords(resultArtistName);
                                    let matched = 0;
                                    knownWords.forEach(w => { if (resultWords.has(w)) matched++; });
                                    return matched > 0;
                                };

                                function pickBestResult(results, searchTitle, searchArtist) {
                                    if (!results || results.length === 0) return null;

                                    const normTitle = normalize(searchTitle);
                                    const normArtist = normalize(searchArtist);

                                    const validResults = results.filter(r =>
                                        r.trackName && (r.artworkUrl100 || r.artworkUrl60)
                                    );
                                    if (validResults.length === 0) return null;

                                    // Level 1: Exact title + exact artist
                                    const exactBoth = validResults.find(r =>
                                        normalize(r.trackName) === normTitle &&
                                        normalize(r.artistName) === normArtist
                                    );
                                    if (exactBoth) { console.log('🎯 Match level 1: exact title + exact artist'); return exactBoth; }

                                    // Level 2: Exact title + partial artist
                                    const exactTitlePartialArtist = validResults.find(r =>
                                        normalize(r.trackName) === normTitle &&
                                        normArtist.length > 1 &&
                                        (normalize(r.artistName).includes(normArtist) ||
                                            normArtist.includes(normalize(r.artistName)))
                                    );
                                    if (exactTitlePartialArtist) { console.log('🎯 Match level 2: exact title, partial artist'); return exactTitlePartialArtist; }

                                    // Level 3: High word-overlap on BOTH title AND artist
                                    const highOverlapBoth = validResults.find(r =>
                                        normArtist.length > 1 &&
                                        wordOverlap(normalize(r.trackName), normTitle) >= 0.8 &&
                                        wordOverlap(normalize(r.artistName), normArtist) >= 0.6
                                    );
                                    if (highOverlapBoth) { console.log('🎯 Match level 3: word-overlap title+artist'); return highOverlapBoth; }

                                    console.log(`⚠️ pickBestResult: no match for "${searchTitle}" by "${searchArtist}"`);
                                    return null;
                                }

                                async function itunesSearch(query) {
                                    try {
                                        const url = `/proxy/itunes-search?term=${encodeURIComponent(query)}`;
                                        console.log('🎵 iTunes search (via proxy):', query);
                                        const response = await fetch(url);
                                        if (!response.ok) throw new Error(`HTTP ${response.status}`);
                                        return await response.json();
                                    } catch (e) {
                                        console.warn(`⚠️ iTunes search failed for "${query}":`, e.message);
                                        return null;
                                    }
                                }

                                try {
                                    let artworkUrl = null;

                                    const cleanTitle = cleanSearchTerm(title);
                                    const cleanArtist = cleanSearchTerm(artist);

                                    // ── Strategy 1: Artist + Title ─────────────────────────────────────────
                                    if (!artworkUrl && cleanArtist.length >= 2 && cleanTitle.length >= 2) {
                                        const query = `${cleanArtist} ${cleanTitle}`;
                                        console.log(`🔍 Strategy 1 — Artist + Title: "${query}"`);
                                        const data = await itunesSearch(query);
                                        if (data?.results?.length > 0) {
                                            const best = pickBestResult(data.results, cleanTitle, cleanArtist);
                                            if (best && artistMatches(best.artistName, cleanArtist)) {
                                                console.log(`🎯 Strategy 1 match: "${best.trackName}" by "${best.artistName}"`);
                                                artworkUrl = getHighResArtwork(best);
                                                if (best.trackTimeMillis) {
                                                    window._currentSongDuration = best.trackTimeMillis / 1000;
                                                    window.updatePositionState(window._currentSongElapsed, window._currentSongDuration);
                                                }
                                            } else {
                                                console.log(`⚠️ Strategy 1: no artist-validated match — skipping`);
                                            }
                                        }
                                    }

                                    // ── Strategy 2: Title + Artist (reversed) ──────────────────────────────
                                    if (!artworkUrl && cleanTitle.length >= 2 && cleanArtist.length >= 2) {
                                        const query = `${cleanTitle} ${cleanArtist}`;
                                        console.log(`🔍 Strategy 2 — Title + Artist (reversed): "${query}"`);
                                        const data = await itunesSearch(query);
                                        if (data?.results?.length > 0) {
                                            const best = pickBestResult(data.results, cleanTitle, cleanArtist);
                                            if (best && artistMatches(best.artistName, cleanArtist)) {
                                                console.log(`🎯 Strategy 2 match: "${best.trackName}" by "${best.artistName}"`);
                                                artworkUrl = getHighResArtwork(best);
                                                if (best.trackTimeMillis) {
                                                    window._currentSongDuration = best.trackTimeMillis / 1000;
                                                    window.updatePositionState(window._currentSongElapsed, window._currentSongDuration);
                                                }
                                            } else {
                                                console.log(`⚠️ Strategy 2: no artist-validated match — skipping`);
                                            }
                                        }
                                    }

                                    console.log(artworkUrl ?
                                        `✅ iTunes artwork found` :
                                        `❌ Both strategies exhausted — will use default artwork`
                                    );

                                    if (artworkUrl) {
                                        let finalArtworkArray = [];

                                        if (artworkUrl.jpg2048) {
                                            finalArtworkArray.push({ src: artworkUrl.jpg2048, sizes: '2048x2048', type: 'image/jpeg' });
                                        }

                                        if (artworkUrl.jpg1024) {
                                            finalArtworkArray.push({ src: artworkUrl.jpg1024, sizes: '1024x1024', type: 'image/jpeg' });
                                            localStorage.removeItem(skey('ios_current_artwork'));
                                            const base64Art = await window.storeArtworkForIOS(artworkUrl.jpg1024);
                                            if (base64Art) {
                                                // Unshift puts Base64 first so the car uses it instantly without an HTTP download
                                                finalArtworkArray.unshift({ src: base64Art, sizes: '1024x1024', type: 'image/jpeg' });
                                            }
                                        } else if (artworkUrl.jpg2048) {
                                            finalArtworkArray.push({ src: artworkUrl.jpg2048, sizes: '2048x2048', type: 'image/jpeg' });
                                            localStorage.removeItem(skey('ios_current_artwork'));
                                            const base64Art = await window.storeArtworkForIOS(artworkUrl.jpg2048);
                                            if (base64Art) {
                                                finalArtworkArray.unshift({ src: base64Art, sizes: '1024x1024', type: 'image/jpeg' });
                                            }
                                        }

                                        // IMMEDIATE flush — this is the final/authoritative artwork
                                        // The debouncer will cancel any pending default-artwork write
                                        updateSessionMetadata(finalArtworkArray, true);
                                        // NOTE: Do NOT also call updateArtwork — duplicate update
                                        return;
                                    } else {
                                        console.warn('⚠️ No iTunes artwork found after both strategies');
                                    }

                                } catch (e) {
                                    console.warn('⚠️ iTunes artwork fetch failed unexpectedly:', e);
                                    localStorage.removeItem(skey('ios_current_artwork'));
                                    window._storedArtworkSrc = null;
                                    updateSessionMetadata([], true);
                                }

                                // Both strategies exhausted — show default station artwork (immediate)
                                localStorage.removeItem(skey('ios_current_artwork'));
                                window._storedArtworkSrc = null;
                                console.log('🏙️ No iTunes artwork found — displaying default station artwork');
                                updateSessionMetadata([], true);

                            })();
                        } else {
                            // No valid artist/title to search iTunes — apply default immediately
                            localStorage.removeItem(skey('ios_current_artwork'));
                            window._storedArtworkSrc = null;
                            console.log('🏙️ No artist/title to search — displaying default station artwork');
                            updateSessionMetadata([], true);
                        }
                    } else {
                        console.warn('⚠️ Media Session API not supported in this browser');
                    }
                } catch (e) {
                    console.error('❌ Error updating document title or Media Session:', e);
                }

                // Make sure the player is visible
                if (stickyPlayer) {
                    stickyPlayer.style.display = 'flex';
                    console.log('👁️ Ensured player is visible');
                }

                console.log('✅ Song info update complete');
            }

            // Expose as global so window.updateMediaSessionWithArtwork can delegate here
            window.updateSongInfo = updateSongInfo;

            // Function to get the next metadata URL to try
            function getNextMetadataUrl() {
                currentEndpointIndex++;
                if (currentEndpointIndex >= metadataEndpoints.length) {
                    currentEndpointIndex = 0;
                    currentProxyIndex = (currentProxyIndex + 1) % corsProxies.length;
                }

                const baseUrl = streamUrl.replace(/\/$/, ''); // Remove trailing slash if exists
                const endpoint = metadataEndpoints[currentEndpointIndex];
                const proxy = corsProxies[currentProxyIndex];

                // Special handling for corsproxy.io which needs the URL as a query parameter
                if (proxy.includes('corsproxy.io')) {
                    return proxy + encodeURIComponent(baseUrl + endpoint);
                }

                return proxy + baseUrl + endpoint;
            }

            // Test if we can access fetchMetadata
            console.log('Defining fetchMetadata function');

            // Test if fetchMetadata is callable
            try {
                console.log('Testing fetchMetadata callable:', typeof fetchMetadata === 'function');
                if (typeof fetchMetadata === 'function') {
                    console.log('Calling fetchMetadata directly...');
                    fetchMetadata().then(() => {
                        console.log('fetchMetadata call completed');
                    }).catch(err => {
                        console.error('Error in fetchMetadata:', err);
                    });
                } else {
                    console.error('fetchMetadata is not a function');
                }
            } catch (e) {
                console.error('Error testing fetchMetadata:', e);
            }

            // ── Global artwork + MediaSession update function ──────────────────────────────
            // Called from: .old-style code, SSE metadata, restorePersistedMetadata, etc.
            // Delegates iTuens search to updateSongInfo() which has the full 4-strategy logic.
            window.updateMediaSessionWithArtwork = function (artist, title, providedArtwork = null) {
                console.log('🎨 updateMediaSessionWithArtwork called for:', { artist, title });

                try {
                    const appName = @json(config('app.name'));
                    const stationName = appName;

                    // document.title is now deferred to updateSessionMetadata() so it
                    // changes atomically with artwork on car/BT displays.

                    if (!('mediaSession' in navigator)) return;

                    // Default station artwork — always used as fallback
                    const baseUrl = window.location.origin;
                    const defaultArtwork = (typeof buildDefaultArtwork === 'function')
                        ? buildDefaultArtwork()
                        : [
                            { src: baseUrl + '/{{ env('DEFAULT_ARTWORK_PATH', 'images/alexaicon.jpg') }}', sizes: '512x512', type: 'image/jpeg' },
                            { src: baseUrl + '/{{ env('DEFAULT_ARTWORK_PATH', 'images/alexaicon.jpg') }}', sizes: '1024x1024', type: 'image/jpeg' },
                            { src: baseUrl + '/{{ env('DEFAULT_ARTWORK_PATH', 'images/alexaicon.jpg') }}', sizes: '2048x2048', type: 'image/jpeg' }
                        ];

                    // Inner helper: push artwork + metadata through debounced commit
                    const commitToMediaSession = (artwork, immediate = false) => {
                        const metadataObj = {
                            title: title || 'Live Stream',
                            artist: artist || stationName,
                            album: getAlbumTitle(),
                            artwork: artwork
                        };
                        console.log('🎵 [updateMediaSessionWithArtwork] Queuing metadata:', metadataObj.title, '| immediate:', immediate);
                        window._commitMediaSession(metadataObj, immediate);
                    };

                    // 1. Use provided artwork if available (e.g. from stream metadata)
                    if (providedArtwork) {
                        let art = providedArtwork;
                        const isDefault = String(art).includes('alexaicon') || String(art).includes('alexaicontrans');
                        if (!isDefault) {
                            const artArr = Array.isArray(art) ? art
                                : typeof art === 'string' && art.startsWith('http')
                                    ? [{ src: art, sizes: '512x512', type: 'image/jpeg' }]
                                    : [];
                            if (artArr.length > 0) {
                                // Immediate flush — we have final artwork
                                commitToMediaSession(artArr, true);
                                // Also push through updateSongInfo for full DOM update
                                if (typeof window.updateSongInfo === 'function') {
                                    window.updateSongInfo({ artist, title, song: title, artwork: art });
                                }
                                return;
                            }
                        }
                    }

                    // 2. DO NOT set default artwork here — updateSongInfo will wait
                    //    for iTunes to resolve before making the ONLY MediaSession write.

                    // 3. Kick off the full iTunes artwork search via updateSongInfo.
                    //    updateSongInfo will NOT write to MediaSession until iTunes resolves:
                    //      • iTunes artwork found → single write with real artwork
                    //      • iTunes failed → single write with default artwork
                    if (artist && title && artist !== stationName &&
                        title !== 'Live Stream' && title !== 'Unknown Song') {
                        if (typeof window.updateSongInfo === 'function') {
                            window.updateSongInfo({ artist, title, song: title });
                        }
                    } else {
                        // No searchable metadata — commit default artwork now
                        commitToMediaSession(defaultArtwork, true);
                    }

                } catch (e) {
                    console.error('❌ updateMediaSessionWithArtwork error:', e);
                }
            };

            // Initialize Media Session API — routes through _commitMediaSession
            function updateMediaSession(meta) {
                if (!('mediaSession' in navigator)) {
                    console.log('⚠️ Media Session API not supported');
                    return;
                }

                const baseUrl = window.location.origin;
                const defaultArtwork = (typeof buildDefaultArtwork === 'function') ?
                    buildDefaultArtwork() : [{
                        src: baseUrl + '/' + '{{ env('DEFAULT_ARTWORK_PATH', 'images/alexaicon.jpg') }}',
                        sizes: '128x128',
                        type: 'image/jpeg'
                    }];

                let artwork = defaultArtwork;

                if (meta && (meta.artwork || meta.cover) && !String(meta.artwork || meta.cover).includes(
                    '{{ env('DEFAULT_ARTWORK_PATH', 'images/alexaicon.jpg') }}'.split('/').pop())) {
                    if (Array.isArray(meta.artwork)) {
                        artwork = meta.artwork;
                    } else {
                        artwork = [{
                            src: meta.artwork || meta.cover,
                            sizes: '300x300',
                            type: 'image/jpeg'
                        },
                        {
                            src: meta.artwork || meta.cover,
                            sizes: '128x128',
                            type: 'image/jpeg'
                        },
                        {
                            src: meta.artwork || meta.cover,
                            sizes: '256x256',
                            type: 'image/jpeg'
                        },
                        ...defaultArtwork
                        ];
                    }
                }

                const stationName = '{{ config('app.name') }}';
                const songTitle = meta ? (meta.song || meta.title || 'Live Stream') : 'Live Stream';
                const artistName = meta ? (meta.artist || stationName) : stationName;

                const metadataObj = {
                    title: songTitle,
                    artist: artistName,
                    album: getAlbumTitle(),
                    artwork: artwork
                };

                console.log('🎵 updateMediaSession queuing:', metadataObj);

                // Route through debounced single-point-of-truth
                window._commitMediaSession(metadataObj, true);

                if (typeof window.configureLiveMediaSessionActions === 'function') {
                    window.configureLiveMediaSessionActions();
                }

                console.log('✅ Media Session update queued successfully');
            }

            // ────────────────────────────────────────────────────────
            // Thin play / pause helpers.
            // These simply delegate to the real implementation in
            // scripts-core-player.blade.php (toggleLiveAudio) so that all code
            // paths — play button, metadata script, BT resume — share exactly
            // one implementation and never get out of sync.
            // ────────────────────────────────────────────────────────
            window.doPlay = function (opts) {
                // If audio is already playing, nothing to do.
                const _a = (typeof RadioAudio !== 'undefined') ? RadioAudio.get() : null;
                if (_a && !_a.paused) {
                    console.log('▶️ doPlay: already playing');
                    return;
                }
                console.log('▶️ doPlay → toggleLiveAudio()');
                if (typeof window.toggleLiveAudio === 'function') {
                    window.toggleLiveAudio();
                } else {
                    // Fallback if toggleLiveAudio not yet available
                    if (_a) {
                        userPaused = false;
                        systemPaused = false;
                        _a.play().catch(e => console.warn('[doPlay fallback]', e.message));
                    }
                }
            };

            window.doPause = function (opts) {
                // If audio is already paused and user-initiated, nothing to do.
                const _a = (typeof RadioAudio !== 'undefined') ? RadioAudio.get() : null;
                if (_a && _a.paused) {
                    console.log('⏸️ doPause: already paused');
                    return;
                }
                console.log('⏸️ doPause → toggleLiveAudio()');
                if (typeof window.toggleLiveAudio === 'function') {
                    window.toggleLiveAudio();
                } else {
                    // Fallback
                    if (_a && !_a.paused) {
                        userPaused = true;
                        _a.pause();
                    }
                }
            };
            // ────────────────────────────────────────────────────────

            // Call immediately on load - restore persisted metadata artwork if available
            // WITH THIS:
            (function () {
                if (!('mediaSession' in navigator)) return;

                // Check for persisted metadata to trigger immediate iTunes search
                const stored = localStorage.getItem(skey('current_song_metadata'));
                if (stored) {
                    try {
                        const m = JSON.parse(stored);
                        if (m.artist && m.title && Date.now() - m.timestamp < 600000) {
                            console.log(
                                '🖼️ Page load: Found recent metadata, triggering iTunes search...');
                            // If updateSongInfo is available globally, use it to start the fetch
                            if (typeof window.updateSongInfo === 'function') {
                                window.updateSongInfo({
                                    artist: m.artist,
                                    title: m.title,
                                    song: m.title
                                });
                            } else {
                                // Fallback if not initialized yet
                                setTimeout(() => {
                                    if (typeof window.updateSongInfo === 'function') {
                                        window.updateSongInfo({
                                            artist: m.artist,
                                            title: m.title,
                                            song: m.title
                                        });
                                    }
                                }, 500);
                            }
                            return;
                        }
                    } catch (e) { }
                }

                console.log('🖼️ Page load: No recent metadata found. MediaSession will populate on play.');
            })();
            // Function set up Radiomast SSE metadata streaming
            function setupRadiomastMetadata() {
                console.log('🎵 Setting up Radiomast SSE metadata...');

                try {
                    // Close any existing connection first
                    if (window.radiomastEventSource) {
                        window.radiomastEventSource.close();
                        window.radiomastEventSource = null;
                    }

                    const metadataUrl = streamUrl + '/metadata';
                    console.log('Connecting to metadata endpoint:', metadataUrl);

                    const eventSource = new EventSource(metadataUrl);
                    let reconnectAttempts = 0;
                    const maxReconnectAttempts = 3;

                    eventSource.onopen = function () {
                        console.log('✅ Connected to Radiomast metadata stream');
                        reconnectAttempts = 0; // Reset on successful connection
                    };

                    eventSource.onmessage = function (event) {
                        try {


                            console.log('📡 Received metadata event:', event.data);

                            const metadata = JSON.parse(event.data);
                            const artistTitle = metadata['metadata'];

                            // ── Extract song timing from SSE payload ──────────────────────
                            // Radiomast sends elapsed/duration in seconds alongside the
                            // metadata string. We read them here so we can forward them to
                            // navigator.mediaSession.setPositionState() for widget/car display.
                            const sseElapsed = (typeof metadata['elapsed'] === 'number') ? metadata['elapsed'] :
                                (typeof metadata['elapsed'] === 'string') ? parseFloat(metadata['elapsed']) : null;
                            let sseDurationRaw = (typeof metadata['duration'] === 'number') ? metadata['duration'] :
                                (typeof metadata['duration'] === 'string') ? parseFloat(metadata['duration']) :
                                    (typeof metadata['song_length'] === 'number') ? metadata['song_length'] :
                                        (typeof metadata['song_length'] === 'string') ? parseFloat(metadata['song_length']) : null;

                            let sseDuration = sseDurationRaw;
                            if (sseDuration > 1000000000) {
                                // It's a timestamp, not a duration. Ignore it so iTunes can fill it.
                                sseDuration = null;
                            } else if (sseDuration > 36000) {
                                // Larger than 10 hours? Likely milliseconds, convert to seconds
                                sseDuration = sseDuration / 1000;
                            }

                            if (sseDuration > 0) {
                                console.log(`📡 SSE timing: elapsed=${sseElapsed}s  duration=${sseDuration}s`);
                            }

                            if (artistTitle) {
                                console.log('🎵 Now Playing:', artistTitle);

                                let artist = '{{ config('app.name') }}';
                                let title = artistTitle;

                                // Parse the metadata string
                                if (artistTitle.includes('-')) {
                                    const parts = artistTitle.split('-');
                                    if (parts.length >= 2) {
                                        title = parts[0].trim(); // First part is TITLE
                                        artist = parts[1].trim(); // Second part is ARTIST
                                    }
                                }

                                // Check if this is generic metadata
                                const stationLower = '{{ strtolower(config('app.name')) }}';
                                const isGeneric = title.toLowerCase() === stationLower ||
                                    artist === 'Live Stream' ||
                                    title === 'Live Stream' ||
                                    artist.toLowerCase() === stationLower;

                                if (isGeneric) {
                                    console.log(
                                        '⚠️ Received generic metadata from SSE, checking for persisted data...'
                                    );
                                    if (restorePersistedMetadata()) {
                                        console.log(
                                            '✅ Restored persisted metadata, ignoring generic SSE data');
                                        // Still update position state even on generic metadata
                                        if (sseDuration > 0) {
                                            window.updatePositionState(sseElapsed, sseDuration);
                                        }
                                        return;
                                    }
                                }

                                // ── Always update metadata on song change ─────────────────
                                // Update MediaSession, document.title, DOM, and trigger
                                // iTunes artwork search regardless of play/pause state.
                                // _commitMediaSession preserves playbackState='paused'
                                // after each write, preventing unwanted auto-resume.
                                updateSongInfo({
                                    title: title,
                                    artist: artist,
                                    song: title,
                                    artwork: metadata.artwork || metadata.cover || null
                                });

                                // ── Forward song timing to Media Session ─────────────────
                                if (sseDuration > 0) {
                                    window._currentSongDuration = sseDuration;
                                    window._currentSongElapsed = sseElapsed >= 0 ? sseElapsed : 0;
                                    window._songTimingTimestamp = Date.now();
                                    window.updatePositionState(window._currentSongElapsed, window._currentSongDuration);
                                } else {
                                    window._currentSongDuration = 0;
                                }

                                console.log('✅ SSE metadata processed and Media Session updated');
                            }
                        } catch (error) {
                            console.error('❌ Error parsing metadata:', error);
                        }
                    };

                    eventSource.onerror = function (error) {
                        console.error('❌ EventSource error:', error);

                        // Close the connection
                        eventSource.close();

                        reconnectAttempts++;
                        console.log(`Reconnection attempt ${reconnectAttempts} of ${maxReconnectAttempts}`);

                        if (reconnectAttempts < maxReconnectAttempts) {
                            console.log(`Attempting to reconnect in ${5 * reconnectAttempts} seconds...`);
                            setTimeout(setupRadiomastMetadata, 5000 * reconnectAttempts);
                        } else {
                            console.warn(
                                '⚠️ Max reconnection attempts reached. Switching to fallback method.');
                            // Switch to the old polling method
                            startFallbackPolling();
                        }
                    };

                    // Store the eventSource for cleanup
                    window.radiomastEventSource = eventSource;

                } catch (error) {
                    console.error('❌ Failed to setup Radiomast metadata:', error);
                    // Immediately fallback to old method
                    startFallbackPolling();
                }
            }

            // New function for fallback polling
            function startFallbackPolling() {
                console.log('🔄 Starting fallback metadata polling...');

                // Clear any existing interval
                if (window.fallbackMetadataInterval) {
                    clearInterval(window.fallbackMetadataInterval);
                }

                // Initial fetch
                fetchMetadata().catch(console.error);

                // Poll every 30 seconds
                window.fallbackMetadataInterval = setInterval(() => {
                    fetchMetadata().catch(console.error);
                }, 30000);
            }

            // Function to fetch metadata from the stream (fallback method)
            async function fetchMetadata() {
                console.log('fetchMetadata called with streamUrl:', streamUrl);

                if (!streamUrl) {
                    console.error('❌ Stream URL is empty or undefined');
                    updateSongInfo('Stream URL not configured');
                    return;
                }

                // If using a blob URL, try to get the original stream URL
                let metadataUrl = streamUrl;
                if (streamUrl.startsWith('blob:')) {
                    // Try to get the original stream URL from the audio element
                    if (audio && audio.src && !audio.src.startsWith('blob:')) {
                        metadataUrl = audio.src;
                    } else {
                        console.log('Using fallback stream URL for metadata');
                        metadataUrl = 'https://streams.radiomast.io/rc101/metadata';
                    }
                }

                console.log('🔍 Starting metadata fetch from:', streamUrl);

                // Try to restore persisted metadata instead of showing loading state
                if (!restorePersistedMetadata()) {
                    updateSongInfo('Loading metadata...');
                }

                try {
                    // 1. First try: Server-side endpoint with direct stream URL
                    console.log('1️⃣ Trying server-side metadata endpoint with stream URL:', metadataUrl);
                    try {
                        const response = await fetch(
                            `/admin/api/stream-metadata?streamUrl=${encodeURIComponent(metadataUrl)}`, {
                            method: 'GET',
                            headers: {
                                'Accept': 'application/json',
                                'Cache-Control': 'no-cache',
                                'Pragma': 'no-cache',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            cache: 'no-store',
                            credentials: 'same-origin'
                        });

                        console.log('📡 Server response status:', response.status, response.statusText);

                        if (response.ok) {
                            const data = await response.json();
                            console.log('✅ Server metadata response:', data);

                            if (data && (data.title || data.artist || data.song)) {
                                // Pass the entire data object to handle structured metadata

                                const _ssAudio = (typeof RadioAudio !== 'undefined') ? RadioAudio.get() : null;
                                const _ssUserPaused = window._smartPlayer ? window._smartPlayer.userPaused : false;
                                const _ssSystemPaused = (typeof systemPaused !== 'undefined') ? systemPaused : false;
                                const _ssPaused = (_ssAudio && _ssAudio.paused) || _ssUserPaused || _ssSystemPaused;

                                if (_ssPaused) {
                                    console.log('⏸️ Server metadata while paused — DOM update only');
                                    const _artEl = document.getElementById('current-artist');
                                    const _titEl = document.getElementById('current-title');
                                    if (_artEl) _artEl.textContent = data.artist || '';
                                    if (_titEl) _titEl.textContent = data.title || data.song || '';
                                    if ('mediaSession' in navigator) navigator.mediaSession.playbackState = 'paused';
                                } else {
                                    updateSongInfo({
                                        artist: data.artist,
                                        song: data.song,
                                        title: data.title
                                    });
                                }
                                console.log('🎵 Updated from server metadata:', data);
                                return true;
                            } else {
                                console.warn('⚠️ No valid metadata in server response');
                            }
                        } else {
                            console.warn(
                                `⚠️ Server responded with ${response.status}: ${response.statusText}`);
                        }
                    } catch (serverError) {
                        console.warn('⚠️ Server metadata fetch failed:', serverError);
                    }


                    // 3. Third try: Direct stream metadata endpoints (Icecast/Shoutcast)
                    console.log('3️⃣ Trying direct stream metadata endpoints...');

                    const endpoints = [
                        '/status-json.xsl',
                        '/status-json',
                        '/status.xsl',
                        '/status',
                        '/7.html',
                        '/currentsong',
                        '/now_playing',
                        '/streaminfo',
                        '/nowplaying',
                        '/api/nowplaying'
                    ];

                    for (const endpoint of endpoints) {
                        try {
                            const url = new URL(streamUrl);
                            const metadataUrl = `${url.origin}${endpoint}`;

                            console.log(`   🔄 Trying endpoint: ${metadataUrl}`);

                            const response = await fetch(metadataUrl, {
                                method: 'GET',
                                cache: 'no-store',
                                headers: {
                                    'Accept': 'application/json, text/plain, */*',
                                    'X-Requested-With': 'XMLHttpRequest'
                                }
                            });

                            if (!response.ok) {
                                console.log(`   ⚠️ Endpoint ${endpoint} returned ${response.status}`);
                                continue;
                            }

                            const contentType = response.headers.get('content-type') || '';
                            let data;

                            try {
                                if (contentType.includes('application/json')) {
                                    data = await response.json();
                                } else {
                                    const text = await response.text();
                                    // Try to parse as JSON even if content-type doesn't match
                                    try {
                                        data = JSON.parse(text);
                                    } catch {
                                        data = text;
                                    }
                                }

                                console.log(`   ✅ Got response from ${endpoint}:`, data);

                                // Try to extract title from common formats
                                let title = '';

                                // Check for common JSON formats
                                if (data && typeof data === 'object') {
                                    const source = data.icestats?.source ||
                                        data.shoutcast?.source ||
                                        data.source ||
                                        (data.icestats?.sources && data.icestats.sources[0]) ||
                                        (data.shoutcast?.sources && data.shoutcast.sources[0]);

                                    title = source?.title ||
                                        data.now_playing?.song?.text ||
                                        data.now_playing?.song?.title ||
                                        data.songtitle ||
                                        data.title ||
                                        data.now_playing?.title ||
                                        data.stream_title;
                                }
                                // Check for SHOUTcast 7.html format (CSV)
                                else if (typeof data === 'string' && data.includes(',')) {
                                    const parts = data.split(',');
                                    if (parts.length >= 7) {
                                        title = parts[6].trim();
                                    }
                                }
                                // Check for plain text or HTML response
                                else if (typeof data === 'string') {
                                    const rawText = data.trim();

                                    // Check if it's HTML
                                    if (rawText.includes('<') && rawText.includes('>')) {
                                        // Try to extract song from Icecast status page pattern
                                        // Look for: <td class="streamdata">Song Name</td>
                                        // or: Current Song:</td><td class="streamdata">Song Name</td>
                                        const songMatch = rawText.match(
                                            /Current Song:.*?<td[^>]*>(.*?)<\/td>/i) ||
                                            rawText.match(/<td[^>]*class="?streamdata"?[^>]*>(.*?)<\/td>/i);

                                        if (songMatch && songMatch[1]) {
                                            let extracted = songMatch[1].replace(/<[^>]+>/g, '').trim();
                                            if (extracted && extracted !== 'Content Type' && extracted !==
                                                'Mount Point') {
                                                title = extracted;
                                                console.log('   🎵 Extracted song match from HTML:', title);
                                            }
                                        }
                                    }
                                    // If not HTML, check if it's a simple text string
                                    else if (rawText.length > 0) {
                                        // Aggressive cleaning for concatenated text like "Song TitleMount Point..."
                                        let cleanedTitle = rawText;

                                        // Split at common server labels that immediately follow the song title
                                        const delimiters = [
                                            'Mount Point',
                                            'Stream Title',
                                            'Stream Description',
                                            'Content Type',
                                            'Bitrate',
                                            'Current Listeners'
                                        ];

                                        for (const delimiter of delimiters) {
                                            if (cleanedTitle.includes(delimiter)) {
                                                cleanedTitle = cleanedTitle.split(delimiter)[0].trim();
                                            }
                                        }

                                        // Clean up "Current Song:" prefix if present
                                        cleanedTitle = cleanedTitle.replace(/^Current Song:\s*/i, '');

                                        // Final validation
                                        if (cleanedTitle && cleanedTitle.length < 100 &&
                                            !delimiters.some(d => cleanedTitle.toLowerCase().includes(d
                                                .toLowerCase()))) {
                                            title = cleanedTitle;
                                            console.log('   🎵 Extracted clean title from text dump:',
                                                title);
                                        }
                                    }
                                }

                                if (title) {
                                    console.log('   🎵 Extracted title:', title);

                                    const _ssAudio = (typeof RadioAudio !== 'undefined') ? RadioAudio.get() : null;
                                    const _ssUserPaused = window._smartPlayer ? window._smartPlayer.userPaused : false;
                                    const _ssSystemPaused = (typeof systemPaused !== 'undefined') ? systemPaused : false;
                                    const _ssPaused = (_ssAudio && _ssAudio.paused) || _ssUserPaused || _ssSystemPaused;

                                    if (_ssPaused) {
                                        console.log('⏸️ Fallback metadata while paused — DOM update only');
                                        const _artEl = document.getElementById('current-artist');
                                        const _titEl = document.getElementById('current-title');
                                        if (_titEl) _titEl.textContent = title;
                                        if ('mediaSession' in navigator) navigator.mediaSession.playbackState = 'paused';
                                    } else {
                                        updateSongInfo(title);
                                    }
                                    return true;
                                }

                            } catch (parseError) {
                                console.warn(`   ⚠️ Error parsing response from ${endpoint}:`, parseError);
                            }

                        } catch (endpointError) {
                            console.warn(`   ⚠️ Error fetching ${endpoint}:`, endpointError.message);
                        }
                    }

                    console.log('3️⃣ Trying audio element metadata as last resort...');
                    // 3. Final fallback: Audio element metadata
                    const success = updateSongInfoFromAudio();
                    if (success) {
                        console.log('✅ Successfully got metadata from audio element');
                        return true;
                    }

                    // If we get here, all methods failed
                    console.warn('❌ All metadata fetch methods failed');

                    // CRITICAL FIX: Do NOT reset to "Live Stream" if we have valid metadata displayed.
                    // Only show fallback if we are currently showing a loading state or nothing.
                    const currentTitle = document.querySelector('.player-song')?.textContent;
                    const isGeneric = !currentTitle ||
                        currentTitle === 'Loading metadata...' ||
                        currentTitle === 'Live Stream' ||
                        currentTitle === 'Radio Station';

                    if (isGeneric) {
                        // Try to restore persisted metadata instead of showing fallback
                        if (!restorePersistedMetadata()) {
                            console.log('ℹ️ No persisted metadata to restore, showing fallback');
                            updateSongInfo('Live Stream');
                        }
                    } else {
                        console.log('ℹ️ Keeping current display despite fetch failure');
                    }

                    return false;

                } catch (error) {
                    console.error('❌ Critical error in fetchMetadata:', error);
                    // Try to restore persisted metadata instead of showing error
                    if (!restorePersistedMetadata()) {
                        console.log('ℹ️ No persisted metadata to restore, showing error');
                        updateSongInfo('Error loading metadata');
                    }
                    return false;
                }
            }

            // Function to update song info from audio element metadata
            function updateSongInfoFromAudio() {
                try {
                    const audio = document.getElementById('mini-player-audio');
                    if (!audio) {
                        console.warn('⚠️ Audio element not found');
                        return false;
                    }

                    // Try to get metadata from audio element
                    const title = audio.getAttribute('data-title') || audio.title || '';
                    const artist = audio.getAttribute('data-artist') || '';

                    if (title || artist) {
                        const metadata = {
                            title: title || 'Unknown Song',
                            artist: artist || 'Unknown Artist'
                        };
                        updateSongInfo(metadata);
                        return true;
                    }

                    // Try to extract from audio src if available
                    const src = audio.src || '';
                    if (src && src.includes('title=')) {
                        try {
                            const url = new URL(src);
                            const title = url.searchParams.get('title');
                            const artist = url.searchParams.get('artist');

                            if (title || artist) {
                                const metadata = {
                                    title: title || 'Unknown Song',
                                    artist: artist || 'Unknown Artist'
                                };
                                updateSongInfo(metadata);
                                return true;
                            }
                        } catch (e) {
                            console.warn('⚠️ Could not parse URL for metadata:', e);
                        }
                    }

                    console.log('⚠️ No metadata found in audio element');
                    return false;

                } catch (error) {
                    console.error('❌ Error in updateSongInfoFromAudio:', error);
                    return false;
                }
            }

            // Helper function to fetch with credentials and handle CORS
            async function fetchWithFallback(url, useProxy = false) {
                try {
                    const init = {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json'
                        },
                        cache: 'no-store',
                        credentials: useProxy ? 'omit' : 'include',
                        mode: useProxy ? 'cors' : 'no-cors'
                    };

                    return await fetch(url, init);
                } catch (error) {
                    console.error('Fetch error:', error);
                    throw error;
                }
            }

            // Fallback to rotating metadata when we can't fetch from the stream
            function useFallbackMetadata() {
                const fallback = fallbackMetadata[fallbackIndex];
                // Use updateSongInfo to ensure persistence system is respected
                updateSongInfo({
                    artist: fallback.artist,
                    title: fallback.title,
                    song: fallback.title
                });
                fallbackIndex = (fallbackIndex + 1) % fallbackMetadata.length;

                // Try to reconnect after a delay
                if (retryCount < maxRetries * metadataEndpoints.length) {
                    retryCount++;
                    setTimeout(fetchMetadata, 5000);
                }
            }

            // Fallback method using audio element (slower)
            function fetchMetadataFallback() {
                if (retryCount >= maxRetries) return;

                const audioMeta = new Audio(streamUrl);
                audioMeta.crossOrigin = 'anonymous';
                audioMeta.preload = 'metadata';

                const timeout = setTimeout(() => {
                    audioMeta.pause();
                    audioMeta.src = '';
                    retryCount++;
                    if (retryCount < maxRetries) {
                        setTimeout(fetchMetadata, 2000 * retryCount); // Exponential backoff
                    }
                }, 2000); // Shorter timeout for fallback

                audioMeta.onloadedmetadata = function () {
                    clearTimeout(timeout);
                    if (audioMeta.metadata && audioMeta.metadata.title) {
                        updateSongInfo(audioMeta.metadata.title);
                        retryCount = 0;
                    }
                    audioMeta.pause();
                    audioMeta.src = '';
                };

                audioMeta.onerror = function () {
                    clearTimeout(timeout);
                    audioMeta.pause();
                    audioMeta.src = '';
                    retryCount++;
                    if (retryCount < maxRetries) {
                        setTimeout(fetchMetadata, 2000 * retryCount);
                    }
                };

                try {
                    audioMeta.load();
                } catch (e) {
                    console.error('Error loading audio metadata:', e);
                }
            }

            // Set up the audio element for playback
            if (audio) {
                audio.crossOrigin = 'anonymous'; // Required for some streams to allow metadata access

                // Listen for play events
                document.querySelectorAll('.listen-btn').forEach(btn => {
                    btn.addEventListener('click', function (e) {
                        e.preventDefault();
                        if (audio.paused) {
                            // User-initiated play — clear intent flags
                            userPaused = false;
                            localStorage.setItem(skey('was_playing'), '1');
                            localStorage.removeItem(skey('tab_closed_at'));

                            // Issue 4.5: if a system pause happened (phone call, BT
                            // disconnect), reload the stream before playing so we don't
                            // play from a stale buffer which causes blips every 5-10 s.
                            if (systemPaused) {
                                systemPaused = false;
                                console.log('🔄 listen-btn: stream refreshed after system pause (prevents blips)');
                                audio.src = streamUrl;
                                audio.load();
                            } else {
                                audio.src = streamUrl;
                            }

                            audio.play().then(() => {
                                if (stickyPlayer) {
                                    stickyPlayer.style.display = 'flex';
                                }
                                if (miniPlayIcon) {
                                    miniPlayIcon.className = 'fas fa-pause';
                                }
                                startMetadataUpdates();
                            }).catch(error => {
                                console.error('Error playing audio:', error);
                            });
                        } else {
                            // User-initiated pause
                            userPaused = true;
                            localStorage.setItem(skey('was_playing'), '0');
                            audio.pause();
                            if (miniPlayIcon) {
                                miniPlayIcon.className = 'fas fa-play';
                            }
                        }
                    });
                });

                // Set up play/pause button in the sticky player
                const playBtn = document.getElementById('play-btn');
                if (playBtn) {
                    playBtn.addEventListener('click', function () {
                        if (audio.paused) {
                            // User-initiated play — clear intent flags
                            userPaused = false;
                            localStorage.setItem(skey('was_playing'), '1');
                            localStorage.removeItem(skey('tab_closed_at'));

                            // Issue 4.5: reload stream if a system pause occurred to
                            // prevent blips from a stale/partially-consumed buffer.
                            if (systemPaused) {
                                systemPaused = false;
                                console.log('🔄 Play btn: stream refreshed after system pause (prevents blips)');
                                audio.src = streamUrl;
                                audio.load();
                            }

                            audio.play().then(() => {
                                if (miniPlayIcon) {
                                    miniPlayIcon.className = 'fas fa-pause';
                                }
                            }).catch(e => console.error('Play error:', e));
                        } else {
                            // User-initiated pause
                            userPaused = true;
                            localStorage.setItem(skey('was_playing'), '0');
                            audio.pause();
                            if (miniPlayIcon) {
                                miniPlayIcon.className = 'fas fa-play';
                            }
                        }
                    });
                }

                // Clean up on page unload
                window.addEventListener('beforeunload', function () {
                    if (metadataInterval) {
                        clearInterval(metadataInterval);
                    }
                });
            }

            // Function to check audio element for metadata
            function checkAudioMetadata() {
                if (!audio) return;

                console.log('Checking audio element for metadata...');

                // Try to get metadata from the audio element
                try {
                    // Create a temporary audio context to analyze the stream
                    const AudioContext = window.AudioContext || window.webkitAudioContext;
                    if (AudioContext) {
                        const audioContext = new AudioContext();
                        const source = audioContext.createMediaElementSource(audio);

                        // Just connect to destination to keep the context alive
                        source.connect(audioContext.destination);

                        console.log('Audio context created, checking for metadata...');
                    }

                    // Check for metadata events
                    audio.onloadedmetadata = function () {
                        console.log('Audio metadata loaded:', {
                            duration: audio.duration,
                            readyState: audio.readyState,
                            error: audio.error
                        });
                    };

                    // Check for errors
                    audio.onerror = function () {
                        console.error('Audio element error:', audio.error);
                    };

                    // Check for metadata updates
                    if ('mediaSession' in navigator) {
                        // Issue 4.5: MediaSession play must also reload stream if a system
                        // pause happened (e.g. user presses Play on lock screen after call).
                        navigator.mediaSession.setActionHandler('play', () => {
                            userPaused = false;
                            localStorage.setItem(skey('was_playing'), '1');
                            if (systemPaused) {
                                systemPaused = false;
                                console.log('🔄 MediaSession play: stream refreshed after system pause');
                                audio.src = streamUrl;
                                audio.load();
                            }
                            audio.play().catch(e => console.log('MediaSession play failed:', e));
                        });
                        navigator.mediaSession.setActionHandler('pause', () => {
                            userPaused = true;
                            localStorage.setItem(skey('was_playing'), '0');
                            audio.pause();
                        });

                        if (typeof window.configureLiveMediaSessionActions === 'function') {
                            window.configureLiveMediaSessionActions();
                        }
                    }

                } catch (e) {
                    console.error('Error checking audio metadata:', e);
                }
            }

            // Function to initialize the player
            // Global variable to store the metadata update interval

            function initializePlayer() {
                console.log('Initializing player...');

                // Show the player immediately
                if (stickyPlayer) {
                    stickyPlayer.style.display = 'flex';
                    console.log('Sticky player shown');
                } else {
                    console.error('Sticky player element not found');
                }

                // Set initial metadata - try to restore persisted metadata first
                if (!restorePersistedMetadata()) {
                    updateSongInfo('Loading...');
                }

                // Set up audio element if it exists
                if (audio) {
                    console.log('Audio element found, setting up...');

                    // Set the audio source
                    audio.src = streamUrl;
                    audio.preload = 'metadata';
                    audio.crossOrigin = 'anonymous'; // Important for CORS

                    // Store the original stream URL for metadata fetching
                    audio.dataset.originalSrc = streamUrl;

                    // Set up event listeners
                    audio.onplay = function () {
                        console.log('Audio playback started');
                        if (miniPlayIcon) {
                            miniPlayIcon.className = 'fas fa-pause';
                        }
                        // Start updating metadata when playback starts
                        startMetadataUpdates();

                        // Try to get metadata from audio element
                        setTimeout(() => {
                            console.log('🎵 Checking audio element metadata...');
                            console.log('Audio src:', audio.src);
                            console.log('Audio currentSrc:', audio.currentSrc);
                            console.log('Audio mediaKeys:', audio.mediaKeys);
                            console.log('Audio readyState:', audio.readyState);
                            console.log('Audio networkState:', audio.networkState);

                            // Check for audio tracks metadata
                            if (audio.audioTracks && audio.audioTracks.length > 0) {
                                console.log('Audio tracks:', audio.audioTracks);
                                for (let i = 0; i < audio.audioTracks.length; i++) {
                                    console.log(`Track ${i}:`, audio.audioTracks[i]);
                                }
                            }

                            // Check for text tracks (might contain metadata)
                            if (audio.textTracks && audio.textTracks.length > 0) {
                                console.log('Text tracks:', audio.textTracks);
                                for (let i = 0; i < audio.textTracks.length; i++) {
                                    console.log(`Text track ${i}:`, audio.textTracks[i]);
                                }
                            }

                            // Try to extract from audio attributes
                            const title = audio.getAttribute('data-title') || audio.title;
                            const artist = audio.getAttribute('data-artist');

                            if (title || artist) {
                                console.log('Found metadata in audio attributes:', {
                                    title,
                                    artist
                                });
                                updateSongInfo({
                                    title,
                                    artist
                                });
                            }
                        }, 2000); // Check after 2 seconds
                    };

                    // ── System-pause detection ─────────────────────────────────────────────
                    // When the OS/BT causes an unexpected pause (phone call, BT disconnect,
                    // audio focus loss), set systemPaused so the next Play press knows to
                    // reload the stream first on resume.
                    // NOTE: We do NOT call audio.load() here — that would make 'canplay' fire
                    // immediately (stream pre-buffering), before BT has actually reconnected,
                    // causing audio.play() to route to no output device.
                    audio.addEventListener('pause', function () {
                        if (!userPaused) {
                            systemPaused = true;
                            console.log('🚗 System pause detected (BT disconnect / phone call / audio focus loss).');
                        }
                    });

                    audio.onerror = function () {
                        console.error('Audio element error:', audio.error);
                        // Try to restore persisted metadata instead of showing error
                        if (!restorePersistedMetadata()) {
                            updateSongInfo('Error loading stream');
                        }
                        // Do not stop metadata updates on audio error, 
                        // as paused live streams often timeout and trigger an error
                        // stopMetadataUpdates();
                    };

                    audio.onloadedmetadata = function () {
                        console.log('Audio metadata loaded:', {
                            duration: audio.duration,
                            readyState: audio.readyState,
                            error: audio.error
                        });

                        // Try to get metadata from the audio element
                        if (audio.mozHasAudioMetadata || audio.webkitAudioDecodedByteCount) {
                            console.log('Audio has metadata');
                            updateSongInfoFromAudio();
                        } else {
                            console.log('No metadata available in audio element');
                            // Try to restore persisted metadata instead of showing fallback
                            if (!restorePersistedMetadata()) {
                                updateSongInfo('Live Stream');
                            }
                        }
                    };

                    // Try to play the audio
                    const playPromise = audio.play();

                    if (playPromise !== undefined) {
                        playPromise.catch(error => {
                            console.error('Play error:', error);
                            // If autoplay is blocked, update UI to show play button
                            if (miniPlayIcon) {
                                miniPlayIcon.className = 'fas fa-play';
                            }
                            // Don't override metadata when autoplay is blocked
                            // Let the persisted metadata remain visible
                        });
                    }

                } else {
                    console.error('Audio element not found');
                    // Try to restore persisted metadata instead of showing error
                    if (!restorePersistedMetadata()) {
                        console.log('ℹ️ No persisted metadata to restore, showing player error');
                        updateSongInfo('Player not available');
                    }
                }

                // Set up play/pause button (initializePlayer path)
                // Routes through the unified doPlay/doPause dispatcher so flags,
                // _smartPlayer intent, MediaSession state, and button icon are always
                // updated atomically by a single code path.
                const playBtnInit = document.getElementById('play-btn');
                if (playBtnInit) {
                    playBtnInit.onclick = function () {
                        const isAudioPaused = audio ? audio.paused : true;
                        if (isAudioPaused) {
                            // User-initiated play
                            window.doPlay && window.doPlay({ force: systemPaused });
                        } else {
                            // User-initiated pause
                            window.doPause && window.doPause({ user: true });
                        }
                    };
                }

                console.log('Player initialization complete');
            }

            function startMetadataUpdates() {
                console.log('🔄 Starting metadata updates...');

                // First, restore persisted metadata
                setTimeout(() => {
                    const restoreResult = restorePersistedMetadata();
                    if (restoreResult) {
                        console.log('✅ Successfully restored persisted metadata on page load');
                    }
                }, 100);

                // Clear any existing updates
                stopMetadataUpdates();

                // Try SSE first, but with a timeout
                console.log('🎵 Attempting Radiomast SSE connection...');
                setupRadiomastMetadata();

                // Set a timeout - if SSE doesn't connect in 10 seconds, use fallback
                setTimeout(() => {
                    if (!window.radiomastEventSource || window.radiomastEventSource.readyState !==
                        EventSource.OPEN) {
                        console.warn(
                            '⚠️ SSE connection not established after 10 seconds, using fallback');
                        startFallbackPolling();
                    }
                }, 10000);
            }

            function stopMetadataUpdates() {
                console.log('⏹️ Stopping metadata updates...');

                // Clear the main interval
                if (metadataInterval) {
                    clearInterval(metadataInterval);
                    metadataInterval = null;
                }

                // Clear fallback interval
                if (window.fallbackMetadataInterval) {
                    clearInterval(window.fallbackMetadataInterval);
                    window.fallbackMetadataInterval = null;
                }

                // Close EventSource connection
                if (window.radiomastEventSource) {
                    window.radiomastEventSource.close();
                    window.radiomastEventSource = null;
                    console.log('Closed EventSource connection');
                }

                console.log('Stopped all metadata updates');
            }

            // Clean up on page unload
            window.addEventListener('beforeunload', function () {
                console.log('Cleaning up...');
                if (window.metadataInterval) {
                    console.log('Clearing metadata interval');
                    clearInterval(window.metadataInterval);
                }
                // Mark that the tab was explicitly navigated away from.
                // This prevents auto-resume from firing on a fresh page load
                // (which would be unexpected autoplay). BT reconnect while the
                // same page is in memory (BFCache / just screen-off) fires
                // visibilitychange WITHOUT beforeunload, so jammin_tab_closed_at
                // will NOT be set in that case → BT auto-resume still works.
                localStorage.setItem(skey('tab_closed_at'), Date.now());
            });

            // ── Issue 4: Bluetooth Reconnect Auto-Resume ─────────────────────────────
            // Three-layer approach to cover all reconnect scenarios:
            //
            //   Layer 1 – navigator.mediaDevices 'devicechange': fires when ANY audio
            //             device connects or disconnects (the real BT API). Handles the
            //             case where the page is already visible when BT reconnects
            //             (visibilitychange/focus won't re-fire in that case).
            //
            //   Layer 2 – visibilitychange / focus: fires when the page goes
            //             background→foreground (screen unlock, app switch).
            //
            //   Layer 3 – Periodic poll every 3 s: safety net for the cases where
            //             neither of the above fires (e.g. niche Android WebViews).
            //
            // Auto-resume fires ONLY when:
            //   • The user was playing before (jammin_was_playing === '1')
            //   • The user did NOT intentionally pause (userPaused === false)
            //   • The tab was NOT closed and reopened (jammin_tab_closed_at not set)
            function handlePageResume(reason = 'unknown') {
                if (userPaused) {
                    console.log('🚫 Auto-resume skipped: User intentionally paused');
                    return;
                }

                const liveAudio = (typeof RadioAudio !== 'undefined') ? RadioAudio.get() : null;
                if (liveAudio && !liveAudio.paused) {
                    console.log(`ðŸ“¡ Auto-resume skipped: stream already playing (Trigger: ${reason})`);
                    if (typeof window.reassertLiveMediaPosition === 'function') {
                        window.reassertLiveMediaPosition(`page-resume:${reason}`);
                    }
                    return;
                }

                const wasPlaying = localStorage.getItem(skey('was_playing')) === '1';
                const tabClosedAt = localStorage.getItem(skey('tab_closed_at'));
                const isFreshLoad = tabClosedAt && (Date.now() - parseInt(tabClosedAt) < 5000);

                if (wasPlaying && !isFreshLoad) {
                    console.log(`📡 Auto-resuming stream (Trigger: ${reason})`);
                    // Use SmartRadioPlayer if available for cache-busting and robust start
                    if (window._smartPlayer && typeof window._smartPlayer.resume === 'function') {
                        window._smartPlayer.resume();
                    } else if (liveAudio) {
                        liveAudio.play().catch(e => console.warn('[Auto-resume] play() failed:', e.message));
                    } else if (audio) {
                        audio.play().catch(e => console.warn('[Auto-resume] fallback play() failed:', e.message));
                    }
                } else {
                    console.log('🚫 Auto-resume skipped: Player was not playing or fresh page load');
                }
            }

            // Layer 1: Bluetooth devicechange detection
            if (navigator.mediaDevices && typeof navigator.mediaDevices.addEventListener === 'function') {
                navigator.mediaDevices.addEventListener('devicechange', function () {
                    console.log('🎧 Media device change detected (BT/CarPlay)');
                    // Small delay to allow OS to stabilize audio routing
                    setTimeout(() => handlePageResume('devicechange'), 1500);
                });
            }

            document.addEventListener('visibilitychange', function () {
                if (!document.hidden) {
                    // Only claim and re-assert if we weren't already the owner
                    const wasAlreadyOwner = _isActiveMediaTab;
                    if (!wasAlreadyOwner) {
                        console.log('👁️ Page became visible — claiming MediaSession ownership');
                        _claimMediaOwnership();
                    }

                    // Layer 2: Auto-resume on visibility
                    handlePageResume('visibilitychange');

                    if (window._storedMediaMetadata && navigator.mediaSession) {
                        const currentSessionTitle = navigator.mediaSession.metadata?.title;
                        if (currentSessionTitle !== window._storedMediaMetadata.title) {
                            // Route through debounced commit to avoid flash
                            window._commitMediaSession(window._storedMediaMetadata, true);
                            console.log('🔄 Re-asserted MediaSession metadata on tab visible:', window._storedMediaMetadata.title);
                        } else {
                            console.log('✅ MediaSession already in sync, skipping re-assertion (visibilitychange)');
                        }
                    }
                } else {
                    const liveAudio = (typeof RadioAudio !== 'undefined') ? RadioAudio.get() : null;
                    if (liveAudio && !liveAudio.paused) {
                        localStorage.setItem(skey('was_playing'), '1');
                    }
                    // CRITICAL: We no longer set _isActiveMediaTab = false here.
                    // A tab remains the owner even in the background until another tab claims it.
                    // This allows lock-screen metadata updates to work while paused/backgrounded.
                }
            });

            window.addEventListener('focus', function () {
                // Only claim and re-assert if we weren't already the owner
                const wasAlreadyOwner = _isActiveMediaTab;
                if (!wasAlreadyOwner) {
                    console.log('🔆 Window gained focus — claiming MediaSession ownership');
                    _claimMediaOwnership();
                }

                // Layer 2: Auto-resume on focus
                handlePageResume('focus');

                if (window._storedMediaMetadata && navigator.mediaSession) {
                    const currentSessionTitle = navigator.mediaSession.metadata?.title;
                    if (currentSessionTitle !== window._storedMediaMetadata.title) {
                        // Route through debounced commit to avoid flash
                        window._commitMediaSession(window._storedMediaMetadata, true);
                        console.log('🔄 Re-asserted MediaSession metadata on window focus:', window._storedMediaMetadata.title);
                    } else {
                        console.log('✅ MediaSession already in sync, skipping re-assertion (focus)');
                    }
                }
            });

            // Layer 3: Periodic safety-net poll for BT reconnect has been removed.

            // --- Unified Player Initialization ---
            console.log('🚀 [Initialization] Starting unified player startup sequence...');
            try {
                // 1. Initialize Player UI and Audio
                initializePlayer();

                // 2. Restore Title/Artist IMMEDIATELY from cache (Prevents flickering to generic name)
                console.log('📌 [Initialization] Restoring persisted metadata...');
                restorePersistedMetadata();

                // 3. Start live metadata updates (SSE/Polling)
                console.log('📡 [Initialization] Starting live metadata updates...');
                startMetadataUpdates();

                console.log('✅ [Initialization] Unified startup sequence complete.');
            } catch (e) {
                console.error('❌ [Initialization] Error during startup sequence:', e);
            }
        } catch (e) {
            console.error('❌ [Initialization] Critical error in player initialization block:', e);
        }
    });
</script>