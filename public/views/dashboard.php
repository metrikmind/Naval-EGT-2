<?php
/**
 * Template per la Dashboard utenti - VERSIONE FINALE CORRETTA
 */

if (!defined('ABSPATH')) {
    exit;
}

// Ottieni utente corrente
$current_user = Naval_EGT_User_Manager::get_current_user();

if (!$current_user) {
    wp_redirect(home_url('/area-riservata-naval-egt/'));
    exit;
}
?>

<div id="naval-egt-dashboard" class="naval-egt-container">
    <div class="dashboard-wrapper">
        <!-- Header Dashboard -->
        <div class="dashboard-header">
            <div class="header-content">
                <div class="logo-section">
                    <h1>🚢 Naval EGT Dashboard</h1>
                </div>
                <div class="user-section">
                    <span class="welcome-text">Benvenuto, <?php echo esc_html($current_user['nome']); ?></span>
                    <button type="button" class="btn-logout" id="logout-btn">
                        <span class="logout-icon">🚪</span> 
                        <span class="logout-text">Logout</span>
                        <span class="logout-loading" style="display: none;">⏳ Uscita...</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="dashboard-content">
            <!-- User Info Card -->
            <div class="user-info-card">
                <div class="card-header">
                    <h2>📋 Informazioni di Contatto</h2>
                </div>
                <div class="card-body">
                    <div class="info-grid">
                        <div class="info-item">
                            <label>Nome Completo:</label>
                            <span><?php echo esc_html($current_user['nome'] . ' ' . $current_user['cognome']); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Codice Utente:</label>
                            <span class="user-code"><?php echo esc_html($current_user['user_code']); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Email:</label>
                            <span><?php echo esc_html($current_user['email']); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Username:</label>
                            <span><?php echo esc_html($current_user['username']); ?></span>
                        </div>
                        <?php if (!empty($current_user['telefono'])): ?>
                        <div class="info-item">
                            <label>Telefono:</label>
                            <span><?php echo esc_html($current_user['telefono']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($current_user['ragione_sociale'])): ?>
                        <div class="info-item">
                            <label>Azienda:</label>
                            <span><?php echo esc_html($current_user['ragione_sociale']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($current_user['partita_iva'])): ?>
                        <div class="info-item">
                            <label>Partita IVA:</label>
                            <span><?php echo esc_html($current_user['partita_iva']); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="info-item">
                            <label>Status Account:</label>
                            <span class="status-badge status-<?php echo strtolower($current_user['status']); ?>">
                                <?php echo esc_html($current_user['status']); ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <label>Registrato il:</label>
                            <span><?php echo mysql2date('d/m/Y', $current_user['created_at']); ?></span>
                        </div>
                        <?php if (!empty($current_user['last_login'])): ?>
                        <div class="info-item">
                            <label>Ultimo Accesso:</label>
                            <span><?php echo mysql2date('d/m/Y H:i', $current_user['last_login']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Dropbox Section -->
            <?php 
            $dropbox_folder_link = '';
            if (!empty($current_user['dropbox_folder'])) {
                if (strpos($current_user['dropbox_folder'], 'http') === false) {
                    $dropbox_folder_link = 'https://www.dropbox.com/home' . $current_user['dropbox_folder'];
                } else {
                    $dropbox_folder_link = $current_user['dropbox_folder'];
                }
            }
            ?>
            
            <?php if (!empty($dropbox_folder_link)): ?>
            <div class="dropbox-card-section">
                <div class="card-header">
                    <h2>🗂️ Cartella Dropbox</h2>
                </div>
                <div class="card-body">
                    <div class="dropbox-card">
                        <div class="dropbox-icon">📁</div>
                        <div class="dropbox-details">
                            <h4>La tua cartella personale</h4>
                            <p>Accedi alla cartella Dropbox dedicata per scaricare e visualizzare i tuoi documenti</p>
                            <a href="<?php echo esc_url($dropbox_folder_link); ?>" target="_blank" class="dropbox-link">
                                <span>🔗</span> Apri Cartella Dropbox
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Actions Card -->
            <div class="actions-card">
                <div class="card-header">
                    <h2>🚀 Azioni Rapide</h2>
                </div>
                <div class="card-body">
                    <div class="actions-grid">
                        <a href="<?php echo home_url('/area-riservata-naval-egt/'); ?>" class="action-button">
                            <span class="action-icon">🏠</span>
                            <span class="action-text">Torna all'Area Riservata</span>
                            <span class="action-arrow">→</span>
                        </a>
                        
                        <a href="mailto:tecnica@naval.it" class="action-button">
                            <span class="action-icon">📧</span>
                            <span class="action-text">Contatta Supporto</span>
                            <span class="action-arrow">→</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Support Card -->
            <div class="support-card">
                <div class="card-header">
                    <h2>💡 Supporto e Assistenza</h2>
                </div>
                <div class="card-body">
                    <p>Per qualsiasi necessità o richiesta di assistenza, non esitare a contattarci:</p>
                    <div class="support-contacts">
                        <div class="contact-item">
                            <strong>📧 Email:</strong> <a href="mailto:tecnica@naval.it">tecnica@naval.it</a>
                        </div>
                        <div class="contact-item">
                            <strong>📍 Sede:</strong> Via Pietro Castellino, 45 - 80128 Napoli (NA)
                        </div>
                        <div class="contact-item">
                            <strong>🌐 Web:</strong> <a href="https://naval.vjformazione.it" target="_blank">naval.vjformazione.it</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Dashboard Styles */
.naval-egt-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}

.dashboard-wrapper {
    background: #f8f9fa;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 10px 40px rgba(0,0,0,0.1);
}

/* Header */
.dashboard-header {
    background: linear-gradient(135deg, #0073aa, #005a87);
    padding: 30px;
}

.header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
}

.logo-section h1 {
    margin: 0;
    color: white;
    font-size: 28px;
    font-weight: 700;
}

.user-section {
    display: flex;
    align-items: center;
    gap: 20px;
}

.welcome-text {
    color: white;
    font-size: 16px;
}

.btn-logout {
    background: rgba(239, 68, 68, 0.9);
    color: white;
    border: 2px solid rgba(239, 68, 68, 0.5);
    padding: 12px 24px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
    backdrop-filter: blur(10px);
}

.btn-logout:hover:not(:disabled) {
    background: rgba(239, 68, 68, 1);
    border-color: rgba(239, 68, 68, 0.8);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}

.btn-logout:disabled {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none;
}

/* Main Content */
.dashboard-content {
    padding: 30px;
    display: grid;
    gap: 30px;
}

/* Cards */
.user-info-card,
.dropbox-card-section,
.actions-card,
.support-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.08);
    overflow: hidden;
}

.card-header {
    background: #f8f9fa;
    padding: 20px 30px;
    border-bottom: 2px solid #e9ecef;
}

.card-header h2 {
    margin: 0;
    font-size: 20px;
    color: #333;
}

.card-body {
    padding: 30px;
}

/* Info Grid */
.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.info-item {
    display: flex;
    flex-direction: column;
    gap: 5px;
    padding: 10px 0;
    border-bottom: 1px solid #f1f3f4;
}

.info-item label {
    font-weight: 600;
    color: #666;
    font-size: 14px;
}

.info-item span {
    color: #333;
    font-size: 16px;
}

.user-code {
    font-family: monospace;
    background: #e9ecef;
    padding: 4px 8px;
    border-radius: 4px;
    font-weight: 600;
}

.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    width: fit-content;
}

.status-attivo {
    background: #d4edda;
    color: #155724;
}

.status-sospeso {
    background: #f8d7da;
    color: #721c24;
}

/* Dropbox Card */
.dropbox-card {
    display: flex;
    align-items: center;
    gap: 25px;
    padding: 25px;
    background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
    border: 2px solid #0ea5e9;
    border-radius: 12px;
    transition: all 0.3s ease;
}

.dropbox-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(14, 165, 233, 0.2);
}

.dropbox-icon {
    font-size: 40px;
    width: 60px;
    height: 60px;
    background: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #0ea5e9;
    box-shadow: 0 3px 10px rgba(14, 165, 233, 0.2);
}

.dropbox-details h4 {
    margin: 0 0 10px 0;
    color: #1e3a8a;
    font-size: 18px;
    font-weight: 700;
}

.dropbox-details p {
    margin: 0 0 15px 0;
    color: #64748b;
    line-height: 1.4;
}

.dropbox-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #0ea5e9;
    color: white;
    text-decoration: none;
    padding: 10px 18px;
    border-radius: 6px;
    font-weight: 600;
    transition: all 0.3s ease;
    font-size: 14px;
}

.dropbox-link:hover {
    background: #0284c7;
    transform: translateX(3px);
}

/* Actions Grid */
.actions-grid {
    display: grid;
    gap: 20px;
}

.action-button {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px 25px;
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    border: 2px solid transparent;
    border-radius: 12px;
    text-decoration: none;
    color: #333;
    transition: all 0.3s;
    cursor: pointer;
}

.action-button:hover {
    background: linear-gradient(135deg, #e9ecef, #dee2e6);
    border-color: #0073aa;
    transform: translateX(5px);
    box-shadow: 0 4px 12px rgba(0, 115, 170, 0.2);
}

.action-icon {
    font-size: 24px;
    margin-right: 15px;
}

.action-text {
    flex: 1;
    font-weight: 600;
    font-size: 16px;
}

.action-arrow {
    font-size: 20px;
    font-weight: bold;
}

/* Support Contacts */
.support-contacts {
    display: flex;
    flex-direction: column;
    gap: 15px;
    margin-top: 20px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
}

.contact-item {
    font-size: 16px;
}

.contact-item a {
    color: #0073aa;
    text-decoration: none;
}

.contact-item a:hover {
    text-decoration: underline;
}

/* Toast Notifications */
.toast {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 10000;
    padding: 16px 24px;
    border-radius: 8px;
    color: white;
    font-weight: 600;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
    animation: slideIn 0.3s ease;
    max-width: 300px;
    word-wrap: break-word;
}

.toast-success {
    background: linear-gradient(135deg, #10b981, #059669);
}

.toast-error {
    background: linear-gradient(135deg, #ef4444, #dc2626);
}

.toast-info {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
}

@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes slideOut {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(100%);
        opacity: 0;
    }
}

/* Responsive */
@media (max-width: 768px) {
    .naval-egt-container {
        padding: 15px;
    }
    
    .header-content {
        flex-direction: column;
        text-align: center;
    }
    
    .user-section {
        flex-direction: column;
        width: 100%;
    }
    
    .btn-logout {
        width: 100%;
        justify-content: center;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .dashboard-content {
        padding: 20px;
    }
    
    .card-body {
        padding: 20px;
    }
    
    .action-button {
        padding: 15px 20px;
    }
    
    .action-icon {
        font-size: 20px;
    }
    
    .action-text {
        font-size: 14px;
    }
    
    .dropbox-card {
        flex-direction: column;
        text-align: center;
        gap: 15px;
    }
    
    .toast {
        left: 20px;
        right: 20px;
        top: 10px;
        max-width: none;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const logoutBtn = document.getElementById('logout-btn');
    const ajaxUrl = '<?php echo admin_url("admin-ajax.php"); ?>';
    const nonce = '<?php echo wp_create_nonce("naval_egt_nonce"); ?>';
    
    if (logoutBtn) {
        logoutBtn.addEventListener('click', handleLogout);
    }
    
    async function handleLogout() {
        if (!confirm('Sei sicuro di voler uscire dalla dashboard?')) {
            return;
        }
        
        const logoutIcon = logoutBtn.querySelector('.logout-icon');
        const logoutText = logoutBtn.querySelector('.logout-text');
        const logoutLoading = logoutBtn.querySelector('.logout-loading');
        
        try {
            logoutBtn.disabled = true;
            if (logoutIcon) logoutIcon.style.display = 'none';
            if (logoutText) logoutText.style.display = 'none';
            if (logoutLoading) logoutLoading.style.display = 'inline';
            
            if (!nonce) {
                throw new Error('Token di sicurezza non disponibile');
            }
            
            const formData = new FormData();
            formData.append('action', 'naval_egt_logout');
            formData.append('nonce', nonce);
            
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 10000);
            
            const response = await fetch(ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                signal: controller.signal
            });
            
            clearTimeout(timeoutId);
            
            if (!response.ok) {
                throw new Error(`Errore HTTP ${response.status}: ${response.statusText}`);
            }
            
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const textResponse = await response.text();
                console.warn('Risposta non JSON:', textResponse);
                throw new Error('Risposta del server non valida (non JSON)');
            }
            
            const result = await response.json();
            
            if (result.success) {
                showToast('Logout effettuato con successo! Reindirizzamento in corso...', 'success');
                
                if (typeof(Storage) !== "undefined") {
                    localStorage.removeItem('naval_egt_user');
                    sessionStorage.clear();
                }
                
                setTimeout(() => {
                    const redirectUrl = '<?php echo home_url("/area-riservata-naval-egt/"); ?>?t=' + Date.now();
                    window.location.replace(redirectUrl);
                }, 1500);
                
            } else {
                const errorMessage = result.data || 'Errore sconosciuto durante il logout';
                showToast(`Errore: ${errorMessage}`, 'error');
                console.error('Logout error:', result);
                
                setTimeout(() => {
                    const redirectUrl = '<?php echo home_url("/area-riservata-naval-egt/"); ?>?t=' + Date.now();
                    window.location.replace(redirectUrl);
                }, 3000);
            }
            
        } catch (error) {
            let errorMessage = 'Errore di connessione';
            
            if (error.name === 'AbortError') {
                errorMessage = 'Timeout della richiesta';
            } else if (error.message) {
                errorMessage = error.message;
            }
            
            console.error('Errore durante logout:', error);
            showToast(`${errorMessage}. Reindirizzamento in corso...`, 'error');
            
            setTimeout(() => {
                const redirectUrl = '<?php echo home_url("/area-riservata-naval-egt/"); ?>?t=' + Date.now();
                window.location.replace(redirectUrl);
            }, 2000);
            
        } finally {
            setTimeout(() => {
                if (logoutBtn && logoutBtn.disabled) {
                    logoutBtn.disabled = false;
                    if (logoutIcon) logoutIcon.style.display = 'inline';
                    if (logoutText) logoutText.style.display = 'inline';
                    if (logoutLoading) logoutLoading.style.display = 'none';
                }
            }, 3000);
        }
    }
    
    function showToast(message, type = 'info', duration = 4000) {
        const existingToasts = document.querySelectorAll(`.toast-${type}`);
        existingToasts.forEach(toast => toast.remove());
        
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.textContent = message;
        
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            padding: 16px 24px;
            border-radius: 8px;
            color: white;
            font-weight: 600;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
            animation: slideIn 0.3s ease;
            max-width: 300px;
            word-wrap: break-word;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        `;
        
        if (type === 'success') {
            toast.style.background = 'linear-gradient(135deg, #10b981, #059669)';
        } else if (type === 'error') {
            toast.style.background = 'linear-gradient(135deg, #ef4444, #dc2626)';
        } else {
            toast.style.background = 'linear-gradient(135deg, #3b82f6, #2563eb)';
        }
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            if (toast && toast.parentNode) {
                toast.style.animation = 'slideOut 0.3s ease forwards';
                setTimeout(() => {
                    if (toast && toast.parentNode) {
                        toast.remove();
                    }
                }, 300);
            }
        }, duration);
    }
    
    console.log('Dashboard script loaded');
    console.log('AJAX URL:', ajaxUrl);
    console.log('Nonce available:', !!nonce);
});
</script>