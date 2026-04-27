const urlParams = new URLSearchParams(window.location.search);
const tripId = urlParams.get('id');
const autoUpload = urlParams.get('upload') === '1';

if (!tripId) {
    alert('Neplatný výlet');
    window.location.href = 'gallery.html';
}

let currentUserId = 0;
let isOwner = false;
let userRole = '';
let firstLoad = true;
let uploadReady = false;
const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10 MB

async function init() {
    try {
        const authRes = await fetch('api_auth.php');
        const authData = await authRes.json();

        if (!authData.authenticated) {
            window.location.href = 'login.html';
            return;
        }

        userRole = authData.role;
        document.getElementById('user-email').textContent = authData.email;

        const logoLink = document.getElementById('logo-link');
        if (userRole === 'teacher' || userRole === 'admin') {
            logoLink.href = 'home_teacher.html';
            document.getElementById('navAddTrip').style.display = '';
        } else {
            logoLink.href = 'home_user.html';
        }

        document.body.style.visibility = 'visible';
        loadPhotos();
    } catch (error) {
        window.location.href = 'login.html';
    }
}

async function loadPhotos() {
    try {
        const res = await fetch('api_photos.php?vyletId=' + tripId);
        const data = await res.json();

        if (!data.success) {
            document.getElementById('photosContainer').innerHTML = '<p class="no-photos">Chyba: ' + escapeHtml(data.message || '') + '</p>';
            return;
        }

        currentUserId = data.currentUserId;
        isOwner = data.isOwner;

        // Upload sekce - zobrazit jen když přišel přes "Přidat fotku"
        if (autoUpload && data.canUpload) {
            document.getElementById('uploadSection').style.display = 'block';
            document.getElementById('uploadDisabled').style.display = 'none';
            document.getElementById('photosContainer').style.display = 'none';
            if (!uploadReady) {
                setupUpload();
                uploadReady = true;
            }
        } else if (autoUpload && !data.canUpload) {
            document.getElementById('uploadSection').style.display = 'none';
            document.getElementById('uploadDisabled').style.display = 'block';
        } else {
            document.getElementById('uploadSection').style.display = 'none';
            document.getElementById('uploadDisabled').style.display = 'none';
        }

        // Název výletu - načteme z api_trips
        try {
            const tripRes = await fetch('api_trips.php?id=' + tripId);
            const tripData = await tripRes.json();
            if (tripData.success && tripData.trip) {
                document.getElementById('pageTitle').textContent = 'Fotky – ' + (tripData.trip.nazev_vyletu || tripData.trip.nazev || 'Výlet');
            }
        } catch (e) {}

        renderPhotos(data.photos);

    } catch (error) {
        document.getElementById('photosContainer').innerHTML = '<p class="no-photos">Chyba při načítání fotek.</p>';
    }
}

function renderPhotos(photos) {
    const container = document.getElementById('photosContainer');
    lightboxPhotos = photos || [];

    if (!photos || photos.length === 0) {
        container.innerHTML = '<p class="no-photos">Zatím žádné fotky.</p>';
        return;
    }

    const html = '<div class="photos-grid">' + photos.map((photo, index) => {
        const canDelete = (currentUserId === parseInt(photo.userId)) || isOwner || userRole === 'admin';
        const deleteBtn = canDelete
            ? `<button class="btn-delete-photo" onclick="deletePhoto(${photo.photoId})" title="Smazat">🗑️</button>`
            : '';

        const imgSrc = 'uploads/trips/' + tripId + '/' + encodeURIComponent(photo.filename);

        return `
            <div class="photo-card">
                <img src="${imgSrc}" alt="Fotka" onclick="openLightbox(${index})">
                <div class="photo-card-footer">
                    <span class="photo-author">${escapeHtml(photo.email)}</span>
                    ${deleteBtn}
                </div>
            </div>
        `;
    }).join('') + '</div>';

    container.innerHTML = html;
}

let pendingFiles = [];
let lightboxPhotos = [];
let lightboxIndex = 0;

function setupUpload() {
    const fileInput = document.getElementById('fileInput');
    const uploadArea = document.getElementById('uploadArea');

    fileInput.addEventListener('change', function () {
        handleFiles(this.files);
        this.value = '';
    });

    // Drag & drop na upload area
    uploadArea.addEventListener('dragover', function (e) {
        e.preventDefault();
        this.classList.add('drag-over');
    });
    uploadArea.addEventListener('dragleave', function (e) {
        if (!this.contains(e.relatedTarget)) this.classList.remove('drag-over');
    });
    uploadArea.addEventListener('drop', function (e) {
        e.preventDefault();
        this.classList.remove('drag-over');
        if (e.dataTransfer.files.length > 0) {
            handleFiles(e.dataTransfer.files);
        }
    });
}

function handleFiles(files) {
    const status = document.getElementById('uploadStatus');
    status.innerHTML = '';
    for (const file of files) {
        if (!file.type.match(/^image\/(jpeg|png|webp)$/)) {
            status.innerHTML = '<span style="color:#dc3545;">Soubor "' + escapeHtml(file.name) + '" není obrázek.</span>';
            continue;
        }
        if (file.size > MAX_FILE_SIZE) {
            status.innerHTML = '<span style="color:#dc3545;">Soubor "' + escapeHtml(file.name) + '" je příliš velký (max 10 MB).</span>';
            continue;
        }
        pendingFiles.push(file);
    }
    renderPreview();
}

function renderPreview() {
    const preview = document.getElementById('uploadPreview');
    const grid = document.getElementById('previewGrid');
    grid.innerHTML = '';

    if (pendingFiles.length === 0) {
        preview.style.display = 'none';
        return;
    }

    pendingFiles.forEach((file, index) => {
        const item = document.createElement('div');
        item.className = 'preview-item';

        const img = document.createElement('img');
        img.src = URL.createObjectURL(file);
        img.alt = file.name;

        const name = document.createElement('span');
        name.className = 'preview-item-name';
        name.textContent = file.name;

        const removeBtn = document.createElement('button');
        removeBtn.className = 'preview-item-remove';
        removeBtn.textContent = '✕';
        removeBtn.title = 'Odebrat';
        removeBtn.onclick = function () {
            pendingFiles.splice(index, 1);
            renderPreview();
        };

        item.appendChild(removeBtn);
        item.appendChild(img);
        item.appendChild(name);
        grid.appendChild(item);
    });

    preview.style.display = 'block';
    document.getElementById('uploadStatus').innerHTML = '';
}

function cancelUpload() {
    pendingFiles = [];
    document.getElementById('fileInput').value = '';
    document.getElementById('uploadPreview').style.display = 'none';
    document.getElementById('uploadStatus').innerHTML = '';
}

async function confirmUpload() {
    if (pendingFiles.length === 0) return;

    const status = document.getElementById('uploadStatus');
    const total = pendingFiles.length;
    let uploaded = 0;
    let errors = 0;

    status.innerHTML = '<span style="color:#007bff;">Nahrávání 0/' + total + '...</span>';

    for (const file of pendingFiles) {
        const formData = new FormData();
        formData.append('photo', file);
        formData.append('vyletId', tripId);

        try {
            const res = await fetch('api_photos.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();

            if (data.success) {
                uploaded++;
            } else {
                errors++;
            }
        } catch (error) {
            errors++;
        }

        status.innerHTML = '<span style="color:#007bff;">Nahrávání ' + (uploaded + errors) + '/' + total + '...</span>';
    }

    let msg = '<span style="color:#28a745;">Nahráno ' + uploaded + '/' + total + ' fotek.</span>';
    if (errors > 0) {
        msg += ' <span style="color:#dc3545;">' + errors + ' se nepodařilo.</span>';
    }
    status.innerHTML = msg;

    pendingFiles = [];
    document.getElementById('uploadPreview').style.display = 'none';
    document.getElementById('fileInput').value = '';
    loadPhotos();
}

async function deletePhoto(photoId) {
    if (!confirm('Opravdu chcete smazat tuto fotku?')) return;

    try {
        const res = await fetch('api_photos.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ photoId: photoId })
        });
        const data = await res.json();

        if (data.success) {
            loadPhotos();
        } else {
            alert(data.message || 'Chyba při mazání.');
        }
    } catch (error) {
        alert('Chyba při mazání fotky.');
    }
}

// Lightbox
function openLightbox(index) {
    lightboxIndex = index;
    const photo = lightboxPhotos[index];
    const imgSrc = 'uploads/trips/' + tripId + '/' + encodeURIComponent(photo.filename);
    document.getElementById('lightboxImg').src = imgSrc;
    document.getElementById('lightboxInfo').textContent = 'Nahrál/a: ' + photo.email + ' • ' + formatDateTime(photo.uploaded_at);
    document.getElementById('lightbox').style.display = 'flex';
    document.getElementById('lightboxPrev').style.visibility = index > 0 ? 'visible' : 'hidden';
    document.getElementById('lightboxNext').style.visibility = index < lightboxPhotos.length - 1 ? 'visible' : 'hidden';
}

function lightboxStep(dir) {
    const next = lightboxIndex + dir;
    if (next >= 0 && next < lightboxPhotos.length) openLightbox(next);
}

function closeLightbox(event) {
    if (event && event.target !== document.getElementById('lightbox') && event.target !== document.querySelector('.lightbox-close')) return;
    document.getElementById('lightbox').style.display = 'none';
}

document.addEventListener('keydown', function (e) {
    const lb = document.getElementById('lightbox');
    if (lb.style.display === 'none') return;
    if (e.key === 'Escape') lb.style.display = 'none';
    if (e.key === 'ArrowRight') lightboxStep(1);
    if (e.key === 'ArrowLeft') lightboxStep(-1);
});

function formatDateTime(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    return d.toLocaleDateString('cs-CZ', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

init();
