@php
    $componentId = 'location-concerts-' . uniqid();
@endphp

<!-- Location-based Pop Concerts Component -->
<div id="{{ $componentId }}" class="location-concerts-container">
    <!-- Location Permission Section -->
    <div class="location-permission-section" id="{{ $componentId }}-permission">
        <div class="location-card">
            <div class="location-icon">
                <i class="fas fa-map-marker-alt"></i>
            </div>
            <h3>Find Pop Concerts Near You</h3>
            <p>Allow us to access your location to discover Pop concerts within 50 miles of your current location.</p>
            
            <div class="location-actions">
                <button class="btn btn-primary" id="{{ $componentId }}-enable-location">
                    <i class="fas fa-location-arrow"></i>
                    Enable Location Access
                </button>
                <button class="btn btn-outline-secondary" id="{{ $componentId }}-manual-location">
                    <i class="fas fa-search"></i>
                    Search by City
                </button>
            </div>
            
            <div class="location-status" id="{{ $componentId }}-status"></div>
        </div>
    </div>
    
    <!-- Manual Location Input (Hidden by default) -->
    <div class="manual-location-section" id="{{ $componentId }}-manual" style="display: none;">
        <div class="location-card">
            <h3>Enter Your Location</h3>
            <div class="location-input-group">
                <input type="text" 
                       class="form-control" 
                       id="{{ $componentId }}-city-input" 
                       placeholder="Enter city name (e.g., New York, Los Angeles)">
                <button class="btn btn-primary" id="{{ $componentId }}-search-city">
                    <i class="fas fa-search"></i>
                    Search
                </button>
            </div>
            <button class="btn btn-link" id="{{ $componentId }}-back-to-location">
                <i class="fas fa-arrow-left"></i>
                Back to location access
            </button>
        </div>
    </div>
    
    <!-- Loading Section -->
    <div class="concerts-loading" id="{{ $componentId }}-loading" style="display: none;">
        <div class="loading-spinner">
            <i class="fas fa-spinner fa-spin"></i>
        </div>
        <p>Searching for Pop concerts near you...</p>
    </div>
    
    <!-- Concerts Results Section -->
    <div class="concerts-results" id="{{ $componentId }}-results" style="display: none;">
        <div class="concerts-header">
            <h3>Pop Concerts Near You</h3>
            <div class="concerts-meta">
                <span class="location-info" id="{{ $componentId }}-location-info"></span>
                <button class="btn btn-sm btn-outline-primary" id="{{ $componentId }}-refresh">
                    <i class="fas fa-sync-alt"></i>
                    Refresh
                </button>
            </div>
        </div>
        
        <div class="concerts-grid" id="{{ $componentId }}-concerts-grid">
            <!-- Concerts will be dynamically loaded here -->
        </div>
        
        <div class="no-concerts" id="{{ $componentId }}-no-concerts" style="display: none;">
            <i class="fas fa-music"></i>
            <h4>No Pop Concerts Found</h4>
            <p>There are no Pop concerts scheduled within 50 miles of your location. Try expanding your search radius or check back later.</p>
        </div>
    </div>
    
    <!-- Error Section -->
    <div class="concerts-error" id="{{ $componentId }}-error" style="display: none;">
        <div class="error-card">
            <i class="fas fa-exclamation-triangle"></i>
            <h4>Unable to Fetch Concerts</h4>
            <p id="{{ $componentId }}-error-message"></p>
            <button class="btn btn-primary" id="{{ $componentId }}-retry">
                <i class="fas fa-redo"></i>
                Try Again
            </button>
        </div>
    </div>
</div>

@push('styles')
<style>
.location-concerts-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.location-card {
    background: white;
    border-radius: 12px;
    padding: 30px;
    text-align: center;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    border: 1px solid #e9ecef;
}

.location-icon {
    font-size: 48px;
    color: #667eea;
    margin-bottom: 20px;
}

.location-card h3 {
    color: #2d3748;
    margin-bottom: 15px;
    font-size: 24px;
    font-weight: 600;
}

.location-card p {
    color: #718096;
    margin-bottom: 25px;
    font-size: 16px;
    line-height: 1.5;
}

.location-actions {
    display: flex;
    gap: 15px;
    justify-content: center;
    flex-wrap: wrap;
}

.location-actions .btn {
    padding: 12px 24px;
    font-weight: 500;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.location-status {
    margin-top: 20px;
    padding: 10px;
    border-radius: 6px;
    font-size: 14px;
}

.location-status.success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.location-status.error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.location-status.info {
    background: #d1ecf1;
    color: #0c5460;
    border: 1px solid #bee5eb;
}

.manual-location-section .location-card {
    max-width: 500px;
    margin: 0 auto;
}

.location-input-group {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
}

.location-input-group input {
    flex: 1;
}

.concerts-loading {
    text-align: center;
    padding: 60px 20px;
}

.loading-spinner {
    font-size: 48px;
    color: #667eea;
    margin-bottom: 20px;
}

.concerts-loading p {
    color: #718096;
    font-size: 18px;
}

.concerts-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    flex-wrap: wrap;
    gap: 15px;
}

.concerts-header h3 {
    color: #2d3748;
    font-size: 28px;
    font-weight: 600;
    margin: 0;
}

.concerts-meta {
    display: flex;
    align-items: center;
    gap: 15px;
}

.location-info {
    color: #718096;
    font-size: 14px;
    background: #f7fafc;
    padding: 6px 12px;
    border-radius: 20px;
}

.concerts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 25px;
    margin-bottom: 30px;
}

.concert-card {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    border: 1px solid #e9ecef;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.concert-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
}

.concert-image {
    width: 100%;
    height: 200px;
    object-fit: cover;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.concert-content {
    padding: 20px;
}

.concert-title {
    font-size: 18px;
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 8px;
    line-height: 1.3;
}

.concert-artist {
    color: #667eea;
    font-weight: 500;
    margin-bottom: 12px;
    font-size: 16px;
}

.concert-details {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 15px;
}

.concert-detail {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #718096;
    font-size: 14px;
}

.concert-detail i {
    width: 16px;
    color: #a0aec0;
}

.concert-price {
    color: #48bb78;
    font-weight: 600;
    font-size: 16px;
    margin-bottom: 15px;
}

.concert-actions {
    display: flex;
    gap: 10px;
}

.concert-actions .btn {
    flex: 1;
    padding: 10px 16px;
    font-size: 14px;
    font-weight: 500;
}

.no-concerts, .concerts-error {
    text-align: center;
    padding: 60px 20px;
}

.no-concerts i, .concerts-error i {
    font-size: 64px;
    color: #cbd5e0;
    margin-bottom: 20px;
}

.no-concerts h4, .concerts-error h4 {
    color: #2d3748;
    margin-bottom: 10px;
    font-size: 20px;
}

.no-concerts p, .concerts-error p {
    color: #718096;
    margin-bottom: 20px;
}

.error-card {
    background: #fff5f5;
    border: 1px solid #fed7d7;
    border-radius: 12px;
    padding: 30px;
    max-width: 500px;
    margin: 0 auto;
}

.error-card i {
    color: #e53e3e;
    font-size: 48px;
    margin-bottom: 15px;
}

.error-card h4 {
    color: #e53e3e;
    margin-bottom: 10px;
}

.error-card p {
    color: #718096;
    margin-bottom: 20px;
}

/* Responsive Design */
@media (max-width: 768px) {
    .location-concerts-container {
        padding: 15px;
    }
    
    .location-card {
        padding: 20px;
    }
    
    .location-actions {
        flex-direction: column;
        align-items: center;
    }
    
    .location-actions .btn {
        width: 100%;
        max-width: 300px;
    }
    
    .concerts-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .concerts-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .location-input-group {
        flex-direction: column;
    }
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const componentId = '{{ $componentId }}';
    const permissionSection = document.getElementById(componentId + '-permission');
    const manualSection = document.getElementById(componentId + '-manual');
    const loadingSection = document.getElementById(componentId + '-loading');
    const resultsSection = document.getElementById(componentId + '-results');
    const errorSection = document.getElementById(componentId + '-error');
    const statusDiv = document.getElementById(componentId + '-status');
    const concertsGrid = document.getElementById(componentId + '-concerts-grid');
    const noConcertsDiv = document.getElementById(componentId + '-no-concerts');
    const errorMessage = document.getElementById(componentId + '-error-message');
    const locationInfo = document.getElementById(componentId + '-location-info');
    
    // Buttons
    const enableLocationBtn = document.getElementById(componentId + '-enable-location');
    const manualLocationBtn = document.getElementById(componentId + '-manual-location');
    const searchCityBtn = document.getElementById(componentId + '-search-city');
    const backToLocationBtn = document.getElementById(componentId + '-back-to-location');
    const refreshBtn = document.getElementById(componentId + '-refresh');
    const retryBtn = document.getElementById(componentId + '-retry');
    
    // Input
    const cityInput = document.getElementById(componentId + '-city-input');
    
    // Show status message
    function showStatus(message, type = 'info') {
        statusDiv.innerHTML = message;
        statusDiv.className = 'location-status ' + type;
        statusDiv.style.display = 'block';
    }
    
    // Hide all sections
    function hideAllSections() {
        permissionSection.style.display = 'none';
        manualSection.style.display = 'none';
        loadingSection.style.display = 'none';
        resultsSection.style.display = 'none';
        errorSection.style.display = 'none';
        statusDiv.style.display = 'none';
    }
    
    // Show section
    function showSection(section) {
        hideAllSections();
        section.style.display = 'block';
    }
    
    // Get user's current location
    function getCurrentLocation() {
        if (!navigator.geolocation) {
            showStatus('Geolocation is not supported by this browser.', 'error');
            return;
        }
        
        showStatus('Requesting location access...', 'info');
        
        navigator.geolocation.getCurrentPosition(
            function(position) {
                const latitude = position.coords.latitude;
                const longitude = position.coords.longitude;
                
                showStatus('Location access granted! Searching for concerts...', 'success');
                
                // Fetch concerts with coordinates
                fetchConcerts(latitude, longitude);
            },
            function(error) {
                let message = 'Unable to get your location. ';
                switch(error.code) {
                    case error.PERMISSION_DENIED:
                        message += 'Location access denied by user. Trying IP-based location...';
                        showStatus(message, 'warning');
                        // Try IP-based location as fallback
                        setTimeout(() => {
                            fetchConcertsWithoutLocation();
                        }, 1000);
                        break;
                    case error.POSITION_UNAVAILABLE:
                        message += 'Location information is unavailable. Trying IP-based location...';
                        showStatus(message, 'warning');
                        // Try IP-based location as fallback
                        setTimeout(() => {
                            fetchConcertsWithoutLocation();
                        }, 1000);
                        break;
                    case error.TIMEOUT:
                        message += 'Location request timed out. Trying IP-based location...';
                        showStatus(message, 'warning');
                        // Try IP-based location as fallback
                        setTimeout(() => {
                            fetchConcertsWithoutLocation();
                        }, 1000);
                        break;
                    default:
                        message += 'An unknown error occurred. Trying IP-based location...';
                        showStatus(message, 'warning');
                        // Try IP-based location as fallback
                        setTimeout(() => {
                            fetchConcertsWithoutLocation();
                        }, 1000);
                        break;
                }
            },
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 0
            }
        );
    }
    
    // Fetch concerts without location (triggers IP-based detection)
    async function fetchConcertsWithoutLocation() {
        showSection(loadingSection);
        showStatus('Detecting your location from IP address...', 'info');
        
        try {
            const url = '/api/concerts/nearby';
            const params = new URLSearchParams({
                radius: 50,
                classification: 'Pop'
            });
            
            const response = await fetch(url + '?' + params);
            const data = await response.json();
            
            if (data.success) {
                const locationName = data.location?.detected_from === 'ip_geolocation' 
                    ? 'Your Location (IP-based)' 
                    : 'Your Location';
                displayConcerts(data.concerts, locationName);
                showStatus(`Location detected from ${data.location?.detected_from || 'IP'}!`, 'success');
            } else {
                if (data.requires_location) {
                    showStatus('Could not detect your location automatically. Please search by city name.', 'error');
                    showSection(manualSection);
                } else {
                    showError(data.error || 'Failed to fetch concerts');
                }
            }
        } catch (error) {
            console.error('Error fetching concerts with IP location:', error);
            showStatus('Network error occurred. Please try searching by city name.', 'error');
            showSection(manualSection);
        }
    }
    
    // Fetch concerts from API
    async function fetchConcerts(latitude, longitude, cityName = null) {
        showSection(loadingSection);
        
        try {
            const url = '/api/concerts/nearby';
            const params = new URLSearchParams({
                latitude: latitude,
                longitude: longitude,
                radius: 50,
                classification: 'Pop'
            });
            
            const response = await fetch(url + '?' + params);
            const data = await response.json();
            
            if (data.success) {
                displayConcerts(data.concerts, cityName || `${latitude.toFixed(4)}, ${longitude.toFixed(4)}`);
            } else {
                showError(data.error || 'Failed to fetch concerts');
            }
        } catch (error) {
            console.error('Error fetching concerts:', error);
            showError('Network error occurred while fetching concerts');
        }
    }
    
    // Display concerts
    function displayConcerts(concerts, locationName) {
        if (concerts.length === 0) {
            showSection(resultsSection);
            concertsGrid.style.display = 'none';
            noConcertsDiv.style.display = 'block';
            locationInfo.textContent = `Near ${locationName}`;
            return;
        }
        
        locationInfo.textContent = `Near ${locationName}`;
        concertsGrid.innerHTML = '';
        noConcertsDiv.style.display = 'none';
        
        concerts.forEach(concert => {
            const concertCard = createConcertCard(concert);
            concertsGrid.appendChild(concertCard);
        });
        
        showSection(resultsSection);
    }
    
    // Create concert card element
    function createConcertCard(concert) {
        const card = document.createElement('div');
        card.className = 'concert-card d-flex flex-column h-100';
        
        const imageUrl = concert.image || 'https://via.placeholder.com/350x200/667eea/ffffff?text=Pop+Concert';
        
        card.innerHTML = `
            <img src="${imageUrl}" alt="${concert.name}" class="concert-image">
            <div class="concert-content d-flex flex-column flex-grow-1">
                <h4 class="concert-title">${concert.name}</h4>
                <div class="concert-artist">${concert.artist}</div>
                <div class="concert-details">
                    <div class="concert-detail">
                        <i class="fas fa-calendar"></i>
                        <span>${concert.date || 'Date TBD'}</span>
                    </div>
                    ${concert.time ? `
                    <div class="concert-detail">
                        <i class="fas fa-clock"></i>
                        <span>${concert.time}</span>
                    </div>
                    ` : ''}
                    <div class="concert-detail">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>${concert.venue.name}, ${concert.venue.city}</span>
                    </div>
                    ${concert.price_range ? `
                    <div class="concert-detail">
                        <i class="fas fa-tag"></i>
                        <span>${concert.price_range}</span>
                    </div>
                    ` : ''}
                </div>
                ${concert.price_range ? `
                <div class="concert-price">${concert.price_range}</div>
                ` : ''}
                <div class="concert-actions mt-auto">
                    <a href="${concert.url}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-ticket-alt"></i>
                        Get Tickets
                    </a>
                </div>
            </div>
        `;
        
        return card;
    }
    
    // Show error
    function showError(message) {
        errorMessage.textContent = message;
        showSection(errorSection);
    }
    
    // Search by city (geocoding)
    async function searchByCity(cityName) {
        if (!cityName.trim()) {
            alert('Please enter a city name');
            return;
        }
        
        showSection(loadingSection);
        
        try {
            // Use Nominatim API for geocoding
            const geocodeUrl = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(cityName)}&limit=1`;
            const response = await fetch(geocodeUrl);
            const data = await response.json();
            
            if (data && data.length > 0) {
                const latitude = parseFloat(data[0].lat);
                const longitude = parseFloat(data[0].lon);
                const displayName = data[0].display_name.split(',')[0];
                
                fetchConcerts(latitude, longitude, displayName);
            } else {
                showError('City not found. Please try a different city name.');
            }
        } catch (error) {
            console.error('Error geocoding city:', error);
            showError('Error finding city coordinates');
        }
    }
    
    // Event listeners
    document.addEventListener('DOMContentLoaded', function() {
        const container = document.getElementById(componentId);
        if (!container) return;
        
        // Get all elements
        const enableLocationBtn = container.querySelector('#' + componentId + '-enable-location');
        const manualLocationBtn = container.querySelector('#' + componentId + '-manual-location');
        const permissionSection = container.querySelector('#' + componentId + '-permission-section');
        const manualSection = container.querySelector('#' + componentId + '-manual-section');
        const loadingSection = container.querySelector('#' + componentId + '-loading-section');
        const resultsSection = container.querySelector('#' + componentId + '-results-section');
        const errorSection = container.querySelector('#' + componentId + '-error-section');
        const cityInput = container.querySelector('#' + componentId + '-city-input');
        const searchCityBtn = container.querySelector('#' + componentId + '-search-city');
        const refreshBtn = container.querySelector('#' + componentId + '-refresh');
        const retryBtn = container.querySelector('#' + componentId + '-retry');
        const backToLocationBtn = container.querySelector('#' + componentId + '-back-to-location');
        
        // Show location section by default
        permissionSection.style.display = 'block';
        
        // Auto-request location on page load
        setTimeout(() => {
            getCurrentLocation();
        }, 1000); // Small delay to ensure page is fully loaded
        
        // Location button click handler
        enableLocationBtn.addEventListener('click', getCurrentLocation);
        
        manualLocationBtn.addEventListener('click', function() {
            showSection(manualSection);
        });
        
        backToLocationBtn.addEventListener('click', function() {
            showSection(permissionSection);
        });
        
        searchCityBtn.addEventListener('click', function() {
            searchByCity(cityInput.value);
        });
        
        cityInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchByCity(cityInput.value);
            }
        });
    
    refreshBtn.addEventListener('click', function() {
        getCurrentLocation();
    });
    
    retryBtn.addEventListener('click', function() {
        getCurrentLocation();
    });
});
</script>
@endpush
