document.addEventListener("DOMContentLoaded", function () {
    const approveButtons = document.querySelectorAll(".approve-btn, .reject-btn");

    approveButtons.forEach((button) => {
        button.addEventListener("click", function (e) {
            e.preventDefault();

            const applicationId = this.getAttribute("data-application-id");
            const action = this.getAttribute("data-action");

            if (!applicationId || !action) {
                alert("ข้อมูลไม่ครบถ้วน");
                return;
            }

            fetch("approve_application.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                },
                body: JSON.stringify({
                    applicationId: applicationId,
                    action: action,
                }),
            })
                .then((response) => response.json())
                .then((data) => {
                    alert(data.message); // แสดงข้อความจากเซิร์ฟเวอร์
                    if (data.success) {
                        window.location.reload(); // โหลดหน้าใหม่เพื่ออัปเดตสถานะ
                    }
                })
                .catch((error) => {
                    console.error("Error:", error);
                    alert("เกิดข้อผิดพลาดในการส่งคำขอ");
                });
        });
    });
});
