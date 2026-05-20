<?php
include 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_type = $_SESSION['user_type'];
$user_id = $_SESSION['user_id'];
$student_table_id = null;

if ($user_type === 'student') {
    $student_sql = "SELECT id FROM students_abelities WHERE user_id = ?";
    $student_stmt = $conn->prepare($student_sql);
    $student_stmt->bind_param("i", $user_id);
    $student_stmt->execute();
    $student_result = $student_stmt->get_result();
    if ($student_result->num_rows === 1) {
        $student_table_id = $student_result->fetch_assoc()['id'];
    }
    $student_stmt->close();
}

// --- 2. GET STATUS FILTER (Default to 'upcoming') ---
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'upcoming';
$valid_statuses = ['upcoming', 'ongoing', 'finished'];
if (!in_array($status_filter, $valid_statuses)) {
    $status_filter = 'upcoming';
}

// --- 3. DATA FETCHING LOGIC ---
$sql = "SELECT e.*, c.club_name 
        FROM events_abelities e 
        JOIN clubs_abelities c ON e.club_id = c.id 
        WHERE e.event_status = ? 
        ORDER BY e.event_date ASC, e.created_at DESC";

// Execute with prepared statement
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $status_filter);
$stmt->execute();
$result = $stmt->get_result();

$events = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $events[] = $row;
    }
}
$stmt->close();

// Handle search and filter variables
$search_term = '';
$category_filter = 'all'; 

if ($_SERVER["REQUEST_METHOD"] == "GET") {
    if (isset($_GET['search'])) {
        $search_term = trim($_GET['search']);
    }
    if (isset($_GET['category']) && !empty($_GET['category'])) {
        $category_filter = $_GET['category'];
    }
}

// Filter events based on search and category
$filtered_events = $events; 
if (!empty($search_term)) {
    $filtered_events = array_filter($filtered_events, function($event) use ($search_term) {
        return stripos($event['title'], $search_term) !== false || 
               stripos($event['description'], $search_term) !== false ||
               stripos($event['club_name'], $search_term) !== false;
    });
}

if ($category_filter != 'all') {
    $filtered_events = array_filter($filtered_events, function($event) use ($category_filter) {
        return $event['category'] == $category_filter;
    });
}

// Personalize ordering for students
$category_preferences = [];
$category_rank = [];
if ($user_type === 'student' && $student_table_id) {
    $pref_sql = "SELECT event_type, SUM(view_count) as total_views
                 FROM event_views_abelities
                 WHERE student_id = ?
                 GROUP BY event_type
                 HAVING total_views > 5
                 ORDER BY total_views DESC";
    $pref_stmt = $conn->prepare($pref_sql);
    $pref_stmt->bind_param("i", $student_table_id);
    $pref_stmt->execute();
    $pref_result = $pref_stmt->get_result();
    while ($row = $pref_result->fetch_assoc()) {
        $category_preferences[] = $row['event_type'];
    }
    $pref_stmt->close();
}

if (!empty($category_preferences) && count($filtered_events) > 1) {
    $category_rank = array_flip($category_preferences); 
    usort($filtered_events, function($a, $b) use ($category_rank) {
        $rankA = isset($category_rank[$a['category']]) ? $category_rank[$a['category']] : PHP_INT_MAX;
        $rankB = isset($category_rank[$b['category']]) ? $category_rank[$b['category']] : PHP_INT_MAX;
        
        if ($rankA != $rankB) { return ($rankA < $rankB) ? -1 : 1; }
        if ($a['event_date'] !== $b['event_date']) { return strcmp($a['event_date'], $b['event_date']); }
        return strcmp($b['created_at'], $a['created_at']);
    });
}

// Recommendations
$recommended_events = [];
if ($status_filter === 'upcoming' && $user_type === 'student' && !empty($category_preferences)) {
    $top_pref_categories = array_slice($category_preferences, 0, 1);
    foreach ($filtered_events as $ev) {
        if (in_array($ev['category'], $top_pref_categories)) { 
            $recommended_events[] = $ev;
        }
        if (count($recommended_events) >= 4) { break; }
    }
}

include 'header.php';
?>

<style>
    /* 1. Icon Scroll Bar Styles */
    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

    .category-icon-btn { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
    .category-icon-btn:hover { transform: translateY(-5px); }
    .category-icon-btn.active .icon-box {
        background-color: var(--ukm-blue); color: white;
        box-shadow: 0 4px 15px rgba(0, 74, 152, 0.4); transform: scale(1.1);
        border-color: var(--ukm-blue);
    }
    .category-icon-btn.active span { color: var(--ukm-blue); font-weight: 800; }
    .icon-box { transition: all 0.3s ease; }

    /* 2. Animations */
    .event-card {
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        border: 1px solid transparent; transform: scale(1);
    }
    .event-card:hover {
        transform: scale(1.02) translateY(-5px);
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        border-color: rgba(0, 74, 152, 0.2); z-index: 10;
    }
    @keyframes fadeInUp { from { opacity: 0; transform: translate3d(0, 20px, 0); } to { opacity: 1; transform: translate3d(0, 0, 0); } }
    .animate-fade-in-up { animation: fadeInUp 0.5s ease-out forwards; }
    .card-btn { transition: transform 0.2s ease, background-color 0.2s ease; }
    .card-btn:hover { transform: translateY(-2px); }

    /* 3. Status Tabs */
    .status-tab { padding: 0.75rem 1.5rem; font-weight: 600; border-bottom: 2px solid transparent; color: #6b7280; transition: all 0.2s; }
    .status-tab.active { color: #7e22ce; border-color: #7e22ce; }
    .status-tab:hover:not(.active) { color: #374151; border-color: #d1d5db; }

    /* 4. DIAGONAL RIBBON STYLE */
    .ribbon-wrapper {
        width: 85px; height: 85px;
        overflow: hidden;
        position: absolute; top: 0; right: 0; z-index: 20;
    }
    .ribbon-diagonal {
        font-weight: 800;
        text-align: center;
        transform: rotate(45deg);
        position: relative;
        padding: 5px 0;
        left: -5px; top: 15px;
        width: 120px;
        background-color: #fbbf24; /* Yellow-400 */
        color: #78350f; /* Brown text */
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        border: 2px dashed #92400e;
        font-size: 11px;
        letter-spacing: 0.5px;
        text-transform: uppercase;
    }
</style>

<div class="max-w-7xl mx-auto px-4 py-8">
    
    <div class="mb-6"> 
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Event Dashboard</h1>
        <?php if ($user_type === 'hep'): ?>
        <div class="flex border-b border-gray-200 mb-6">
            <a href="?status=upcoming" class="status-tab <?php echo ($status_filter == 'upcoming') ? 'active' : ''; ?>">Upcoming</a>
            <a href="?status=ongoing" class="status-tab <?php echo ($status_filter == 'ongoing') ? 'active' : ''; ?>">Ongoing</a>
            <a href="?status=finished" class="status-tab <?php echo ($status_filter == 'finished') ? 'active' : ''; ?>">Past Events</a>
        </div>
        <?php endif; ?>
    </div>

    <div class="mb-8 overflow-x-auto no-scrollbar p-4 -mx-4 md:mx-0">
        <div class="flex md:grid md:grid-cols-7 gap-4 min-w-max md:min-w-0 px-1">
            <?php
            $cats = array(
                'all' => array('label' => 'All', 'path' => 'M4 6h16M4 12h16M4 18h16'),
                'academic' => array('label' => 'Academic', 'path' => 'M12 14l9-5-9-5-9 5 9 5z M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z'),
                'sports' => array('label' => 'Sports', 'path' => 'M12 2a10 10 0 100 20 10 10 0 000-20zm0 18a8 8 0 110-16 8 8 0 010 16z M12 12l3.5-3.5 M12 12l-3.5 3.5 M12 12l-3.5-3.5 M12 12l3.5 3.5'),
                'cultural' => array('label' => 'Cultural', 'path' => 'M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3'),
                'social' => array('label' => 'Social', 'path' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z'),
                'workshop' => array('label' => 'Workshop', 'path' => 'M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z'),
                'competition' => array('label' => 'Contest', 'path' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z')
            );
            foreach ($cats as $key => $data) {
                $isActive = ($category_filter == $key) ? 'active' : '';
                $url = "?status=" . urlencode($status_filter) . "&category=$key";
                if (!empty($search_term)) $url .= "&search=" . urlencode($search_term);
                echo '<a href="' . $url . '" class="category-icon-btn flex flex-col items-center justify-center min-w-[80px] md:w-full cursor-pointer group flex-shrink-0 snap-center ' . $isActive . '">';
                echo '  <div class="icon-box w-16 h-16 rounded-full bg-white border border-gray-200 flex items-center justify-center text-gray-500 shadow-sm group-hover:border-blue-400 group-hover:text-blue-600 mb-2">';
                echo '      <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="' . $data['path'] . '" /></svg>';
                echo '  </div>';
                echo '  <span class="text-xs font-medium text-gray-500 group-hover:text-gray-800">' . $data['label'] . '</span>';
                echo '</a>';
            }
            ?>
        </div>
    </div>

    <?php if (!empty($recommended_events) && $status_filter == 'upcoming'): ?>
        <div class="mb-10 animate-fade-in-up">
            <div class="flex items-center gap-2 mb-4">
                <span class="inline-flex items-center px-3 py-1 rounded-full bg-purple-100 text-purple-700 text-xs font-semibold shadow-sm">Recommended for you</span>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($recommended_events as $event): ?>
                    <?php
                    // Create ISO datetime string for JS
                    $iso_datetime = date('Y-m-d\TH:i:s', strtotime($event['event_date'] . ' ' . $event['event_time']));
                    
                    // Simple PHP date logic for "Today" visual only (non-critical)
                    $today_date = new DateTime('now', new DateTimeZone('Asia/Kuala_Lumpur'));
                    $today_date->setTime(0, 0, 0);
                    $event_date_display = new DateTime($event['event_date'], new DateTimeZone('Asia/Kuala_Lumpur'));
                    $event_date_display->setTime(0, 0, 0);
                    $interval = $today_date->diff($event_date_display);
                    $days_diff = (int)$interval->format('%r%a');
                    $is_today = ($days_diff === 0);
                    
                    // Default PHP Badge (Will be overridden by JS if expired)
                    $time_badge = null;
                    if ($event['event_status'] == 'finished') {
                        $time_badge = array('text' => 'Completed', 'class' => 'bg-gray-100 text-gray-600 border border-gray-300');
                    } else {
                        if (!$is_today) { 
                            $time_badge = array('text' => 'Upcoming', 'class' => 'bg-green-100 text-green-800');
                            if ($days_diff === 1) {
                                $time_badge = array('text' => 'Tomorrow', 'class' => 'bg-orange-100 text-orange-800');
                            } elseif ($days_diff > 0 && $days_diff <= 7) {
                                $time_badge = array('text' => 'This week', 'class' => 'bg-yellow-100 text-yellow-800');
                            }
                        }
                    }

                    $reg_sql = "SELECT COUNT(*) as reg_count FROM event_registrations_abelities WHERE event_id = ?";
                    $reg_stmt = $conn->prepare($reg_sql);
                    $reg_stmt->bind_param("i", $event['id']);
                    $reg_stmt->execute();
                    $reg_result = $reg_stmt->get_result();
                    $reg_count = $reg_result->fetch_assoc()['reg_count'];
                    $reg_stmt->close();
                    ?>

                    <div class="event-card bg-white rounded-xl shadow-md overflow-hidden border border-gray-100 hover:shadow-xl transition-shadow duration-300 group relative" 
                         data-event-date="<?php echo $iso_datetime; ?>"
                         data-event-status="<?php echo $event['event_status']; ?>">
                         
                        <div class="h-48 bg-gray-100 flex items-center justify-center relative overflow-hidden">
                            <?php if ($is_today && $event['event_status'] !== 'finished'): ?>
                                <div class="ribbon-wrapper">
                                    <div class="ribbon-diagonal">TODAY</div>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($event['poster_path']) && file_exists($event['poster_path'])): ?>
                                <img src="<?php echo htmlspecialchars($event['poster_path']); ?>" alt="<?php echo htmlspecialchars($event['title']); ?>" class="w-full h-full object-cover transition-transform duration-700 ease-out group-hover:scale-110 <?php echo ($event['event_status'] == 'finished') ? 'grayscale' : ''; ?>">
                            <?php else: ?>
                                <div class="text-center text-white">
                                    <div class="text-4xl mb-2">
                                        <?php $iconPath = isset($cats[$event['category']]) ? $cats[$event['category']]['path'] : $cats['all']['path']; ?>
                                        <svg class="w-12 h-12 mx-auto text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="<?php echo $iconPath; ?>" /></svg>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="absolute top-3 left-3 space-y-2">
                                <span class="category-badge category-<?php echo $event['category']; ?> inline-block px-3 py-1 text-xs font-bold rounded-full shadow-sm whitespace-nowrap bg-white/90 backdrop-blur-sm text-gray-800"><?php echo ucfirst($event['category']); ?></span>
                            </div>
                            
                            <?php if ($time_badge): ?>
                            <div class="absolute top-3 right-3 time-badge-container">
                                <span class="time-badge <?php echo $time_badge['class']; ?> inline-block px-3 py-1 rounded-full text-xs font-bold shadow-sm whitespace-nowrap"><?php echo $time_badge['text']; ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="p-6">
                            <div class="mb-3"><span class="text-sm font-semibold text-purple-600"><?php echo htmlspecialchars($event['club_name']); ?></span></div>
                            <h3 class="text-xl font-bold text-gray-900 mb-3 line-clamp-2 h-14 group-hover:text-purple-700 transition-colors"><?php echo htmlspecialchars($event['title']); ?></h3>
                            <p class="text-gray-600 text-sm mb-4 line-clamp-3"><?php echo htmlspecialchars(substr($event['description'], 0, 120)) . '...'; ?></p>
                            
                            <div class="space-y-2 text-sm text-gray-700 mb-4">
                                <div class="flex items-center"><svg class="w-4 h-4 mr-2 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg><span><?php echo date('F j, Y', strtotime($event['event_date'])); ?></span></div>
                                <div class="flex items-center"><svg class="w-4 h-4 mr-2 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /></svg><span class="truncate"><?php echo htmlspecialchars($event['location']); ?></span></div>
                            </div>
                            
                            <div class="flex gap-2">
                                <a href="event_details.php?id=<?php echo $event['id']; ?>" class="card-btn action-btn flex-1 bg-purple-600 hover:bg-purple-700 text-white py-2 rounded-lg transition text-center text-sm font-medium shadow-md">
                                    <?php echo ($event['event_status'] == 'finished') ? 'View Report/Details' : 'View Details'; ?>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div id="results-count-container" class="mb-4 animate-fade-in-up" style="animation-delay: 0.1s;">
        <p class="text-gray-600">Showing <?php echo count($filtered_events); ?> <strong><?php echo ucfirst($status_filter); ?></strong> events</p>
    </div>

    <div id="main-events-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 animate-fade-in-up" style="animation-delay: 0.2s;">
        <?php foreach ($filtered_events as $event): ?>
            <?php
            // Create ISO datetime string for JS
            $iso_datetime = date('Y-m-d\TH:i:s', strtotime($event['event_date'] . ' ' . $event['event_time']));
            
            // Simple PHP date logic
            $today_date = new DateTime('now', new DateTimeZone('Asia/Kuala_Lumpur'));
            $today_date->setTime(0, 0, 0);
            $event_date_display = new DateTime($event['event_date'], new DateTimeZone('Asia/Kuala_Lumpur'));
            $event_date_display->setTime(0, 0, 0);
            $interval = $today_date->diff($event_date_display);
            $days_diff = (int)$interval->format('%r%a');
            $is_today = ($days_diff === 0);
            
            // Default Badge
            $time_badge = null;
            if ($event['event_status'] == 'finished') {
                $time_badge = array('text' => 'Completed', 'class' => 'bg-gray-100 text-gray-600 border border-gray-300');
            } else {
                if (!$is_today) { 
                    $time_badge = array('text' => 'Upcoming', 'class' => 'bg-green-100 text-green-800');
                    if ($days_diff === 1) {
                        $time_badge = array('text' => 'Tomorrow', 'class' => 'bg-orange-100 text-orange-800');
                    } elseif ($days_diff > 0 && $days_diff <= 7) {
                        $time_badge = array('text' => 'This week', 'class' => 'bg-yellow-100 text-yellow-800');
                    }
                }
            }

            $reg_sql = "SELECT COUNT(*) as reg_count FROM event_registrations_abelities WHERE event_id = ?";
            $reg_stmt = $conn->prepare($reg_sql);
            $reg_stmt->bind_param("i", $event['id']);
            $reg_stmt->execute();
            $reg_result = $reg_stmt->get_result();
            $reg_count = $reg_result->fetch_assoc()['reg_count'];
            $reg_stmt->close();
            ?>

            <div class="event-card bg-white rounded-xl shadow-md overflow-hidden border border-gray-100 hover:shadow-xl transition-shadow duration-300 group relative"
                 data-event-date="<?php echo $iso_datetime; ?>"
                 data-event-status="<?php echo $event['event_status']; ?>">
                 
                <div class="h-48 bg-gray-100 flex items-center justify-center relative overflow-hidden">
                    <?php if ($is_today && $event['event_status'] !== 'finished'): ?>
                        <div class="ribbon-wrapper">
                            <div class="ribbon-diagonal">TODAY</div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($event['poster_path']) && file_exists($event['poster_path'])): ?>
                        <img src="<?php echo htmlspecialchars($event['poster_path']); ?>" alt="<?php echo htmlspecialchars($event['title']); ?>" class="w-full h-full object-cover transition-transform duration-700 ease-out group-hover:scale-110 <?php echo ($event['event_status'] == 'finished') ? 'grayscale' : ''; ?>">
                    <?php else: ?>
                        <div class="text-center text-white">
                            <div class="text-4xl mb-2">
                                <?php $iconPath = isset($cats[$event['category']]) ? $cats[$event['category']]['path'] : $cats['all']['path']; ?>
                                <svg class="w-12 h-12 mx-auto text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="<?php echo $iconPath; ?>" /></svg>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="absolute top-3 left-3 space-y-2">
                        <span class="category-badge category-<?php echo $event['category']; ?> inline-block px-3 py-1 text-xs font-bold rounded-full shadow-sm whitespace-nowrap bg-white/90 backdrop-blur-sm text-gray-800"><?php echo ucfirst($event['category']); ?></span>
                    </div>
                    
                    <?php if ($time_badge): ?>
                    <div class="absolute top-3 right-3 time-badge-container">
                        <span class="time-badge <?php echo $time_badge['class']; ?> inline-block px-3 py-1 rounded-full text-xs font-bold shadow-sm whitespace-nowrap"><?php echo $time_badge['text']; ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="p-6">
                    <div class="mb-3"><span class="text-sm font-semibold text-purple-600"><?php echo htmlspecialchars($event['club_name']); ?></span></div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3 line-clamp-2 h-14 group-hover:text-purple-700 transition-colors"><?php echo htmlspecialchars($event['title']); ?></h3>
                    <p class="text-gray-600 text-sm mb-4 line-clamp-3"><?php echo htmlspecialchars(substr($event['description'], 0, 120)) . '...'; ?></p>
                    
                    <div class="space-y-2 text-sm text-gray-700 mb-4">
                        <div class="flex items-center"><svg class="w-4 h-4 mr-2 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg><span><?php echo date('F j, Y', strtotime($event['event_date'])); ?></span></div>
                        <div class="flex items-center"><svg class="w-4 h-4 mr-2 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /></svg><span class="truncate"><?php echo htmlspecialchars($event['location']); ?></span></div>
                    </div>
                    
                    <div class="flex gap-2">
                        <a href="event_details.php?id=<?php echo $event['id']; ?>" class="card-btn action-btn flex-1 bg-purple-600 hover:bg-purple-700 text-white py-2 rounded-lg transition text-center text-sm font-medium shadow-md">
                            <?php echo ($event['event_status'] == 'finished') ? 'View Report/Details' : 'View Details'; ?>
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <div id="js-empty-placeholder" style="display:none;" class="text-center py-12 animate-fade-in-up">
        <svg class="w-16 h-16 mx-auto text-gray-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 12h6m-6-4h6m2 5.291A7.962 7.962 0 0112 15c-2.34 0-4.5-1.006-6-2.709M15 11V9a6 6 0 00-12 0v2m0 0v.01M3 11v6a2 2 0 002 2h14a2 2 0 002-2v-6"/>
        </svg>
        <h3 class="text-lg font-medium text-gray-900 mb-2">No active events found</h3>
        <p class="text-gray-500 mb-4">Past events have been hidden.</p>
    </div>

    <?php if (count($filtered_events) == 0): ?>
        <div class="text-center py-12 animate-fade-in-up">
            <svg class="w-16 h-16 mx-auto text-gray-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 12h6m-6-4h6m2 5.291A7.962 7.962 0 0112 15c-2.34 0-4.5-1.006-6-2.709M15 11V9a6 6 0 00-12 0v2m0 0v.01M3 11v6a2 2 0 002 2h14a2 2 0 002-2v-6"/>
            </svg>
            <h3 class="text-lg font-medium text-gray-900 mb-2">No <?php echo $status_filter; ?> events found</h3>
            <p class="text-gray-500 mb-4">Try adjusting your search or switching tabs.</p>
        </div>
    <?php endif; ?>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        // --- CLIENT-SIDE TIME CHECK ---
        const userTime = new Date();
        const todayMidnight = new Date(userTime.getFullYear(), userTime.getMonth(), userTime.getDate());
        
        // Get URL status
        const urlParams = new URLSearchParams(window.location.search);
        const statusFilter = urlParams.get('status') || 'upcoming';

        const eventCards = document.querySelectorAll('.event-card');
        let visibleCount = 0;

        eventCards.forEach(card => {
            const eventDateStr = card.getAttribute('data-event-date');
            const eventStatus = card.getAttribute('data-event-status');
            let isHidden = false;
            
            // Skip if event is already marked as 'finished' in DB (those are usually in History tab)
            // But if we are in 'upcoming' tab, we want to hide finished/past events.
            
            if (eventDateStr) {
                const eventTime = new Date(eventDateStr);
                const eventDayMidnight = new Date(eventTime.getFullYear(), eventTime.getMonth(), eventTime.getDate());

                // LOGIC 1: DISAPPEAR FROM FEED (If today is strictly AFTER event day)
                // Only applies to Active/Upcoming feeds. 'Finished' feed shows everything.
                if (statusFilter !== 'finished' && todayMidnight > eventDayMidnight) {
                    card.style.display = 'none';
                    isHidden = true;
                }

                // LOGIC 2: REGISTRATION CLOSED (If not hidden)
                // If the user's current time is past the event start time
                if (!isHidden && eventStatus !== 'finished') {
                    if (userTime > eventTime) {
                        
                        // 1. Update Badge
                        const badgeContainer = card.querySelector('.time-badge-container');
                        if (badgeContainer) {
                            badgeContainer.innerHTML = '<span class="time-badge bg-slate-200 text-slate-500 border border-slate-300 inline-block px-3 py-1 rounded-full text-xs font-bold shadow-sm whitespace-nowrap">Registration Closed</span>';
                        } else {
                            // If no badge existed (e.g. was Today), add one
                            const ribbon = card.querySelector('.ribbon-wrapper');
                            if (ribbon) ribbon.remove(); 
                            
                            const imgContainer = card.querySelector('.h-48');
                            if (imgContainer) {
                                const newBadge = document.createElement('div');
                                newBadge.className = 'absolute top-3 right-3 time-badge-container';
                                newBadge.innerHTML = '<span class="time-badge bg-slate-200 text-slate-500 border border-slate-300 inline-block px-3 py-1 rounded-full text-xs font-bold shadow-sm whitespace-nowrap">Registration Closed</span>';
                                imgContainer.appendChild(newBadge);
                            }
                        }

                        // 2. Update Button (Disable visually)
                        const actionBtn = card.querySelector('.action-btn');
                        if (actionBtn) {
                            actionBtn.classList.remove('bg-purple-600', 'hover:bg-purple-700');
                            actionBtn.classList.add('bg-gray-400', 'hover:bg-gray-500');
                            actionBtn.textContent = 'Registration Closed';
                        }
                    }
                }
            }
            
            if (!isHidden) visibleCount++;
        });

        // UPDATE "SHOWING X EVENTS" TEXT
        const countContainer = document.querySelector('#results-count-container p');
        if (countContainer) {
            // Capitalize first letter
            const statusText = statusFilter.charAt(0).toUpperCase() + statusFilter.slice(1);
            countContainer.innerHTML = `Showing ${visibleCount} <strong>${statusText}</strong> events`;
        }

        // SHOW EMPTY PLACEHOLDER IF ALL HIDDEN
        const mainGrid = document.getElementById('main-events-grid');
        const emptyPlaceholder = document.getElementById('js-empty-placeholder');
        
        // If grid exists, has children (PHP loaded them), but JS hid them all (visibleCount is 0)
        if (mainGrid && mainGrid.children.length > 0 && visibleCount === 0) {
            mainGrid.style.display = 'none'; // Hide grid gap spacing
            if (emptyPlaceholder) emptyPlaceholder.style.display = 'block';
        }
    });

    let searchTimeout;
    const searchInput = document.querySelector('input[name="search"]');
    
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                this.form.submit();
            }, 500);
        });
    }
</script>

<?php 
$conn->close();
include 'footer.php'; 
?>