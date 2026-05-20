<?php
include 'config.php';

// Check if user is logged in and is a club
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'club') {
    header("Location: index.php");
    exit();
}

// Check if event ID is provided
if (!isset($_GET['event_id']) || empty($_GET['event_id'])) {
    header("Location: club_events.php");
    exit();
}

$event_id = intval($_GET['event_id']);
$user_id = $_SESSION['user_id'];

// Verify that the event belongs to this club
$event_sql = "SELECT e.*, c.club_name 
              FROM events_abelities e 
              JOIN clubs_abelities c ON e.club_id = c.id 
              WHERE e.id = ? AND c.user_id = ?";
$event_stmt = $conn->prepare($event_sql);
$event_stmt->bind_param("ii", $event_id, $user_id);
$event_stmt->execute();
$event_result = $event_stmt->get_result();

if ($event_result->num_rows == 0) {
    header("Location: club_events.php");
    exit();
}

$event = $event_result->fetch_assoc();
$event_stmt->close();

// Get unique students who sent messages for this event
$students_sql = "SELECT DISTINCT s.id, s.full_name, s.matric_number,
                        (SELECT COUNT(*) FROM organizer_messages_abelities om 
                         WHERE om.student_id = s.id AND om.event_id = ?) as message_count,
                        (SELECT MAX(created_at) FROM organizer_messages_abelities om 
                         WHERE om.student_id = s.id AND om.event_id = ?) as last_message_time,
                        (SELECT COUNT(*) FROM organizer_messages_abelities om 
                         WHERE om.student_id = s.id AND om.event_id = ? AND om.is_read = FALSE AND om.sender_type = 'student') as unread_count
                 FROM students_abelities s
                 JOIN organizer_messages_abelities om ON s.id = om.student_id
                 WHERE om.event_id = ?
                 ORDER BY last_message_time DESC";
$students_stmt = $conn->prepare($students_sql);
$students_stmt->bind_param("iiii", $event_id, $event_id, $event_id, $event_id);
$students_stmt->execute();
$students_result = $students_stmt->get_result();
$students = [];
while ($row = $students_result->fetch_assoc()) {
    $students[] = $row;
}
$students_stmt->close();

// Get messages for a specific student if selected
$selected_student = null;
$conversation = [];
$selected_student_id = null;

if (isset($_GET['student_id']) && !empty($_GET['student_id'])) {
    $selected_student_id = intval($_GET['student_id']);
    
    // Verify the student has messages for this event
    $student_verify_sql = "SELECT s.id, s.full_name, s.matric_number 
                           FROM students_abelities s
                           JOIN organizer_messages_abelities om ON s.id = om.student_id
                           WHERE s.id = ? AND om.event_id = ? LIMIT 1";
    $student_verify_stmt = $conn->prepare($student_verify_sql);
    $student_verify_stmt->bind_param("ii", $selected_student_id, $event_id);
    $student_verify_stmt->execute();
    $student_verify_result = $student_verify_stmt->get_result();
    
    if ($student_verify_result->num_rows > 0) {
        $selected_student = $student_verify_result->fetch_assoc();
        
        // Get all messages for this student and event (both student and organizer messages)
        $conversation_sql = "SELECT om.*, 
                                    CASE 
                                        WHEN om.sender_type = 'student' THEN s.full_name 
                                        WHEN om.sender_type = 'organizer' THEN 'You'
                                    END as sender_name,
                                    om.sender_type
                             FROM organizer_messages_abelities om 
                             LEFT JOIN students_abelities s ON om.student_id = s.id 
                             WHERE om.event_id = ? AND om.student_id = ?
                             ORDER BY om.created_at ASC";
        $conversation_stmt = $conn->prepare($conversation_sql);
        $conversation_stmt->bind_param("ii", $event_id, $selected_student_id);
        $conversation_stmt->execute();
        $conversation_result = $conversation_stmt->get_result();
        while ($row = $conversation_result->fetch_assoc()) {
            $conversation[] = $row;
        }
        $conversation_stmt->close();

        // Mark all student messages as read when organizer views the conversation
        $mark_read_sql = "UPDATE organizer_messages_abelities 
                          SET is_read = TRUE 
                          WHERE event_id = ? AND student_id = ? AND sender_type = 'student' AND is_read = FALSE";
        $mark_read_stmt = $conn->prepare($mark_read_sql);
        $mark_read_stmt->bind_param("ii", $event_id, $selected_student_id);
        $mark_read_stmt->execute();
        $mark_read_stmt->close();
    }
    $student_verify_stmt->close();
}

// Handle sending response
if ($_SERVER['REQUEST_METHOD'] == "POST" && isset($_POST['send_response']) && isset($selected_student)) {
    $response_message = trim($_POST['response_message']);
    
    if (!empty($response_message)) {
        // Get club table ID first
        $club_id_sql = "SELECT id FROM clubs_abelities WHERE user_id = ?";
        $club_id_stmt = $conn->prepare($club_id_sql);
        $club_id_stmt->bind_param("i", $user_id);
        $club_id_stmt->execute();
        $club_id_result = $club_id_stmt->get_result();
        
        if ($club_id_result->num_rows === 1) {
            $club_data = $club_id_result->fetch_assoc();
            $club_table_id = $club_data['id'];
            
            // Insert organizer response with sender_type and organizer_id
            $insert_sql = "INSERT INTO organizer_messages_abelities (event_id, student_id, message, sender_type, organizer_id, created_at) 
                           VALUES (?, ?, ?, 'organizer', ?, NOW())";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("iisi", $event_id, $selected_student_id, $response_message, $club_table_id);
            
            if ($insert_stmt->execute()) {
                // Refresh the page to show the new message
                header("Location: view_event_messages.php?event_id=" . $event_id . "&student_id=" . $selected_student_id);
                exit();
            } else {
                $error = "Failed to send response: " . $insert_stmt->error;
            }
            $insert_stmt->close();
        } else {
            $error = "Club profile not found.";
        }
        $club_id_stmt->close();
    } else {
        $error = "Please enter a response message.";
    }
}

// --- INCLUDE HEADER ---
include 'header.php'; 
?>

<style>
    /* Utility classes matching Header theme */
    .text-ukm-blue { color: var(--ukm-blue); }
    .bg-ukm-blue { background-color: var(--ukm-blue); }
    .bg-ukm-red { background-color: var(--ukm-red); }
    
    .chat-layout {
        height: calc(100vh - 200px); /* Adjust height to fit between header/footer */
        min-height: 600px;
    }

    .message-bubble {
        max-width: 75%;
        padding: 12px 16px;
        border-radius: 12px;
        margin-bottom: 12px;
        word-wrap: break-word;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    }
    
    /* Student Message (Incoming) -> Gray/White */
    .message-student {
        background: #f3f4f6;
        color: #1f2937;
        margin-right: auto;
        border: 1px solid #e5e7eb;
        border-bottom-left-radius: 2px;
    }
    
    /* Organizer Message (Outgoing) -> UKM Blue */
    .message-organizer {
        background: var(--ukm-blue);
        color: white;
        margin-left: auto;
        border-bottom-right-radius: 2px;
    }
    
    .chat-messages {
        flex: 1;
        overflow-y: auto;
        padding: 24px;
        background: #ffffff;
    }
    
    .event-header {
        background: white;
        border-bottom: 1px solid #e5e7eb;
        padding: 16px 24px;
    }
    
    .student-item {
        transition: all 0.2s ease;
        cursor: pointer;
        border-left: 3px solid transparent;
    }
    
    .student-item:hover {
        background-color: #f9fafb;
    }
    
    .student-item.active {
        background-color: #f0f7ff;
        border-left-color: var(--ukm-blue);
    }
    
    .unread-badge {
        background-color: var(--ukm-red);
        color: white;
        border-radius: 9999px;
        padding: 0.1rem 0.4rem;
        font-size: 0.7rem;
        font-weight: 700;
        margin-left: 0.5rem;
    }
    
    .student-list-container {
        overflow-y: auto;
        height: 100%;
    }
    
    /* Scrollbar styling */
    .chat-messages::-webkit-scrollbar,
    .student-list-container::-webkit-scrollbar {
        width: 6px;
    }
    
    .chat-messages::-webkit-scrollbar-track,
    .student-list-container::-webkit-scrollbar-track {
        background: #f1f1f1;
    }
    
    .chat-messages::-webkit-scrollbar-thumb,
    .student-list-container::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 10px;
    }
</style>

<div class="max-w-7xl mx-auto px-4 py-6 min-h-screen">
    
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
                <span class="text-gray-400 font-normal">Messages:</span> 
                <?php echo htmlspecialchars($event['title']); ?>
            </h1>
            <div class="flex items-center text-sm text-gray-500 mt-1">
                <span><?php echo date('d M Y', strtotime($event['event_date'])); ?></span>
                <span class="mx-2">•</span>
                <span><?php echo htmlspecialchars($event['club_name']); ?></span>
            </div>
        </div>
        <a href="club_events.php" class="inline-flex items-center text-gray-600 hover:text-ukm-blue font-medium transition-colors text-sm">
            <svg class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Back to My Events
        </a>
    </div>

    <?php if (isset($error)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded shadow-sm" role="alert">
            <p class="font-bold">Error</p>
            <p><?php echo $error; ?></p>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden flex chat-layout">
        
        <div class="w-1/3 border-r border-gray-200 flex flex-col bg-gray-50">
            <div class="p-4 border-b border-gray-200 bg-white">
                <h3 class="font-bold text-gray-800 flex justify-between items-center">
                    Inbox
                    <span class="bg-blue-100 text-ukm-blue text-xs px-2 py-1 rounded-full"><?php echo count($students); ?> Students</span>
                </h3>
            </div>
            
            <div class="student-list-container">
                <?php if (empty($students)): ?>
                    <div class="flex flex-col items-center justify-center h-full text-gray-400 p-4 text-center">
                        <svg class="w-12 h-12 mb-2 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" /></svg>
                        <p class="text-sm">No messages yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($students as $student): ?>
                        <a href="view_event_messages.php?event_id=<?php echo $event_id; ?>&student_id=<?php echo $student['id']; ?>"
                           class="student-item block p-4 border-b border-gray-100 <?php echo ($selected_student && $selected_student['id'] == $student['id']) ? 'active' : ''; ?>">
                            <div class="flex justify-between items-start">
                                <div class="truncate pr-2">
                                    <h4 class="font-bold text-gray-800 text-sm truncate"><?php echo htmlspecialchars($student['full_name']); ?></h4>
                                    <p class="text-xs text-gray-500"><?php echo htmlspecialchars($student['matric_number']); ?></p>
                                </div>
                                <?php if ($student['unread_count'] > 0 && !($selected_student && $selected_student['id'] == $student['id'])): ?>
                                    <span class="unread-badge"><?php echo $student['unread_count']; ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="flex justify-between items-center mt-2">
                                <p class="text-xs text-gray-400">
                                    <?php echo date('M j', strtotime($student['last_message_time'])); ?>
                                </p>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="w-2/3 flex flex-col bg-white">
            <?php if ($selected_student): ?>
                <div class="p-4 border-b border-gray-200 bg-white flex justify-between items-center shadow-sm z-10">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center text-ukm-blue font-bold">
                            <?php echo strtoupper(substr($selected_student['full_name'], 0, 1)); ?>
                        </div>
                        <div>
                            <h3 class="font-bold text-gray-900"><?php echo htmlspecialchars($selected_student['full_name']); ?></h3>
                            <p class="text-xs text-gray-500"><?php echo htmlspecialchars($selected_student['matric_number']); ?></p>
                        </div>
                    </div>
                </div>

                <div class="chat-messages" id="chatMessages">
                    <?php if (empty($conversation)): ?>
                        <div class="flex flex-col items-center justify-center h-full text-gray-400">
                            <p class="text-sm">Start the conversation</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($conversation as $message): ?>
                            <div class="flex flex-col <?php echo $message['sender_type'] == 'student' ? 'items-start' : 'items-end'; ?> mb-4">
                                <div class="message-bubble <?php echo $message['sender_type'] == 'student' ? 'message-student' : 'message-organizer'; ?>">
                                    <p class="text-sm leading-relaxed whitespace-pre-wrap"><?php echo htmlspecialchars($message['message']); ?></p>
                                </div>
                                <div class="flex items-center gap-1 text-xs text-gray-400 px-1">
                                    <span><?php echo date('M j, g:i A', strtotime($message['created_at'])); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="p-4 border-t border-gray-200 bg-gray-50">
                    <form method="POST" action="" class="flex gap-3">
                        <input type="text" name="response_message" 
                               placeholder="Type your reply..." 
                               class="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm transition-shadow" 
                               required
                               autocomplete="off">
                        
                        <button type="submit" name="send_response" 
                                class="bg-ukm-blue hover:bg-blue-800 text-white px-6 py-3 rounded-lg font-medium flex items-center gap-2 transition-all shadow-sm hover:shadow-md">
                            <span>Send</span>
                            <svg class="w-4 h-4 transform rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                            </svg>
                        </button>
                    </form>
                </div>

            <?php else: ?>
                <div class="flex-1 flex flex-col items-center justify-center bg-gray-50 text-gray-500">
                    <div class="w-16 h-16 bg-white rounded-full flex items-center justify-center shadow-sm mb-4">
                        <svg class="w-8 h-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold text-gray-800 mb-1">Select a Conversation</h3>
                    <p class="text-sm">Choose a student from the sidebar to view messages.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    // Auto-scroll to bottom of chat
    function scrollToBottom() {
        const chatMessages = document.getElementById('chatMessages');
        if (chatMessages) {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
    }

    // Scroll to bottom when page loads
    document.addEventListener('DOMContentLoaded', scrollToBottom);
    
    // Auto-scroll when conversation changes
    <?php if ($selected_student): ?>
        setTimeout(scrollToBottom, 100);
    <?php endif; ?>

    // Refresh unread counts when tab becomes visible
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden && !window.location.href.includes('student_id=')) {
            window.location.reload();
        }
    });
</script>

<?php 
// --- INCLUDE FOOTER ---
include 'footer.php'; 
?>
<?php $conn->close(); ?>