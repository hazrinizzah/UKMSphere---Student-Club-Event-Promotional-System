<?php
// Default values
$user_type = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : 'guest';
$search_value = isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '';
$current_page = basename($_SERVER['PHP_SELF']);

// Determine Display Name
$display_name = 'User';
if (isset($_SESSION['full_name'])) {
    $names = explode(" ", trim($_SESSION['full_name']));
    $display_name = $names[0];
} elseif (isset($_SESSION['club_name'])) {
    $display_name = $_SESSION['club_name'];
}

// --- NOTIFICATION & INBOX LOGIC ---
$chat_list = [];
$total_unread = 0;

if (isset($_SESSION['user_id'])) {
    
    // ----------------------------------------------------
    // CASE 1: USER IS A STUDENT
    // ----------------------------------------------------
    if ($user_type == 'student') {
        // 1. Get Internal ID
        $s_sql = "SELECT id FROM students_abelities WHERE user_id = ?";
        if ($s_stmt = $conn->prepare($s_sql)) {
            $s_stmt->bind_param("i", $_SESSION['user_id']);
            $s_stmt->execute();
            $s_res = $s_stmt->get_result();
            
            if ($s_res->num_rows > 0) {
                $student_id = $s_res->fetch_assoc()['id'];
                
                // 2. [FIX] Get Total Unread Count (Simple, robust query for the bubble)
                $count_sql = "SELECT COUNT(*) as total 
                              FROM organizer_messages_abelities 
                              WHERE student_id = ? AND sender_type = 'club' AND is_read = 0";
                if ($c_stmt = $conn->prepare($count_sql)) {
                    $c_stmt->bind_param("i", $student_id);
                    $c_stmt->execute();
                    $total_unread = $c_stmt->get_result()->fetch_assoc()['total'];
                    $c_stmt->close();
                }

                // 3. Get Chat List (For the dropdown menu)
                $msg_sql = "SELECT 
                                c.club_name as display_name, 
                                c.id as entity_id,
                                MAX(m.event_id) as event_id,
                                SUM(CASE WHEN m.is_read = 0 AND m.sender_type = 'club' THEN 1 ELSE 0 END) as unread_count
                            FROM organizer_messages_abelities m
                            JOIN clubs_abelities c ON m.organizer_id = c.id
                            WHERE m.student_id = ?
                            GROUP BY c.id
                            ORDER BY unread_count DESC, MAX(m.created_at) DESC"; 
                
                if ($stmt = $conn->prepare($msg_sql)) {
                    $stmt->bind_param("i", $student_id);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    while ($row = $res->fetch_assoc()) {
                        $row['link'] = "contact_organizer.php?event_id=" . $row['event_id']; 
                        $chat_list[] = $row;
                    }
                    $stmt->close();
                }
            }
            $s_stmt->close();
        }
    } 
    // ----------------------------------------------------
    // CASE 2: USER IS A CLUB
    // ----------------------------------------------------
    elseif ($user_type == 'club') {
        // 1. Get Internal ID
        $c_sql = "SELECT id FROM clubs_abelities WHERE user_id = ?";
        if ($c_stmt = $conn->prepare($c_sql)) {
            $c_stmt->bind_param("i", $_SESSION['user_id']);
            $c_stmt->execute();
            $c_res = $c_stmt->get_result();
            
            if ($c_res->num_rows > 0) {
                $club_id = $c_res->fetch_assoc()['id'];

                // 2. [FIX] Get Total Unread Count (Simple, robust query for the bubble)
                $count_sql = "SELECT COUNT(*) as total 
                              FROM organizer_messages_abelities 
                              WHERE organizer_id = ? AND sender_type = 'student' AND is_read = 0";
                if ($cnt_stmt = $conn->prepare($count_sql)) {
                    $cnt_stmt->bind_param("i", $club_id);
                    $cnt_stmt->execute();
                    $total_unread = $cnt_stmt->get_result()->fetch_assoc()['total'];
                    $cnt_stmt->close();
                }
                
                // 3. Get Chat List (For the dropdown menu)
                // We group by student AND event so you know exactly which event they are asking about
                $msg_sql = "SELECT 
                                s.full_name as display_name, 
                                s.id as entity_id,
                                m.event_id,
                                SUM(CASE WHEN m.is_read = 0 AND m.sender_type = 'student' THEN 1 ELSE 0 END) as unread_count
                            FROM organizer_messages_abelities m
                            JOIN students_abelities s ON m.student_id = s.id
                            WHERE m.organizer_id = ?
                            GROUP BY s.id, m.event_id
                            ORDER BY unread_count DESC, MAX(m.created_at) DESC"; 
                
                if ($stmt = $conn->prepare($msg_sql)) {
                    $stmt->bind_param("i", $club_id);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    while ($row = $res->fetch_assoc()) {
                        $row['link'] = "view_event_messages.php?event_id=" . $row['event_id'] . "&student_id=" . $row['entity_id'];
                        $chat_list[] = $row;
                    }
                    $stmt->close();
                }
            }
            $c_stmt->close();
        }
    }
}

// HELPER FUNCTION: Defines navigation links
function render_nav_links($type, $current, $is_mobile = false) {
    // Styles for Desktop vs Mobile
    $base_class = $is_mobile 
        ? "block px-4 py-3 rounded-lg text-gray-700 font-medium hover:bg-gray-100 hover:text-[var(--ukm-blue)] transition flex items-center gap-3" 
        : "nav-link";
        
    $active_class = $is_mobile
        ? "bg-blue-50 text-[var(--ukm-blue)] border-l-4 border-[var(--ukm-blue)]"
        : "active";

    // Icons
    $icons = [
        'dash' => '<i class="fa-solid fa-chart-pie w-5"></i>',
        'feed' => '<i class="fa-solid fa-calendar-days w-5"></i>',
        'ticket' => '<i class="fa-solid fa-ticket w-5"></i>',
        'plus' => '<i class="fa-solid fa-plus w-5"></i>',
        'list' => '<i class="fa-solid fa-list w-5"></i>',
        'file' => '<i class="fa-solid fa-file-lines w-5"></i>',
        'login' => '<i class="fa-solid fa-right-to-bracket w-5"></i>'
    ];
    $i = $is_mobile ? $icons : array_fill_keys(array_keys($icons), ''); 

    if ($type == 'student') {
        echo '<a href="dashboard.php" class="' . $base_class . ' ' . ($current=='dashboard.php'?$active_class:'') . '">'.$i['dash'].' Dashboard</a>';
        echo '<a href="view_events.php" class="' . $base_class . ' ' . ($current=='view_events.php'?$active_class:'') . '">'.$i['feed'].' Event Feed</a>';
        echo '<a href="event_registered.php" class="' . $base_class . ' ' . ($current=='event_registered.php'?$active_class:'') . '">'.$i['ticket'].' My Events</a>';
    } elseif ($type == 'club') {
        echo '<a href="dashboard.php" class="' . $base_class . ' ' . ($current=='dashboard.php'?$active_class:'') . '">'.$i['dash'].' Dashboard</a>';
        echo '<a href="create_event.php" class="' . $base_class . ' ' . ($current=='create_event.php'?$active_class:'') . '">'.$i['plus'].' Create Event</a>';
        echo '<a href="club_events.php" class="' . $base_class . ' ' . ($current=='club_events.php'?$active_class:'') . '">'.$i['list'].' My Events</a>';
        echo '<a href="view_events.php" class="' . $base_class . ' ' . ($current=='view_events.php'?$active_class:'') . '">'.$i['feed'].' Event Feed</a>';
    } elseif ($type == 'hep') {
        echo '<a href="dashboard.php" class="' . $base_class . ' ' . ($current=='dashboard.php'?$active_class:'') . '">'.$i['dash'].' Dashboard</a>';
        echo '<a href="view_events.php" class="' . $base_class . ' ' . ($current=='view_events.php'?$active_class:'') . '">'.$i['feed'].' Event Feed</a>';
        echo '<a href="reports.php" class="' . $base_class . ' ' . ($current=='reports.php'?$active_class:'') . '">'.$i['file'].' Reports</a>';
    } else {
        echo '<a href="view_events.php" class="' . $base_class . ' ' . ($current=='view_events.php'?$active_class:'') . '">'.$i['feed'].' Event Feed</a>';
        echo '<a href="auth.php?mode=login" class="' . $base_class . '">'.$i['login'].' Login</a>';
        if($is_mobile) {
            echo '<a href="auth.php?mode=register" class="block px-4 py-3 rounded-lg text-white font-medium bg-[var(--ukm-blue)] text-center mt-4">Register Now</a>';
        } else {
            echo '<a href="auth.php?mode=register" class="nav-link register-btn">Register</a>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UKMSphere</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        body { font-family: 'Montserrat', sans-serif; background-color: #f7f7f7; }
        :root { --ukm-blue: #004a98; --ukm-red: #d10028; }

        .dashboard-header {
            position: sticky; top: 0; z-index: 50;
            background-color: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .nav-link {
            color: #333; font-weight: 500; font-size: 0.875rem;
            padding: 8px 12px; border-radius: 6px; transition: all 0.2s;
            text-decoration: none; display: inline-block;
        }
        .nav-link:hover { color: var(--ukm-blue); background-color: #f0f7ff; }
        .nav-link.active { background-color: var(--ukm-blue); color: #fff; }
        .register-btn { background-color: var(--ukm-blue); color: #fff !important; }

        #mobile-sidebar { transform: translateX(100%); transition: transform 0.3s ease-in-out; }
        #mobile-sidebar.open { transform: translateX(0); }
        
        #mobile-search-overlay {
            transform: translateY(-100%); transition: transform 0.3s ease-in-out;
            opacity: 0; pointer-events: none;
        }
        #mobile-search-overlay.open { 
            transform: translateY(0); opacity: 1; pointer-events: auto;
        }
        
        .profile-dropdown {
            transform-origin: top right;
            transition: transform 0.1s ease-out, opacity 0.1s ease-out;
        }

        @keyframes pulse-red {
            0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
            70% { transform: scale(1.1); box-shadow: 0 0 0 4px rgba(239, 68, 68, 0); }
            100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }
        .animate-pulse-red {
            animation: pulse-red 2s infinite;
        }
    </style>
</head>
<body class="bg-gray-50">

<header class="dashboard-header w-full">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16 relative">

            <div class="flex-shrink-0 flex items-center gap-3 z-20" id="header-brand">
                <a href="view_events.php" class="flex items-center gap-2 text-decoration-none group">
                    <img src="images/ukm-logo.png" alt="UKM Logo" class="h-10 w-auto transition-transform group-hover:scale-105">
                    <span class="font-extrabold text-xl text-[var(--ukm-blue)] tracking-tight">UKMSphere</span>
                </a>
            </div>

            <div class="hidden md:flex flex-1 justify-center px-8">
                <form action="view_events.php" method="GET" class="w-full max-w-lg">
                    <div class="relative group">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </span>
                        <input type="text" name="search" value="<?php echo $search_value; ?>" 
                               placeholder="Search events..." 
                               class="w-full py-2 pl-10 pr-4 bg-gray-100 border-transparent rounded-full focus:bg-white focus:border-[var(--ukm-blue)] focus:ring-2 focus:ring-blue-100 outline-none transition-all">
                    </div>
                </form>
            </div>

            <div class="hidden md:flex items-center space-x-1">
                <?php render_nav_links($user_type, $current_page, false); ?>
                
                <?php if ($user_type != 'guest'): ?>
                    <div class="relative ml-4">
                        
                        <button type="button" id="desktop-profile-btn" onclick="toggleDesktopProfile(event)" class="relative flex items-center justify-center h-10 w-10 transition cursor-pointer hover:shadow-md rounded-full focus:outline-none">
                            
                            <?php if ($total_unread > 0): ?>
                                <div class="h-10 w-10 bg-red-600 text-white font-bold flex items-center justify-center rounded-full animate-pulse-red shadow-lg border-2 border-white z-50 text-sm">
                                    <?php echo $total_unread > 9 ? '9+' : $total_unread; ?>
                                </div>
                            <?php else: ?>
                                <img class="h-10 w-10 rounded-full object-cover border-2 border-gray-100" src="images/profile.png" alt="Profile">
                            <?php endif; ?>

                        </button>
                        
                        <div id="desktop-profile-menu" class="hidden absolute right-0 mt-3 w-80 bg-white rounded-xl shadow-2xl py-2 ring-1 ring-black ring-opacity-5 z-[60] profile-dropdown">
                            <div class="px-4 py-3 border-b bg-gray-50 rounded-t-xl">
                                <p class="text-xs text-gray-500 uppercase tracking-wider font-semibold">Signed in as</p>
                                <p class="text-sm font-bold text-[var(--ukm-blue)] truncate"><?php echo htmlspecialchars($display_name); ?></p>
                            </div>

                            <div class="py-1 border-b border-gray-100 max-h-60 overflow-y-auto">
                                <div class="px-4 py-2 flex justify-between items-center bg-white sticky top-0 z-10 border-b border-gray-50">
                                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">
                                        Inbox <?php echo ($total_unread > 0) ? "($total_unread)" : ""; ?>
                                    </span>
                                </div>
                                
                                <?php if (!empty($chat_list)): ?>
                                    <?php foreach ($chat_list as $chat): ?>
                                        <a href="<?php echo $chat['link']; ?>" class="flex items-center px-4 py-3 text-sm text-gray-700 hover:bg-blue-50 transition border-l-2 border-transparent hover:border-[var(--ukm-blue)]">
                                            <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-[var(--ukm-blue)] mr-3 flex-shrink-0">
                                                <i class="fa-solid fa-envelope"></i>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="font-medium truncate <?php echo $chat['unread_count'] > 0 ? 'text-gray-900 font-bold' : 'text-gray-600'; ?>">
                                                    <?php echo htmlspecialchars($chat['display_name']); ?>
                                                </p>
                                                <?php if(isset($chat['event_id']) && $user_type == 'club'): ?>
                                                    <p class="text-xs text-gray-400 truncate">Event ID: <?php echo $chat['event_id']; ?></p>
                                                <?php else: ?>
                                                    <p class="text-xs text-gray-400 truncate">Tap to view chat</p>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($chat['unread_count'] > 0): ?>
                                                <span class="bg-red-500 text-white text-[10px] px-2 py-0.5 rounded-full font-bold shadow-sm">
                                                    <?php echo $chat['unread_count']; ?>
                                                </span>
                                            <?php endif; ?>
                                        </a>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="px-4 py-4 text-center text-sm text-gray-400 italic">
                                        No messages found.
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <a href="logout.php" class="block px-4 py-3 text-sm text-red-600 hover:bg-red-50 transition w-full text-left rounded-b-xl">
                                <i class="fa-solid fa-arrow-right-from-bracket mr-2"></i> Logout
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="flex items-center gap-3 md:hidden z-20">
                <button onclick="toggleMobileSearch()" class="text-gray-600 hover:text-[var(--ukm-blue)] p-2 rounded-full hover:bg-gray-100 transition">
                    <i class="fa-solid fa-magnifying-glass text-lg"></i>
                </button>
                
                <button onclick="toggleSidebar()" class="text-gray-600 hover:text-[var(--ukm-blue)] p-2 rounded-full hover:bg-gray-100 transition relative overflow-visible">
                    <i class="fa-solid fa-bars text-xl"></i>
                    <?php if ($total_unread > 0): ?>
                        <span class="absolute top-0 right-0 h-3 w-3 bg-red-500 rounded-full border-2 border-white animate-pulse"></span>
                    <?php endif; ?>
                </button>
            </div>

            <div id="mobile-search-overlay" class="absolute inset-0 bg-white z-30 flex items-center px-2 w-full h-full shadow-sm">
                <form action="view_events.php" method="GET" class="w-full flex items-center gap-2">
                    <div class="relative flex-grow">
                         <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-[var(--ukm-blue)]">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </span>
                        <input type="text" name="search" value="<?php echo $search_value; ?>" 
                               placeholder="Search UKMSphere..." 
                               class="w-full py-2 pl-10 pr-4 bg-gray-100 rounded-full focus:outline-none focus:ring-2 focus:ring-[var(--ukm-blue)] text-sm" autofocus>
                    </div>
                    <button type="button" onclick="toggleMobileSearch()" class="text-gray-500 px-2 py-1 text-sm font-semibold hover:text-red-500">
                        Cancel
                    </button>
                </form>
            </div>

        </div>
    </div>
</header>

<div id="sidebar-backdrop" onclick="toggleSidebar()" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden transition-opacity backdrop-blur-sm"></div>

<div id="mobile-sidebar" class="fixed inset-y-0 right-0 w-[280px] bg-white shadow-2xl z-50 overflow-y-auto">
    <div class="p-5 border-b flex justify-between items-center bg-gray-50">
        <div class="flex items-center gap-2">
            <img src="images/ukm-logo.png" class="h-6 w-auto">
            <span class="font-bold text-gray-800">Menu</span>
        </div>
        <button onclick="toggleSidebar()" class="w-8 h-8 flex items-center justify-center rounded-full bg-gray-200 text-gray-600 hover:bg-red-100 hover:text-red-600 transition">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    <?php if ($user_type != 'guest'): ?>
    <div class="p-5 border-b bg-blue-50/50">
        <div class="flex items-center gap-3">
            <div class="relative overflow-visible">
                <?php if ($total_unread > 0): ?>
                    <div class="h-12 w-12 bg-red-600 text-white font-bold flex items-center justify-center rounded-full shadow-md border-2 border-white animate-pulse-red text-lg">
                        <?php echo $total_unread; ?>
                    </div>
                <?php else: ?>
                    <img src="images/profile.png" class="h-12 w-12 rounded-full border-2 border-white shadow-sm">
                <?php endif; ?>
            </div>
            <div>
                <p class="text-xs text-gray-500 uppercase font-bold tracking-wide">Hello,</p>
                <p class="font-bold text-[var(--ukm-blue)] text-lg leading-tight truncate w-32"><?php echo htmlspecialchars($display_name); ?></p>
            </div>
        </div>
        
        <div class="mt-4 pt-3 border-t border-blue-100">
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">
                Inbox <?php echo ($total_unread > 0) ? "($total_unread)" : ""; ?>
            </p>
            <?php if (!empty($chat_list)): ?>
                <div class="space-y-2">
                    <?php foreach ($chat_list as $chat): ?>
                        <a href="<?php echo $chat['link']; ?>" class="block bg-white border border-gray-200 rounded-lg p-3 shadow-sm active:bg-blue-50 transition relative">
                            <div class="flex justify-between items-center">
                                <span class="text-sm font-semibold text-[var(--ukm-blue)] truncate pr-6">
                                    <i class="fa-solid fa-envelope mr-1"></i> <?php echo htmlspecialchars($chat['display_name']); ?>
                                </span>
                                <?php if ($chat['unread_count'] > 0): ?>
                                    <span class="bg-red-500 text-white text-[10px] px-2 py-0.5 rounded-full font-bold">
                                        <?php echo $chat['unread_count']; ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <p class="text-xs text-gray-400 mt-1">Tap to chat</p>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-xs text-gray-400 italic">No conversations yet.</p>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <nav class="p-4 flex flex-col space-y-1">
        <?php render_nav_links($user_type, $current_page, true); ?>
        
        <?php if ($user_type != 'guest'): ?>
            <div class="border-t my-4"></div>
            <a href="logout.php" class="block px-4 py-3 rounded-lg text-red-600 font-medium hover:bg-red-50 flex items-center gap-3">
                <i class="fa-solid fa-arrow-right-from-bracket w-5"></i> Logout
            </a>
        <?php endif; ?>
    </nav>
</div>

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('mobile-sidebar');
        const backdrop = document.getElementById('sidebar-backdrop');
        sidebar.classList.toggle('open');
        backdrop.classList.toggle('hidden');
    }

    function toggleMobileSearch() {
        const overlay = document.getElementById('mobile-search-overlay');
        overlay.classList.toggle('open');
        if(overlay.classList.contains('open')) {
            overlay.querySelector('input').focus();
        }
    }

    function toggleDesktopProfile(e) {
        if(e) e.stopPropagation(); 
        const menu = document.getElementById('desktop-profile-menu');
        menu.classList.toggle('hidden');
    }

    document.addEventListener('click', function(event) {
        const menu = document.getElementById('desktop-profile-menu');
        const btn = document.getElementById('desktop-profile-btn');
        
        if (menu && !menu.classList.contains('hidden')) {
            if (!btn.contains(event.target) && !menu.contains(event.target)) {
                menu.classList.add('hidden');
            }
        }
    });
</script>
</body>
</html>