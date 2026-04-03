// Zkontroluj, zda má URL parametr ?registered=true
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('registered') === 'true') {
    const successMessage = document.getElementById('successMessage');
    if (successMessage) {
        successMessage.style.display = 'block';
    }
    // Odstraň parametr z URL aby se nezobrazoval pokaždé
    window.history.replaceState({}, document.title, window.location.pathname);
}

// Zkontroluj, zda má URL parametr ?logout=true
if (urlParams.get('logout') === 'true') {
    const logoutMessage = document.getElementById('logoutMessage');
    if (logoutMessage) {
        logoutMessage.style.display = 'block';
    }
    // Odstraň parametr z URL aby se nezobrazoval pokaždé
    window.history.replaceState({}, document.title, window.location.pathname);
}
