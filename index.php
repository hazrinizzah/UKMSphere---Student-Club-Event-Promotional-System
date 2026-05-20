<?php
include 'config.php';

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['login'])) {
        $email = trim($_POST['email']);
        $password = $_POST['password'];

        $sql = "SELECT id, username, password, user_type FROM users_abelities WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_type'] = $user['user_type'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $email;

                // Fetch extra details
                $user_type = $user['user_type'];
                if ($user_type == 'student') {
                    $q = "SELECT full_name, matric_number, phone_number FROM students_abelities WHERE user_id = ?";
                } elseif ($user_type == 'club') {
                    $q = "SELECT club_name, club_id FROM clubs_abelities WHERE user_id = ?";
                } elseif ($user_type == 'hep') {
                    $q = "SELECT full_name, work_id FROM hep_abelities WHERE user_id = ?";
                }
                
                if (isset($q)) {
                    $stmt_extra = $conn->prepare($q);
                    $stmt_extra->bind_param("i", $user['id']);
                    $stmt_extra->execute();
                    $res = $stmt_extra->get_result()->fetch_assoc();
                    foreach ($res as $key => $val) {
                        $_SESSION[$key] = $val;
                    }
                }
                
                header("Location: dashboard.php");
                exit();
            } else {
                $login_error = "Invalid password!";
            }
        } else {
            $login_error = "User not found!";
        }
        $stmt->close();
    } elseif (isset($_POST['register'])) {
        $user_type = $_POST['user_type'];
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        // Fix for older PHP versions
        $raw_name = isset($_POST['full_name']) ? $_POST['full_name'] : (isset($_POST['club_name']) ? $_POST['club_name'] : '');
        $username = strtolower(str_replace(' ', '_', $raw_name));
        
        $check_sql = "SELECT id FROM users_abelities WHERE email = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            $register_error = "Email already exists!";
        } else {
            if ($user_type == 'student' && !preg_match('/^A/i', trim($_POST['matric_number']))) $register_error = "Matric number must start with 'A'!";
            elseif ($user_type == 'club' && !preg_match('/^C/i', trim($_POST['club_id']))) $register_error = "Club ID must start with 'C'!";
            elseif ($user_type == 'hep' && !preg_match('/^K/i', trim($_POST['work_id']))) $register_error = "Work ID must start with 'K'!";

            if (!isset($register_error)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $user_sql = "INSERT INTO users_abelities (username, password, email, user_type) VALUES (?, ?, ?, ?)";
                $user_stmt = $conn->prepare($user_sql);
                $user_stmt->bind_param("ssss", $username, $hashed_password, $email, $user_type);
                
                if ($user_stmt->execute()) {
                    $user_id = $user_stmt->insert_id;
                    if ($user_type == 'student') {
                        $sql = "INSERT INTO students_abelities (user_id, full_name, matric_number, phone_number) VALUES (?, ?, ?, ?)";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("isss", $user_id, $_POST['full_name'], $_POST['matric_number'], $_POST['phone_number']);
                        $stmt->execute();
                    } elseif ($user_type == 'club') {
                        $sql = "INSERT INTO clubs_abelities (user_id, club_name, club_id) VALUES (?, ?, ?)";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("iss", $user_id, $_POST['club_name'], $_POST['club_id']);
                        $stmt->execute();
                    } elseif ($user_type == 'hep') {
                        $sql = "INSERT INTO hep_abelities (user_id, full_name, work_id) VALUES (?, ?, ?)";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("iss", $user_id, $_POST['full_name'], $_POST['work_id']);
                        $stmt->execute();
                    }
                    $register_success = "Registration successful! You can now login.";
                } else {
                    $register_error = "Error: " . $user_stmt->error;
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UKMSphere - Login</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            margin: 0;
            background-color: #000;
            min-height: 100vh;
            overflow-y: auto; 
        }

        :root {
            --ukm-blue: #004a98;
        }

        /* 1. LAYERS */
        #matrixCanvas { position: fixed; top: 0; left: 0; z-index: 0; transition: opacity 1s ease; }
        
        /* 2. STATIC BACKGROUND (From your original code) */
        #staticBackground {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            /* Original Gradient */
            background: linear-gradient(135deg, var(--ukm-blue) 0%, #002b5e 100%);
            opacity: 0; z-index: 1; transition: opacity 1s ease;
            overflow: hidden;
        }
        #staticBackground::before {
            content: ''; position: absolute; top: -100px; right: -100px; width: 300px; height: 300px;
            background: rgba(255, 255, 255, 0.1); border-radius: 50%;
        }
        #staticBackground::after {
            content: ''; position: absolute; bottom: -50px; left: -50px; width: 200px; height: 200px;
            background: rgba(255, 255, 255, 0.05); border-radius: 50%;
        }

        /* 3. HEADER WRAPPER (Logo + Text) */
        #brandingWrapper {
            position: fixed;
            top: 0; 
            left: 0; 
            width: 100%;
            height: 100vh; /* Start full screen */
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 50;
            transition: all 1s ease-in-out; 
            padding-top: 0;
        }

        /* 4. MAIN FORM CONTAINER */
        #formContainer {
            position: relative;
            z-index: 20;
            opacity: 0;
            transform: translateY(50px);
            transition: all 1s ease-out;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding-top: 160px; /* Space reserved for the header */
        }

        /* --- ANIMATION STATES --- */

        .dock-to-header {
            height: 160px !important; 
            justify-content: flex-start !important; 
            padding-top: 30px !important; 
            /* Subtle overlay for readability */
            background: linear-gradient(to bottom, rgba(0,0,0,0.3), transparent); 
        }

        .dock-to-header img {
            height: 60px !important;
            width: auto !important;
            margin-bottom: 5px !important;
        }

        .dock-to-header h1 {
            font-size: 1.8rem !important;
        }
        
        .dock-to-header p {
            opacity: 1 !important;
            height: auto !important;
            margin-top: 0 !important;
        }

        .show-form {
            opacity: 1 !important;
            transform: translateY(0) !important;
        }

        .bg-static-active { opacity: 1 !important; }
        .matrix-fade { opacity: 0; }
        
        /* TEXT STYLING: UKM Blue with White Stroke */
        .ukm-title {
            color: var(--ukm-blue);
            font-weight: 800;
            text-shadow: 
                -2px -2px 0 #fff,  
                 2px -2px 0 #fff,
                -2px  2px 0 #fff,
                 2px  2px 0 #fff,
                 0 0 20px rgba(255, 255, 255, 0.7);
            transition: all 1s ease;
        }

        #brandingLogo {
            height: 140px;
            width: auto;
            margin-bottom: 20px;
            filter: drop-shadow(0 0 15px rgba(255,255,255,0.2));
            transition: all 1s ease;
        }
        
        #brandingSubtitle {
            opacity: 0;
            height: 0;
            overflow: hidden;
            transition: opacity 1s ease;
            color: #dbeafe; /* Light blue text for visibility on dark BG */
        }

        /* Card Styles */
        .form-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            width: 100%; max-width: 440px;
        }

        /* Buttons & Inputs */
        .btn-ukm { background-color: var(--ukm-blue); color: white; transition: 0.3s; }
        .btn-ukm:hover { background-color: #003370; transform: translateY(-1px); }
        .input-ukm:focus { border-color: var(--ukm-blue); outline: none; ring: 2px rgba(0, 74, 152, 0.1); }
        .account-btn.active { border-color: var(--ukm-blue); background: #f0f7ff; color: var(--ukm-blue); font-weight: 700; }
        .hidden { display: none; }
    </style>
</head>
<body>

    <canvas id="matrixCanvas"></canvas>

    <div id="staticBackground"></div>

    <div id="brandingWrapper">
        <img id="brandingLogo" src="images/UKM.png" alt="UKM Logo">
        <h1 class="text-5xl font-extrabold ukm-title tracking-wide text-center">UKMSphere</h1>
        <p id="brandingSubtitle" class="mt-1 font-bold text-xs tracking-widest text-center">
            Student Event Promotion Hub & Resource Ecosystem
        </p>
    </div>

    <div id="formContainer">
        
        <?php if (isset($login_error)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded shadow-lg w-full max-w-md"><p class="font-bold">Error</p><p><?php echo $login_error; ?></p></div>
        <?php endif; ?>
        <?php if (isset($register_error)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded shadow-lg w-full max-w-md"><p class="font-bold">Error</p><p><?php echo $register_error; ?></p></div>
        <?php endif; ?>
        <?php if (isset($register_success)): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded shadow-lg w-full max-w-md"><p class="font-bold">Success</p><p><?php echo $register_success; ?></p></div>
        <?php endif; ?>

        <div id="loginForm" class="form-card p-8">
            <div class="text-center mb-6">
                <h2 class="text-xl font-bold text-gray-900 mb-1">Welcome Back</h2>
                <p class="text-gray-500 text-sm">Sign in to continue</p>
            </div>
            <form method="POST" action="">
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Email Address</label>
                        <input name="email" type="email" required class="w-full px-4 py-3 border border-gray-300 rounded-lg input-ukm bg-gray-50" placeholder="Enter your email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Password</label>
                        <input name="password" type="password" required class="w-full px-4 py-3 border border-gray-300 rounded-lg input-ukm bg-gray-50" placeholder="Enter your password">
                    </div>
                </div>
                <button type="submit" name="login" class="btn-ukm w-full py-3 px-4 rounded-lg font-bold text-lg mt-6 shadow-lg">Sign In</button>
            </form>
            <div class="mt-6 text-center pt-4 border-t border-gray-100">
                <p class="text-gray-600 text-sm">New to UKMSphere? <button type="button" onclick="showRegister()" class="text-ukm-blue font-bold hover:underline">Create an account</button></p>
            </div>
        </div>

        <div id="registerForm" class="form-card p-8 hidden">
            <div class="text-center mb-4">
                <h2 class="text-lg font-bold text-gray-800">Create Account</h2>
                <p class="text-gray-500 text-xs">Join the community</p>
            </div>
            <div class="grid grid-cols-3 gap-2 mb-4">
                <button type="button" class="account-btn active py-2 px-1 rounded text-xs font-bold uppercase tracking-wide" onclick="selectAccountType('student')" id="studentBtn">Student</button>
                <button type="button" class="account-btn py-2 px-1 rounded text-xs font-bold uppercase tracking-wide" onclick="selectAccountType('club')" id="clubBtn">Club</button>
                <button type="button" class="account-btn py-2 px-1 rounded text-xs font-bold uppercase tracking-wide" onclick="selectAccountType('hep')" id="hepBtn">HEP</button>
            </div>
            <form method="POST" action="" id="registrationForm">
                <input type="hidden" name="user_type" id="userType" value="student">
                <div id="formFields" class="space-y-3"></div>
                <button type="submit" name="register" class="btn-ukm w-full py-3 px-4 rounded-lg font-bold text-lg mt-6 shadow-lg">Sign Up</button>
            </form>
            <div class="mt-4 text-center pt-4 border-t border-gray-100">
                <p class="text-gray-600 text-sm">Already have an account? <button type="button" onclick="showLogin()" class="text-ukm-blue font-bold hover:underline">Log in</button></p>
            </div>
        </div>
    </div>

    <?php $mode = isset($_GET['mode']) ? $_GET['mode'] : 'login'; ?>

    <script>
        // --- 1. MATRIX ANIMATION ---
        const canvas = document.getElementById('matrixCanvas');
        const ctx = canvas.getContext('2d');
        let matrixInterval;

        function resizeCanvas() { canvas.width = window.innerWidth; canvas.height = window.innerHeight; }
        window.addEventListener('resize', resizeCanvas);
        resizeCanvas();

        const letters = "01010101UKMSPHERE";
        const fontSize = 16;
        const columns = canvas.width / fontSize;
        const drops = [];
        for (let x = 0; x < columns; x++) drops[x] = 1;

        function drawMatrix() {
            ctx.fillStyle = "rgba(0, 0, 0, 0.05)";
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.fillStyle = "#004a98"; 
            ctx.font = fontSize + "px 'Montserrat'";
            for (let i = 0; i < drops.length; i++) {
                const text = letters.charAt(Math.floor(Math.random() * letters.length));
                ctx.fillText(text, i * fontSize, drops[i] * fontSize);
                if (drops[i] * fontSize > canvas.height && Math.random() > 0.975) drops[i] = 0;
                drops[i]++;
            }
        }
        matrixInterval = setInterval(drawMatrix, 33);

        // --- 2. SEQUENCE LOGIC ---
        document.addEventListener("DOMContentLoaded", () => {
            const hasMessages = <?php echo (isset($login_error) || isset($register_error) || isset($register_success)) ? 'true' : 'false'; ?>;
            const delay = hasMessages ? 100 : 2500; 

            setTimeout(() => {
                // Fade Matrix & Show Static BG
                document.getElementById('matrixCanvas').classList.add('matrix-fade');
                clearInterval(matrixInterval); 
                document.getElementById('staticBackground').classList.add('bg-static-active');
                
                // Dock Logo to Top
                document.getElementById('brandingWrapper').classList.add('dock-to-header');

                // Show Form (Slide Up)
                setTimeout(() => {
                    document.getElementById('formContainer').classList.add('show-form');
                }, 600); 

            }, delay);
        });

        // --- 3. FORM LOGIC ---
        let currentAccountType = 'student';
        function selectAccountType(type) {
            currentAccountType = type;
            document.getElementById('userType').value = type;
            document.querySelectorAll('.account-btn').forEach(btn => btn.classList.remove('active'));
            document.getElementById(type + 'Btn').classList.add('active');
            updateFormFields();
        }

        function updateFormFields() {
            const formFields = document.getElementById('formFields');
            let fieldsHTML = '';
            const commonFields = `<div><label class="block text-xs font-bold text-gray-700 uppercase mb-1">Email</label><input name="email" type="email" required class="w-full px-4 py-2 border border-gray-300 rounded-lg input-ukm bg-gray-50" placeholder="Enter your email"></div><div><label class="block text-xs font-bold text-gray-700 uppercase mb-1">Password</label><input name="password" type="password" required class="w-full px-4 py-2 border border-gray-300 rounded-lg input-ukm bg-gray-50" placeholder="Create a password (min. 6 chars)" minlength="6"></div>`;
            
            if (currentAccountType === 'student') {
                fieldsHTML = `<div><label class="block text-xs font-bold text-gray-700 uppercase mb-1">Full Name</label><input name="full_name" type="text" required class="w-full px-4 py-2 border border-gray-300 rounded-lg input-ukm bg-gray-50" placeholder="Enter full name"></div><div><label class="block text-xs font-bold text-gray-700 uppercase mb-1">Matric Number</label><input name="matric_number" type="text" required class="w-full px-4 py-2 border border-gray-300 rounded-lg input-ukm bg-gray-50" placeholder="e.g., A123456" pattern="A.*" title="Matric number must start with 'A'"></div>${commonFields}<div><label class="block text-xs font-bold text-gray-700 uppercase mb-1">Phone Number</label><input name="phone_number" type="tel" required class="w-full px-4 py-2 border border-gray-300 rounded-lg input-ukm bg-gray-50" placeholder="Enter phone number"></div>`;
            } else if (currentAccountType === 'club') {
                fieldsHTML = `<div><label class="block text-xs font-bold text-gray-700 uppercase mb-1">Club Full Name</label><input name="club_name" type="text" required class="w-full px-4 py-2 border border-gray-300 rounded-lg input-ukm bg-gray-50" placeholder="Enter club name"></div><div><label class="block text-xs font-bold text-gray-700 uppercase mb-1">Club Official ID</label><input name="club_id" type="text" required class="w-full px-4 py-2 border border-gray-300 rounded-lg input-ukm bg-gray-50" placeholder="e.g., C123456" pattern="C.*" title="Club ID must start with 'C'"></div>${commonFields}`;
            } else if (currentAccountType === 'hep') {
                fieldsHTML = `<div><label class="block text-xs font-bold text-gray-700 uppercase mb-1">Full Name</label><input name="full_name" type="text" required class="w-full px-4 py-2 border border-gray-300 rounded-lg input-ukm bg-gray-50" placeholder="Enter full name"></div><div><label class="block text-xs font-bold text-gray-700 uppercase mb-1">Work ID</label><input name="work_id" type="text" required class="w-full px-4 py-2 border border-gray-300 rounded-lg input-ukm bg-gray-50" placeholder="e.g., K123456" pattern="K.*" title="Work ID must start with 'K'"></div>${commonFields}`;
            }
            formFields.innerHTML = fieldsHTML;
        }

        function showRegister() { document.getElementById('loginForm').classList.add('hidden'); document.getElementById('registerForm').classList.remove('hidden'); updateFormFields(); }
        function showLogin() { document.getElementById('registerForm').classList.add('hidden'); document.getElementById('loginForm').classList.remove('hidden'); }

        document.addEventListener('DOMContentLoaded', function() {
            updateFormFields();
            if ("<?php echo $mode; ?>" === 'register') showRegister();
        });
    </script>
</body>
</html>