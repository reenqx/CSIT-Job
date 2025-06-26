function showReportModal() {
    document.getElementById('reportModal').style.display = 'flex';
}

function closeReportModal() {
    document.getElementById('reportModal').style.display = 'none';
}

// Ensure the report form submits properly
document.addEventListener("DOMContentLoaded", function () {
    const reportForm = document.getElementById("reportForm");

    if (reportForm) {
        reportForm.addEventListener("submit", function (event) {
            const selectedReason = document.getElementById("report-reason").value;
            
            if (!selectedReason) {
                event.preventDefault(); // Prevent form submission
                alert("กรุณาเลือกเหตุผลในการรายงาน!");
                return;
            }

            // Show confirmation before submitting the report
            const confirmReport = confirm("คุณแน่ใจหรือไม่ว่าต้องการรายงานงานนี้?");
            if (!confirmReport) {
                event.preventDefault(); // Stop form submission if canceled
            }
        });
    }
});


function showReportModal() {
    document.getElementById('reportModal').style.display = 'flex';
}

function closeReportModal() {
    document.getElementById('reportModal').style.display = 'none';
}
