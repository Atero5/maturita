const urlParams = new URLSearchParams(window.location.search);
const tripId = urlParams.get('id');

if (!tripId) {
    alert('Neplatný výlet');
    window.history.back();
}

let userRole = '';

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

        // Nastavení logo linku podle role
        const logoLink = document.getElementById('logo-link');
        if (userRole === 'teacher' || userRole === 'admin') {
            logoLink.href = 'teacher.html';
            document.getElementById('navAddTrip').style.display = '';
        } else {
            logoLink.href = 'user.html';
        }

        // Tlačítko zpět
        document.getElementById('btnBack').addEventListener('click', () => {
            window.history.back();
        });

        document.body.style.visibility = 'visible';
        loadTripDetail();
    } catch (error) {
        window.location.href = 'login.html';
    }
}

async function loadTripDetail() {
    try {
        const response = await fetch(`api_trips.php?id=${tripId}`);
        const data = await response.json();

        if (!data.success) {
            document.getElementById('tripDetail').innerHTML = `
                <p style="text-align:center; color:#e74c3c; font-size:16px;">Výlet nenalezen</p>
            `;
            return;
        }

        renderDetail(data.trip);
    } catch (error) {
        console.error('Chyba při načítání detailu:', error);
        document.getElementById('tripDetail').innerHTML = `
            <p style="text-align:center; color:#e74c3c;">Chyba při načítání výletu</p>
        `;
    }
}

function formatDateTime(value) {
    if (!value) return '—';
    const date = new Date(value);
    if (isNaN(date)) return escapeHtml(value);
    return date.toLocaleString('cs-CZ', { day: 'numeric', month: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function formatCena(value) {
    return new Intl.NumberFormat('cs-CZ', {
        style: 'currency',
        currency: 'CZK',
        minimumFractionDigits: 0
    }).format(value || 0);
}

function mealBadge(type) {
    if (type === 'restaurace') {
        return '<span class="meal-badge meal-badge-restaurant">Restaurace</span>';
    }
    return '<span class="meal-badge meal-badge-own">Vlastní</span>';
}

function renderMealSection(title, type, restaurant, address, time) {
    let html = `
        <div class="detail-section">
            <h2 class="detail-section-title">${title}</h2>
            <div class="detail-grid">
                <div class="detail-item">
                    <span class="detail-label">Typ</span>
                    <span class="detail-value">${mealBadge(type)}</span>
                </div>
    `;

    if (type === 'restaurace') {
        html += `
                <div class="detail-item">
                    <span class="detail-label">Restaurace</span>
                    <span class="detail-value">${escapeHtml(restaurant || '—')}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Adresa restaurace</span>
                    <span class="detail-value">${escapeHtml(address || '—')}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Čas</span>
                    <span class="detail-value">${escapeHtml(time || '—')}</span>
                </div>
        `;
    }

    html += `
            </div>
        </div>
    `;
    return html;
}

function renderDetail(trip) {
    const container = document.getElementById('tripDetail');

    // Třídy
    const tridyHtml = (trip.tridy || []).map(t => `<span class="class-tag">${escapeHtml(t)}</span>`).join('');

    // Mapa
    const mapUrl = trip.adresa_ubytovani
        ? `https://maps.google.com/maps?q=${encodeURIComponent(trip.adresa_ubytovani)}&output=embed`
        : '';

    container.innerHTML = `
        <h1 class="trip-title">${escapeHtml(trip.nazev_vyletu || 'Bez názvu')}</h1>

        <!-- Základní info -->
        <div class="detail-section">
            <h2 class="detail-section-title">Základní informace</h2>
            <div class="detail-grid">
                <div class="detail-item">
                    <span class="detail-label">Adresa ubytování</span>
                    <span class="detail-value">${escapeHtml(trip.adresa_ubytovani || '—')}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Délka pobytu</span>
                    <span class="detail-value">${escapeHtml(trip.delka_pobytu || '—')}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Třídy</span>
                    <span class="detail-value">${tridyHtml || '—'}</span>
                </div>
            </div>
            ${mapUrl ? `<iframe class="detail-map" src="${mapUrl}" allowfullscreen></iframe>` : ''}
        </div>

        <!-- Doprava tam -->
        <div class="detail-section">
            <h2 class="detail-section-title">Doprava tam</h2>
            <div class="detail-grid">
                <div class="detail-item">
                    <span class="detail-label">Místo odjezdu</span>
                    <span class="detail-value">${escapeHtml(trip.misto_odjezdu_tam || '—')}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Datum a čas odjezdu</span>
                    <span class="detail-value">${formatDateTime(trip.cas_odjezdu_tam)}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Dopravní prostředek</span>
                    <span class="detail-value">${escapeHtml(trip.dopravni_prostredek_tam || '—')}</span>
                </div>
            </div>
        </div>

        <!-- Doprava zpět -->
        <div class="detail-section">
            <h2 class="detail-section-title">Doprava zpět</h2>
            <div class="detail-grid">
                <div class="detail-item">
                    <span class="detail-label">Místo odjezdu</span>
                    <span class="detail-value">${escapeHtml(trip.misto_odjezdu_zpet || '—')}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Datum a čas odjezdu</span>
                    <span class="detail-value">${formatDateTime(trip.cas_odjezdu_zpet)}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Dopravní prostředek</span>
                    <span class="detail-value">${escapeHtml(trip.dopravni_prostredek_zpet || '—')}</span>
                </div>
            </div>
        </div>

        <!-- Stravování -->
        ${renderMealSection('Snídaně', trip.typ_snidane, trip.nazev_restaurace_snidane, trip.adresa_restaurace_snidane, trip.cas_snidane)}
        ${renderMealSection('Oběd', trip.typ_obeda, trip.nazev_restaurace_obed, trip.adresa_restaurace_obed, trip.cas_obeda)}
        ${renderMealSection('Večeře', trip.typ_vecere, trip.nazev_restaurace_vecere, trip.adresa_restaurace_vecere, trip.cas_vecere)}

        <!-- Cena -->
        <div class="detail-section">
            <h2 class="detail-section-title">Cena</h2>
            <div class="price-box">
                <span class="price-amount">${formatCena(trip.celkova_cena)}</span>
                <span class="price-account">Číslo účtu: <strong>${escapeHtml(trip.cislo_uctu || '—')}</strong></span>
            </div>
        </div>
    `;
}

function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

init();
