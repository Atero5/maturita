// Původní funkce pro mapu
function zobrazMapu() {
            let adresa = document.getElementById("address").value;
            if (adresa === "") {
                alert("Zadej adresu!");
                return;
            }

            let url = "https://maps.google.com/maps?q=" + encodeURIComponent(adresa) + "&output=embed";

            let mapa = document.getElementById("mapa");
            mapa.src = url;

            // 👇 zobrazí mapu
            mapa.classList.remove("hidden");
        }

// Skript pro přepínání skrytých polí + required pro restauraci
const radios = document.querySelectorAll("input[type=radio]");
radios.forEach(radio => {
    radio.addEventListener("change", function(){
        const group = document.querySelectorAll(`input[name="${this.name}"]`);
        group.forEach(r => {
            if(r.dataset.target){
                const box = document.getElementById(r.dataset.target);
                box.classList.add("hidden");
                // Odebrat required z inputů když je skrytý
                box.querySelectorAll("input").forEach(input => input.required = false);
            }
        });
        if(this.dataset.target){
            const box = document.getElementById(this.dataset.target);
            box.classList.remove("hidden");
            // Přidat required na inputy když je viditelný
            box.querySelectorAll("input").forEach(input => input.required = true);
        }
    });
});

// Validace a odeslání formuláře přes fetch
document.querySelector('form').addEventListener('submit', function(e) {
    e.preventDefault();
    const checked = document.querySelectorAll('input[name="tridy[]"]:checked');
    if (checked.length === 0) {
        alert("Vyberte alespoň jednu třídu!");
        return;
    }

    const formData = new FormData(this);

    fetch('save_vylet.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            window.location.href = 'teacher.html';
        } else {
            alert(data.message || 'Chyba při ukládání výletu');
        }
    })
    .catch(error => {
        console.error('Chyba:', error);
        alert('Chyba při ukládání výletu');
    });
});

// Načítání emailu uživatele
fetch('api_auth.php')
    .then(res => res.json())
    .then(data => {
        if (data.authenticated) {
            document.getElementById('user-email').textContent = data.email;
            document.body.style.visibility = 'visible';
        } else {
            window.location.href = 'login.html';
        }
    });
