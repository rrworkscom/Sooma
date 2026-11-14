<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$pageTitle = 'Timetable';
$bodyClass = 'timetable-page';
$headerType = 'customer';
$headerLogo = 'black';
$basePath = '../assets/';
require_once __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="../assets/css/timetable.css">

<main class="timetable-main">
  <section class="timetable-heading">
    <h1>Timetable</h1>
    <p>Class schedules and booking page</p>
  </section>

  <section class="timetable-calendar" aria-label="Choose a date">
    <div class="calendar-month-row">
      <h2 class="calendar-month-title">
        <img src="../assets/images/calendar.svg" alt="" class="calendar-icon">
        <span id="monthLabel"></span>
      </h2>
      <button class="private-request-btn" type="button">✚ Request Private</button>
    </div>
    <div class="date-strip">
      <button class="date-nav-btn" id="previousWeek" type="button" aria-label="Previous week"><span class="chevron chevron-left"></span></button>
      <div class="date-list" id="dateList"></div>
      <button class="date-nav-btn" id="nextWeek" type="button" aria-label="Next week"><span class="chevron chevron-right"></span></button>
    </div>
  </section>

  <section class="timetable-filters" aria-label="Filter classes">
    <select id="classFilter" aria-label="Class">
      <option value="">Class</option>
      <option>Pilates</option>
      <option>Yoga</option>
    </select>
    <select id="levelFilter" aria-label="Level">
      <option value="">Level</option>
      <option>Beginner</option>
      <option>Intermediate</option>
      <option>Advanced</option>
    </select>
    <select id="typeFilter" aria-label="Type">
      <option value="">Type</option>
      <option>Public</option>
      <option>Private</option>
    </select>
    <select id="instructorFilter" aria-label="Instructor">
      <option value="">Instructor</option>
      <option>Alifa</option>
      <option>Bintari</option>
      <option>Bunga</option>
      <option>Grace</option>
      <option>Ulung</option>
    </select>
  </section>
  <div class="timetable-divider"></div>
  <section id="scheduleList" class="schedule-list" aria-live="polite"></section>
</main>

<script src="../assets/js/timetable.js?v=2"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>