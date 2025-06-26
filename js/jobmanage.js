function addImage(event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            // สร้าง container สำหรับรูปภาพ
            const container = document.createElement('div');
            container.classList.add('image-container');

            // สร้าง element รูปภาพ
            const img = document.createElement('img');
            img.src = e.target.result;
            img.alt = "Uploaded Image";
            img.onclick = () => selectImage(img); // คลิกเพื่อเลือก
            container.appendChild(img);

            // สร้างปุ่มลบ
            const deleteBtn = document.createElement('button');
            deleteBtn.classList.add('delete-btn');
            deleteBtn.innerHTML = "&times;";
            deleteBtn.onclick = () => container.remove(); // คลิกเพื่อลบรูป
            container.appendChild(deleteBtn);

            // เพิ่ม container ใน .images
            document.querySelector('.images').appendChild(container);
        };
        reader.readAsDataURL(file);
    }
}

function selectImage(img) {
    // เพิ่ม/ลบคลาส selected
    const selected = document.querySelector('.images img.selected');
    if (selected) selected.classList.remove('selected');
    img.classList.add('selected');
}

function selectImage(imgElement) {
    // ดึงค่าพาธของรูปภาพ
    let imagePath = imgElement.getAttribute("src");

    // ตั้งค่าค่าของ input hidden ให้เป็นพาธของภาพ
    document.getElementById("selectedImagePath").value = imagePath;

    // ลบคลาส active จากรูปอื่น
    document.querySelectorAll(".images img").forEach(img => img.classList.remove("active"));

    // เพิ่มคลาส active ให้รูปที่ถูกเลือก
    imgElement.classList.add("active");
}

function previewImage(event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = document.createElement('img');
            img.src = e.target.result;
            img.alt = "Uploaded Image";
            img.style.width = "180px";
            img.style.height = "135px";
            img.style.objectFit = "cover";
            img.style.borderRadius = "8px";
            
            const addPhoto = document.querySelector('.add-photo');
            addPhoto.insertAdjacentElement('beforebegin', img);
        };
        reader.readAsDataURL(file);
    }
}
function toggleImageActive(img) {
    const images = document.querySelectorAll('.images img');
    images.forEach(image => image.classList.remove('active'));
    img.classList.add('active');
}

function toggleViewApp(element) {
    document.querySelectorAll('.nav-link').forEach(link => link.classList.remove('active'));
    element.classList.add('active');
}

function toggleManageJob(element) {
    document.querySelectorAll('.nav-link').forEach(link => link.classList.remove('active'));
    element.classList.add('active');
}

