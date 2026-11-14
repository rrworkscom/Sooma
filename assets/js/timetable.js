document.addEventListener('DOMContentLoaded', () => {
  const dateList = document.getElementById('dateList');
  const monthLabel = document.getElementById('monthLabel');
  const scheduleList = document.getElementById('scheduleList');
  const dateStrip = document.querySelector('.date-strip');
  const timetableCalendar = document.querySelector('.timetable-calendar');
  const filters = {
    className: document.getElementById('classFilter'),
    level: document.getElementById('levelFilter'),
    type: document.getElementById('typeFilter'),
    instructor: document.getElementById('instructorFilter')
  };

  // sample data
  const schedules = [
    { date: '2026-09-17', start: '11:00', end: '12:00', instructor: 'Bintari', className: 'Pilates', category: 'Reformer Pilates', level: 'Intermediate', type: 'Public', slot: 4, capacity: 4 },
    { date: '2026-09-17', start: '13:00', end: '14:00', instructor: 'Bintari', className: 'Pilates', category: 'Reformer Pilates', level: 'Beginner', type: 'Public', slot: 4, capacity: 4 },
    { date: '2026-09-17', start: '16:00', end: '17:00', instructor: 'Grace', className: 'Pilates', category: 'Reformer Pilates', level: 'Beginner', type: 'Private', slot: 2, capacity: 2 },
    { date: '2026-09-18', start: '09:00', end: '10:00', instructor: 'Bintari', className: 'Pilates', category: 'Reformer Pilates', level: 'Intermediate', type: 'Public', slot: 4, capacity: 4 },
    { date: '2026-09-18', start: '10:00', end: '11:00', instructor: 'Grace', className: 'Pilates', category: 'Reformer Pilates', level: 'Beginner', type: 'Public', slot: 3, capacity: 4 },
    { date: '2026-09-18', start: '16:30', end: '17:30', instructor: 'Alifa', className: 'Yoga', category: 'Regular Yoga', level: 'Beginner', type: 'Public', slot: 10, capacity: 10 },
    { date: '2026-09-18', start: '10:00', end: '11:00', instructor: 'Grace', className: 'Pilates', category: 'Reformer Pilates', level: 'Advanced', type: 'Private', slot: 0, capacity: 2 },
    { date: '2026-09-19', start: '08:00', end: '09:00', instructor: 'Grace', className: 'Pilates', category: 'Reformer Pilates', level: 'Beginner', type: 'Public', slot: 1, capacity: 4 },
    { date: '2026-09-19', start: '10:00', end: '11:00', instructor: 'Grace', className: 'Pilates', category: 'Reformer Pilates', level: 'Intermediate', type: 'Public', slot: 0, capacity: 4 },
    { date: '2026-09-19', start: '14:00', end: '15:00', instructor: 'Bintari', className: 'Pilates', category: 'Reformer Pilates', level: 'Intermediate', type: 'Private', slot: 2, capacity: 2 }
  ];

  // keep the visible week separate from the currently selected date.
  let weekStartDate = new Date(2026, 8, 17);
  let selectedDate = new Date(2026, 8, 17);
  const pad = n => String(n).padStart(2, '0');
  const iso = d => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
  const timeText = t => { const [h, m] = t.split(':').map(Number); return `${pad(h)}.${pad(m)}`; };
  const monthText = d => d.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
  const longDate = d => d.toLocaleDateString('en-US', { weekday: 'short', day: '2-digit', month: 'short', year: 'numeric' });

  function renderDates() {
    dateList.innerHTML = '';
    monthLabel.textContent = monthText(weekStartDate);

    for (let i = 0; i < 7; i++) {
      const date = new Date(weekStartDate);
      date.setDate(weekStartDate.getDate() + i);

      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'date-card' + (iso(date) === iso(selectedDate) ? ' selected' : '');
      button.setAttribute('aria-pressed', iso(date) === iso(selectedDate) ? 'true' : 'false');
      button.innerHTML = `
        <span class="date-day">${date.toLocaleDateString('en-US', { weekday: 'short' })}</span>
        <strong class="date-number">${date.getDate()}</strong>
      `;

      button.addEventListener('click', () => {
        if (iso(date) === iso(selectedDate)) return;
        selectedDate = new Date(date);
        renderDates();
        renderSchedules();
        moveDateStripToSelectedDay(true);
      });

      dateList.appendChild(button);
    }
  }

  function renderSchedules() {
    // return the date strip to its original container before rebuilding the schedule DOM.
    if (dateStrip && timetableCalendar && dateStrip.parentElement !== timetableCalendar) {
      timetableCalendar.appendChild(dateStrip);
    }
    scheduleList.innerHTML = '';
    for (let i = 0; i < 7; i++) {
      const date = new Date(weekStartDate);
      date.setDate(weekStartDate.getDate() + i);
      const dayItems = schedules.filter(s => s.date === iso(date))
        .filter(s => !filters.className.value || s.className === filters.className.value)
        .filter(s => !filters.level.value || s.level === filters.level.value)
        .filter(s => !filters.type.value || s.type === filters.type.value)
        .filter(s => !filters.instructor.value || s.instructor === filters.instructor.value)
        .sort((a, b) => a.start.localeCompare(b.start));

      const section = document.createElement('section');
      section.className = 'schedule-day schedule-day-enter';
      section.dataset.date = iso(date);
      const heading = document.createElement('h3');
      heading.className = 'schedule-day-heading';
      heading.innerHTML = `<span>${longDate(date)}</span><small>${dayItems.length} ${dayItems.length === 1 ? 'class' : 'classes'}</small>`;
      section.appendChild(heading);

      if (!dayItems.length) {
        const empty = document.createElement('p');
        empty.className = 'empty-schedule';
        empty.textContent = 'No classes scheduled.';
        section.appendChild(empty);
      }

      dayItems.forEach(item => {
        const full = item.slot >= item.capacity;
        const label = full ? 'Join Waitlist' : 'Book Now';
        const card = document.createElement('article');
        card.className = 'schedule-card';
        card.innerHTML = `
          <div class="schedule-time"><span>${timeText(item.start)} -</span><span>${timeText(item.end)} WIB</span></div>
          ${[['Coach', item.instructor], ['Class', item.className], ['Category', item.category], ['Level', item.level], ['Class Type', item.type], ['Slot', `${item.slot} / ${item.capacity}`]].map(([title, value]) => `<div class="schedule-detail"><span class="detail-label">${title}</span><span>${value}</span></div>`).join('')}
          <button class="schedule-action ${full ? 'waitlist' : 'book'}" type="button">${label}</button>`;
        card.querySelector('button').addEventListener('click', () => {
          if (full) {
            alert('This class is fully booked.');
            return;
          }

          const params = new URLSearchParams({
            date: item.date,
            start: item.start,
            end: item.end,
            instructor: item.instructor,
            className: item.className,
            category: item.category,
            level: item.level,
            type: item.type,
            slot: item.slot,
            capacity: item.capacity
          });

          window.location.href = `booking-detail.php?${params.toString()}`;
        });
        section.appendChild(card);
      });
      scheduleList.appendChild(section);
    }
  }

  function moveDateStripToSelectedDay(animate = true) {
    const targetSection = scheduleList.querySelector(`[data-date="${iso(selectedDate)}"]`);
    if (!dateStrip || !targetSection) return;

    const firstRect = dateStrip.getBoundingClientRect();
    targetSection.insertBefore(dateStrip, targetSection.querySelector('.schedule-day-heading'));
    dateStrip.classList.add('date-strip--moved');

    const lastRect = dateStrip.getBoundingClientRect();

    // Scroll the page so the moved date picker sits BELOW the fixed header.
    // Read the header's real rendered height instead of assuming a fixed offset.
    const fixedHeader = document.querySelector('.site-header, .main-nav');
    let headerBottom = 0;

    if (fixedHeader) {
      const headerRect = fixedHeader.getBoundingClientRect();
      const headerPosition = window.getComputedStyle(fixedHeader).position;

      if (headerPosition === 'fixed' || headerPosition === 'sticky') {
        headerBottom = headerRect.bottom;
      }
    }

    const headerGap = 24;
    const visibleTop = headerBottom + headerGap;
    const scrollTarget = Math.max(0, window.scrollY + lastRect.top - visibleTop);

    window.scrollTo({
      top: scrollTarget,
      behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth'
    });

    if (!animate || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      dateStrip.style.transform = '';
      dateStrip.style.transition = '';
      return;
    }

    const deltaX = firstRect.left - lastRect.left;
    const deltaY = firstRect.top - lastRect.top;
    dateStrip.style.transition = 'none';
    dateStrip.style.transform = `translate(${deltaX}px, ${deltaY}px)`;
    dateStrip.getBoundingClientRect(); // Force layout before starting the transition.

    requestAnimationFrame(() => {
      dateStrip.style.transition = 'transform .7s cubic-bezier(.22,.8,.25,1)';
      dateStrip.style.transform = 'translate(0, 0)';
    });

    dateStrip.addEventListener('transitionend', function clearMove(event) {
      if (event.propertyName !== 'transform') return;
      dateStrip.style.transition = '';
      dateStrip.style.transform = '';
      dateStrip.removeEventListener('transitionend', clearMove);
    });
  }

  document.getElementById('previousWeek').addEventListener('click', () => {
    weekStartDate.setDate(weekStartDate.getDate() - 7);
    selectedDate = new Date(weekStartDate);
    renderDates();
    renderSchedules();
    moveDateStripToSelectedDay(true);
  });
  document.getElementById('nextWeek').addEventListener('click', () => {
    weekStartDate.setDate(weekStartDate.getDate() + 7);
    selectedDate = new Date(weekStartDate);
    renderDates();
    renderSchedules();
    moveDateStripToSelectedDay(true);
  });
  Object.values(filters).forEach(select => select.addEventListener('change', () => {
    renderSchedules();
    moveDateStripToSelectedDay(false);
  }));
  renderDates();
  renderSchedules();
});
