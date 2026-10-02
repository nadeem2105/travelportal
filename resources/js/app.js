import './bootstrap';
import './analytics';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

/**
 * Global wishlist toggle. Available on every public page (cards call it), not
 * just the account wishlist page. Routes come from window.LeemrozRoutes, injected
 * by the base layout. Guests are sent to login. Backend: POST wishlist.toggle.
 */
window.toggleWishlist = async function (btn, id, type) {
    const routes = window.LeemrozRoutes || {};
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    if (!routes.wishlistToggle) return;
    try {
        const res = await fetch(routes.wishlistToggle, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ id, type }),
        });
        if (res.status === 401 || res.status === 419) {
            if (routes.login) window.location = routes.login;
            return;
        }
        if (res.ok) {
            const data = await res.json();
            if (btn) {
                btn.classList.toggle('active', data.status === 'added');
                btn.classList.add('scale-110');
                setTimeout(() => btn.classList.remove('scale-110'), 200);
            }
        }
    } catch (e) {
        // Silent — wishlist is a non-critical enhancement.
    }
};

Alpine.start();
