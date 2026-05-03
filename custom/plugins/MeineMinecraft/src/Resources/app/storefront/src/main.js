// ─── Header: homepage transparency ─────────────────────────────────────────
// We use `pageshow` instead of `DOMContentLoaded` so this also runs when the
// browser restores the page from the back/forward cache (bfcache). That cache
// preserves the painted DOM but fires pageshow again, giving us a chance to
// re-apply the correct class based on the *current* URL.
function updateHeaderHomepageClass() {
    const header = document.getElementById('my-header');
    if (!header) return;

    // Shopware home page is always exactly "/"
    const isHome = window.location.pathname === '/'
        || window.location.pathname === '/index.php'; // safety fallback

    header.classList.toggle('is-homepage', isHome);
}

// Fires on normal load AND on bfcache restore (persisted === true)
window.addEventListener('pageshow', updateHeaderHomepageClass);
