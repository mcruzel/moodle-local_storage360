/**
 * Storage 360 initialization module.
 *
 * @module     local_storage360/init
 * @copyright  2025 Storage 360
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    /**
     * Escape a string for safe insertion into HTML.
     * @param {string} text The text to escape.
     * @return {string} The escaped text.
     */
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

    /**
     * Show a Bootstrap modal using jQuery if available, fallback to classList.
     * @param {HTMLElement} modal The modal element.
     */
    function showModal(modal) {
        if (typeof window.jQuery !== 'undefined') {
            window.jQuery(modal).modal('show');
        } else {
            modal.classList.add('show');
            modal.style.display = 'block';
        }
    }

    return {
        init: function() {
            // Select-all checkbox handler for cleanup page.
            var selectAll = document.getElementById('selectall');
            if (selectAll) {
                selectAll.addEventListener('change', function() {
                    var checkboxes = document.querySelectorAll('input[name="fileids[]"]');
                    checkboxes.forEach(function(cb) {
                        cb.checked = selectAll.checked;
                    });
                });
            }

            // Per-row delete button handler – opens Bootstrap modal.
            var deleteModal = document.getElementById('storage360-delete-modal');
            if (deleteModal) {
                var filenameEl = document.getElementById('storage360-delete-filename');
                var filesizeEl = document.getElementById('storage360-delete-filesize');
                var fileidInput = document.getElementById('storage360-delete-fileid');

                document.querySelectorAll('.storage360-delete-one').forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        filenameEl.textContent = btn.getAttribute('data-filename');
                        filesizeEl.textContent = btn.getAttribute('data-filesize');
                        fileidInput.value = btn.getAttribute('data-fileid');
                        showModal(deleteModal);
                    });
                });
            }

            // Backup files modal – fetch file list via AJAX and display in modal.
            var backupsModal = document.getElementById('storage360-backups-modal');
            if (backupsModal) {
                var modalTitle = document.getElementById('storage360-backups-modal-label');
                var modalBody = document.getElementById('storage360-backups-modal-body');

                document.querySelectorAll('.storage360-backup-count').forEach(function(badge) {
                    badge.addEventListener('click', function(e) {
                        e.preventDefault();
                        var courseid = badge.getAttribute('data-courseid');
                        var coursename = badge.getAttribute('data-coursename');

                        modalTitle.textContent = coursename;
                        modalBody.innerHTML =
                            '<div class="text-center"><div class="spinner-border" role="status"></div></div>';
                        showModal(backupsModal);

                        // Build the AJAX URL.
                        var url = window.location.pathname +
                            '?action=getfiles&courseid=' + encodeURIComponent(courseid) +
                            '&sesskey=' + encodeURIComponent(M.cfg.sesskey);

                        fetch(url).then(function(response) {
                            return response.json();
                        }).then(function(files) {
                            if (!files || files.length === 0) {
                                modalBody.innerHTML = '<p class="text-muted">Aucun fichier.</p>';
                                return;
                            }
                            var html = '<table class="table table-sm table-striped">';
                            html += '<thead><tr><th>Fichier</th><th>Type</th>' +
                                    '<th>Taille</th><th>Date</th></tr></thead><tbody>';
                            files.forEach(function(file) {
                                html += '<tr>';
                                html += '<td>' + escapeHtml(file.filename) + '</td>';
                                html += '<td><span class="badge badge-secondary">' +
                                        escapeHtml(file.filearea) + '</span></td>';
                                html += '<td>' + escapeHtml(file.filesize) + '</td>';
                                html += '<td>' + escapeHtml(file.timecreated) + '</td>';
                                html += '</tr>';
                            });
                            html += '</tbody></table>';
                            modalBody.innerHTML = html;
                        }).catch(function() {
                            modalBody.innerHTML = '<p class="text-danger">Erreur lors du chargement.</p>';
                        });
                    });
                });
            }

            // Set date modal – populate courseid when button is clicked.
            var setdateModal = document.getElementById('storage360-setdate-modal');
            if (setdateModal) {
                var courseidInput = document.getElementById('storage360-setdate-courseid');

                document.querySelectorAll('.storage360-setdate-btn').forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        courseidInput.value = btn.getAttribute('data-courseid');
                        showModal(setdateModal);
                    });
                });
            }
        }
    };
});
