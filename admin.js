    let currentPage = 1;




    async function loadAdminData(page = 1) {
        currentPage = page;
        const search = document.getElementById('filter-email')?.value.trim() || '';
        const params = new URLSearchParams({ page, search });
        try {
            const response = await fetch(`api_admin.php?${params}`);
            if (response.status === 403) {
                window.location.href = "login.html";
                return;
            }
            const data = await response.json();
            document.getElementById('admin-email').textContent = data.admin_email;
            const tableBody = document.getElementById('users-table-body');
            tableBody.innerHTML = '';
            data.users.forEach(user => {
                const row = `
                    <tr>
                        <td>${user.userId}</td>
                        <td>${user.email}</td>
                        <td>
                            <form method="post" action="update_class.php" style="display:inline;">
                                <input type="hidden" name="id" value="${user.userId}">
                                <input type="text" name="class" value="${user.class || ''}" style="width:60px;">
                                <button type="submit" style="padding:2px 8px;">Uložit</button>
                            </form>
                        </td>
                        <td>${user.role}</td>
                        <td>
                            <a href="change_role.php?id=${user.userId}&role=student">Student</a> |
                            <a href="change_role.php?id=${user.userId}&role=teacher">Teacher</a> |
                            <a href="change_role.php?id=${user.userId}&role=admin">Admin</a> |
                            <a href="delete_user.php?id=${user.userId}"
                               class="btn-delete"
                               onclick="return confirm('Opravdu chcete smazat tohoto uživatele?');">
                               Smazat
                            </a>
                        </td>
                    </tr>
                `;
                tableBody.innerHTML += row;
            });
            generatePagination(data.total, data.limit);
            document.body.style.display = 'block';
            loadAdminTrips();
        } catch (error) {
            console.error("Chyba:", error);
            alert("Nepodařilo se načíst data.");
        }
    }

    function generatePagination(total, limit) {
        const totalPages = Math.ceil(total / limit);
        let controls = '';

        if (currentPage > 1) {
            controls += `<button onclick="loadAdminData(${currentPage - 1})">← Předchozí</button> `;
        }

        for (let i = 1; i <= totalPages; i++) {
            if (i === currentPage) {
                controls += `<strong style="margin: 0 5px;">[${i}]</strong>`;
            } else {
                controls += `<button onclick="loadAdminData(${i})" style="margin: 0 2px;">${i}</button>`;
            }
        }

        if (currentPage < totalPages) {
            controls += ` <button onclick="loadAdminData(${currentPage + 1})">Další →</button>`;
        }

        document.getElementById('pagination-controls').innerHTML = controls;
    }

    // načítání
    loadAdminData();

    // ========== VÝLETY ==========

    let currentTripsPage = 1;

    async function loadAdminTrips(page = 1) {
        currentTripsPage = page;
        try {
            const response = await fetch(`api_admin_trips.php?page=${page}`);
            if (response.status === 403) return;

            const data = await response.json();
            if (!data.success) return;

            const tableBody = document.getElementById('trips-table-body');
            tableBody.innerHTML = '';

            data.trips.forEach(trip => {
                const cenaFormatted = new Intl.NumberFormat('cs-CZ', {
                    style: 'currency', currency: 'CZK', minimumFractionDigits: 0
                }).format(trip.cena || 0);

                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${trip.id}</td>
                    <td>${escapeHtml(trip.nazev)}</td>
                    <td>${escapeHtml(trip.delka_pobytu || '')}</td>
                    <td>${escapeHtml(trip.misto || '')}</td>
                    <td>${cenaFormatted}</td>
                    <td>${escapeHtml(trip.tridy || '')}</td>
                    <td>
                        <a href="#" class="btn-delete" onclick="deleteTrip(${trip.id}); return false;">Smazat</a>
                    </td>
                `;
                tableBody.appendChild(row);
            });

            generateTripsPagination(data.total, data.limit);
        } catch (error) {
            console.error('Chyba při načítání výletů:', error);
        }
    }

    function generateTripsPagination(total, limit) {
        const totalPages = Math.ceil(total / limit);
        let controls = '';

        if (currentTripsPage > 1) {
            controls += `<button onclick="loadAdminTrips(${currentTripsPage - 1})">← Předchozí</button> `;
        }
        for (let i = 1; i <= totalPages; i++) {
            if (i === currentTripsPage) {
                controls += `<strong style="margin: 0 5px;">[${i}]</strong>`;
            } else {
                controls += `<button onclick="loadAdminTrips(${i})" style="margin: 0 2px;">${i}</button>`;
            }
        }
        if (currentTripsPage < totalPages) {
            controls += ` <button onclick="loadAdminTrips(${currentTripsPage + 1})">Další →</button>`;
        }

        document.getElementById('trips-pagination-controls').innerHTML = controls;
    }

    async function deleteTrip(id) {
        if (!confirm('Opravdu chcete smazat tento výlet?')) return;

        try {
            const response = await fetch('api_admin_trips.php', {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            });
            const data = await response.json();
            if (data.success) {
                loadAdminTrips(currentTripsPage);
            } else {
                alert('Chyba: ' + (data.message || 'Nepodařilo se smazat výlet.'));
            }
        } catch (error) {
            console.error('Chyba při mazání výletu:', error);
        }
    }

    async function openEditModal(id) {
        try {
            const response = await fetch(`api_admin_trips.php?id=${id}`);
            const data = await response.json();
            if (!data.success) return;


            const trip = data.trip;
            document.getElementById('edit-trip-id').value = trip.id ?? '';
            document.getElementById('edit-nazev').value = trip.nazev ?? '';
            document.getElementById('edit-adresa').value = trip.adresa ?? '';
            document.getElementById('edit-delka').value = trip.delka_pobytu ?? '';
            document.getElementById('edit-harmonogram').value = trip.harmonogram ?? '';
            document.getElementById('edit-ucitele').value = trip.ucitele ?? '';
            document.getElementById('edit-misto').value = trip.misto ?? '';
            document.getElementById('edit-cas').value = trip.cas ?? '';
            document.getElementById('edit-doprava').value = trip.doprava ?? '';
            document.getElementById('edit-misto-zpet').value = trip.misto_zpet ?? '';
            document.getElementById('edit-cas-zpet').value = trip.cas_zpet ?? '';
            document.getElementById('edit-doprava-zpet').value = trip.doprava_zpet ?? '';
            document.getElementById('edit-cena').value = trip.cena ?? '';
            document.getElementById('edit-cislo-uctu').value = trip.cislo_uctu ?? '';

            document.getElementById('editTripModal').classList.add('active');
        } catch (error) {
            console.error('Chyba při načítání výletu:', error);
        }
    }

    function closeEditModal() {
        document.getElementById('editTripModal').classList.remove('active');
    }

    async function saveTrip() {
        const id = document.getElementById('edit-trip-id').value;
        const tripData = {
            id: parseInt(id),
            nazev: document.getElementById('edit-nazev').value,
            adresa: document.getElementById('edit-adresa').value,
            delka_pobytu: document.getElementById('edit-delka').value,
            harmonogram: document.getElementById('edit-harmonogram').value,
            ucitele: document.getElementById('edit-ucitele').value,
            misto: document.getElementById('edit-misto').value,
            cas: document.getElementById('edit-cas').value,
            doprava: document.getElementById('edit-doprava').value,
            misto_zpet: document.getElementById('edit-misto-zpet').value,
            cas_zpet: document.getElementById('edit-cas-zpet').value,
            doprava_zpet: document.getElementById('edit-doprava-zpet').value,
            cena: document.getElementById('edit-cena').value,
            cislo_uctu: document.getElementById('edit-cislo-uctu').value
        };

        try {
            const response = await fetch('api_admin_trips.php', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(tripData)
            });
            const data = await response.json();
            if (data.success) {
                closeEditModal();
                loadAdminTrips(currentTripsPage);
            } else {
                alert('Chyba: ' + (data.message || 'Nepodařilo se uložit.'));
            }
        } catch (error) {
            console.error('Chyba při ukládání výletu:', error);
        }
    }

    function escapeHtml(text) {
        if (!text) return '';
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(text).replace(/[&<>"']/g, m => map[m]);
    }