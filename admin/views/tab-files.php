<?php
/**
 * Template tab-files.php - Gestione Completa File Dropbox
 * VERSIONE COMPLETA E FUNZIONANTE CON TUTTE LE FUNZIONALITÀ
 */

if (!defined('ABSPATH')) {
    exit;
}

// Debug inizializzazione
error_log('Naval EGT Files Tab: Starting initialization at ' . current_time('Y-m-d H:i:s'));

// Verifica che le classi necessarie siano disponibili
$required_classes = array(
    'Naval_EGT_User_Manager',
    'Naval_EGT_File_Manager',
    'Naval_EGT_Dropbox'
);

$missing_classes = array();
foreach ($required_classes as $class) {
    if (!class_exists($class)) {
        $missing_classes[] = $class;
    }
}

if (!empty($missing_classes)) {
    echo '<div class="notice notice-error"><p><strong>Errore:</strong> Classi mancanti: ' . implode(', ', $missing_classes) . '</p></div>';
    error_log('Naval EGT Files Tab: Missing classes: ' . implode(', ', $missing_classes));
    return;
}

// Inizializza il File Manager
$file_manager = Naval_EGT_File_Manager::get_instance();

// Ottieni i dati dell'utente corrente
$current_user = Naval_EGT_User_Manager::get_current_user();
$is_admin = current_user_can('manage_options');

// Se non è admin e non c'è utente loggato, mostra messaggio
if (!$is_admin && !$current_user) {
    echo '<div class="notice notice-warning"><p>Devi essere loggato per visualizzare i file.</p></div>';
    return;
}

// Ottieni lista utenti
$users_list = array();
if ($is_admin) {
    $users_list = Naval_EGT_User_Manager::get_users(array(), 1000, 0);
}

// Parametri per la gestione
$selected_user_id = intval($_GET['selected_user'] ?? 0);
$selected_user = null;

if ($selected_user_id > 0) {
    $selected_user = Naval_EGT_User_Manager::get_user_by_id($selected_user_id);
}

// Se non è admin, usa l'utente corrente
if (!$is_admin) {
    $selected_user_id = $current_user['id'];
    $selected_user = $current_user;
}

// Enqueue scripts
wp_enqueue_script('jquery');
?>

<div class="wrap naval-egt-files-manager">
    <!-- Header -->
    <div class="files-header">
        <div class="header-content">
            <div class="header-title">
                <h1>
                    <span class="dashicons dashicons-portfolio"></span>
                    Gestione File Dropbox
                </h1>
                <p>Gestisci file e cartelle sincronizzati con Dropbox</p>
            </div>
            <div class="header-actions">
                <span class="status-info">
                    <?php if ($selected_user): ?>
                        <strong>Utente:</strong> <?php echo esc_html($selected_user['nome'] . ' ' . $selected_user['cognome']); ?>
                        (<?php echo esc_html($selected_user['user_code']); ?>)
                    <?php else: ?>
                        Nessun utente selezionato
                    <?php endif; ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Selezione Utente -->
    <?php if ($is_admin): ?>
    <div class="user-selection-panel">
        <div class="panel-header">
            <h3>
                <span class="dashicons dashicons-admin-users"></span>
                1. Seleziona Utente
            </h3>
        </div>
        <div class="panel-content">
            <form method="GET" class="user-selection-form">
                <input type="hidden" name="page" value="naval-egt" />
                <input type="hidden" name="tab" value="files" />
                
                <div class="form-row">
                    <select name="selected_user" id="user-selector" class="user-select">
                        <option value="">Seleziona un utente...</option>
                        <?php foreach ($users_list as $user): ?>
                            <option value="<?php echo esc_attr($user['id']); ?>" 
                                    <?php selected($selected_user_id, $user['id']); ?>>
                                <?php echo esc_html($user['nome'] . ' ' . $user['cognome'] . ' (' . $user['user_code'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <button type="submit" class="btn btn-primary">
                        <span class="dashicons dashicons-search"></span>
                        Seleziona
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($selected_user): ?>
    <!-- Debug Panel -->
    <?php if ($is_admin): ?>
    <div class="debug-panel">
        <div class="panel-header">
            <h3>
                <span class="dashicons dashicons-admin-tools"></span>
                Debug e Diagnostica
            </h3>
        </div>
        <div class="panel-content">
            <div class="debug-actions">
                <button type="button" id="test-ajax-btn" class="btn btn-info">
                    <span class="dashicons dashicons-admin-network"></span>
                    Test AJAX
                </button>
                <button type="button" id="debug-hooks-btn" class="btn btn-info">
                    <span class="dashicons dashicons-admin-tools"></span>
                    Debug Hooks
                </button>
                <button type="button" id="check-dropbox-btn" class="btn btn-info">
                    <span class="dashicons dashicons-cloud"></span>
                    Test Dropbox
                </button>
                <button type="button" id="system-status-btn" class="btn btn-info">
                    <span class="dashicons dashicons-admin-generic"></span>
                    System Status
                </button>
                <button type="button" id="view-logs-btn" class="btn btn-info">
                    <span class="dashicons dashicons-text-page"></span>
                    Visualizza Log
                </button>
                <button type="button" id="export-debug-btn" class="btn btn-secondary">
                    <span class="dashicons dashicons-download"></span>
                    Esporta Debug
                </button>
            </div>
            <div id="debug-output"></div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Verifica Connessione -->
    <div class="connection-panel">
        <div class="panel-header">
            <h3>
                <span class="dashicons dashicons-admin-links"></span>
                2. Verifica Connessione Utente-Cartella
            </h3>
        </div>
        <div class="panel-content">
            <div class="user-info">
                <div class="user-card">
                    <div class="user-avatar">
                        <span class="dashicons dashicons-admin-users"></span>
                    </div>
                    <div class="user-details">
                        <h4><?php echo esc_html($selected_user['nome'] . ' ' . $selected_user['cognome']); ?></h4>
                        <p>Codice: <strong><?php echo esc_html($selected_user['user_code']); ?></strong></p>
                        <p>Cartella: 
                            <span id="user-folder-path">
                                <?php echo esc_html($selected_user['dropbox_folder'] ?? 'Non configurata'); ?>
                            </span>
                        </p>
                    </div>
                    <div class="connection-status">
                        <div id="connection-status" class="status-indicator checking">
                            <span class="dashicons dashicons-update spin"></span>
                            Verificando...
                        </div>
                        <div class="connection-actions">
                            <button type="button" id="verify-connection-btn" class="btn btn-secondary">
                                <span class="dashicons dashicons-search"></span>
                                Verifica Connessione
                            </button>
                            <button type="button" id="repair-connection-btn" class="btn btn-warning" style="display: none;">
                                <span class="dashicons dashicons-admin-tools"></span>
                                Ripara Connessione
                            </button>
                            <button type="button" id="create-user-folder-btn" class="btn btn-success" style="display: none;">
                                <span class="dashicons dashicons-plus"></span>
                                Crea Cartella
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Gestione Cartelle e File -->
    <div class="file-management-panel">
        <div class="panel-header">
            <h3>
                <span class="dashicons dashicons-category"></span>
                3. Gestione Cartelle e File
            </h3>
            <div class="panel-actions">
                <button type="button" id="create-folder-btn" class="btn btn-primary">
                    <span class="dashicons dashicons-plus"></span>
                    Nuova Cartella
                </button>
                <button type="button" id="upload-files-btn" class="btn btn-success">
                    <span class="dashicons dashicons-upload"></span>
                    Carica File
                </button>
                <button type="button" id="refresh-structure-btn" class="btn btn-secondary">
                    <span class="dashicons dashicons-update"></span>
                    Aggiorna
                </button>
                <button type="button" id="sync-folder-btn" class="btn btn-info">
                    <span class="dashicons dashicons-update-alt"></span>
                    Sincronizza
                </button>
                <?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
                <button type="button" id="debug-logs-btn" class="btn btn-info">
                    <span class="dashicons dashicons-admin-tools"></span>
                    Debug
                </button>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="panel-content">
            <div class="file-browser">
                <!-- Breadcrumb -->
                <div class="breadcrumb-nav">
                    <div class="breadcrumb" id="breadcrumb">
                        <span class="breadcrumb-item active">
                            <span class="dashicons dashicons-admin-home"></span>
                            Cartella Principale
                        </span>
                    </div>
                </div>

                <!-- Struttura File e Cartelle -->
                <div class="file-structure" id="file-structure">
                    <div class="loading-state">
                        <span class="dashicons dashicons-update spin"></span>
                        Caricamento struttura file...
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <!-- Messaggio quando nessun utente è selezionato -->
    <div class="no-user-selected">
        <span class="dashicons dashicons-admin-users"></span>
        <h3>Seleziona un Utente</h3>
        <p>Scegli un utente dal menu a tendina sopra per gestire i suoi file e cartelle Dropbox.</p>
    </div>
    <?php endif; ?>
</div>

<!-- Modal Creazione Cartella -->
<div id="create-folder-modal" class="naval-modal" style="display: none;">
    <div class="modal-backdrop"></div>
    <div class="modal-container">
        <div class="modal-header">
            <h3>
                <span class="dashicons dashicons-plus"></span>
                Crea Nuova Cartella
            </h3>
            <button type="button" class="modal-close">
                <span class="dashicons dashicons-no"></span>
            </button>
        </div>
        <div class="modal-body">
            <form id="create-folder-form">
                <div class="form-group">
                    <label for="folder-name">Nome Cartella:</label>
                    <input type="text" 
                           id="folder-name" 
                           name="folder_name" 
                           class="form-input" 
                           placeholder="Inserisci nome cartella" 
                           required>
                    <small class="form-help">La cartella sarà creata nel percorso corrente</small>
                </div>
                
                <div class="form-group">
                    <label>Percorso di destinazione:</label>
                    <div class="destination-path" id="destination-path">
                        /cartella-principale
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary modal-close">Annulla</button>
                    <button type="submit" class="btn btn-primary">
                        <span class="dashicons dashicons-yes"></span>
                        Crea Cartella
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Upload File -->
<div id="upload-modal" class="naval-modal" style="display: none;">
    <div class="modal-backdrop"></div>
    <div class="modal-container large">
        <div class="modal-header">
            <h3>
                <span class="dashicons dashicons-upload"></span>
                Carica File
            </h3>
            <button type="button" class="modal-close">
                <span class="dashicons dashicons-no"></span>
            </button>
        </div>
        <div class="modal-body">
            <form id="upload-form" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Cartella di destinazione:</label>
                    <select id="upload-destination" name="destination_folder" class="form-select" required>
                        <option value="">Seleziona cartella...</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="file-input">Seleziona File:</label>
                    <div class="file-drop-zone" id="file-drop-zone">
                        <div class="drop-zone-content">
                            <span class="dashicons dashicons-cloud-upload"></span>
                            <h4>Trascina i file qui o clicca per selezionare</h4>
                            <p>Tipi supportati: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG, GIF, DWG, DXF, ZIP, RAR</p>
                            <p>Dimensione massima: <?php echo size_format(wp_max_upload_size()); ?></p>
                        </div>
                        <input type="file" 
                               id="file-input" 
                               name="files[]" 
                               multiple 
                               accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.dwg,.dxf,.zip,.rar">
                    </div>
                </div>
                
                <div class="selected-files" id="selected-files" style="display: none;">
                    <h4>File Selezionati:</h4>
                    <div class="files-list" id="files-preview"></div>
                </div>
                
                <div class="upload-progress" id="upload-progress" style="display: none;">
                    <div class="progress-bar">
                        <div class="progress-fill"></div>
                    </div>
                    <div class="progress-info">
                        <span class="progress-text">Upload in corso...</span>
                        <span class="progress-percentage">0%</span>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary modal-close">Annulla</button>
                    <button type="submit" class="btn btn-success">
                        <span class="dashicons dashicons-upload"></span>
                        Carica File
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Debug -->
<?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
<div id="debug-modal" class="naval-modal" style="display: none;">
    <div class="modal-backdrop"></div>
    <div class="modal-container large">
        <div class="modal-header">
            <h3>
                <span class="dashicons dashicons-admin-tools"></span>
                Debug Log Dropbox
            </h3>
            <button type="button" class="modal-close">
                <span class="dashicons dashicons-no"></span>
            </button>
        </div>
        <div class="modal-body">
            <div class="debug-controls">
                <button type="button" id="refresh-debug-logs" class="btn btn-primary">
                    <span class="dashicons dashicons-update"></span>
                    Aggiorna Log
                </button>
                <button type="button" id="clear-debug-logs" class="btn btn-secondary">
                    <span class="dashicons dashicons-trash"></span>
                    Pulisci Log
                </button>
            </div>
            <div id="debug-logs-content">
                <div>Caricamento log...</div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal Dettagli File -->
<div id="file-info-modal" class="naval-modal" style="display: none;">
    <div class="modal-backdrop"></div>
    <div class="modal-container">
        <div class="modal-header">
            <h3>
                <span class="dashicons dashicons-info"></span>
                Informazioni File
            </h3>
            <button type="button" class="modal-close">
                <span class="dashicons dashicons-no"></span>
            </button>
        </div>
        <div class="modal-body">
            <div id="file-info-content">
                <div class="loading-spinner">
                    <span class="dashicons dashicons-update spin"></span>
                    Caricamento...
                </div>
            </div>
        </div>
    </div>
</div>

<style type="text/css">
.naval-egt-files-manager {
    max-width: 100%;
    margin: 0;
}

/* Header */
.files-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 30px;
    border-radius: 10px;
    margin-bottom: 30px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.header-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.header-title h1 {
    margin: 0;
    font-size: 28px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 15px;
}

.header-title p {
    margin: 8px 0 0 0;
    opacity: 0.9;
    font-size: 16px;
}

.status-info {
    background: rgba(255,255,255,0.2);
    padding: 10px 15px;
    border-radius: 5px;
    font-size: 14px;
}

/* Pannelli */
.user-selection-panel,
.connection-panel,
.file-management-panel,
.debug-panel {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 25px;
    overflow: hidden;
}

.panel-header {
    background: #f8f9fa;
    padding: 20px 30px;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.panel-header h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
    color: #495057;
}

.panel-content {
    padding: 30px;
}

/* Form */
.form-row {
    display: flex;
    align-items: center;
    gap: 15px;
}

.user-select {
    min-width: 300px;
    padding: 12px 16px;
    border: 2px solid #dee2e6;
    border-radius: 8px;
    font-size: 16px;
    background: white;
    transition: all 0.3s ease;
}

.user-select:focus {
    border-color: #667eea;
    outline: none;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

/* Card Utente */
.user-card {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 10px;
    border: 2px solid #e9ecef;
}

.user-avatar {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 24px;
}

.user-details {
    flex: 1;
}

.user-details h4 {
    margin: 0 0 8px 0;
    font-size: 18px;
    color: #212529;
}

.user-details p {
    margin: 4px 0;
    color: #6c757d;
}

.connection-status {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 10px;
}

.connection-actions {
    display: flex;
    flex-direction: column;
    gap: 8px;
    align-items: flex-end;
}

.status-indicator {
    padding: 8px 16px;
    border-radius: 6px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
}

.status-indicator.checking {
    background: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
}

.status-indicator.connected {
    background: #d1edff;
    color: #0c5460;
    border: 1px solid #bee5eb;
}

.status-indicator.error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

/* Debug */
.debug-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 15px;
}

#debug-output {
    margin-top: 15px;
    padding: 10px;
    background: #1e1e1e;
    color: #ffffff;
    border-radius: 3px;
    font-family: monospace;
    font-size: 12px;
    max-height: 200px;
    overflow-y: auto;
    display: none;
}

#debug-logs-content {
    background: #1e1e1e;
    color: #ffffff;
    padding: 15px;
    border-radius: 5px;
    font-family: monospace;
    font-size: 12px;
    max-height: 400px;
    overflow-y: auto;
}

.debug-controls {
    margin-bottom: 20px;
    display: flex;
    gap: 10px;
}

/* Breadcrumb */
.breadcrumb-nav {
    background: #f8f9fa;
    padding: 15px 20px;
    border-bottom: 1px solid #e9ecef;
}

.breadcrumb {
    display: flex;
    align-items: center;
    gap: 8px;
}

.breadcrumb-item {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 6px 12px;
    background: white;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 14px;
    color: #495057;
}

.breadcrumb-item:hover {
    background: #e9ecef;
}

.breadcrumb-item.active {
    background: #667eea;
    color: white;
}

.breadcrumb-separator {
    color: #6c757d;
}

/* Struttura File */
.file-structure {
    min-height: 400px;
    position: relative;
}

.loading-state {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
    color: #6c757d;
    font-size: 16px;
}

.file-tree {
    list-style: none;
    padding: 0;
    margin: 0;
}

.file-tree-item {
    border-bottom: 1px solid #f1f3f4;
    transition: all 0.3s ease;
}

.file-tree-item:hover {
    background: #f8f9fa;
}

.tree-item-content {
    display: flex;
    align-items: center;
    padding: 12px 15px;
    cursor: pointer;
    gap: 12px;
}

.tree-item-icon {
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
}

.tree-item-icon.folder {
    color: #ffa726;
}

.tree-item-icon.file {
    color: #42a5f5;
}

.tree-item-details {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.tree-item-name {
    font-weight: 500;
    color: #212529;
}

.tree-item-meta {
    display: flex;
    align-items: center;
    gap: 15px;
    color: #6c757d;
    font-size: 13px;
}

.tree-item-actions {
    display: flex;
    align-items: center;
    gap: 5px;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.file-tree-item:hover .tree-item-actions {
    opacity: 1;
}

.tree-action-btn {
    padding: 6px;
    border: none;
    background: none;
    border-radius: 4px;
    cursor: pointer;
    color: #6c757d;
    transition: all 0.3s ease;
}

.tree-action-btn:hover {
    background: #e9ecef;
    color: #495057;
}

/* Bottoni */
.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
    text-decoration: none;
}

.btn-primary {
    background: #667eea;
    color: white;
}

.btn-primary:hover {
    background: #5a6fd8;
    transform: translateY(-2px);
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #5a6268;
}

.btn-success {
    background: #28a745;
    color: white;
}

.btn-success:hover {
    background: #218838;
}

.btn-warning {
    background: #ffc107;
    color: #212529;
}

.btn-warning:hover {
    background: #e0a800;
}

.btn-danger {
    background: #dc3545;
    color: white;
}

.btn-danger:hover {
    background: #c82333;
}

.btn-info {
    background: #17a2b8;
    color: white;
}

.btn-info:hover {
    background: #138496;
}

/* Modal */
.naval-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 999999;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-backdrop {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
}

.modal-container {
    background: white;
    border-radius: 10px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    position: relative;
    max-width: 500px;
    width: 90%;
    max-height: 80vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.modal-container.large {
    max-width: 800px;
}

.modal-header {
    padding: 20px 25px;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #f8f9fa;
}

.modal-header h3 {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
    color: #495057;
}

.modal-close {
    background: none;
    border: none;
    font-size: 20px;
    cursor: pointer;
    color: #6c757d;
    padding: 5px;
    border-radius: 4px;
    transition: all 0.3s ease;
}

.modal-close:hover {
    background: #e9ecef;
    color: #495057;
}

.modal-body {
    padding: 25px;
    flex: 1;
    overflow-y: auto;
}

/* Form */
.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #495057;
}

.form-input,
.form-select {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #dee2e6;
    border-radius: 6px;
    font-size: 14px;
    transition: all 0.3s ease;
}

.form-input:focus,
.form-select:focus {
    border-color: #667eea;
    outline: none;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.form-help {
    display: block;
    margin-top: 5px;
    color: #6c757d;
    font-size: 12px;
}

.destination-path {
    background: #f8f9fa;
    padding: 12px 16px;
    border-radius: 6px;
    border: 2px solid #e9ecef;
    font-family: monospace;
    color: #495057;
}

/* Drop Zone */
.file-drop-zone {
    border: 3px dashed #dee2e6;
    border-radius: 10px;
    padding: 40px 20px;
    text-align: center;
    background: #f8f9fa;
    transition: all 0.3s ease;
    cursor: pointer;
    position: relative;
}

.file-drop-zone:hover {
    border-color: #667eea;
    background: #f0f2ff;
}

.file-drop-zone.dragover {
    border-color: #28a745;
    background: #f0fff0;
}

.file-drop-zone input[type="file"] {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    cursor: pointer;
}

.drop-zone-content h4 {
    margin: 10px 0;
    color: #495057;
}

.drop-zone-content p {
    margin: 5px 0;
    color: #6c757d;
    font-size: 13px;
}

/* File Preview */
.selected-files {
    margin-top: 20px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
}

.files-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.file-preview-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px;
    background: white;
    border-radius: 6px;
    border: 1px solid #e9ecef;
}

.file-preview-icon {
    width: 24px;
    text-align: center;
    color: #667eea;
}

.file-preview-details {
    flex: 1;
}

.file-preview-name {
    font-weight: 500;
    color: #212529;
    margin-bottom: 2px;
}

.file-preview-size {
    font-size: 12px;
    color: #6c757d;
}

.file-remove-btn {
    background: none;
    border: none;
    color: #dc3545;
    cursor: pointer;
    padding: 5px;
    border-radius: 4px;
    transition: all 0.3s ease;
}

.file-remove-btn:hover {
    background: #f8d7da;
}

/* Progress */
.upload-progress {
    margin-top: 20px;
}

.progress-bar {
    width: 100%;
    height: 8px;
    background: #e9ecef;
    border-radius: 4px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #667eea, #764ba2);
    width: 0%;
    transition: width 0.3s ease;
}

.progress-info {
    display: flex;
    justify-content: space-between;
    margin-top: 10px;
    font-size: 14px;
    color: #495057;
}

/* Form Actions */
.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 15px;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #e9ecef;
}

/* States */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #6c757d;
}

.empty-state .dashicons {
    font-size: 48px;
    margin-bottom: 20px;
    opacity: 0.5;
}

.empty-state h4 {
    margin-bottom: 10px;
    color: #495057;
}

.error-state {
    text-align: center;
    padding: 40px 20px;
    color: #dc3545;
    background: #f8d7da;
    border-radius: 8px;
    margin: 20px 0;
}

.success-state {
    text-align: center;
    padding: 40px 20px;
    color: #155724;
    background: #d4edda;
    border-radius: 8px;
    margin: 20px 0;
}

.no-user-selected {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin: 20px 0;
    text-align: center;
    padding: 60px 20px;
    color: #6c757d;
}

.no-user-selected .dashicons {
    font-size: 72px;
    margin-bottom: 20px;
    opacity: 0.3;
}

/* Animazioni */
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.spin {
    animation: spin 1s linear infinite;
}

/* Responsive */
@media (max-width: 768px) {
    .files-header {
        padding: 20px;
    }
    
    .header-title h1 {
        font-size: 24px;
    }
    
    .panel-content {
        padding: 20px;
    }
    
    .user-card {
        flex-direction: column;
        text-align: center;
    }
    
    .connection-status {
        align-items: center;
    }
    
    .modal-container {
        width: 95%;
        margin: 10px;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .btn {
        justify-content: center;
    }
    
    .debug-actions {
        flex-direction: column;
    }
    
    .panel-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }
}

/* Miglioramenti per dispositivi mobile */
@media (max-width: 480px) {
    .header-content {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .user-card {
        padding: 15px;
    }
    
    .tree-item-content {
        padding: 10px;
    }
    
    .tree-item-meta {
        flex-direction: column;
        align-items: flex-start;
        gap: 5px;
    }
    
    .breadcrumb {
        flex-wrap: wrap;
    }
    
    .form-row {
        flex-direction: column;
        align-items: stretch;
    }
    
    .user-select {
        min-width: auto;
        width: 100%;
    }
}
</style>

<script type="text/javascript">
// JavaScript Completo per Naval EGT File Manager - VERSIONE FINALE COMPLETA
jQuery(document).ready(function($) {
    console.log('Naval EGT Files: JavaScript loading...');
    
    // Variabili globali
    let currentPath = '';
    let selectedUserId = <?php echo $selected_user_id; ?>;
    let fileStructure = {};
    
    // Configurazione AJAX
    const ajaxConfig = {
        url: '<?php echo admin_url('admin-ajax.php'); ?>',
        nonce: '<?php echo wp_create_nonce('naval_egt_nonce'); ?>',
        isAdmin: <?php echo $is_admin ? 'true' : 'false'; ?>
    };
    
    console.log('Naval EGT: Configuration loaded', ajaxConfig);
    console.log('Selected User ID:', selectedUserId);
    
    // === INIZIALIZZAZIONE ===
    if (selectedUserId > 0) {
        console.log('Auto-verifying connection for user', selectedUserId);
        setTimeout(() => {
            verifyConnection();
            loadFileStructure();
        }, 1000);
    }
    
    // === EVENT HANDLERS ===
    
    // Debug buttons
    $('#test-ajax-btn').on('click', function() {
        testAjaxConnection();
    });
    
    $('#debug-hooks-btn').on('click', function() {
        debugAjaxHooks();
    });
    
    $('#check-dropbox-btn').on('click', function() {
        testDropboxConnection();
    });
    
    $('#system-status-btn').on('click', function() {
        testSystemStatus();
    });
    
    $('#view-logs-btn').on('click', function() {
        viewDebugLogs();
    });
    
    $('#export-debug-btn').on('click', function() {
        exportDebugInfo();
    });
    
    // Connection buttons
    $('#verify-connection-btn').on('click', function() {
        verifyConnection();
    });
    
    $('#repair-connection-btn').on('click', function() {
        repairConnection();
    });
    
    $('#create-user-folder-btn').on('click', function() {
        createUserFolder();
    });
    
    // File management buttons
    $('#create-folder-btn').on('click', function() {
        if (selectedUserId <= 0) {
            showNotification('Seleziona un utente prima di creare una cartella', 'error');
            return;
        }
        openCreateFolderModal();
    });
    
    $('#upload-files-btn').on('click', function() {
        if (selectedUserId <= 0) {
            showNotification('Seleziona un utente prima di caricare file', 'error');
            return;
        }
        openUploadModal();
    });
    
    $('#refresh-structure-btn').on('click', function() {
        if (selectedUserId <= 0) {
            showNotification('Seleziona un utente per visualizzare i file', 'warning');
            return;
        }
        loadFileStructure(currentPath);
    });
    
    // NUOVO: Pulsante sincronizza
    $('#sync-folder-btn').on('click', function() {
        if (selectedUserId <= 0) {
            showNotification('Seleziona un utente per sincronizzare', 'warning');
            return;
        }
        syncUserFolder();
    });
    
    $('#debug-logs-btn').on('click', function() {
        openDebugModal();
    });
    
    // Modal events
    $('.modal-close, .modal-backdrop').on('click', function(e) {
        if (e.target === this) {
            closeModal(e);
        }
    });
    
    // Form events
    $('#create-folder-form').on('submit', function(e) {
        e.preventDefault();
        submitCreateFolder();
    });
    
    $('#upload-form').on('submit', function(e) {
        e.preventDefault();
        submitUploadFiles();
    });
    
    // File structure events (delegated)
    $(document).on('click', '.enter-folder-btn', function() {
        const path = $(this).closest('.file-tree-item').data('path');
        currentPath = path;
        loadFileStructure(path);
        updateBreadcrumb(path);
    });
    
    $(document).on('click', '#back-folder-btn', function() {
        goBackFolder();
    });
    
    $(document).on('click', '.breadcrumb-item:not(.active)', function() {
        const path = $(this).data('path') || '';
        currentPath = path;
        loadFileStructure(path);
        updateBreadcrumb(path);
    });
    
    $(document).on('click', '.download-file-btn', function() {
        downloadFile($(this));
    });
    
    $(document).on('click', '.file-info-btn', function() {
        showFileInfo($(this));
    });
    
    $(document).on('click', '.delete-file-btn', function() {
        deleteFile($(this));
    });
    
    // File upload setup
    setupFileUpload();
    
    // Debug modal events
    $('#refresh-debug-logs').on('click', function() {
        loadDebugLogs();
    });
    
    $('#clear-debug-logs').on('click', function() {
        if (confirm('Sei sicuro di voler cancellare tutti i log di debug?')) {
            clearDebugLogs();
        }
    });
    
    // Remove file from upload list
    $(document).on('click', '.file-remove-btn', function() {
        removeFileFromUpload($(this));
    });
    
    // === FUNCTIONS ===
    
    function makeAjaxRequest(action, data = {}, options = {}) {
        const requestData = {
            action: 'naval_egt_ajax',
            naval_action: action,
            nonce: ajaxConfig.nonce,
            ...data
        };
        
        const defaultOptions = {
            url: ajaxConfig.url,
            type: 'POST',
            data: requestData,
            timeout: 30000
        };
        
        return $.ajax({...defaultOptions, ...options});
    }
    
    function testAjaxConnection() {
        appendDebugOutput('=== TEST AJAX CONNECTION ===');
        
        const $btn = $('#test-ajax-btn');
        const originalText = $btn.html();
        $btn.html('<span class="dashicons dashicons-update spin"></span> Testing...').prop('disabled', true);
        
        makeAjaxRequest('test_ajax_connection', {user_id: selectedUserId})
            .done(function(response) {
                appendDebugOutput('✅ AJAX Response received');
                appendDebugOutput(JSON.stringify(response, null, 2));
                
                if (response.success) {
                    showNotification('AJAX connection working correctly', 'success');
                } else {
                    showNotification('AJAX test failed: ' + response.data, 'error');
                }
            })
            .fail(function(xhr, status, error) {
                appendDebugOutput('❌ AJAX request failed:');
                appendDebugOutput('Status: ' + status + ', Error: ' + error);
                showNotification('AJAX request failed: ' + error, 'error');
            })
            .always(function() {
                $btn.html(originalText).prop('disabled', false);
            });
    }
    
    function debugAjaxHooks() {
        appendDebugOutput('=== DEBUG AJAX HOOKS ===');
        
        makeAjaxRequest('debug_ajax_hooks')
            .done(function(response) {
                appendDebugOutput('Hooks Debug Response:');
                appendDebugOutput(JSON.stringify(response, null, 2));
                
                if (response.success) {
                    const data = response.data;
                    appendDebugOutput('✅ AJAX hooks debug successful');
                    appendDebugOutput('📊 Total AJAX hooks: ' + data.total_ajax_hooks);
                    appendDebugOutput('🎯 Naval EGT hooks: ' + data.naval_ajax_hooks.length);
                    
                    showNotification('Hooks debug completed', 'info');
                } else {
                    showNotification('Hooks debug failed: ' + response.data, 'error');
                }
            })
            .fail(function(xhr, status, error) {
                appendDebugOutput('❌ Hooks debug failed: ' + error);
                showNotification('Hooks debug request failed', 'error');
            });
    }
    
    function testDropboxConnection() {
        if (selectedUserId <= 0) {
            showNotification('Seleziona un utente prima di testare Dropbox', 'error');
            return;
        }
        
        appendDebugOutput('=== TEST DROPBOX CONNECTION ===');
        
        makeAjaxRequest('verify_repair_user_folder', {user_id: selectedUserId})
            .done(function(response) {
                appendDebugOutput('Dropbox Response:');
                appendDebugOutput(JSON.stringify(response, null, 2));
                
                if (response.success) {
                    showNotification('Dropbox connection test passed', 'success');
                } else {
                    showNotification('Dropbox connection test failed', 'error');
                }
            })
            .fail(function(xhr, status, error) {
                appendDebugOutput('❌ Dropbox test failed: ' + error);
                showNotification('Dropbox test request failed', 'error');
            });
    }
    
    function testSystemStatus() {
        appendDebugOutput('=== SYSTEM STATUS CHECK ===');
        
        makeAjaxRequest('system_status')
            .done(function(response) {
                if (response.success) {
                    const status = response.data;
                    appendDebugOutput('📊 SYSTEM STATUS:');
                    appendDebugOutput('✅ File Manager: ' + (status.file_manager_loaded ? 'LOADED' : 'NOT LOADED'));
                    appendDebugOutput('✅ Dropbox Class: ' + (status.dropbox_class_loaded ? 'LOADED' : 'NOT LOADED'));
                    appendDebugOutput('✅ Hooks Registered: ' + (status.hooks_registered ? 'YES' : 'NO'));
                    
                    showNotification('System status check completed', 'info');
                } else {
                    showNotification('System status check failed', 'error');
                }
            })
            .fail(function() {
                showNotification('System status request failed', 'error');
            });
    }
    
    function viewDebugLogs() {
        appendDebugOutput('=== LOADING DEBUG LOGS ===');
        
        makeAjaxRequest('get_debug_logs')
            .done(function(response) {
                if (response.success) {
                    const logs = response.data.logs || [];
                    appendDebugOutput('📋 Found ' + logs.length + ' log entries');
                    
                    if (logs.length > 0) {
                        logs.slice(-10).forEach(function(log) {
                            appendDebugOutput('[' + (log.timestamp || 'N/A') + '] ' + (log.message || 'No message'));
                        });
                    }
                    
                    showNotification('Debug logs loaded', 'info');
                } else {
                    showNotification('Failed to load debug logs', 'error');
                }
            })
            .fail(function() {
                showNotification('Debug logs request failed', 'error');
            });
    }
    
    function exportDebugInfo() {
        const debugInfo = {
            timestamp: new Date().toISOString(),
            user_id: selectedUserId,
            ajax_config: ajaxConfig,
            debug_output: $('#debug-output').text()
        };
        
        const dataStr = JSON.stringify(debugInfo, null, 2);
        const dataBlob = new Blob([dataStr], {type: 'application/json'});
        const url = URL.createObjectURL(dataBlob);
        
        const link = document.createElement('a');
        link.href = url;
        link.download = 'naval-egt-debug-' + Date.now() + '.json';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
        
        showNotification('Debug info exported successfully', 'success');
    }
    
    function verifyConnection() {
        if (selectedUserId <= 0) {
            showNotification('Seleziona un utente prima di verificare la connessione', 'error');
            return;
        }
        
        const $status = $('#connection-status');
        const $repairBtn = $('#repair-connection-btn');
        const $createBtn = $('#create-user-folder-btn');
        
        $status.removeClass('connected error').addClass('checking')
               .html('<span class="dashicons dashicons-update spin"></span> Verificando connessione...');
        $repairBtn.hide();
        $createBtn.hide();
        
        makeAjaxRequest('verify_repair_user_folder', {user_id: selectedUserId})
            .done(function(response) {
                if (response.success) {
                    $status.removeClass('checking error').addClass('connected')
                           .html('<span class="dashicons dashicons-yes-alt"></span> Connessione verificata');
                    
                    if (response.data && response.data.folder_path) {
                        $('#user-folder-path').text(response.data.folder_path);
                    }
                    
                    showNotification('Connessione verificata con successo', 'success');
                    loadFileStructure();
                } else {
                    $status.removeClass('checking connected').addClass('error')
                           .html('<span class="dashicons dashicons-warning"></span> Errore connessione');
                    
                    if (response.data && response.data.includes('Nessuna cartella trovata')) {
                        $createBtn.show();
                    } else {
                        $repairBtn.show();
                    }
                    
                    showNotification('Errore connessione: ' + response.data, 'error');
                }
            })
            .fail(function() {
                $status.removeClass('checking connected').addClass('error')
                       .html('<span class="dashicons dashicons-warning"></span> Errore di comunicazione');
                $repairBtn.show();
                showNotification('Errore di comunicazione con il server', 'error');
            });
    }
    
    function repairConnection() {
        if (selectedUserId <= 0) {
            showNotification('Seleziona un utente prima di riparare la connessione', 'error');
            return;
        }
        
        const $btn = $('#repair-connection-btn');
        const originalText = $btn.html();
        
        $btn.html('<span class="dashicons dashicons-update spin"></span> Riparando...').prop('disabled', true);
        
        makeAjaxRequest('verify_repair_user_folder', {user_id: selectedUserId})
            .done(function(response) {
                if (response.success) {
                    showNotification('Connessione riparata con successo!', 'success');
                    verifyConnection();
                } else {
                    showNotification('Impossibile riparare la connessione: ' + response.data, 'error');
                }
            })
            .fail(function() {
                showNotification('Errore durante la riparazione della connessione', 'error');
            })
            .always(function() {
                $btn.html(originalText).prop('disabled', false);
            });
    }
    
    function createUserFolder() {
        if (selectedUserId <= 0) {
            showNotification('Seleziona un utente prima di creare la cartella', 'error');
            return;
        }
        
        const userText = $('#user-selector option:selected').text();
        const userMatch = userText.match(/\(([^)]+)\)/);
        const userCode = userMatch ? userMatch[1] : 'USER';
        const userName = userText.replace(/\s*\([^)]*\)/, '');
        const folderName = userCode + ' - ' + userName;
        
        if (!confirm('Vuoi creare la cartella "' + folderName + '" nella cartella principale di Dropbox?')) {
            return;
        }
        
        const $btn = $('#create-user-folder-btn');
        const originalText = $btn.html();
        
        $btn.html('<span class="dashicons dashicons-update spin"></span> Creando...').prop('disabled', true);
        
        makeAjaxRequest('create_user_main_folder', {
            user_id: selectedUserId,
            folder_name: folderName
        })
            .done(function(response) {
                if (response.success) {
                    showNotification('Cartella utente creata con successo!', 'success');
                    $btn.hide();
                    setTimeout(() => verifyConnection(), 1000);
                } else {
                    showNotification('Errore nella creazione della cartella: ' + response.data, 'error');
                }
            })
            .fail(function() {
                showNotification('Errore di comunicazione durante la creazione della cartella', 'error');
            })
            .always(function() {
                $btn.html(originalText).prop('disabled', false);
            });
    }
    
    // NUOVA FUNZIONE: Sincronizza cartella utente
    function syncUserFolder() {
        if (selectedUserId <= 0) {
            showNotification('Seleziona un utente prima di sincronizzare', 'error');
            return;
        }
        
        const $btn = $('#sync-folder-btn');
        const originalText = $btn.html();
        
        $btn.html('<span class="dashicons dashicons-update spin"></span> Sincronizzando...').prop('disabled', true);
        
        makeAjaxRequest('sync_user_folder', {user_id: selectedUserId})
            .done(function(response) {
                if (response.success) {
                    showNotification('Sincronizzazione completata con successo!', 'success');
                    // Ricarica la struttura file dopo la sincronizzazione
                    setTimeout(() => {
                        verifyConnection();
                        loadFileStructure();
                    }, 1000);
                } else {
                    showNotification('Errore durante la sincronizzazione: ' + response.data, 'error');
                }
            })
            .fail(function() {
                showNotification('Errore di comunicazione durante la sincronizzazione', 'error');
            })
            .always(function() {
                $btn.html(originalText).prop('disabled', false);
            });
    }
    
    function loadFileStructure(path = '') {
        if (selectedUserId <= 0) {
            $('#file-structure').html(`
                <div class="empty-state">
                    <span class="dashicons dashicons-admin-users"></span>
                    <h4>Nessun utente selezionato</h4>
                    <p>Seleziona un utente per visualizzare la struttura file</p>
                </div>
            `);
            return;
        }
        
        const $structure = $('#file-structure');
        
        $structure.html(`
            <div class="loading-state">
                <span class="dashicons dashicons-update spin"></span> 
                <span>Caricamento struttura file...</span>
            </div>
        `);
        
        makeAjaxRequest('get_folder_structure', {
            user_id: selectedUserId,
            folder_path: path
        })
            .done(function(response) {
                if (response.success) {
                    displayFileStructure(response.data, path);
                    updateBreadcrumb(path);
                    showNotification('Struttura file caricata', 'success', 2000);
                } else {
                    $structure.html(`
                        <div class="error-state">
                            <span class="dashicons dashicons-warning"></span>
                            <h4>Errore caricamento struttura</h4>
                            <p>${response.data}</p>
                            <button type="button" class="btn btn-primary" onclick="window.navalEgtDebug.loadFileStructure('${path}')">
                                <span class="dashicons dashicons-update"></span>
                                Riprova
                            </button>
                        </div>
                    `);
                    showNotification('Errore nel caricamento: ' + response.data, 'error');
                }
            })
            .fail(function() {
                $structure.html(`
                    <div class="error-state">
                        <span class="dashicons dashicons-warning"></span>
                        <h4>Errore di comunicazione</h4>
                        <p>Impossibile comunicare con il server</p>
                        <button type="button" class="btn btn-primary" onclick="window.navalEgtDebug.loadFileStructure('${path}')">
                            <span class="dashicons dashicons-update"></span>
                            Riprova
                        </button>
                    </div>
                `);
                showNotification('Errore di comunicazione', 'error');
            });
    }
    
    function displayFileStructure(data, path) {
        const $structure = $('#file-structure');
        
        if (!data.folders || data.folders.length === 0) {
            $structure.html(`
                <div class="empty-state">
                    <span class="dashicons dashicons-portfolio"></span>
                    <h4>Cartella vuota</h4>
                    <p>Non ci sono file o cartelle in questo percorso</p>
                    <button type="button" class="btn btn-info" onclick="window.navalEgtDebug.syncUserFolder()">
                        <span class="dashicons dashicons-update-alt"></span>
                        Sincronizza cartella
                    </button>
                </div>
            `);
            return;
        }
        
        let html = '<ul class="file-tree">';
        
        // Prima le cartelle
        data.folders.forEach(function(item) {
            if (item.type === 'folder') {
                html += `
                    <li class="file-tree-item folder-item" data-path="${item.path}" data-name="${item.name}">
                        <div class="tree-item-content">
                            <div class="tree-item-icon folder">
                                <span class="dashicons dashicons-category"></span>
                            </div>
                            <div class="tree-item-details">
                                <div class="tree-item-name">${item.name}</div>
                                <div class="tree-item-meta">
                                    <span>Cartella</span>
                                </div>
                            </div>
                            <div class="tree-item-actions">
                                <button type="button" class="tree-action-btn enter-folder-btn" title="Apri cartella">
                                    <span class="dashicons dashicons-arrow-right-alt2"></span>
                                </button>
                            </div>
                        </div>
                    </li>
                `;
            }
        });
        
        // Poi i file
        data.folders.forEach(function(item) {
            if (item.type === 'file') {
                const fileIcon = getFileIcon(item.name);
                const fileSize = formatFileSize(item.size || 0);
                const fileDate = formatDate(item.modified || '');
                
                html += `
                    <li class="file-tree-item file-item" data-path="${item.path}" data-file-id="${item.db_id || ''}" data-name="${item.name}">
                        <div class="tree-item-content">
                            <div class="tree-item-icon file">
                                <span class="${fileIcon}"></span>
                            </div>
                            <div class="tree-item-details">
                                <div class="tree-item-name">${item.name}</div>
                                <div class="tree-item-meta">
                                    <span>${fileSize}</span>
                                    <span>${fileDate}</span>
                                </div>
                            </div>
                            <div class="tree-item-actions">
                                ${item.db_id ? `
                                    <button type="button" class="tree-action-btn download-file-btn" title="Scarica">
                                        <span class="dashicons dashicons-download"></span>
                                    </button>
                                    <button type="button" class="tree-action-btn file-info-btn" title="Informazioni">
                                        <span class="dashicons dashicons-info"></span>
                                    </button>
                                    <button type="button" class="tree-action-btn delete-file-btn" title="Elimina">
                                        <span class="dashicons dashicons-trash"></span>
                                    </button>
                                ` : `
                                    <small style="color: #666; font-style: italic;">Non sincronizzato</small>
                                `}
                            </div>
                        </div>
                    </li>
                `;
            }
        });
        
        html += '</ul>';
        
        // Add back button if not in root
        if (path) {
            const backButton = `
                <div style="margin-bottom: 20px;">
                    <button type="button" class="btn btn-secondary" id="back-folder-btn">
                        <span class="dashicons dashicons-arrow-left-alt2"></span>
                        Torna alla cartella superiore
                    </button>
                </div>
            `;
            html = backButton + html;
        }
        
        $structure.html(html);
    }
    
    function updateBreadcrumb(path) {
        const $breadcrumb = $('#breadcrumb');
        let html = `
            <span class="breadcrumb-item ${!path ? 'active' : ''}" data-path="">
                <span class="dashicons dashicons-admin-home"></span>
                Cartella Principale
            </span>
        `;
        
        if (path) {
            const parts = path.split('/').filter(p => p.length > 0);
            let currentPath = '';
            
            parts.forEach(function(part, index) {
                currentPath += '/' + part;
                const isLast = index === parts.length - 1;
                
                html += `
                    <span class="breadcrumb-separator">/</span>
                    <span class="breadcrumb-item ${isLast ? 'active' : ''}" data-path="${currentPath}">
                        ${part}
                    </span>
                `;
            });
        }
        
        $breadcrumb.html(html);
    }
    
    function goBackFolder() {
        const pathParts = currentPath.split('/').filter(p => p.length > 0);
        pathParts.pop();
        currentPath = pathParts.join('/');
        loadFileStructure(currentPath);
    }
    
    function openCreateFolderModal() {
        $('#destination-path').text(currentPath || '/cartella-principale');
        $('#create-folder-modal').show();
        $('#folder-name').focus();
    }
    
    function submitCreateFolder() {
        const folderName = $('#folder-name').val().trim();
        if (!folderName) {
            showNotification('Inserisci un nome per la cartella', 'error');
            return;
        }
        
        if (selectedUserId <= 0) {
            showNotification('Seleziona un utente', 'error');
            return;
        }
        
        const $submitBtn = $('#create-folder-form button[type="submit"]');
        const originalText = $submitBtn.html();
        
        $submitBtn.html('<span class="dashicons dashicons-update spin"></span> Creando...').prop('disabled', true);
        
        makeAjaxRequest('create_folder', {
            user_id: selectedUserId,
            folder_name: folderName,
            parent_path: currentPath
        })
            .done(function(response) {
                if (response.success) {
                    showNotification('Cartella creata con successo!', 'success');
                    $('#create-folder-modal').hide();
                    $('#folder-name').val('');
                    loadFileStructure(currentPath);
                } else {
                    showNotification('Errore nella creazione della cartella: ' + response.data, 'error');
                }
            })
            .fail(function() {
                showNotification('Errore di connessione durante la creazione della cartella', 'error');
            })
            .always(function() {
                $submitBtn.html(originalText).prop('disabled', false);
            });
    }
    
    function openUploadModal() {
        loadFoldersForUpload();
        $('#upload-modal').show();
    }
    
    function loadFoldersForUpload() {
        if (selectedUserId <= 0) {
            showNotification('Seleziona un utente prima di caricare file', 'error');
            return;
        }
        
        const $select = $('#upload-destination');
        $select.html('<option value="">Caricamento cartelle...</option>');
        
        makeAjaxRequest('get_all_folders', {user_id: selectedUserId})
            .done(function(response) {
                if (response.success) {
                    let options = '<option value="">Seleziona cartella di destinazione...</option>';
                    
                    response.data.forEach(function(folder) {
                        const selected = folder.path === currentPath ? 'selected' : '';
                        options += `<option value="${folder.path}" ${selected}>${folder.display_name}</option>`;
                    });
                    
                    $select.html(options);
                } else {
                    $select.html('<option value="">Errore caricamento cartelle</option>');
                    showNotification('Errore nel caricamento delle cartelle: ' + response.data, 'error');
                }
            })
            .fail(function() {
                $select.html('<option value="">Errore caricamento cartelle</option>');
                showNotification('Errore di connessione nel caricamento cartelle', 'error');
            });
    }
    
    function setupFileUpload() {
        const $dropZone = $('#file-drop-zone');
        const $fileInput = $('#file-input');
        
        // Click to select files
        $dropZone.on('click', function(e) {
            e.preventDefault();
            $fileInput.click();
        });
        
        // Drag and drop
        $dropZone.on('dragover dragenter', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).addClass('dragover');
        });
        
        $dropZone.on('dragleave dragend drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('dragover');
        });
        
        $dropZone.on('drop', function(e) {
            e.preventDefault();
            const files = e.originalEvent.dataTransfer.files;
            $fileInput[0].files = files;
            displaySelectedFiles(files);
        });
        
        // File input change
        $fileInput.on('change', function() {
            displaySelectedFiles(this.files);
        });
    }
    
    function displaySelectedFiles(files) {
        if (files.length === 0) {
            $('#selected-files').hide();
            return;
        }
        
        let html = '';
        Array.from(files).forEach(function(file, index) {
            const icon = getFileIcon(file.name);
            const size = formatFileSize(file.size);
            
            html += `
                <div class="file-preview-item" data-index="${index}">
                    <div class="file-preview-icon">
                        <span class="${icon}"></span>
                    </div>
                    <div class="file-preview-details">
                        <div class="file-preview-name">${file.name}</div>
                        <div class="file-preview-size">${size}</div>
                    </div>
                    <button type="button" class="file-remove-btn" data-index="${index}">
                        <span class="dashicons dashicons-no"></span>
                    </button>
                </div>
            `;
        });
        
        $('#files-preview').html(html);
        $('#selected-files').show();
    }
    
    function removeFileFromUpload($btn) {
        const index = parseInt($btn.data('index'));
        const $fileInput = $('#file-input')[0];
        const dt = new DataTransfer();
        
        Array.from($fileInput.files).forEach(function(file, i) {
            if (i !== index) {
                dt.items.add(file);
            }
        });
        
        $fileInput.files = dt.files;
        displaySelectedFiles($fileInput.files);
    }
    
    function submitUploadFiles() {
        const destination = $('#upload-destination').val();
        const files = $('#file-input')[0].files;
        
        if (!destination) {
            showNotification('Seleziona una cartella di destinazione', 'error');
            return;
        }
        
        if (files.length === 0) {
            showNotification('Seleziona almeno un file da caricare', 'error');
            return;
        }
        
        if (selectedUserId <= 0) {
            showNotification('Seleziona un utente', 'error');
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'naval_egt_ajax');
        formData.append('naval_action', 'upload_files');
        formData.append('user_id', selectedUserId);
        formData.append('destination_folder', destination);
        formData.append('nonce', ajaxConfig.nonce);
        
        Array.from(files).forEach(function(file) {
            formData.append('files[]', file);
        });
        
        const $progress = $('#upload-progress');
        const $progressFill = $('.progress-fill');
        const $progressText = $('.progress-text');
        const $progressPercentage = $('.progress-percentage');
        const $submitBtn = $('#upload-form button[type="submit"]');
        
        $progress.show();
        $submitBtn.prop('disabled', true);
        
        $.ajax({
            url: ajaxConfig.url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            xhr: function() {
                const xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function(evt) {
                    if (evt.lengthComputable) {
                        const percentComplete = Math.round((evt.loaded / evt.total) * 100);
                        $progressFill.css('width', percentComplete + '%');
                        $progressPercentage.text(percentComplete + '%');
                        
                        if (percentComplete < 100) {
                            $progressText.text('Caricamento in corso...');
                        } else {
                            $progressText.text('Elaborazione...');
                        }
                    }
                }, false);
                return xhr;
            },
            success: function(response) {
                if (response.success) {
                    showNotification('File caricati con successo!', 'success');
                    $('#upload-modal').hide();
                    resetUploadForm();
                    loadFileStructure(currentPath);
                } else {
                    showNotification('Errore durante il caricamento: ' + response.data, 'error');
                }
            },
            error: function(xhr, status, error) {
                showNotification('Errore di connessione durante il caricamento: ' + error, 'error');
            },
            complete: function() {
                $progress.hide();
                $submitBtn.prop('disabled', false);
                $progressFill.css('width', '0%');
                $progressText.text('Upload in corso...');
                $progressPercentage.text('0%');
            }
        });
    }
    
    function resetUploadForm() {
        $('#file-input').val('');
        $('#selected-files').hide();
        $('#files-preview').empty();
        $('#upload-destination').val('');
    }
    
    function downloadFile($btn) {
        const fileId = $btn.closest('.file-tree-item').data('file-id');
        if (!fileId) {
            showNotification('ID file non valido', 'error');
            return;
        }
        
        showNotification('Avvio download...', 'info', 2000);
        
        // Create a form to download the file
        const form = $('<form>', {
            'method': 'POST',
            'action': ajaxConfig.url,
            'target': '_blank'
        });
        
        form.append($('<input>', {
            'type': 'hidden',
            'name': 'action',
            'value': 'naval_egt_download_file'
        }));
        
        form.append($('<input>', {
            'type': 'hidden',
            'name': 'file_id',
            'value': fileId
        }));
        
        form.append($('<input>', {
            'type': 'hidden',
            'name': 'nonce',
            'value': ajaxConfig.nonce
        }));
        
        $('body').append(form);
        form.submit();
        form.remove();
    }
    
    function showFileInfo($btn) {
        const fileId = $btn.closest('.file-tree-item').data('file-id');
        if (!fileId) {
            showNotification('ID file non valido', 'error');
            return;
        }
        
        $('#file-info-modal').show();
        $('#file-info-content').html('<div class="loading-spinner"><span class="dashicons dashicons-update spin"></span> Caricamento...</div>');
        
        $.post(ajaxConfig.url, {
            action: 'naval_egt_get_file_info',
            file_id: fileId,
            nonce: ajaxConfig.nonce
        }, function(response) {
            if (response.success) {
                const file = response.data;
                const html = `
                    <div class="file-info-details">
                        <div class="file-info-header">
                            <div class="file-icon-large">
                                <span class="${getFileIcon(file.name)}" style="font-size: 48px;"></span>
                            </div>
                            <div class="file-info-title">
                                <h4>${file.name}</h4>
                                <p>${file.type}</p>
                            </div>
                        </div>
                        <div class="file-info-meta" style="margin-top: 20px;">
                            <div class="meta-item" style="margin-bottom: 10px;">
                                <strong>Dimensione:</strong> ${file.size}
                            </div>
                            <div class="meta-item" style="margin-bottom: 10px;">
                                <strong>Caricato:</strong> ${file.uploaded}
                            </div>
                            <div class="meta-item" style="margin-bottom: 10px;">
                                <strong>Utente:</strong> ${file.user} (${file.user_code})
                            </div>
                        </div>
                    </div>
                `;
                $('#file-info-content').html(html);
            } else {
                $('#file-info-content').html('<div class="error-state">Errore nel caricamento delle informazioni del file: ' + response.data + '</div>');
            }
        }).fail(function() {
            $('#file-info-content').html('<div class="error-state">Errore di connessione</div>');
        });
    }
    
    function deleteFile($btn) {
        const fileId = $btn.closest('.file-tree-item').data('file-id');
        const fileName = $btn.closest('.file-tree-item').find('.tree-item-name').text();
        
        if (!fileId) {
            showNotification('ID file non valido', 'error');
            return;
        }
        
        if (!confirm('Sei sicuro di voler eliminare il file "' + fileName + '"?')) {
            return;
        }
        
        const originalHtml = $btn.html();
        $btn.html('<span class="dashicons dashicons-update spin"></span>').prop('disabled', true);
        
        makeAjaxRequest('delete_file', {file_id: fileId})
            .done(function(response) {
                if (response.success) {
                    showNotification('File eliminato con successo', 'success');
                    loadFileStructure(currentPath);
                } else {
                    showNotification('Errore nell\'eliminazione del file: ' + response.data, 'error');
                }
            })
            .fail(function() {
                showNotification('Errore di connessione durante l\'eliminazione', 'error');
            })
            .always(function() {
                $btn.html(originalHtml).prop('disabled', false);
            });
    }
    
    function closeModal(e) {
        const $modal = $(e.target).closest('.naval-modal');
        $modal.hide();
        
        // Reset forms when closing modals
        if ($modal.attr('id') === 'create-folder-modal') {
            $('#folder-name').val('');
        } else if ($modal.attr('id') === 'upload-modal') {
            resetUploadForm();
        }
    }
    
    function openDebugModal() {
        $('#debug-modal').show();
        loadDebugLogs();
    }
    
    function loadDebugLogs() {
        $('#debug-logs-content').html('<div style="color: #888;">Caricamento log...</div>');
        
        makeAjaxRequest('get_debug_logs')
            .done(function(response) {
                if (response.success) {
                    const logs = response.data.logs || [];
                    
                    if (logs.length === 0) {
                        $('#debug-logs-content').html('<div style="color: #888;">Nessun log disponibile</div>');
                        return;
                    }
                    
                    let html = '';
                    logs.forEach(function(log) {
                        const timestamp = log.timestamp || 'N/A';
                        const message = log.message || 'No message';
                        const data = log.data ? JSON.stringify(log.data, null, 2) : '';
                        
                        html += `
                            <div style="margin-bottom: 15px; border-bottom: 1px solid #333; padding-bottom: 10px;">
                                <div style="color: #4CAF50; font-weight: bold;">[${timestamp}]</div>
                                <div style="color: #FFF; margin: 5px 0;">${message}</div>
                                ${data ? `<div style="color: #888; font-size: 11px; margin-left: 20px; white-space: pre-wrap;">${data}</div>` : ''}
                            </div>
                        `;
                    });
                    
                    $('#debug-logs-content').html(html);
                    
                    const container = document.getElementById('debug-logs-content');
                    container.scrollTop = container.scrollHeight;
                } else {
                    $('#debug-logs-content').html('<div style="color: #f44336;">Errore nel caricamento dei log: ' + response.data + '</div>');
                }
            })
            .fail(function() {
                $('#debug-logs-content').html('<div style="color: #f44336;">Errore di comunicazione</div>');
            });
    }
    
    function clearDebugLogs() {
        makeAjaxRequest('clear_debug_logs')
            .done(function(response) {
                if (response.success) {
                    showNotification('Log di debug cancellati', 'success');
                    loadDebugLogs();
                } else {
                    showNotification('Errore nella cancellazione dei log: ' + response.data, 'error');
                }
            })
            .fail(function() {
                showNotification('Errore di connessione nella cancellazione log', 'error');
            });
    }
    
    // === UTILITY FUNCTIONS ===
    
    function appendDebugOutput(message) {
        const $output = $('#debug-output');
        $output.show();
        
        const timestamp = new Date().toLocaleTimeString();
        const line = '[' + timestamp + '] ' + message + '\n';
        
        $output.append(line);
        $output.scrollTop($output[0].scrollHeight);
        
        const lines = $output.text().split('\n');
        if (lines.length > 100) {
            $output.text(lines.slice(-100).join('\n'));
        }
    }
    
    function showNotification(message, type = 'info', duration = 5000) {
        $('.naval-notification').remove();
        
        const typeClasses = {
            'success': 'notice-success',
            'error': 'notice-error',
            'warning': 'notice-warning',
            'info': 'notice-info'
        };
        
        const icons = {
            'success': 'yes-alt',
            'error': 'warning',
            'warning': 'flag',
            'info': 'info'
        };
        
        const notification = $(`
            <div class="notice ${typeClasses[type] || 'notice-info'} naval-notification" style="position: fixed; top: 32px; right: 20px; z-index: 999999; max-width: 400px; padding: 15px; border-left: 4px solid; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                <div style="display: flex; align-items: flex-start; gap: 10px;">
                    <span class="dashicons dashicons-${icons[type] || 'info'}" style="margin-top: 2px;"></span>
                    <div style="flex: 1;">
                        <p style="margin: 0; white-space: pre-line; word-wrap: break-word;">${message}</p>
                    </div>
                    <button type="button" class="notice-dismiss" style="background: none; border: none; cursor: pointer; padding: 0;">
                        <span class="dashicons dashicons-dismiss"></span>
                    </button>
                </div>
            </div>
        `);
        
        $('body').append(notification);
        
        if (duration > 0) {
            setTimeout(() => {
                notification.fadeOut(300, () => notification.remove());
            }, duration);
        }
        
        notification.find('.notice-dismiss').on('click', function() {
            notification.fadeOut(300, () => notification.remove());
        });
    }
    
    function getFileIcon(fileName) {
        const ext = fileName.split('.').pop().toLowerCase();
        const iconMap = {
            'pdf': 'dashicons-media-document',
            'doc': 'dashicons-media-document',
            'docx': 'dashicons-media-document',
            'xls': 'dashicons-media-spreadsheet',
            'xlsx': 'dashicons-media-spreadsheet',
            'jpg': 'dashicons-format-image',
            'jpeg': 'dashicons-format-image',
            'png': 'dashicons-format-image',
            'gif': 'dashicons-format-image',
            'zip': 'dashicons-media-archive',
            'rar': 'dashicons-media-archive',
            'dwg': 'dashicons-admin-customizer',
            'dxf': 'dashicons-admin-customizer'
        };
        return iconMap[ext] || 'dashicons-media-default';
    }
    
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    function formatDate(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        return date.toLocaleDateString('it-IT');
    }
    
    // Keyboard shortcuts
    $(document).on('keydown', function(e) {
        // Escape key to close modals
        if (e.key === 'Escape') {
            $('.naval-modal:visible').hide();
        }
        
        // Ctrl+U to open upload modal (if user selected)
        if (e.ctrlKey && e.key === 'u' && selectedUserId > 0) {
            e.preventDefault();
            openUploadModal();
        }
        
        // Ctrl+N to create new folder (if user selected)
        if (e.ctrlKey && e.key === 'n' && selectedUserId > 0) {
            e.preventDefault();
            openCreateFolderModal();
        }
        
        // F5 to refresh file structure
        if (e.key === 'F5' && selectedUserId > 0) {
            e.preventDefault();
            loadFileStructure(currentPath);
        }
        
        // Ctrl+R to sync folder
        if (e.ctrlKey && e.key === 'r' && selectedUserId > 0) {
            e.preventDefault();
            syncUserFolder();
        }
    });
    
    // Auto-refresh ogni 5 minuti per mantenere la sessione attiva
    setInterval(function() {
        if (selectedUserId > 0) {
            // Ping silenzioso per mantenere la sessione
            makeAjaxRequest('test_ajax_connection', {user_id: selectedUserId})
                .done(function(response) {
                    console.log('Session keep-alive ping successful');
                })
                .fail(function() {
                    console.log('Session keep-alive ping failed');
                });
        }
    }, 300000); // 5 minuti
    
    // Rilevamento disconnect per riconnessione automatica
    $(window).on('online', function() {
        showNotification('Connessione ristabilita', 'success', 3000);
        if (selectedUserId > 0) {
            verifyConnection();
        }
    });
    
    $(window).on('offline', function() {
        showNotification('Connessione persa - verifica la tua connessione internet', 'warning', 0);
    });
    
    // Gestione errori AJAX globali
    $(document).ajaxError(function(event, jqXHR, ajaxSettings, thrownError) {
        if (jqXHR.status === 0 && jqXHR.statusText === 'error') {
            showNotification('Connessione di rete persa', 'error', 0);
        } else if (jqXHR.status === 500) {
            showNotification('Errore del server (500) - riprova più tardi', 'error');
        } else if (jqXHR.status === 403) {
            showNotification('Accesso negato - ricarica la pagina', 'error');
        }
    });
    
    // Salvataggio automatico dello stato
    window.addEventListener('beforeunload', function() {
        if (selectedUserId > 0) {
            localStorage.setItem('naval_egt_last_user', selectedUserId);
            localStorage.setItem('naval_egt_last_path', currentPath);
        }
    });
    
    // Ripristino stato precedente
    $(document).ready(function() {
        if (selectedUserId <= 0) {
            const lastUser = localStorage.getItem('naval_egt_last_user');
            const lastPath = localStorage.getItem('naval_egt_last_path');
            
            if (lastUser && ajaxConfig.isAdmin) {
                $('#user-selector').val(lastUser);
                showNotification('Ripristinato ultimo utente selezionato', 'info', 3000);
            }
            
            if (lastPath) {
                currentPath = lastPath;
            }
        }
    });
    
    // Expose functions globally for debugging
    window.navalEgtDebug = {
        testAjaxConnection,
        debugAjaxHooks,
        testDropboxConnection,
        testSystemStatus,
        verifyConnection,
        loadFileStructure,
        syncUserFolder,
        showNotification,
        selectedUserId: () => selectedUserId,
        currentPath: () => currentPath,
        setSelectedUser: (id) => { selectedUserId = id; },
        setCurrentPath: (path) => { currentPath = path; },
        makeAjaxRequest,
        resetUploadForm,
        openCreateFolderModal,
        openUploadModal
    };
    
    console.log('Naval EGT: All functions loaded and ready');
    console.log('Available debug functions:', Object.keys(window.navalEgtDebug));
    console.log('Current user ID:', selectedUserId);
    console.log('Current path:', currentPath);
});
</script>

<?php
// Log finale
error_log('Naval EGT Files Tab: Template loaded successfully with user_id=' . $selected_user_id);
?>