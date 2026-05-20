<?php
include 'config.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'student') {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$student_name = $_SESSION['full_name'];

// Get student ID from students_abelities table
$student_sql = "SELECT id FROM students_abelities WHERE user_id = ?";
$student_stmt = $conn->prepare($student_sql);
$student_stmt->bind_param("i", $user_id);
$student_stmt->execute();
$student_result = $student_stmt->get_result();

if ($student_result->num_rows === 0) {
    die("Error: Student profile not found!");
}

$student_data = $student_result->fetch_assoc();
$student_table_id = $student_data['id'];
$student_stmt->close();

// Get student's registered events with event details and feedback status
$sql = "SELECT e.*, er.registered_at, c.club_name,
               CASE 
                   WHEN f.id IS NOT NULL THEN 1
                   ELSE 0
               END as has_feedback
        FROM event_registrations_abelities er
        JOIN events_abelities e ON er.event_id = e.id
        JOIN clubs_abelities c ON e.club_id = c.id
        LEFT JOIN feedback_abelities f ON f.event_id = e.id AND f.student_id = er.student_id
        WHERE er.student_id = ?
        ORDER BY e.event_date DESC, er.registered_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $student_table_id);
$stmt->execute();
$result = $stmt->get_result();

$events = [];
$upcoming_events = [];
$past_events = [];

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $events[] = $row;
        
        // --- FIXED LOGIC HERE ---
        // If event is explicitly 'finished' OR the date is strictly in the past, move to PAST.
        // This ensures events finishing 'Today' are moved to history if status is updated.
        if ($row['event_status'] === 'finished' || $row['event_date'] < date('Y-m-d')) {
            $past_events[] = $row;
        } else {
            $upcoming_events[] = $row;
        }
    }
}
$stmt->close();

// Handle cancellation request
$cancellation_message = null;
$cancellation_error = null;

if ($_SERVER['REQUEST_METHOD'] == "POST" && isset($_POST['cancel_registration'])) {
    $event_id_to_cancel = intval($_POST['event_id']);
    
    // Check if the registration belongs to this student
    $check_sql = "SELECT er.id FROM event_registrations_abelities er 
                  WHERE er.event_id = ? AND er.student_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $event_id_to_cancel, $student_table_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 1) {
        // Delete the registration
        $delete_sql = "DELETE FROM event_registrations_abelities WHERE event_id = ? AND student_id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("ii", $event_id_to_cancel, $student_table_id);
        
        if ($delete_stmt->execute()) {
            // Refresh to show updated list
            header("Location: event_registered.php?success=cancelled");
            exit();
        } else {
            $cancellation_error = "Error cancelling registration: " . $delete_stmt->error;
        }
        $delete_stmt->close();
    } else {
        $cancellation_error = "Error: Registration not found.";
    }
    $check_stmt->close();
}

// Check for success message from redirect
if (isset($_GET['success']) && $_GET['success'] == 'cancelled') {
    $cancellation_message = "Registration cancelled successfully!";
}

// --- INCLUDE HEADER ---
include 'header.php'; 
?>

<style>
    /* Modal styles needed for cancellation confirmation */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1000;
    }
    
    .modal.show {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    /* Custom color utility to match header variables if needed */
    .text-ukm-blue { color: var(--ukm-blue); }
    .bg-ukm-blue { background-color: var(--ukm-blue); }
    .text-ukm-red { color: var(--ukm-red); }
    .bg-ukm-red { background-color: var(--ukm-red); }

    /* Zoom effect */
    .event-card {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .event-card:hover {
        transform: scale(1.025); 
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1); 
        z-index: 10;
    }
</style>

<div class="max-w-7xl mx-auto px-4 py-8 min-h-screen">
    <div class="mb-8 border-b border-gray-200 pb-4">
        <h1 class="text-3xl font-bold text-gray-800">My Registered Events</h1>
        <p class="text-gray-600 mt-2">Manage your event registrations and track your campus activities.</p>
    </div>

    <div class="flex justify-end mb-6 space-x-3">
        <button onclick="filterEvents('upcoming')" class="bg-ukm-blue hover:bg-blue-800 text-white px-4 py-2 rounded shadow-sm text-sm transition-colors">Show Upcoming</button>
        <button onclick="filterEvents('past')" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded shadow-sm text-sm transition-colors">Show Past</button>
        <button onclick="filterEvents('all')" class="bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 px-4 py-2 rounded shadow-sm text-sm transition-colors">Show All</button>
    </div>

    <?php if (isset($cancellation_message)): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded shadow-sm" role="alert">
            <p class="font-bold">Success</p>
            <p><?php echo $cancellation_message; ?></p>
        </div>
    <?php endif; ?>

    <?php if (isset($cancellation_error)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded shadow-sm" role="alert">
            <p class="font-bold">Error</p>
            <p><?php echo $cancellation_error; ?></p>
        </div>
    <?php endif; ?>

    <div id="upcomingSection">
        <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center">
            <span class="bg-blue-100 text-blue-800 text-sm font-semibold mr-2 px-2.5 py-0.5 rounded">Upcoming</span>
            Events
        </h2>
        
        <?php if (!empty($upcoming_events)): ?>
            <div class="space-y-6">
                <?php foreach ($upcoming_events as $event): ?>
                    <div class="event-card bg-white p-6 rounded-xl relative shadow-sm">
                        <div class="flex flex-col md:flex-row justify-between items-start mb-4">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="category-badge category-<?php echo $event['category']; ?>">
                                        <?php echo ucfirst($event['category']); ?>
                                    </span>
                                    <span class="bg-green-100 text-green-800 text-xs font-semibold px-2.5 py-0.5 rounded border border-green-400">Registered</span>
                                    
                                    <?php if($event['event_status'] == 'ongoing'): ?>
                                        <span class="bg-purple-100 text-purple-800 text-xs font-semibold px-2.5 py-0.5 rounded border border-purple-400 animate-pulse">Live Now</span>
                                    <?php endif; ?>
                                </div>
                                <h3 class="text-xl font-bold text-ukm-blue"><?php echo htmlspecialchars($event['title']); ?></h3>
                                <p class="text-sm font-medium text-gray-500 mt-1">Organized by <?php echo htmlspecialchars($event['club_name']); ?></p>
                                
                                <div class="text-sm text-gray-600 mt-3 flex flex-wrap gap-4">
                                    <div class="flex items-center">
                                        <svg class="w-4 h-4 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                        <?php echo date('F j, Y', strtotime($event['event_date'])); ?>
                                    </div>
                                    <div class="flex items-center">
                                        <svg class="w-4 h-4 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        <?php echo date('g:i A', strtotime($event['event_time'])); ?>
                                    </div>
                                    <div class="flex items-center">
                                        <svg class="w-4 h-4 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                        <?php echo htmlspecialchars($event['location']); ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-5 bg-gray-50 p-4 rounded-lg border border-gray-100">
                            <p class="text-gray-700 text-sm leading-relaxed">
                                <?php 
                                $description = $event['description'];
                                if (strlen($description) > 200) {
                                    echo htmlspecialchars(substr($description, 0, 200)) . '...';
                                } else {
                                    echo htmlspecialchars($description);
                                }
                                ?>
                            </p>
                        </div>

                        <div class="border-t border-gray-100 pt-4 flex flex-col sm:flex-row justify-between items-center gap-4">
                            <div class="text-sm text-gray-500">
                                <span class="font-medium">Registered on:</span>
                                <?php echo date('M j, Y \a\t g:i A', strtotime($event['registered_at'])); ?>
                            </div>
                            
                            <div class="flex space-x-3 w-full sm:w-auto">
                                <a href="event_details.php?id=<?php echo $event['id']; ?>" 
                                   class="flex-1 sm:flex-none text-center bg-gray-100 hover:bg-gray-200 text-gray-800 px-4 py-2 rounded text-sm font-medium transition-colors">
                                    View Details
                                </a>
                                <button onclick="showCancelModal(<?php echo $event['id']; ?>, '<?php echo addslashes(htmlspecialchars($event['title'])); ?>')" 
                                        class="flex-1 sm:flex-none text-center bg-red-50 text-red-600 hover:bg-red-100 border border-red-200 px-4 py-2 rounded text-sm font-medium transition-colors">
                                    Cancel Registration
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-lg p-8 text-center border border-gray-200">
                <p class="text-gray-500">No upcoming events found.</p>
                <a href="view_events.php" class="text-ukm-blue hover:underline text-sm mt-2 inline-block">Browse Event Feed &rarr;</a>
            </div>
        <?php endif; ?>
    </div>

    <div id="pastSection" class="mt-12">
        <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center">
            <span class="bg-gray-200 text-gray-700 text-sm font-semibold mr-2 px-2.5 py-0.5 rounded">History</span>
            Past Events
        </h2>
        <?php if (!empty($past_events)): ?>
            <div class="space-y-6">
                <?php foreach ($past_events as $event): ?>
                    <div class="event-card bg-white p-6 rounded-xl border-l-4 border-gray-300 opacity-90 hover:opacity-100 shadow-sm">
                        <div class="flex justify-between items-start mb-4">
                            <div class="flex-1">
                                <h3 class="text-xl font-bold text-gray-700"><?php echo htmlspecialchars($event['title']); ?></h3>
                                <p class="text-sm text-gray-500 mt-1">By <?php echo htmlspecialchars($event['club_name']); ?></p>
                                <div class="text-sm text-gray-500 mt-2">
                                    Held on: <?php echo date('F j, Y', strtotime($event['event_date'])); ?>
                                </div>
                            </div>
                            <div class="flex flex-col items-end gap-2">
                                <span class="bg-gray-100 text-gray-600 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wide">Completed</span>
                                <?php if ($event['has_feedback']): ?>
                                    <span class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs font-bold border border-green-200 flex items-center">
                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        Feedback Sent
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="flex space-x-3 mt-4">
                            <a href="event_details.php?id=<?php echo $event['id']; ?>" 
                               class="text-gray-600 hover:text-ukm-blue text-sm font-medium hover:underline">
                                View Details
                            </a>
                            <span class="text-gray-300">|</span>
                            <?php if ($event['has_feedback']): ?>
                                <a href="view_submitted_feedback.php?event_id=<?php echo $event['id']; ?>" 
                                   class="text-green-600 hover:text-green-800 text-sm font-medium hover:underline">
                                    View My Feedback
                                </a>
                            <?php else: ?>
                                <a href="give_feedback.php?event_id=<?php echo $event['id']; ?>" 
                                   class="text-ukm-red hover:text-red-800 text-sm font-bold hover:underline">
                                    Give Feedback
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-gray-500 italic">No past events found.</p>
        <?php endif; ?>
    </div>

    <?php if (empty($events)): ?>
        <div class="text-center py-16">
            <div class="bg-gray-100 rounded-full h-24 w-24 flex items-center justify-center mx-auto mb-6">
                <svg class="w-12 h-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
            </div>
            <h3 class="text-2xl font-bold text-gray-800 mb-2">No events registered yet</h3>
            <p class="text-gray-500 mb-8 max-w-md mx-auto">
                You haven't registered for any events yet. Explore the event feed to find interesting activities and workshops happening on campus.
            </p>
            <a href="view_events.php" class="bg-ukm-blue hover:bg-blue-800 text-white px-8 py-3 rounded-lg font-medium transition-colors inline-flex items-center shadow-md">
                <svg class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                Explore Events
            </a>
        </div>
    <?php endif; ?>
</div>

<div id="cancelModal" class="modal">
    <div class="bg-white rounded-xl shadow-2xl p-6 max-w-md w-full mx-4 transform transition-all">
        <div class="mb-4 text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-900 mb-2">Cancel Registration?</h3>
            <p class="text-sm text-gray-500" id="cancelModalText">Are you sure you want to cancel your registration for this event?</p>
        </div>
        <form method="POST" action="" id="cancelForm">
            <input type="hidden" name="event_id" id="cancelEventId">
            <div class="flex space-x-3 mt-6">
                <button type="button" onclick="closeCancelModal()" class="flex-1 bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 px-4 py-2 rounded-lg font-medium transition-colors">
                    No, Keep it
                </button>
                <button type="submit" name="cancel_registration" class="flex-1 bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-medium transition-colors shadow-sm">
                    Yes, Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function showCancelModal(eventId, eventTitle) {
        document.getElementById('cancelEventId').value = eventId;
        document.getElementById('cancelModalText').textContent = 
            `Are you sure you want to cancel your registration for "${eventTitle}"? This action cannot be undone.`;
        document.getElementById('cancelModal').classList.add('show');
    }

    function closeCancelModal() {
        document.getElementById('cancelModal').classList.remove('show');
    }

    function filterEvents(type) {
        const upcoming = document.getElementById('upcomingSection');
        const past = document.getElementById('pastSection');

        if (type === 'upcoming') {
            upcoming.style.display = 'block';
            past.style.display = 'none';
        } else if (type === 'past') {
            upcoming.style.display = 'none';
            past.style.display = 'block';
        } else {
            upcoming.style.display = 'block';
            past.style.display = 'block';
        }
    }

    // Close modal when clicking outside
    document.addEventListener('click', function(e) {
        const modal = document.getElementById('cancelModal');
        if (modal && e.target === modal) {
            closeCancelModal();
        }
    });

    // Highlight the "My Events" link in the header if possible
    document.addEventListener("DOMContentLoaded", function() {
        const links = document.querySelectorAll('.nav-link');
        links.forEach(link => {
            if(link.getAttribute('href') === 'event_registered.php') {
                link.classList.add('active');
            }
        });
    });
</script>

<?php 
include 'footer.php'; 
?>
<?php $conn->close(); ?>