@php
    // Professional device detection
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $isIOS = stripos($userAgent, 'iPhone') !== false || stripos($userAgent, 'iPad') !== false;
    $isAndroid = stripos($userAgent, 'Android') !== false;
    $isMobile = $isIOS || $isAndroid;
    
    // App store URLs — TODO: move to admin-managed affiliate/external link settings
    // (see Front-Page Administration requirements) once that backend field exists.
    // Falls back to a generic store search for this station's App Name so the
    // link is never a dead '#' in the meantime.
    $appStoreSearch = urlencode(config('app.name'));
    $appStoreURL = $isIOS ? (config('app.ios_app_url') ?: 'https://apps.apple.com/us/search?term=' . $appStoreSearch) :
                   ($isAndroid ? (config('app.android_app_url') ?: 'https://play.google.com/store/search?q=' . $appStoreSearch . '&c=apps') : '#');

    // Messaging uses the configurable App Name — no station name is hardcoded here.
    $bannerTitle = config('app.name');
    $bannerSubtitle = $isMobile ? 'Listen anywhere, anytime' : 'Get the app for the best experience';
    $buttonText = $isIOS ? 'Download on the App Store' :
                  ($isAndroid ? 'Get it on Google Play' : 'Download App');

    // Generate unique banner ID for this session
    $bannerId = 'app-banner-' . uniqid();
@endphp

@if($isMobile)
<!-- Professional App Download Banner -->
<div id="{{ $bannerId }}" class="professional-app-banner" data-banner-id="{{ $bannerId }}">
    <div class="app-banner-container">
        <div class="app-banner-logo">
            <div class="app-logo-wrapper">
                <i class="fas fa-broadcast-tower"></i>
            </div>
        </div>
        
        <div class="app-banner-content">
            <div class="app-banner-title">{{ $bannerTitle }}</div>
            <div class="app-banner-subtitle">{{ $bannerSubtitle }}</div>
        </div>
        
        <div class="app-banner-actions">
            <a href="{{ $appStoreURL }}" class="app-banner-cta" target="_blank" rel="noopener noreferrer">
                <i class="fas fa-download"></i>
                <span class="cta-text">{{ $buttonText }}</span>
                <i class="fas fa-external-link-alt cta-icon"></i>
            </a>
            <button class="app-banner-dismiss" aria-label="Dismiss banner">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
</div>

{{-- Inlined here (not @push('styles')) because this component renders inside <body>,
     after the layout's <head> — where @stack('styles') already echoed — has been output. --}}
<style>
.professional-app-banner {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 10000;
    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%) !important;
    border-bottom: 3px solid #667eea !important;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15) !important;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    transform: translateY(0);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    opacity: 1 !important;
    display: block !important;
}

.professional-app-banner.hidden {
    transform: translateY(-100%);
    opacity: 0;
}

.app-banner-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 16px;
    display: flex;
    align-items: center;
    min-height: 64px;
    gap: 16px;
}

.app-banner-logo {
    flex-shrink: 0;
}

.app-logo-wrapper {
    width: 44px;
    height: 44px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    transition: transform 0.2s ease;
}

.app-logo-wrapper:hover {
    transform: scale(1.05);
}

.app-logo-wrapper i {
    color: white;
    font-size: 22px;
    font-weight: 600;
}

.app-banner-content {
    flex: 1;
    min-width: 0;
}

.app-banner-title {
    font-size: 16px;
    font-weight: 600;
    color: #1a1a1a;
    margin-bottom: 2px;
    letter-spacing: -0.01em;
}

.app-banner-subtitle {
    font-size: 13px;
    color: #6b7280;
    font-weight: 400;
    line-height: 1.4;
}

.app-banner-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
}

.app-banner-cta {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
    color: white !important;
    border: none;
    padding: 12px 24px !important;
    border-radius: 25px;
    font-size: 16px !important;
    font-weight: 700 !important;
    text-decoration: none;
    display: inline-flex !important;
    align-items: center;
    gap: 8px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4) !important;
    white-space: nowrap;
    cursor: pointer;
    opacity: 1 !important;
    visibility: visible !important;
}

.app-banner-cta:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 16px rgba(102, 126, 234, 0.4);
    background: linear-gradient(135deg, #5a67d8 0%, #6b46c1 100%);
}

.app-banner-cta:active {
    transform: translateY(0);
}

.cta-text {
    font-weight: 500;
}

.cta-icon {
    font-size: 11px;
    opacity: 0.8;
}

.app-banner-dismiss {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    border: none;
    background: rgba(0, 0, 0, 0.04);
    color: #6b7280;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    flex-shrink: 0;
}

.app-banner-dismiss:hover {
    background: rgba(0, 0, 0, 0.08);
    color: #374151;
    transform: scale(1.05);
}

.app-banner-dismiss:active {
    transform: scale(0.95);
}

/* Body padding adjustment — the actual value is set dynamically in JS
   (syncBodyPadding) since the banner's height varies by viewport width
   (it wraps to two rows on narrow screens). This is just a fallback for the
   brief instant before JS runs. */
body.has-professional-app-banner {
    padding-top: 64px;
}

/* Responsive design */
@media (max-width: 640px) {
    .app-banner-container {
        padding: 0 12px;
        min-height: 60px;
        gap: 12px;
    }
    
    .app-logo-wrapper {
        width: 40px;
        height: 40px;
    }
    
    .app-logo-wrapper i {
        font-size: 20px;
    }
    
    .app-banner-title {
        font-size: 15px;
    }
    
    .app-banner-subtitle {
        font-size: 12px;
    }
    
    .app-banner-cta {
        padding: 6px 12px;
        font-size: 12px;
    }
    
    .app-banner-dismiss {
        width: 28px;
        height: 28px;
    }
    
    body.has-professional-app-banner {
        padding-top: 60px;
    }
}

@media (max-width: 480px) {
    .app-banner-subtitle {
        display: none;
    }

    .app-banner-cta .cta-icon {
        display: none;
    }

    .app-banner-cta {
        padding: 6px 10px;
    }

    /* At narrow widths the CTA's natural (nowrap) text width otherwise squeezes
       the title/logo down to zero width instead of shrinking itself — wrap the
       actions onto their own row instead. */
    .app-banner-title {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .app-banner-container {
        flex-wrap: wrap;
        row-gap: 8px;
    }

    .app-banner-content {
        flex-basis: 100%;
        order: 1;
    }

    .app-banner-logo {
        order: 0;
    }

    .app-banner-actions {
        order: 2;
        flex-basis: 100%;
        justify-content: space-between;
    }
}
</style>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const banner = document.getElementById('{{ $bannerId }}');
    if (!banner) return;

    // Station-namespaced so a dismissal on one station never suppresses the
    // banner on another station sharing this codebase/origin.
    const storageKey = (window.skey ? window.skey('app_banner_dismissed_at') : 'app_banner_dismissed_at');
    const dismissedFor = 7 * 24 * 60 * 60 * 1000; // re-show a week after dismissal

    let dismissedAt = 0;
    try { dismissedAt = parseInt(localStorage.getItem(storageKey) || '0', 10); } catch (e) {}

    if (dismissedAt && (Date.now() - dismissedAt) < dismissedFor) {
        return; // recently dismissed — stay hidden
    }

    document.body.classList.add('has-professional-app-banner');

    // The banner can wrap to a second row on narrow screens, so its height isn't
    // fixed — measure it instead of relying on a single hardcoded padding value.
    function syncBodyPadding() {
        document.body.style.paddingTop = banner.offsetHeight + 'px';
    }
    syncBodyPadding();
    window.addEventListener('resize', syncBodyPadding);

    const dismissBtn = banner.querySelector('.app-banner-dismiss');
    if (dismissBtn) {
        dismissBtn.addEventListener('click', function() {
            banner.classList.add('hidden');
            document.body.classList.remove('has-professional-app-banner');
            document.body.style.paddingTop = '';
            window.removeEventListener('resize', syncBodyPadding);

            try { localStorage.setItem(storageKey, Date.now().toString()); } catch (e) {}

            setTimeout(() => {
                if (banner.parentNode) {
                    banner.parentNode.removeChild(banner);
                }
            }, 300);
        });
    }
});
</script>
@endpush
@endif
