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
        }
    } catch (error) {
        window.location.href = "login.html";
    }
}
checkTeacherAuth();