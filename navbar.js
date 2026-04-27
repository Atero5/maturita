function toggleMenu() {
    const menu = document.getElementById("userDropdown");
    menu.classList.toggle("hidden");
}

// Zavření dropdownu při kliknutí mimo
window.onclick = function(event) {
    if (!event.target.matches('.user-menu button')) {
        const dropdown = document.getElementById("userDropdown");
        if (!dropdown.classList.contains('hidden')) {
            dropdown.classList.add('hidden');
        }
    }
}

async function setupMyTripsButton() {
    const buttons = document.querySelectorAll('.menu button');
    const myTripsButton = Array.from(buttons).find(btn => btn.textContent.trim() === 'Výlety');
    if (!myTripsButton) return;

    myTripsButton.addEventListener('click', async () => {
        try {
            const res = await fetch('api_auth.php');
            const data = await res.json();

            if (!data.authenticated) {
                window.location.href = 'login.html';
                return;
            }

            if (data.role === 'teacher' || data.role === 'admin') {
                window.location.href = 'teacher.html';
            } else {
                window.location.href = 'user.html';
            }
        } catch (error) {
            window.location.href = 'login.html';
        }
    });
}

setupMyTripsButton();

async function setupNavbar() {
    try {
        const res = await fetch('api_auth.php');
        const data = await res.json();

        if (data.authenticated && (data.role === 'teacher' || data.role === 'admin')) {
            // Show calendar button for teachers/admins
            const calendarBtn = document.getElementById('calendarBtn');
            if (calendarBtn) {
                calendarBtn.style.display = 'block';
            }
            // Show add trip button for teachers/admins
            const addTripBtn = document.getElementById('navAddTrip');
            if (addTripBtn) {
                addTripBtn.style.display = 'block';
            }
        }
    } catch (error) {

    }
}

setupNavbar();