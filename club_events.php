<?php
include 'config.php';

// Check if user is logged in and is a club
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'club') {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$club_name = $_SESSION['club_name'];

// Get club ID from club_abelities table
$club_sql = "SELECT id FROM clubs_abelities WHERE user_id = ?";
$club_stmt = $conn->prepare($club_sql);
$club_stmt->bind_param("i", $user_id);
$club_stmt->execute();
$club_result = $club_stmt->get_result();

if ($club_result->num_rows === 0) {
    die("Error: Club profile not found!");
}

$club_data = $club_result->fetch_assoc();
$club_table_id = $club_data['id'];
$club_stmt->close();

// Get events created by this club with unread message counts
// UPDATED SORT: ORDER BY e.created_at DESC (Latest Created First)
$sql = "SELECT e.*, 
           (SELECT COUNT(*) FROM event_registrations_abelities er WHERE er.event_id = e.id) AS registered_count,
           (SELECT COUNT(DISTINCT student_id) FROM organizer_messages_abelities om 
            WHERE om.event_id = e.id AND om.is_read = FALSE AND om.sender_type = 'student') AS unread_student_count
        FROM events_abelities e
        WHERE e.club_id = ?
        ORDER BY e.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $club_table_id);
$stmt->execute();
$result = $stmt->get_result();

$active_events = [];
$ended_events = [];

// Initial Server-Side Sort (Fallback)
if ($result->num_rows > 0) {
 while($row = $result->fetch_assoc()) {
        // We use server time for initial bucket, but JS will fix it visually
        if ($row['event_status'] === 'finished' || $row['event_date'] < date('Y-m-d')) {
             $ended_events[] = $row;
        } else {
             $active_events[] = $row;
        }
    }
}
$stmt->close();

// --- INCLUDE HEADER ---
include 'header.php';
?>

<style>
    /* Utility classes for UKM Colors */
    .text-ukm-blue { color: var(--ukm-blue); }
    .bg-ukm-blue { background-color: var(--ukm-blue); }
    .hover-bg-ukm-blue:hover { background-color: #003878; }

    .message-badge {
        background-color: #ef4444;
        color: white;
        border-radius: 9999px;
        padding: 0.25rem 0.5rem;
        font-size: 0.7rem;
        font-weight: 700;
        margin-left: 0.5rem;
        box-shadow: 0 1px 2px rgba(0,0,0,0.1);
    }
    
    .filter-btn {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .filter-btn.active-filter {
        background-color: var(--ukm-blue);
        color: white;
        border-color: var(--ukm-blue);
        transform: translateY(-2px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    }

    /* --- ANIMATIONS --- */
    .event-card {
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        border: 1px solid transparent;
        transform: scale(1);
    }
    .event-card:hover {
        transform: scale(1.02) translateY(-5px);
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        border-color: rgba(0, 74, 152, 0.2);
        z-index: 10;
    }
    @keyframes fadeInUp { from { opacity: 0; transform: translate3d(0, 20px, 0); } to { opacity: 1; transform: translate3d(0, 0, 0); } }
    .animate-fade-in-up { animation: fadeInUp 0.5s ease-out forwards; }
    .card-btn { transition: transform 0.2s ease, background-color 0.2s ease; }
    .card-btn:hover { transform: translateY(-2px); }
</style>

<div class="max-w-7xl mx-auto px-4 py-8 min-h-screen">
    
    <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 border-b border-gray-200 pb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Manage My Events</h1>
            <p class="text-gray-600 mt-2">View and manage events posted by <span class="font-medium text-ukm-blue"><?php echo htmlspecialchars($club_name); ?></span></p>
        </div>
        <div class="mt-4 md:mt-0">
            <a href="create_event.php" class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg shadow-sm transition-all hover:-translate-y-1 hover:shadow-md">
                <svg class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Create New Event
            </a>
        </div>
    </div>

    <div class="flex flex-wrap justify-end gap-2 mb-8">
        <button onclick="filterEvents('active', this)" class="filter-btn active-filter px-4 py-2 rounded-lg text-sm font-medium border border-transparent bg-gray-200 text-gray-700 hover:bg-gray-300">Show Active</button>
        <button onclick="filterEvents('ended', this)" class="filter-btn px-4 py-2 rounded-lg text-sm font-medium border border-transparent bg-gray-200 text-gray-700 hover:bg-gray-300">Show Ended</button>
        <button onclick="filterEvents('all', this)" class="filter-btn px-4 py-2 rounded-lg text-sm font-medium border border-transparent bg-gray-200 text-gray-700 hover:bg-gray-300">Show All</button>
    </div>

    <div id="activeSection" class="animate-fade-in-up">
        <h2 class="text-xl font-bold text-gray-800 mb-6 flex items-center">
            <span class="bg-green-100 text-green-800 text-sm font-bold px-3 py-1 rounded-full mr-3 shadow-sm">Live</span>
            Active Events
        </h2>
        
        <div id="active-events-grid" class="grid gap-6">
            <?php foreach ($active_events as $event): ?>
                <?php 
                    $capacity = isset($event['participant_limit']) ? $event['participant_limit'] : NULL;
                    $registered = isset($event['registered_count']) ? $event['registered_count'] : 0;
                    $unread_student_count = isset($event['unread_student_count']) ? $event['unread_student_count'] : 0;
                    
                    if (empty($capacity) || $capacity == 0) {
                        $progress = 100;
                        $capacity_display = "∞";
                    } else {
                        $progress = min(100, ($registered / $capacity) * 100);
                        $capacity_display = $capacity;
                    }

                    // ISO Date for JS
                    $iso_datetime = date('Y-m-d\TH:i:s', strtotime($event['event_date'] . ' ' . $event['event_time']));
                ?>
                <div class="event-card active-card-item bg-white p-6 rounded-xl relative border-l-4 border-l-ukm-blue group"
                     data-event-id="<?php echo $event['id']; ?>"
                     data-event-datetime="<?php echo $iso_datetime; ?>"
                     data-event-status="<?php echo $event['event_status']; ?>">
                     
                    <div class="flex flex-col lg:flex-row justify-between items-start gap-4 mb-4">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="category-badge category-<?php echo htmlspecialchars($event['category']); ?>">
                                    <?php echo ucfirst($event['category']); ?>
                                </span>
                                <span class="status-indicator-badge"></span>
                            </div>
                            <h3 class="text-xl font-bold text-ukm-blue mb-2 group-hover:text-blue-700 transition-colors"><?php echo htmlspecialchars($event['title']); ?></h3>
                            <div class="flex flex-wrap text-sm text-gray-600 gap-y-2 gap-x-4">
                                <div class="flex items-center">
                                    <svg class="w-4 h-4 mr-1 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    <?php echo date('F j, Y', strtotime($event['event_date'])); ?>
                                </div>
                                <div class="flex items-center">
                                    <svg class="w-4 h-4 mr-1 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    <?php echo date('g:i A', strtotime($event['event_time'])); ?>
                                </div>
                                <div class="flex items-center">
                                    <svg class="w-4 h-4 mr-1 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                    <?php echo htmlspecialchars($event['location']); ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="w-full lg:w-64 bg-gray-50 p-4 rounded-lg border border-gray-100">
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-xs font-bold text-gray-500 uppercase">Participation</span>
                                <span class="text-sm font-semibold text-ukm-blue"><?php echo "$registered / $capacity_display"; ?></span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-ukm-blue h-2 rounded-full transition-all duration-1000 ease-out" style="width: <?php echo $progress; ?>%;"></div>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-3 mt-6 pt-4 border-t border-gray-100">
                        <a href="update_events.php?id=<?php echo $event['id']; ?>" class="card-btn flex-1 sm:flex-none text-center px-4 py-2 bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium rounded-lg">
                            ✏️ Update
                        </a>
                        <a href="get_participants.php?event_id=<?php echo $event['id']; ?>" class="card-btn flex-1 sm:flex-none text-center px-4 py-2 bg-ukm-blue hover:bg-blue-800 text-white text-sm font-medium rounded-lg">
                            👥 Participants
                        </a>
                        <a href="view_event_messages.php?event_id=<?php echo $event['id']; ?>" class="card-btn flex-1 sm:flex-none text-center px-4 py-2 bg-white border border-ukm-blue text-ukm-blue hover:bg-blue-50 text-sm font-medium rounded-lg flex items-center justify-center">
                            💬 Messages
                            <?php if ($unread_student_count > 0): ?>
                                <span class="message-badge animate-bounce"><?php echo $unread_student_count; ?></span>
                            <?php endif; ?>
                        </a>
                        <div class="flex-grow hidden sm:block"></div>
                        <a href="delete_event.php?event_id=<?= $event['id'] ?>" onclick="return confirm('Are you sure you want to delete this event? This action cannot be undone.');" class="card-btn flex-1 sm:flex-none text-center px-4 py-2 bg-red-50 text-red-600 hover:bg-red-100 border border-red-200 text-sm font-medium rounded-lg">
                            🗑️ Delete
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div id="no-active-placeholder" class="text-center py-12 bg-white rounded-xl border border-dashed border-gray-300" style="<?php echo empty($active_events) ? 'display:block' : 'display:none'; ?>">
            <p class="text-gray-500">No active events found.</p>
            <a href="create_event.php" class="text-ukm-blue font-medium hover:underline mt-2 inline-block">Post a new event</a>
        </div>
    </div>

    <div id="endedSection" class="mt-16 animate-fade-in-up" style="display: none;">
        <h2 class="text-xl font-bold text-gray-800 mb-6 flex items-center">
            <span class="bg-gray-200 text-gray-700 text-sm font-bold px-3 py-1 rounded-full mr-3 shadow-sm">History</span>
            Ended Events
        </h2>
        
        <div id="ended-events-grid" class="grid gap-6">
            <?php foreach ($ended_events as $event): ?>
                <?php 
                    $unread_student_count = isset($event['unread_student_count']) ? $event['unread_student_count'] : 0; 
                    // ISO Date for JS
                    $iso_datetime = date('Y-m-d\TH:i:s', strtotime($event['event_date'] . ' ' . $event['event_time']));
                ?>
                <div class="event-card ended-card-item bg-white p-6 rounded-xl border-l-4 border-l-gray-400 opacity-90 hover:opacity-100 transition-opacity"
                     data-event-id="<?php echo $event['id']; ?>"
                     data-event-datetime="<?php echo $iso_datetime; ?>"
                     data-event-status="<?php echo $event['event_status']; ?>">
                     
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h3 class="text-lg font-bold text-gray-700"><?php echo htmlspecialchars($event['title']); ?></h3>
                            <div class="text-sm text-gray-500 mt-1">
                                Ended on <?php echo date('F j, Y', strtotime($event['event_date'])); ?>
                            </div>
                        </div>
                        <span class="category-badge category-<?php echo htmlspecialchars($event['category']); ?>">
                            <?php echo ucfirst($event['category']); ?>
                        </span>
                    </div>

                    <div class="flex items-center justify-between mt-4 p-3 bg-gray-50 rounded-lg text-sm">
                        <span class="text-gray-600">Total Participants:</span>
                        <span class="font-bold text-gray-800"><?php echo $event['registered_count']; ?></span>
                    </div>

                    <div class="flex gap-3 mt-4 pt-4 border-t border-gray-100">
                        <a href="get_participants.php?event_id=<?php echo $event['id']; ?>" class="card-btn text-ukm-blue hover:underline text-sm font-medium">View Participants</a>
                        <span class="text-gray-300">|</span>
                        <a href="view_event_messages.php?event_id=<?php echo $event['id']; ?>" class="card-btn text-ukm-blue hover:underline text-sm font-medium flex items-center">
                            Messages
                            <?php if ($unread_student_count > 0): ?>
                                <span class="ml-2 bg-red-500 text-white text-xs font-bold px-1.5 py-0.5 rounded-full"><?php echo $unread_student_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div id="no-ended-placeholder" class="text-center py-8" style="<?php echo empty($ended_events) ? 'display:block' : 'display:none'; ?>">
            <p class="text-gray-500 italic">No past events found.</p>
        </div>
    </div>
</div>

<script>
    // --- 1. CLIENT-SIDE SORTING LOGIC ---
    document.addEventListener("DOMContentLoaded", function() {
        const userTime = new Date();
        const activeContainer = document.getElementById('active-events-grid');
        const endedContainer = document.getElementById('ended-events-grid');
        const noActiveMsg = document.getElementById('no-active-placeholder');
        const noEndedMsg = document.getElementById('no-ended-placeholder');

        // Select all event cards in the Active section
        // We only move FROM Active TO Ended if time passed (Safety check)
        const activeCards = document.querySelectorAll('.active-card-item');

        activeCards.forEach(card => {
            const eventDateStr = card.getAttribute('data-event-datetime');
            const eventStatus = card.getAttribute('data-event-status');

            if (eventDateStr && eventStatus !== 'finished') {
                const eventTime = new Date(eventDateStr);
                
                // If User Time > Event Time -> MOVE TO ENDED
                if (userTime > eventTime) {
                    
                    // 1. Move DOM Element
                    if (endedContainer) {
                        // Change styling to look like an "Ended" card
                        card.classList.remove('border-l-ukm-blue');
                        card.classList.add('border-l-gray-400', 'opacity-90', 'hover:opacity-100');
                        
                        // Add a badge indicating "Time Passed"
                        const statusBadge = card.querySelector('.status-indicator-badge');
                        if(statusBadge) {
                            statusBadge.innerHTML = '<span class="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded ml-2">Time Passed</span>';
                        }

                        // Move it
                        endedContainer.insertBefore(card, endedContainer.firstChild);
                    }
                }
            }
        });

        // 2. Update Placeholder Visibility after moves
        if (activeContainer && activeContainer.children.length === 0) {
            noActiveMsg.style.display = 'block';
        } else {
            noActiveMsg.style.display = 'none';
        }

        if (endedContainer && endedContainer.children.length === 0) {
            noEndedMsg.style.display = 'block';
        } else {
            noEndedMsg.style.display = 'none';
        }

        // Initialize Filter State
        const firstBtn = document.querySelector('.filter-btn');
        if(firstBtn) {
            firstBtn.classList.remove('bg-gray-200', 'text-gray-700');
            firstBtn.classList.add('active-filter', 'bg-ukm-blue', 'text-white');
        }
    });

    // --- 2. FILTER TABS LOGIC ---
    function filterEvents(type, btnElement) {
        const active = document.getElementById('activeSection');
        const ended = document.getElementById('endedSection');
        const buttons = document.querySelectorAll('.filter-btn');

        // Update Buttons
        if(btnElement) {
            buttons.forEach(btn => {
                btn.classList.remove('active-filter', 'bg-ukm-blue', 'text-white');
                btn.classList.add('bg-gray-200', 'text-gray-700');
                btn.style.transform = 'translateY(0)'; // Reset lift
                btn.style.boxShadow = 'none'; // Reset shadow
            });
            btnElement.classList.remove('bg-gray-200', 'text-gray-700');
            btnElement.classList.add('active-filter', 'bg-ukm-blue', 'text-white');
        }

        // Show/Hide Sections with Animation Reset
        if (type === 'active') {
            active.style.display = 'block';
            ended.style.display = 'none';
            resetAnimation(active);
        } else if (type === 'ended') {
            active.style.display = 'none';
            ended.style.display = 'block';
            resetAnimation(ended);
        } else {
            active.style.display = 'block';
            ended.style.display = 'block';
            resetAnimation(active);
            resetAnimation(ended);
        }
    }

    function resetAnimation(element) {
        element.style.animation = 'none';
        element.offsetHeight; /* trigger reflow */
        element.style.animation = null; 
    }
</script>

<?php if (isset($_GET['success'])): ?>
    <div id="toastMessage" class="fixed top-20 right-4 z-50 px-6 py-4 rounded-lg shadow-xl text-white font-medium transform transition-all duration-500 ease-in-out translate-y-0 opacity-100 flex items-center"
         style="background-color: <?= $_GET['success'] === 'deleted' ? '#ef4444' : '#10b981' ?>;">
        <?php if($_GET['success'] === 'deleted'): ?>
            <svg class="w-6 h-6 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
            Event deleted successfully.
        <?php else: ?>
            <svg class="w-6 h-6 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            Event updated successfully!
        <?php endif; ?>
    </div>
    <script>
        setTimeout(() => {
            const toast = document.getElementById('toastMessage');
            if (toast) {
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(-20px)';
                setTimeout(() => toast.remove(), 500);
            }
        }, 3000);
    </script>
<?php endif; ?>

<?php 
include 'footer.php'; 
?>
<?php $conn->close(); ?>