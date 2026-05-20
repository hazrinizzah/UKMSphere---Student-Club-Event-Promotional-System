<?php
include 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'club') {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get club id
$club_sql = "SELECT id FROM clubs_abelities WHERE user_id = ?";
$club_stmt = $conn->prepare($club_sql);
$club_stmt->bind_param("i", $user_id);
$club_stmt->execute();
$club_result = $club_stmt->get_result();
$club_data = $club_result->fetch_assoc();
$club_id = $club_data['id'];
$club_stmt->close();

$event_id = intval($_GET['event_id']);

// Verify event belongs to club
$sql = "SELECT id, title, event_date, event_time, location, category FROM events_abelities WHERE id=? AND club_id=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $event_id, $club_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    die("Error: Event not found or you don't have permission!");
}
$event = $result->fetch_assoc();
$stmt->close();

// Get participants
$part_sql = "SELECT s.full_name, s.matric_number, s.phone_number, er.registered_at
             FROM event_registrations_abelities er
             JOIN students_abelities s ON er.student_id = s.id
             WHERE er.event_id=?";
$part_stmt = $conn->prepare($part_sql);
$part_stmt->bind_param("i", $event_id);
$part_stmt->execute();
$part_result = $part_stmt->get_result();

$total_participants = $part_result->num_rows;

// --- INCLUDE HEADER ---
include 'header.php'; 
?>

<style>
    .text-ukm-blue { color: var(--ukm-blue); }
    .bg-ukm-blue { background-color: var(--ukm-blue); }
</style>

<div class="max-w-7xl mx-auto px-4 py-8 min-h-screen">
    
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <span class="category-badge category-<?php echo htmlspecialchars($event['category']); ?>">
                    <?php echo ucfirst($event['category']); ?>
                </span>
                <span class="text-gray-500 text-sm">Event ID: #<?php echo $event['id']; ?></span>
            </div>
            <h1 class="text-3xl font-bold text-gray-900 leading-tight"><?php echo htmlspecialchars($event['title']); ?></h1>
            <div class="flex flex-wrap text-gray-600 mt-2 gap-x-4 text-sm font-medium">
                <span class="flex items-center"><svg class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg><?php echo date('F j, Y', strtotime($event['event_date'])); ?></span>
                <span class="flex items-center"><svg class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg><?php echo date('g:i A', strtotime($event['event_time'])); ?></span>
                <span class="flex items-center"><svg class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg><?php echo htmlspecialchars($event['location']); ?></span>
            </div>
        </div>
        <a href="club_events.php" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 font-medium transition-colors shadow-sm">
            <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Back to My Events
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
            <h2 class="text-lg font-bold text-gray-800 flex items-center">
                <svg class="w-5 h-5 mr-2 text-ukm-blue" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                Participant List
            </h2>
            <span class="bg-blue-100 text-ukm-blue py-1 px-3 rounded-full text-xs font-bold border border-blue-200">
                Total: <?php echo $total_participants; ?>
            </span>
        </div>

        <?php if ($total_participants > 0): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">No.</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Name</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Matric No</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Phone</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Registered At</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php 
                        $counter = 1;
                        while ($row = $part_result->fetch_assoc()): 
                        ?>
                            <tr class="hover:bg-blue-50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $counter++; ?></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($row['full_name']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                        <?php echo htmlspecialchars($row['matric_number']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($row['phone_number']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('d M Y, h:i A', strtotime($row['registered_at'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No participants yet</h3>
                <p class="mt-1 text-sm text-gray-500">Wait for students to register for this event.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php 
// --- INCLUDE FOOTER ---
include 'footer.php';

$part_stmt->close(); 
$conn->close(); 
?>