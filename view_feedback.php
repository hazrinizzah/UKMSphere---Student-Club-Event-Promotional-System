<?php
include 'config.php';

// 1. Check if user is a logged-in club
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'club') {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// 2. Get the club's internal ID
$club_sql = "SELECT id FROM clubs_abelities WHERE user_id = ?";
$club_stmt = $conn->prepare($club_sql);
$club_stmt->bind_param("i", $user_id);
$club_stmt->execute();
$club_result = $club_stmt->get_result();

if ($club_result->num_rows === 0) {
    die("Error: Club profile not found!");
}
$club_id = $club_result->fetch_assoc()['id'];
$club_stmt->close();

// 3. Get all events for this club that have feedback
$events_sql = "SELECT e.id, e.title, AVG(f.rating) as avg_rating, COUNT(f.id) as feedback_count
               FROM events_abelities e
               JOIN feedback_abelities f ON e.id = f.event_id
               WHERE e.club_id = ?
               GROUP BY e.id, e.title
               ORDER BY e.event_date DESC";
$events_stmt = $conn->prepare($events_sql);
$events_stmt->bind_param("i", $club_id);
$events_stmt->execute();
$events_result = $events_stmt->get_result();
$events_with_feedback = [];
while($row = $events_result->fetch_assoc()) {
    $events_with_feedback[$row['id']] = $row;
}
$events_stmt->close();

// 4. Get all feedback details for all events of this club
// --- MODIFICATION: Use 'submitted_at' ---
$feedback_sql = "SELECT f.*, s.full_name, s.matric_number, e.id as event_id
                 FROM feedback_abelities f
                 JOIN students_abelities s ON f.student_id = s.id
                 JOIN events_abelities e ON f.event_id = e.id
                 WHERE e.club_id = ?
                 ORDER BY f.submitted_at DESC";
// --- END MODIFICATION ---
$feedback_stmt = $conn->prepare($feedback_sql);
$feedback_stmt->bind_param("i", $club_id);
$feedback_stmt->execute();
$feedback_result = $feedback_stmt->get_result();

$all_feedback = [];
while($row = $feedback_result->fetch_assoc()) {
    $all_feedback[$row['event_id']][] = $row;
}
$feedback_stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Feedback - UKMSphere</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #6b46c1 0%, #8b5cf6 25%, #a855f7 50%, #c084fc 75%, #e879f9 100%);
        }
        .star-filled {
            color: #f59e0b; /* amber-500 */
        }
        .star-empty {
            color: #d1d5db; /* gray-300 */
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <nav class="gradient-bg shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center space-x-3">
                    <img src="images/ukm-logo.png" alt="UKM Logo" class="h-28 w-40">
                    <h1 class="text-white text-lg font-bold">UKMSphere</h1>
                </div>
                <div class="flex items-center space-x-2">
                    <a href="dashboard.php" class="text-purple-100 hover:text-white hover:bg-purple-700 px-3 py-2 rounded text-sm font-medium transition-colors">Dashboard</a>
                    <a href="create_event.php" class="text-purple-100 hover:text-white hover:bg-purple-700 px-3 py-2 rounded text-sm font-medium transition-colors">Create Event</a>
                    <a href="club_events.php" class="text-purple-100 hover:text-white hover:bg-purple-700 px-3 py-2 rounded text-sm font-medium transition-colors">My Events</a>
                    <a href="view_feedback.php" class="text-white bg-purple-700 px-3 py-2 rounded text-sm font-medium">Event Feedbacks</a>
                    <a href="view_events.php" class="text-purple-100 hover:text-white hover:bg-purple-700 px-3 py-2 rounded text-sm font-medium transition-colors">Event Feed</a>
                    <!-- Profile Toggle -->
                   <div class="relative">
                        <!-- Toggle Button -->
                        <button onclick="document.getElementById('profileMenu').classList.toggle('hidden')" class="w-10 h-10 rounded-full overflow-hidden border-2 border-purple-700">
                            <img src="images/profile.png" alt="Profile" class="w-full h-full object-cover object-center scale-90">
                        </button>
                        <!-- Dropdown Menu -->
                        <div id="profileMenu" class="absolute right-0 mt-2 w-40 bg-white shadow-lg rounded hidden">
                            <p class="px-4 py-2 text-gray-700 border-b">Hi, <?php echo $_SESSION['club_name']; ?>!</p>
                            <a href="logout.php" class="block px-4 py-2 text-red-600 hover:bg-red-100">Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-6xl mx-auto px-4 py-8">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Event Feedback</h1>
            <p class="text-gray-600 mt-2">See what students are saying about your events.</p>
        </div>

        <?php if (empty($events_with_feedback)): ?>
            <div class="text-center py-16 bg-white rounded-lg shadow-md">
                <svg class="w-24 h-24 mx-auto text-gray-400 mb-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                </svg>
                <h3 class="text-2xl font-medium text-gray-900 mb-4">No Feedback Yet</h3>
                <p class="text-gray-500 max-w-md mx-auto">
                    There is no feedback for any of your past events. Once students submit feedback, it will appear here.
                </p>
            </div>
        <?php else: ?>
            <div class="space-y-8">
                <?php foreach ($events_with_feedback as $event_id => $event): ?>
                    <div class="bg-white rounded-lg shadow-md overflow-hidden">
                        <div class="p-6 border-b border-gray-200">
                            <h2 class="text-2xl font-semibold text-purple-700"><?php echo htmlspecialchars($event['title']); ?></h2>
                            <div class="flex items-center text-gray-600 mt-2">
                                <div class="flex items-center">
                                    <span class="text-lg font-bold text-amber-500 mr-1"><?php echo number_format($event['avg_rating'], 1); ?></span>
                                    <span class="star-filled">★</span>
                                </div>
                                <span class="mx-2">·</span>
                                <span><?php echo $event['feedback_count']; ?> <?php echo ($event['feedback_count'] == 1) ? 'review' : 'reviews'; ?></span>
                            </div>
                        </div>
                        
                        <div class="divide-y divide-gray-200">
                            <?php if (isset($all_feedback[$event_id])): ?>
                                <?php foreach ($all_feedback[$event_id] as $feedback): ?>
                                    <div class="p-6">
                                        <div class="flex items-center justify-between mb-2">
                                            <div>
                                                <span class="font-semibold text-gray-800"><?php echo htmlspecialchars($feedback['full_name']); ?></span>
                                                <span class="text-gray-500 text-sm"> (<?php echo htmlspecialchars($feedback['matric_number']); ?>)</span>
                                            </div>
                                            <span class="text-sm text-gray-500"><?php echo date('M j, Y', strtotime($feedback['submitted_at'])); ?></span>
                                            </div>
                                        
                                        <div class="flex items-center mb-3">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <span class="<?php echo ($i <= $feedback['rating']) ? 'star-filled' : 'star-empty'; ?> text-xl">★</span>
                                            <?php endfor; ?>
                                        </div>

                                        <?php if (!empty($feedback['comments'])): ?>
                                            <p class="text-gray-700 mb-4"><?php echo nl2br(htmlspecialchars($feedback['comments'])); ?></p>
                                        <?php endif; ?>
                                        <?php if (!empty($feedback['photo_path'])): ?>
                                            <img src="<?php echo htmlspecialchars($feedback['photo_path']); ?>" 
                                                 alt="Feedback photo" 
                                                 class="max-w-xs h-auto rounded-lg border border-gray-200">
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>