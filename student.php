<?php
session_start();

// معالج تسجيل الخروج
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $_SESSION = array();
    session_destroy();
    header('Location: student.php');
    exit;
}

// دالة للتحقق من بيانات تسجيل الدخول بمرونة
function authenticateUser($username, $password, $grade) {
    $databases = [];
    switch ($grade) {
        case '1':
            $databases = [
                'data/1/L/database.sqlite',
                'data/1/S/database.sqlite'
            ];
            break;
        case '2':
            $databases = [
                'data/2/L/LANG/database.sqlite',
                'data/2/L/PH&L/database.sqlite',
                'data/2/S/SC/database.sqlite',
                'data/2/S/TECH/database.sqlite',
                'data/2/S/ECO/database.sqlite',
                'data/2/S/MAT/database.sqlite'
            ];
            break;
        case '3':
            $databases = [
                'data/3/L/LANG/database.sqlite',
                'data/3/L/PH&L/database.sqlite',
                'data/3/S/SC/database.sqlite',
                'data/3/S/MAT/database.sqlite',
                'data/3/S/TECH/database.sqlite',
                'data/3/S/ECO/database.sqlite'
            ];
            break;
    }
    
    $username_password_combo = $username . ':' . $password;
    
    foreach ($databases as $dbPath) {
        if (!file_exists($dbPath)) {
            continue;
        }

        try {
            $pdo = new PDO("sqlite:" . $dbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            // البحث ديناميكيًا عن جميع جداول المستخدم
            $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);

            foreach ($tables as $table) {
                // تجاهل جداول النظام
                if (str_starts_with($table, 'sqlite_')) {
                    continue;
                }
                
                $sql = "SELECT student_id, full_name, class_name FROM " . $table . " WHERE username_password = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$username_password_combo]);
                $student = $stmt->fetch();
                
                if ($student) {
                    return ['student' => $student, 'db_path' => $dbPath, 'table_name' => $table];
                }
            }
        } catch (PDOException $e) {
            continue;
        }
    }
    
    return false;
}

// دالة لحساب عدد الغيابات من حقل absence_details باستخدام الفاصل |
function calculateAbsenceCount($absence_details) {
    if (empty($absence_details) || trim($absence_details) === '') {
        return 0;
    }
    
    // فصل النص باستخدام الفاصل |
    $absences = explode('|', trim($absence_details));
    
    // إزالة أي عناصر فارغة قد تنتج عن الفاصل
    $absences = array_filter($absences, function($absence) {
        return !empty(trim($absence));
    });
    
    return count($absences);
}

// متغيرات للرسائل والأخطاء
$error_message = '';
$show_dashboard = false;
$student_data = null;

// معالجة تسجيل الدخول
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_SESSION['student_id'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $grade = $_POST['grade'] ?? '';

    if (!empty($username) && !empty($password) && !empty($grade)) {
        $auth_result = authenticateUser($username, $password, $grade);
        
        if ($auth_result) {
            $_SESSION['student_id'] = $auth_result['student']['student_id'];
            $_SESSION['full_name'] = $auth_result['student']['full_name'];
            $_SESSION['class_name'] = $auth_result['student']['class_name'];
            $_SESSION['db_path'] = $auth_result['db_path'];
            $_SESSION['table_name'] = $auth_result['table_name'];
            $_SESSION['grade'] = $grade;
            
            header('Location: student.php');
            exit;
        } else {
            $error_message = 'اسم المستخدم أو كلمة المرور غير صحيحة أو الشعبة غير مطابقة.';
        }
    } else {
        $error_message = 'الرجاء إدخال جميع البيانات المطلوبة.';
    }
}

// إذا كان المستخدم مسجل الدخول بالفعل، اعرض لوحة التحكم
if (isset($_SESSION['student_id'])) {
    $show_dashboard = true;
    try {
        $pdo = new PDO("sqlite:" . $_SESSION['db_path']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        
        $table = $_SESSION['table_name'];
        if (str_starts_with($table, 'sqlite_')) {
            throw new Exception("اسم جدول غير صالح.");
        }

        $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE student_id = ?");
        $stmt->execute([$_SESSION['student_id']]);
        $student_data = $stmt->fetch();

        if (!$student_data) {
             throw new Exception("لم يتم العثور على بيانات التلميذ.");
        }

    } catch (Exception $e) {
        $error_message = 'خطأ في جلب بيانات التلميذ: ' . $e->getMessage();
        $show_dashboard = false;
    }
}

// إعداد بيانات لوحة التحكم
if ($show_dashboard && $student_data) {
    $full_name = $_SESSION['full_name'];
    $class_name = $_SESSION['class_name'];
    $display_class_name = $class_name;
    
    $name_parts = explode(' ', $full_name, 2);
    $first_name = $name_parts[0];
    $last_name = $name_parts[1] ?? '';
    
    // تعديل معالجة الملاحظات لإزالة الفواصل المتتالية والنصوص الفارغة
    $notes_string = $student_data['note'] ?? '';
    $notes_array = array_filter(
        array_map(
            'trim',
            explode('،', $notes_string)
        ),
        'strlen'
    );
    
    if (empty($notes_array)) {
        $notes_array = ['لا توجد ملاحظات'];
    }
    
    // حساب عدد الغيابات من حقل absence_details
    $absence_count = calculateAbsenceCount($student_data['absence_details'] ?? '');
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $show_dashboard ? 'لوحة تحكم التلميذ' : 'تسجيل الدخول'; ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700&display=swap');

        :root {
            --primary-color: #336699;
            --secondary-color: #f0f4f7;
            --text-color: #343a40;
            --light-bg: #e9ecef;
            --table-header-bg: #336699;
            --table-header-text: #fff;
            --border-color: #dee2e6;
            --success-color: #28a745;
            --info-color: #5bc0de;
            --warning-color: #ffc107;
            --logout-btn-bg: #c84358;
        }

        body {
            font-family: 'Tajawal', sans-serif;
            background-color: #f4f4f4;
            color: var(--text-color);
            text-align: right;
            line-height: 1.6;
            padding: 20px;
            margin: 0;
            min-height: 100vh;
        }

        /* إزالة جميع التأثيرات المزعجة من الأزرار */
        button, .grade-btn, .login-btn, .logout-btn, a.logout-btn {
            -webkit-tap-highlight-color: transparent !important;
            -webkit-touch-callout: none !important;
            -webkit-user-select: none !important;
            -moz-user-select: none !important;
            -ms-user-select: none !important;
            user-select: none !important;
            outline: none !important;
        }

        button:focus, .grade-btn:focus, .login-btn:focus, .logout-btn:focus, a.logout-btn:focus {
            outline: none !important;
            box-shadow: none !important;
        }

        button:active, .grade-btn:active, .login-btn:active, .logout-btn:active, a.logout-btn:active {
            outline: none !important;
            box-shadow: none !important;
            transform: none !important;
            background-color: inherit !important;
        }

        <?php if (!$show_dashboard): ?>
        /* تنسيقات صفحة تسجيل الدخول */
        .login-container {
            max-width: 450px;
            margin: 5% auto;
            background: rgba(255, 255, 255, 0.95);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-header h2 {
            color: var(--primary-color);
            font-size: 2.2em;
            margin: 0 0 10px 0;
        }

        .login-header p {
            color: #6c757d;
            font-size: 1.1em;
        }

        .grade-selection {
            margin-bottom: 25px;
        }

        .grade-selection label {
            display: block;
            margin-bottom: 10px;
            color: var(--primary-color);
            font-weight: bold;
            font-size: 1.1em;
        }

        .grade-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .grade-btn {
            padding: 12px 20px;
            border: 2px solid var(--primary-color);
            background: transparent;
            color: var(--primary-color);
            border-radius: 25px;
            cursor: pointer;
            font-size: 1em;
            font-weight: bold;
        }

        .grade-btn:hover, .grade-btn.active {
            background: var(--primary-color);
            color: white;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--primary-color);
            font-weight: bold;
        }

        .form-group input {
            width: 100%;
            padding: 15px;
            border: 2px solid #ddd;
            border-radius: 10px;
            box-sizing: border-box;
            font-size: 1em;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .login-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(45deg, var(--primary-color), #4a90e2);
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 1.1em;
            font-weight: bold;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            border: 1px solid #f5c6cb;
        }
        <?php endif; ?>

        <?php if ($show_dashboard): ?>
        /* تنسيقات لوحة التحكم */
        .container {
            max-width: 1300px;
            margin: 0 auto;
            background-color: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 20px;
            margin-bottom: 30px;
            flex-direction: column; /* جعل العناصر تتراص عمودياً */
            gap: 10px; /* مسافة بين العناصر */
        }

        .header h1 {
            color: var(--primary-color);
            font-size: 2.2em;
            margin: 0;
        }

        .header .sub-info {
            font-size: 1.1em;
            color: #6c757d;
            margin-top: 5px;
        }
        
        .logout-btn {
            background-color: var(--logout-btn-bg);
            color: white;
            border: none;
            padding: 8px 16px; /* تم تصغير الزر هنا */
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            font-size: 0.9em; /* تم تصغير حجم الخط */
            white-space: nowrap;
            display: inline-block; /* لضمان أن الزر يأخذ حجمه الطبيعي */
        }

        .logout-btn:hover {
            background-color: #a72d42;
        }

        h2 {
            color: var(--primary-color);
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 10px;
            margin-top: 40px;
            font-size: 1.7em;
        }

        .info-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .card {
            background-color: var(--secondary-color);
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border-left: 4px solid var(--primary-color);
        }

        .card strong {
            display: block;
            margin-bottom: 5px;
            color: var(--primary-color);
        }

        .table-responsive {
            width: 100%;
            overflow-x: auto;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .grades-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }

        .grades-table th, .grades-table td {
            border: 1px solid var(--border-color);
            padding: 15px;
            text-align: center;
        }

        .grades-table thead th {
            background-color: var(--table-header-bg);
            color: var(--table-header-text);
            font-weight: 700;
            white-space: nowrap;
        }

        .grades-table tbody tr:nth-child(odd) {
            background-color: #f9fbfd;
        }

        .grades-table tbody tr:hover {
            background-color: #e9f0ff;
            transition: background-color 0.3s;
        }

        .grades-table td.subject-name {
            text-align: right;
            font-weight: bold;
            color: var(--primary-color);
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background-color: #fefefe;
            padding: 30px;
            border-radius: 10px;
            width: 80%;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            position: relative;
            max-height: 80vh;
            display: flex;
            flex-direction: column;
            margin: 10vh auto;
        }

        .close-btn {
            color: #aaa;
            float: left;
            font-size: 28px;
            font-weight: bold;
            position: absolute;
            top: 10px;
            left: 20px;
        }

        .close-btn:hover, .close-btn:focus {
            color: #000;
            text-decoration: none;
            cursor: pointer;
        }
        
        .note-list {
            list-style-type: none;
            padding: 0;
            margin: 0;
            overflow-y: auto;
            flex-grow: 1;
        }

        .note-list li {
            background: var(--light-bg);
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 10px;
            border-right: 4px solid var(--primary-color);
            text-align: right;
        }
        <?php endif; ?>

        @media (max-width: 768px) {
            .login-container {
                margin: 2% auto;
                padding: 20px;
            }
            
            .grade-buttons {
                flex-direction: column;
            }
            
            .grade-btn {
                width: 100%;
            }
            
            .header {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>

<?php if (!$show_dashboard): ?>
    <div class="login-container">
        <div class="login-header">
            <h2>تسجيل الدخول</h2>
            <p>منطقة تسجيل الدخول للتلاميذ</p>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <form method="POST" action="student.php">
            <div class="grade-selection">
                <label>اختر المستوى الدراسي:</label>
                <div class="grade-buttons">
                    <button type="button" class="grade-btn" data-grade="1">أول ثانوي</button>
                    <button type="button" class="grade-btn" data-grade="2">ثاني ثانوي</button>
                    <button type="button" class="grade-btn" data-grade="3">ثالث ثانوي</button>
                </div>
                <input type="hidden" name="grade" id="selectedGrade" required>
            </div>

            <div class="form-group">
                <label for="username">اسم المستخدم</label>
                <input type="text" id="username" name="username" required>
            </div>

            <div class="form-group">
                <label for="password">كلمة المرور</label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit" class="login-btn">دخول</button>
        </form>
    </div>

    <script>
        document.querySelectorAll('.grade-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.grade-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                document.getElementById('selectedGrade').value = this.dataset.grade;
            });
        });
        document.querySelector('form').addEventListener('submit', function(e) {
            if (!document.getElementById('selectedGrade').value) {
                e.preventDefault();
                alert('الرجاء اختيار المستوى الدراسي');
            }
        });
    </script>

<?php else: ?>
    <div class="container">
        <div class="header">
            <div>
                <h1>أهلاً بك، <?php echo htmlspecialchars($first_name); ?></h1>
                <p class="sub-info">الصف: <?php echo htmlspecialchars($display_class_name); ?></p>
            </div>
            <a href="student.php?action=logout" class="logout-btn">تسجيل خروج</a>
        </div>
        
        <h2>بيانات التلميذ</h2>
        <div class="info-cards">
            <div class="card"><strong>الاسم:</strong> <?php echo htmlspecialchars($first_name); ?></div>
            <div class="card"><strong>اللقب:</strong> <?php echo htmlspecialchars($last_name); ?></div>
            <div class="card"><strong>عدد الغيابات:</strong> <?php echo htmlspecialchars($absence_count); ?></div>
            <div class="card">
                <strong>الملاحظات:</strong>
                <?php if (!empty($notes_array) && trim($notes_array[0]) != 'لا توجد ملاحظات'): ?>
                    <button onclick="openModal()" class="logout-btn" style="background-color: var(--primary-color);">عرض الملاحظات</button>
                <?php else: ?>
                    <span>لا توجد ملاحظات حاليًا.</span>
                <?php endif; ?>
            </div>
        </div>

        <h2>الدرجات</h2>
        <div class="table-responsive">
            <table class="grades-table">
                <thead>
                    <tr>
                        <th rowspan="2">المادة</th>
                        <th colspan="2">الفصل الأول</th>
                        <th colspan="2">الفصل الثاني</th>
                        <th colspan="2">الفصل الثالث</th>
                    </tr>
                    <tr><th>فرض</th><th>اختبار</th><th>فرض</th><th>اختبار</th><th>فرض</th><th>اختبار</th></tr>
                </thead>
                <tbody>
                    <?php
                    $subject_map = [
                        'arabic_language' => 'اللغة العربية',
                        'french_language' => 'اللغة الفرنسية',
                        'english_language' => 'اللغة الإنجليزية',
                        'spanish' => 'اللغة الإسبانية',
                        'german' => 'اللغة الألمانية',
                        'philosophy' => 'الفلسفة',
                        'history_geography' => 'التاريخ والجغرافيا',
                        'islamic_sciences' => 'العلوم الإسلامية',
                        'mathematics' => 'الرياضيات',
                        'natural_life_sciences' => 'علوم الطبيعة والحياة',
                        'natural_sciences' => 'العلوم الطبيعية',
                        'physics_technology_sciences' => 'الفيزياء والتكنولوجيا',
                        'physics_sciences' => 'الفيزياء',
                        'economics_management' => 'اقتصاد وتسيير',
                        'economics' => 'الاقتصاد',
                        'accounting_management' => 'التسيير المحاسبي والمالي',
                        'law' => 'القانون',
                        'technology' => 'تكنولوجيا',
                        'computer_science' => 'الإعلام الآلي',
                        'physical_education' => 'التربية البدنية',
                        'amazigh_language' => 'اللغة الأمازيغية',
                    ];
                    
                    foreach ($subject_map as $key => $label) {
                        if (array_key_exists($key . '_term1', $student_data)) {
                            echo '<tr>';
                            echo '<td class="subject-name">' . htmlspecialchars($label) . '</td>';

                            for ($term = 1; $term <= 3; $term++) {
                                $term_key = $key . '_term' . $term;
                                if (isset($student_data[$term_key]) && !empty($student_data[$term_key])) {
                                    $grades = explode(',', $student_data[$term_key]);
                                    echo '<td>' . (isset($grades[0]) ? htmlspecialchars(trim($grades[0])) : '-') . '</td>';
                                    echo '<td>' . (isset($grades[1]) ? htmlspecialchars(trim($grades[1])) : '-') . '</td>';
                                } else {
                                    echo '<td>-</td><td>-</td>';
                                }
                            }
                            echo '</tr>';
                        }
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="notesModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeModal()">&times;</span>
            <h2>ملاحظات الإدارة والأساتذة</h2>
            <ul class="note-list">
                <?php if (!empty($notes_array) && trim($notes_array[0]) != 'لا توجد ملاحظات'): ?>
                    <?php foreach ($notes_array as $note) : ?>
                        <li><?php echo htmlspecialchars(trim($note)); ?></li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li>لا توجد ملاحظات حاليًا.</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>

    <script>
        function openModal() { document.getElementById('notesModal').style.display = 'flex'; }
        function closeModal() { document.getElementById('notesModal').style.display = 'none'; }
        window.onclick = function(event) {
            if (event.target == document.getElementById('notesModal')) { closeModal(); }
        }
    </script>

<?php endif; ?>

</body>
</html>
