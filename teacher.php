<?php
session_start();

// --- AJAX HANDLER FOR FETCHING STUDENTS AND GRADES, AND REGISTERING ABSENCE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');

    // إعداد خرائط التحويل (كما في الكود السابق)
    $year_map = ['اول ثانوي' => '1', 'ثاني ثانوي' => '2', 'ثالث ثانوي' => '3'];
    $branch_map = ['علمي' => 'S', 'ادبي' => 'L'];
    $part_map = [
        'لغات أجنبية' => 'LANG',
        'آداب وفلسفة' => 'PH&L',
        'تقني رياضي' => 'TECH',
        'العلوم التجريبية' => 'SC',
        'الرياضيات' => 'MAT',
        'تسيير واقتصاد' => 'ECO'
    ];
    $table_map = [
        'لغات أجنبية' => 'foreign_languages_students',
        'آداب وفلسفة' => 'arts_economics_students',
        'تقني رياضي' => 'technical_mathematics_students',
        'العلوم التجريبية' => 'experimental_sciences_students',
        'الرياضيات' => 'mathematics_students',
        'تسيير واقتصاد' => 'management_economics_students',
        'علمي' => 'scientific_students',
        'ادبي' => 'literary_students'
    ];
    // خريطة المواد
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

    // دالة مساعدة لجلب البيانات الأساسية (مسار DB واسم الجدول واسم الفصل)
    function getDbInfo($year, $branch, $part, $classNum, $year_map, $branch_map, $part_map, $table_map) {
        $yearNum = $year_map[$year] ?? null;
        $branchCode = $branch_map[$branch] ?? null;

        if (!$yearNum || !$branchCode) return ['error' => 'بيانات السنة أو الفرع غير صالحة.'];

        $dbPath = "data/";
        $tableName = null;
        $className = "";

        if ($yearNum === '1') {
            $tableName = $table_map[$branch] ?? null;
            $dbPath .= "{$yearNum}/{$branchCode}/database.sqlite";
            $className = "{$yearNum} {$branch} {$classNum}";
        } else {
            $partCode = $part_map[$part] ?? null;
            if (!$partCode) return ['error' => 'بيانات الشعبة غير صالحة.'];
            $tableName = $table_map[$part] ?? null;
            $dbPath .= "{$yearNum}/{$branchCode}/{$partCode}/database.sqlite";
            $className = "{$yearNum} {$part} {$classNum}";
        }

        if (!$tableName) return ['error' => 'لم يتم العثور على اسم جدول مناسب.'];

        return ['dbPath' => $dbPath, 'tableName' => $tableName, 'className' => $className];
    }
    
    // --- 1. جلب قائمة التلاميذ (الإجراء الأصلي) ---
    if ($_POST['action'] === 'get_students') {
        header('Content-Type: text/html; charset=utf-8');
        $dbInfo = getDbInfo($_POST['year'], $_POST['branch'], $_POST['part'], $_POST['classNum'], $year_map, $branch_map, $part_map, $table_map);
        if (isset($dbInfo['error'])) {
            echo '<p class="modal-error">' . htmlspecialchars($dbInfo['error']) . '</p>';
            exit;
        }
        
        $dbPath = $dbInfo['dbPath'];
        $tableName = $dbInfo['tableName'];
        $className = $dbInfo['className'];

        if (!file_exists($dbPath)) {
            echo '<h3>قائمة التلاميذ - ' . htmlspecialchars($className) . '</h3>';
            echo '<p class="modal-error">خطأ: لم يتم العثور على قاعدة البيانات.</p>';
            exit;
        }

        try {
            $pdo = new PDO("sqlite:" . $dbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            $stmt = $pdo->prepare("SELECT full_name FROM {$tableName} WHERE class_name = ?");
            $stmt->execute([$className]);
            $students = $stmt->fetchAll();

            echo '<h3>قائمة التلاميذ - ' . htmlspecialchars($className) . '</h3>';
            if ($students) {
                echo '<div class="student-list-container">'; // Containe for scrollable list
                foreach ($students as $student) {
                    echo '<div class="student-row">';
                    echo '<span class="student-info-btn grades-btn" data-student-name="' . htmlspecialchars($student['full_name']) . '">';
                    echo '<span class="student-name">' . htmlspecialchars($student['full_name']) . '</span>';
                    echo '<div class="grades-dropdown-toggle"></div>';
                    echo '</span>';
                    echo '<div class="absence-btn-wrapper">';
                    echo '<span class="divider"></span>';
                    echo '<button type="button" class="absence-btn" data-student-name="' . htmlspecialchars($student['full_name']) . '">غياب</button>';
                    echo '</div>';
                    echo '</div>'; // .student-row
                    echo '<div class="grades-dropdown-container" style="display: none;"></div>';
                }
                echo '</div>'; // .student-list-container
            } else {
                echo '<p class="modal-info">لا يوجد تلاميذ مسجلون في هذا القسم.</p>';
            }

        } catch (PDOException $e) {
            echo '<p class="modal-error">حدث خطأ أثناء الاتصال بقاعدة البيانات: ' . $e->getMessage() . '</p>';
        }

    // --- 2. جلب بيانات الدرجات للطالب المحدد ---
    } elseif ($_POST['action'] === 'get_grades') {
        header('Content-Type: text/html; charset=utf-8');
        $studentName = $_POST['student_name'] ?? '';
        $year = $_POST['year'] ?? '';
        $branch = $_POST['branch'] ?? '';
        $part = $_POST['part'] ?? '';
        $classNum = $_POST['classNum'] ?? '';
        $subjectName = $_POST['subject_name'] ?? '';

        if (empty($studentName)) {
            echo '<p class="modal-error">خطأ: اسم الطالب غير محدد.</p>';
            exit;
        }
        
        $dbInfo = getDbInfo($year, $branch, $part, $classNum, $year_map, $branch_map, $part_map, $table_map);
        if (isset($dbInfo['error'])) {
            echo '<p class="modal-error">' . htmlspecialchars($dbInfo['error']) . '</p>';
            exit;
        }

        $dbPath = $dbInfo['dbPath'];
        $tableName = $dbInfo['tableName'];

        if (!file_exists($dbPath)) {
            echo '<p class="modal-error">خطأ: لم يتم العثور على قاعدة البيانات.</p>';
            exit;
        }

        // Find the database column name for the subject
        $subject_key = array_search($subjectName, $subject_map);
        if (!$subject_key) {
            echo '<p class="modal-error">خطأ: لم يتم العثور على المادة المحددة في قاعدة البيانات.</p>';
            exit;
        }

        try {
            $pdo = new PDO("sqlite:" . $dbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            // Fetch grades and note for the specified subject and student
            $stmt = $pdo->prepare("SELECT {$subject_key}_term1, {$subject_key}_term2, {$subject_key}_term3, note FROM {$tableName} WHERE full_name = ?");
            $stmt->execute([$studentName]);
            $student_data = $stmt->fetch();

            if ($student_data) {
                // عرض جدول الدرجات
                echo '<div class="grades-content">';
                echo '    <div class="table-responsive">';
                echo '        <table class="grades-table">';
                echo '            <thead>';
                echo '                <tr>';
                echo '                    <th>المادة: ' . htmlspecialchars($subjectName) . '</th>';
                echo '                    <th colspan="2">الفصل الأول</th>';
                echo '                    <th colspan="2">الفصل الثاني</th>';
                echo '                    <th colspan="2">الفصل الثالث</th>';
                echo '                </tr>';
                echo '                <tr><th>الدرجات</th><th>فرض</th><th>اختبار</th><th>فرض</th><th>اختبار</th><th>فرض</th><th>اختبار</th></tr>';
                echo '            </thead>';
                echo '            <tbody>';
                
                echo '<tr>';
                echo '<td class="subject-name">' . htmlspecialchars($subjectName) . '</td>';
                for ($term = 1; $term <= 3; $term++) {
                    $term_key = $subject_key . '_term' . $term;
                    if (isset($student_data[$term_key]) && !empty($student_data[$term_key])) {
                        $grades = explode(',', $student_data[$term_key]);
                        echo '<td><input type="text" value="' . (isset($grades[0]) ? htmlspecialchars(trim($grades[0])) : '') . '" class="grade-input" disabled data-term="' . $term . '" data-type="فرض"></td>';
                        echo '<td><input type="text" value="' . (isset($grades[1]) ? htmlspecialchars(trim($grades[1])) : '') . '" class="grade-input" disabled data-term="' . $term . '" data-type="اختبار"></td>';
                    } else {
                        echo '<td><input type="text" value="" class="grade-input" disabled data-term="' . $term . '" data-type="فرض"></td>';
                        echo '<td><input type="text" value="" class="grade-input" disabled data-term="' . $term . '" data-type="اختبار"></td>';
                    }
                }
                echo '</tr>';

                echo '            </tbody>';
                echo '        </table>';
                echo '    </div>';
                echo '    <div class="grade-actions">';
                echo '        <button class="edit-grades-btn">تعديل</button>';
                echo '        <button class="save-grades-btn" style="display: none;">حفظ</button>';
                echo '    </div>';
                
                // إضافة حقل الملاحظة الجديد
                echo '<div class="note-section">';
                echo '    <h4>إضافة ملاحظة للطالب</h4>';
                echo '    <textarea id="note-textarea" rows="4" placeholder="اكتب ملاحظاتك هنا..."></textarea>';
                echo '    <button class="send-note-btn">إرسال الملاحظة</button>';
                echo '</div>';
                
                echo '</div>';

            } else {
                echo '<p class="modal-info">لم يتم العثور على بيانات لهذا الطالب.</p>';
            }
        } catch (PDOException $e) {
            echo '<p class="modal-error">حدث خطأ في جلب البيانات: ' . $e->getMessage() . '</p>';
        }

    // --- 3. تسجيل الغياب للطالب المحدد ---
    } elseif ($_POST['action'] === 'register_absence') {
        $studentName = $_POST['student_name'] ?? '';
        $subject = $_POST['subject'] ?? '';
        $year = $_POST['year'] ?? '';
        $branch = $_POST['branch'] ?? '';
        $part = $_POST['part'] ?? '';
        $classNum = $_POST['classNum'] ?? '';
        
        if (empty($studentName) || empty($subject)) {
            echo json_encode(['success' => false, 'message' => 'بيانات الطالب أو المادة غير محددة.']);
            exit;
        }
        
        $dbInfo = getDbInfo($year, $branch, $part, $classNum, $year_map, $branch_map, $part_map, $table_map);
        if (isset($dbInfo['error'])) {
            echo json_encode(['success' => false, 'message' => $dbInfo['error']]);
            exit;
        }

        $dbPath = $dbInfo['dbPath'];
        $tableName = $dbInfo['tableName'];

        if (!file_exists($dbPath)) {
            echo json_encode(['success' => false, 'message' => 'خطأ: لم يتم العثور على قاعدة البيانات.']);
            exit;
        }

        try {
            $pdo = new PDO("sqlite:" . $dbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $currentDateTime = date('Y-m-d H:i');
            $absenceNote = htmlspecialchars($subject) . ": " . $currentDateTime;
            
            $stmt = $pdo->prepare("SELECT absence_details, absence_count FROM {$tableName} WHERE full_name = ?");
            $stmt->execute([$studentName]);
            $studentData = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($studentData) {
                $newAbsenceDetails = !empty($studentData['absence_details'])
                    ? $studentData['absence_details'] . ' | ' . $absenceNote
                    : $absenceNote;
                $newAbsenceCount = (int)$studentData['absence_count'] + 1;
                
                $updateStmt = $pdo->prepare("UPDATE {$tableName} SET absence_details = ?, absence_count = ? WHERE full_name = ?");
                $updateStmt->execute([$newAbsenceDetails, $newAbsenceCount, $studentName]);
                
                echo json_encode(['success' => true, 'message' => 'تم تسجيل الغياب بنجاح.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'الطالب غير موجود.']);
            }

        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'خطأ أثناء تسجيل الغياب: ' . $e->getMessage()]);
        }
    // --- 4. تحديث الدرجات (الإجراء الجديد والمعدّل) ---
    } elseif ($_POST['action'] === 'save_grades') {
        $studentName = $_POST['student_name'] ?? '';
        $year = $_POST['year'] ?? '';
        $branch = $_POST['branch'] ?? '';
        $part = $_POST['part'] ?? '';
        $classNum = $_POST['classNum'] ?? '';
        $subjectName = $_POST['subject_name'] ?? '';
        $grades = json_decode($_POST['grades'], true) ?? [];
    
        if (empty($studentName) || empty($subjectName)) {
            echo json_encode(['success' => false, 'message' => 'بيانات الطالب أو المادة غير محددة.']);
            exit;
        }
    
        $dbInfo = getDbInfo($year, $branch, $part, $classNum, $year_map, $branch_map, $part_map, $table_map);
        if (isset($dbInfo['error'])) {
            echo json_encode(['success' => false, 'message' => $dbInfo['error']]);
            exit;
        }
    
        $dbPath = $dbInfo['dbPath'];
        $tableName = $dbInfo['tableName'];
    
        if (!file_exists($dbPath)) {
            echo json_encode(['success' => false, 'message' => 'خطأ: لم يتم العثور على قاعدة البيانات.']);
            exit;
        }

        $subject_key = array_search($subjectName, $subject_map);
        if (!$subject_key) {
            echo json_encode(['success' => false, 'message' => 'خطأ: لم يتم العثور على المادة المحددة في قاعدة البيانات.']);
            exit;
        }
    
        try {
            $pdo = new PDO("sqlite:" . $dbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $updateFields = [];
            $updateValues = [];
            
            foreach ($grades as $term => $grade_values) {
                $columnName = "{$subject_key}_{$term}";
                $updateFields[] = "{$columnName} = ?";
                $updateValues[] = implode(',', $grade_values);
            }
            
            if (empty($updateFields)) {
                echo json_encode(['success' => false, 'message' => 'لا توجد درجات للتحديث.']);
                exit;
            }
            
            $updateSql = "UPDATE {$tableName} SET " . implode(', ', $updateFields) . " WHERE full_name = ?";
            $updateValues[] = $studentName;
            
            $stmt = $pdo->prepare($updateSql);
            $stmt->execute($updateValues);
    
            echo json_encode(['success' => true, 'message' => 'تم حفظ التغييرات بنجاح.']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'خطأ أثناء حفظ الدرجات: ' . $e->getMessage()]);
        }
    // --- 5. إضافة الملاحظة الجديدة (الإجراء الجديد) ---
    } elseif ($_POST['action'] === 'send_note') {
        $studentName = $_POST['student_name'] ?? '';
        $noteText = $_POST['note_text'] ?? '';
        $subject = $_POST['subject_name'] ?? '';
        $year = $_POST['year'] ?? '';
        $branch = $_POST['branch'] ?? '';
        $part = $_POST['part'] ?? '';
        $classNum = $_POST['classNum'] ?? '';
    
        if (empty($studentName) || empty($noteText)) {
            echo json_encode(['success' => false, 'message' => 'بيانات الطالب أو الملاحظة غير محددة.']);
            exit;
        }

        $dbInfo = getDbInfo($year, $branch, $part, $classNum, $year_map, $branch_map, $part_map, $table_map);
        if (isset($dbInfo['error'])) {
            echo json_encode(['success' => false, 'message' => $dbInfo['error']]);
            exit;
        }
    
        $dbPath = $dbInfo['dbPath'];
        $tableName = $dbInfo['tableName'];
    
        if (!file_exists($dbPath)) {
            echo json_encode(['success' => false, 'message' => 'خطأ: لم يتم العثور على قاعدة البيانات.']);
            exit;
        }

        try {
            $pdo = new PDO("sqlite:" . $dbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
            $currentDate = date('Y-m-d');
            $newNote = "مدرس " . htmlspecialchars($subject) . " " . $currentDate . ": " . htmlspecialchars($noteText);
    
            $stmt = $pdo->prepare("SELECT note FROM {$tableName} WHERE full_name = ?");
            $stmt->execute([$studentName]);
            $studentData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $existingNote = $studentData['note'] ?? '';
            $updatedNote = !empty($existingNote) ? $existingNote . ', ' . $newNote : $newNote;
    
            $updateStmt = $pdo->prepare("UPDATE {$tableName} SET note = ? WHERE full_name = ?");
            $updateStmt->execute([$updatedNote, $studentName]);
    
            echo json_encode(['success' => true, 'message' => 'تم إرسال الملاحظة بنجاح.']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'خطأ أثناء إرسال الملاحظة: ' . $e->getMessage()]);
        }
    }
    
    exit;
}

// معالج تسجيل الخروج
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $_SESSION = array();
    session_destroy();
    header('Location: teacher.php');
    exit;
}

// دالة للتحقق من بيانات تسجيل الدخول
function authenticateTeacher($username, $password) {
    $dbPath = "data/teachers/database.sqlite";
    if (!file_exists($dbPath)) return false;
    $username_password_combo = $username . ':' . $password;
    try {
        $pdo = new PDO("sqlite:" . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $stmt = $pdo->prepare("SELECT teacher_id, full_name, permission FROM teachers WHERE username_password = ?");
        $stmt->execute([$username_password_combo]);
        return $stmt->fetch() ?: false;
    } catch (PDOException $e) {
        return false;
    }
}

// دالة لتحليل الصلاحيات الجديدة
function parsePermissions($permissionText) {
    $permissions = [];
    $entries = explode('|', $permissionText);
    foreach ($entries as $entry) {
        $entry = trim($entry);
        if (empty($entry)) continue;
        $data = [];
        $parts = explode('/', $entry);
        foreach ($parts as $part) {
            if (strpos($part, ':') !== false) {
                list($key, $value) = explode(':', $part, 2);
                $data[trim($key)] = trim($value);
            }
        }
        if (isset($data['Year']) && isset($data['branch']) && isset($data['subject'])) {
            $year = $data['Year'];
            $branch = $data['branch'];
            if (!isset($permissions[$year])) $permissions[$year] = [];
            if (!isset($permissions[$year][$branch])) $permissions[$year][$branch] = [];
            $permissions[$year][$branch][] = [
                'subject' => $data['subject'],
                'part' => $data['part'] ?? '',
                'classes' => isset($data['classes']) ? array_map('trim', explode(',', $data['classes'])) : []
            ];
        }
    }
    return $permissions;
}

$error_message = '';
$show_dashboard = false;
$teacher_data = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_SESSION['teacher_id']) && !isset($_POST['action'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    if (!empty($username) && !empty($password)) {
        $teacher_data = authenticateTeacher($username, $password);
        if ($teacher_data) {
            $_SESSION['teacher_id'] = $teacher_data['teacher_id'];
            $_SESSION['full_name'] = $teacher_data['full_name'];
            $_SESSION['permission'] = $teacher_data['permission'];
            $show_dashboard = true;
        } else {
            $error_message = 'اسم المستخدم أو كلمة المرور غير صحيحة.';
        }
    } else {
        $error_message = 'الرجاء إدخال جميع البيانات المطلوبة.';
    }
}

if (isset($_SESSION['teacher_id'])) {
    $show_dashboard = true;
    $teacher_data = [
        'teacher_id' => $_SESSION['teacher_id'],
        'full_name' => $_SESSION['full_name'],
        'permission' => $_SESSION['permission']
    ];
}

if ($show_dashboard && $teacher_data) {
    $full_name = $teacher_data['full_name'];
    $name_parts = explode(' ', $full_name, 2);
    $first_name = $name_parts[0];
    $last_name = $name_parts[1] ?? '';
    $permissions = parsePermissions($teacher_data['permission']);
    $totalSubjects = 0;
    $totalSections = 0;
    if (!empty($permissions)) {
        foreach ($permissions as $year => $branches) {
            foreach ($branches as $branchName => $subjects) {
                foreach ($subjects as $subjectData) {
                    $totalSubjects++;
                    $totalSections += count($subjectData['classes']);
                }
            }
        }
    }
}

if (isset($_POST['subject_name'])) {
    $_SESSION['subject_name'] = $_POST['subject_name'];
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $show_dashboard ? 'لوحة تحكم المدرس' : 'تسجيل دخول المدرس'; ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap');
        :root{--primary-color:#4a90e2;--secondary-color:#f8f9fa;--accent-color:#6ba3f5;--text-color:#2c3e50;--border-color:#dee2e6;--success-color:#27ae60;--danger-color:#d9534f;--danger-hover-color:#c9302c;--shadow:0 4px 15px rgba(0,0,0,0.08);--light-blue:#e3f2fd;--medium-blue:#bbdefb;--section-color-1:#e8f4fd;--section-color-2:#d1e9fc;--section-color-3:#b3dcfb;--section-color-4:#94cef9;--section-color-5:#74c0f8;--dark-blue:#2c5a90;--brown-color:#5a3f2d;}
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'Tajawal',sans-serif;background-color:#f4f7f6;color:var(--text-color);line-height:1.6;}
        <?php if (!$show_dashboard): ?>
        .login-container{display:flex;justify-content:center;align-items:center;min-height:100vh;padding:20px;}
        .login-box{background:#ffffff;padding:40px;border-radius:15px;box-shadow:0 10px 30px rgba(0,0,0,0.1);width:100%;max-width:420px;text-align:center;}
        .logo-container{margin-bottom:25px;}.logo-container img{max-width:120px;height:auto;}
        .login-title{font-size:1.8em;color:var(--primary-color);margin-bottom:8px;font-weight:700;}
        .login-subtitle{color:#6c757d;margin-bottom:30px;}
        .form-group{margin-bottom:20px;text-align:right;}
        .form-group label{display:block;margin-bottom:8px;color:#555;font-weight:500;}
        .form-group input{width:100%;padding:12px 15px;border:1px solid var(--border-color);border-radius:8px;font-size:1em;transition:all 0.3s ease;}
        .form-group input:focus{outline:none;border-color:var(--primary-color);box-shadow:0 0 0 3px rgba(44,90,160,0.15);}
        .login-btn{width:100%;padding:15px;background:linear-gradient(45deg,var(--primary-color),#4a90e2);color:white;border:none;border-radius:10px;cursor:pointer;font-size:1.1em;font-weight:bold;transition:all 0.3s ease;}
        .login-btn:hover{background:#244a87;box-shadow:0 5px 15px rgba(44,90,160,0.2);}
        .error{background-color:#f8d7da;color:#721c24;padding:12px 15px;border:1px solid #f5c6cb;border-radius:8px;margin-bottom:20px;}
        <?php else: ?>
        .dashboard-container{max-width:1200px;margin:0 auto;padding:30px 20px;}
        .dashboard-header{background:#ffffff;padding:25px 30px;border-radius:15px;box-shadow:var(--shadow);margin-bottom:30px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:20px;}
        .teacher-info{display:flex;align-items:center;gap:20px;}
        .teacher-logo-dashboard{width:70px;height:70px;}.teacher-logo-dashboard img{width:100%;height:100%;object-fit:contain;}
        .teacher-details{display:flex;flex-direction:column;gap:5px;}
        .teacher-details h1{color:var(--primary-color);font-size:1.8em;font-weight:700;margin:0;}
        .teacher-details p{color:#555;font-size:1em;margin:0;}
        .teacher-details .full-name-split{color:#555;font-size:1em;display:flex;gap:5px;}.teacher-details .full-name-split span{font-weight:500;color:var(--text-color);}
        .logout-btn{background-color:var(--danger-color);color:white;padding:10px 20px;border-radius:8px;text-decoration:none;font-weight:500;transition:all 0.2s ease-in-out;border:none;cursor:pointer;}
        .logout-btn:hover{background-color:var(--danger-hover-color);box-shadow:0 4px 10px rgba(217,83,79,0.3);}
        .separator-line{width:100%;height:1px;background-color:var(--border-color);margin:20px 0;}
        .permissions-section{background:#ffffff;padding:30px;border-radius:15px;box-shadow:var(--shadow);margin-bottom:25px;}
        .section-title{color:var(--primary-color);font-size:1.6em;margin-bottom:25px;padding-bottom:10px;border-bottom:2px solid #eee;font-weight:700;}
        .grades-container{display:flex;flex-direction:column;gap:25px;}
        .grade-card{background-color:var(--secondary-color);border-radius:12px;border-right:2px solid var(--primary-color);overflow:hidden;transition:all 0.3s ease;}
        .grade-card:hover{box-shadow:0 6px 20px rgba(0,0,0,0.08);}
        .grade-header{padding:20px;cursor:pointer;display:flex;justify-content:space-between;align-items:center;transition:background-color 0.2s;background:linear-gradient(135deg,#f8f9fa 0%,#e9ecef 100%);}
        .grade-header:hover{background:linear-gradient(135deg,#e9ecef 0%,#dee2e6 100%);}
        .grade-card.expanded .grade-header{background:linear-gradient(135deg,var(--primary-color) 0%,#4a90e2 100%);color:white;}
        .grade-card h3{color:var(--primary-color);font-size:1.4em;margin:0;font-weight:700;}
        .grade-card.expanded h3{color:white;}
        .expand-arrow{display:inline-block;width:24px;height:24px;background-image:url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="%232c5aa0" d="M16.293 9.293L12 13.586l-4.293-4.293a1 1 0 0 0-1.414 1.414l5 5a1 1 0 0 0 1.414 0l5-5a1 1 0 0 0-1.414-1.414z"/></svg>');background-repeat:no-repeat;background-position:center;transition:transform 0.3s ease;}
        .grade-card.expanded .expand-arrow{transform:rotate(180deg);background-image:url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="white" d="M16.293 9.293L12 13.586l-4.293-4.293a1 1 0 0 0-1.414 1.414l5 5a1 1 0 0 0 1.414 0l5-5a1 1 0 0 0-1.414-1.414z"/></svg>');}
        .subjects-dropdown{background:#ffffff;padding:0;display:none;border-top:1px solid #e5e5e5;}
        .subjects-dropdown.show{display:block;animation:slideDown 0.4s ease-out;}
        .branch-section{border-bottom:1px solid #f0f0f0;}.branch-section:last-child{border-bottom:none;}
        .branch-header{padding:15px 20px;font-weight:600;font-size:1.1em;color:#333;border-bottom:1px solid #f8f9fa;background:var(--light-blue);}
        .subjects-grid{padding:20px;display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:20px;}
        .subject-card{background:#fdfdff;border:1px solid #eef2f7;border-radius:10px;padding:20px;transition:all 0.3s ease;}
        .subject-card:hover{box-shadow:0 4px 12px rgba(0,0,0,0.08);transform:translateY(-2px);}
        .subject-title{font-weight:700;color:#333;margin-bottom:15px;font-size:1.2em;display:flex;align-items:center;gap:10px;}
        .sections-container{display:flex;flex-wrap:wrap;gap:8px;}
        .section-tag{background:linear-gradient(135deg,var(--primary-color),var(--accent-color));color:white;padding:8px 15px;border-radius:20px;font-size:0.9em;font-weight:500;box-shadow:0 2px 4px rgba(44,90,160,0.2);transition:all 0.3s ease;border:none;cursor:pointer;font-family:'Tajawal',sans-serif;}
        .section-tag:hover{transform:scale(1.05);box-shadow:0 4px 8px rgba(44,90,160,0.3);}
        .no-permissions{text-align:center;padding:40px;color:#666;font-size:1.1em;}
        .stats-container{margin-top:25px;background:#ffffff;padding:30px;border-radius:15px;box-shadow:var(--shadow);}
        .stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px;justify-content:center;}
        .stat-card{background:rgba(0,0,0,0.7);color:white;padding:25px;border-radius:12px;text-align:center;box-shadow:0 4px 12px rgba(0,0,0,0.2);transition:all 0.3s ease;display:flex;flex-direction:column;align-items:center;justify-content:center;}
        .stat-card:hover{transform:translateY(-5px);background:rgba(0,0,0,0.85);box-shadow:0 6px 20px rgba(0,0,0,0.3);}
        .stat-number{font-size:2.5em;font-weight:700;margin-bottom:5px;line-height:1;}
        .stat-label{font-size:1em;opacity:0.9;font-weight:500;}
        @keyframes slideDown{from{opacity:0;transform:translateY(-10px);}to{opacity:1;transform:translateY(0);}}
        .modal{display:none;position:fixed;z-index:1000;left:0;top:0;width:100%;height:100%;overflow:auto;background-color:rgba(0,0,0,0.6);backdrop-filter:blur(5px);animation:fadeIn 0.3s ease-in-out;}
        @keyframes fadeIn{from{opacity:0;}to{opacity:1;}}
        .modal-content{background-color:#fefefe;margin:10% auto;padding:30px;border:1px solid #888;width:90%;max-width:600px;border-radius:15px;box-shadow:0 5px 25px rgba(0,0,0,0.2);position:relative;animation:slideIn 0.4s ease-out;max-height:80vh;overflow:hidden;display:flex;flex-direction:column;}
        @keyframes slideIn{from{transform:translateY(-50px);opacity:0;}to{transform:translateY(0);opacity:1;}}
        .close-button{color:#aaa;position:absolute;left:25px;top:15px;font-size:28px;font-weight:bold;transition:color 0.2s;z-index:1;}.close-button:hover,.close-button:focus{color:#d9534f;text-decoration:none;cursor:pointer;}
        #modal-body h3{color:var(--primary-color);margin-top:0;margin-bottom:20px;padding-bottom:10px;border-bottom:1px solid var(--border-color);}
        
        .student-list-container{max-height:400px;overflow-y:auto;padding-right:10px;margin-bottom:15px;}
        .student-row{display:flex;align-items:center;justify-content:space-between;background:linear-gradient(135deg,#f8f9fa,#e9ecef);transition:all 0.3s ease;
            border:1px solid var(--border-color); border-top-left-radius: 8px; border-top-right-radius: 8px; border-bottom: none;padding: 0;}
        .student-row:hover{background:var(--light-blue);border-color:var(--primary-color);}
        .student-info-btn{flex-grow:1;text-align:right;border:none;background:transparent;display:flex;align-items:center;justify-content:space-between;padding:12px 15px;font-family:'Tajawal',sans-serif;font-size:1em;color:var(--text-color);white-space:nowrap;cursor:pointer;}
        .student-info-btn:hover{background:rgba(0,0,0,0.05);}
        .student-info-btn.expanded {background:rgba(0,0,0,0.05);}
        .student-name{flex-grow:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
        .grades-dropdown-toggle{width:20px;height:20px;background-image:url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="%234a90e2" d="M16.293 9.293L12 13.586l-4.293-4.293a1 1 0 0 0-1.414 1.414l5 5a1 1 0 0 0 1.414 0l5-5a1 1 0 0 0-1.414-1.414z"/></svg>');background-repeat:no-repeat;background-position:center;transition:transform 0.3s ease;}
        .student-info-btn.expanded .grades-dropdown-toggle{transform:rotate(180deg);}
        .absence-btn-wrapper {display: flex; align-items: center;}
        .absence-btn{background-color:rgba(0,0,0,0.7);color:#fff;border:none;padding:12px 15px;border-radius:0 8px 8px 0;cursor:pointer;font-weight:500;transition:background-color 0.3s;font-family:'Tajawal',sans-serif;font-size:0.9em;white-space:nowrap;}
        .absence-btn:hover{background-color:rgba(0,0,0,0.85);}
        .divider { height: 30px; width: 1px; background-color: rgba(0,0,0,0.1); margin: 0 5px; }

        .grades-dropdown-container{border-radius:0 0 8px 8px;margin-top:0;margin-bottom:10px;overflow-x:auto;padding:0;background:#fff;border:1px solid var(--border-color);border-top:none;}
        .grades-dropdown-container.hidden{display:none !important;}
        .grades-dropdown-content{padding:15px;background-color:var(--secondary-color);border-radius:0 0 8px 8px; border-top: 1px solid var(--border-color);}
        .grades-content{display:flex;flex-direction:column;gap:15px;}
        .table-responsive{width:100%;overflow-x:auto;padding-bottom:10px;}
        .grades-table{width:100%;border-collapse:collapse;background-color:white;min-width:550px;}.grades-table th, .grades-table td{border:1px solid #ddd;padding:6px;text-align:center;white-space:nowrap;}
        .grades-table th{background-color:var(--primary-color);color:white;font-weight:600;}
        .grades-table tr:nth-child(even){background-color:#f9f9f9;}
        .grades-table td.subject-name{text-align:right;font-weight:500;color:var(--text-color);}
        .grade-input{width:50px;padding:5px;text-align:center;border:1px solid #ccc;border-radius:4px;font-family:'Tajawal',sans-serif;background-color:#fff;}
        .grade-input:disabled{background-color:#eee;cursor:not-allowed;}
        .grade-actions{text-align:center;}
        .edit-grades-btn, .save-grades-btn{padding:10px 20px;border-radius:8px;font-weight:bold;cursor:pointer;border:none;transition:background-color 0.3s, color 0.3s;background-color:var(--dark-blue);color:white;}
        .edit-grades-btn:hover, .save-grades-btn:hover{background-color:#1c3d63;}
        
        .note-section{margin-top:20px;padding-top:15px;border-top:1px solid #ddd;text-align:center;}
        .note-section h4{color:var(--primary-color);margin-bottom:10px;font-size:1.1em;}
        #note-textarea{width:100%;resize:vertical;padding:10px;border:1px solid #ccc;border-radius:8px;font-family:'Tajawal',sans-serif;font-size:0.95em;min-height:40px;max-height:80px;}.note-section .send-note-btn{margin-top:10px;padding:10px 20px;background-color:var(--dark-blue);color:white;border:none;border-radius:8px;cursor:pointer;font-weight:bold;transition:background-color 0.3s;}.note-section .send-note-btn:hover{background-color:#1c3d63;}
        
        .modal-alert{display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);width:350px;background-color:white;border-radius:10px;box-shadow:0 5px 20px rgba(0,0,0,0.2);z-index:1001;padding:25px;text-align:center;font-family:'Tajawal',sans-serif;}
        .modal-alert .alert-header{display:flex;justify-content:flex-end;}.modal-alert .alert-close-btn{font-size:24px;color:#ccc;cursor:pointer;line-height:1;transition:color 0.2s;position:absolute;top:10px;left:10px;}.modal-alert .alert-close-btn:hover{color:var(--brown-color);}
        .modal-alert .alert-icon{font-size:3em;color:#fff;background:var(--brown-color);width:60px;height:60px;border-radius:50%;display:flex;justify-content:center;align-items:center;margin:0 auto 15px auto;}
        .modal-alert .alert-icon svg{width:30px;height:30px;}
        .modal-alert.success .alert-icon{background-color:var(--success-color);}
        .modal-alert.confirm .alert-icon{background-color:var(--danger-color);}
        .modal-alert h4{color:var(--brown-color);font-size:1.4em;margin-bottom:10px;font-weight:700;}
        .modal-alert p{color:var(--brown-color);margin-bottom:20px;}.modal-alert .alert-actions{display:flex;justify-content:center;gap:10px;}
        .modal-alert .alert-actions button{padding:10px 20px;border-radius:8px;border:none;font-weight:bold;cursor:pointer;font-family:'Tajawal',sans-serif;transition:background-color 0.2s;}.modal-alert .btn-confirm{background-color:var(--danger-color);color:white;}.modal-alert .btn-confirm:hover{background-color:var(--danger-hover-color);}.modal-alert .btn-cancel{background-color:#eee;color:#333;}.modal-alert .btn-cancel:hover{background-color:#ddd;}
        .modal-overlay{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background-color:rgba(0,0,0,0.4);z-index:1000;}
        @media (max-width:768px){.dashboard-header{flex-direction:column;align-items:flex-start;}.logout-btn{align-self:flex-start;margin-top:10px;}.subjects-grid{grid-template-columns:1fr;}.stats-grid{grid-template-columns:1fr;}}
        <?php endif; ?>
    </style>
</head>
<body>
<?php if (!$show_dashboard): ?>
    <div class="login-container">
        <div class="login-box">
            <div class="logo-container"><img src="https://iili.io/FQLtDjs.md.png" alt="شعار المدرسة"></div>
            <h1 class="login-title">تسجيل دخول المدرس</h1>
            <p class="login-subtitle">أهلاً بك في المنطقة الخاصة بالمدرسين</p>
            <?php if (!empty($error_message)): ?><div class="error"><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>
            <form method="POST" action="teacher.php">
                <div class="form-group"><label for="username">اسم المستخدم</label><input type="text" id="username" name="username" required></div>
                <div class="form-group"><label for="password">كلمة المرور</label><input type="password" id="password" name="password" required></div>
                <button type="submit" class="login-btn">دخول</button>
            </form>
        </div>
    </div>
<?php else: ?>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <div class="teacher-info">
                <div class="teacher-logo-dashboard"><img src="https://iili.io/FQLtDjs.md.png" alt="شعار المدرسة"></div>
                <div class="teacher-details">
                    <h1>أهلاً بك، <?php echo htmlspecialchars($first_name); ?></h1>
                    <p class="full-name-split"><span style="font-weight: bold;">الاسم:</span> <span><?php echo htmlspecialchars($first_name); ?></span></p>
                    <p class="full-name-split"><span style="font-weight: bold;">اللقب:</span> <span><?php echo htmlspecialchars($last_name); ?></span></p>
                </div>
            </div>
            <a href="teacher.php?action=logout" class="logout-btn">تسجيل الخروج</a>
        </header>

        <div class="separator-line"></div>
        
        <main class="permissions-section">
            <h2 class="section-title">منطقة التلاميذ تحت التدريس</h2>
            <div class="grades-container">
                <?php
                $gradeNames = ['اول ثانوي' => 'السنة الأولى ثانوي','ثاني ثانوي' => 'السنة الثانية ثانوي','ثالث ثانوي' => 'السنة الثالثة ثانوي'];
                if (!empty($permissions)):
                    foreach ($permissions as $year => $branches):
                        $displayYear = $gradeNames[$year] ?? $year;
                ?>
                    <div class="grade-card" id="card-<?php echo str_replace(' ', '_', $year); ?>">
                        <div class="grade-header" onclick="toggleDropdown('<?php echo str_replace(' ', '_', $year); ?>')">
                            <h3><?php echo htmlspecialchars($displayYear); ?></h3>
                            <div class="expand-arrow"></div>
                        </div>
                        <div class="subjects-dropdown" id="dropdown-<?php echo str_replace(' ', '_', $year); ?>">
                            <?php foreach ($branches as $branchName => $subjects): ?>
                                <div class="branch-section">
                                    <div class="branch-header">الفرع <?php echo htmlspecialchars($branchName); ?></div>
                                    <div class="subjects-grid">
                                        <?php foreach ($subjects as $subjectData): ?>
                                            <div class="subject-card">
                                                <div class="subject-title">
                                                    <span class="subject-icon"></span><?php echo htmlspecialchars($subjectData['subject']); ?>
                                                    <?php if (!empty($subjectData['part'])): ?><span style="color: #666; font-size: 0.9em; font-weight: normal;"> (<?php echo htmlspecialchars($subjectData['part']); ?>)</span><?php endif; ?>
                                                </div>
                                                <div class="sections-container">
                                                    <?php if (!empty($subjectData['classes'])): ?>
                                                        <?php foreach ($subjectData['classes'] as $class): ?>
                                                            <button type="button" class="section-tag"
                                                                data-year="<?php echo htmlspecialchars($year); ?>" 
                                                                data-branch="<?php echo htmlspecialchars($branchName); ?>" 
                                                                data-part="<?php echo htmlspecialchars($subjectData['part']); ?>"
                                                                data-class-num="<?php echo htmlspecialchars($class); ?>"
                                                                data-subject-name="<?php echo htmlspecialchars($subjectData['subject']); ?>">
                                                                قسم <?php echo htmlspecialchars($class); ?>
                                                            </button>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <span class="section-tag" style="background: #6c757d; cursor: default;">لا توجد أقسام محددة</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; else: ?>
                    <div class="no-permissions"><p>لا توجد لديك صلاحيات لعرض أي صف دراسي حالياً.</p></div>
                <?php endif; ?>
            </div>
        </main>
        
        <div class="separator-line"></div>
        
        <div class="stats-container">
            <div class="stats-grid">
                <div class="stat-card"><div class="stat-number"><?php echo $totalSubjects; ?></div><div class="stat-label">إجمالي المواد</div></div>
                <div class="stat-card"><div class="stat-number"><?php echo $totalSections; ?></div><div class="stat-label">إجمالي الأقسام</div></div>
            </div>
        </div>
    </div>

    <div id="studentModal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <div id="modal-body"></div>
        </div>
    </div>
    
    <div id="absenceSuccessModal" class="modal-alert success">
        <div class="alert-header">
            <span class="alert-close-btn">&times;</span>
        </div>
        <div class="alert-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="white" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
        </div>
        <h4>تم التسجيل بنجاح</h4>
        <p id="success-message"></p>
    </div>

    <div id="gradesSuccessModal" class="modal-alert success">
        <div class="alert-header">
            <span class="alert-close-btn">&times;</span>
        </div>
        <div class="alert-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="white" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
        </div>
        <h4>تم الحفظ بنجاح</h4>
        <p id="grades-success-message">تم حفظ الدرجات بنجاح.</p>
    </div>
    
    <div id="noteSuccessModal" class="modal-alert success">
        <div class="alert-header">
            <span class="alert-close-btn">&times;</span>
        </div>
        <div class="alert-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="white" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
        </div>
        <h4>تم إرسال الملاحظة</h4>
        <p id="note-success-message">تم إرسال الملاحظة بنجاح.</p>
    </div>

    <div id="modal-overlay" class="modal-overlay"></div>

    <script>
        function toggleDropdown(yearId) {
            const card = document.getElementById('card-' + yearId);
            const dropdown = document.getElementById('dropdown-' + yearId);
            const isExpanded = card.classList.contains('expanded');
            document.querySelectorAll('.grade-card').forEach(c => c.id !== 'card-' + yearId && c.classList.remove('expanded'));
            document.querySelectorAll('.subjects-dropdown').forEach(dd => dd.id !== 'dropdown-' + yearId && dd.classList.remove('show'));
            card.classList.toggle('expanded', !isExpanded);
            dropdown.classList.toggle('show', !isExpanded);
        }
        
        function showCustomAlert(type, message) {
            const overlay = document.getElementById('modal-overlay');
            const successModal = document.getElementById('absenceSuccessModal');
            const gradesSuccessModal = document.getElementById('gradesSuccessModal');
            const noteSuccessModal = document.getElementById('noteSuccessModal');
            
            overlay.style.display = 'block';

            if (type === 'success_absence') {
                document.getElementById('success-message').textContent = message;
                successModal.style.display = 'block';
            } else if (type === 'success_grades') {
                gradesSuccessModal.style.display = 'block';
            } else if (type === 'success_note') {
                noteSuccessModal.style.display = 'block';
            }
        }
        
        function hideCustomAlert() {
            document.getElementById('modal-overlay').style.display = 'none';
            document.getElementById('absenceSuccessModal').style.display = 'none';
            document.getElementById('gradesSuccessModal').style.display = 'none';
            document.getElementById('noteSuccessModal').style.display = 'none';
        }
        document.querySelectorAll('.alert-close-btn').forEach(btn => {
            btn.onclick = hideCustomAlert;
        });

        document.addEventListener('DOMContentLoaded', function() {
            // --- Modal Logic ---
            const modal = document.getElementById('studentModal');
            if (modal) {
                const modalBody = document.getElementById('modal-body');
                const closeButton = modal.querySelector('.close-button');
                
                closeButton.onclick = () => modal.style.display = "none";
                window.onclick = event => { if (event.target == modal) modal.style.display = "none"; };
                
                // Handle click on Section Tag
                document.querySelectorAll('.section-tag').forEach(button => {
                    button.addEventListener('click', function(event) {
                        const buttonData = event.currentTarget.dataset;
                        modal.style.display = "block";
                        modalBody.innerHTML = '<p class="modal-info">جاري تحميل قائمة التلاميذ...</p>';

                        const formData = new URLSearchParams({
                            action: 'get_students',
                            year: buttonData.year,
                            branch: buttonData.branch,
                            part: buttonData.part,
                            classNum: buttonData.classNum
                        });

                        // Store section info for later use
                        modal.dataset.year = buttonData.year;
                        modal.dataset.branch = buttonData.branch;
                        modal.dataset.part = buttonData.part;
                        modal.dataset.classNum = buttonData.classNum;
                        modal.dataset.subjectName = buttonData.subjectName;

                        fetch('teacher.php', { method: 'POST', body: formData })
                        .then(response => response.ok ? response.text() : Promise.reject('Network response was not ok.'))
                        .then(html => { modalBody.innerHTML = html; })
                        .catch(error => {
                            console.error('Fetch Error:', error);
                            modalBody.innerHTML = `<p class="modal-error">حدث خطأ في جلب البيانات. الرجاء التأكد من اتصالك بالشبكة والمحاولة مرة أخرى.</p>`;
                        });
                    });
                });

                // Handle clicks inside the modal (for absence and grades buttons)
                modalBody.addEventListener('click', function(event) {
                    // Register Absence
                    if (event.target.classList.contains('absence-btn')) {
                        const studentName = event.target.dataset.studentName;
                        const subjectName = modal.dataset.subjectName;
                        const year = modal.dataset.year;
                        const branch = modal.dataset.branch;
                        const part = modal.dataset.part;
                        const classNum = modal.dataset.classNum;
                        
                        const formData = new URLSearchParams({
                            action: 'register_absence',
                            student_name: studentName,
                            subject: subjectName,
                            year: year,
                            branch: branch,
                            part: part,
                            classNum: classNum
                        });

                        fetch('teacher.php', { method: 'POST', body: formData })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                showCustomAlert('success_absence', data.message);
                            } else {
                                alert('خطأ: ' + data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('حدث خطأ أثناء الاتصال بالخادم.');
                        });
                    }

                    // Toggle Grades Dropdown
                    if (event.target.closest('.student-info-btn')) {
                        const studentInfoBtn = event.target.closest('.student-info-btn');
                        const studentRow = studentInfoBtn.closest('.student-row');
                        const dropdownContainer = studentRow.nextElementSibling;
                        const studentName = studentInfoBtn.dataset.studentName;

                        // Toggle active class and rotate arrow
                        studentInfoBtn.classList.toggle('expanded');
                        
                        if (dropdownContainer.style.display !== 'none') {
                            dropdownContainer.style.display = 'none';
                            studentRow.style.borderBottomLeftRadius = '8px';
                            studentRow.style.borderBottomRightRadius = '8px';
                            studentRow.style.borderBottom = '1px solid var(--border-color)';
                            return;
                        }

                        document.querySelectorAll('.grades-dropdown-container').forEach(d => {
                            if (d !== dropdownContainer) {
                                d.style.display = 'none';
                                d.previousElementSibling.querySelector('.student-info-btn').classList.remove('expanded');
                                d.previousElementSibling.style.borderBottomLeftRadius = '8px';
                                d.previousElementSibling.style.borderBottomRightRadius = '8px';
                                d.previousElementSibling.style.borderBottom = '1px solid var(--border-color)';
                            }
                        });
                        
                        dropdownContainer.style.display = 'block';
                        studentRow.style.borderBottomLeftRadius = '0';
                        studentRow.style.borderBottomRightRadius = '0';
                        studentRow.style.borderBottom = 'none';

                        dropdownContainer.innerHTML = '<div class="grades-dropdown-content"><p class="modal-info">جاري تحميل الدرجات...</p></div>';

                        const formData = new URLSearchParams({
                            action: 'get_grades',
                            student_name: studentName,
                            year: modal.dataset.year,
                            branch: modal.dataset.branch,
                            part: modal.dataset.part,
                            classNum: modal.dataset.classNum,
                            subject_name: modal.dataset.subjectName
                        });

                        fetch('teacher.php', { method: 'POST', body: formData })
                        .then(response => response.ok ? response.text() : Promise.reject('Network response was not ok.'))
                        .then(html => { dropdownContainer.innerHTML = `<div class="grades-dropdown-content">${html}</div>`; })
                        .catch(error => {
                            console.error('Fetch Error:', error);
                            dropdownContainer.innerHTML = `<div class="grades-dropdown-content"><p class="modal-error">حدث خطأ في جلب البيانات.</p></div>`;
                        });
                    }

                    // Edit Grades
                    if (event.target.classList.contains('edit-grades-btn')) {
                        const gradesDropdown = event.target.closest('.grades-dropdown-content');
                        const inputs = gradesDropdown.querySelectorAll('.grade-input');
                        inputs.forEach(input => input.disabled = false);
                        event.target.style.display = 'none';
                        gradesDropdown.querySelector('.save-grades-btn').style.display = 'inline-block';
                    }

                    // Save Grades
                    if (event.target.classList.contains('save-grades-btn')) {
                        const gradesDropdown = event.target.closest('.grades-dropdown-content');
                        const studentRow = gradesDropdown.closest('.grades-dropdown-container').previousElementSibling;
                        const studentName = studentRow.querySelector('.student-name').textContent.trim();
                        
                        const grades = {};
                        gradesDropdown.querySelectorAll('.grades-table tbody tr').forEach(row => {
                            const term1Inputs = row.querySelectorAll('[data-term="1"]');
                            const term2Inputs = row.querySelectorAll('[data-term="2"]');
                            const term3Inputs = row.querySelectorAll('[data-term="3"]');

                            grades['term1'] = [term1Inputs[0].value.trim(), term1Inputs[1].value.trim()];
                            grades['term2'] = [term2Inputs[0].value.trim(), term2Inputs[1].value.trim()];
                            grades['term3'] = [term3Inputs[0].value.trim(), term3Inputs[1].value.trim()];
                        });
                        
                        const postData = {
                            action: 'save_grades',
                            student_name: studentName,
                            year: modal.dataset.year,
                            branch: modal.dataset.branch,
                            part: modal.dataset.part,
                            classNum: modal.dataset.classNum,
                            subject_name: modal.dataset.subjectName,
                            grades: JSON.stringify(grades)
                        };

                        fetch('teacher.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: new URLSearchParams(postData)
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                showCustomAlert('success_grades', data.message);
                                gradesDropdown.querySelectorAll('.grade-input').forEach(input => input.disabled = true);
                                gradesDropdown.querySelector('.save-grades-btn').style.display = 'none';
                                gradesDropdown.querySelector('.edit-grades-btn').style.display = 'inline-block';
                            } else {
                                alert('خطأ: ' + data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('حدث خطأ أثناء الاتصال بالخادم.');
                            // Reset UI even if fetch fails
                            gradesDropdown.querySelectorAll('.grade-input').forEach(input => input.disabled = true);
                            gradesDropdown.querySelector('.save-grades-btn').style.display = 'none';
                            gradesDropdown.querySelector('.edit-grades-btn').style.display = 'inline-block';
                        });
                    }
                    
                    // Send Note
                    if (event.target.classList.contains('send-note-btn')) {
                        const noteTextarea = document.getElementById('note-textarea');
                        const noteText = noteTextarea.value.trim();
                        if (noteText.length === 0) {
                            alert('الرجاء كتابة الملاحظة قبل الإرسال.');
                            return;
                        }

                        const gradesDropdown = event.target.closest('.grades-dropdown-content');
                        const studentRow = gradesDropdown.closest('.grades-dropdown-container').previousElementSibling;
                        const studentName = studentRow.querySelector('.student-name').textContent.trim();
                        
                        const postData = {
                            action: 'send_note',
                            student_name: studentName,
                            note_text: noteText,
                            subject_name: modal.dataset.subjectName,
                            year: modal.dataset.year,
                            branch: modal.dataset.branch,
                            part: modal.dataset.part,
                            classNum: modal.dataset.classNum,
                        };

                        fetch('teacher.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: new URLSearchParams(postData)
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                showCustomAlert('success_note', data.message);
                                noteTextarea.value = '';
                            } else {
                                alert('خطأ: ' + data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('حدث خطأ أثناء إرسال الملاحظة.');
                        });
                    }
                });
            }
        });
    </script>
<?php endif; ?>
</body>
</html>
