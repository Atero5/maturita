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

document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('teacherDropdown').addEventListener('change', function() {
        addTeacher(this.value);
        this.value = '';
    });
});

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

// Přepínání kroků formuláře
function goToStep(step) {
    document.querySelectorAll('.form-step').forEach(s => s.classList.remove('active'));
    document.querySelectorAll('.step-tab').forEach(t => t.classList.remove('active'));
    document.getElementById('step-' + step).classList.add('active');
    document.querySelector('.step-tab[data-step="' + step + '"]').classList.add('active');
    window.scrollTo(0, 0);
}

// Přechod na další krok s validací
function validateAndGoToStep(from, to) {
    if (from === 1) {
        const nazev = document.querySelector('input[name="nazev_vyletu"]').value.trim();
        const adresa = document.querySelector('input[name="adresa_ubytovani"]').value.trim();
        const delka = document.getElementById('delkaPobytu').value;
        const mistoTam = document.querySelector('input[name="misto_odjezdu_tam"]').value.trim();
        const casTam = document.querySelector('input[name="cas_odjezdu_tam"]').value;
        const dopravaTamVal = document.getElementById('dopravaTam').value;
        const mistoZpet = document.querySelector('input[name="misto_odjezdu_zpet"]').value.trim();
        const casZpet = document.querySelector('input[name="cas_odjezdu_zpet"]').value;
        const dopravaZpetVal = document.getElementById('dopravaZpet').value;

        if (!nazev || !adresa || !delka) {
            alert('Vyplňte název výletu, adresu ubytování a délku pobytu.');
            return;
        }
        if (delka === 'jine' && !document.querySelector('input[name="delka_pobytu_custom"]').value.trim()) {
            alert('Zadejte vlastní délku pobytu.');
            return;
        }
        if (!mistoTam || !casTam || !dopravaTamVal) {
            alert('Vyplňte místo odjezdu tam, datum a dopravní prostředek.');
            return;
        }
        if (dopravaTamVal === 'jine' && !document.querySelector('input[name="dopravni_prostredek_tam_custom"]').value.trim()) {
            alert('Zadejte vlastní dopravní prostředek tam.');
            return;
        }
        if (!mistoZpet || !casZpet || !dopravaZpetVal) {
            alert('Vyplňte místo odjezdu zpět, datum a dopravní prostředek.');
            return;
        }
        if (dopravaZpetVal === 'jine' && !document.querySelector('input[name="dopravni_prostredek_zpet_custom"]').value.trim()) {
            alert('Zadejte vlastní dopravní prostředek zpět.');
            return;
        }
        if (casTam && casZpet && casZpet <= casTam) {
            alert('Datum a čas odjezdu zpět musí být po odjezdu tam.');
            return;
        }
        document.querySelector('.step-tab[data-step="1"]').classList.add('done');
    }
    if (from === 2) {
        document.querySelector('.step-tab[data-step="2"]').classList.add('done');
    }
    goToStep(to);
}

// Kliknutí na lištu kroků (pouze zpět nebo na done krok)
document.querySelectorAll('.step-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        const target = parseInt(this.dataset.step);
        const current = parseInt(document.querySelector('.step-tab.active').dataset.step);
        if (target < current || this.classList.contains('done')) {
            goToStep(target);
        }
    });
});
document.getElementById('delkaPobytu').addEventListener('change', function() {
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

// Při změně vlastní délky přegenerovat stravu
document.querySelector('input[name="delka_pobytu_custom"]').addEventListener('input', function() {
    if (document.getElementById('delkaPobytu').value === 'jine') {
        generateMealFields();
    }
});

// Zobrazení vlastního pole pro dopravní prostředek tam
document.getElementById('dopravaTam').addEventListener('change', function() {
    const customBox = document.getElementById('dopravaTamCustom');
    const customInput = customBox.querySelector('input');
    if (this.value === 'jine') {
        customBox.classList.remove('hidden');
        customInput.required = true;
    } else {
        customBox.classList.add('hidden');
        customInput.required = false;
    }
});

// Zobrazení vlastního pole pro dopravní prostředek zpět
document.getElementById('dopravaZpet').addEventListener('change', function() {
    const customBox = document.getElementById('dopravaZpetCustom');
    const customInput = customBox.querySelector('input');
    if (this.value === 'jine') {
        customBox.classList.remove('hidden');
        customInput.required = true;
    } else {
        customBox.classList.add('hidden');
        customInput.required = false;
    }
});

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

    // Krok 1 - základní info
    const nazev = document.querySelector('input[name="nazev_vyletu"]').value.trim();
    const adresa = document.querySelector('input[name="adresa_ubytovani"]').value.trim();
    const delka = document.querySelector('select[name="delka_pobytu"]').value;
    const mistoTam = document.querySelector('input[name="misto_odjezdu_tam"]').value.trim();
    const casTam = document.querySelector('input[name="cas_odjezdu_tam"]').value.trim();
    const dopravaTamVal = document.querySelector('select[name="dopravni_prostredek_tam"]').value;
    const mistoZpet = document.querySelector('input[name="misto_odjezdu_zpet"]').value.trim();
    const casZpet = document.querySelector('input[name="cas_odjezdu_zpet"]').value.trim();
    const dopravaZpetVal = document.querySelector('select[name="dopravni_prostredek_zpet"]').value;

    if (!nazev || !adresa || !delka) {
        alert("Vyplňte prosím název výletu, adresu ubytování a délku pobytu.");
        goToStep(1);
        return;
    }
    if (delka === 'jine' && !document.querySelector('input[name="delka_pobytu_custom"]').value.trim()) {
        alert("Zadejte vlastní délku pobytu.");
        goToStep(1);
        return;
    }
    if (!mistoTam || !casTam || !dopravaTamVal) {
        alert("Vyplňte místo odjezdu tam, čas a dopravní prostředek.");
        goToStep(1);
        return;
    }
    if (dopravaTamVal === 'jine' && !document.querySelector('input[name="dopravni_prostredek_tam_custom"]').value.trim()) {
        alert("Zadejte vlastní dopravní prostředek tam.");
        goToStep(1);
        return;
    }
    if (!mistoZpet || !casZpet || !dopravaZpetVal) {
        alert("Vyplňte místo odjezdu zpět, čas a dopravní prostředek.");
        goToStep(1);
        return;
    }
    if (dopravaZpetVal === 'jine' && !document.querySelector('input[name="dopravni_prostredek_zpet_custom"]').value.trim()) {
        alert("Zadejte vlastní dopravní prostředek zpět.");
        goToStep(1);
        return;
    }

    // Krok 3 - cena a třídy
    const cena = document.querySelector('input[name="celkova_cena"]').value.trim();
    const ucet = document.querySelector('input[name="cislo_uctu"]').value.trim();
    const checked = document.querySelectorAll('input[name="tridy[]"]:checked');

    if (!cena || !ucet) {
        alert("Vyplňte prosím celkovou cenu a číslo účtu.");
        goToStep(3);
        return;
    }
    if (checked.length === 0) {
        alert("Vyberte alespoň jednu třídu!");
        goToStep(3);
        return;
    }

    const formData = new FormData(this);
    const submitBtn = document.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Odesílám...';

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
            submitBtn.disabled = false;
            submitBtn.textContent = 'Potvrdit a odeslat výlet';
        }
    })
    .catch(error => {
        console.error('Chyba:', error);
        alert('Chyba při ukládání výletu');
        submitBtn.disabled = false;
        submitBtn.textContent = 'Potvrdit a odeslat výlet';
    });
});

// Načítání emailu uživatele
fetch('api_auth.php')
    .then(res => res.json())
    .then(data => {
        if (data.authenticated) {
            document.getElementById('user-email').textContent = data.email;
            currentUserEmail = data.email;
            loadTeachers(data.email);
            renderSelectedTeachers();
            document.body.style.visibility = 'visible';
            // Nastavit minimální datum na dnešek
            const nowStr = new Date().toISOString().slice(0, 16);
            document.querySelector('input[name="cas_odjezdu_tam"]').min = nowStr;
            document.querySelector('input[name="cas_odjezdu_zpet"]').min = nowStr;
            // Aktualizovat min zpět při změně tam
            document.querySelector('input[name="cas_odjezdu_tam"]').addEventListener('change', function() {
                if (this.value) {
                    document.querySelector('input[name="cas_odjezdu_zpet"]').min = this.value;
                }
            });
        } else {
            window.location.href = 'login.html';
        }
    });
