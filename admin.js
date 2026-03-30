    let currentPage = 1;

    async function loadAdminData(page = 1) {
        currentPage = page;
        try {
            const response = await fetch(`api_admin.php?page=${page}`);
            
            if (response.status === 403) {
                window.location.href = "login.html";
                return;
            }

            const data = await response.json();

            // 1. Nastavíme email admina
            document.getElementById('admin-email').textContent = data.admin_email;

            // 2. Vygenerujeme řádky tabulky
            const tableBody = document.getElementById('users-table-body');
            tableBody.innerHTML = ''; // Vyčistit tabulku

            data.users.forEach(user => {
                const row = `
                    <tr>
                        <td>${user.userId}</td>
                        <td>${user.email}</td>
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

            // 3. Generujeme pagination controls
            generatePagination(data.total, data.limit);

            // 4. Zobrazíme stránku
            document.body.style.display = 'block';

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

    // Spustíme načítání
    loadAdminData();