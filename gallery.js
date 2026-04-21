let userRole = '';
let allTrips = [];

async function init() {
    try {
        const authRes = await fetch('api_auth.php');
        const authData = await authRes.json();

        if (!authData.authenticated) {
            window.location.href = 'login.html';
            return;
        }

        userRole = authData.role;
        document.getElementById('user-email').textContent = authData.email;

        const logoLink = document.getElementById('logo-link');
        if (userRole === 'teacher' || userRole === 'admin') {
            logoLink.href = 'home_teacher.html';
            document.getElementById('navAddTrip').style.display = '';
        } else {
            logoLink.href = 'home_user.html';
        }

        document.body.style.visibility = 'visible';
        loadTrips();

        document.getElementById('searchInput').addEventListener('input', function () {
            filterAndRenderTrips();
        });
    } catch (error) {
        window.location.href = 'login.html';
    }
}

async function loadTrips() {
    try {
        const res = await fetch('api_trips.php');
        const data = await res.json();

        if (!data.success || !data.trips || data.trips.length === 0) {
            document.getElementById('tripsContainer').innerHTML = '<p class="no-trips">Žádné výlety k zobrazení.</p>';
            return;
        }

        allTrips = data.trips.filter(trip => {
            const returnDate = trip.cas_odjezdu_zpet ? new Date(trip.cas_odjezdu_zpet) : null;
            return returnDate && returnDate <= new Date();
        });
        filterAndRenderTrips();
    } catch (error) {
        document.getElementById('tripsContainer').innerHTML = '<p class="no-trips">Chyba při načítání výletů.</p>';
    }
}

function filterAndRenderTrips() {
    const query = document.getElementById('searchInput').value.toLowerCase().trim();
    const filtered = allTrips.filter(trip => {
        const name = (trip.nazev || trip.nazev_vyletu || '').toLowerCase();
        return name.includes(query);
    });

    if (filtered.length === 0) {
        document.getElementById('tripsContainer').innerHTML = '<p class="no-trips">Žádné výlety neodpovídají hledání.</p>';
    } else {
        renderTrips(filtered);
    }
}

function renderTrips(trips) {
    const container = document.getElementById('tripsContainer');

    const html = '<div class="gallery-list">' + trips.map(trip => {
        const dateStr = trip.cas_odjezdu_zpet ? formatDateTime(trip.cas_odjezdu_zpet) : 'Datum neuvedeno';

        return `
            <div class="gallery-item">
                <div class="gallery-item-info">
                    <span class="gallery-item-name">${escapeHtml(trip.nazev || trip.nazev_vyletu || '')}</span>
                    <span class="gallery-item-date">Návrat: ${dateStr}</span>
                </div>
                <div class="gallery-item-right">
                    ${trip.photo_count > 0 ? `<span class="photo-count-badge">${trip.photo_count} ${trip.photo_count === 1 ? 'fotka' : trip.photo_count < 5 ? 'fotky' : 'fotek'}</span>` : ''}
                    <div class="gallery-item-buttons">
                        <button class="btn-gallery btn-add-photo" onclick="goUpload(${trip.id || trip.vyletId})">Přidat fotku</button>
                        <button class="btn-gallery btn-view-photos" onclick="goPhotos(${trip.id || trip.vyletId})">Zobrazit fotky</button>
                    </div>
                </div>
            </div>
        `;
    }).join('') + '</div>';

    container.innerHTML = html;
}

function goUpload(tripId) {
    window.location.href = 'trip_photos.html?id=' + tripId + '&upload=1';
}

function goPhotos(tripId) {
    window.location.href = 'trip_photos.html?id=' + tripId;
}

function formatDateTime(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    return d.toLocaleDateString('cs-CZ', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

init();
