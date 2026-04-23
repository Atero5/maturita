// Načtení učitelů do dropdownu
const selectedTeachersList = [];
let currentUserEmail = null;

function loadTeachers(myEmail) {
    fetch('api_teachers.php')
        .then(res => res.json())
        .then(data => {
            const dropdown = document.getElementById('teacherDropdown');
            if (data.teachers) {
                data.teachers.forEach(t => {
                    if (t.email === myEmail) return; // přeskočit sebe
                    const opt = document.createElement('option');
                    opt.value = t.email;
                    opt.textContent = t.email;
                    dropdown.appendChild(opt);
                });
            }
        });
}

function addTeacher(email) {
    if (!email || selectedTeachersList.includes(email)) return;
    selectedTeachersList.push(email);
    renderSelectedTeachers();
}

function removeTeacher(email) {
    const idx = selectedTeachersList.indexOf(email);
    if (idx > -1) {
        selectedTeachersList.splice(idx, 1);
        renderSelectedTeachers();
    }
}

function renderSelectedTeachers() {
    const container = document.getElementById('selectedTeachers');
    container.innerHTML = '';
    // Vždy zobrazit sebe jako první nepohyblivý tag
    if (currentUserEmail) {
        const selfTag = document.createElement('span');
        selfTag.className = 'teacher-tag teacher-tag-self';
        selfTag.textContent = currentUserEmail + ' (já)';
        container.appendChild(selfTag);
    }
    selectedTeachersList.forEach(email => {
        const tag = document.createElement('span');
        tag.className = 'teacher-tag';
        tag.innerHTML = email + ' <button type="button" onclick="removeTeacher(\'' + email + '\')">&times;</button>';
        container.appendChild(tag);
    });
    document.getElementById('uciteleHidden').value = selectedTeachersList.join(', ');
}

function loadClasses() {
    return fetch('api_classes.php')
        .then(res => res.json())
        .then(classes => {
            const grid = document.getElementById('classesGrid');
            if (classes && classes.length > 0) {
                let html = '';
                classes.forEach(cls => {
                    html += `<label><input type="checkbox" name="tridy[]" value="${cls}"> ${cls}</label>`;
                });
                grid.innerHTML = html;
            } else {
                grid.innerHTML = '<p style="color: #999;">Žádné třídy v databázi</p>';
            }
        })
        .catch(err => {
            console.error('Chyba při načítání tříd:', err);
            grid.innerHTML = '<p style="color: red;">Chyba při načítání tříd</p>';
        });
}

// Generování polí stravy podle délky pobytu
function generateMealFields() {
    const delka = document.getElementById('delkaPobytu').value;
    const container = document.getElementById('stravaContainer');
    const info = document.getElementById('stravaInfo');
    
    let days = 0;
    if (delka === '1 den') days = 1;
    else if (delka === '2 dny') days = 2;
    else if (delka === '3 dny') days = 3;
    else if (delka === '4 dny') days = 4;
    else if (delka === '5 dní') days = 5;
    else if (delka === 'jine') {
        const custom = document.querySelector('input[name="delka_pobytu_custom"]').value;
        days = parseInt(custom) || 0;
    }

    if (days === 0) {
        container.innerHTML = '';
        info.style.display = 'block';
        return;
    }
    info.style.display = 'none';

    const meals = [
        { key: 'snidane', label: 'Snídaně' },
        { key: 'obed', label: 'Oběd' },
        { key: 'vecere', label: 'Večeře' }
    ];

    let html = '';
    for (let d = 1; d <= days; d++) {
        html += `<h3 style="text-align:center; margin-top:25px; border-bottom:1px solid #ccc; padding-bottom:5px;">Den ${d}</h3>`;
        meals.forEach(meal => {
            const prefix = `strava[${d}][${meal.key}]`;
            const boxId = `box_${d}_${meal.key}`;
            const customId = `custom_${d}_${meal.key}`;
            html += `
                <h4>${meal.label}</h4>
                <label><input type="radio" name="${prefix}[typ]" value="vlastni" checked onchange="toggleMealBox('${boxId}','${customId}','vlastni')"> vlastní</label><br>
                <label><input type="radio" name="${prefix}[typ]" value="restaurace" onchange="toggleMealBox('${boxId}','${customId}','restaurace')"> restaurace</label>
                <div id="${customId}" class="">
                    <textarea name="${prefix}[vlastni_text]" class="textarea-field" placeholder="Vlastní strava..." rows="2"></textarea>
                </div>
                <div id="${boxId}" class="hidden">
                    <input type="text" name="${prefix}[nazev_restaurace]" placeholder="Název restaurace...">
                    <input type="text" name="${prefix}[adresa_restaurace]" placeholder="Adresa restaurace...">
                    <input type="text" name="${prefix}[kontakt_restaurace]" placeholder="Kontakt na restauraci...">
                    <input type="text" name="${prefix}[cas]" placeholder="Čas...">
                </div>
            `;
        });
    }
    container.innerHTML = html;
}

function toggleMealBox(boxId, customId, typ) {
    document.getElementById(boxId).classList.toggle('hidden', typ !== 'restaurace');
    document.getElementById(customId).classList.toggle('hidden', typ !== 'vlastni');
}

// Listener na změnu délky pobytu
const delkaSelect = document.getElementById('delkaPobytu');
if (delkaSelect) {
    delkaSelect.addEventListener('change', function() {
        const customBox = document.getElementById('delkaPobytuCustom');
        const customInput = customBox.querySelector('input');
        if (this.value === 'jine') {
            customBox.classList.remove('hidden');
            customInput.required = true;
        } else {
            customBox.classList.add('hidden');
            customInput.required = false;
        }
        generateMealFields();
    });
}

// Listener na změnu vlastní délky pobytu
const customDelkaInput = document.querySelector('input[name="delka_pobytu_custom"]');
if (customDelkaInput) {
    customDelkaInput.addEventListener('input', function() {
        if (document.getElementById('delkaPobytu').value === 'jine') {
            generateMealFields();
        }
    });
}

// Listener na Doprava tam
const dopravaTamSelect = document.getElementById('dopravaTam');
if (dopravaTamSelect) {
    dopravaTamSelect.addEventListener('change', function() {
        const customBox = document.getElementById('dopravaTamCustom');
        const customInput = customBox.querySelector('input');
        if (this.value === 'jine') {
            customBox.classList.remove('hidden');
            customInput.required = true;
        } else {
            customBox.classList.add('hidden');
            customInput.required = false;
            customInput.value = '';
        }
    });
}

// Listener na Doprava zpět
const dopravaZpetSelect = document.getElementById('dopravaZpet');
if (dopravaZpetSelect) {
    dopravaZpetSelect.addEventListener('change', function() {
        const customBox = document.getElementById('dopravaZpetCustom');
        const customInput = customBox.querySelector('input');
        if (this.value === 'jine') {
            customBox.classList.remove('hidden');
            customInput.required = true;
        } else {
            customBox.classList.add('hidden');
            customInput.required = false;
            customInput.value = '';
        }
    });
}

// Listener na teacherDropdown
const teacherDropdown = document.getElementById('teacherDropdown');
if (teacherDropdown) {
    teacherDropdown.addEventListener('change', function() {
        addTeacher(this.value);
        this.value = '';
    });
}

// Listener na náhledový obrázek
const nahledovyObrazekInput = document.getElementById('nahledovyObrazek');
if (nahledovyObrazekInput) {
    nahledovyObrazekInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        const preview = document.getElementById('obrazekPreview');
        const img = document.getElementById('previewImg');
        
        if (file) {
            // Kontrola typu souboru
            if (!file.type.startsWith('image/')) {
                alert('Vyberte prosím obrázek.');
                this.value = '';
                preview.style.display = 'none';
                return;
            }
            
            // Kontrola velikosti (max 5MB)
            if (file.size > 5 * 1024 * 1024) {
                alert('Obrázek je příliš velký. Maximální velikost je 5MB.');
                this.value = '';
                preview.style.display = 'none';
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                img.src = e.target.result;
                preview.style.display = 'block';
            };
            reader.readAsDataURL(file);
        } else {
            preview.style.display = 'none';
        }
    });
}

// Funkce pro sbírání stravy z formuláře
function collectMealData() {
    const strava = {};
    const delka = document.querySelector('select[name="delka_pobytu"]').value;
    const match = delka.match(/(\d+)/);
    const days = match ? parseInt(match[1]) : 0;
    
    const meals = ['snidane', 'obed', 'vecere'];
    
    for (let d = 1; d <= days; d++) {
        strava[d] = {};
        meals.forEach(meal => {
            const prefix = `strava[${d}][${meal}]`;
            const typeRadio = document.querySelector(`input[name="${prefix}[typ]"]:checked`);
            const typ = typeRadio ? typeRadio.value : 'vlastni';
            
            strava[d][meal] = {
                typ: typ
            };
            
            if (typ === 'vlastni') {
                const textArea = document.querySelector(`textarea[name="${prefix}[vlastni_text]"]`);
                strava[d][meal].vlastni_text = textArea ? textArea.value : '';
            } else if (typ === 'restaurace') {
                const nazev = document.querySelector(`input[name="${prefix}[nazev_restaurace]"]`);
                const adresa = document.querySelector(`input[name="${prefix}[adresa_restaurace]"]`);
                const kontakt = document.querySelector(`input[name="${prefix}[kontakt_restaurace]"]`);
                const cas = document.querySelector(`input[name="${prefix}[cas]"]`);
                
                strava[d][meal].nazev_restaurace = nazev ? nazev.value : '';
                strava[d][meal].adresa_restaurace = adresa ? adresa.value : '';
                strava[d][meal].kontakt_restaurace = kontakt ? kontakt.value : '';
                strava[d][meal].cas = cas ? cas.value : '';
            }
        });
    }
    
    return strava;
}

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
        
        // Nastavit ID výletu do hidden fieldu
        document.getElementById('vyletId').value = trip.id;

        // Základní informace
        document.querySelector('input[name="nazev_vyletu"]').value = trip.nazev_vyletu || '';
        document.querySelector('input[name="adresa_ubytovani"]').value = trip.adresa_ubytovani || '';
        
        // Náhledový obrázek - zobrazit existující obrázek
        if (trip.nahledovy_obrazek) {
            const currentImgDiv = document.getElementById('obrazekCurrent');
            const currentImg = document.getElementById('currentImg');
            currentImg.src = trip.nahledovy_obrazek;
            currentImgDiv.style.display = 'block';
        }
        
        // Délka pobytu - nastavit select
        const delkaSelect = document.getElementById('delkaPobytu');
        if (['1 den', '2 dny', '3 dny', '4 dny', '5 dní'].includes(trip.delka_pobytu)) {
            delkaSelect.value = trip.delka_pobytu;
        } else {
            delkaSelect.value = 'jine';
            document.getElementById('delkaPobytuCustom').classList.remove('hidden');
            document.querySelector('input[name="delka_pobytu_custom"]').value = trip.delka_pobytu;
        }

        // Generovat pole pro stravu
        generateMealFields();

        // Předvyplnění stravy
        if (data.trip.strava && Array.isArray(data.trip.strava)) {
            data.trip.strava.forEach(s => {
                const prefix = `strava[${s.den}][${s.typ_jidla}]`;
                // Nastavit typ (vlastní nebo restaurace)
                const typeRadio = document.querySelector(`input[name="${prefix}[typ]"][value="${s.typ}"]`);
                if (typeRadio) {
                    typeRadio.checked = true;
                    typeRadio.dispatchEvent(new Event('change'));
                }
                // Předvyplnit hodnoty
                if (s.typ === 'vlastni' && s.vlastni_text) {
                    const customInput = document.querySelector(`textarea[name="${prefix}[vlastni_text]"]`);
                    if (customInput) customInput.value = s.vlastni_text;
                } else if (s.typ === 'restaurace') {
                    if (s.nazev_restaurace) {
                        const nazevInput = document.querySelector(`input[name="${prefix}[nazev_restaurace]"]`);
                        if (nazevInput) nazevInput.value = s.nazev_restaurace;
                    }
                    if (s.adresa_restaurace) {
                        const adresaInput = document.querySelector(`input[name="${prefix}[adresa_restaurace]"]`);
                        if (adresaInput) adresaInput.value = s.adresa_restaurace;
                    }
                    if (s.kontakt_restaurace) {
                        const kontaktInput = document.querySelector(`input[name="${prefix}[kontakt_restaurace]"]`);
                        if (kontaktInput) kontaktInput.value = s.kontakt_restaurace;
                    }
                    if (s.cas) {
                        const casInput = document.querySelector(`input[name="${prefix}[cas]"]`);
                        if (casInput) casInput.value = s.cas;
                    }
                }
            });
        }

        // Harmonogram
        document.querySelector('textarea[name="harmonogram"]').value = trip.harmonogram || '';

        // Učitelé - rozparsovat a naplnit
        if (trip.uciitele) {
            const teachers = trip.uciitele.split(',').map(e => e.trim()).filter(e => e && e !== currentUserEmail);
            selectedTeachersList.length = 0;
            selectedTeachersList.push(...teachers);
            renderSelectedTeachers();
        }

        // Doprava tam
        document.querySelector('input[name="misto_odjezdu_tam"]').value = trip.misto_odjezdu_tam || '';
        document.querySelector('input[name="cas_odjezdu_tam"]').value = trip.cas_odjezdu_tam || '';
        
        const dopravaTamSelect = document.getElementById('dopravaTam');
        if (['autobus', 'vlak'].includes(trip.dopravni_prostredek_tam)) {
            dopravaTamSelect.value = trip.dopravni_prostredek_tam;
            document.getElementById('dopravaTamCustom').classList.add('hidden');
        } else {
            dopravaTamSelect.value = 'jine';
            document.getElementById('dopravaTamCustom').classList.remove('hidden');
            document.querySelector('input[name="dopravni_prostredek_tam_custom"]').value = trip.dopravni_prostredek_tam;
        }

        // Doprava zpět
        document.querySelector('input[name="misto_odjezdu_zpet"]').value = trip.misto_odjezdu_zpet || '';
        document.querySelector('input[name="cas_odjezdu_zpet"]').value = trip.cas_odjezdu_zpet || '';
        
        const dopravaZpetSelect = document.getElementById('dopravaZpet');
        if (['autobus', 'vlak'].includes(trip.dopravni_prostredek_zpet)) {
            dopravaZpetSelect.value = trip.dopravni_prostredek_zpet;
            document.getElementById('dopravaZpetCustom').classList.add('hidden');
        } else {
            dopravaZpetSelect.value = 'jine';
            document.getElementById('dopravaZpetCustom').classList.remove('hidden');
            document.querySelector('input[name="dopravni_prostredek_zpet_custom"]').value = trip.dopravni_prostredek_zpet;
        }

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

// Odeslání upraveného výletu - validace a fetch s FormData
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

    const submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Ukládám...';

    // Vytvoření FormData z formuláře
    const formData = new FormData(this);

    fetch('update_trip.php', {
        method: 'POST',
        body: formData
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
    .then(async data => {
        if (data.authenticated) {
            currentUserEmail = data.email;
            document.getElementById('user-email').textContent = data.email;
            document.body.style.visibility = 'visible';
            // Načtení učitelů do dropdownu
            loadTeachers(data.email);
            // Načtení tříd do checkboxů
            await loadClasses();
            // Načtení dat výletu po načtení stránky
            loadTripData();
        } else {
            window.location.href = 'login.html';
        }
    });

