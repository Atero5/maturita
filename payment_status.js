// Payment management module
let paymentData = [];

async function loadPaymentStatus(tripId) {
    try {
        const response = await fetch(`api_payment_status.php?tripId=${tripId}`);
        const data = await response.json();
        
        if (data.success) {
            paymentData = data.students;
            renderPaymentList(data.students);
            return true;
        } else {
            console.error('Chyba při načítání stavu plateb:', data.error);
            return false;
        }
    } catch (error) {
        console.error('Chyba:', error);
        return false;
    }
}

function renderPaymentList(students) {
    const container = document.getElementById('paymentListContainer');
    if (!container) return;
    
    let html = '<h3>Stav plateb</h3>';
    html += '<table class="payment-table">';
    html += '<thead><tr><th>Email</th><th>Třída</th><th>Status</th><th>Zaplaceno</th><th>Akce</th></tr></thead>';
    html += '<tbody>';
    
    const paid = students.filter(s => s.zaplaceno === 1 && s.odhlasen === 0).length;
    const unpaid = students.filter(s => s.zaplaceno === 0 && s.odhlasen === 0).length;
    const withdrawn = students.filter(s => s.odhlasen === 1).length;
    
    students.forEach(student => {
        const checked = student.zaplaceno === 1 ? 'checked' : '';
        let statusText = '';
        let rowClass = '';
        
        if (student.odhlasen === 1) {
            statusText = '⚠️ Odhlášen';
            rowClass = 'withdrawn';
        } else {
            statusText = 'Přihlášen';
            rowClass = checked ? 'paid' : 'unpaid';
        }
        
        // Checkbox je disabled pro odhlášené studenty
        const disabled = student.odhlasen === 1 ? 'disabled' : '';
        
        html += `<tr class="payment-row ${rowClass}">
            <td>${escapeHtml(student.email)}</td>
            <td>${escapeHtml(student.class)}</td>
            <td>${statusText}</td>
            <td>
                <input type="checkbox" ${checked} ${disabled} class="payment-checkbox" data-user-id="${student.userId}" onchange="togglePayment(event)">
            </td>
            <td>${student.zaplaceno && student.odhlasen === 0 ? '✓ Zaplaceno' : student.odhlasen === 1 ? '✗ Odhlášen' : '✗ Nezaplaceno'}</td>
        </tr>`;
    });
    
    html += '</tbody></table>';
    html += `<p style="margin-top: 20px; padding: 10px; background: #f0f0f0; border-radius: 4px;">
        <strong>Souhrn:</strong> 
        <span style="color: green; font-weight: bold;">${paid} zaplaceno</span> / 
        <span style="color: red; font-weight: bold;">${unpaid} nezaplaceno</span> / 
        <span style="color: orange; font-weight: bold;">${withdrawn} odhlášeno</span>
        (Celkem: ${students.length})
    </p>`;
    
    container.innerHTML = html;
}

async function togglePayment(event) {
    const checkbox = event.target;
    const userId = parseInt(checkbox.dataset.userId);
    const zaplaceno = checkbox.checked ? 1 : 0;
    
    
    const urlParams = new URLSearchParams(window.location.search);
    const tripId = parseInt(urlParams.get('id'));
    
    if (!tripId) {
        alert('Chyba: Nelze zjistit ID výletu');
        checkbox.checked = !zaplaceno;
        return;
    }
    
    try {
        const response = await fetch('api_payment_status.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                tripId: tripId,
                userId: userId,
                zaplaceno: zaplaceno
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Aktualizuj barvu řádku
            const row = checkbox.closest('tr');
            if (zaplaceno) {
                row.classList.remove('unpaid');
                row.classList.add('paid');
            } else {
                row.classList.remove('paid');
                row.classList.add('unpaid');
            }
            
            // Reload souhrnu
            const students = paymentData;
            students.find(s => s.userId === userId).zaplaceno = zaplaceno;
            renderPaymentList(students);
        } else {
            alert('Chyba: ' + data.message);
            checkbox.checked = !zaplaceno;
        }
    } catch (error) {
        console.error('Chyba při aktualizaci:', error);
        alert('Chyba při aktualizaci stavu platby');
        checkbox.checked = !zaplaceno;
    }
}

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}
