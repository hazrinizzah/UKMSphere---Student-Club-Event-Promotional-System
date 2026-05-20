<?php
include 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'club') {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get the club ID
$club_sql = "SELECT id FROM clubs_abelities WHERE user_id = ?";
$club_stmt = $conn->prepare($club_sql);
$club_stmt->bind_param("i", $user_id);
$club_stmt->execute();
$club_result = $club_stmt->get_result();
$club_data = $club_result->fetch_assoc();

// FIX: Replaced '??' with isset check for older PHP compatibility
$club_id = isset($club_data['id']) ? $club_data['id'] : null;

$club_stmt->close();

// FIX: Ensure event_id exists and valid
if (isset($_POST['event_id'])) {
    $event_id = intval($_POST['event_id']);
} elseif (isset($_GET['event_id'])) {
    $event_id = intval($_GET['event_id']);
} else {
    die("Error: Event ID not specified!");
}

// Verify event ownership
$sql = "SELECT id FROM events_abelities WHERE id=? AND club_id=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $event_id, $club_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    die("Error: Event not found or you don't have permission!");
}
$stmt->close();

// Delete event registrations first (Foreign Key cleanup)
$del_reg = $conn->prepare("DELETE FROM event_registrations_abelities WHERE event_id=?");
$del_reg->bind_param("i", $event_id);
$del_reg->execute();
$del_reg->close();

// Delete associated feedback (Good practice to clean up feedback too)
$del_feed = $conn->prepare("DELETE FROM feedback_abelities WHERE event_id=?");
$del_feed->bind_param("i", $event_id);
$del_feed->execute();
$del_feed->close();

// Delete event itself
$del_event = $conn->prepare("DELETE FROM events_abelities WHERE id=? AND club_id=?");
$del_event->bind_param("ii", $event_id, $club_id);
if ($del_event->execute()) {
    header("Location: club_events.php?success=deleted");
    exit();
} else {
    die("Error deleting event: " . $del_event->error);
}
$del_event->close();
$conn->close();
?>