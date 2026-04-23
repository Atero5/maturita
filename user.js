        // Funkce, která se spustí hned při načtení
        async function checkAuth() {
            try {
                // Zavolám naše PHP API
                const response = await fetch('api_auth.php');
                const data = await response.json();

                if (!data.authenticated) {
                    // Pokud není přihlášen, přesměruje na login
                    window.location.href = "login.html";
                } else {
                    // Pokud je přihlášen, doplní e-mail do tlačítka
                    document.getElementById('user-email').textContent = data.email;
                    // Zobrazíme stránku
                    document.body.style.visibility = 'visible';
                    
                    // Načtení výletů
                    loadTrips();
                }
            } catch (error) {
                console.error("Chyba při komunikaci s API:", error);
                // V případě chyby raději přesměruje na login
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

        function formatDateTime(value) {
            if (!value) return 'N/A';
            const date = new Date(value);
            if (isNaN(date)) return value;
            return date.toLocaleString('cs-CZ', { day: 'numeric', month: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' });
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
                
                // Náhledový obrázek - vlevo, nebo text "Obrázek není k dispozici"
                const imageHtml = trip.nahledovy_obrazek 
                    ? `<div class="trip-card-image"><img src="${escapeHtml(trip.nahledovy_obrazek)}" alt="Náhled"></div>` 
                    : `<div class="trip-card-image trip-card-no-image"><span>Obrázek není k dispozici</span></div>`;
                
                card.innerHTML = `
                    <a href="trip_detail.html?id=${trip.id}" style="text-decoration: none; color: inherit; display: flex; gap: 15px;">
                        ${imageHtml}
                        <div class="trip-card-content">
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
                                    <span class="trip-card-value">${escapeHtml(formatDateTime(trip.cas))}</span>
                                </div>
                            </div>
                            <div class="trip-card-price">Cena: ${cenaFormatted}</div>
                        </div>
                    </a>
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

        // Spustí kontrolu
        checkAuth();

        // Vyhledávání výletů v reálném čase
        document.getElementById('searchInput')?.addEventListener('input', filterAndRenderTrips);