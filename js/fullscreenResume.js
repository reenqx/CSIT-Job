// fullscreenResume.js

function openFullscreenResume(resumeFile) {
    const fileType = resumeFile.split('.').pop().toLowerCase();

    if (fileType === 'pdf') {
        window.open(resumeFile, '_blank'); // เปิด PDF ในแท็บใหม่
        return;
    }
}

