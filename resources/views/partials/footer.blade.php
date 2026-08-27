@php
    $footer = App\Models\Footer::getFooter();

    // Check if any contact info link is visible
    $hasAddress = $footer->address !== null && $footer->address !== '' && $footer->address !== '#';
    $hasFrequency = $footer->frequency !== null && $footer->frequency !== '' && $footer->frequency !== '#';
    $hasContactInfo = $hasAddress || $hasFrequency;
@endphp

<!-- Footer -->
<footer>
    <div class="container">
        <div class="row g-4 justify-content-center">
            <!-- Left Column: Branding & Social -->
            <div class="col-md-3">
                <div class="footer-column">
                    <h3 class="brand-font" style="font-size: 1.5rem; margin-bottom: 1rem;">{{ config('app.name') }}</h3>
                    <p style="font-size: 1rem; margin-bottom: 2rem; opacity: 0.9; line-height: 1.6;">Your favorite source for today's hottest music, news, and entertainment. Tune in 24/7.</p>
                    <div class="social-links">
                        <a href="https://www.facebook.com/92cle" title="Facebook" target="_blank" rel="noopener noreferrer">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="https://www.instagram.com/92cle" title="Instagram" target="_blank" rel="noopener noreferrer">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="https://x.com/92cle" title="Twitter" target="_blank" rel="noopener noreferrer">
                            <i class="fab fa-twitter"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Right Column: Quick Links -->
            <div class="col-md-3">
                <div class="footer-column quick-links-column">
                    <h3 style="font-size: 1.5rem; margin-bottom: 1rem;">Quick Links</h3>
                    <ul class="list-unstyled footer-links">
                        <!-- Home Link -->
                        <li>
                            <a href="#" onclick="event.preventDefault(); window.scrollTo({top: 0, behavior: 'smooth'});">
                                <i class="fas fa-chevron-right"></i> 
                                <span>Home</span>
                            </a>
                        </li>

                        <!-- News Link -->
                        <li>
                            <a href="#pop-culture-news">
                                <i class="fas fa-chevron-right"></i> 
                                <span>News</span>
                            </a>
                        </li>

                        <!-- Other links -->
                        <li>
                            <a href="{{ $footer->concerts_link_url }}">
                                <i class="fas fa-chevron-right"></i> 
                                <span>Concerts</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ $footer->events_link_url }}">
                                <i class="fas fa-chevron-right"></i> 
                                <span>Events</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ $footer->contact_link_url }}">
                                <i class="fas fa-chevron-right"></i> 
                                <span>Contact</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="copyright">
            &copy; 2026 Dreamzone Media. All Rights Reserved.
        </div>
    </div>
</footer>