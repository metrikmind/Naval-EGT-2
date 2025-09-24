<?php
/**
 * Template area riservata - VERSIONE CORRETTA FINALE CON DEBUG PASSWORD
 */

if (!defined('ABSPATH')) {
    exit;
}

$current_user = Naval_EGT_User_Manager::get_current_user();
$is_logged_in = !empty($current_user);
?>

<div id="naval-egt-area-riservata" class="naval-egt-container">
    
    <?php if (!$is_logged_in): ?>
        <!-- SEZIONE LOGIN/REGISTRAZIONE -->
        <div class="forms-section">
            <!-- LOGIN FORM -->
            <div class="login-box">
                <div class="login-header">
                    <div class="login-icon">🔐</div>
                    <h3>Accedi al tuo account</h3>
                </div>

                <form id="naval-login-form">
                    <div class="form-group">
                        <label for="login-email">Email o Username</label>
                        <input type="text" id="login-email" name="login" required>
                    </div>

                    <div class="form-group">
                        <label for="login-password">Password</label>
                        <input type="password" id="login-password" name="password" required>
                    </div>

                    <div class="form-group checkbox-group">
                        <input type="checkbox" id="remember" name="remember" value="1">
                        <label for="remember">Ricordami per i prossimi accessi</label>
                    </div>

                    <button type="submit" class="btn-primary">
                        <span class="btn-text">Accedi all'Area Riservata</span>
                        <span class="btn-loading" style="display: none;">Caricamento...</span>
                    </button>
                </form>

                <div class="login-footer">
                    <p><strong>Non hai un account?</strong> 
                    <a href="#" class="register-toggle-link">Richiedi registrazione</a></p>
                </div>
            </div>

            <!-- REGISTRATION FORM -->
            <div class="register-box" style="display: none;">
                <div class="register-header">
                    <div class="register-icon">📝</div>
                    <h3>Richiedi Registrazione</h3>
                </div>

                <form id="naval-register-form">
                    <div class="form-group">
                        <label for="register-nome">Nome *</label>
                        <input type="text" id="register-nome" name="nome" required>
                    </div>

                    <div class="form-group">
                        <label for="register-cognome">Cognome *</label>
                        <input type="text" id="register-cognome" name="cognome" required>
                    </div>

                    <div class="form-group">
                        <label for="register-email">Email *</label>
                        <input type="email" id="register-email" name="email" required>
                    </div>

                    <div class="form-group">
                        <label for="register-username">Username *</label>
                        <input type="text" id="register-username" name="username" required minlength="4" maxlength="20" pattern="[a-zA-Z0-9._-]+">
                        <small style="color: #666; font-size: 12px;">Almeno 4 caratteri, solo lettere, numeri, punti e trattini</small>
                    </div>

                    <div class="form-group">
                        <label for="register-password">Password *</label>
                        <input type="password" id="register-password" name="password" required minlength="6">
                        <small style="color: #666; font-size: 12px;">Almeno 6 caratteri</small>
                    </div>

                    <div class="form-group">
                        <label for="register-password-confirm">Conferma Password *</label>
                        <input type="password" id="register-password-confirm" name="password_confirm" required minlength="6">
                    </div>

                    <div class="form-group">
                        <label for="register-telefono">Telefono</label>
                        <input type="tel" id="register-telefono" name="telefono">
                    </div>

                    <div class="form-group">
                        <label for="register-azienda">Ragione Sociale</label>
                        <input type="text" id="register-azienda" name="ragione_sociale">
                    </div>

                    <div class="form-group">
                        <label for="register-piva">Partita IVA</label>
                        <input type="text" id="register-piva" name="partita_iva">
                    </div>

                    <div class="form-group checkbox-group privacy-group">
                        <input type="checkbox" id="privacy-consent" name="privacy_consent" value="1" required>
                        <label for="privacy-consent">
                            Accetto l'informativa per il trattamento dei dati personali: Articoli 13 e 14 REGOLAMENTO EUROPEO N. 679/2016 D.lgs. 196/2003 novellato dal D.lgs. 101/2018.<a href="https://www.navalegt.it/privacy-policy-2/" target="_blank" class="privacy-link"> <b>Leggi di più</b></a>*
                        </label>
                    </div>

                    <button type="submit" class="btn-primary">
                        <span class="btn-text">Invia Richiesta di Registrazione</span>
                        <span class="btn-loading" style="display: none;">Invio in corso...</span>
                    </button>
                </form>

                <div class="register-footer">
                    <p><strong>Hai già un account?</strong> 
                    <a href="#" class="login-toggle-link">Torna al Login</a></p>
                </div>
            </div>
        </div>
        
        <div class="assistance-box">
            <div class="assistance-icon">📞</div>
            <h3>Assistenza Tecnica</h3>
            <p><strong>Email:</strong> <a href="mailto:tecnica@naval.it">tecnica@naval.it</a></p>
            <p>Per problemi di accesso, richieste di registrazione o supporto tecnico</p>
        </div>

    <?php else: ?>
        <!-- DASHBOARD UTENTE SEMPLIFICATA -->
        <div class="naval-egt-dashboard">
            <!-- Header con info utente -->
            <div class="dashboard-header">
                <div class="user-info">
                    <div class="user-avatar">👤</div>
                    <div class="user-details">
                        <h2>Benvenuto, <?php echo esc_html($current_user['nome'] . ' ' . $current_user['cognome']); ?></h2>
                        <p>Codice utente: <strong><?php echo esc_html($current_user['user_code']); ?></strong></p>
                        <?php if (!empty($current_user['ragione_sociale'])): ?>
                        <p>Azienda: <strong><?php echo esc_html($current_user['ragione_sociale']); ?></strong></p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="dashboard-actions">
                    <button type="button" class="btn-outline" id="logout-btn">
                        <span>🚪</span> <span class="logout-text">Logout</span>
                        <span class="logout-loading" style="display: none;">⏳ Uscita...</span>
                    </button>
                </div>
            </div>

            <!-- Scheda Anagrafica Completa -->
            <div class="dashboard-content">
                <div class="user-profile-section">
                    <div class="section-header">
                        <h3>📋 Scheda Anagrafica Completa</h3>
                    </div>
                    <div class="profile-grid">
                        <div class="profile-item">
                            <label>Nome Completo:</label>
                            <span><?php echo esc_html($current_user['nome'] . ' ' . $current_user['cognome']); ?></span>
                        </div>
                        <div class="profile-item">
                            <label>Codice Utente:</label>
                            <span class="user-code"><?php echo esc_html($current_user['user_code']); ?></span>
                        </div>
                        <div class="profile-item">
                            <label>Email:</label>
                            <span><?php echo esc_html($current_user['email']); ?></span>
                        </div>
                        <div class="profile-item">
                            <label>Username:</label>
                            <span><?php echo esc_html($current_user['username']); ?></span>
                        </div>
                        <?php if (!empty($current_user['telefono'])): ?>
                        <div class="profile-item">
                            <label>Telefono:</label>
                            <span><?php echo esc_html($current_user['telefono']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($current_user['ragione_sociale'])): ?>
                        <div class="profile-item">
                            <label>Ragione Sociale:</label>
                            <span><?php echo esc_html($current_user['ragione_sociale']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($current_user['partita_iva'])): ?>
                        <div class="profile-item">
                            <label>Partita IVA:</label>
                            <span><?php echo esc_html($current_user['partita_iva']); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="profile-item">
                            <label>Status Account:</label>
                            <span class="status-badge status-<?php echo strtolower($current_user['status']); ?>">
                                <?php echo esc_html($current_user['status']); ?>
                            </span>
                        </div>
                        <div class="profile-item">
                            <label>Data Registrazione:</label>
                            <span><?php echo mysql2date('d/m/Y H:i', $current_user['created_at']); ?></span>
                        </div>
                        <?php if (!empty($current_user['last_login'])): ?>
                        <div class="profile-item">
                            <label>Ultimo Accesso:</label>
                            <span><?php echo mysql2date('d/m/Y H:i', $current_user['last_login']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Link Dropbox -->
                <div class="dropbox-section">
                    <div class="section-header">
                        <h3>🗂️ Cartella Dropbox Assegnata</h3>
                    </div>
                    <div class="dropbox-info">
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
                        <?php else: ?>
                        <div class="dropbox-card disabled">
                            <div class="dropbox-icon">📁</div>
                            <div class="dropbox-details">
                                <h4>Cartella non configurata</h4>
                                <p>La cartella Dropbox non è ancora stata configurata per il tuo account. Contatta il supporto tecnico per l'attivazione.</p>
                                <a href="mailto:tecnica@naval.it" class="support-link">
                                    <span>📧</span> Contatta Supporto
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Supporto Tecnico -->
                <div class="support-section">
                    <div class="section-header">
                        <h3>💡 Supporto e Assistenza</h3>
                    </div>
                    <div class="support-info">
                        <p>Per qualsiasi necessità o richiesta di assistenza, non esitare a contattarci:</p>
                        <div class="support-contacts">
                            <div class="contact-item">
                                <strong>📧 Email:</strong> <a href="mailto:tecnica@naval.it">tecnica@naval.it</a>
                            </div>
                            <div class="contact-item">
                                <strong>🏢 Sede:</strong> Via Pietro Castellino, 45 - 80128 Napoli (NA)
                            </div>
                            <div class="contact-item">
                                <strong>🌐 Web:</strong> <a href="https://naval.vjformazione.it" target="_blank">naval.vjformazione.it</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.naval-egt-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    color: #333;
}

.assistance-box {
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    border: 2px solid #cbd5e1;
    border-radius: 16px;
    padding: 30px;
    text-align: center;
    margin-top: 30px;
    transition: transform 0.3s ease;
}

.assistance-box:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
}

.assistance-box .assistance-icon {
    font-size: 48px;
    margin-bottom: 20px;
}

.assistance-box h3 {
    color: #1e3a8a;
    margin: 0 0 15px 0;
    font-size: 24px;
    font-weight: 700;
}

.assistance-box a {
    color: #3b82f6;
    text-decoration: none;
    font-weight: 600;
}

.assistance-box a:hover {
    text-decoration: underline;
}

.forms-section {
    margin-bottom: 30px;
    display: flex;
    justify-content: center;
    width: 100%;
}

.login-box,
.register-box {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 15px 50px rgba(0, 0, 0, 0.1);
    max-width: 1200px;
    width: 100%;
}

.login-header,
.register-header {
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    padding: 30px;
    text-align: center;
    border-bottom: 2px solid #e2e8f0;
}

.login-header .login-icon,
.register-header .register-icon {
    font-size: 48px;
    margin-bottom: 15px;
}

.login-header h3,
.register-header h3 {
    margin: 0;
    color: #1e3a8a;
    font-size: 24px;
    font-weight: 700;
}

form {
    padding: 40px;
}

.form-group {
    margin-bottom: 25px;
}

label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #374151;
    font-size: 15px;
}

input[type="text"],
input[type="email"],
input[type="password"],
input[type="tel"],
textarea {
    width: 100%;
    padding: 16px 20px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    font-size: 16px;
    transition: all 0.3s ease;
    box-sizing: border-box;
    background: #fafafa;
}

textarea {
    resize: vertical;
    font-family: inherit;
}

input:focus,
textarea:focus {
    outline: none;
    border-color: #3b82f6;
    background: white;
    box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
}

.checkbox-group {
    display: flex;
    align-items: center;
    gap: 12px;
}

.checkbox-group input[type="checkbox"] {
    width: auto;
    margin: 0;
    transform: scale(1.2);
}

.btn-primary {
    background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
    color: white;
    border: none;
    padding: 18px 30px;
    border-radius: 12px;
    font-size: 17px;
    font-weight: 700;
    cursor: pointer;
    width: 100%;
    transition: all 0.3s ease;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #1e40af 0%, #2563eb 100%);
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(30, 58, 138, 0.4);
}

.btn-primary:disabled {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none;
}

.login-footer,
.register-footer {
    padding: 25px 40px;
    background: #f8fafc;
    border-top: 2px solid #e2e8f0;
    text-align: center;
}

.register-toggle-link,
.login-toggle-link {
    color: #3b82f6;
    text-decoration: none;
    font-weight: 600;
    cursor: pointer;
}

.register-toggle-link:hover,
.login-toggle-link:hover {
    text-decoration: underline;
}

.naval-egt-dashboard {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 15px 50px rgba(0, 0, 0, 0.05);
}

.dashboard-header {
    background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
    color: white;
    padding: 40px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
}

.user-info {
    display: flex;
    align-items: center;
    gap: 25px;
}

.user-avatar {
    width: 70px;
    height: 70px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 30px;
    backdrop-filter: blur(10px);
}

.user-details h2 {
    margin: 0 0 8px 0;
    font-size: 28px;
    font-weight: 800;
}

.user-details p {
    margin: 4px 0;
    opacity: 0.9;
    font-size: 16px;
}

.dashboard-actions {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}

.btn-outline {
    background: transparent;
    color: white;
    border: 2px solid #ef4444;
    padding: 12px 20px;
    border-radius: 10px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
}

.btn-outline:hover:not(:disabled) {
    background: #ef4444;
    transform: translateY(-2px);
}

.btn-outline:disabled {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none;
}

.dashboard-content {
    padding: 40px;
    display: flex;
    flex-direction: column;
    gap: 40px;
}

.user-profile-section,
.dropbox-section,
.support-section {
    background: white;
    border: 2px solid #f1f5f9;
    border-radius: 16px;
    padding: 30px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
}

.section-header {
    margin-bottom: 25px;
    border-bottom: 2px solid #f1f5f9;
    padding-bottom: 15px;
}

.section-header h3 {
    margin: 0;
    font-size: 20px;
    font-weight: 700;
    color: #1e3a8a;
}

.profile-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.profile-item {
    display: flex;
    flex-direction: column;
    gap: 5px;
    padding: 15px;
    background: #f8fafc;
    border-radius: 8px;
    border-left: 4px solid #3b82f6;
}

.profile-item label {
    font-weight: 600;
    color: #6b7280;
    font-size: 14px;
    margin-bottom: 5px;
}

.profile-item span {
    color: #1f2937;
    font-size: 16px;
    font-weight: 500;
}

.user-code {
    font-family: monospace;
    background: #e5e7eb;
    padding: 6px 12px;
    border-radius: 6px;
    font-weight: 700;
    color: #1e3a8a;
}

.status-badge {
    display: inline-block;
    padding: 6px 16px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    width: fit-content;
    letter-spacing: 0.5px;
}

.status-attivo {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #bbf7d0;
}

.status-sospeso {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

.dropbox-card {
    display: flex;
    align-items: center;
    gap: 25px;
    padding: 30px;
    background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
    border: 2px solid #0ea5e9;
    border-radius: 16px;
    transition: all 0.3s ease;
}

.dropbox-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(14, 165, 233, 0.2);
}

.dropbox-card.disabled {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border-color: #cbd5e1;
    opacity: 0.8;
}

.dropbox-icon {
    font-size: 48px;
    width: 80px;
    height: 80px;
    background: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #0ea5e9;
    box-shadow: 0 4px 15px rgba(14, 165, 233, 0.2);
}

.dropbox-details h4 {
    margin: 0 0 10px 0;
    color: #1e3a8a;
    font-size: 20px;
    font-weight: 700;
}

.dropbox-details p {
    margin: 0 0 15px 0;
    color: #64748b;
    line-height: 1.5;
}

.dropbox-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #0ea5e9;
    color: white;
    text-decoration: none;
    padding: 12px 20px;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.dropbox-link:hover {
    background: #0284c7;
    transform: translateX(5px);
}

.support-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #6b7280;
    color: white;
    text-decoration: none;
    padding: 12px 20px;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.support-link:hover {
    background: #4b5563;
    transform: translateX(5px);
}

.support-info p {
    margin-bottom: 20px;
    color: #64748b;
    line-height: 1.6;
}

.support-contacts {
    display: flex;
    flex-direction: column;
    gap: 15px;
    padding: 25px;
    background: #f8fafc;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
}

.contact-item {
    font-size: 16px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.contact-item strong {
    color: #1e3a8a;
}

.contact-item a {
    color: #3b82f6;
    text-decoration: none;
    font-weight: 600;
}

.contact-item a:hover {
    text-decoration: underline;
}

.toast {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 10000;
    padding: 15px 25px;
    border-radius: 10px;
    color: white;
    font-weight: 600;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
    animation: slideIn 0.3s ease;
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

@media (max-width: 768px) {
    .naval-egt-container {
        padding: 15px;
    }
    
    .dashboard-header {
        padding: 25px;
        flex-direction: column;
        text-align: center;
    }
    
    .user-info {
        flex-direction: column;
    }
    
    .dashboard-content {
        padding: 25px;
    }
    
    .profile-grid {
        grid-template-columns: 1fr;
    }
    
    .dropbox-card {
        flex-direction: column;
        text-align: center;
        gap: 20px;
    }
    
    .support-contacts {
        text-align: center;
    }
    
    .contact-item {
        justify-content: center;
    }

    .forms-section {
        width: 100%;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    
    const container = document.getElementById('naval-egt-area-riservata');
    if (!container) {
        console.error('Naval EGT: Container non trovato');
        return;
    }
    
    const CONFIG = {
        isLoggedIn: <?php echo $is_logged_in ? 'true' : 'false'; ?>,
        ajaxUrl: '<?php echo admin_url("admin-ajax.php"); ?>',
        nonce: '<?php echo wp_create_nonce("naval_egt_nonce"); ?>',
        currentUrl: window.location.href
    };
    
    console.log('Naval EGT inizializzato:', CONFIG);
    
    if (CONFIG.isLoggedIn) {
        initDashboard();
    } else {
        initAuth();
    }
    
    function initAuth() {
        const loginForm = document.getElementById('naval-login-form');
        const registerForm = document.getElementById('naval-register-form');
        const loginToggle = document.querySelector('.login-toggle-link');
        const registerToggle = document.querySelector('.register-toggle-link');
        
        if (loginForm) {
            loginForm.addEventListener('submit', handleLogin);
        }
        
        if (registerForm) {
            registerForm.addEventListener('submit', handleRegister);
        }
        
        if (registerToggle) {
            registerToggle.addEventListener('click', function(e) {
                e.preventDefault();
                showRegisterForm();
            });
        }
        
        if (loginToggle) {
            loginToggle.addEventListener('click', function(e) {
                e.preventDefault();
                showLoginForm();
            });
        }
    }
    
    function showRegisterForm() {
        const loginBox = document.querySelector('.login-box');
        const registerBox = document.querySelector('.register-box');
        
        if (loginBox) loginBox.style.display = 'none';
        if (registerBox) registerBox.style.display = 'block';
    }
    
    function showLoginForm() {
        const loginBox = document.querySelector('.login-box');
        const registerBox = document.querySelector('.register-box');
        
        if (registerBox) registerBox.style.display = 'none';
        if (loginBox) loginBox.style.display = 'block';
    }
    
    async function handleLogin(e) {
        e.preventDefault();
        
        const form = e.target;
        const submitBtn = form.querySelector('button[type="submit"]');
        const btnText = submitBtn.querySelector('.btn-text');
        const btnLoading = submitBtn.querySelector('.btn-loading');
        
        setLoadingState(submitBtn, btnText, btnLoading, true);
        
        try {
            const formData = new FormData(form);
            formData.append('action', 'naval_egt_login');
            formData.append('nonce', CONFIG.nonce);
            
            console.log('Naval EGT: Invio richiesta login...');
            
            const response = await fetch(CONFIG.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const responseText = await response.text();
                console.error('Naval EGT: Non-JSON response:', responseText);
                throw new Error('Risposta del server non valida');
            }
            
            const result = await response.json();
            console.log('Naval EGT Login Response:', result);
            
            if (result.success) {
                showToast('Login effettuato con successo!', 'success');
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                showToast(result.data || 'Errore durante il login', 'error');
            }
            
        } catch (error) {
            console.error('Naval EGT Login error:', error);
            showToast(`Errore: ${error.message}`, 'error');
        } finally {
            setLoadingState(submitBtn, btnText, btnLoading, false);
        }
    }
    
    async function handleRegister(e) {
        e.preventDefault();
        
        const form = e.target;
        const submitBtn = form.querySelector('button[type="submit"]');
        const btnText = submitBtn.querySelector('.btn-text');
        const btnLoading = submitBtn.querySelector('.btn-loading');
        
        // Validazione lato client
        const password = form.querySelector('#register-password').value;
        const passwordConfirm = form.querySelector('#register-password-confirm').value;
        const username = form.querySelector('#register-username').value;
        const privacyConsent = form.querySelector('#privacy-consent').checked;
        
        console.log('Naval EGT: Validazione client-side...');
        console.log('Naval EGT: Password length:', password.length);
        console.log('Naval EGT: Username length:', username.length);
        console.log('Naval EGT: Privacy consent:', privacyConsent);
        
        if (password !== passwordConfirm) {
            showToast('Le password non corrispondono', 'error');
            return;
        }
        
        if (password.length < 6) {
            showToast('La password deve essere di almeno 6 caratteri', 'error');
            return;
        }
        
        if (username.length < 4) {
            showToast('Lo username deve essere di almeno 4 caratteri', 'error');
            return;
        }
        
        // Validazione username (solo lettere, numeri, punti e trattini)
        if (!/^[a-zA-Z0-9._-]+$/.test(username)) {
            showToast('Lo username può contenere solo lettere, numeri, punti e trattini', 'error');
            return;
        }
        
        if (!privacyConsent) {
            showToast('È necessario accettare la Privacy Policy', 'error');
            return;
        }
        
        setLoadingState(submitBtn, btnText, btnLoading, true);
        
        try {
            const formData = new FormData(form);
            formData.append('action', 'naval_egt_register_request');
            formData.append('nonce', CONFIG.nonce);
            
            console.log('Naval EGT: Invio richiesta registrazione...');
            
            const response = await fetch(CONFIG.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            console.log('Naval EGT: Response status:', response.status);
            
            if (!response.ok) {
                const responseText = await response.text();
                console.error('Naval EGT: Response text:', responseText);
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const responseText = await response.text();
                console.error('Naval EGT: Non-JSON response:', responseText);
                throw new Error('Risposta del server non valida');
            }
            
            const result = await response.json();
            console.log('Naval EGT: Response result:', result);
            
            if (result.success) {
                showToast('Richiesta di registrazione inviata con successo! Ti contatteremo presto.', 'success');
                form.reset();
                setTimeout(() => {
                    showLoginForm();
                }, 3000);
            } else {
                showToast(result.data || 'Errore durante l\'invio della richiesta', 'error');
            }
            
        } catch (error) {
            console.error('Naval EGT Register error:', error);
            showToast(`Errore: ${error.message}`, 'error');
        } finally {
            setLoadingState(submitBtn, btnText, btnLoading, false);
        }
    }
    
    function initDashboard() {
        const logoutBtn = document.getElementById('logout-btn');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', handleLogout);
        }
    }
    
    async function handleLogout() {
        if (!confirm('Sei sicuro di voler uscire?')) return;
        
        const logoutBtn = document.getElementById('logout-btn');
        const logoutText = logoutBtn ? logoutBtn.querySelector('.logout-text') : null;
        const logoutLoading = logoutBtn ? logoutBtn.querySelector('.logout-loading') : null;
        
        if (logoutBtn) {
            logoutBtn.disabled = true;
            if (logoutText) logoutText.style.display = 'none';
            if (logoutLoading) logoutLoading.style.display = 'inline';
        }
        
        try {
            const formData = new FormData();
            formData.append('action', 'naval_egt_logout');
            formData.append('nonce', CONFIG.nonce);
            
            const response = await fetch(CONFIG.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            
            const result = await response.json();
            
            if (result.success) {
                showToast('Logout completato!', 'success');
                
                cleanupUserData();
                
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
                
            } else {
                showToast('Errore logout: ' + (result.data || 'Errore sconosciuto'), 'error');
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            }
            
        } catch (error) {
            console.error('Naval EGT Logout error:', error);
            showToast('Errore durante il logout', 'error');
            
            cleanupUserData();
            setTimeout(() => {
                window.location.reload();
            }, 1500);
            
        } finally {
            if (logoutBtn) {
                setTimeout(() => {
                    logoutBtn.disabled = false;
                    if (logoutText) logoutText.style.display = 'inline';
                    if (logoutLoading) logoutLoading.style.display = 'none';
                }, 2000);
            }
        }
    }
    
    function cleanupUserData() {
        try {
            if (typeof(Storage) !== "undefined") {
                localStorage.removeItem('naval_egt_user');
                sessionStorage.clear();
            }
        } catch (error) {
            console.warn('Naval EGT: Errore pulizia dati:', error);
        }
    }
    
    function setLoadingState(button, textElement, loadingElement, isLoading) {
        if (!button) return;
        
        button.disabled = isLoading;
        
        if (textElement) {
            textElement.style.display = isLoading ? 'none' : 'inline';
        }
        
        if (loadingElement) {
            loadingElement.style.display = isLoading ? 'inline' : 'none';
        }
    }
    
    function showToast(message, type = 'info', duration = 5000) {
        // Rimuovi toast precedenti dello stesso tipo
        document.querySelectorAll(`.toast-${type}`).forEach(toast => {
            toast.remove();
        });
        
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.textContent = message;
        
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            padding: 15px 25px;
            border-radius: 10px;
            color: white;
            font-weight: 600;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
            animation: slideIn 0.3s ease;
            max-width: 350px;
            word-wrap: break-word;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-size: 14px;
            line-height: 1.4;
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
});
</script>