<?php
include 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Check if event ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: view_events.php");
    exit();
}

$event_id = intval($_GET['id']);
$user_type = $_SESSION['user_type'];
$user_id = $_SESSION['user_id'];

// Get event details with club information
$sql = "SELECT e.*, c.club_name, c.id as club_table_id
    FROM events_abelities e
    JOIN clubs_abelities c ON e.club_id = c.id
    WHERE e.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $event_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: view_events.php");
    exit();
}

$event = $result->fetch_assoc();
$stmt->close();

// --- TRACKING ALGORITHM: RECORD VIEW ---
// Only track if the user is a student
if ($user_type == 'student') {
    // 1. Get the student's internal table ID first
    $track_student_sql = "SELECT id FROM students_abelities WHERE user_id = ?";
    $track_stmt = $conn->prepare($track_student_sql);
    $track_stmt->bind_param("i", $user_id);
    $track_stmt->execute();
    $track_res = $track_stmt->get_result();
    
    if ($track_res->num_rows === 1) {
        $track_student_id = $track_res->fetch_assoc()['id'];
        $current_category = $event['category'];

        // 2. Check if a view record already exists
        $check_view_sql = "SELECT id FROM event_views_abelities WHERE student_id = ? AND event_id = ?";
        $check_view_stmt = $conn->prepare($check_view_sql);
        $check_view_stmt->bind_param("ii", $track_student_id, $event_id);
        $check_view_stmt->execute();
        $view_result = $check_view_stmt->get_result();

        // Use NOW() for SQL time to be consistent with DB, or just skip time tracking precision for now
        if ($view_result->num_rows > 0) {
            $view_row = $view_result->fetch_assoc();
            $update_view_sql = "UPDATE event_views_abelities SET view_count = view_count + 1, last_viewed = NOW() WHERE id = ?";
            $update_stmt = $conn->prepare($update_view_sql);
            $update_stmt->bind_param("i", $view_row['id']);
            $update_stmt->execute();
            $update_stmt->close();
        } else {
            $insert_view_sql = "INSERT INTO event_views_abelities (student_id, event_id, event_type, view_count, last_viewed) VALUES (?, ?, ?, 1, NOW())";
            $insert_stmt = $conn->prepare($insert_view_sql);
            $insert_stmt->bind_param("iis", $track_student_id, $event_id, $current_category);
            $insert_stmt->execute();
            $insert_stmt->close();
        }
        $check_view_stmt->close();
    }
    $track_stmt->close();
}
// --- END TRACKING ALGORITHM ---

// Get current registration count
$registration_sql = "SELECT COUNT(*) as reg_count FROM event_registrations_abelities WHERE event_id = ?";
$reg_stmt = $conn->prepare($registration_sql);
$reg_stmt->bind_param("i", $event_id);
$reg_stmt->execute();
$reg_result = $reg_stmt->get_result();
$registration_count = $reg_result->fetch_assoc()['reg_count'];
$reg_stmt->close();

// Check if student is already registered
$is_registered = false;
$student_table_id = null;

if ($user_type == 'student') {
    $student_sql = "SELECT id FROM students_abelities WHERE user_id = ?";
    $student_stmt = $conn->prepare($student_sql);
    $student_stmt->bind_param("i", $user_id);
    $student_stmt->execute();
    $student_result = $student_stmt->get_result();
    
    if ($student_result->num_rows === 1) {
        $student_data = $student_result->fetch_assoc();
        $student_table_id = $student_data['id'];
        
        $check_sql = "SELECT id FROM event_registrations_abelities WHERE event_id = ? AND student_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ii", $event_id, $student_table_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $is_registered = $check_result->num_rows > 0;
        $check_stmt->close();
    }
    $student_stmt->close();
}

// Calculate available spots
$available_spots = $event['participant_limit'] ? $event['participant_limit'] - $registration_count : null;
$is_full = $event['participant_limit'] && $available_spots <= 0;

// Handle registration
$registration_success = null;
$registration_error = null;
$notification_message = null;

if ($_SERVER['REQUEST_METHOD'] == "POST" && isset($_POST['register_event']) && $user_type == 'student') {
    // Get student ID (if not already retrieved)
    if (!$student_table_id) {
        $student_sql = "SELECT id FROM students_abelities WHERE user_id = ?";
        $student_stmt = $conn->prepare($student_sql);
        $student_stmt->bind_param("i", $user_id);
        $student_stmt->execute();
        $student_result = $student_stmt->get_result();
        
        if ($student_result->num_rows === 1) {
            $student_data = $student_result->fetch_assoc();
            $student_table_id = $student_data['id'];
        }
        $student_stmt->close();
    }
    
    if ($student_table_id) {
        // Double check registration status
        $check_sql = "SELECT id FROM event_registrations_abelities WHERE event_id = ? AND student_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ii", $event_id, $student_table_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $is_registered = $check_result->num_rows > 0;
        $check_stmt->close();
        
        // NOTE: removed strict PHP time check here because server time is wrong.
        // We rely on the Frontend (JS) to hide the button.
        
        if (!$is_registered && !$is_full) {
            // Insert registration
            $register_sql = "INSERT INTO event_registrations_abelities (event_id, student_id) VALUES (?, ?)";
            $register_stmt = $conn->prepare($register_sql);
            $register_stmt->bind_param("ii", $event_id, $student_table_id);

            if ($register_stmt->execute()) {
                $registration_success = "Successfully registered for the event!";
                $is_registered = true;
                $registration_count++;
                $available_spots = $event['participant_limit'] ? $event['participant_limit'] - $registration_count : null;
                $is_full = $event['participant_limit'] && $available_spots <= 0;
                
                if (isset($_POST['receive_notifications']) && $_POST['receive_notifications'] == '1') {
                    $notification_message = "You will receive email notifications about this event.";
                }
            } else {
                $registration_error = "Error registering for event: " . $register_stmt->error;
            }
            $register_stmt->close();
        } elseif ($is_registered) {
            $registration_error = "You are already registered for this event.";
        } elseif ($is_full) {
            $registration_error = "This event is full. No more spots available.";
        }
    } else {
        $registration_error = "Error: Student profile not found.";
    }
}

include 'header.php';
?>

<input type="hidden" id="event-datetime-iso" value="<?php echo date('Y-m-d\TH:i:s', strtotime($event['event_date'] . ' ' . $event['event_time'])); ?>">

<style>
    /* Utility classes for UKM Colors */
    .text-ukm-blue { color: var(--ukm-blue); }
    .bg-ukm-blue { background-color: var(--ukm-blue); }
    .hover-bg-ukm-blue:hover { background-color: #003878; }
    
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
</style>

<div class="max-w-7xl mx-auto px-4 py-8 min-h-screen">
    
    <div class="mb-6">
        <a href="view_events.php" class="inline-flex items-center text-gray-500 hover:text-ukm-blue font-medium transition-colors">
            <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Back to Event Feed
        </a>
    </div>

    <?php if (isset($registration_error)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded shadow-sm" role="alert">
            <p class="font-bold">Error</p>
            <p><?php echo $registration_error; ?></p>
        </div>
    <?php endif; ?>

    <?php if (isset($registration_success)): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded shadow-sm" role="alert">
            <p class="font-bold">Success!</p>
            <p><?php echo $registration_success; ?></p>
            <?php if (isset($notification_message)): ?>
                <p class="text-sm mt-1"><?php echo $notification_message; ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
        <div class="grid grid-cols-1 lg:grid-cols-3">
            
            <div class="lg:col-span-1 bg-gray-50 p-6 border-b lg:border-b-0 lg:border-r border-gray-200">
                <div class="sticky top-24">
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-4">Event Poster</h3>
                    <?php if (!empty($event['poster_path'])): ?>
                        <div class="rounded-lg overflow-hidden shadow-sm border border-gray-200 bg-white">
                            <img src="<?php echo $event['poster_path']; ?>" 
                                 alt="<?php echo htmlspecialchars($event['title']); ?>"
                                 class="w-full h-auto object-contain">
                        </div>
                    <?php else: ?>
                        <div class="w-full aspect-[3/4] bg-gradient-to-br from-blue-500 to-red-600 rounded-lg flex flex-col items-center justify-center text-white p-6 text-center shadow-md">
                            <div class="text-6xl mb-4 opacity-80">🎉</div>
                            <p class="text-lg font-bold leading-tight"><?php echo htmlspecialchars($event['title']); ?></p>
                            <p class="text-xs opacity-75 mt-2 uppercase tracking-wide">No Poster Available</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="lg:col-span-2 p-8">
                
                <div class="mb-8">
                    <div class="flex flex-wrap items-center gap-2 mb-3">
                        <span class="category-badge category-<?php echo $event['category']; ?>">
                            <?php echo ucfirst($event['category']); ?>
                        </span>
                        
                        <span id="dynamic-status-badge">
                            <?php if($is_full): ?>
                                <span class="bg-red-100 text-red-800 text-xs font-bold px-2.5 py-0.5 rounded border border-red-200">Full Capacity</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    
                    <h1 class="text-3xl md:text-4xl font-bold text-gray-900 mb-2 leading-tight"><?php echo htmlspecialchars($event['title']); ?></h1>
                    <div class="flex items-center text-gray-600 text-lg">
                        <span>Organized by</span>
                        <span class="font-semibold ml-1 text-ukm-blue"><?php echo htmlspecialchars($event['club_name']); ?></span>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6 mb-10">
                    <div class="flex items-start">
                        <div class="w-10 h-10 rounded-full bg-blue-50 flex items-center justify-center mr-4 flex-shrink-0 text-ukm-blue">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-gray-900 uppercase tracking-wide">Date & Time</p>
                            <p class="text-gray-700 mt-1"><?php echo date('F j, Y', strtotime($event['event_date'])); ?></p>
                            <p class="text-gray-500 text-sm"><?php echo date('g:i A', strtotime($event['event_time'])); ?></p>
                        </div>
                    </div>

                    <div class="flex items-start">
                        <div class="w-10 h-10 rounded-full bg-blue-50 flex items-center justify-center mr-4 flex-shrink-0 text-ukm-blue">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-gray-900 uppercase tracking-wide">Location</p>
                            <p class="text-gray-700 mt-1"><?php echo htmlspecialchars($event['location']); ?></p>
                        </div>
                    </div>

                    <div class="flex items-start">
                        <div class="w-10 h-10 rounded-full bg-blue-50 flex items-center justify-center mr-4 flex-shrink-0 text-ukm-blue">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                        </div>
                        <div class="w-full">
                            <p class="text-sm font-bold text-gray-900 uppercase tracking-wide">Participation</p>
                            <div class="flex items-center justify-between mt-1">
                                <span class="text-gray-700">
                                    <?php echo $registration_count; ?> 
                                    <?php if ($event['participant_limit']): ?>
                                        / <?php echo $event['participant_limit']; ?> registered
                                    <?php else: ?>
                                        registered
                                    <?php endif; ?>
                                </span>
                            </div>
                            <?php if ($event['participant_limit']): ?>
                                <div class="w-full bg-gray-200 rounded-full h-1.5 mt-2">
                                    <div class="<?php echo $is_full ? 'bg-red-500' : 'bg-ukm-blue'; ?> h-1.5 rounded-full transition-all duration-500" 
                                         style="width: <?php echo min(($registration_count / $event['participant_limit']) * 100, 100); ?>%">
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="flex items-start">
                        <div class="w-10 h-10 rounded-full bg-blue-50 flex items-center justify-center mr-4 flex-shrink-0 text-ukm-blue">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" /></svg>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-gray-900 uppercase tracking-wide">Contact</p>
                            <?php if ($user_type == 'student'): ?>
                                <a href="contact_organizer.php?event_id=<?php echo $event_id; ?>" 
                                   class="text-ukm-blue hover:underline font-medium mt-1 inline-block">
                                    Message Organizer
                                </a>
                            <?php else: ?>
                                <p class="text-gray-500 mt-1 italic">Messaging available for students</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <hr class="border-gray-200 my-8">

                <div class="prose max-w-none text-gray-700">
                    <div class="mb-8">
                        <h2 class="text-xl font-bold text-gray-900 mb-4">About This Event</h2>
                        <div class="leading-relaxed whitespace-pre-line">
                            <?php echo htmlspecialchars($event['description']); ?>
                        </div>
                    </div>

                    <div class="mb-8">
                        <h2 class="text-xl font-bold text-gray-900 mb-4">Terms & Conditions</h2>
                        <div class="bg-gray-50 p-4 rounded-lg border border-gray-100 text-sm leading-relaxed whitespace-pre-line">
                            <?php echo htmlspecialchars($event['terms_conditions']); ?>
                        </div>
                    </div>
                </div>

                <?php if ($user_type == 'student'): ?>
                    <div class="mt-8 pt-8 border-t border-gray-200">
                        <h3 class="text-lg font-bold text-gray-900 mb-4">Registration</h3>

                        <?php if ($is_registered): ?>
                            <div class="bg-green-50 border border-green-200 rounded-xl p-6 text-center">
                                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                    <svg class="w-6 h-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                </div>
                                <h4 class="text-lg font-bold text-green-800">You are registered!</h4>
                                <p class="text-green-700 mt-1 mb-4">You're all set for this event.</p>
                                <a href="event_registered.php" class="inline-block bg-white text-green-700 border border-green-300 font-semibold py-2 px-6 rounded-lg hover:bg-green-50 transition-colors">
                                    View My Events
                                </a>
                            </div>

                        <?php elseif ($is_full): ?>
                            <div class="bg-gray-100 border border-gray-200 rounded-xl p-6 text-center opacity-75">
                                <h4 class="text-lg font-bold text-gray-600">Event Fully Booked</h4>
                                <p class="text-gray-500 mt-1">Sorry, there are no more spots available for this event.</p>
                            </div>

                        <?php else: ?>
                            
                            <div id="registration-panel">
                                <form method="POST" action="" class="bg-blue-50 border border-blue-100 rounded-xl p-6">
                                    <div class="mb-5">
                                        <label class="flex items-start cursor-pointer select-none">
                                            <div class="flex items-center h-5">
                                                <input type="checkbox" name="receive_notifications" value="1" class="w-4 h-4 text-ukm-blue rounded focus:ring-blue-500" checked>
                                            </div>
                                            <div class="ml-3 text-sm">
                                                <span class="font-medium text-gray-800">Email Notifications</span>
                                                <p class="text-gray-500">Receive updates and reminders about this event via email.</p>
                                            </div>
                                        </label>
                                    </div>

                                    <button type="submit" name="register_event" class="w-full bg-ukm-blue hover-bg-ukm-blue text-white font-bold py-3 px-6 rounded-lg shadow-md hover:shadow-lg transition-all transform hover:-translate-y-0.5">
                                        Register Now
                                    </button>
                                    <p class="text-xs text-center text-gray-500 mt-3">By registering, you agree to the Terms & Conditions above.</p>
                                </form>
                            </div>

                            <div id="time-expired-panel" style="display: none;" class="bg-slate-100 border border-slate-200 rounded-xl p-6 text-center">
                                <div class="w-12 h-12 bg-slate-200 rounded-full flex items-center justify-center mx-auto mb-3">
                                    <svg class="w-6 h-6 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <h4 class="text-lg font-bold text-slate-700">Registration Closed</h4>
                                <p class="text-slate-500 mt-1">The registration deadline for this event has passed.</p>
                            </div>

                        <?php endif; ?>
                    </div>
                <?php elseif ($user_type == 'club' || $user_type == 'hep'): ?>
                    <div class="mt-8 pt-8 border-t border-gray-200">
                        <div class="bg-gray-50 rounded-lg p-4 text-center border border-gray-200">
                            <p class="text-gray-500 font-medium">Administrative View Only</p>
                            <p class="text-xs text-gray-400 mt-1"><?php echo ucfirst($user_type); ?> accounts cannot register for events.</p>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        // 1. Get Event Time from Hidden Input
        const eventDateInput = document.getElementById("event-datetime-iso");
        if (!eventDateInput) return;
        
        const eventDateStr = eventDateInput.value;
        if (!eventDateStr) return;

        const eventTime = new Date(eventDateStr); // JS parses ISO string using device timezone
        const userTime = new Date(); // User's device current time

        // 2. Compare Time
        if (userTime > eventTime) {
            // Event has passed!
            
            // Toggle Panels
            const regPanel = document.getElementById("registration-panel");
            const expiredPanel = document.getElementById("time-expired-panel");
            
            if (regPanel) regPanel.style.display = "none";
            if (expiredPanel) expiredPanel.style.display = "block";

            // Add Badge to Header if not present
            const badgeContainer = document.getElementById("dynamic-status-badge");
            if (badgeContainer) {
                // Prepend or Append closed badge
                badgeContainer.innerHTML += '<span class="bg-slate-200 text-slate-700 text-xs font-bold px-2.5 py-0.5 rounded border border-slate-300 ml-1">Registration Closed</span>';
            }
        }
    });

    // Modal Logic
    function closeModal() {
        const modal = document.getElementById('registrationModal');
        if (modal) {
            modal.style.display = 'none';
        }
    }

    document.addEventListener('click', function(e) {
        const modal = document.getElementById('registrationModal');
        if (modal && e.target === modal) {
            closeModal();
        }
    });

    <?php if (isset($registration_success)): ?>
        setTimeout(() => {
            closeModal();
        }, 5000);
    <?php endif; ?>
</script>

<?php 
$conn->close();
include 'footer.php'; 
?>