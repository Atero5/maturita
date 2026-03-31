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

// Skript pro přepínání skrytých polí
const radios = document.querySelectorAll("input[type=radio]");
radios.forEach(radio => {
    radio.addEventListener("change", function(){
        const group = document.querySelectorAll(`input[name="${this.name}"]`);
        group.forEach(r => {
            if(r.dataset.target){
                document.getElementById(r.dataset.target).classList.add("hidden");
            }
        });
        if(this.dataset.target){
            document.getElementById(this.dataset.target).classList.remove("hidden");
        }
    });
});
