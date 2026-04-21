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

// Získání ID výletu z URL
const urlParams = new URLSearchParams(window.location.search);
const tripId = urlParams.get('id');

if (!tripId) {
    alert('Neplatný výlet');
    window.location.href = 'home_teacher.html';
}

// Načtení dat výletu a předvyplnění formuláře
async function loadTripData() {
    try {
        const response = await fetch(`api_trips.php?id=${tripId}`);
        const data = await response.json();

        if (!data.success) {
            alert(data.message || 'Výlet nenalezen');
            window.location.href = 'home_teacher.html';
            return;
        }

        const trip = data.trip;

        // Základní informace
        document.querySelector('input[name="nazev_vyletu"]').value = trip.nazev_vyletu || '';
        document.querySelector('input[name="adresa_ubytovani"]').value = trip.adresa_ubytovani || '';
        document.querySelector('input[name="delka_pobytu"]').value = trip.delka_pobytu || '';

        // Harmonogram
        document.querySelector('textarea[name="harmonogram"]').value = trip.harmonogram || '';

        // Učitelé
        document.querySelector('input[name="uciitele"]').value = trip.uciitele || '';

        // Doprava tam
        document.querySelector('input[name="misto_odjezdu_tam"]').value = trip.misto_odjezdu_tam || '';
        document.querySelector('input[name="cas_odjezdu_tam"]').value = trip.cas_odjezdu_tam || '';
        document.querySelector('input[name="dopravni_prostredek_tam"]').value = trip.dopravni_prostredek_tam || '';

        // Doprava zpět
        document.querySelector('input[name="misto_odjezdu_zpet"]').value = trip.misto_odjezdu_zpet || '';
        document.querySelector('input[name="cas_odjezdu_zpet"]').value = trip.cas_odjezdu_zpet || '';
        document.querySelector('input[name="dopravni_prostredek_zpet"]').value = trip.dopravni_prostredek_zpet || '';

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
        window.location.href = 'home_teacher.html';
    }
}

// Odeslání upraveného výletu
document.querySelector('form').addEventListener('submit', function(e) {
    e.preventDefault();

    // Validace povinných polí
    const required = [
        { name: 'nazev_vyletu', label: 'Název výletu' },
        { name: 'adresa_ubytovani', label: 'Adresa ubytování' },
        { name: 'delka_pobytu', label: 'Délka pobytu' },
        { name: 'misto_odjezdu_tam', label: 'Místo odjezdu tam' },
        { name: 'cas_odjezdu_tam', label: 'Čas odjezdu tam' },
        { name: 'dopravni_prostredek_tam', label: 'Dopravní prostředek tam' },
        { name: 'misto_odjezdu_zpet', label: 'Místo odjezdu zpět' },
        { name: 'cas_odjezdu_zpet', label: 'Čas odjezdu zpět' },
        { name: 'dopravni_prostredek_zpet', label: 'Dopravní prostředek zpět' },
        { name: 'celkova_cena', label: 'Celková cena' },
        { name: 'cislo_uctu', label: 'Číslo účtu' },
    ];

    for (const field of required) {
        const el = document.querySelector(`[name="${field.name}"]`);
        if (!el || !el.value.trim()) {
            alert(`Vyplňte pole: ${field.label}`);
            el && el.focus();
            return;
        }
    }

    const accountPattern = /^\d{2,10}\/\d{4}$/;
    const accountVal = document.querySelector('input[name="cislo_uctu"]').value.trim();
    if (!accountPattern.test(accountVal)) {
        alert('Číslo účtu musí být ve formátu Čísloúčtu/Kódbanka (např. 123456/0100)');
        document.querySelector('input[name="cislo_uctu"]').focus();
        return;
    }

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
        harmonogram: document.querySelector('textarea[name="harmonogram"]').value,
        uciitele: document.querySelector('input[name="uciitele"]').value,
        misto_odjezdu_tam: document.querySelector('input[name="misto_odjezdu_tam"]').value,
        cas_odjezdu_tam: document.querySelector('input[name="cas_odjezdu_tam"]').value,
        dopravni_prostredek_tam: document.querySelector('input[name="dopravni_prostredek_tam"]').value,
        misto_odjezdu_zpet: document.querySelector('input[name="misto_odjezdu_zpet"]').value,
        cas_odjezdu_zpet: document.querySelector('input[name="cas_odjezdu_zpet"]').value,
        dopravni_prostredek_zpet: document.querySelector('input[name="dopravni_prostredek_zpet"]').value,
        celkova_cena: document.querySelector('input[name="celkova_cena"]').value,
        cislo_uctu: document.querySelector('input[name="cislo_uctu"]').value,
        tridy: tridy
    };

    const submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Ukládám...';

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
            submitBtn.disabled = false;
            submitBtn.textContent = 'Potvrdit a upravit výlet';
        }
    })
    .catch(error => {
        console.error('Chyba:', error);
        alert('Chyba při úpravě výletu');
        submitBtn.disabled = false;
        submitBtn.textContent = 'Potvrdit a upravit výlet';
    });
});

// Načtení emailu uživatele
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

// Načtení dat výletu po načtení stránky
loadTripData();
