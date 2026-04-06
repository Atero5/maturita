
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('registered') === 'true') {
    const successMessage = document.getElementById('successMessage');
    if (successMessage) {
        successMessage.style.display = 'block';
    }
    
    window.history.replaceState({}, document.title, window.location.pathname);
}


if (urlParams.get('logout') === 'true') {
    const logoutMessage = document.getElementById('logoutMessage');
    if (logoutMessage) {
        logoutMessage.style.display = 'block';
    }
    
    window.history.replaceState({}, document.title, window.location.pathname);
}
