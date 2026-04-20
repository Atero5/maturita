// Načítání emailu uživatele a ověření role
fetch('api_auth.php')
    .then(res => res.json())
    .then(data => {
        if (data.authenticated && data.role === 'teacher') {
            document.getElementById('user-email').textContent = data.email;
            document.body.style.visibility = 'visible';
            initializeCalendar();
        } else {
            window.location.href = 'login.html';
        }
    })
    .catch(error => {
        console.error('Chyba:', error);
        window.location.href = 'login.html';
    });

// Inicializace kalendáře
function initializeCalendar() {
    const today = new Date();
    let currentDate = new Date(today.getFullYear(), today.getMonth(), 1);
    
    renderCalendar(currentDate);
    setupTaskHandlers();
    loadAllTasks();
}

// Obsluha tlačítka pro vytvoření úkolu
function setupTaskHandlers() {
    document.querySelector('.btn-create-task').addEventListener('click', function() {
        const taskName = document.getElementById('taskName').value.trim();
        const endTime = document.getElementById('taskEndTime').value;
        if (!taskName || !endTime) {
            alert('Zadejte název úkolu a koncový čas.');
            return;
        }
        fetch('api_tasks.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ task: taskName, end_time: endTime })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('taskName').value = '';
                document.getElementById('taskEndTime').value = '';
                loadAllTasks();
            } else {
                alert('Chyba při ukládání úkolu.');
            }
        })
        .catch(err => console.error('Chyba:', err));
    });
}

// Načtení a zobrazení všech úkolů učitele
function loadAllTasks() {
    fetch('api_tasks.php')
        .then(res => res.json())
        .then(data => {
            const container = document.getElementById('tasksContainer');
            if (data.success && data.tasks.length > 0) {
                container.innerHTML = '<ul>' + data.tasks.map(t =>
                    `<li><strong>${t.task}</strong> – ${t.end_time}</li>`
                ).join('') + '</ul>';
            } else {
                container.innerHTML = '<p>Žádné úkoly.</p>';
            }
        })
        .catch(err => console.error('Chyba:', err));
}

// Vykreslení kalendáře
function renderCalendar(date) {
    const year = date.getFullYear();
    const month = date.getMonth();
    
    const monthNames = [
        "Leden", "Únor", "Březen", "Duben", "Květen", "Červen",
        "Červenec", "Srpen", "Září", "Říjen", "Listopad", "Prosinec"
    ];
    
    const weekdayNames = ["Po", "Út", "St", "Čt", "Pá", "So", "Ne"];
    
    // Počet dní v měsíci
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const firstDayOfMonth = new Date(year, month, 1).getDay();
    const adjustedFirstDay = firstDayOfMonth === 0 ? 6 : firstDayOfMonth - 1;
    
    let html = `
        <div class="calendar-header">
            <button onclick="previousMonth()">← Předchozí</button>
            <h2>${monthNames[month]} ${year}</h2>
            <button onclick="nextMonth()">Další →</button>
        </div>
        
        <div class="calendar-weekdays">
    `;
    
    // Těsty dní v týdnu
    weekdayNames.forEach(day => {
        html += `<div class="calendar-weekday">${day}</div>`;
    });
    
    html += `</div><div class="calendar-grid">`;
    
    // Prázdné buňky pro dny z předchozího měsíce
    for (let i = 0; i < adjustedFirstDay; i++) {
        html += `<div class="calendar-day other-month"></div>`;
    }
    
    // Dny běžného měsíce
    for (let day = 1; day <= daysInMonth; day++) {
        const isToday = day === new Date().getDate() && month === new Date().getMonth() && year === new Date().getFullYear();
        const activeClass = isToday ? 'active' : '';
        html += `<div class="calendar-day ${activeClass}">${day}</div>`;
    }
    
    // Prázdné buňky pro dny z následujícího měsíce
    const totalCells = adjustedFirstDay + daysInMonth;
    const remainingCells = 42 - totalCells; // 6 řádků × 7 dní
    for (let i = 0; i < remainingCells; i++) {
        html += `<div class="calendar-day other-month"></div>`;
    }
    
    html += `</div>`;
    
    document.getElementById('calendarWidget').innerHTML = html;
    
    // Uložení aktuálního měsíce pro navigaci
    window.currentCalendarDate = new Date(year, month, 1);
}

// Navigace na předchozí měsíc
function previousMonth() {
    window.currentCalendarDate = new Date(window.currentCalendarDate.getFullYear(), window.currentCalendarDate.getMonth() - 1, 1);
    renderCalendar(window.currentCalendarDate);
}

// Navigace na další měsíc
function nextMonth() {
    window.currentCalendarDate = new Date(window.currentCalendarDate.getFullYear(), window.currentCalendarDate.getMonth() + 1, 1);
    renderCalendar(window.currentCalendarDate);
}
