<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .sidebar {
            min-height: 100vh;
            background: #343a40;
            color: white;
        }
        .sidebar a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            padding: 10px 15px;
            display: block;
        }
        .sidebar a:hover, .sidebar a.active {
            color: white;
            background: rgba(255, 255, 255, 0.1);
        }
        .sidebar i {
            width: 20px;
            margin-right: 10px;
            text-align: center;
        }
        .main-content {
            padding: 20px;
        }
        
        /* Sticky Player Styles */
        :root {
            --player-height: 80px;
            --accent-blue: #667eea;
            --dark-bg: #1a1a1a;
        }
        
        .sticky-player {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: var(--player-height);
            background: var(--dark-bg);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
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
            overflow: hidden;
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
        }
        
        .player-title {
            font-weight: 600;
            font-size: 1rem;
            color: white;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .player-song {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.8);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .player-buttons {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .player-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: none;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 18px;
        }
        
        .player-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: scale(1.1);
        }
    </style>
    @stack('styles')
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 p-0 sidebar">
                <div class="p-3">
                    <h4>{{ config('app.name') }}</h4>
                </div>
                <ul class="nav flex-column">
                    <li><a href="{{ route('admin.index') }}" class="{{ request()->is('admin') ? 'active' : '' }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="{{ route('admin.contests.index') }}" class="{{ request()->is('admin/contests*') ? 'active' : '' }}"><i class="fas fa-trophy"></i> Contests</a></li>
                    <li><a href="{{ route('admin.uploaded-news.index') }}" class="{{ request()->is('admin/uploaded-news*') ? 'active' : '' }}"><i class="fas fa-newspaper"></i> News</a></li>
                    <li><a href="{{ route('admin.community-events.index') }}" class="{{ request()->is('admin/community-events*') ? 'active' : '' }}"><i class="fas fa-calendar-alt"></i> Community Events</a></li>
                    <li><a href="{{ route('admin.ads.index') }}" class="{{ request()->is('admin/ads*') ? 'active' : '' }}"><i class="fas fa-ad"></i> Ads Management</a></li>
                    <li><a href="{{ route('admin.footer.index') }}" class="{{ request()->is('admin/footer*') ? 'active' : '' }}"><i class="fas fa-cog"></i> Appearance</a></li>
                    <li><a href="{{ route('admin.apis') }}" class="{{ request()->is('admin/apis*') ? 'active' : '' }}"><i class="fas fa-plug"></i> API Settings</a></li>
                </ul>
                
                <!-- Logout Button -->
                <div class="p-3 mt-auto">
                    <form method="POST" action="{{ route('admin.logout') }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-outline-light btn-sm w-100">
                            <i class="fas fa-sign-out-alt me-2"></i> Logout
                        </button>
                    </form>
                </div>
            </div>

            <!-- Main content -->
            <div class="col-md-10 main-content">
                <!-- Success message handled by individual views -->

                @yield('content')
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
    
    <script>

        audio.src = 'https://streams.radiomast.io/rc101';

        // Mini Player JavaScript
        document.addEventListener('DOMContentLoaded', function() {
const audio = document.getElementById('radioPlayer');
            const stickyPlayer = document.getElementById('sticky-player');
            const miniPlayBtn = document.getElementById('mini-play-btn');
            const miniPlayIcon = document.getElementById('mini-play-icon');
            const minimizeBtn = document.getElementById('minimize-btn');
            const currentArtist = document.getElementById('current-artist');
            const currentTitle = document.getElementById('current-title');
            
            if (!audio || !stickyPlayer) {
                console.error('Player elements not found');
                return;
            }
            
            // Play/Pause functionality
            miniPlayBtn.addEventListener('click', function() {
                if (audio.paused) {
                    audio.play().then(() => {
                        miniPlayIcon.className = 'fas fa-pause';
                        console.log('Audio playback started');
                    }).catch(error => {
                        console.error('Error playing audio:', error);
                    });
                } else {
                    audio.pause();
                    miniPlayIcon.className = 'fas fa-play';
                    console.log('Audio playback paused');
                }
            });
            
            // Minimize functionality
            minimizeBtn.addEventListener('click', function() {
                stickyPlayer.classList.toggle('player-minimized');
                const icon = minimizeBtn.querySelector('i');
                icon.className = stickyPlayer.classList.contains('player-minimized') ? 'fas fa-chevron-up' : 'fas fa-chevron-down';
            });
            
            // Audio event listeners
// Add these inside your DOMContentLoaded function

audio.addEventListener('play', function() {
    console.log('▶️ Audio playing');
    if ('mediaSession' in navigator) {
        navigator.mediaSession.playbackState = 'playing';
    }
    startMetadataUpdates();
});

audio.addEventListener('pause', function() {
    console.log('⏸️ Audio paused');
    if ('mediaSession' in navigator) {
        navigator.mediaSession.playbackState = 'paused';
    }
    stopMetadataUpdates();
});
            audio.addEventListener('error', function() {
                console.error('Audio error:', audio.error);
                currentArtist.textContent = 'Stream Error';
                currentTitle.textContent = 'Unable to connect';
            });
            
            // Initialize player state
            console.log('Mini player initialized');
            
            // Function to update the player with song information
            function updateSongInfo(info) {
    // [1] Validation checks
    if (!info) return;
    
    // [2] Get DOM elements
    const artistElement = document.getElementById('current-artist');
    const titleElement = document.getElementById('current-title');
    
    // [3] Parse and process metadata
    let artist = 'Now Playing';
    let title = 'Live Stream';
    // ... parsing logic ...
    
    // [4] Update DOM
    artistElement.textContent = artist;
    titleElement.textContent = title;
    
    // [5] Update document title
    document.title = @json(config('app.name'));
    
    // [6] 🆕 UPDATE MEDIA SESSION API ← ADD HERE
const _liveAudio = document.getElementById('live-audio');
if ('mediaSession' in navigator && _liveAudio && !_liveAudio.paused) {
        navigator.mediaSession.metadata = new MediaMetadata({
            title: title,
            artist: artist,
            album: 'Live Radio',
            artwork: [...]
        });
    }
    
    // [7] Show player
    if (stickyPlayer) {
        stickyPlayer.style.display = 'flex';
    }
    
    // [8] Final log
    console.log('✅ Song info update complete');
}
            
            // Function to fetch metadata from the audio stream
            function fetchMetadata() {
                // Try to get metadata from the audio element
                if (audio) {
                     fetch('/admin/api/stream-metadata')
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Map API response to the format updateSongInfo expects
                                const metadata = {
                                    artist: data.artist || '',
                                    title: data.song || 'Live Stream'
                                };
                                updateSongInfo(metadata);
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching metadata:', error);
                            // Fallback to generic info on error
                             updateSongInfo({
                                artist: '',
                                title: 'Live Stream'
                            });
                        });
                }
            }
            
            // Update metadata periodically
            setInterval(fetchMetadata, 30000); // Update every 30 seconds
            fetchMetadata(); // Initial update
            
            // Also try to get metadata when audio starts playing
            audio.addEventListener('play', function() {
                setTimeout(fetchMetadata, 2000); // Wait 2 seconds then fetch metadata
            });
        });
    </script>
    
    <!-- Sticky Player -->
    <!--<div class="sticky-player" id="sticky-player">-->
    <!--    <div class="player-controls">-->
    <!--        <div class="player-info" style="cursor: pointer;">-->
    <!--            <div class="mini-player-icon" id="mini-play-btn">-->
    <!--                <i class="fas fa-play" id="mini-play-icon"></i>-->
    <!--            </div>-->
    <!--            <div>-->
    <!--                <div class="player-title">Radio Station Live</div>-->
    <!--                <div class="player-song" id="mini-song">-->
    <!--                    <div id="current-artist">Loading...</div>-->
    <!--                    <div id="current-title"></div>-->
    <!--                </div>-->
    <!--            </div>-->
    <!--        </div>-->
    <!--        <div class="player-buttons">-->
    <!--            <button class="player-btn minimize-btn" id="minimize-btn" title="Minimize">-->
    <!--                <i class="fas fa-chevron-down"></i>-->
    <!--            </button>-->
    <!--        </div>-->
            <!-- Hidden audio element -->
    <!--        <audio id="live-audio" preload="none" style="display: none;">-->
    <!--            <source src="{{ config('app.stream_url') }}" type="audio/mpeg">-->
    <!--            Your browser does not support the audio element.-->
    <!--        </audio>-->
    <!--    </div>-->
    <!--</div>-->
</body>
</html>
