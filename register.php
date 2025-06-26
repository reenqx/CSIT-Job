<?php
session_start();
include 'database.php';
$rolesql = "SELECT * FROM role";
$role_result = $conn->query($rolesql);

// เก็บ gender ลง array
$gendersql = "SELECT * FROM gender";
$gender_query = $conn->query($gendersql);
$gender_options = [];
while ($row = $gender_query->fetch_assoc()) {
    $gender_options[] = $row;
}

// เก็บ major ลง array
$majorsql = "SELECT * FROM major";
$major_query = $conn->query($majorsql);
$major_options = [];
while ($row = $major_query->fetch_assoc()) {
    $major_options[] = $row;
}

?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สมัครสมาชิก</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/register.css">
</head>

<body>

    <div class="container">
        <h2 class="text-center">สมัครสมาชิก</h2>
        <form class="form-box" action="register_process.php" method="POST">
            <input type="text" name="id" placeholder="ไอดีผู้ใช้งาน" required>
            <input type="text" name="username" placeholder="ชื่อผู้ใช้" required>
            <input type="email" name="email" placeholder="อีเมล" required>
            <input type="password" name="password" placeholder="รหัสผ่าน" required>

            <label>เลือกบทบาทผู้ใช้:</label>
            <div class="radio-group">
                <?php while ($row = $role_result->fetch_assoc()): ?>
                    <?php
                    // ข้าม role ที่มี role_id เท่ากับ 1 หรือ 2
                    if ($row['role_id'] == 1 || $row['role_id'] == 2) {
                        continue;
                    }
                    ?>
                    <div class="radio-option">
                        <input type="radio" name="role_id" id="role<?= $row['role_id']; ?>" value="<?= $row['role_id']; ?>" onchange="handleRoleChange(this.value)" required>
                        <label for="role<?= $row['role_id']; ?>"><?= $row['role_name_th']; ?></label>
                    </div>
                <?php endwhile; ?>
            </div>



            <!-- เฉพาะนักศึกษา -->
            <div id="studentFields" class="role-field" style="display:none;">
                <label>เพศ:</label>
                <div class="radio-group">
                    <?php foreach ($gender_options as $row): ?>
                        <div class="radio-option">
                            <input type="radio" name="gender_id" id="gender<?= $row['gender_id']; ?>" value="<?= $row['gender_id']; ?>" required>
                            <label for="gender<?= $row['gender_id']; ?>"><?= $row['gender_name_th']; ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>

                <label>สาขา:</label>
                <div class="radio-group">
                    <?php foreach ($major_options as $row): ?>
                        <div class="radio-option">
                            <input type="radio" name="major_id" id="major<?= $row['major_id']; ?>" value="<?= $row['major_id']; ?>" required>
                            <label for="major<?= $row['major_id']; ?>"><?= $row['major_name_th']; ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>


                <label>ชั้นปี:</label>
                <div class="radio-group">
                    <div class="radio-option">
                        <input type="radio" name="year" id="year1" value="1" required>
                        <label for="year1">ปี 1</label>
                    </div>
                    <div class="radio-option">
                        <input type="radio" name="year" id="year2" value="2">
                        <label for="year2">ปี 2</label>
                    </div>
                    <div class="radio-option">
                        <input type="radio" name="year" id="year3" value="3">
                        <label for="year3">ปี 3</label>
                    </div>
                    <div class="radio-option">
                        <input type="radio" name="year" id="year4" value="4">
                        <label for="year4">ปี 4</label>
                    </div>
                </div>

            </div>

            <!-- เฉพาะอาจารย์ -->
            <div id="teacherFields" class="role-field" style="display:none;">
                <label>เพศ:</label>
                <div class="radio-group">
                    <?php foreach ($gender_options as $row): ?>
                        <div class="radio-option">
                            <input type="radio" name="gender_id" id="gender<?= $row['gender_id']; ?>" value="<?= $row['gender_id']; ?>" required>
                            <label for="gender<?= $row['gender_id']; ?>"><?= $row['gender_name_th']; ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>


                <label>สาขา:</label>
                <div class="radio-group">
                    <?php foreach ($major_options as $row): ?>
                        <div class="radio-option">
                            <input type="radio" name="major_id" id="major<?= $row['major_id']; ?>" value="<?= $row['major_id']; ?>" required>
                            <label for="major<?= $row['major_id']; ?>"><?= $row['major_name_th']; ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>


                <input type="tel" name="phone" placeholder="เบอร์โทรผู้ใช้" pattern="[0-9]{10}" maxlength="10" required>

            </div>

            <button type="submit">สมัครสมาชิก</button>
        </form>

    </div>
    <p class="signup-link">
        มีบัญชี? <a href="login.php">เข้าสู่ระบบที่นี่</a>
    </p>
    <!-- ลิงค์สมัครสมาชิก -->


    <script>
        function handleRoleChange(roleId) {
            const student = document.getElementById('studentFields');
            const teacher = document.getElementById('teacherFields');

            // ซ่อนและปิดการใช้งานทุก input
            student.style.display = 'none';
            teacher.style.display = 'none';

            document.querySelectorAll('#studentFields input, #teacherFields input').forEach(el => {
                el.disabled = true;
            });

            // แสดงและเปิดการใช้งานเฉพาะ role ที่เลือก
            if (roleId == '4') {
                student.style.display = 'block';
                student.querySelectorAll('input').forEach(el => {
                    el.disabled = false;
                });
            } else if (roleId == '3') {
                teacher.style.display = 'block';
                teacher.querySelectorAll('input').forEach(el => {
                    el.disabled = false;
                });
            }
        }
    </script>


</body>

</html>