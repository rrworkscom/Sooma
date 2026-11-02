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

    if (!calendarBody || !weekLabel) {
        console.error('Elemen kalender utama tidak ditemukan di DOM.');
        return;
    }

    let schedules = JSON.parse(
        localStorage.getItem('sooma_schedules') || '[]'
    );

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

        if (calendarDays) {
            calendarDays.innerHTML = weekDates.map(date => {
                const formattedDay = date.toLocaleDateString('en-GB', {
                    weekday: 'long',
                    day: 'numeric'
                });

                return `<div class="calendar-day-header">${formattedDay}</div>`;
            }).join('');
        }

        calendarBody.innerHTML = hours.map(hour =>
            weekDates.map(date => {
                const dateStr = formatDateForStorage(date);

                const matchedSchedules = schedules.filter(schedule =>
                    schedule.date === dateStr &&
                    schedule.startTime &&
                    schedule.startTime.startsWith(hour.substring(0, 2))
                );

                const content = matchedSchedules.map(() => `
                    <div class="scheduled-item">
                        <small class="scheduled-label">Scheduled</small>
                    </div>
                `).join('');

                return `
                    <div
                        class="calendar-cell"
                        data-date="${dateStr}"
                        data-time="${hour}"
                        tabindex="0"
                        role="button"
                        aria-label="Add schedule on ${dateStr} at ${hour}">
                        ${content}
                    </div>
                `;
            }).join('')
        ).join('');

        if (prevWeekBtn) {
            prevWeekBtn.disabled = false;
        }

        if (nextWeekBtn) {
            nextWeekBtn.disabled = false;
        }
    }

    function openModal(dateString, timeString) {
        if (!modal || !scheduleForm) return;

        selectedDate = dateString;
        selectedTime = timeString;

        modalDateEl.textContent = formatDateForModal(selectedDate);

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
            event.preventDefault();

            if (!selectedDate || !selectedTime) {
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
                price: formData.get('price')
            };

            schedules.push(newSchedule);
            localStorage.setItem(
                'sooma_schedules',
                JSON.stringify(schedules)
            );

            closeModal();
            renderCalendar();

            alert('Schedule saved successfully!');
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
