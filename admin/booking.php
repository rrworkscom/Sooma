<?php
session_start();

$headerType = 'admin';
$pageTitle = 'Booking';
$bodyClass = 'admin-page';

require_once __DIR__ . '/../includes/header.php';
?>

<main class="admin-main">
    <section class="schedule-heading">
        <div>
            <h1>Schedule &amp; Class</h1>

            <div class="week-controls">
                <button
                    class="round-button"
                    id="previousWeek"
                    aria-label="Previous week"
                    type="button">
                    &lt;
                </button>

                <strong id="weekLabel"></strong>

                <button
                    class="round-button"
                    id="nextWeek"
                    aria-label="Next week"
                    type="button">
                    &gt;
                </button>
            </div>
        </div>
    </section>

    <section class="calendar-shell" aria-label="Weekly schedule calendar">
        <div class="time-column">
            <div class="time-column-spacer"></div>
            <div class="time-labels" id="timeLabels"></div>
        </div>

        <div class="calendar-scroll">
            <div class="calendar-grid">
                <div class="calendar-days" id="calendarDays"></div>
                <div class="calendar-body" id="calendarBody"></div>
            </div>
        </div>
    </section>
</main>

<footer class="admin-footer">
    <div class="admin-footer-main">
        <div class="footer-brand-section">
            <a class="footer-logo" href="../index.php">
                <img
                    src="../assets/images/sooma-logo-black.png"
                    alt="Sooma Wellness">
            </a>

            <div class="footer-info">
                <h3>Location</h3>
                <p>Jl. Ciliwung no.62, Blitar</p>

                <h3>Operating Hours</h3>
                <p>
                    Tue &amp; Fri: 16:30 WIB<br>
                    Wed: 08:00 &amp; 16:30 WIB<br>
                    Sat: 16:00 WIB | Sun: 08:00 WIB
                </p>
            </div>
        </div>

        <div class="footer-quick-links">
            <h3>Quick Link</h3>
            <a href="../index.php">Home</a>
            <a href="scheduleclass.php">Schedule &amp; Class</a>
            <a href="booking.php">Booking</a>
            <a href="instructors.php">Instructors</a>
        </div>
    </div>

    <div class="admin-footer-bottom">
        <span>© 2026 Sooma Wellness. All rights reserved.</span>

        <div class="footer-legal-links">
            <a href="#">Privacy Policy</a>
            <a href="#">Terms of Service</a>
            <a href="#">Cookie Settings</a>
        </div>
    </div>
</footer>

<div class="modal-backdrop" id="scheduleModal" hidden>
    <section
        class="schedule-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="modalTitle">

        <button
            class="modal-close"
            id="closeScheduleModal"
            type="button"
            aria-label="Close">
            &times;
        </button>

        <h2 id="modalTitle">Add New Schedule</h2>
        <p class="modal-date" id="modalDate"></p>

        <form
            id="scheduleForm"
            action="booking-schedule.php"
            method="POST">

            <input
                type="hidden"
                name="scheduleDate"
                id="scheduleDate">

            <label class="form-field">
                <span>Class Name</span>
                <select name="className" required>
                    <option value="Pilates Reformer">Pilates Reformer</option>
                    <option value="Yoga Class">Yoga Class</option>
                </select>
            </label>

            <label class="form-field">
                <span>Class Type</span>
                <select name="classType" required>
                    <option value="Reguler">Reguler</option>
                    <option value="Private">Private</option>
                </select>
            </label>

            <label class="form-field">
                <span>Instructor</span>
                <select name="instructor" required>
                    <option value="Coach Grace">Coach Grace</option>
                    <option value="Coach Alifa">Coach Alifa</option>
                    <option value="Coach Bunga">Coach Bunga</option>
                    <option value="Coach Bintari">Coach Bintari</option>
                    <option value="Coach Ulung">Coach Ulung</option>
                </select>
            </label>

            <label class="form-field">
                <span>Member Slot</span>
                <input
                    name="memberSlot"
                    type="number"
                    min="1"
                    max="30"
                    value="4"
                    required>
            </label>

            <div class="form-field-row">
                <label class="form-field">
                    <span>Start Time</span>
                    <input
                        name="startTime"
                        type="time"
                        value="10:00"
                        required>
                </label>

                <label class="form-field">
                    <span>End Time</span>
                    <input
                        name="endTime"
                        type="time"
                        value="11:00"
                        required>
                </label>
            </div>

            <label class="form-field">
                <span>Level</span>
                <select name="level" required>
                    <option value="Beginner">Beginner</option>
                    <option value="Intermediate">Intermediate</option>
                    <option value="Advanced">Advanced</option>
                </select>
            </label>

            <label class="form-field">
                <span>Price (Rp.)</span>
                <input
                    name="price"
                    type="number"
                    min="0"
                    step="1000"
                    value="100000"
                    required>
            </label>

            <p class="form-error" id="formError" role="alert" hidden></p>

            <div class="modal-actions">
                <button
                    class="cancel-button"
                    id="cancelSchedule"
                    type="button">
                    Cancel
                </button>

                <button class="save-button" type="submit">
                    Save
                </button>
            </div>
        </form>
    </section>
</div>

<script src="schedule.js"></script>
</body>
</html>