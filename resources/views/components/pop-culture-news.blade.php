@props([
    'news' => collect()
])

<div class="pop-culture-news-section" id="popCultureNewsSection">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3 class="display-5">
                        <i class="fas fa-newspaper me-2 text-warning"></i>
                        Entertainment News
                    </h3>
                    
                    <!--<button class="btn btn-outline-primary btn-sm" onclick="refreshPopCultureNews()">-->
                    <!--    <i class="fas fa-sync-alt me-1"></i>Refresh-->
                    <!--</button>-->
                </div>
            </div>
        </div>

        <!-- Loading State -->
        <div id="newsLoading" class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-3 text-muted">Loading latest news...</p>
        </div>

        <!-- Error State -->
        <div id="newsError" class="alert alert-danger d-none" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <span id="newsErrorMessage">Failed to load news. Please try again.</span>
            <button class="btn btn-outline-danger btn-sm ms-2" onclick="refreshPopCultureNews()">
                <i class="fas fa-redo me-1"></i>Retry
            </button>
        </div>

        <!-- News Grid -->
        <div id="newsGrid" class="row g-4 d-none">
            <!-- News articles will be dynamically inserted here -->
        </div>
        <!-- Database News (Hidden, appended after API news) -->
        <div id="dbNewsGrid" class="row g-4 d-none">
            @foreach ($news as $item)
                <div class="col-lg-4 col-md-6">
                    <a href="{{ $item->source_url ?? '#' }}" target="_blank" class="news-card-link">
                        <div class="news-card">
                            <img src="{{ $item->image_path ? asset('storage/' . $item->image_path) : 'https://picsum.photos/seed/' . $item->id . '/400/200' }}"
                                class="news-card-image"
                                alt="{{ $item->title }}"
                                onerror="this.src='https://picsum.photos/seed/fallback/400/200.jpg'">
                            <div class="news-card-body">
                                <h5 class="news-card-title">{{ $item->title }}</h5>
                                <div class="news-card-description-wrapper">
                                    <p class="news-card-description">
                                        {{ \Str::limit(strip_tags($item->content), 200) }}
                                    </p>
                                </div>
                                <div class="news-card-meta">
                                    <span class="btn btn-primary btn-sm" >Read More</span>
                                    <span class="news-card-date">{{ $item->published_at->format('M d, Y') }}</span>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>

        <!-- Empty State -->
        <div id="newsEmpty" class="text-center py-5 d-none">
            <i class="fas fa-newspaper text-muted" style="font-size: 3rem;"></i>
            <h4 class="mt-3 text-muted">No news available</h4>
            <p class="text-muted">Check back later for the latest updates.</p>
        </div>
    </div>
</div>

<style>
.pop-culture-news-section {
    margin: 40px 0;
}

.news-card {
    background: white;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    height: 100%;
    display: flex;
    flex-direction: column;
}

.news-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
}

.news-card-image {
    width: 100%;
    height: 200px;
    object-fit: cover;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
}

.news-card-body {
    padding: 20px;
    flex: 1;
    display: flex;
    flex-direction: column;
    min-height: 280px; /* Set minimum height for consistency */
}

.news-card-title {
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: 10px;
    color: #2c3e50;
    line-height: 1.4;
    min-height: 50px; /* Ensure consistent title height */
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.news-card-description-wrapper {
    flex: 1;
    overflow: hidden;
    margin-bottom: 15px;
}

.news-card-description {
    color: #6c757d;
    font-size: 0.9rem;
    line-height: 1.5;
    margin-bottom: 0;
    display: -webkit-box;
    -webkit-line-clamp: 4; /* Show up to 4 lines */
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
    min-height: 60px; /* Ensure minimum height for description */
}

.news-card-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.8rem;
    color: #6c757d;
    margin-top: auto;
    padding-top: 15px;
    border-top: 1px solid #f0f0f0;
}

.news-card-source {
    background: #e9ecef;
    padding: 4px 8px;
    border-radius: 12px;
    font-weight: 500;
}

.news-card-date {
    font-style: italic;
}

.news-card-link {
    color: #1E3C72;
    text-decoration: none;
    display: block;
    height: 100%;
}

.news-card-link:hover {
    text-decoration: none;
}

/* Card height consistency */
.row {
    display: flex;
    flex-wrap: wrap;
}

.row > [class*='col-'] {
    display: flex;
    flex-direction: column;
}

/* Ensure all cards in a row have same height */
@media (max-width: 768px) {
    .news-card-image {
        height: 150px;
    }
    
    .news-card-title {
        font-size: 1rem;
        min-height: 45px;
    }
    
    .news-card-description {
        font-size: 0.85rem;
        -webkit-line-clamp: 3; /* Show fewer lines on mobile */
        min-height: 50px;
    }
    
    .news-card-body {
        min-height: 250px;
    }
}
</style>

<script>
function fetchPopCultureNews() {
    const loadingEl = document.getElementById('newsLoading');
    const errorEl = document.getElementById('newsError');
    const gridEl = document.getElementById('newsGrid');
    const emptyEl = document.getElementById('newsEmpty');
    
    // Show loading state
    loadingEl.classList.remove('d-none');
    errorEl.classList.add('d-none');
    gridEl.classList.add('d-none');
    emptyEl.classList.add('d-none');
    
    fetch('/api/news/pop-culture')
        .then(response => response.json())
        .then(data => {
            loadingEl.classList.add('d-none');
            
            if (data.success && data.data && data.data.length > 0) {
                displayNewsArticles(data.data);
                gridEl.classList.remove('d-none');
            } else {
                loadingEl.classList.add('d-none');

                // Show DB news if API is empty
                const dbGrid = document.getElementById('dbNewsGrid');
                if (dbGrid && dbGrid.children.length > 0) {
                    displayNewsArticles([]);
                    gridEl.classList.remove('d-none');
                } else {
                    emptyEl.classList.remove('d-none');
                }
            }

        })
        .catch(error => {
            console.error('Error fetching pop culture news:', error);
            loadingEl.classList.add('d-none');
            // Fallback to DB news if API fails
            const dbGrid = document.getElementById('dbNewsGrid');
            if (dbGrid && dbGrid.children.length > 0) {
                displayNewsArticles([]);
                gridEl.classList.remove('d-none');
            } else {
                errorEl.classList.remove('d-none');
            }

            document.getElementById('newsErrorMessage').textContent = 
                'Failed to load news. Please check your connection and try again.';
        })
.finally(() => {
    if (typeof window.notifyAsyncLoadComplete === 'function') {
        window.notifyAsyncLoadComplete();
    }
});
}
let allNews = [];
let visibleNewsCount = 8;

function displayNewsArticles(articles) {
    const dbGrid = document.getElementById('dbNewsGrid');

    // Reset combined news array
    allNews = [];

    // 1️⃣ Add DATABASE news first (admin news)
    if (dbGrid && dbGrid.children.length > 0) {
        Array.from(dbGrid.children).forEach(dbChild => {
            allNews.push({ type: 'dom', element: dbChild.cloneNode(true) });
        });
    }

    // 2️⃣ Append API news AFTER database news
    if (articles && articles.length > 0) {
        articles.forEach(article => {
            allNews.push({ type: 'api', data: article });
        });
    }
    
    visibleNewsCount = 8;
    renderNewsBatch();
}

function renderNewsBatch() {
    const gridEl = document.getElementById('newsGrid');
    gridEl.innerHTML = '';
    
    const newsToShow = allNews.slice(0, visibleNewsCount);
    
    newsToShow.forEach(item => {
        if (item.type === 'dom') {
            gridEl.appendChild(item.element.cloneNode(true));
        } else {
            const newsCard = createNewsCard(item.data);
            gridEl.appendChild(newsCard);
        }
    });

    // Make sure all database news cards are visible and have same structure
    const allNewsCards = gridEl.querySelectorAll('.news-card');
    allNewsCards.forEach(card => {
        card.classList.add('news-card');
    });
    
    // Check load more button
    let loadMoreContainer = document.getElementById('news-load-more-container');
    if (!loadMoreContainer) {
        loadMoreContainer = document.createElement('div');
        loadMoreContainer.id = 'news-load-more-container';
        loadMoreContainer.className = 'col-12 text-center mt-4';
        loadMoreContainer.innerHTML = '<button id="load-more-news-btn" class="btn btn-outline-primary px-4 py-2" onclick="loadMoreNews()">Load More News</button>';
        gridEl.insertAdjacentElement('afterend', loadMoreContainer);
    }
    
    if (allNews.length > visibleNewsCount) {
        loadMoreContainer.style.display = 'block';
    } else {
        loadMoreContainer.style.display = 'none';
    }

    // Apply equal height to cards in each row
    setTimeout(() => {
        equalizeCardHeights();
    }, 100);
}

function loadMoreNews() {
    visibleNewsCount += 8;
    renderNewsBatch();
}
function createNewsCard(article) {
    const col = document.createElement('div');
    col.className = 'col-lg-4 col-md-6';
    
    const imageUrl = article.urlToImage || `https://picsum.photos/seed/${article.title}/400/200.jpg`;
    
    col.innerHTML = `
        <a href="${article.url}" target="_blank" class="news-card-link">
            <div class="news-card">
                <img src="${imageUrl}" 
                     alt="${article.title}" 
                     class="news-card-image"
                     onerror="this.src='https://picsum.photos/seed/fallback/400/200.jpg'">
                <div class="news-card-body">
                    <h5 class="news-card-title">${article.title}</h5>
                    <div class="news-card-description-wrapper">
                        <p class="news-card-description">${article.description || 'No description available'}</p>
                    </div>
                    <div class="news-card-meta">
                        <span class="btn btn-primary btn-sm" >Read More</span>
                        <span class="news-card-date">${article.formattedDate}</span>
                    </div>
                </div>
            </div>
        </a>
    `;
    
    return col;
}

function equalizeCardHeights() {
    const rows = document.querySelectorAll('#newsGrid .row');
    if (!rows.length) return;
    
    // For each row group (on larger screens, 3 per row, on medium 2, on small 1)
    const cards = document.querySelectorAll('#newsGrid .news-card');
    
    // Group cards by their row position
    let maxHeight = 0;
    
    // First, reset all heights to auto
    cards.forEach(card => {
        card.style.height = 'auto';
    });
    
    // Then set the maximum height for all cards
    cards.forEach(card => {
        const height = card.offsetHeight;
        if (height > maxHeight) {
            maxHeight = height;
        }
    });
    
    // Apply the maximum height to all cards
    cards.forEach(card => {
        card.style.height = maxHeight + 'px';
    });
}

function refreshPopCultureNews() {
    fetchPopCultureNews();
}

// Initialize news fetching when page loads
document.addEventListener('DOMContentLoaded', function() {
    fetchPopCultureNews();
    window.addEventListener('resize', equalizeCardHeights);
});
</script>