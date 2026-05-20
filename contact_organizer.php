<?php
include 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Check if user is a student
if ($_SESSION['user_type'] != 'student') {
    header("Location: view_events.php");
    exit();
}

// Check if event ID is provided
if (!isset($_GET['event_id']) || empty($_GET['event_id'])) {
    header("Location: view_events.php");
    exit();
}

$event_id = intval($_GET['event_id']);
$user_id = $_SESSION['user_id'];

// Get event details with club information
$sql = "SELECT e.*, c.club_name, c.id as club_table_id, u.email as organizer_email
        FROM events_abelities e
        JOIN clubs_abelities c ON e.club_id = c.id
        JOIN users_abelities u ON c.user_id = u.id
        WHERE e.id = ?";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Error preparing statement: " . $conn->error);
}

$stmt->bind_param("i", $event_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: view_events.php");
    exit();
}

$event = $result->fetch_assoc();
$stmt->close();

// Get student information
$student_sql = "SELECT full_name FROM students_abelities WHERE user_id = ?";
$student_stmt = $conn->prepare($student_sql);

if (!$student_stmt) {
    die("Error preparing student statement: " . $conn->error);
}

$student_stmt->bind_param("i", $user_id);
$student_stmt->execute();
$student_result = $student_stmt->get_result();
$student = $student_result->fetch_assoc();
$student_stmt->close();

// Handle sending message
$message_sent = false;
$error = null;

if ($_SERVER['REQUEST_METHOD'] == "POST" && isset($_POST['send_message'])) {
    $message = trim($_POST['message']);
    
    if (empty($message)) {
        $error = "Please enter a message.";
    } else {
        // Get student table ID first
        $student_id_sql = "SELECT id FROM students_abelities WHERE user_id = ?";
        $student_id_stmt = $conn->prepare($student_id_sql);
        
        if (!$student_id_stmt) {
            $error = "Error preparing student ID statement: " . $conn->error;
        } else {
            $student_id_stmt->bind_param("i", $user_id);
            $student_id_stmt->execute();
            $student_id_result = $student_id_stmt->get_result();
            
            if ($student_id_result->num_rows === 1) {
                $student_data = $student_id_result->fetch_assoc();
                $student_table_id = $student_data['id'];
                
                // Insert message into database with sender_type as 'student' and is_read as FALSE
                $insert_sql = "INSERT INTO organizer_messages_abelities (event_id, student_id, message, sender_type, is_read, created_at) VALUES (?, ?, ?, 'student', FALSE, NOW())";
                $insert_stmt = $conn->prepare($insert_sql);
                
                if (!$insert_stmt) {
                    $error = "Error preparing insert statement: " . $conn->error;
                } else {
                    $insert_stmt->bind_param("iis", $event_id, $student_table_id, $message);
                    
                    if ($insert_stmt->execute()) {
                        $message_sent = true;
                        // Refresh the page to show the new message
                        header("Location: contact_organizer.php?event_id=" . $event_id);
                        exit();
                    } else {
                        $error = "Failed to send message: " . $insert_stmt->error;
                    }
                    $insert_stmt->close();
                }
            } else {
                $error = "Student profile not found.";
            }
            $student_id_stmt->close();
        }
    }
}

// Get message history - Include sender_type and club name for organizer messages
$messages_sql = "SELECT om.*, 
                        s.full_name as student_name,
                        c.club_name as organizer_name,
                        om.sender_type
                 FROM organizer_messages_abelities om 
                 JOIN students_abelities s ON om.student_id = s.id 
                 LEFT JOIN clubs_abelities c ON om.organizer_id = c.id
                 WHERE om.event_id = ? AND s.user_id = ?
                 ORDER BY om.created_at ASC";
$messages_stmt = $conn->prepare($messages_sql);

if (!$messages_stmt) {
    die("Error preparing messages statement: " . $conn->error);
}

$messages_stmt->bind_param("ii", $event_id, $user_id);
$messages_stmt->execute();
$messages_result = $messages_stmt->get_result();
$messages = [];
while ($row = $messages_result->fetch_assoc()) {
    $messages[] = $row;
}
$messages_stmt->close();

// --- INCLUDE HEADER ---
include 'header.php'; 
?>

<style>
    .text-ukm-blue { color: var(--ukm-blue); }
    .bg-ukm-blue { background-color: var(--ukm-blue); }
    
    .chat-container {
        height: calc(100vh - 250px);
        min-height: 500px;
    }

    .message-bubble {
        max-width: 75%;
        padding: 12px 16px;
        border-radius: 12px;
        margin-bottom: 8px;
        word-wrap: break-word;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    }
    
    /* Sent by Student (Me) -> UKM Blue */
    .message-student {
        background-color: var(--ukm-blue);
        color: white;
        margin-left: auto;
        border-bottom-right-radius: 2px;
    }
    
    /* Sent by Organizer -> Gray */
    .message-organizer {
        background-color: #f3f4f6;
        color: #1f2937;
        margin-right: auto;
        border: 1px solid #e5e7eb;
        border-bottom-left-radius: 2px;
    }
    
    .chat-messages {
        flex: 1;
        overflow-y: auto;
        padding: 24px;
        background-color: #ffffff;
        /* Scrollbar styling */
        scrollbar-width: thin;
        scrollbar-color: #cbd5e1 transparent;
    }
    
    .chat-messages::-webkit-scrollbar { width: 6px; }
    .chat-messages::-webkit-scrollbar-track { background: transparent; }
    .chat-messages::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 20px; }

    .chat-input-area {
        padding: 16px;
        background-color: white;
        border-top: 1px solid #e5e7eb;
    }
    
    .event-header {
        background: white;
        border-bottom: 1px solid #e5e7eb;
        padding: 16px 24px;
    }
</style>

<div class="max-w-5xl mx-auto px-4 py-8">
    
    <div class="mb-4">
        <a href="event_details.php?id=<?php echo $event_id; ?>" class="inline-flex items-center text-gray-500 hover:text-ukm-blue font-medium transition-colors">
            <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Back to Event Details
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden flex flex-col chat-container">
        
        <div class="event-header flex justify-between items-center bg-gray-50">
            <div>
                <h2 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                    <?php echo htmlspecialchars($event['title']); ?>
                </h2>
                <div class="flex items-center text-sm text-gray-600 mt-1">
                    <span class="font-semibold text-ukm-blue mr-2"><?php echo htmlspecialchars($event['club_name']); ?></span>
                    <span class="text-gray-300 mx-2">|</span>
                    <span>Organizer Chat</span>
                </div>
            </div>
            <div class="text-right hidden sm:block">
                <p class="text-xs text-gray-500">Event Date</p>
                <p class="text-sm font-medium text-gray-700"><?php echo date('d M Y', strtotime($event['event_date'])); ?></p>
            </div>
        </div>

        <?php if ($message_sent): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-3 m-4 text-sm" role="alert">
                <p class="font-bold">Success</p>
                <p>Message sent successfully.</p>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-3 m-4 text-sm" role="alert">
                <p class="font-bold">Error</p>
                <p><?php echo $error; ?></p>
            </div>
        <?php endif; ?>

        <div class="chat-messages" id="chatMessages">
            <div class="text-center text-gray-400 py-6 mb-4">
                <div class="bg-blue-50 border border-blue-100 text-ukm-blue rounded-full px-4 py-1 text-xs font-semibold inline-block mb-2">
                    Start of conversation
                </div>
                <p class="text-xs">Ask questions about requirements, schedule, or venue.</p>
            </div>

            <?php if (empty($messages)): ?>
                <div class="flex flex-col items-center justify-center h-48 text-gray-400">
                    <svg class="w-12 h-12 mb-2 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                    </svg>
                    <p class="text-sm">No messages yet.</p>
                </div>
            <?php else: ?>
                <?php foreach ($messages as $message): ?>
                    <div class="flex flex-col <?php echo $message['sender_type'] == 'student' ? 'items-end' : 'items-start'; ?> mb-4">
                        <div class="message-bubble <?php echo $message['sender_type'] == 'student' ? 'message-student' : 'message-organizer'; ?>">
                            <p class="text-sm leading-relaxed whitespace-pre-wrap"><?php echo htmlspecialchars($message['message']); ?></p>
                        </div>
                        <div class="flex items-center gap-1 text-xs text-gray-400 mt-1 px-1">
                            <span>
                                <?php 
                                    if ($message['sender_type'] == 'student') {
                                        echo "You";
                                    } else {
                                        echo htmlspecialchars($message['organizer_name'] ?: 'Organizer');
                                    }
                                ?>
                            </span>
                            <span>•</span>
                            <span><?php echo date('M j, g:i A', strtotime($message['created_at'])); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="chat-input-area">
            <form method="POST" action="" class="flex gap-3">
                <input type="text" name="message" 
                       placeholder="Type your message here..." 
                       class="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm transition-shadow" 
                       required
                       autocomplete="off"
                       value="<?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?>">
                
                <button type="submit" name="send_message" 
                        class="bg-ukm-blue hover:bg-blue-800 text-white px-6 py-3 rounded-lg font-medium flex items-center gap-2 transition-all shadow-sm hover:shadow-md">
                    <span>Send</span>
                    <svg class="w-4 h-4 transform rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                    </svg>
                </button>
            </form>
            <p class="text-xs text-gray-400 mt-2 text-center">
                Please be respectful. Messages are monitored for quality assurance.
            </p>
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
</script>

<?php 
// --- INCLUDE FOOTER ---
include 'footer.php'; 
?>
<?php $conn->close(); ?>