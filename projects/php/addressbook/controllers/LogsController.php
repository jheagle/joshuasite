<?php

session_start();

require_once($_SERVER['DOCUMENT_ROOT'] . '/projects/php/addressbook/models/TrackingClass.php');

$limit = isset($_POST['limit']) ? $_POST['limit'] : 10;
$offset = isset($_POST['offset']) ? $_POST['offset'] : 0;
if (isset($_POST['customer']) && $_POST['customer'] < 0) {
    header('Content-Type: application/json');
    $db = new DBConnect('', '', '', '', false);
    $tracking = new Tracking($db, isset($_SESSION['ab_user']) ? $_SESSION['ab_user'] : "");

    $events = $tracking->get_all_events($limit, $offset);
    echo json_encode($events);
    return;
} elseif (isset($_POST['customer']) && $_POST['customer'] > 0) {
    header('Content-Type: application/json');
    $customer = isset($_POST) ? "`class_id`='" . intval($_POST['customer']) . "'" : '';
    $db = new DBConnect('', '', '', '', false);
    $tracking = new Tracking($db, isset($_SESSION['ab_user']) ? $_SESSION['ab_user'] : "");
    $events = $tracking->get_all_events($limit, $offset, $customer, "", "");
    echo json_encode($events);
    return;
} else {
    header('Content-Type: application/json');
    $count = isset($_POST['count']) ? "`class_id`='" . intval($_POST['count']) . "'" : '';
    $db = new DBConnect('', '', '', '', false);
    $tracking = new Tracking($db, isset($_SESSION['ab_user']) ? $_SESSION['ab_user'] : "");
    echo $tracking->get_events_count($count);
    return;
}
