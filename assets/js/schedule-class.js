document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('scheduleModal');
    const closeModalBtn = document.getElementById('closeScheduleModal');
    const cancelScheduleBtn = document.getElementById('cancelSchedule');
    const scheduleForm = document.getElementById('scheduleForm');
    const modalDateEl = document.getElementById('modalDate');
    const weekLabel = document.getElementById('weekLabel');
    const prevWeekBtn = document.getElementById('previousWeek');
    const nextWeekBtn = document.getElementById('nextWeek');
    const calendarDays = document.getElementById('calendarDays');
    const calendarBody = document.getElementById('calendarBody');
    const timeLabels = document.getElementById('timeLabels');

    if (!calendarBody || !weekLabel || !scheduleForm) {
        console.error('Elemen kalender atau form tidak ditemukan.');
        return;
    }

    let schedules = JSON.parse(
        localStorage.getItem('sooma_schedules') || '[]'
    );

    const coachColors = {
        Bintari: '#A3B2A1',
        Grace: '#FF9F50',
        Alifa: '#F4D66D',
        Bunga: '#A98BC4',
        Ulung: '#EFA2A8',
        Vanes: '#65AFA5'
    };

    function escapeHTML(value) {
        return String(value ?? '').replace(/[&<>"']/g, char => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        })[char]);
    }

    function getCoachColor(instructor) {
        const coachName = String(instructor ?? '')
            .replace(/^Coach\s+/i, '')
            .trim();

        return coachColors[coachName] || '#A3B2A1';
    }

    let selectedDate = '';
    let selectedTime = '';

    const today = new Date();
    today.setHours(0, 0, 0, 0);

    function getStartOfWeek(date) {
        const start = new Date(date);
        const day = start.getDay();
        const diff = day === 0 ? -6 : 1 - day;

        start.setDate(start.getDate() + diff);
        start.setHours(0, 0, 0, 0);

        return start;
    }

    let currentDate = getStartOfWeek(today);

    const hours = [];

    for (let i = 8; i <= 16; i++) {
        hours.push(`${String(i).padStart(2, '0')}:00`);
    }

    function formatDateForStorage(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');

        return `${year}-${month}-${day}`;
    }

    function formatDateForModal(dateString) {
        const [year, month, day] = dateString.split('-').map(Number);
        const date = new Date(year, month - 1, day);

        return date.toLocaleDateString('en-GB', {
            weekday: 'long',
            day: 'numeric',
            month: 'short',
            year: 'numeric'
        });
    }

    function renderTimeLabels() {
        if (timeLabels) {
            timeLabels.innerHTML = hours
                .map(hour => `<div class="time-label">${hour}</div>`)
                .join('');
        }
    }

    function getWeekDates(startDate) {
        const monday = getStartOfWeek(startDate);
        const dates = [];

        for (let i = 0; i < 5; i++) {
            const date = new Date(monday);
            date.setDate(monday.getDate() + i);
            dates.push(date);
        }

        return dates;
    }

    function renderCalendar() {
        renderTimeLabels();

        const weekDates = getWeekDates(currentDate);

        weekLabel.textContent =
            `${weekDates[0].toLocaleDateString('en-GB', {
                day: 'numeric',
                month: 'short',
                year: 'numeric'
            })} - ${weekDates[4].toLocaleDateString('en-GB', {
                day: 'numeric',
                month: 'short',
                year: 'numeric'
            })}`;

        calendarDays.innerHTML = weekDates.map(date => {
            const formattedDay = date.toLocaleDateString('en-GB', {
                weekday: 'long',
                day: 'numeric'
            });

            return `<div class="calendar-day-header">${formattedDay}</div>`;
        }).join('');

        // Membuat grid kalender kosong
        calendarBody.innerHTML = hours.map(hour =>
            weekDates.map(date => {
                const dateStr = formatDateForStorage(date);

                return `
                <div
                    class="calendar-cell"
                    data-date="${dateStr}"
                    data-time="${hour}"
                    tabindex="0"
                    role="button"
                    aria-label="Add schedule on ${dateStr} at ${hour}">
                </div>
            `;
            }).join('')
        ).join('');

        // Menampilkan schedule berdasarkan coach dan waktu
        schedules.forEach(schedule => {
            const dayIndex = weekDates.findIndex(
                date => formatDateForStorage(date) === schedule.date
            );

            if (dayIndex === -1 || !schedule.startTime || !schedule.endTime) {
                return;
            }

            const [startHour, startMinute] = schedule.startTime.split(':').map(Number);
            const [endHour, endMinute] = schedule.endTime.split(':').map(Number);

            const startMinutes = startHour * 60 + startMinute;
            const endMinutes = endHour * 60 + endMinute;

            // Kalender menampilkan pukul 08.00–17.00
            const calendarStart = 8 * 60;
            const calendarEnd = 17 * 60;

            if (
                endMinutes <= startMinutes ||
                startMinutes < calendarStart ||
                endMinutes > calendarEnd
            ) {
                return;
            }

            const startPosition = (startMinutes - calendarStart) / 60;
            const duration = (endMinutes - startMinutes) / 60;

            const capacity = Number(schedule.memberSlot) || 0;
            const bookedCount = Number(schedule.bookedCount) || 0;

            const className = escapeHTML(schedule.className);

            const event = document.createElement('div');

            event.className = 'schedule-event';

            event.style.setProperty('--col', dayIndex);
            event.style.setProperty('--start', startPosition);
            event.style.setProperty('--len', duration);
            event.style.setProperty('--coach-color', getCoachColor(schedule.instructor));

            event.innerHTML = `
    <div class="schedule-event-content">
        <strong>${className}</strong>
        <div class="schedule-participants">
            ${bookedCount}/${capacity} Participants
        </div>
    </div>
`;

            calendarBody.appendChild(event);
        });

        if (prevWeekBtn) prevWeekBtn.disabled = false;
        if (nextWeekBtn) nextWeekBtn.disabled = false;
    }

    function openModal(dateString, timeString) {
        if (!modal || !scheduleForm) return;

        selectedDate = dateString;
        selectedTime = timeString;

        modalDateEl.textContent = formatDateForModal(selectedDate);

        if (scheduleForm.elements.scheduleDate) {
            scheduleForm.elements.scheduleDate.value = selectedDate;
        }

        scheduleForm.elements.startTime.value = selectedTime;

        const [hours, minutes] = selectedTime.split(':').map(Number);
        const endTime = new Date(2000, 0, 1, hours, minutes);
        endTime.setHours(endTime.getHours() + 1);

        scheduleForm.elements.endTime.value =
            `${String(endTime.getHours()).padStart(2, '0')}:${String(endTime.getMinutes()).padStart(2, '0')}`;
        modal.hidden = false;
    }

    function closeModal() {
        if (modal) modal.hidden = true;
        if (scheduleForm) scheduleForm.reset();

        selectedDate = '';
        selectedTime = '';
    }

    calendarBody.addEventListener('click', event => {
        const cell = event.target.closest('.calendar-cell');

        if (!cell) return;

        openModal(cell.dataset.date, cell.dataset.time);
    });

    calendarBody.addEventListener('keydown', event => {
        if (event.key !== 'Enter' && event.key !== ' ') return;

        const cell = event.target.closest('.calendar-cell');

        if (!cell) return;

        event.preventDefault();
        openModal(cell.dataset.date, cell.dataset.time);
    });

    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', closeModal);
    }

    if (cancelScheduleBtn) {
        cancelScheduleBtn.addEventListener('click', closeModal);
    }

    if (scheduleForm) {
        scheduleForm.addEventListener('submit', event => {
            if (!selectedDate || !selectedTime) {
                event.preventDefault();
                alert('Please select a calendar cell first.');
                return;
            }

            const formData = new FormData(scheduleForm);

            const newSchedule = {
                date: selectedDate,
                className: formData.get('className'),
                classType: formData.get('classType'),
                instructor: formData.get('instructor'),
                memberSlot: formData.get('memberSlot'),
                startTime: formData.get('startTime'),
                endTime: formData.get('endTime'),
                level: formData.get('level'),
                price: formData.get('price'),
                bookedCount: 0
            };

            schedules.push(newSchedule);

            localStorage.setItem(
                'sooma_schedules',
                JSON.stringify(schedules)
            );

        });
    }

    if (prevWeekBtn) {
        prevWeekBtn.addEventListener('click', () => {
            currentDate.setDate(currentDate.getDate() - 7);
            renderCalendar();
        });
    }

    if (nextWeekBtn) {
        nextWeekBtn.addEventListener('click', () => {
            currentDate.setDate(currentDate.getDate() + 7);
            renderCalendar();
        });
    }

    renderCalendar();
});