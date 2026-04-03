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

// Validace - alespoň jedna třída musí být vybraná
document.querySelector('form').addEventListener('submit', function(e) {
    const checked = document.querySelectorAll('input[name="tridy[]"]:checked');
    if (checked.length === 0) {
        e.preventDefault();
        alert("Vyberte alespoň jednu třídu!");
    }
});

// Načítání emailu uživatele
fetch('api_auth.php')
    .then(res => res.json())
    .then(data => {
        if (data.authenticated) {
            document.getElementById('user-email').textContent = data.email;
        }
    });
