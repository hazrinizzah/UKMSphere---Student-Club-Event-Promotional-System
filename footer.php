<style>
    /* 1. Server Time Display (Bottom Left) */
    .server-time-container {
        position: fixed;
        bottom: 20px;
        left: 20px;
        background-color: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(4px);
        padding: 8px 16px;
        border-radius: 50px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        border: 1px solid #e5e7eb;
        font-family: monospace;
        font-size: 0.85rem;
        color: #374151;
        z-index: 9999;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: opacity 0.3s ease;
    }

    .status-dot {
        width: 8px;
        height: 8px;
        background-color: #10b981; /* Green */
        border-radius: 50%;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
        70% { box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
        100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
    }

    /* 2. Loading Animation Box (Bottom Right) */
    .page-loading-pill {
        position: fixed;
        bottom: 25px;
        right: 25px;
        z-index: 9999;
        background-color: white;
        padding: 10px 20px; /* Wider padding for pill shape */
        border-radius: 50px; /* Pill shape */
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15), 0 5px 10px -5px rgba(0, 0, 0, 0.05);
        border: 1px solid #f3f4f6;
        
        display: flex;
        align-items: center;
        gap: 12px;
        
        opacity: 0;
        visibility: hidden;
        transform: translateY(20px);
        transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1); /* Smooth elastic easing */
        
        font-family: sans-serif;
        font-size: 0.9rem;
        font-weight: 600;
        color: #4b5563;
    }

    .page-loading-pill.active {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }

    .spinner {
        width: 18px;
        height: 18px;
        border: 2.5px solid #e5e7eb;
        border-top: 2.5px solid var(--ukm-blue, #003878); /* Fallback to UKM Blue */
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
</style>

<div class="server-time-container">
    <div class="status-dot"></div>
    <span id="live-server-clock">Loading Clock...</span>
</div>

<div id="bottom-right-loader" class="page-loading-pill">
    <div class="spinner"></div>
    <span>Loading...</span>
</div>

<script>
    // --- 1. LIVE CLOCK LOGIC (Uses Device Time) ---
    document.addEventListener('DOMContentLoaded', () => {
        function updateClock() {
            // Use 'new Date()' to get the user's current device time directly
            const now = new Date();
            
            const options = { 
                weekday: 'short', 
                day: '2-digit', 
                month: 'short', 
                year: 'numeric', 
                hour: '2-digit', 
                minute: '2-digit', 
                second: '2-digit',
                hour12: true 
            };
            
            const clockElement = document.getElementById('live-server-clock');
            if(clockElement) {
                // 'en-MY' tries to format it like Malaysian standard, 
                // but it will display the User's local time value.
                clockElement.innerText = now.toLocaleString('en-MY', options);
            }
        }

        // Update immediately, then every second
        updateClock(); 
        setInterval(updateClock, 1000);
    });

    // --- 2. LOADING ANIMATION LOGIC ---
    document.addEventListener('DOMContentLoaded', function() {
        const brLoader = document.getElementById('bottom-right-loader');
        
        // Hide loader when page is fully loaded
        window.addEventListener('load', function() {
            if(brLoader) brLoader.classList.remove('active');
        });

        // Trigger on Link Click
        document.addEventListener('click', function(e) {
            const link = e.target.closest('a');
            
            // Filter valid internal links
            if (link && link.href && !link.target && !link.hasAttribute('download') && 
                !e.ctrlKey && !e.metaKey && link.getAttribute('href') !== '#' && 
                !link.href.includes('javascript:') && !link.href.includes('mailto:')) {
                
                if (link.hostname === window.location.hostname) {
                    if(brLoader) brLoader.classList.add('active');
                }
            }
        });

        // Trigger on Form Submit
        document.addEventListener('submit', function(e) {
            const form = e.target;
            if (!form.target && brLoader) {
                brLoader.classList.add('active');
            }
        });

        // Back Button Fix (bfcache)
        window.addEventListener('pageshow', function(event) {
            if (event.persisted && brLoader) {
                brLoader.classList.remove('active');
            }
        });
    });
    
    // --- 3. HEADER SCROLL EFFECT ---
    document.addEventListener('DOMContentLoaded', () => {
        const header = document.querySelector('.dashboard-header');
        if (!header) return;

        window.addEventListener('scroll', () => {
            if (window.scrollY > 10) {
                header.classList.add('header-scrolled');
            } else {
                header.classList.remove('header-scrolled');
            }
        }, { passive: true });
    });
</script>

</body>
</html>