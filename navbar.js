function toggleMenu() {
    const menu = document.getElementById("userDropdown");
    menu.classList.toggle("hidden");
}

function logout() {
    alert("Odhlášen");
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