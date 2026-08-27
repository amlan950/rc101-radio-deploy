<nav class="navbar navbar-expand-lg navbar-light sticky-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="#top">
            <img src="{{ asset('/Landscape_Logo.png') }}" width="180px">
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="#top">
                        <i class="fas fa-home me-1"></i> Home
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#pop-culture-news">
                        <i class="fas fa-newspaper me-1"></i> News
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#concerts">
                        <i class="fas fa-music me-1"></i> Concerts
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#events">
                        <i class="fas fa-calendar-alt me-1"></i> Events
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#contest-section">
                        <i class="fas fa-trophy me-1"></i> Contest
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#contact">
                        <i class="fas fa-envelope me-1"></i> Contact
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle no-hover" href="#" id="navbarShare" role="button"
                        data-bs-toggle="dropdown" aria-expanded="false" title="Share">
<i class="fas fa-share-alt" style="color: inherit;"></i>                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0" aria-labelledby="navbarShare"
                        style="border-radius: 10px;">
                        <li>
                            <a class="dropdown-item py-2"
                                href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(url('/')) }}"
                                target="_blank" rel="noopener noreferrer">
                                <i class="fab fa-facebook text-primary me-2" style="font-size: 1.2rem;"></i> Facebook
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item py-2"
                                href="https://twitter.com/intent/tweet?url={{ urlencode(url('/')) }}"
                                target="_blank" rel="noopener noreferrer">
                                <i class="fab fa-twitter me-2" style="font-size: 1.2rem; color: #1DA1F2;"></i> X (Twitter)
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item py-2"
                                href="https://api.whatsapp.com/send?text={{ urlencode(url('/')) }}"
                                target="_blank" rel="noopener noreferrer">
                                <i class="fab fa-whatsapp text-success me-2" style="font-size: 1.2rem;"></i> WhatsApp
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item py-2" href="https://www.instagram.com/" target="_blank"
                                rel="noopener noreferrer"
                                onclick="shareToInstagram(event, '{{ url('/') }}')">
                                <i class="fab fa-instagram text-danger me-2" style="font-size: 1.2rem;"></i> Instagram
                            </a>
                        </li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li>
                            <a class="dropdown-item py-2" href="#"
                                onclick="copyShareLink(event, '{{ url('/') }}', 'Link copied to clipboard!')">
                                <i class="fas fa-link text-secondary me-2" style="font-size: 1.2rem;"></i> Copy Link
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Custom Share Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1080">
    <div id="shareToast" class="toast align-items-center text-white bg-success border-0" role="alert"
        aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body" id="shareToastMessage">Link copied to clipboard!</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"
                aria-label="Close"></button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    // ── Same-page navbar click handler ───────────────────────────────
    document.querySelectorAll('.navbar a[href^="#"]').forEach(link => {
        link.addEventListener('click', function (e) {
            const href = this.getAttribute('href');
            if (!href || href === '#') return;
            e.preventDefault();
            const targetId = href.substring(1);
            if (targetId === 'top') {
                window.scrollTo({ top: 0, behavior: 'smooth' });
                history.pushState(null, null, href);
                return;
            }
            try {
                const targetElement = document.getElementById(targetId);
                if (targetElement) {
                    const navbar = document.querySelector('.navbar');
                    const navbarHeight = navbar ? navbar.offsetHeight : 0;
                    const offsetPosition =
                        targetElement.getBoundingClientRect().top +
                        window.pageYOffset - navbarHeight - 40;
                    window.scrollTo({ top: offsetPosition, behavior: 'smooth' });
                    history.pushState(null, null, href);
                }
            } catch (err) {}
        });
    });

    // ── Hash-on-load: scroll AFTER all async loaders finish ──────────
    if (window.location.hash) {
        const targetId = window.location.hash.substring(1);
        let _scrollDone = false;
        let _fallbackTimer = null;

        function _doScroll() {
            const el = document.getElementById(targetId);
            if (!el) return;
            const navbar = document.querySelector('.navbar');
            const navbarHeight = navbar ? navbar.offsetHeight : 0;
            const offsetPosition =
                el.getBoundingClientRect().top +
                window.pageYOffset - navbarHeight - 40;
            window.scrollTo({ top: offsetPosition, behavior: 'instant' });
        }

        function _scrollToTarget() {
            if (_scrollDone) return;
            _scrollDone = true;
            clearTimeout(_fallbackTimer);

            // Initial scroll
            _doScroll();

            // Re-anchor silently every 300ms for ~1.8s to absorb late image/content shifts
            // behavior:'instant' makes these completely invisible to the user
            let attempts = 0;
            const reAnchor = setInterval(function () {
                attempts++;
                _doScroll();
                if (attempts >= 6) clearInterval(reAnchor);
            }, 300);
        }

        window._asyncLoadersPending = 3; // news + concerts + communityEvents

        window.notifyAsyncLoadComplete = function () {
            window._asyncLoadersPending--;
            if (window._asyncLoadersPending <= 0) {
                window._asyncLoadersPending = 0; // guard against going negative
                setTimeout(_scrollToTarget, 100);
            }
        };

        // Fallback if any loader fails or hangs after 5s
        _fallbackTimer = setTimeout(_scrollToTarget, 5000);

    } else {
        window.notifyAsyncLoadComplete = function () {};
    }

});

function showShareToast(message) {
    const toastMessage = document.getElementById('shareToastMessage');
    const toastEl = document.getElementById('shareToast');
    if (toastMessage && toastEl) {
        toastMessage.textContent = message || 'Link copied to clipboard!';
        const toast = new bootstrap.Toast(toastEl, { delay: 3000 });
        toast.show();
    }
}

function copyShareLink(event, url, message) {
    event.preventDefault();
    if (navigator.clipboard) {
        navigator.clipboard.writeText(url)
            .then(() => showShareToast(message))
            .catch(() => fallbackCopy(url, message));
    } else {
        fallbackCopy(url, message);
    }
}

function fallbackCopy(url, message) {
    const tempInput = document.createElement('input');
    tempInput.style = 'position: absolute; left: -1000px; top: -1000px';
    tempInput.value = url;
    document.body.appendChild(tempInput);
    tempInput.select();
    try {
        document.execCommand('copy');
        showShareToast(message);
    } catch (err) {}
    document.body.removeChild(tempInput);
}

function shareToInstagram(event, url) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(url).catch(() => fallbackCopy(url, ''));
    } else {
        fallbackCopy(url, '');
    }
    showShareToast('Link copied! Paste it on Instagram — opening Instagram now…');
}
</script>

<style>
.nav-link.no-hover::after {
    display: none !important;
}

#pop-culture-news,
#concerts,
#events,
#contest-section,
#contact {
    scroll-margin-top: 100px;
}

@media (max-width: 992px) {
    #pop-culture-news,
    #concerts,
    #events,
    #contest-section,
    #contact {
        scroll-margin-top: 80px;
    }
}
</style>