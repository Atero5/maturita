        // Funkce, která se spustí hned při načtení
        async function checkAuth() {
            try {
                // Zavoláme naše PHP API
                const response = await fetch('api_auth.php');
                const data = await response.json();

                if (!data.authenticated) {
                    // Pokud není přihlášen, přesměrujeme na login
                    window.location.href = "login.html";
                } else {
                    // Pokud je přihlášen, doplníme e-mail do tlačítka
                    document.getElementById('user-email').textContent = data.email;
                    // Zobrazíme stránku
                    document.body.style.visibility = 'visible';
                }
            } catch (error) {
                console.error("Chyba při komunikaci s API:", error);
                // V případě chyby raději přesměrujeme na login
                window.location.href = "login.html";
            }
        }

        // Spustíme kontrolu
        checkAuth();