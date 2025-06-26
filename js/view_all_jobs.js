function applyFilter() {
    let sort = document.getElementById('sort').value;
    let status = document.getElementById('status').value;
    let subcategoryId = <?php echo json_encode($subcategory_id); ?>; // ป้องกันปัญหาการเรียกใช้ตัวแปร PHP ใน JS

    // ตรวจสอบว่า subcategory_id มีค่าหรือไม่
    if (!subcategoryId || subcategoryId === "null") {
        console.error("Error: subcategory_id is missing or invalid.");
        return;
    }

    // อัปเดต URL ตามค่าที่เลือก
    let urlParams = new URLSearchParams(window.location.search);
    urlParams.set('subcategory_id', subcategoryId);
    urlParams.set('sort', sort);
    urlParams.set('status', status);

    window.location.href = '?' + urlParams.toString();
}
