document.addEventListener("DOMContentLoaded", function() {
    const filterBtn = document.getElementById("filter-btn");
    const messageBox = document.getElementById("hidden-message");
    const applyBtn = document.getElementById("apply-btn");
    const clearBtn = document.getElementById("clear-btn");
    const barLinks = document.querySelectorAll(".bar a");
    const branchButtons = document.querySelectorAll(".branch-btn");
    const yearButtons = document.querySelectorAll(".year-btn");

    let selectedFilters = {
        major_name: new URLSearchParams(window.location.search).get('major_name') 
                      ? decodeURIComponent(new URLSearchParams(window.location.search).get('major_name')).replace(/\+/g, " ") 
                      : null,
        year: new URLSearchParams(window.location.search).get('year') || null
    };

    // **กดปุ่ม Bar เพื่อเปลี่ยนสาขา**
    barLinks.forEach(link => {
        link.addEventListener("click", function(event) {
            event.preventDefault();
            const params = new URLSearchParams(window.location.search);
            let major_name = new URL(this.href).searchParams.get("major_name");

            if (major_name) {
                major_name = decodeURIComponent(major_name).replace(/\+/g, " ");
                params.set("major_name", major_name);
                selectedFilters.major_name = major_name; // อัปเดตค่าใน selectedFilters
            } else {
                params.delete("major_name");
                selectedFilters.major_name = null;
            }

            history.pushState({}, "", "viewapply.php?" + params.toString());
            fetchApplications(params);
        });
    });

    // **เมื่อกด "ตกลง" ให้ Fetch ข้อมูลใหม่**
    applyBtn.addEventListener("click", function() {
        let params = new URLSearchParams();
        if (selectedFilters.major_name) {
            params.set("major_name", selectedFilters.major_name);
        }
        if (selectedFilters.year) {
            params.set("year", selectedFilters.year);
        }

        history.pushState({}, "", "viewapply.php?" + params.toString());
        fetchApplications(params);
    });

    // **ปุ่มล้างค่าฟิลเตอร์**
    clearBtn.addEventListener("click", function() {
        selectedFilters.major_name = null;
        selectedFilters.year = null;

        branchButtons.forEach(btn => btn.classList.remove("active"));
        yearButtons.forEach(btn => btn.classList.remove("active"));

        let params = new URLSearchParams();
        history.pushState({}, "", "viewapply.php");
        fetchApplications(params);
    });

    // **ปุ่ม Filter แสดง/ซ่อน Filter Box**
    if (filterBtn && messageBox) {
        filterBtn.addEventListener("click", function() {
            messageBox.style.display = messageBox.style.display === "none" ? "block" : "none";
        });
    }
});
