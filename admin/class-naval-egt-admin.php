<?php
/**
 * Classe per la gestione dell'area admin - Versione semplificata senza File e Log
 */

if (!defined('ABSPATH')) {
    exit;
}

class Naval_EGT_Admin {
    
    private static $instance = null;
    private $admin_notices = array();
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_naval_egt_ajax', array($this, 'handle_ajax_requests'));
        add_action('wp_ajax_naval_egt_export', array($this, 'handle_export_requests'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_notices', array($this, 'display_admin_notices'));
    }
    
    /**
     * Aggiunge menu admin
     */
    public function add_admin_menu() {
        add_menu_page(
            'Naval EGT',
            'Naval EGT',
            'manage_options',
            'naval-egt',
            array($this, 'render_admin_page'),
            'dashicons-cloud',
            30
        );
    }
    
    /**
     * Carica script admin
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'naval-egt') === false) {
            return;
        }
        
        wp_enqueue_script('naval-egt-admin', plugin_dir_url(__FILE__) . 'js/admin.js', array('jquery'), '1.0.0', true);
        wp_enqueue_style('naval-egt-admin', plugin_dir_url(__FILE__) . 'css/admin.css', array(), '1.0.0');
        
        wp_localize_script('naval-egt-admin', 'naval_egt_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('naval_egt_nonce')
        ));
    }
    
    /**
     * Inizializzazione admin
     */
    public function admin_init() {
        // Gestione callback Dropbox
        if (isset($_GET['page']) && $_GET['page'] === 'naval-egt' && 
            isset($_GET['tab']) && $_GET['tab'] === 'dropbox' && 
            isset($_GET['action']) && $_GET['action'] === 'callback') {
            $this->handle_dropbox_callback();
        }
        
        if (isset($_GET['dropbox_callback']) && $_GET['dropbox_callback'] === '1') {
            $this->handle_dropbox_callback_legacy();
        }

        // Gestione azioni debug Dropbox
        if (isset($_GET['page']) && $_GET['page'] === 'naval-egt' && 
            isset($_GET['tab']) && $_GET['tab'] === 'dropbox') {
            $this->handle_dropbox_debug_actions();
        }
    }

    private function handle_dropbox_debug_actions() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $action = isset($_GET['dropbox_debug_action']) ? sanitize_text_field($_GET['dropbox_debug_action']) : '';
        
        if (empty($action)) {
            return;
        }

        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'naval_egt_dropbox_debug')) {
            $this->add_admin_notice('Errore di sicurezza. Riprova.', 'error');
            return;
        }

        $dropbox = Naval_EGT_Dropbox::get_instance();

        switch ($action) {
            case 'test_diagnosis':
                $diagnosis = $dropbox->full_system_diagnosis();
                $this->set_dropbox_diagnosis_data($diagnosis);
                $this->add_admin_notice('Diagnosi completa eseguita.', 'info');
                break;

            case 'test_connection':
                $test = $dropbox->test_connection();
                if ($test['success']) {
                    $this->add_admin_notice('Test connessione riuscito: ' . $test['message'], 'success');
                } else {
                    $this->add_admin_notice('Test connessione fallito: ' . $test['message'], 'error');
                }
                break;

            case 'regenerate_token':
                $result = $dropbox->force_reauth();
                if ($result['success']) {
                    $this->add_admin_notice($result['message'], 'success');
                } else {
                    $this->add_admin_notice('Errore: ' . $result['message'], 'error');
                }
                break;
        }

        wp_redirect(admin_url('admin.php?page=naval-egt&tab=dropbox&debug_completed=1'));
        exit;
    }

    private function set_dropbox_diagnosis_data($diagnosis) {
        set_transient('naval_egt_dropbox_diagnosis', $diagnosis, 300);
    }
    
    public function add_admin_notice($message, $type = 'success') {
        $this->admin_notices[] = array(
            'message' => $message,
            'type' => $type
        );
    }
    
    public function display_admin_notices() {
        foreach ($this->admin_notices as $notice) {
            $class = 'notice notice-' . $notice['type'];
            echo '<div class="' . esc_attr($class) . '"><p>' . wp_kses_post($notice['message']) . '</p></div>';
        }
    }
    
    /**
     * Gestisce callback Dropbox
     */
    private function handle_dropbox_callback() {
        if (!current_user_can('manage_options')) {
            wp_die('Permessi insufficienti');
        }
        
        if (isset($_GET['error'])) {
            $error_message = isset($_GET['error_description']) ? $_GET['error_description'] : $_GET['error'];
            $this->add_admin_notice('Errore Dropbox: ' . $error_message, 'error');
            return;
        }
        
        if (!isset($_GET['code'])) {
            $this->add_admin_notice('Codice di autorizzazione mancante', 'error');
            return;
        }
        
        $dropbox = Naval_EGT_Dropbox::get_instance();
        $result = $dropbox->handle_authorization_callback();
        
        if ($result['success']) {
            $this->add_admin_notice($result['message'], 'success');
        } else {
            $this->add_admin_notice('Errore configurazione: ' . $result['message'], 'error');
        }
    }
    
    private function handle_dropbox_callback_legacy() {
        if (!current_user_can('manage_options')) {
            wp_die('Permessi insufficienti');
        }
        
        if (isset($_GET['error'])) {
            $error_message = isset($_GET['error_description']) ? $_GET['error_description'] : $_GET['error'];
            error_log('Naval EGT: Errore OAuth: ' . $error_message);
            
            add_action('admin_notices', function() use ($error_message) {
                echo '<div class="notice notice-error"><p><strong>Errore Dropbox:</strong> ' . esc_html($error_message) . '</p></div>';
            });
            return;
        }
        
        if (isset($_GET['code'])) {
            $code = sanitize_text_field($_GET['code']);
            $redirect_uri = admin_url('admin.php?page=naval-egt-settings&dropbox_callback=1');
            
            $dropbox = Naval_EGT_Dropbox::get_instance();
            $result = $dropbox->exchange_code_for_token($code, $redirect_uri);
            
            if ($result['success']) {
                $account_info = $dropbox->get_account_info();
                if ($account_info['success']) {
                    $name = isset($account_info['data']['name']['display_name']) ? $account_info['data']['name']['display_name'] : 'Utente';
                    $this->add_admin_notice('Dropbox configurato con successo! Connesso come: ' . $name, 'success');
                } else {
                    $this->add_admin_notice('Token ottenuto ma test di connessione fallito.', 'warning');
                }
            } else {
                $this->add_admin_notice('Errore durante l\'ottenimento del token: ' . $result['message'], 'error');
            }
            
            wp_redirect(admin_url('admin.php?page=naval-egt&tab=dropbox'));
            exit;
        }
    }
    
    /**
     * Gestisce richieste AJAX - Solo per utenti e Dropbox
     */
    public function handle_ajax_requests() {
        check_ajax_referer('naval_egt_nonce', 'nonce');
        
        $action = sanitize_text_field($_POST['naval_action'] ?? '');
        
        switch ($action) {
            case 'get_users_list':
                $this->ajax_get_users_list();
                break;
                
            case 'sync_all_user_folders':
                $this->ajax_sync_all_user_folders();
                break;
                
            case 'test_dropbox_connection':
                $this->ajax_test_dropbox_connection();
                break;
            
            case 'get_user_data':
                $this->ajax_get_user_data();
                break;
                
            case 'create_user':
                $this->ajax_create_user();
                break;
                
            case 'update_user':
                $this->ajax_update_user();
                break;
                
            case 'delete_user':
                $this->ajax_delete_user();
                break;
                
            case 'toggle_user_status':
                $this->ajax_toggle_user_status();
                break;
                
            case 'bulk_user_action':
                $this->ajax_bulk_user_action();
                break;
            
            default:
                wp_send_json_error('Azione non valida');
        }
    }
    
    private function ajax_get_users_list() {
        $users = Naval_EGT_User_Manager::get_users(array(), 100, 0);
        wp_send_json_success(array('users' => $users));
    }
    
    private function ajax_sync_all_user_folders() {
        $dropbox = Naval_EGT_Dropbox::get_instance();
        
        if (!$dropbox->is_configured()) {
            wp_send_json_error('Dropbox non configurato');
            return;
        }
        
        $users = Naval_EGT_User_Manager::get_users(array(), 1000, 0);
        $stats = array(
            'users_processed' => 0,
            'folders_found' => 0,
            'files_synced' => 0,
            'errors' => array()
        );
        
        foreach ($users as $user) {
            $stats['users_processed']++;
            
            try {
                $sync_result = $dropbox->sync_user_folder($user['user_code']);
                
                if ($sync_result['success']) {
                    if ($sync_result['folder_found']) {
                        $stats['folders_found']++;
                        $stats['files_synced'] += $sync_result['files_synced'];
                    }
                } else {
                    $stats['errors'][] = $user['user_code'] . ': ' . $sync_result['message'];
                }
            } catch (Exception $e) {
                $stats['errors'][] = $user['user_code'] . ': ' . $e->getMessage();
            }
        }
        
        wp_send_json_success(array('stats' => $stats));
    }
    
    private function ajax_test_dropbox_connection() {
        $dropbox = Naval_EGT_Dropbox::get_instance();
        
        $test_result = $dropbox->test_connection();
        
        if ($test_result['success']) {
            $account_info = $dropbox->get_account_info();
            $account_email = $account_info['success'] && isset($account_info['data']['email']) ? 
                            $account_info['data']['email'] : 'Account Dropbox';
            
            wp_send_json_success(array(
                'message' => $test_result['message'],
                'account_email' => $account_email
            ));
        } else {
            wp_send_json_error($test_result['message']);
        }
    }
    
    private function ajax_get_user_data() {
        if (!isset($_POST['user_id'])) {
            wp_send_json_error('ID utente mancante');
            return;
        }
        
        $user_id = intval($_POST['user_id']);
        $user = Naval_EGT_User_Manager::get_user_by_id($user_id);
        
        if (!$user) {
            wp_send_json_error('Utente non trovato');
            return;
        }
        
        wp_send_json_success($user);
    }
    
    private function ajax_create_user() {
        $user_data = $this->validate_user_data($_POST);
        
        if (is_wp_error($user_data)) {
            wp_send_json_error($user_data->get_error_message());
            return;
        }
        
        $result = Naval_EGT_User_Manager::create_user($user_data);
        
        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    private function ajax_update_user() {
        if (!isset($_POST['user_id'])) {
            wp_send_json_error('ID utente mancante');
            return;
        }
        
        $user_id = intval($_POST['user_id']);
        $user_data = $this->validate_user_data($_POST, true);
        
        if (is_wp_error($user_data)) {
            wp_send_json_error($user_data->get_error_message());
            return;
        }
        
        $result = Naval_EGT_User_Manager::update_user($user_id, $user_data);
        
        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    private function ajax_delete_user() {
        if (!isset($_POST['user_id'])) {
            wp_send_json_error('ID utente mancante');
            return;
        }
        
        $user_id = intval($_POST['user_id']);
        $result = Naval_EGT_User_Manager::delete_user($user_id);
        
        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    private function ajax_toggle_user_status() {
        if (!isset($_POST['user_id']) || !isset($_POST['status'])) {
            wp_send_json_error('Parametri mancanti');
            return;
        }
        
        $user_id = intval($_POST['user_id']);
        $status = sanitize_text_field($_POST['status']);
        
        if (!in_array($status, ['ATTIVO', 'SOSPESO'])) {
            wp_send_json_error('Status non valido');
            return;
        }
        
        $result = Naval_EGT_User_Manager::update_user_status($user_id, $status);
        
        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    private function ajax_bulk_user_action() {
        if (!isset($_POST['bulk_action']) || !isset($_POST['user_ids'])) {
            wp_send_json_error('Parametri mancanti');
            return;
        }
        
        $bulk_action = sanitize_text_field($_POST['bulk_action']);
        $user_ids = array_map('intval', $_POST['user_ids']);
        
        if (!in_array($bulk_action, ['activate', 'suspend', 'delete'])) {
            wp_send_json_error('Azione non valida');
            return;
        }
        
        $results = array(
            'success' => 0,
            'failed' => 0,
            'errors' => array()
        );
        
        foreach ($user_ids as $user_id) {
            switch ($bulk_action) {
                case 'activate':
                    $result = Naval_EGT_User_Manager::update_user_status($user_id, 'ATTIVO');
                    break;
                    
                case 'suspend':
                    $result = Naval_EGT_User_Manager::update_user_status($user_id, 'SOSPESO');
                    break;
                    
                case 'delete':
                    $result = Naval_EGT_User_Manager::delete_user($user_id);
                    break;
            }
            
            if ($result['success']) {
                $results['success']++;
            } else {
                $results['failed']++;
                $results['errors'][] = "Utente ID {$user_id}: " . $result['message'];
            }
        }
        
        $message = sprintf(
            'Operazione completata: %d successi, %d fallimenti',
            $results['success'],
            $results['failed']
        );
        
        wp_send_json_success(array(
            'message' => $message,
            'details' => $results
        ));
    }
    
    /**
     * Gestisce richieste di export
     */
    public function handle_export_requests() {
        check_ajax_referer('naval_egt_nonce', 'nonce');
        
        $export_type = sanitize_text_field($_POST['export_type'] ?? '');
        
        switch ($export_type) {
            case 'users':
                $this->export_users();
                break;
            
            default:
                wp_send_json_error('Tipo di export non valido');
        }
    }
    
    private function export_users() {
        $users = Naval_EGT_User_Manager::get_users(array(), 10000, 0);
        
        $filename = 'naval_egt_users_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        fputcsv($output, array('Codice', 'Nome', 'Cognome', 'Email', 'Telefono', 'Status', 'Creato'));
        
        foreach ($users as $user) {
            fputcsv($output, array(
                $user['user_code'],
                $user['nome'],
                $user['cognome'],
                $user['email'],
                $user['telefono'],
                $user['status'],
                $user['created_at']
            ));
        }
        
        fclose($output);
        exit;
    }
    
    private function validate_user_data($data, $is_update = false) {
        $errors = array();
        
        $required_fields = array('nome', 'cognome', 'email', 'username');
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                $errors[] = sprintf('Il campo %s è obbligatorio', ucfirst($field));
            }
        }
        
        if (!$is_update && empty($data['password'])) {
            $errors[] = 'La password è obbligatoria per i nuovi utenti';
        }
        
        if (!empty($data['email']) && !is_email($data['email'])) {
            $errors[] = 'Email non valida';
        }
        
        if (!empty($data['ragione_sociale']) && empty($data['partita_iva'])) {
            $errors[] = 'Partita IVA obbligatoria se specificata la Ragione Sociale';
        }
        
        if (!empty($data['status']) && !in_array($data['status'], ['ATTIVO', 'SOSPESO'])) {
            $errors[] = 'Status non valido';
        }
        
        if (!empty($errors)) {
            return new WP_Error('validation_failed', implode(', ', $errors));
        }
        
        return array(
            'nome' => sanitize_text_field($data['nome']),
            'cognome' => sanitize_text_field($data['cognome']),
            'email' => sanitize_email($data['email']),
            'telefono' => sanitize_text_field($data['telefono'] ?? ''),
            'username' => sanitize_user($data['username']),
            'password' => $data['password'],
            'ragione_sociale' => sanitize_text_field($data['ragione_sociale'] ?? ''),
            'partita_iva' => sanitize_text_field($data['partita_iva'] ?? ''),
            'status' => sanitize_text_field($data['status'] ?? 'SOSPESO'),
            'dropbox_folder' => sanitize_text_field($data['dropbox_folder'] ?? '')
        );
    }
    
    /**
     * Renderizza pagina admin principale - SOLO 3 TAB
     */
    public function render_admin_page() {
        $current_tab = $_GET['tab'] ?? 'overview';
        
        ?>
        <div class="wrap">
            <h1>Naval EGT - Area Riservata Clienti</h1>
            
            <nav class="nav-tab-wrapper">
                <a href="?page=naval-egt&tab=overview" class="nav-tab <?php echo $current_tab === 'overview' ? 'nav-tab-active' : ''; ?>">Panoramica</a>
                <a href="?page=naval-egt&tab=users" class="nav-tab <?php echo $current_tab === 'users' ? 'nav-tab-active' : ''; ?>">Utenti</a>
                <a href="?page=naval-egt&tab=dropbox" class="nav-tab <?php echo $current_tab === 'dropbox' ? 'nav-tab-active' : ''; ?>">Dropbox</a>
            </nav>
            
            <div class="tab-content">
                <?php
                switch ($current_tab) {
                    case 'overview':
                        $this->render_overview_tab();
                        break;
                    case 'users':
                        $this->render_users_tab();
                        break;
                    case 'dropbox':
                        $this->render_dropbox_settings();
                        break;
                    default:
                        $this->render_overview_tab();
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    private function render_overview_tab() {
        $stats = Naval_EGT_Database::get_user_stats();
        $dropbox = Naval_EGT_Dropbox::get_instance();
        $dropbox_status = $dropbox->get_connection_status();
        
        ?>
        <div class="naval-egt-dashboard">
            <h2>Benvenuto in Naval EGT</h2>
            
            <div class="dashboard-stats">
                <div class="stat-card">
                    <h3>Utenti Totali</h3>
                    <div class="stat-number"><?php echo $stats['total_users'] ?? 0; ?></div>
                </div>
                
                <div class="stat-card">
                    <h3>Utenti Attivi</h3>
                    <div class="stat-number"><?php echo $stats['active_users'] ?? 0; ?></div>
                </div>
                
                <div class="stat-card">
                    <h3>In Attesa di Approvazione</h3>
                    <div class="stat-number"><?php echo $stats['pending_users'] ?? 0; ?></div>
                </div>
            </div>
            
            <div class="card">
                <h3>Stato Dropbox</h3>
                <?php if ($dropbox_status['connected']): ?>
                    <p style="color: green;"><strong>Connesso</strong></p>
                    <p><?php echo esc_html($dropbox_status['message']); ?></p>
                    <?php if (isset($dropbox_status['account_email'])): ?>
                        <p><strong>Account:</strong> <?php echo esc_html($dropbox_status['account_email']); ?></p>
                    <?php endif; ?>
                <?php else: ?>
                    <p style="color: red;"><strong>Non Connesso</strong></p>
                    <p><?php echo esc_html($dropbox_status['message']); ?></p>
                    <div style="margin-top: 15px;">
                        <a href="?page=naval-egt&tab=dropbox" class="button button-primary">Configura Dropbox</a>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="card">
                <h3>Azioni Rapide</h3>
                <div class="quick-actions">
                    <a href="?page=naval-egt&tab=users" class="button">Gestisci Utenti</a>
                    <?php if ($dropbox_status['connected']): ?>
                        <button class="button" onclick="syncAllFolders()">Sincronizza Cartelle</button>
                        <button class="button" onclick="testDropboxQuick()">Test Dropbox</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <style>
        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .stat-card {
            background: white;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            text-align: center;
        }
        
        .stat-card h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #666;
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #0073aa;
        }
        
        .card {
            background: white;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
        }
        </style>
        
        <script>
        function syncAllFolders() {
            if (!confirm('Vuoi sincronizzare tutte le cartelle utenti con Dropbox?')) return;
            
            jQuery.post(ajaxurl, {
                action: 'naval_egt_ajax',
                naval_action: 'sync_all_user_folders',
                nonce: '<?php echo wp_create_nonce('naval_egt_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    alert('Sincronizzazione completata!\n\nUtenti processati: ' + response.data.stats.users_processed + '\nCartelle trovate: ' + response.data.stats.folders_found + '\nFile sincronizzati: ' + response.data.stats.files_synced);
                    location.reload();
                } else {
                    alert('Errore durante la sincronizzazione: ' + response.data);
                }
            });
        }

        function testDropboxQuick() {
            jQuery.post(ajaxurl, {
                action: 'naval_egt_ajax',
                naval_action: 'test_dropbox_connection',
                nonce: '<?php echo wp_create_nonce('naval_egt_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    alert('Test Dropbox OK!\n\nConnesso come: ' + (response.data.account_email || 'Account Dropbox'));
                } else {
                    alert('Test Dropbox fallito!\n\nErrore: ' + response.data);
                }
            });
        }
        </script>
        <?php
    }
    
    private function render_users_tab() {
        $tab_file = NAVAL_EGT_PLUGIN_DIR . 'admin/views/tab-users.php';
        
        if (file_exists($tab_file)) {
            include_once $tab_file;
        } else {
            ?>
            <div class="card">
                <h2>Gestione Utenti</h2>
                <p>File tab non trovato: <?php echo esc_html($tab_file); ?></p>
            </div>
            <?php
        }
    }
    
    private function render_dropbox_settings() {
        $dropbox = Naval_EGT_Dropbox::get_instance();
        
        if (isset($_POST['test_dropbox_connection'])) {
            check_admin_referer('naval_egt_dropbox_test');
            
            $test_result = $dropbox->test_connection();
            
            if ($test_result['success']) {
                $this->add_admin_notice($test_result['message'], 'success');
            } else {
                $this->add_admin_notice('Test connessione fallito: ' . $test_result['message'], 'error');
            }
        }
        
        if (isset($_POST['reset_dropbox_config'])) {
            check_admin_referer('naval_egt_dropbox_reset');
            
            $result = $dropbox->disconnect();
            $this->add_admin_notice($result['message'], 'success');
        }
        
        $is_configured = $dropbox->is_configured();
        $connection_status = $dropbox->get_connection_status();
        
        ?>
        <div class="wrap">
            <h2>Configurazione Dropbox</h2>
            
            <div class="card">
                <h3>Stato Configurazione</h3>
                <table class="form-table">
                    <tr>
                        <th>Stato Dropbox</th>
                        <td>
                            <?php if ($is_configured && $connection_status['connected']): ?>
                                <span style="color: green; font-weight: bold;">CONFIGURATO E CONNESSO</span>
                                <br><small><?php echo esc_html($connection_status['message']); ?></small>
                            <?php elseif ($is_configured): ?>
                                <span style="color: orange; font-weight: bold;">CONFIGURATO MA NON CONNESSO</span>
                                <br><small><?php echo esc_html($connection_status['message']); ?></small>
                            <?php else: ?>
                                <span style="color: red; font-weight: bold;">NON CONFIGURATO</span>
                                <br><small>È necessario autorizzare l'applicazione su Dropbox</small>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Credenziali App</th>
                        <td>
                            <strong>Preconfigurate</strong>
                            <br><small>App Key e App Secret sono integrati nel plugin</small>
                        </td>
                    </tr>
                    <?php if ($connection_status['connected'] && isset($connection_status['account_email'])): ?>
                    <tr>
                        <th>Account Connesso</th>
                        <td>
                            <?php echo esc_html($connection_status['account_email']); ?>
                            <?php if (isset($connection_status['account_name'])): ?>
                                <br><small><?php echo esc_html($connection_status['account_name']); ?></small>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
            
            <?php if (!$is_configured || !$connection_status['connected']): ?>
            <div class="card">
                <h3>Autorizzazione Dropbox</h3>
                <p>Per utilizzare le funzionalità di Dropbox, è necessario autorizzare l'applicazione.</p>
                
                <?php
                $auth_url = $dropbox->get_authorization_url();
                if ($auth_url):
                ?>
                <p>
                    <a href="<?php echo esc_url($auth_url); ?>" class="button button-primary button-large">
                        Autorizza su Dropbox
                    </a>
                </p>
                <p class="description">
                    Verrai reindirizzato su Dropbox per autorizzare l'applicazione. 
                    Dopo l'autorizzazione, tornerai automaticamente qui.
                </p>
                <?php else: ?>
                <p style="color: red;">Errore nella generazione dell'URL di autorizzazione.</p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php if ($is_configured): ?>
            <div class="card">
                <h3>Gestione Configurazione</h3>
                
                <form method="post" style="display: inline-block; margin-right: 10px;">
                    <?php wp_nonce_field('naval_egt_dropbox_test'); ?>
                    <input type="submit" name="test_dropbox_connection" class="button" value="Testa Connessione" />
                </form>
                
                <button class="button" onclick="syncAllUserFolders()" style="margin-right: 10px;">Sincronizza Tutte le Cartelle</button>
                
                <form method="post" style="display: inline-block;" 
                      onsubmit="return confirm('Sei sicuro di voler disconnettere Dropbox? Dovrai riautorizzare l\'applicazione.');">
                    <?php wp_nonce_field('naval_egt_dropbox_reset'); ?>
                    <input type="submit" name="reset_dropbox_config" class="button button-secondary" value="Disconnetti Dropbox" />
                </form>
            </div>
            <?php endif; ?>
            
            <div class="card">
                <h3>Informazioni Configurazione</h3>
                <p><strong>URL di Redirect configurato nell'app Dropbox:</strong></p>
                <code><?php echo admin_url('admin.php?page=naval-egt&tab=dropbox&action=callback'); ?></code>
                
                <h4>Permessi richiesti:</h4>
                <ul>
                    <li><code>files.metadata.write</code> - Scrittura metadati file</li>
                    <li><code>files.metadata.read</code> - Lettura metadati file</li>
                    <li><code>files.content.write</code> - Scrittura contenuto file</li>
                    <li><code>files.content.read</code> - Lettura contenuto file</li>
                </ul>
                
                <p><strong>Struttura cartelle:</strong></p>
                <p>Il plugin cerca automaticamente cartelle che iniziano con il codice utente (es. <code>100001_Nome_Cliente</code>)</p>
            </div>
        </div>
        
        <script>
        function syncAllUserFolders() {
            if (!confirm('Vuoi sincronizzare tutte le cartelle utenti con Dropbox?')) return;
            
            jQuery.post(ajaxurl, {
                action: 'naval_egt_ajax',
                naval_action: 'sync_all_user_folders',
                nonce: '<?php echo wp_create_nonce('naval_egt_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    var stats = response.data.stats;
                    var message = 'Sincronizzazione completata!\n\n';
                    message += 'Utenti processati: ' + stats.users_processed + '\n';
                    message += 'Cartelle trovate: ' + stats.folders_found + '\n';
                    message += 'File sincronizzati: ' + stats.files_synced;
                    
                    if (stats.errors.length > 0) {
                        message += '\n\nErrori:\n' + stats.errors.join('\n');
                    }
                    
                    alert(message);
                } else {
                    alert('Errore durante la sincronizzazione: ' + response.data);
                }
            });
        }
        </script>
        <?php
    }
}

Naval_EGT_Admin::get_instance();