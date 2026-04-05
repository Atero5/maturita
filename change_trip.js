// Funkce pro mapu
function zobrazMapu() {
    let adresa = document.getElementById("address").value;
    if (adresa === "") {
        alert("Zadej adresu!");
        return;
    }
    let url = "https://maps.google.com/maps?q=" + encodeURIComponent(adresa) + "&output=embed";
    let mapa = document.getElementById("mapa");
    mapa.src = url;
    mapa.classList.remove("hidden");
}

// Přepínání skrytých polí pro restauraci
const radios = document.querySelectorAll("input[type=radio]");
radios.forEach(radio => {
    radio.addEventListener("change", function(){
        const group = document.querySelectorAll(`input[name="${this.name}"]`);
        group.forEach(r => {
            if(r.dataset.target){
                const box = document.getElementById(r.dataset.target);
                box.classList.add("hidden");
                box.querySelectorAll("input").forEach(input => input.required = false);
            }
        });
        if(this.dataset.target){
            const box = document.getElementById(this.dataset.target);
            box.classList.remove("hidden");
            box.querySelectorAll("input").forEach(input => input.required = true);
        }
    });
});

// Získání ID výletu z URL
const urlParams = new URLSearchParams(window.location.search);
const tripId = urlParams.get('id');

if (!tripId) {
    alert('Neplatný výlet');
    window.location.href = 'teacher.html';
}

// Načtení dat výletu a předvyplnění formuláře
async function loadTripData() {
    try {
        const response = await fetch(`api_trips.php?id=${tripId}`);
        const data = await response.json();

        if (!data.success) {
            alert(data.message || 'Výlet nenalezen');
            window.location.href = 'teacher.html';
            return;
        }

        const trip = data.trip;

        // Základní informace
        document.querySelector('input[name="nazev_vyletu"]').value = trip.nazev_vyletu || '';
        document.querySelector('input[name="adresa_ubytovani"]').value = trip.adresa_ubytovani || '';
        document.querySelector('input[name="delka_pobytu"]').value = trip.delka_pobytu || '';

        // Doprava tam
        document.querySelector('input[name="misto_odjezdu_tam"]').value = trip.misto_odjezdu_tam || '';
        document.querySelector('input[name="cas_odjezdu_tam"]').value = trip.cas_odjezdu_tam || '';
        document.querySelector('input[name="dopravni_prostredek_tam"]').value = trip.dopravni_prostredek_tam || '';

        // Doprava zpět
        document.querySelector('input[name="misto_odjezdu_zpet"]').value = trip.misto_odjezdu_zpet || '';
        document.querySelector('input[name="cas_odjezdu_zpet"]').value = trip.cas_odjezdu_zpet || '';
        document.querySelector('input[name="dopravni_prostredek_zpet"]').value = trip.dopravni_prostredek_zpet || '';

        // Stravování - snídaně
        setRadioAndBox('typ_snidane', trip.typ_snidane, 'BreakfastBox');
        document.querySelector('input[name="nazev_restaurace_snidane"]').value = trip.nazev_restaurace_snidane || '';
        document.querySelector('input[name="adresa_restaurace_snidane"]').value = trip.adresa_restaurace_snidane || '';
        document.querySelector('input[name="cas_snidane"]').value = trip.cas_snidane || '';

        // Stravování - oběd
        setRadioAndBox('typ_obeda', trip.typ_obeda, 'LunchBox');
        document.querySelector('input[name="nazev_restaurace_obed"]').value = trip.nazev_restaurace_obed || '';
        document.querySelector('input[name="adresa_restaurace_obed"]').value = trip.adresa_restaurace_obed || '';
        document.querySelector('input[name="cas_obeda"]').value = trip.cas_obeda || '';

        // Stravování - večeře
        setRadioAndBox('typ_vecere', trip.typ_vecere, 'DinnerBox');
        document.querySelector('input[name="nazev_restaurace_vecere"]').value = trip.nazev_restaurace_vecere || '';
        document.querySelector('input[name="adresa_restaurace_vecere"]').value = trip.adresa_restaurace_vecere || '';
        document.querySelector('input[name="cas_vecere"]').value = trip.cas_vecere || '';

        // Cena
        document.querySelector('input[name="celkova_cena"]').value = trip.celkova_cena || '';
        document.querySelector('input[name="cislo_uctu"]').value = trip.cislo_uctu || '';

        // Třídy
        const checkboxes = document.querySelectorAll('input[name="tridy[]"]');
        checkboxes.forEach(cb => {
            cb.checked = trip.tridy.includes(cb.value);
        });

    } catch (error) {
        console.error('Chyba při načítání výletu:', error);
        alert('Chyba při načítání výletu');
        window.location.href = 'teacher.html';
    }
}

// Nastavení radio buttonu a zobrazení/skrytí boxu
function setRadioAndBox(radioName, value, boxId) {
    const radios = document.querySelectorAll(`input[name="${radioName}"]`);
    radios.forEach(radio => {
        if (radio.value === value) {
            radio.checked = true;
            if (radio.dataset.target) {
                const box = document.getElementById(radio.dataset.target);
                box.classList.remove('hidden');
                box.querySelectorAll('input').forEach(input => input.required = true);
            }
        }
    });
}

// Odeslání upraveného výletu
document.querySelector('form').addEventListener('submit', function(e) {
    e.preventDefault();

    const checked = document.querySelectorAll('input[name="tridy[]"]:checked');
    if (checked.length === 0) {
        alert("Vyberte alespoň jednu třídu!");
        return;
    }

    const tridy = [];
    checked.forEach(cb => tridy.push(cb.value));

    const body = {
        id: parseInt(tripId),
        nazev_vyletu: document.querySelector('input[name="nazev_vyletu"]').value,
        adresa_ubytovani: document.querySelector('input[name="adresa_ubytovani"]').value,
        delka_pobytu: document.querySelector('input[name="delka_pobytu"]').value,
        misto_odjezdu_tam: document.querySelector('input[name="misto_odjezdu_tam"]').value,
        cas_odjezdu_tam: document.querySelector('input[name="cas_odjezdu_tam"]').value,
        dopravni_prostredek_tam: document.querySelector('input[name="dopravni_prostredek_tam"]').value,
        misto_odjezdu_zpet: document.querySelector('input[name="misto_odjezdu_zpet"]').value,
        cas_odjezdu_zpet: document.querySelector('input[name="cas_odjezdu_zpet"]').value,
        dopravni_prostredek_zpet: document.querySelector('input[name="dopravni_prostredek_zpet"]').value,
        typ_snidane: document.querySelector('input[name="typ_snidane"]:checked')?.value || 'vlastni',
        nazev_restaurace_snidane: document.querySelector('input[name="nazev_restaurace_snidane"]').value,
        adresa_restaurace_snidane: document.querySelector('input[name="adresa_restaurace_snidane"]').value,
        cas_snidane: document.querySelector('input[name="cas_snidane"]').value,
        typ_obeda: document.querySelector('input[name="typ_obeda"]:checked')?.value || 'vlastni',
        nazev_restaurace_obed: document.querySelector('input[name="nazev_restaurace_obed"]').value,
        adresa_restaurace_obed: document.querySelector('input[name="adresa_restaurace_obed"]').value,
        cas_obeda: document.querySelector('input[name="cas_obeda"]').value,
        typ_vecere: document.querySelector('input[name="typ_vecere"]:checked')?.value || 'vlastni',
        nazev_restaurace_vecere: document.querySelector('input[name="nazev_restaurace_vecere"]').value,
        adresa_restaurace_vecere: document.querySelector('input[name="adresa_restaurace_vecere"]').value,
        cas_vecere: document.querySelector('input[name="cas_vecere"]').value,
        celkova_cena: document.querySelector('input[name="celkova_cena"]').value,
        cislo_uctu: document.querySelector('input[name="cislo_uctu"]').value,
        tridy: tridy
    };

    fetch('api_trips.php', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('Výlet byl úspěšně upraven');
            window.location.href = 'teacher.html';
        } else {
            alert(data.message || 'Chyba při úpravě výletu');
        }
    })
    .catch(error => {
        console.error('Chyba:', error);
        alert('Chyba při úpravě výletu');
    });
});

// Načtení emailu uživatele
fetch('api_auth.php')
    .then(res => res.json())
    .then(data => {
        if (data.authenticated) {
            document.getElementById('user-email').textContent = data.email;
        }
    });

// Načtení dat výletu po načtení stránky
loadTripData();
