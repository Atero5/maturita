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
    window.currentCalendarDate = new Date(today.getFullYear(), today.getMonth(), 1);
    window.selectedDay = null;
    window.currentSelectedDate = null;
    window.allTasks = [];

    setupTaskHandlers();
    // Nejprve načíst úkoly, pak vykreslit kalendář
    loadAllTasks().then(() => renderCalendar(window.currentCalendarDate));
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
                loadAllTasks().then(() => renderCalendar(window.currentCalendarDate));
            } else {
                alert('Chyba při ukládání úkolu.');
            }
        })
        .catch(() => alert('Chyba při ukládání úkolu.'));
    });
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
        const dateKey = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        const isToday = day === new Date().getDate() && month === new Date().getMonth() && year === new Date().getFullYear();
        const activeClass = isToday ? 'active' : '';
        const dayTasks = getDayTasks(dateKey);
        const taskCount = dayTasks.length;
        html += `
            <div class="calendar-day ${activeClass}" data-date="${dateKey}" onclick="toggleDay(this)">
                <div class="day-number">${day}</div>
                ${taskCount > 0 ? `<div class="task-count">${taskCount}</div>` : ''}
                <div class="day-details" style="display: none;">
                    <div class="day-tasks" ondrop="dropTask(event)" ondragover="allowDrop(event)"
                         ondragenter="this.classList.add('drag-over')"
                         ondragleave="if(!this.contains(event.relatedTarget))this.classList.remove('drag-over')">
                        ${dayTasks.map(task => `<div class="assigned-task" draggable="true" ondragstart="dragTask(event)" data-task-id="${task.taskId}">${task.task} – ${task.end_time}</div>`).join('')}
                    </div>
                </div>
            </div>
        `;
    }
    
    // Prázdné buňky pro dny z následujícího měsíce
    const totalCells = adjustedFirstDay + daysInMonth;
    const remainingCells = 42 - totalCells; // 6 řádků × 7 dní
    for (let i = 0; i < remainingCells; i++) {
        html += `<div class="calendar-day other-month"></div>`;
    }
    
    html += `</div>`;
    
    document.getElementById('calendarWidget').innerHTML = html;
    
    // Přidat dayView pokud neexistuje
    if (!document.getElementById('dayView')) {
        const dayViewHtml = `
            <div id="dayView" style="display: none;">
                <div class="day-view-header">
                    <button id="backToCalendar" class="btn-back">← Zpět do kalendáře</button>
                    <h2 id="dayViewDate"></h2>
                </div>
                <div id="dayViewTasks" class="day-view-tasks" style="min-height: 200px; border: 2px dashed #ccc; padding: 15px; border-radius: 5px; background-color: #f9f9f9;">
                    <!-- Tasks will be populated here -->
                </div>
            </div>
        `;
        document.getElementById('calendarWidget').insertAdjacentHTML('beforeend', dayViewHtml);
        
        // Přidat event listener pro back button
        document.getElementById('backToCalendar').addEventListener('click', function() {
            const dayView = document.getElementById('dayView');
            const calendarHeader = document.querySelector('.calendar-header');
            const calendarWeekdays = document.querySelector('.calendar-weekdays');
            const calendarGrid = document.querySelector('.calendar-grid');
            window.currentSelectedDate = null;
            dayView.style.display = 'none';
            calendarHeader.style.display = 'flex';
            calendarWeekdays.style.display = 'flex';
            calendarGrid.style.display = 'grid';
        });
        
        // Přidat event listeners pro drop zone v dayViewTasks
        const dayViewTasks = document.getElementById('dayViewTasks');
        dayViewTasks.addEventListener('dragover', allowDrop);
        dayViewTasks.addEventListener('drop', dropTaskToDay);
        dayViewTasks.addEventListener('dragenter', () => dayViewTasks.classList.add('drag-over'));
        dayViewTasks.addEventListener('dragleave', (e) => {
            if (!dayViewTasks.contains(e.relatedTarget)) {
                dayViewTasks.classList.remove('drag-over');
            }
        });
    }
    
    // Skrýt dayView pokud je zobrazen
    const dayView = document.getElementById('dayView');
    if (dayView) {
        dayView.style.display = 'none';
    }
    
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

// Funkce pro rozbalení/sbalení dne
function toggleDay(dayElement, forceDateKey) {
    const calendarWidget = document.getElementById('calendarWidget');
    const calendarHeader = calendarWidget.querySelector('.calendar-header');
    const calendarWeekdays = calendarWidget.querySelector('.calendar-weekdays');
    const calendarGrid = calendarWidget.querySelector('.calendar-grid');
    const dayView = document.getElementById('dayView');
    const dateKey = forceDateKey || (dayElement && dayElement.dataset.date);
    if (!dateKey) return;

    // Uložit vybraný den
    window.currentSelectedDate = dateKey;
    
    // Skrýt kalendář
    calendarHeader.style.display = 'none';
    calendarWeekdays.style.display = 'none';
    calendarGrid.style.display = 'none';
    
    // Zobrazit day view
    dayView.style.display = 'block';
    
    // Naplnit údaje
    const date = new Date(dateKey);
    const dayTasks = getDayTasks(dateKey);
    
    document.getElementById('dayViewDate').textContent = date.toLocaleDateString('cs-CZ', { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    });
    const tasksContainer = document.getElementById('dayViewTasks');
    if (dayTasks.length > 0) {
        tasksContainer.innerHTML = dayTasks.map(task =>
            `<div class="assigned-task" draggable="true" ondragstart="dragTask(event)" data-task-id="${task.taskId}">`
            + `<span class="task-item-text">${task.task} – ${task.end_time}</span>`
            + `<button class="btn-task-unassign" onclick="unassignTask(${task.taskId})" title="Odebrat z dne">↩</button>`
            + `</div>`
        ).join('');
    } else {
        tasksContainer.innerHTML = '<p class="drop-hint">Přetáhněte sem úkol z panelu úkolů.</p>';
    }
}

// Funkce pro drag and drop
function allowDrop(ev) {
    ev.preventDefault();
}

function dragTask(ev) {
    let element = ev.target;
    while (element && !element.getAttribute('data-task-id')) {
        element = element.parentElement;
    }
    if (element) {
        ev.dataTransfer.effectAllowed = 'move';
        ev.dataTransfer.setData('text', element.getAttribute('data-task-id'));
        element.classList.add('dragging');
        document.addEventListener('dragend', () => element.classList.remove('dragging'), { once: true });
    }
}

function dropTask(ev) {
    ev.preventDefault();
    ev.currentTarget.classList.remove('drag-over');
    const taskId = ev.dataTransfer.getData('text');
    const dayElement = ev.target.closest('.calendar-day');
    if (!dayElement) return;
    const dateKey = dayElement.dataset.date;
    assignTaskToDay(parseInt(taskId), dateKey);
}

function dropTaskToDay(ev) {
    ev.preventDefault();
    ev.currentTarget.classList.remove('drag-over');
    const taskId = ev.dataTransfer.getData('text');
    const dateKey = window.currentSelectedDate;
    if (!dateKey || !taskId) return;
    assignTaskToDay(parseInt(taskId), dateKey);
}

// Pomocné funkce pro správu úkolů
function getDayTasks(dateKey) {
    return (window.allTasks || []).filter(t => t.date === dateKey);
}

function assignTaskToDay(taskId, dateKey) {
    const dayView = document.getElementById('dayView');
    const dayViewWasOpen = dayView && dayView.style.display !== 'none';

    fetch('api_tasks.php', {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ taskId: taskId, date: dateKey })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            loadAllTasks().then(() => {
                renderCalendar(window.currentCalendarDate);
                if (dayViewWasOpen && window.currentSelectedDate) {
                    toggleDay(null, window.currentSelectedDate);
                }
            });
        }
    })
    .catch(() => alert('Chyba při přiřazení úkolu.'));
}

// Načtení a zobrazení úkolů
function loadAllTasks() {
    return fetch('api_tasks.php')
        .then(res => res.json())
        .then(data => {
            window.allTasks = (data.success && data.tasks) ? data.tasks : [];
            renderTaskPanel();
        })
        .catch(() => {
            window.allTasks = [];
            renderTaskPanel();
        });
}

function renderTaskPanel() {
    const container = document.getElementById('tasksContainer');
    const unassigned = (window.allTasks || []).filter(t => !t.date || t.date === '0000-00-00');
    if (unassigned.length > 0) {
        container.innerHTML = unassigned.map(t =>
            `<div draggable="true" ondragstart="dragTask(event)" data-task-id="${t.taskId}" class="task-item">`
            + `<span class="task-item-text"><strong>${t.task}</strong> – ${t.end_time}</span>`
            + `<button class="btn-task-delete" onclick="deleteTask(${t.taskId})" title="Smazat úkol">&times;</button>`
            + `</div>`
        ).join('');
    } else {
        container.innerHTML = '<p>Žádné nepřiřazené úkoly.</p>';
    }
}

function deleteTask(taskId) {
    if (!confirm('Opravdu chcete smazat tento úkol?')) return;
    fetch('api_tasks.php', {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ taskId: taskId })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            loadAllTasks().then(() => renderCalendar(window.currentCalendarDate));
        } else {
            alert('Chyba při mazání úkolu.');
        }
    })
    .catch(() => alert('Chyba při mazání úkolu.'));
}

function unassignTask(taskId) {
    fetch('api_tasks.php', {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ taskId: taskId, date: null })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            loadAllTasks().then(() => {
                renderCalendar(window.currentCalendarDate);
                if (window.currentSelectedDate) {
                    toggleDay(null, window.currentSelectedDate);
                }
            });
        } else {
            alert('Chyba při odebírání úkolu.');
        }
    })
    .catch(() => alert('Chyba při odebírání úkolu.'));
}
