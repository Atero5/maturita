async function checkTeacherAuth() {
    try {
        const response = await fetch('api_auth.php');
        const data = await response.json();

        // Kontrola: 1. Musí být přihlášen, 2. Musí mít roli teacher (nebo admin)
        if (!data.authenticated || (data.role !== 'teacher' && data.role !== 'admin')) {
            window.location.href = "login.html";
        } else {
            // Pokud je to učitel, vypíšeme e-mail
            document.getElementById('user-email').textContent = data.email;
            document.body.style.visibility = 'visible';
            
            // Načtení výletů
            loadTrips();
        }
    } catch (error) {
        window.location.href = "login.html";
    }
}

let allTrips = [];

async function loadTrips() {
    try {
        const response = await fetch('api_trips.php');
        const data = await response.json();
        
        if (data.success && data.trips.length > 0) {
            allTrips = data.trips;
            filterAndRenderTrips();
        } else {
            allTrips = [];
            renderNoTripsMessage();
        }
    } catch (error) {
        console.error('Chyba při načítání výletů:', error);
        allTrips = [];
        renderNoTripsMessage();
    }
}

function filterAndRenderTrips() {
    const searchInput = document.getElementById('searchInput');
    const query = searchInput ? searchInput.value.toLowerCase().trim() : '';

    if (query === '') {
        renderTripsCards(allTrips);
        return;
    }

    const filtered = allTrips.filter(trip => {
        const nazev = (trip.nazev || '').toLowerCase();
        return nazev.includes(query);
    });

    if (filtered.length > 0) {
        renderTripsCards(filtered);
    } else {
        const container = document.getElementById('tripsContainer');
        container.innerHTML = `
            <div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: #999;">
                <p style="font-size: 16px;">Žádné výlety neodpovídají vyhledávání</p>
            </div>
        `;
    }
}

function renderTripsCards(trips) {
    const container = document.getElementById('tripsContainer');
    container.innerHTML = '';
    
    trips.forEach(trip => {
        const card = document.createElement('div');
        card.className = 'trip-card';
        
        // Formátování ceny
        const cenaFormatted = new Intl.NumberFormat('cs-CZ', {
            style: 'currency',
            currency: 'CZK',
            minimumFractionDigits: 0
        }).format(trip.cena || 0);
        
        card.innerHTML = `
            <div class="trip-card-main">
                <a href="trip-detail.html?id=${trip.id}" style="text-decoration: none; color: inherit;">
                    <h2 class="trip-card-title">${escapeHtml(trip.nazev)}</h2>
                    <div class="trip-card-info">
                        <div class="trip-card-row">
                            <span class="trip-card-label">Délka pobytu:</span>
                            <span class="trip-card-value">${escapeHtml(trip.delka_pobytu || 'N/A')}</span>
                        </div>
                        <div class="trip-card-row">
                            <span class="trip-card-label">Místo odjezdu:</span>
                            <span class="trip-card-value">${escapeHtml(trip.misto || 'N/A')}</span>
                        </div>
                        <div class="trip-card-row">
                            <span class="trip-card-label">Čas odjezdu:</span>
                            <span class="trip-card-value">${escapeHtml(trip.cas || 'N/A')}</span>
                        </div>
                    </div>
                </a>
                <div class="trip-card-footer">
                    <div class="trip-card-price">Cena: ${cenaFormatted}</div>
                    <div class="trip-card-actions">
                        <button type="button" class="trip-card-button trip-card-button-edit" onclick="editTrip(event, ${trip.id})">Upravit</button>
                        <button type="button" class="trip-card-button trip-card-button-delete" onclick="deleteTrip(event, ${trip.id})">Smazat</button>
                    </div>
                </div>
            </div>
        `;
        
        container.appendChild(card);
    });
}

function renderNoTripsMessage() {
    const container = document.getElementById('tripsContainer');
    container.innerHTML = `
        <div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: #999;">
            <p style="font-size: 16px;">Zatím nejsou k dispozici žádné výlety</p>
        </div>
    `;
}

// Ochrana proti XSS útokům
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

// Funkce pro úpravu výletu
function editTrip(event, tripId) {
    event.stopPropagation(); // Zastaví propagaci události na rodičovské prvky
    window.location.href = `Change_trip.html?id=${tripId}`;
}

// Funkce pro smazání výletu
async function deleteTrip(event, tripId) {
    event.stopPropagation();
    event.preventDefault();
    if (!confirm('Opravdu chcete smazat tento výlet?')) return;

    try {
        const response = await fetch('api_trips.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: tripId })
        });
        const data = await response.json();

        if (data.success) {
            await loadTrips();
            alert('Výlet byl úspěšně smazán');
        } else {
            alert(data.message || 'Chyba při mazání výletu');
        }
    } catch (error) {
        console.error('Chyba při mazání výletu:', error);
        alert('Chyba při mazání výletu');
    }
}

checkTeacherAuth();

// Vyhledávání výletů v reálném čase
document.getElementById('searchInput')?.addEventListener('input', filterAndRenderTrips);