/**
 * Naval EGT Public JavaScript - VERSIONE CORRETTA
 */

(function($) {
    'use strict';
    
    // Variabili globali
    let fileUploadQueue = [];
    let isUploading = false;
    
    /**
     * Inizializzazione
     */
    $(document).ready(function() {
        // Verifica se siamo in una pagina Naval EGT
        if ($('#naval-egt-area-riservata').length > 0) {
            initPublicFunctions();
        }
    });
    
    function initPublicFunctions() {
        // Definisci naval_egt_ajax se non è definito
        if (typeof naval_egt_ajax === 'undefined') {
            window.naval_egt_ajax = {
                ajax_url: getAjaxUrl(),
                nonce: getNonce()
            };
        }
        
        // Gestione form login/registrazione
        initAuthForms();
        
        // Gestione upload file
        initFileUpload();
        
        // Gestione download file
        initFileDownload();
        
        // Auto-refresh periodico per dashboard
        if ($('.naval-egt-dashboard').length > 0) {
            setInterval(refreshUserData, 300000); // Ogni 5 minuti
        }
        
        // Gestione responsive
        initResponsiveHandlers();
        
        // Animazioni e UX
        initAnimations();
    }
    
    /**
     * Utility per ottenere URL AJAX
     */
    function getAjaxUrl() {
        // Prova a ottenere dall'elemento admin-ajax.php
        const scripts = document.querySelectorAll('script');
        for (let script of scripts) {
            const src = script.src;
            if (src.includes('wp-admin/admin-ajax.php')) {
                return src;
            }
        }
        
        // Fallback: costruisci URL base
        const baseUrl = window.location.origin;
        return baseUrl + '/wp-admin/admin-ajax.php';
    }
    
    /**
     * Utility per ottenere nonce
     */
    function getNonce() {
        // Cerca nonce nei meta tag o in elementi nascosti
        const nonceMeta = document.querySelector('meta[name="naval-egt-nonce"]');
        if (nonceMeta) {
            return nonceMeta.content;
        }
        
        const nonceInput = document.querySelector('input[name="nonce"]');
        if (nonceInput) {
            return nonceInput.value;
        }
        
        // Fallback: genera nonce temporaneo
        return 'temp_nonce_' + Date.now();
    }
    
    /**
     * Gestione Form Autenticazione
     */
    function initAuthForms() {
        // Form login
        $(document).on('submit', '#naval-login-form', function(e) {
            e.preventDefault();
            handleLogin($(this));
        });
        
        // Form registrazione
        $(document).on('submit', '#naval-register-form', function(e) {
            e.preventDefault();
            handleRegistration($(this));
        });
        
        // Validazione password in tempo reale
        $(document).on('input', '#reg-password-confirm', function() {
            validatePasswordConfirm();
        });
        
        // Validazione Partita IVA quando si inserisce Ragione Sociale
        $(document).on('input', '#reg-ragione-sociale', function() {
            togglePartitaIvaRequired();
        });
        
        // Show/hide password
        $(document).on('click', '.toggle-password', function() {
            togglePasswordVisibility($(this));
        });
        
        // Tab switching per auth
        $(document).on('click', '.auth-tab', function() {
            const targetTab = $(this).data('tab');
            
            $('.auth-tab').removeClass('active');
            $('.auth-tab-content').removeClass('active');
            
            $(this).addClass('active');
            $('#tab-' + targetTab).addClass('active');
        });
    }
    
    function handleLogin(form) {
        const submitBtn = form.find('button[type="submit"]');
        const btnText = submitBtn.find('.btn-text');
        const btnLoading = submitBtn.find('.btn-loading');
        
        // Disabilita form durante invio
        form.find('input, button').prop('disabled', true);
        btnText.hide();
        btnLoading.show();
        
        showLoading('Accesso in corso...');
        
        const formData = new FormData(form[0]);
        formData.append('action', 'naval_egt_login');
        
        $.ajax({
            url: naval_egt_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                hideLoading();
                
                if (response.success) {
                    showMessage('Accesso effettuato con successo!', 'success');
                    
                    // Redirect o reload dopo 1 secondo
                    setTimeout(function() {
                        if (response.data && response.data.redirect) {
                            window.location.href = response.data.redirect;
                        } else {
                            window.location.reload();
                        }
                    }, 1000);
                } else {
                    showMessage(response.data || 'Errore durante l\'accesso', 'error');
                    form.find('input, button').prop('disabled', false);
                    btnText.show();
                    btnLoading.hide();
                }
            },
            error: function() {
                hideLoading();
                showMessage('Errore di connessione. Riprova.', 'error');
                form.find('input, button').prop('disabled', false);
                btnText.show();
                btnLoading.hide();
            }
        });
    }
    
    function handleRegistration(form) {
        // Validazione client-side
        if (!validateRegistrationForm(form)) {
            return;
        }
        
        const submitBtn = form.find('button[type="submit"]');
        const btnText = submitBtn.find('.btn-text');
        const btnLoading = submitBtn.find('.btn-loading');
        
        // Disabilita form durante invio
        form.find('input, button').prop('disabled', true);
        btnText.hide();
        btnLoading.show();
        
        showLoading('Invio richiesta di registrazione...');
        
        const formData = new FormData(form[0]);
        formData.append('action', 'naval_egt_register');
        
        $.ajax({
            url: naval_egt_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                hideLoading();
                
                if (response.success) {
                    showMessage(response.data.message || 'Richiesta inviata con successo!', 'success');
                    form[0].reset();
                    
                    // Mostra messaggio di conferma dettagliato
                    showRegistrationSuccess(response.data);
                    
                    // Torna al login dopo 3 secondi
                    setTimeout(function() {
                        $('.auth-tab[data-tab="login"]').click();
                    }, 3000);
                } else {
                    showMessage(response.data || 'Errore durante la registrazione', 'error');
                }
                
                form.find('input, button').prop('disabled', false);
                btnText.show();
                btnLoading.hide();
            },
            error: function() {
                hideLoading();
                showMessage('Errore di connessione. Riprova.', 'error');
                form.find('input, button').prop('disabled', false);
                btnText.show();
                btnLoading.hide();
            }
        });
    }
    
    function validateRegistrationForm(form) {
        const password = form.find('#reg-password').val();
        const passwordConfirm = form.find('#reg-password-confirm').val();
        const ragioneSociale = form.find('#reg-ragione-sociale').val();
        const partitaIva = form.find('#reg-partita-iva').val();
        const privacyPolicy = form.find('#reg-privacy-policy').is(':checked');
        
        // Verifica password
        if (password !== passwordConfirm) {
            showMessage('Le password non corrispondono', 'error');
            form.find('#reg-password-confirm').focus();
            return false;
        }
        
        if (password.length < 6) {
            showMessage('La password deve essere di almeno 6 caratteri', 'error');
            form.find('#reg-password').focus();
            return false;
        }
        
        // Verifica P.IVA se ragione sociale presente
        if (ragioneSociale && !partitaIva) {
            showMessage('La Partita IVA è obbligatoria se si specifica la Ragione Sociale', 'error');
            form.find('#reg-partita-iva').focus();
            return false;
        }
        
        // Verifica privacy policy
        if (!privacyPolicy) {
            showMessage('È necessario accettare la Privacy Policy', 'error');
            return false;
        }
        
        return true;
    }
    
    function validatePasswordConfirm() {
        const password = $('#reg-password').val();
        const passwordConfirm = $('#reg-password-confirm').val();
        const confirmField = $('#reg-password-confirm');
        
        if (passwordConfirm && password !== passwordConfirm) {
            confirmField.css('border-color', '#dc3545');
            showMessage('Le password non corrispondono', 'warning');
        } else if (passwordConfirm) {
            confirmField.css('border-color', '#28a745');
        }
    }
    
    function togglePartitaIvaRequired() {
        const ragioneSociale = $('#reg-ragione-sociale').val();
        const partitaIvaField = $('#reg-partita-iva');
        const helpText = partitaIvaField.siblings('small');
        
        if (ragioneSociale.trim()) {
            partitaIvaField.prop('required', true);
            helpText.html('<strong>Obbligatoria se specificata la Ragione Sociale</strong>');
            partitaIvaField.addClass('required');
        } else {
            partitaIvaField.prop('required', false);
            helpText.html('Obbligatoria se specificata la Ragione Sociale');
            partitaIvaField.removeClass('required');
        }
    }
    
    function showRegistrationSuccess(data) {
        const successHtml = `
            <div class="registration-success" style="text-align: center; padding: 30px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 8px; color: #155724;">
                <div class="success-icon" style="font-size: 48px; margin-bottom: 20px;">✅</div>
                <h3>Registrazione completata!</h3>
                <p>La tua richiesta è stata inviata con successo.</p>
                <div class="user-code-box" style="background: #c3e6cb; padding: 15px; border-radius: 6px; margin: 20px 0;">
                    <strong>Il tuo codice utente è: ${data.user_code || 'TBD'}</strong>
                </div>
                <p>Il tuo account sarà attivato manualmente dal nostro staff. Riceverai una email di conferma.</p>
                <p>Per assistenza contatta: <a href="mailto:tecnica@naval.it">tecnica@naval.it</a></p>
            </div>
        `;
        
        $('#tab-register .auth-card').html(successHtml);
    }
    
    function togglePasswordVisibility(button) {
        const inputWrapper = button.closest('.input-wrapper');
        const input = inputWrapper.find('input');
        const icon = button.find('.dashicons');
        
        if (input.attr('type') === 'password') {
            input.attr('type', 'text');
            icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
        } else {
            input.attr('type', 'password');
            icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
        }
    }
    
    /**
     * Gestione Upload File
     */
    function initFileUpload() {
        // Tab dashboard switching
        $(document).on('click', '.dashboard-tab', function() {
            const targetTab = $(this).data('tab');
            
            $('.dashboard-tab').removeClass('active');
            $('.dashboard-tab-content').removeClass('active');
            
            $(this).addClass('active');
            $('#tab-' + targetTab).addClass('active');
            
            // Carica contenuto specifico del tab
            switch(targetTab) {
                case 'files':
                    loadUserFiles();
                    break;
                case 'activity':
                    loadUserActivity();
                    break;
            }
        });
        
        // Upload area click
        $(document).on('click', '#upload-drop-zone', function() {
            $('#files-input').click();
        });
        
        // Input file change
        $(document).on('change', '#files-input', function() {
            const files = this.files;
            if (files.length > 0) {
                addFilesToQueue(files);
            }
        });
        
        // Drag & Drop
        $(document).on('dragover', '#upload-drop-zone', function(e) {
            e.preventDefault();
            $(this).addClass('drag-over');
        });
        
        $(document).on('dragleave', '#upload-drop-zone', function(e) {
            e.preventDefault();
            $(this).removeClass('drag-over');
        });
        
        $(document).on('drop', '#upload-drop-zone', function(e) {
            e.preventDefault();
            $(this).removeClass('drag-over');
            
            const files = e.originalEvent.dataTransfer.files;
            if (files.length > 0) {
                addFilesToQueue(files);
            }
        });
        
        // Upload submit
        $(document).on('submit', '#files-upload-form', function(e) {
            e.preventDefault();
            handleFileUpload();
        });
        
        // Logout
        $(document).on('click', '#logout-btn', function() {
            handleLogout();
        });
        
        // Refresh data
        $(document).on('click', '#refresh-data', function() {
            loadUserStats();
            loadUserFiles();
        });
    }
    
    function addFilesToQueue(files) {
        // Validazione file
        const validFiles = [];
        const errors = [];
        
        Array.from(files).forEach(file => {
            const validation = validateFile(file);
            if (validation.valid) {
                validFiles.push(file);
            } else {
                errors.push(`${file.name}: ${validation.error}`);
            }
        });
        
        if (errors.length > 0) {
            showMessage('Alcuni file non sono validi:\n' + errors.join('\n'), 'error');
        }
        
        if (validFiles.length > 0) {
            selectedFiles = [...(selectedFiles || []), ...validFiles];
            renderSelectedFiles();
            $('#upload-submit').show();
        }
    }
    
    let selectedFiles = [];
    
    function renderSelectedFiles() {
        const container = $('#selected-files');
        
        const html = selectedFiles.map((file, index) => `
            <div class="selected-file">
                <div class="file-info">
                    <span class="dashicons ${getFileIcon(file.name)}"></span>
                    <span>${escapeHtml(file.name)} (${formatFileSize(file.size)})</span>
                </div>
                <button type="button" class="remove-file" data-index="${index}">×</button>
            </div>
        `).join('');
        
        container.html(html);
        
        // Event listener per rimozione file
        container.find('.remove-file').on('click', function() {
            const index = parseInt($(this).data('index'));
            selectedFiles.splice(index, 1);
            renderSelectedFiles();
            
            if (selectedFiles.length === 0) {
                $('#upload-submit').hide();
            }
        });
    }
    
    function validateFile(file) {
        // Tipi consentiti (dovrebbe essere sincronizzato con PHP)
        const allowedTypes = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'dwg', 'dxf', 'zip', 'rar'];
        const maxSize = 20 * 1024 * 1024; // 20MB
        
        const extension = file.name.split('.').pop().toLowerCase();
        
        if (!allowedTypes.includes(extension)) {
            return { valid: false, error: 'Tipo di file non consentito' };
        }
        
        if (file.size > maxSize) {
            return { valid: false, error: 'File troppo grande (max 20MB)' };
        }
        
        return { valid: true };
    }
    
    function handleFileUpload() {
        if (!selectedFiles || selectedFiles.length === 0) {
            showMessage('Seleziona almeno un file', 'warning');
            return;
        }
        
        if (isUploading) {
            showMessage('Upload già in corso, attendi...', 'warning');
            return;
        }
        
        isUploading = true;
        
        const formData = new FormData();
        formData.append('action', 'naval_egt_upload_file');
        formData.append('nonce', naval_egt_ajax.nonce);
        
        selectedFiles.forEach(file => {
            formData.append('files[]', file);
        });
        
        showLoading(`Caricamento ${selectedFiles.length} file...`);
        
        $.ajax({
            url: naval_egt_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            xhr: function() {
                const xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        const percentage = Math.round((e.loaded / e.total) * 100);
                        updateUploadProgress(percentage);
                    }
                }, false);
                return xhr;
            },
            success: function(response) {
                hideLoading();
                isUploading = false;
                
                if (response.success) {
                    showMessage(response.data.message || 'File caricati con successo!', 'success');
                    
                    // Reset
                    selectedFiles = [];
                    renderSelectedFiles();
                    $('#files-input').val('');
                    $('#upload-submit').hide();
                    
                    // Refresh dati
                    loadUserFiles();
                    loadUserStats();
                    
                    // Vai al tab file
                    $('.dashboard-tab[data-tab="files"]').click();
                } else {
                    showMessage(response.data || 'Errore durante il caricamento', 'error');
                }
            },
            error: function() {
                hideLoading();
                isUploading = false;
                showMessage('Errore di connessione durante il caricamento', 'error');
            }
        });
    }
    
    function updateUploadProgress(percentage) {
        const loadingDiv = $('#naval-loading');
        if (loadingDiv.length > 0) {
            loadingDiv.find('p').html(`Caricamento... ${percentage}%`);
        }
    }
    
    /**
     * Gestione Download File
     */
    function initFileDownload() {
        $(document).on('click', '.btn-download', function() {
            const url = $(this).attr('href');
            if (url) {
                // Il browser gestirà il download tramite href
                setTimeout(loadUserActivity, 2000); // Aggiorna attività dopo download
            }
        });
        
        $(document).on('click', '.delete-file-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const fileId = $(this).data('file-id');
            deleteFile(fileId);
        });
    }
    
    function deleteFile(fileId) {
        if (!confirm('Sei sicuro di voler eliminare questo file?')) return;
        
        $.post(naval_egt_ajax.ajax_url, {
            action: 'naval_egt_delete_file',
            nonce: naval_egt_ajax.nonce,
            file_id: fileId
        }, function(response) {
            if (response.success) {
                showMessage('File eliminato con successo', 'success');
                loadUserFiles();
                loadUserStats();
            } else {
                showMessage(response.data || 'Errore durante l\'eliminazione', 'error');
            }
        });
    }
    
    /**
     * Dashboard Functions
     */
    function handleLogout() {
        if (!confirm('Sei sicuro di voler uscire?')) return;
        
        $.post(naval_egt_ajax.ajax_url, {
            action: 'naval_egt_logout',
            nonce: naval_egt_ajax.nonce
        }, function(response) {
            if (response.success) {
                showMessage('Logout effettuato con successo', 'success');
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            }
        });
    }
    
    function loadUserStats() {
        $.post(naval_egt_ajax.ajax_url, {
            action: 'naval_egt_get_user_stats',
            nonce: naval_egt_ajax.nonce
        }, function(response) {
            if (response.success) {
                updateStatsDisplay(response.data);
            }
        });
    }
    
    function updateStatsDisplay(stats) {
        $('#total-files-count').text(stats.total_files || 0);
        $('#total-storage-size').text(formatFileSize(stats.total_size || 0));
        
        if (stats.last_upload) {
            const date = new Date(stats.last_upload).toLocaleDateString('it-IT');
            $('#last-upload-date').text(date);
        } else {
            $('#last-upload-date').text('Mai');
        }
    }
    
    function loadUserFiles() {
        showLoading('Caricamento file...');
        
        $.post(naval_egt_ajax.ajax_url, {
            action: 'naval_egt_get_user_files',
            nonce: naval_egt_ajax.nonce,
            page: 1,
            search: $('#files-search').val() || ''
        }, function(response) {
            hideLoading();
            
            if (response.success) {
                displayFiles(response.data.files);
            } else {
                showMessage('Errore nell\'aggiornamento file', 'error');
            }
        });
    }
    
    function loadUserActivity() {
        $.post(naval_egt_ajax.ajax_url, {
            action: 'naval_egt_get_user_activity',
            nonce: naval_egt_ajax.nonce,
            page: 1
        }, function(response) {
            if (response.success) {
                displayActivity(response.data.activities);
            }
        });
    }
    
    function refreshUserData() {
        loadUserFiles();
        loadUserActivity();
        loadUserStats();
    }
    
    /**
     * Display Functions
     */
    function displayFiles(files) {
        const filesGrid = $('#files-list');
        const noFilesMessage = $('#no-files-message');
        
        if (!files || files.length === 0) {
            filesGrid.hide();
            noFilesMessage.show();
            return;
        }
        
        filesGrid.show();
        noFilesMessage.hide();
        
        let html = '';
        files.forEach(file => {
            const fileIcon = getFileIcon(file.name);
            
            html += `
                <div class="file-card" data-file-id="${file.id}">
                    <div class="file-header">
                        <span class="file-icon dashicons ${fileIcon}"></span>
                        <div class="file-name" title="${file.name}">${file.name}</div>
                    </div>
                    <div class="file-meta">
                        <span class="file-size">${file.size}</span>
                        <span class="file-date">${file.date}</span>
                    </div>
                    <div class="file-actions">
                        <a href="${file.download_url}" class="btn-small btn-download" title="Scarica" target="_blank">
                            <span class="dashicons dashicons-download"></span> Scarica
                        </a>
                        <button type="button" class="btn-small btn-danger delete-file-btn" data-file-id="${file.id}" title="Elimina">
                            <span class="dashicons dashicons-trash"></span> Elimina
                        </button>
                    </div>
                </div>
            `;
        });
        
        filesGrid.html(html);
        
        // Animazione fade in
        filesGrid.find('.file-card').css('opacity', 0).animate({ opacity: 1 }, 300);
    }
    
    function displayActivity(activities) {
        const activityList = $('#activity-list');
        const noActivityMessage = $('#no-activity-message');
        
        if (!activities || activities.length === 0) {
            activityList.hide();
            noActivityMessage.show();
            return;
        }
        
        activityList.show();
        noActivityMessage.hide();
        
        let html = '';
        activities.forEach(activity => {
            const activityIcon = getActivityIcon(activity.action);
            
            html += `
                <div class="activity-item">
                    <div class="activity-icon">
                        <span class="dashicons ${activityIcon}"></span>
                    </div>
                    <div class="activity-content">
                        <div class="activity-title">${activity.action}</div>
                        <div class="activity-description">${escapeHtml(activity.description)}</div>
                        <div class="activity-meta">${activity.date} - IP: ${activity.ip_address}</div>
                    </div>
                </div>
            `;
        });
        
        activityList.html(html);
    }
    
    /**
     * Utility Functions
     */
    function getFileIcon(fileName) {
        const extension = fileName.split('.').pop().toLowerCase();
        const icons = {
            pdf: 'dashicons-pdf',
            doc: 'dashicons-media-document', docx: 'dashicons-media-document',
            xls: 'dashicons-media-spreadsheet', xlsx: 'dashicons-media-spreadsheet',
            jpg: 'dashicons-format-image', jpeg: 'dashicons-format-image', 
            png: 'dashicons-format-image', gif: 'dashicons-format-image',
            dwg: 'dashicons-admin-tools', dxf: 'dashicons-admin-tools',
            zip: 'dashicons-media-archive', rar: 'dashicons-media-archive'
        };
        return icons[extension] || 'dashicons-media-default';
    }
    
    function getActivityIcon(action) {
        const icons = {
            'Accesso': 'dashicons-unlock',
            'Disconnessione': 'dashicons-lock',
            'Caricamento file': 'dashicons-upload',
            'Scaricamento file': 'dashicons-download',
            'Eliminazione file': 'dashicons-trash',
            'Registrazione': 'dashicons-plus'
        };
        return icons[action] || 'dashicons-admin-generic';
    }
    
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }
    
    /**
     * UI Functions
     */
    function showMessage(message, type = 'info') {
        // Rimuovi messaggi esistenti
        $('.naval-message').remove();
        
        const messageDiv = $(`
            <div class="naval-message naval-message-${type}">
                <span>${escapeHtml(message)}</span>
                <button type="button" class="message-close">&times;</button>
            </div>
        `);
        
        $('body').append(messageDiv);
        
        // Auto-remove dopo 5 secondi
        setTimeout(() => {
            messageDiv.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
        
        // Click per chiudere
        messageDiv.find('.message-close').on('click', function() {
            messageDiv.fadeOut(300, function() {
                $(this).remove();
            });
        });
    }
    
    function showLoading(message = 'Caricamento...') {
        hideLoading(); // Rimuovi loading esistente
        
        const loadingHtml = `
            <div id="naval-loading" class="naval-loading">
                <div class="loading-content">
                    <div class="spinner"></div>
                    <p>${message}</p>
                </div>
            </div>
        `;
        $('body').append(loadingHtml);
    }
    
    function hideLoading() {
        $('#naval-loading').remove();
    }
    
    /**
     * Responsive Handlers
     */
    function initResponsiveHandlers() {
        // Mobile menu toggle
        $(window).on('resize', function() {
            // Gestione resize se necessario
        });
    }
    
    /**
     * Animazioni e UX
     */
    function initAnimations() {
        // Hover effects per file items
        $(document).on('mouseenter', '.file-card', function() {
            $(this).addClass('hover');
        });
        
        $(document).on('mouseleave', '.file-card', function() {
            $(this).removeClass('hover');
        });
        
        // Click animations
        $(document).on('click', '.btn-small', function() {
            $(this).addClass('clicked');
            setTimeout(() => {
                $(this).removeClass('clicked');
            }, 150);
        });
    }
    
    // Esponi funzioni globali se necessario
    window.navalEgt = {
        refreshFiles: loadUserFiles,
        refreshActivity: loadUserActivity,
        showMessage: showMessage
    };
    
})(jQuery);