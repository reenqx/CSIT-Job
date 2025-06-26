<!-- Navbar Placeholder -->
<style>
    /* ปรับแต่งแถบเมนูหลัก */


    .box {
        display: flex;
        justify-content: center;
        align-items: center;
        text-align: center;
        background-color: #ff7f27;
        height: 250px;
        width: 100%;
    }

    .box h2 {
        font-size: 24px;
    }

    .typing-container {
        font-size: 28px;
        font-weight: bold;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .cursor {
        display: inline-block;
        width: 3px;
        height: 24px;
        background: rgb(255, 255, 255);
        animation: blink 0.7s infinite;
        margin-left: 2px;
    }

    @keyframes blink {
        50% {
            opacity: 0;
        }
    }

    .nav-menu {
        display: flex;
        justify-content: center;
        align-items: center;
        height: 280px;
    }

    .menu {
        display: flex;
        flex-direction: column;
        align-items: center;
        width: 90%;
        max-width: 1800px;
        border-radius: 30px;
        background-color: white;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        margin-top: 20px;
        top: 250px;
        position: absolute;
        z-index: 999; /* ให้สูงกว่าทุกเนื้อหาอื่น */
    }

    .first-menu {
        padding-top: 20px;
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        justify-content: center;
    }

    /* ปรับแต่งเมนูหลัก */
    .menu-item {
        padding: 15px 20px;
        text-align: center;
        font-weight: bold;
        border-radius: 10px;
        cursor: pointer;
        transition: 0.3s;
        position: relative;
        font-size: 14px;
    }

    .menu-item:hover {
        transform: translateY(-5px);
        /* ลอยขึ้น 5px */
        color: black;
    }

    /* Active State (หมวดหมู่ที่ถูกเลือก) */
    .menu-item.active {
        border-radius: 10px;
        color: #ff7f27;
        background-color: white;
    }

    .menu-item.active::after {
        content: "";
        display: block;
        height: 4px;
        width: 80%;
        background: #ff7f27;
        position: absolute;
        bottom: -5px;
        left: 50%;
        transform: translateX(-50%);
        border-radius: 10px;
    }

    .icon {
        display: block;
        font-size: 18px;
        font-weight: bold;
        margin-bottom: 5px;
    }

    /* ปรับแต่ง Subcategories */
    #subcategories-container {
        display: flex;
        /* ลดขนาดเส้นขอบให้พอดี */
        width: 100%;
        /* ให้กว้างเต็มพื้นที่ */
        max-width: 1200px;
        /* จำกัดขนาดสูงสุด */
        align-items: flex-start;
        /* จัดให้อยู่บนสุด */
        justify-content: flex-start;
        /* ชิดซ้าย */
        flex-wrap: wrap;
        /* ให้ subcategories ไม่บีบเกินไป */
        padding: 15px;
        /* เพิ่ม padding ให้สมดุล */
        margin-left: 0;
        /* ชิดซ้ายสุด */
    }

    /* ปรับ Subcategories */
    .sub-menu {
        display: none;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 10px;
        padding: 10px;
        width: 100%;
        transition: all 0.3s ease-in-out;
    }




    /* ปรับขนาดของ sub-item */


    /* เมื่อ Hover ที่ Sub Item */
    .sub-item:hover {
        background: #ff7f27;
        color: white;
        border-radius: 30px;
        transform: scale(1.05);
        /* ทำให้ขยายเล็กน้อย */
    }

    /* สไตล์พื้นฐานของ sub-item */
    .sub-item {
        padding: 12px 20px;
        height: 80px;
        width: 200px;
        font-size: 18px;
        font-weight: bold;
        text-transform: capitalize;
        border-radius: 8px;
        cursor: pointer;
        background: rgba(255, 255, 255, 0.9);
        /* สีขาวโปร่งใส 90% */
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        transition: 0.3s;
    }



    /* Active State สำหรับหมวดหมู่ย่อย */
    .sub-item.active {
        background: white;
        color: #ff5c00;
        transform: scale(1.05);
        border: 2px solid white;
        border-radius: 30px;
    }

    /* Sub-menu แสดงขึ้นแบบลื่นไหล */
    .sub-menu.show {
        display: flex;
        animation: fadeIn 0.3s ease-in-out;
    }
</style>
<div class="box">
    <div class="typing-container">
        <span id="changing-text"></span>
        <span class="cursor"></span>
    </div>
</div>

<script>
    const words = ["ระบบจัดหางานและพัฒนาทักษะ CSIT"];
    let wordIndex = 0;
    let charIndex = 0;
    let currentWord = "";
    let isDeleting = false;

    function typeEffect() {
        currentWord = words[wordIndex];
        let displayedText = currentWord.substring(0, charIndex);
        document.getElementById("changing-text").textContent = displayedText;

        if (!isDeleting && charIndex < currentWord.length) {
            charIndex++;
            setTimeout(typeEffect, 100);
        } else if (isDeleting && charIndex > 0) {
            charIndex--;
            setTimeout(typeEffect, 50);
        } else {
            isDeleting = !isDeleting;
            if (!isDeleting) {
                wordIndex = (wordIndex + 1) % words.length;
            }
            setTimeout(typeEffect, 1000);
        }
    }

    document.addEventListener("DOMContentLoaded", () => {
        setTimeout(typeEffect, 1000);
    });
</script>
<div class="nav-menu">
    <div class="menu">
        <!-- หมวดหมู่หลัก -->
        <div class="first-menu">
            <?php
            foreach ($orderedCategories as $category) {
                $iconText = strtoupper(substr($category['job_category_name'], 0, 2));
                echo '<div class="menu-item" onclick="showSubmenu(' . htmlspecialchars($category['id']) . ', this)" 
                     id="menu-' . htmlspecialchars($category['id']) . '">
                    <span class="icon">' . htmlspecialchars($iconText) . '</span>
                    ' . htmlspecialchars($category['job_category_name']) . '
                </div>';
            }
            ?>
        </div>

        <!-- รายการ Subcategories -->
        <div id="subcategories-container">
            <?php
            foreach ($subcategory as $category_id => $sub_list) {
                echo '<div class="sub-menu" id="sub-' . htmlspecialchars($category_id) . '">';
                foreach ($sub_list as $sub) {
                    echo '<a href="view_all_jobs.php?job_subcategory_id=' . htmlspecialchars($sub['job_subcategory_id']) . '" 
                            class="sub-item" 
                            id="subitem-' . htmlspecialchars($sub['job_subcategory_id']) . '">'
                        . htmlspecialchars($sub['job_subcategory_name']) .
                        '</a>';
                }
                echo '</div>';
            }
            ?>
        </div>
    </div>
</div>

<script>
    function showSubmenu(categoryId, element) {
        // ซ่อน sub-menu ทั้งหมดก่อน
        document.querySelectorAll('.sub-menu').forEach(submenu => {
            submenu.classList.remove("show");
        });

        // เอา active ออกจากเมนูหลักทั้งหมด
        document.querySelectorAll('.menu-item').forEach(item => {
            item.classList.remove("active");
        });

        // แสดง sub-menu ที่ตรงกับ category ที่ถูกเลือก
        var selectedSubmenu = document.getElementById("sub-" + categoryId);
        if (selectedSubmenu) {
            selectedSubmenu.classList.add("show");
        }

        // เพิ่ม active ให้หมวดหมู่หลักที่ถูกเลือก
        element.classList.add("active");

        // บันทึก active state ของหมวดหมู่หลักใน LocalStorage
        localStorage.setItem('activeCategory', categoryId);
    }

    document.addEventListener("DOMContentLoaded", function() {
        // ✅ ดึงค่า `subcategory_id` จาก URL
        let urlParams = new URLSearchParams(window.location.search);
        let subcategoryId = urlParams.get('subcategory_id');

        // ✅ ถ้ามีค่า `subcategory_id` ให้เพิ่ม `active` ให้เมนูที่ตรงกัน
        if (subcategoryId) {
            let activeSubMenuItem = document.getElementById(`subitem-${subcategoryId}`);
            if (activeSubMenuItem) {
                activeSubMenuItem.classList.add("active");

                // ✅ หาหมวดหมู่หลักที่เกี่ยวข้อง และทำให้ Active ด้วย
                let parentCategory = activeSubMenuItem.closest(".sub-menu").id.replace("sub-", "");
                let activeCategoryItem = document.getElementById(`menu-${parentCategory}`);
                if (activeCategoryItem) {
                    activeCategoryItem.classList.add("active");
                    document.getElementById(`sub-${parentCategory}`).classList.add("show");
                }
            }
        }

        // ✅ กำหนด Active State จาก LocalStorage สำหรับหมวดหมู่หลัก
        let activeCategory = localStorage.getItem('activeCategory');
        if (activeCategory && !subcategoryId) {
            let activeElement = document.getElementById(`menu-${activeCategory}`);
            if (activeElement) {
                activeElement.classList.add("active");
                document.getElementById(`sub-${activeCategory}`).classList.add("show");
            }
        }
    });
</script>