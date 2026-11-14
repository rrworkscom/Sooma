<?php
session_start();

require_once __DIR__ . '/../login-regist/connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: schedule-class.php');
    exit;
}

$className  = $_POST['className'] ?? '';
$classType  = $_POST['classType'] ?? '';
$level      = $_POST['level'] ?? '';
$instructor = $_POST['instructor'] ?? '';
$memberSlot = $_POST['memberSlot'] ?? '';
$date       = $_POST['scheduleDate'] ?? '';
$startTime  = $_POST['startTime'] ?? '';
$endTime    = $_POST['endTime'] ?? '';
$price      = $_POST['price'] ?? '';

$sql = "INSERT INTO classes
        (class_name, class_type, level, instructor,
         schedule_date, start_time, end_time, price, member_slot)
        VALUES
        (:class_name, :class_type, :level, :instructor,
         :schedule_date, :start_time, :end_time, :price, :member_slot)";

try {
    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':class_name'    => $className,
        ':class_type'    => $classType,
        ':level'         => $level,
        ':instructor'    => $instructor,
        ':schedule_date' => $date,
        ':start_time'    => $startTime,
        ':end_time'      => $endTime,
        ':price'         => $price,
        ':member_slot'   => $memberSlot
    ]);

    header('Location: schedule-class.php?success=1');
    exit;

} catch (PDOException $e) {
    error_log('Schedule insert failed: ' . $e->getMessage());

    header('Location: schedule-class.php?error=save_failed');
    exit;
}