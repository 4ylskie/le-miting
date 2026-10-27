const reportStatus = document.querySelector('[data-report-download-status]');

if (reportStatus) {
    document.querySelectorAll('[data-report-download]').forEach((link) => {
        link.addEventListener('click', () => {
            reportStatus.hidden = false;
            reportStatus.textContent = `${link.dataset.reportName} PDF requested. Your browser is handling the download; this page cannot confirm completion.`;
        });
    });
}
