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
    
    window.selectedDay = null; // Přidáno pro sledování vybraného dne
    window.currentSelectedDate = null; // Pro sledování vybraného dne v day view
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
                // Přidat do localStorage
                const allTasks = JSON.parse(localStorage.getItem('allTasks') || '[]');
                allTasks.push({ id: data.task_id, task: taskName, end_time: endTime });
                localStorage.setItem('allTasks', JSON.stringify(allTasks));
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
                // Uložit do localStorage
                localStorage.setItem('allTasks', JSON.stringify(data.tasks));
                
                container.innerHTML = data.tasks.map(t =>
                    `<div draggable="true" ondragstart="dragTask(event)" data-task-id="${t.id}" style="cursor: grab; background: #e8f4f8; padding: 10px 12px; margin: 6px 0; border-radius: 6px; border-left: 4px solid #3498db;">`
                    + `<strong>${t.task}</strong> – ${t.end_time}` + `</div>`
                ).join('');
            } else {
                container.innerHTML = '<p>Žádné úkoly.</p>';
                localStorage.setItem('allTasks', JSON.stringify([]));
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
                    <div class="day-tasks" ondrop="dropTask(event)" ondragover="allowDrop(event)">
                        ${dayTasks.map(task => `<div class="assigned-task" draggable="true" ondragstart="dragTask(event)" data-task-id="${task.taskId}">${task.task} - ${task.end_time}</div>`).join('')}
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
            dayView.style.display = 'none';
            calendarHeader.style.display = 'flex';
            calendarWeekdays.style.display = 'flex';
            calendarGrid.style.display = 'grid';
        });
        
        // Přidat event listeners pro drop zone v dayViewTasks
        const dayViewTasks = document.getElementById('dayViewTasks');
        dayViewTasks.addEventListener('dragover', allowDrop);
        dayViewTasks.addEventListener('drop', dropTaskToDay);
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
function toggleDay(dayElement) {
    const calendarWidget = document.getElementById('calendarWidget');
    const calendarHeader = calendarWidget.querySelector('.calendar-header');
    const calendarWeekdays = calendarWidget.querySelector('.calendar-weekdays');
    const calendarGrid = calendarWidget.querySelector('.calendar-grid');
    const dayView = document.getElementById('dayView');
    const dateKey = dayElement.dataset.date;
    
    console.log('Toggling day:', dateKey);
    
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
    console.log('Day tasks:', dayTasks);
    
    document.getElementById('dayViewDate').textContent = date.toLocaleDateString('cs-CZ', { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    });
    const tasksContainer = document.getElementById('dayViewTasks');
    if (dayTasks.length > 0) {
        tasksContainer.innerHTML = dayTasks.map(task => `<div class="assigned-task" draggable="true" ondragstart="dragTask(event)" data-task-id="${task.taskId}" style="cursor: grab; background: white; padding: 10px; margin: 5px 0; border-radius: 3px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); color: #333;">${task.task} - ${task.end_time}</div>`).join('');
    } else {
        tasksContainer.innerHTML = '<p style="color: #999; text-align: center;">Přetáhněte sem úkol nebo <br>nejsou pro tento den žádné úkoly.</p>';
    }
}

// Funkce pro drag and drop
function allowDrop(ev) {
    ev.preventDefault();
}

function dragTask(ev) {
    let element = ev.target;
    // Proklikej až k elementu s data-task-id
    while (element && !element.getAttribute('data-task-id')) {
        element = element.parentElement;
    }
    if (element) {
        const taskId = element.getAttribute('data-task-id');
        ev.dataTransfer.effectAllowed = 'move';
        ev.dataTransfer.setData("text", taskId);
        console.log('Dragging task ID:', taskId, 'Element:', element);
    } else {
        console.log('No element with data-task-id found');
    }
}

function dropTask(ev) {
    ev.preventDefault();
    const taskId = ev.dataTransfer.getData("text");
    const dayElement = ev.target.closest('.calendar-day');
    const dateKey = dayElement.dataset.date;
    
    // Najít úkol podle ID
    const allTasks = JSON.parse(localStorage.getItem('allTasks') || '[]');
    const taskIndex = allTasks.findIndex(t => t.taskId == taskId);
    if (taskIndex !== -1) {
        const task = allTasks.splice(taskIndex, 1)[0];
        assignTaskToDay(task, dateKey);
        localStorage.setItem('allTasks', JSON.stringify(allTasks));
        loadAllTasks();
        renderCalendar(window.currentCalendarDate);
    }
}

function dropTaskToDay(ev) {
    ev.preventDefault();
    const taskId = ev.dataTransfer.getData("text");
    const dateKey = window.currentSelectedDate;
    
    console.log('Dropping task ID:', taskId, 'to date:', dateKey);
    
    if (!dateKey || !taskId) {
        console.log('Invalid dateKey or taskId');
        return;
    }
    
    // Najít úkol podle ID
    const allTasks = JSON.parse(localStorage.getItem('allTasks') || '[]');
    console.log('All tasks:', allTasks);
    
    const task = allTasks.find(t => t.taskId == taskId);
    
    if (task) {
        console.log('Found task:', task);
        assignTaskToDay(task, dateKey);
        // Aktualizovat dayViewTasks
        const dayTasks = getDayTasks(dateKey);
        console.log('Day tasks after assignment:', dayTasks);
        
        const tasksContainer = document.getElementById('dayViewTasks');
        if (dayTasks.length > 0) {
            tasksContainer.innerHTML = dayTasks.map(task => `<div class="assigned-task" draggable="true" ondragstart="dragTask(event)" data-task-id="${task.taskId}" style="cursor: grab; background: white; padding: 10px; margin: 5px 0; border-radius: 3px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); color: #333;">${task.task} - ${task.end_time}</div>`).join('');
        } else {
            tasksContainer.innerHTML = '<p style="color: #999; text-align: center;">Přetáhněte sem úkol nebo <br>nejsou pro tento den žádné úkoly.</p>';
        }
        loadAllTasks();
    } else {
        console.log('Task not found!');
    }
}

// Pomocné funkce pro správu úkolů
function getDayTasks(dateKey) {
    const dayTasks = JSON.parse(localStorage.getItem('dayTasks') || '{}');
    console.log('getDayTasks for', dateKey, ':', dayTasks[dateKey] || []);
    return dayTasks[dateKey] || [];
}

function assignTaskToDay(task, dateKey) {
    const dayTasks = JSON.parse(localStorage.getItem('dayTasks') || '{}');
    if (!dayTasks[dateKey]) dayTasks[dateKey] = [];
    // Zkontrolovat, zda už není přiřazen
    if (!dayTasks[dateKey].find(t => t.taskId == task.taskId)) {
        dayTasks[dateKey].push(task);
        localStorage.setItem('dayTasks', JSON.stringify(dayTasks));
        console.log('Assigned task', task.taskId, 'to', dateKey, '- updated dayTasks:', dayTasks);
    } else {
        console.log('Task', task.taskId, 'already assigned to', dateKey);
    }
}

// Aktualizace loadAllTasks pro draggable
function loadAllTasks() {
    fetch('api_tasks.php')
        .then(res => res.json())
        .then(data => {
            console.log('API response:', data);
            console.log('Tasks data:', data.tasks);
            const container = document.getElementById('tasksContainer');
            if (data.success && data.tasks.length > 0) {
                // Uložit do localStorage
                localStorage.setItem('allTasks', JSON.stringify(data.tasks));
                
                container.innerHTML = data.tasks.map(t => {
                    console.log('Task object:', t);
                    return `<div draggable="true" ondragstart="dragTask(event)" data-task-id="${t.taskId}" style="cursor: grab; background: #e8f4f8; padding: 10px 12px; margin: 6px 0; border-radius: 6px; border-left: 4px solid #3498db;">`
                        + `<strong>${t.task}</strong> – ${t.end_time}` + `</div>`;
                }).join('');
            } else {
                container.innerHTML = '<p>Žádné úkoly.</p>';
                localStorage.setItem('allTasks', JSON.stringify([]));
            }
        })
        .catch(err => console.error('Chyba:', err));
}
