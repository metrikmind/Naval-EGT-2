<?php
/**
 * Classe per la gestione dell'area admin - Versione completa aggiornata
 * Con tutti i nuovi metodi AJAX per gestione utenti e file
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
        add_action('wp_ajax_naval_egt_download_file', array($this, 'handle_file_download'));
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
        // Gestione callback Dropbox se presente - URL AGGIORNATO
        if (isset($_GET['page']) && $_GET['page'] === 'naval-egt' && 
            isset($_GET['tab']) && $_GET['tab'] === 'dropbox' && 
            isset($_GET['action']) && $_GET['action'] === 'callback') {
            $this->handle_dropbox_callback();
        }
        
        // Mantieni compatibilità con URL legacy
        if (isset($_GET['dropbox_callback']) && $_GET['dropbox_callback'] === '1') {
            $this->handle_dropbox_callback_legacy();
        }

        // Gestione azioni debug Dropbox
        if (isset($_GET['page']) && $_GET['page'] === 'naval-egt' && 
            isset($_GET['tab']) && $_GET['tab'] === 'dropbox') {
            $this->handle_dropbox_debug_actions();
        }
    }

    /**
     * Gestisce le azioni di debug Dropbox
     */
    private function handle_dropbox_debug_actions() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $action = isset($_GET['dropbox_debug_action']) ? sanitize_text_field($_GET['dropbox_debug_action']) : '';
        
        if (empty($action)) {
            return;
        }

        // Verifica nonce per sicurezza
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'naval_egt_dropbox_debug')) {
            $this->add_admin_notice('Errore di sicurezza. Riprova.', 'error');
            return;
        }

        $dropbox = Naval_EGT_Dropbox::get_instance();

        switch ($action) {
            case 'test_diagnosis':
                $diagnosis = $dropbox->full_system_diagnosis();
                $this->set_dropbox_diagnosis_data($diagnosis);
                $this->add_admin_notice('Diagnosi completa eseguita. Controlla i risultati qui sotto.', 'info');
                break;

            case 'test_app_credentials':
                $cred_test = $dropbox->test_app_credentials();
                if ($cred_test['success']) {
                    $this->add_admin_notice('✅ Test credenziali app: ' . $cred_test['message'], 'success');
                } else {
                    $this->add_admin_notice('❌ Test credenziali app fallito: ' . $cred_test['message'], 'error');
                }
                break;

            case 'regenerate_token':
                $result = $dropbox->force_reauth();
                if ($result['success']) {
                    $this->add_admin_notice($result['message'], 'success');
                    if (!empty($result['auth_url'])) {
                        $this->set_auth_url_for_display($result['auth_url']);
                    }
                } else {
                    $this->add_admin_notice('Errore nella rigenerazione: ' . $result['message'], 'error');
                }
                break;

            case 'test_connection':
                $test = $dropbox->test_connection();
                if ($test['success']) {
                    $this->add_admin_notice('✅ Test connessione riuscito: ' . $test['message'], 'success');
                } else {
                    $this->add_admin_notice('❌ Test connessione fallito: ' . $test['message'], 'error');
                }
                break;

            case 'analyze_token':
                $analysis = $dropbox->analyze_token_detailed();
                $this->set_token_analysis_data($analysis);
                $this->add_admin_notice('Analisi token completata. Controlla i risultati qui sotto.', 'info');
                break;

            case 'test_multiple_methods':
                $tests = $dropbox->test_token_multiple_methods();
                $this->set_multiple_tests_data($tests);
                $this->add_admin_notice('Test multipli completati. Controlla i risultati qui sotto.', 'info');
                break;

            case 'debug_400_error':
                $code = isset($_GET['test_code']) ? sanitize_text_field($_GET['test_code']) : '';
                if (!empty($code)) {
                    $debug_result = $dropbox->debug_400_error($code);
                    $this->set_debug_400_data($debug_result);
                    $this->add_admin_notice('Debug errore HTTP 400 completato. Controlla i risultati.', 'info');
                } else {
                    $this->add_admin_notice('Codice di test non fornito per debug 400.', 'warning');
                }
                break;

            case 'reload_credentials':
                $dropbox->reload_credentials();
                $this->add_admin_notice('Credenziali ricaricate dal database.', 'info');
                break;

            case 'clear_debug_logs':
                $dropbox->clear_debug_logs();
                $this->add_admin_notice('Log di debug puliti.', 'success');
                break;

            case 'export_debug_info':
                $debug_info = $dropbox->export_debug_info();
                $this->set_debug_export_data($debug_info);
                $this->add_admin_notice('Informazioni debug esportate. Controlla qui sotto.', 'info');
                break;
        }

        // Redirect per pulire l'URL
        wp_redirect(admin_url('admin.php?page=naval-egt&tab=dropbox&debug_completed=1'));
        exit;
    }

    /**
     * Salva i dati della diagnosi per la visualizzazione
     */
    private function set_dropbox_diagnosis_data($diagnosis) {
        set_transient('naval_egt_dropbox_diagnosis', $diagnosis, 300); // 5 minuti
    }

    /**
     * Salva l'URL di autorizzazione per la visualizzazione
     */
    private function set_auth_url_for_display($auth_url) {
        set_transient('naval_egt_dropbox_auth_url', $auth_url, 600); // 10 minuti
    }

    /**
     * Salva i dati dell'analisi token
     */
    private function set_token_analysis_data($analysis) {
        set_transient('naval_egt_token_analysis', $analysis, 300); // 5 minuti
    }

    /**
     * Salva i dati dei test multipli
     */
    private function set_multiple_tests_data($tests) {
        set_transient('naval_egt_multiple_tests', $tests, 300); // 5 minuti
    }

    /**
     * Salva i dati del debug 400
     */
    private function set_debug_400_data($debug_result) {
        set_transient('naval_egt_debug_400', $debug_result, 300); // 5 minuti
    }

    /**
     * Salva i dati di export debug
     */
    private function set_debug_export_data($debug_info) {
        set_transient('naval_egt_debug_export', $debug_info, 300); // 5 minuti
    }
    
    /**
     * Aggiunge notice admin
     */
    public function add_admin_notice($message, $type = 'success') {
        $this->admin_notices[] = array(
            'message' => $message,
            'type' => $type
        );
    }
    
    /**
     * Mostra notice admin
     */
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
        
        // Gestisce errori OAuth
        if (isset($_GET['error'])) {
            $error_message = isset($_GET['error_description']) ? $_GET['error_description'] : $_GET['error'];
            $this->add_admin_notice('Errore Dropbox: ' . $error_message, 'error');
            return;
        }
        
        // Elabora il codice di autorizzazione
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
            
            // Se fallisce, salva i dati per il debug
            if (isset($result['debug_info'])) {
                $this->set_debug_callback_data($result);
            }
        }
    }

    /**
     * Salva i dati del callback per debug
     */
    private function set_debug_callback_data($callback_result) {
        set_transient('naval_egt_callback_debug', $callback_result, 300); // 5 minuti
    }
    
    /**
     * Gestisce callback Dropbox legacy
     */
    private function handle_dropbox_callback_legacy() {
        if (!current_user_can('manage_options')) {
            wp_die('Permessi insufficienti');
        }
        
        // Gestisce errori OAuth
        if (isset($_GET['error'])) {
            $error_message = isset($_GET['error_description']) ? $_GET['error_description'] : $_GET['error'];
            error_log('Naval EGT: Errore OAuth ricevuto: ' . $error_message);
            
            add_action('admin_notices', function() use ($error_message) {
                echo '<div class="notice notice-error"><p><strong>Errore Dropbox:</strong> ' . esc_html($error_message) . '</p></div>';
            });
            return;
        }
        
        // Elabora il codice di autorizzazione
        if (isset($_GET['code'])) {
            $code = sanitize_text_field($_GET['code']);
            $redirect_uri = admin_url('admin.php?page=naval-egt-settings&dropbox_callback=1');
            
            $dropbox = Naval_EGT_Dropbox::get_instance();
            $result = $dropbox->exchange_code_for_token($code, $redirect_uri);
            
            if ($result['success']) {
                // Test della connessione
                $account_info = $dropbox->get_account_info();
                if ($account_info['success']) {
                    $name = isset($account_info['data']['name']['display_name']) ? $account_info['data']['name']['display_name'] : 'Utente';
                    $this->add_admin_notice('Dropbox configurato con successo! Connesso come: ' . $name, 'success');
                } else {
                    $this->add_admin_notice('Token ottenuto ma test di connessione fallito. Verifica le impostazioni.', 'warning');
                }
            } else {
                $this->add_admin_notice('Errore durante l\'ottenimento del token: ' . $result['message'], 'error');
            }
            
            // Redirect per pulire l'URL
            wp_redirect(admin_url('admin.php?page=naval-egt&tab=dropbox'));
            exit;
        }
    }
    
    /**
     * Gestisce richieste AJAX - VERSIONE COMPLETA AGGIORNATA
     */
    public function handle_ajax_requests() {
        check_ajax_referer('naval_egt_nonce', 'nonce');
        
        $action = sanitize_text_field($_POST['naval_action'] ?? '');
        
        switch ($action) {
            case 'get_users_list':
                $this->ajax_get_users_list();
                break;
            
            case 'filter_logs':
                $this->ajax_filter_logs();
                break;
            
            case 'clear_logs':
                $this->ajax_clear_logs();
                break;
                
            case 'sync_all_user_folders':
                $this->ajax_sync_all_user_folders();
                break;
                
            case 'test_dropbox_connection':
                $this->ajax_test_dropbox_connection();
                break;
                
            case 'admin_upload_files':
                $this->ajax_admin_upload_files();
                break;
            
            // NUOVE AZIONI PER GESTIONE UTENTI
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
            
            // NUOVE AZIONI PER GESTIONE FILE
            case 'get_user_folders':
                $this->ajax_get_user_folders();
                break;
                
            case 'get_user_folder_tree':
                $this->ajax_get_user_folder_tree();
                break;
                
            case 'create_user_folder':
                $this->ajax_create_user_folder();
                break;
                
            case 'sync_user_folder':
                $this->ajax_sync_user_folder();
                break;
                
            case 'get_file_preview':
                $this->ajax_get_file_preview();
                break;
                
            case 'delete_file':
                $this->ajax_delete_file();
                break;
            
            default:
                wp_send_json_error('Azione non valida');
        }
    }
    
    /**
     * AJAX: Ottieni lista utenti
     */
    private function ajax_get_users_list() {
        $users = Naval_EGT_User_Manager::get_users(array(), 100, 0);
        wp_send_json_success(array('users' => $users));
    }
    
    /**
     * AJAX: Filtra log
     */
    private function ajax_filter_logs() {
        $filters = $_POST['filters'] ?? array();
        $limit = 50;
        $offset = 0;
        
        $logs = Naval_EGT_Activity_Logger::get_logs($filters, $limit, $offset);
        
        $html = '';
        foreach ($logs as $log) {
            $html .= '<tr>';
            $html .= '<td>' . mysql2date('d/m/Y H:i', $log['created_at']) . '</td>';
            $html .= '<td>' . esc_html($log['user_code']) . '</td>';
            $html .= '<td>' . esc_html($log['action']) . '</td>';
            $html .= '<td>' . esc_html($log['file_name'] ?? '-') . '</td>';
            $html .= '<td>' . esc_html($log['user_ip'] ?? '-') . '</td>';
            $html .= '</tr>';
        }
        
        wp_send_json_success(array('html' => $html, 'count' => count($logs)));
    }
    
    /**
     * AJAX: Pulisci log
     */
    private function ajax_clear_logs() {
        $result = Naval_EGT_Activity_Logger::clear_logs();
        if ($result) {
            wp_send_json_success('Log eliminati con successo');
        } else {
            wp_send_json_error('Errore nell\'eliminazione dei log');
        }
    }
    
    /**
     * AJAX: Sincronizza tutte le cartelle utenti
     */
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
    
    /**
     * AJAX: Test connessione Dropbox
     */
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
    
    /**
     * AJAX: Ottieni dati utente per modifica
     */
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
    
    /**
     * AJAX: Crea nuovo utente
     */
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
    
    /**
     * AJAX: Aggiorna utente esistente
     */
    private function ajax_update_user() {
        if (!isset($_POST['user_id'])) {
            wp_send_json_error('ID utente mancante');
            return;
        }
        
        $user_id = intval($_POST['user_id']);
        $user_data = $this->validate_user_data($_POST, true); // true = update mode
        
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
    
    /**
     * AJAX: Elimina utente
     */
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
    
    /**
     * AJAX: Cambia status utente
     */
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
    
    /**
     * AJAX: Azioni di gruppo su utenti
     */
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
     * AJAX: Ottieni cartelle utente
     */
    private function ajax_get_user_folders() {
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
        
        $dropbox = Naval_EGT_Dropbox::get_instance();
        
        if (!$dropbox->is_configured()) {
            wp_send_json_error('Dropbox non configurato');
            return;
        }
        
        // Ottieni struttura cartelle per l'utente
        $folder_result = $dropbox->get_user_folder_structure($user['user_code']);
        
        if (!$folder_result['success']) {
            wp_send_json_error($folder_result['message']);
            return;
        }
        
        $folders = $this->format_folders_for_select($folder_result['folders'], $user['user_code']);
        
        wp_send_json_success(array(
            'folders' => $folders,
            'user_code' => $user['user_code']
        ));
    }
    
    /**
     * AJAX: Ottieni struttura ad albero delle cartelle utente
     */
    private function ajax_get_user_folder_tree() {
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
        
        $dropbox = Naval_EGT_Dropbox::get_instance();
        
        if (!$dropbox->is_configured()) {
            wp_send_json_error('Dropbox non configurato');
            return;
        }
        
        // Ottieni struttura cartelle per l'utente
        $folder_result = $dropbox->get_user_folder_structure($user['user_code']);
        
        if (!$folder_result['success']) {
            wp_send_json_error($folder_result['message']);
            return;
        }
        
        $folders = $this->format_folders_for_tree($folder_result['folders'], $user['user_code']);
        
        wp_send_json_success(array(
            'folders' => $folders,
            'user_code' => $user['user_code']
        ));
    }
    
    /**
     * AJAX: Crea nuova cartella per utente
     */
    private function ajax_create_user_folder() {
        if (!isset($_POST['user_id']) || !isset($_POST['folder_name'])) {
            wp_send_json_error('Parametri mancanti');
            return;
        }
        
        $user_id = intval($_POST['user_id']);
        $folder_name = sanitize_text_field($_POST['folder_name']);
        $parent_folder = sanitize_text_field($_POST['parent_folder'] ?? '');
        
        if (empty($folder_name)) {
            wp_send_json_error('Nome cartella obbligatorio');
            return;
        }
        
        $user = Naval_EGT_User_Manager::get_user_by_id($user_id);
        
        if (!$user) {
            wp_send_json_error('Utente non trovato');
            return;
        }
        
        $dropbox = Naval_EGT_Dropbox::get_instance();
        
        if (!$dropbox->is_configured()) {
            wp_send_json_error('Dropbox non configurato');
            return;
        }
        
        // Costruisci il percorso completo della nuova cartella
        $base_path = '/' . $user['user_code'];
        if (!empty($parent_folder)) {
            $folder_path = rtrim($parent_folder, '/') . '/' . $folder_name;
        } else {
            $folder_path = $base_path . '/' . $folder_name;
        }
        
        $result = $dropbox->create_folder($folder_path);
        
        if ($result['success']) {
            wp_send_json_success('Cartella creata con successo');
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * AJAX: Sincronizza cartella utente
     */
    private function ajax_sync_user_folder() {
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
        
        $dropbox = Naval_EGT_Dropbox::get_instance();
        
        if (!$dropbox->is_configured()) {
            wp_send_json_error('Dropbox non configurato');
            return;
        }
        
        $result = $dropbox->sync_user_folder($user['user_code']);
        
        if ($result['success']) {
            $message = 'Sincronizzazione completata';
            if (isset($result['files_synced'])) {
                $message .= ': ' . $result['files_synced'] . ' file sincronizzati';
            }
            wp_send_json_success($message);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * AJAX: Ottieni anteprima file
     */
    private function ajax_get_file_preview() {
        if (!isset($_POST['file_id'])) {
            wp_send_json_error('ID file mancante');
            return;
        }
        
        $file_id = intval($_POST['file_id']);
        $file = Naval_EGT_File_Manager::get_file_by_id($file_id);
        
        if (!$file) {
            wp_send_json_error('File non trovato');
            return;
        }
        
        $preview_html = $this->generate_file_preview($file);
        
        wp_send_json_success(array(
            'file_name' => $file['file_name'],
            'preview_html' => $preview_html
        ));
    }
    
    /**
     * AJAX: Elimina file
     */
    private function ajax_delete_file() {
        if (!isset($_POST['file_id'])) {
            wp_send_json_error('ID file mancante');
            return;
        }
        
        $file_id = intval($_POST['file_id']);
        $result = Naval_EGT_File_Manager::delete_file($file_id);
        
        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * AJAX: Upload file admin per utente specifico - VERSIONE AGGIORNATA
     */
    private function ajax_admin_upload_files() {
        if (!isset($_POST['user_id']) || !isset($_FILES['files'])) {
            wp_send_json_error('Parametri mancanti');
            return;
        }
        
        $user_id = intval($_POST['user_id']);
        $folder_path = sanitize_text_field($_POST['folder_path'] ?? '');
        
        $user = Naval_EGT_User_Manager::get_user_by_id($user_id);
        
        if (!$user) {
            wp_send_json_error('Utente non trovato');
            return;
        }
        
        if (empty($folder_path)) {
            wp_send_json_error('Cartella di destinazione non specificata');
            return;
        }
        
        $dropbox = Naval_EGT_Dropbox::get_instance();
        
        if (!$dropbox->is_configured()) {
            wp_send_json_error('Dropbox non configurato');
            return;
        }
        
        $files = $_FILES['files'];
        $uploaded_files = array();
        $failed_files = array();
        
        // Gestisci sia array di file che file singolo
        if (is_array($files['name'])) {
            $file_count = count($files['name']);
        } else {
            $file_count = 1;
            // Normalizza in array
            $files = array(
                'name' => array($files['name']),
                'type' => array($files['type']),
                'tmp_name' => array($files['tmp_name']),
                'error' => array($files['error']),
                'size' => array($files['size'])
            );
        }
        
        for ($i = 0; $i < $file_count; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $file_data = array(
                    'name' => $files['name'][$i],
                    'type' => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error' => $files['error'][$i],
                    'size' => $files['size'][$i]
                );
                
                // Validazione file
                $validation = $this->validate_uploaded_file($file_data);
                if (is_wp_error($validation)) {
                    $failed_files[] = array(
                        'name' => $file_data['name'],
                        'error' => $validation->get_error_message()
                    );
                    continue;
                }
                
                // Upload su Dropbox
                $dropbox_path = rtrim($folder_path, '/') . '/' . $file_data['name'];
                $upload_result = $dropbox->upload_file($file_data['tmp_name'], $dropbox_path);
                
                if ($upload_result['success']) {
                    // Salva nel database
                    $db_result = Naval_EGT_File_Manager::save_file_record(array(
                        'user_id' => $user_id,
                        'file_name' => $file_data['name'],
                        'file_size' => $file_data['size'],
                        'file_type' => $file_data['type'],
                        'dropbox_path' => $dropbox_path,
                        'file_path' => $dropbox_path // Per compatibilità
                    ));
                    
                    if ($db_result['success']) {
                        $uploaded_files[] = $file_data['name'];
                        
                        // Log attività
                        Naval_EGT_Activity_Logger::log_activity(
                            $user['user_code'],
                            'ADMIN_UPLOAD',
                            $file_data['name'],
                            'File caricato dall\'amministratore'
                        );
                    } else {
                        $failed_files[] = array(
                            'name' => $file_data['name'],
                            'error' => 'Errore salvataggio database: ' . $db_result['message']
                        );
                    }
                } else {
                    $failed_files[] = array(
                        'name' => $file_data['name'],
                        'error' => 'Errore upload Dropbox: ' . $upload_result['message']
                    );
                }
            } else {
                $failed_files[] = array(
                    'name' => $files['name'][$i],
                    'error' => $this->get_upload_error_message($files['error'][$i])
                );
            }
        }
        
        // Prepara risposta
        $total_files = count($uploaded_files) + count($failed_files);
        $success_count = count($uploaded_files);
        $failed_count = count($failed_files);
        
        if ($success_count > 0) {
            $message = sprintf(
                '%d di %d file caricati con successo',
                $success_count,
                $total_files
            );
            
            $response_data = array(
                'message' => $message,
                'uploaded_files' => $uploaded_files,
                'success_count' => $success_count,
                'failed_count' => $failed_count
            );
            
            if ($failed_count > 0) {
                $response_data['failed_files'] = $failed_files;
            }
            
            wp_send_json_success($response_data);
        } else {
            wp_send_json_error(array(
                'message' => 'Nessun file è stato caricato',
                'failed_files' => $failed_files
            ));
        }
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
            
            case 'logs':
                $this->export_logs();
                break;
            
            default:
                wp_send_json_error('Tipo di export non valido');
        }
    }
    
    /**
     * Gestisce download file
     */
    public function handle_file_download() {
        check_ajax_referer('naval_egt_nonce', 'nonce');
        
        if (!isset($_GET['file_id'])) {
            wp_die('ID file mancante');
        }
        
        $file_id = intval($_GET['file_id']);
        $file = Naval_EGT_File_Manager::get_file_by_id($file_id);
        
        if (!$file) {
            wp_die('File non trovato');
        }
        
        $dropbox = Naval_EGT_Dropbox::get_instance();
        $download_result = $dropbox->download_file($file['dropbox_path']);
        
        if (!$download_result['success']) {
            wp_die('Errore download: ' . $download_result['message']);
        }
        
        // Imposta header per download
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file['file_name'] . '"');
        header('Content-Length: ' . $file['file_size']);
        
        // Output del contenuto file
        echo $download_result['content'];
        
        // Log download
        Naval_EGT_Activity_Logger::log_activity(
            $file['user_code'] ?? 'ADMIN',
            'DOWNLOAD',
            $file['file_name'],
            'File scaricato dall\'amministratore'
        );
        
        exit;
    }
    
    /**
     * Export utenti in CSV
     */
    private function export_users() {
        $users = Naval_EGT_User_Manager::get_users(array(), 10000, 0);
        
        $filename = 'naval_egt_users_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // Intestazioni CSV
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
    
    /**
     * Export log in CSV
     */
    private function export_logs() {
        $logs = Naval_EGT_Activity_Logger::get_logs(array(), 10000, 0);
        
        $filename = 'naval_egt_logs_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // Intestazioni CSV
        fputcsv($output, array('Data', 'Utente', 'Azione', 'File', 'IP', 'Dettagli'));
        
        foreach ($logs as $log) {
            fputcsv($output, array(
                $log['created_at'],
                $log['user_code'],
                $log['action'],
                $log['file_name'] ?? '',
                $log['user_ip'] ?? '',
                $log['details'] ?? ''
            ));
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Valida dati utente per creazione/aggiornamento
     */
    private function validate_user_data($data, $is_update = false) {
        $errors = array();
        
        // Campi obbligatori
        $required_fields = array('nome', 'cognome', 'email', 'username');
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                $errors[] = sprintf('Il campo %s è obbligatorio', ucfirst($field));
            }
        }
        
        // Password obbligatoria solo per nuovi utenti
        if (!$is_update && empty($data['password'])) {
            $errors[] = 'La password è obbligatoria per i nuovi utenti';
        }
        
        // Validazione email
        if (!empty($data['email']) && !is_email($data['email'])) {
            $errors[] = 'Email non valida';
        }
        
        // Validazione ragione sociale e partita IVA
        if (!empty($data['ragione_sociale']) && empty($data['partita_iva'])) {
            $errors[] = 'Partita IVA obbligatoria se specificata la Ragione Sociale';
        }
        
        // Validazione status
        if (!empty($data['status']) && !in_array($data['status'], ['ATTIVO', 'SOSPESO'])) {
            $errors[] = 'Status non valido';
        }
        
        if (!empty($errors)) {
            return new WP_Error('validation_failed', implode(', ', $errors));
        }
        
        // Sanitizza i dati
        return array(
            'nome' => sanitize_text_field($data['nome']),
            'cognome' => sanitize_text_field($data['cognome']),
            'email' => sanitize_email($data['email']),
            'telefono' => sanitize_text_field($data['telefono'] ?? ''),
            'username' => sanitize_user($data['username']),
            'password' => $data['password'], // Non sanitizzare la password
            'ragione_sociale' => sanitize_text_field($data['ragione_sociale'] ?? ''),
            'partita_iva' => sanitize_text_field($data['partita_iva'] ?? ''),
            'status' => sanitize_text_field($data['status'] ?? 'SOSPESO'),
            'dropbox_folder' => sanitize_text_field($data['dropbox_folder'] ?? '')
        );
    }
    
    /**
     * Valida file uploadato
     */
    private function validate_uploaded_file($file_data) {
        // Controlla errori di upload
        if ($file_data['error'] !== UPLOAD_ERR_OK) {
            return new WP_Error('upload_error', $this->get_upload_error_message($file_data['error']));
        }
        
        // Controlla dimensione file (max 10MB)
        $max_size = 10 * 1024 * 1024; // 10MB
        if ($file_data['size'] > $max_size) {
            return new WP_Error('file_too_large', 'File troppo grande. Dimensione massima: 10MB');
        }
        
        // Controlla estensione file
        $allowed_extensions = array('pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'dwg', 'dxf', 'zip', 'rar');
        $file_extension = strtolower(pathinfo($file_data['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_extension, $allowed_extensions)) {
            return new WP_Error('invalid_file_type', 'Tipo di file non supportato');
        }
        
        // Controlla che il file esista e sia leggibile
        if (!file_exists($file_data['tmp_name']) || !is_readable($file_data['tmp_name'])) {
            return new WP_Error('file_not_readable', 'File temporaneo non leggibile');
        }
        
        return true;
    }
    
    /**
     * Ottieni messaggio errore upload
     */
    private function get_upload_error_message($error_code) {
        switch ($error_code) {
            case UPLOAD_ERR_INI_SIZE:
                return 'File troppo grande (limite server)';
            case UPLOAD_ERR_FORM_SIZE:
                return 'File troppo grande (limite form)';
            case UPLOAD_ERR_PARTIAL:
                return 'Upload incompleto';
            case UPLOAD_ERR_NO_FILE:
                return 'Nessun file uploadato';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Cartella temporanea mancante';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Errore scrittura file';
            case UPLOAD_ERR_EXTENSION:
                return 'Upload bloccato da estensione';
            default:
                return 'Errore sconosciuto';
        }
    }
    
    /**
     * Formatta cartelle per select dropdown
     */
    private function format_folders_for_select($folders, $user_code) {
        $formatted = array();
        
        // Aggiungi cartella principale
        $formatted[] = array(
            'path' => '/' . $user_code,
            'name' => $user_code,
            'display_name' => $user_code . ' (Cartella Principale)',
            'is_root' => true,
            'level' => 0
        );
        
        // Aggiungi sottocartelle
        if (!empty($folders)) {
            foreach ($folders as $folder) {
                $level = substr_count($folder['path_display'], '/') - 1;
                $formatted[] = array(
                    'path' => $folder['path_lower'],
                    'name' => $folder['name'],
                    'display_name' => str_repeat('└─ ', max(0, $level - 1)) . $folder['name'],
                    'is_root' => false,
                    'level' => $level
                );
            }
        }
        
        return $formatted;
    }
    
    /**
     * Formatta cartelle per tree view
     */
    private function format_folders_for_tree($folders, $user_code) {
        $formatted = array();
        
        // Aggiungi cartella principale
        $formatted[] = array(
            'path' => '/' . $user_code,
            'name' => $user_code,
            'display_name' => $user_code,
            'is_root' => true,
            'level' => 0
        );
        
        // Aggiungi sottocartelle
        if (!empty($folders)) {
            foreach ($folders as $folder) {
                $level = substr_count($folder['path_display'], '/') - 1;
                $formatted[] = array(
                    'path' => $folder['path_lower'],
                    'name' => $folder['name'],
                    'display_name' => $folder['name'],
                    'is_root' => false,
                    'level' => $level
                );
            }
        }
        
        // Ordina per livello e nome
        usort($formatted, function($a, $b) {
            if ($a['level'] == $b['level']) {
                return strcmp($a['name'], $b['name']);
            }
            return $a['level'] - $b['level'];
        });
        
        return $formatted;
    }
    
    /**
     * Genera anteprima HTML per file
     */
    private function generate_file_preview($file) {
        $extension = strtolower(pathinfo($file['file_name'], PATHINFO_EXTENSION));
        $file_size = size_format($file['file_size']);
        $created_date = date('d/m/Y H:i', strtotime($file['created_at']));
        
        $preview_html = '<div class="file-preview-container">';
        
        // Informazioni file
        $preview_html .= '<div class="file-info-header">';
        $preview_html .= '<h4>' . esc_html($file['file_name']) . '</h4>';
        $preview_html .= '<div class="file-meta-info">';
        $preview_html .= '<span><strong>Dimensione:</strong> ' . $file_size . '</span> | ';
        $preview_html .= '<span><strong>Data:</strong> ' . $created_date . '</span> | ';
        $preview_html .= '<span><strong>Tipo:</strong> ' . strtoupper($extension) . '</span>';
        $preview_html .= '</div>';
        $preview_html .= '</div>';
        
        // Anteprima specifica per tipo
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
            case 'png':
            case 'gif':
                $preview_html .= $this->generate_image_preview($file);
                break;
                
            case 'pdf':
                $preview_html .= $this->generate_pdf_preview($file);
                break;
                
            case 'txt':
            case 'csv':
                $preview_html .= $this->generate_text_preview($file);
                break;
                
            default:
                $preview_html .= $this->generate_generic_preview($file);
        }
        
        $preview_html .= '</div>';
        
        return $preview_html;
    }
    
    /**
     * Genera anteprima per immagini
     */
    private function generate_image_preview($file) {
        $dropbox = Naval_EGT_Dropbox::get_instance();
        
        // Prova a ottenere un link temporaneo per l'anteprima
        $temp_link_result = $dropbox->get_temporary_link($file['dropbox_path']);
        
        if ($temp_link_result['success']) {
            return '<div class="image-preview">
                        <img src="' . esc_url($temp_link_result['link']) . '" 
                             alt="' . esc_attr($file['file_name']) . '" 
                             style="max-width: 100%; max-height: 400px; border-radius: 4px;" />
                    </div>';
        } else {
            return '<div class="preview-placeholder">
                        <div class="placeholder-icon">🖼️</div>
                        <p>Anteprima immagine non disponibile</p>
                        <small>' . esc_html($temp_link_result['message']) . '</small>
                    </div>';
        }
    }
    
    /**
     * Genera anteprima per PDF
     */
    private function generate_pdf_preview($file) {
        $dropbox = Naval_EGT_Dropbox::get_instance();
        
        // Prova a ottenere un link temporaneo
        $temp_link_result = $dropbox->get_temporary_link($file['dropbox_path']);
        
        if ($temp_link_result['success']) {
            return '<div class="pdf-preview">
                        <iframe src="' . esc_url($temp_link_result['link']) . '" 
                                width="100%" 
                                height="500px" 
                                style="border: 1px solid #ddd; border-radius: 4px;">
                        </iframe>
                        <p><small>Se il PDF non si carica, <a href="' . esc_url($temp_link_result['link']) . '" target="_blank">clicca qui per aprirlo</a></small></p>
                    </div>';
        } else {
            return '<div class="preview-placeholder">
                        <div class="placeholder-icon">📄</div>
                        <p>Anteprima PDF non disponibile</p>
                        <small>' . esc_html($temp_link_result['message']) . '</small>
                    </div>';
        }
    }
    
    /**
     * Genera anteprima per file di testo
     */
    private function generate_text_preview($file) {
        $dropbox = Naval_EGT_Dropbox::get_instance();
        
        // Scarica il contenuto del file (limitato ai primi 1000 caratteri)
        $content_result = $dropbox->download_file_content($file['dropbox_path'], 1000);
        
        if ($content_result['success']) {
            $content = esc_html($content_result['content']);
            $is_truncated = strlen($content_result['content']) >= 1000;
            
            return '<div class="text-preview">
                        <pre style="background: #f8f9fa; padding: 15px; border-radius: 4px; max-height: 300px; overflow: auto; white-space: pre-wrap;">' . $content . '</pre>
                        ' . ($is_truncated ? '<p><small>Contenuto troncato (primi 1000 caratteri)</small></p>' : '') . '
                    </div>';
        } else {
            return '<div class="preview-placeholder">
                        <div class="placeholder-icon">📝</div>
                        <p>Anteprima testo non disponibile</p>
                        <small>' . esc_html($content_result['message']) . '</small>
                    </div>';
        }
    }
    
    /**
     * Genera anteprima generica
     */
    private function generate_generic_preview($file) {
        $extension = strtolower(pathinfo($file['file_name'], PATHINFO_EXTENSION));
        
        $icons = array(
            'doc' => '📝', 'docx' => '📝',
            'xls' => '📊', 'xlsx' => '📊',
            'zip' => '📦', 'rar' => '📦',
            'dwg' => '📐', 'dxf' => '📐'
        );
        
        $icon = $icons[$extension] ?? '📎';
        
        return '<div class="generic-preview">
                    <div class="preview-placeholder">
                        <div class="placeholder-icon" style="font-size: 64px;">' . $icon . '</div>
                        <p><strong>' . esc_html($file['file_name']) . '</strong></p>
                        <p>Anteprima non disponibile per questo tipo di file</p>
                        <div style="margin-top: 20px;">
                            <a href="' . admin_url('admin-ajax.php?action=naval_egt_download_file&file_id=' . $file['id'] . '&nonce=' . wp_create_nonce('naval_egt_nonce')) . '" 
                               class="button button-primary" target="_blank">
                                <span class="dashicons dashicons-download"></span> Scarica File
                            </a>
                        </div>
                    </div>
                </div>';
    }
    
    /**
     * Renderizza pagina admin principale
     */
    public function render_admin_page() {
        $current_tab = $_GET['tab'] ?? 'overview';
        
        ?>
        <div class="wrap">
            <h1>Naval EGT - Area Riservata Clienti</h1>
            
            <nav class="nav-tab-wrapper">
                <a href="?page=naval-egt&tab=overview" class="nav-tab <?php echo $current_tab === 'overview' ? 'nav-tab-active' : ''; ?>">📊 Panoramica</a>
                <a href="?page=naval-egt&tab=users" class="nav-tab <?php echo $current_tab === 'users' ? 'nav-tab-active' : ''; ?>">👥 Utenti</a>
                <a href="?page=naval-egt&tab=files" class="nav-tab <?php echo $current_tab === 'files' ? 'nav-tab-active' : ''; ?>">📁 File</a>
                <a href="?page=naval-egt&tab=logs" class="nav-tab <?php echo $current_tab === 'logs' ? 'nav-tab-active' : ''; ?>">📋 Log</a>
                <a href="?page=naval-egt&tab=dropbox" class="nav-tab <?php echo $current_tab === 'dropbox' ? 'nav-tab-active' : ''; ?>">☁️ Dropbox</a>
                <a href="?page=naval-egt&tab=dropbox-debug" class="nav-tab <?php echo $current_tab === 'dropbox-debug' ? 'nav-tab-active' : ''; ?>" style="color: #d63638;">🔍 Debug</a>
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
                    case 'files':
                        $this->render_files_tab();
                        break;
                    case 'logs':
                        $this->render_logs_tab();
                        break;
                    case 'dropbox':
                        $this->render_dropbox_settings();
                        break;
                    case 'dropbox-debug':
                        $this->render_dropbox_debug();
                        break;
                    default:
                        $this->render_overview_tab();
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Renderizza tab panoramica
     */
    private function render_overview_tab() {
        $stats = Naval_EGT_Database::get_user_stats();
        $dropbox = Naval_EGT_Dropbox::get_instance();
        $dropbox_status = $dropbox->get_connection_status();
        
        ?>
        <div class="naval-egt-dashboard">
            <h2>Benvenuto in Naval EGT</h2>
            
            <!-- Statistiche Rapide -->
            <div class="dashboard-stats">
                <div class="stat-card">
                    <h3>👥 Utenti Totali</h3>
                    <div class="stat-number"><?php echo $stats['total_users'] ?? 0; ?></div>
                </div>
                
                <div class="stat-card">
                    <h3>✅ Utenti Attivi</h3>
                    <div class="stat-number"><?php echo $stats['active_users'] ?? 0; ?></div>
                </div>
                
                <div class="stat-card">
                    <h3>📁 File Totali</h3>
                    <div class="stat-number"><?php echo $stats['total_files'] ?? 0; ?></div>
                </div>
                
                <div class="stat-card">
                    <h3>💾 Spazio Usato</h3>
                    <div class="stat-number"><?php echo size_format($stats['total_size'] ?? 0); ?></div>
                </div>
            </div>
            
            <!-- Stato Dropbox -->
            <div class="card">
                <h3>☁️ Stato Dropbox</h3>
                <?php if ($dropbox_status['connected']): ?>
                    <p style="color: green;">✅ <strong>Connesso</strong></p>
                    <p><?php echo esc_html($dropbox_status['message']); ?></p>
                    <?php if (isset($dropbox_status['account_email'])): ?>
                        <p><strong>Account:</strong> <?php echo esc_html($dropbox_status['account_email']); ?></p>
                    <?php endif; ?>
                <?php else: ?>
                    <p style="color: red;">❌ <strong>Non Connesso</strong></p>
                    <p><?php echo esc_html($dropbox_status['message']); ?></p>
                    <div style="margin-top: 15px;">
                        <a href="?page=naval-egt&tab=dropbox" class="button button-primary">Configura Dropbox</a>
                        <a href="?page=naval-egt&tab=dropbox-debug" class="button button-secondary" style="margin-left: 10px;">🔍 Debug Problemi</a>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Azioni Rapide -->
            <div class="card">
                <h3>🚀 Azioni Rapide</h3>
                <div class="quick-actions">
                    <a href="?page=naval-egt&tab=users" class="button">👥 Gestisci Utenti</a>
                    <a href="?page=naval-egt&tab=files" class="button">📁 Gestisci File</a>
                    <a href="?page=naval-egt&tab=logs" class="button">📋 Visualizza Log</a>
                    <?php if ($dropbox_status['connected']): ?>
                        <button class="button" onclick="syncAllFolders()">🔄 Sincronizza Tutte le Cartelle</button>
                        <button class="button" onclick="testDropboxQuick()">🧪 Test Dropbox Veloce</button>
                    <?php else: ?>
                        <button class="button" onclick="diagnoseDropbox()" style="background: #dc3232; border-color: #dc3232; color: white;">🔍 Diagnosi Dropbox</button>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Attività Recenti -->
            <div class="card">
                <h3>📈 Attività Recenti</h3>
                <?php
                $recent_logs = Naval_EGT_Activity_Logger::get_logs(array(), 10, 0);
                if ($recent_logs):
                ?>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Utente</th>
                            <th>Azione</th>
                            <th>Dettagli</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_logs as $log): ?>
                        <tr>
                            <td><?php echo mysql2date('d/m/Y H:i', $log['created_at']); ?></td>
                            <td><?php echo esc_html($log['user_code']); ?></td>
                            <td><?php echo esc_html($log['action']); ?></td>
                            <td><?php echo esc_html($log['file_name'] ?? '-'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p><em>Nessuna attività recente</em></p>
                <?php endif; ?>
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
            max-width: 100% !important; 
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
                    alert('✅ Test Dropbox OK!\n\nConnesso come: ' + (response.data.account_email || 'Account Dropbox'));
                } else {
                    alert('❌ Test Dropbox fallito!\n\nErrore: ' + response.data);
                }
            });
        }

        function diagnoseDropbox() {
            if (confirm('Vuoi eseguire una diagnosi completa di Dropbox?\n\nQuesta operazione analizzerà la configurazione e identificherà eventuali problemi.')) {
                window.location.href = '<?php echo admin_url('admin.php?page=naval-egt&tab=dropbox-debug&auto_diagnose=1'); ?>';
            }
        }
        </script>
        <?php
    }
    
    /**
     * Renderizza tab utenti
     */
    private function render_users_tab() {
        // Percorso corretto: admin/views/tab-users.php
        $tab_file = NAVAL_EGT_PLUGIN_DIR . 'admin/views/tab-users.php';
        
        if (file_exists($tab_file)) {
            include_once $tab_file;
        } else {
            // Fallback: mostra errore e percorso
            ?>
            <div class="card">
                <h2>👥 Gestione Utenti</h2>
                <div style="background: #fff3cd; padding: 20px; border: 1px solid #ffeaa7; border-radius: 4px; margin: 20px 0;">
                    <h3 style="color: #856404;">⚠️ File tab-users.php non trovato!</h3>
                    <p><strong>Percorso atteso:</strong> <code><?php echo esc_html($tab_file); ?></code></p>
                    <p><strong>Percorso plugin:</strong> <code><?php echo esc_html(NAVAL_EGT_PLUGIN_DIR); ?></code></p>
                    <p><strong>Cosa fare:</strong></p>
                    <ol>
                        <li>Crea il file <code>admin/views/tab-users.php</code> nella cartella del plugin</li>
                        <li>Copia il contenuto fornito nel file</li>
                        <li>Verifica i permessi delle cartelle</li>
                    </ol>
                </div>
                
                <!-- Lista base degli utenti come fallback -->
                <?php
                $users = Naval_EGT_User_Manager::get_users(array(), 20, 0);
                if (!empty($users)):
                ?>
                <h3>Utenti nel Database (Lista Base)</h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Codice</th>
                            <th>Nome</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Creato</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><strong><?php echo esc_html($user['user_code']); ?></strong></td>
                            <td><?php echo esc_html($user['nome'] . ' ' . $user['cognome']); ?></td>
                            <td><?php echo esc_html($user['email']); ?></td>
                            <td>
                                <span style="color: <?php echo $user['status'] === 'ATTIVO' ? 'green' : 'orange'; ?>;">
                                    <?php echo esc_html($user['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p><em>Nessun utente trovato nel database.</em></p>
                <?php endif; ?>
            </div>
            <?php
        }
    }
    
    /**
     * Renderizza tab file
     */
    private function render_files_tab() {
        // Percorso corretto: admin/views/tab-files.php
        $tab_file = NAVAL_EGT_PLUGIN_DIR . 'admin/views/tab-files.php';
        
        if (file_exists($tab_file)) {
            include_once $tab_file;
        } else {
            // Fallback semplificato
            ?>
            <div class="card">
                <h2>📁 Gestione File</h2>
                <div style="background: #fff3cd; padding: 20px; border: 1px solid #ffeaa7; border-radius: 4px; margin: 20px 0;">
                    <h3 style="color: #856404;">⚠️ File tab-files.php non trovato!</h3>
                    <p><strong>Percorso atteso:</strong> <code><?php echo esc_html($tab_file); ?></code></p>
                    <p><strong>Cosa fare:</strong></p>
                    <ol>
                        <li>Crea il file <code>admin/views/tab-files.php</code> nella cartella del plugin</li>
                        <li>Copia il contenuto fornito nel file</li>
                    </ol>
                </div>
            </div>
            <?php
        }
    }
    
    /**
     * Renderizza tab log
     */
    private function render_logs_tab() {
        ?>
        <div class="card">
            <h2>📋 Log Attività</h2>
            <p>Visualizza e gestisci i log delle attività del sistema.</p>
            
            <!-- Filtri Log -->
            <div class="logs-filters">
                <select id="log-user-filter">
                    <option value="">Tutti gli utenti</option>
                </select>
                <select id="log-action-filter">
                    <option value="">Tutte le azioni</option>
                    <option value="LOGIN">Login</option>
                    <option value="LOGOUT">Logout</option>
                    <option value="UPLOAD">Upload</option>
                    <option value="DOWNLOAD">Download</option>
                    <option value="DELETE">Delete</option>
                </select>
                <input type="date" id="log-date-from" />
                <input type="date" id="log-date-to" />
                <button class="button" onclick="filterLogs()">🔍 Filtra</button>
                <button class="button button-secondary" onclick="clearLogs()" style="color: red;">🗑️ Pulisci Log</button>
            </div>
            
            <!-- Tabella Log -->
            <div id="logs-table-container">
                <p><em>Caricamento log...</em></p>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function() {
            loadLogs();
            loadUsersForLogFilter();
        });
        
        function loadLogs() {
            jQuery.post(ajaxurl, {
                action: 'naval_egt_ajax',
                naval_action: 'filter_logs',
                nonce: '<?php echo wp_create_nonce('naval_egt_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    jQuery('#logs-table-container').html('<table class="widefat"><thead><tr><th>Data</th><th>Utente</th><th>Azione</th><th>File</th><th>IP</th></tr></thead><tbody>' + response.data.html + '</tbody></table>');
                }
            });
        }
        
        function loadUsersForLogFilter() {
            jQuery.post(ajaxurl, {
                action: 'naval_egt_ajax',
                naval_action: 'get_users_list',
                nonce: '<?php echo wp_create_nonce('naval_egt_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    var options = '<option value="">Tutti gli utenti</option>';
                    response.data.users.forEach(function(user) {
                        options += '<option value="' + user.user_code + '">' + user.user_code + ' - ' + user.nome + ' ' + user.cognome + '</option>';
                    });
                    jQuery('#log-user-filter').html(options);
                }
            });
        }
        
        function filterLogs() {
            var filters = {
                user_code: jQuery('#log-user-filter').val(),
                action: jQuery('#log-action-filter').val(),
                date_from: jQuery('#log-date-from').val(),
                date_to: jQuery('#log-date-to').val()
            };
            
            jQuery.post(ajaxurl, {
                action: 'naval_egt_ajax',
                naval_action: 'filter_logs',
                filters: filters,
                nonce: '<?php echo wp_create_nonce('naval_egt_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    jQuery('#logs-table-container').html('<table class="widefat"><thead><tr><th>Data</th><th>Utente</th><th>Azione</th><th>File</th><th>IP</th></tr></thead><tbody>' + response.data.html + '</tbody></table>');
                }
            });
        }
        
        function clearLogs() {
            if (!confirm('Sei sicuro di voler eliminare tutti i log? Questa azione non può essere annullata.')) return;
            
            jQuery.post(ajaxurl, {
                action: 'naval_egt_ajax',
                naval_action: 'clear_logs',
                nonce: '<?php echo wp_create_nonce('naval_egt_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    alert('Log eliminati con successo');
                    loadLogs();
                } else {
                    alert('Errore nell\'eliminazione dei log');
                }
            });
        }
        </script>
        <?php
    }
    
    /**
     * Renderizza pagina impostazioni Dropbox
     */
    private function render_dropbox_settings() {
        $dropbox = Naval_EGT_Dropbox::get_instance();
        
        // Gestisci il salvataggio delle credenziali (se necessario per compatibilità)
        if (isset($_POST['save_dropbox_credentials'])) {
            check_admin_referer('naval_egt_dropbox_settings');
            $this->add_admin_notice('Le credenziali sono già preconfigurate nel plugin.', 'info');
        }
        
        // Gestisci test connessione
        if (isset($_POST['test_dropbox_connection'])) {
            check_admin_referer('naval_egt_dropbox_test');
            
            $test_result = $dropbox->test_connection();
            
            if ($test_result['success']) {
                $this->add_admin_notice($test_result['message'], 'success');
            } else {
                $this->add_admin_notice('Test connessione fallito: ' . $test_result['message'], 'error');
            }
        }
        
        // Gestisci reset configurazione
        if (isset($_POST['reset_dropbox_config'])) {
            check_admin_referer('naval_egt_dropbox_reset');
            
            $result = $dropbox->disconnect();
            $this->add_admin_notice($result['message'], 'success');
        }
        
        $is_configured = $dropbox->is_configured();
        $connection_status = $dropbox->get_connection_status();

        // Recupera tutti i dati debug se disponibili
        $diagnosis_data = get_transient('naval_egt_dropbox_diagnosis');
        $auth_url_display = get_transient('naval_egt_dropbox_auth_url');
        $token_analysis = get_transient('naval_egt_token_analysis');
        $multiple_tests = get_transient('naval_egt_multiple_tests');
        $debug_400 = get_transient('naval_egt_debug_400');
        $callback_debug = get_transient('naval_egt_callback_debug');
        
        ?>
        <div class="wrap">
            <h2>☁️ Configurazione Dropbox</h2>
            
            <!-- Strumenti di Debug Integrati -->
            <div class="card" style="border-left: 4px solid #dc3232;">
                <h3>🔧 Strumenti di Debug e Risoluzione Problemi Avanzati</h3>
                <p><strong>Se Dropbox non funziona correttamente, usa questi strumenti per diagnosticare e risolvere i problemi:</strong></p>
                
                <div style="margin: 15px 0;">
                    <?php
                    $debug_base_url = admin_url('admin.php?page=naval-egt&tab=dropbox');
                    $nonce = wp_create_nonce('naval_egt_dropbox_debug');
                    ?>
                    
                    <!-- Riga 1: Diagnosi Principale -->
                    <div style="margin-bottom: 10px;">
                        <a href="<?php echo esc_url($debug_base_url . '&dropbox_debug_action=test_diagnosis&_wpnonce=' . $nonce); ?>" 
                           class="button button-primary" style="margin-right: 10px;">
                            🔍 Diagnosi Completa
                        </a>
                        
                        <a href="<?php echo esc_url($debug_base_url . '&dropbox_debug_action=test_app_credentials&_wpnonce=' . $nonce); ?>" 
                           class="button" style="margin-right: 10px;">
                            🔑 Test Credenziali App
                        </a>
                        
                        <a href="<?php echo esc_url($debug_base_url . '&dropbox_debug_action=test_connection&_wpnonce=' . $nonce); ?>" 
                           class="button" style="margin-right: 10px;">
                            📡 Test Connessione
                        </a>
                    </div>
                    
                    <!-- Riga 2: Analisi Token -->
                    <div style="margin-bottom: 10px;">
                        <a href="<?php echo esc_url($debug_base_url . '&dropbox_debug_action=analyze_token&_wpnonce=' . $nonce); ?>" 
                           class="button" style="margin-right: 10px;">
                            🔑 Analizza Token
                        </a>
                        
                        <a href="<?php echo esc_url($debug_base_url . '&dropbox_debug_action=test_multiple_methods&_wpnonce=' . $nonce); ?>" 
                           class="button" style="margin-right: 10px;">
                            🧪 Test Metodi Multipli
                        </a>
                        
                        <a href="<?php echo esc_url($debug_base_url . '&dropbox_debug_action=export_debug_info&_wpnonce=' . $nonce); ?>" 
                           class="button" style="margin-right: 10px;">
                            📊 Esporta Info Debug
                        </a>
                    </div>
                    
                    <!-- Riga 3: Azioni Avanzate -->
                    <div style="margin-bottom: 10px;">
                        <a href="<?php echo esc_url($debug_base_url . '&dropbox_debug_action=reload_credentials&_wpnonce=' . $nonce); ?>" 
                           class="button" style="margin-right: 10px;">
                            🔄 Ricarica Credenziali
                        </a>
                        
                        <a href="<?php echo esc_url($debug_base_url . '&dropbox_debug_action=clear_debug_logs&_wpnonce=' . $nonce); ?>" 
                           class="button" style="margin-right: 10px;">
                            🗑️ Pulisci Log Debug
                        </a>
                        
                        <a href="<?php echo esc_url($debug_base_url . '&dropbox_debug_action=regenerate_token&_wpnonce=' . $nonce); ?>" 
                           class="button" style="background: #dc3232; border-color: #dc3232; color: white; margin-right: 10px;"
                           onclick="return confirm('⚠️ ATTENZIONE: Questo cancellerà il token corrente e dovrai riautorizzare Dropbox.\n\nUsa questo solo se il token attuale è corrotto.\n\nProcedere?');">
                            🔄 Rigenera Token
                        </a>
                    </div>
                </div>
                
                <p><small><strong>💡 Suggerimento:</strong> Se Dropbox non funziona, prova prima "Diagnosi Completa" per capire il problema, poi "Rigenera Token" se necessario.</small></p>
            </div>
            
            <!-- Stato configurazione -->
            <div class="card">
                <h3>Stato Configurazione</h3>
                <table class="form-table">
                    <tr>
                        <th>Stato Dropbox</th>
                        <td>
                            <?php if ($is_configured && $connection_status['connected']): ?>
                                <span style="color: green; font-weight: bold;">✅ CONFIGURATO E CONNESSO</span>
                                <br><small><?php echo esc_html($connection_status['message']); ?></small>
                            <?php elseif ($is_configured): ?>
                                <span style="color: orange; font-weight: bold;">⚠️ CONFIGURATO MA NON CONNESSO</span>
                                <br><small><?php echo esc_html($connection_status['message']); ?></small>
                            <?php else: ?>
                                <span style="color: red; font-weight: bold;">❌ NON CONFIGURATO</span>
                                <br><small>È necessario autorizzare l'applicazione su Dropbox</small>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Credenziali App</th>
                        <td>
                            ✅ <strong>Preconfigurate</strong>
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
            <!-- Autorizzazione -->
            <div class="card">
                <h3>🔐 Autorizzazione Dropbox</h3>
                <p>Per utilizzare le funzionalità di Dropbox, è necessario autorizzare l'applicazione.</p>
                
                <?php
                $auth_url = $dropbox->get_authorization_url();
                if ($auth_url):
                ?>
                <p>
                    <a href="<?php echo esc_url($auth_url); ?>" class="button button-primary button-large">
                        🔐 Autorizza su Dropbox
                    </a>
                </p>
                <p class="description">
                    Verrai reindirizzato su Dropbox per autorizzare l'applicazione. 
                    Dopo l'autorizzazione, tornerai automaticamente qui.
                </p>
                <?php else: ?>
                <p style="color: red;">❌ Errore nella generazione dell'URL di autorizzazione.</p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php if ($is_configured): ?>
            <!-- Dropbox configurato -->
            <div class="card">
                <h3>⚙️ Gestione Configurazione</h3>
                
                <!-- Test connessione -->
                <form method="post" style="display: inline-block; margin-right: 10px;">
                    <?php wp_nonce_field('naval_egt_dropbox_test'); ?>
                    <input type="submit" name="test_dropbox_connection" class="button" value="🔍 Testa Connessione" />
                </form>
                
                <!-- Sincronizza cartelle -->
                <button class="button" onclick="syncAllUserFolders()" style="margin-right: 10px;">🔄 Sincronizza Tutte le Cartelle</button>
                
                <!-- Reset configurazione -->
                <form method="post" style="display: inline-block;" 
                      onsubmit="return confirm('Sei sicuro di voler disconnettere Dropbox? Dovrai riautorizzare l\'applicazione.');">
                    <?php wp_nonce_field('naval_egt_dropbox_reset'); ?>
                    <input type="submit" name="reset_dropbox_config" class="button button-secondary" value="🔌 Disconnetti Dropbox" />
                </form>
            </div>
            <?php endif; ?>
            
            <!-- Guida configurazione -->
            <div class="card">
                <h3>📋 Informazioni Configurazione</h3>
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
            if (!confirm('Vuoi sincronizzare tutte le cartelle utenti con Dropbox? Questa operazione potrebbe richiedere alcuni minuti.')) return;
            
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
    
    /**
     * Renderizza pagina debug Dropbox
     */
    private function render_dropbox_debug() {
        $dropbox = Naval_EGT_Dropbox::get_instance();
        
        // Auto-diagnosi se richiesta
        if (isset($_GET['auto_diagnose']) && $_GET['auto_diagnose'] === '1') {
            $diagnosis = $dropbox->full_system_diagnosis();
            $this->set_dropbox_diagnosis_data($diagnosis);
            $this->add_admin_notice('Diagnosi automatica eseguita. Controlla i risultati qui sotto.', 'info');
        }
        
        // Ottieni informazioni debug
        $debug_info = $dropbox->debug_configuration();
        $debug_logs = $dropbox->get_debug_logs();
        $is_configured = $dropbox->is_configured();

        // Recupera tutti i dati diagnosi se disponibili
        $diagnosis_data = get_transient('naval_egt_dropbox_diagnosis');
        $auth_url_display = get_transient('naval_egt_dropbox_auth_url');
        $token_analysis = get_transient('naval_egt_token_analysis');
        $multiple_tests = get_transient('naval_egt_multiple_tests');
        $debug_export = get_transient('naval_egt_debug_export');
        
        ?>
        <div class="wrap">
            <h1>🔍 Debug Dropbox Avanzato - Naval EGT</h1>
            
            <!-- Stato configurazione -->
            <div class="card">
                <h2>Stato Configurazione Dettagliato</h2>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th>Elemento</th>
                            <th>Proprietà Classe</th>
                            <th>Database</th>
                            <th>Stato</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>App Key</strong></td>
                            <td><?php echo $debug_info['property_values']['app_key'] ? '✅ SET' : '❌ EMPTY'; ?></td>
                            <td><?php echo $debug_info['database_values']['app_key'] ? '✅ SET' : '❌ EMPTY'; ?></td>
                            <td><?php echo $debug_info['property_values']['app_key'] && $debug_info['database_values']['app_key'] ? '✅ OK' : '⚠️ PROBLEM'; ?></td>
                        </tr>
                        <tr>
                            <td><strong>App Secret</strong></td>
                            <td><?php echo $debug_info['property_values']['app_secret'] ? '✅ SET' : '❌ EMPTY'; ?></td>
                            <td><?php echo $debug_info['database_values']['app_secret'] ? '✅ SET' : '❌ EMPTY'; ?></td>
                            <td><?php echo $debug_info['property_values']['app_secret'] && $debug_info['database_values']['app_secret'] ? '✅ OK' : '⚠️ PROBLEM'; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Access Token</strong></td>
                            <td><?php echo $debug_info['property_values']['access_token'] ? '✅ SET' : '❌ EMPTY'; ?></td>
                            <td><?php echo $debug_info['database_values']['access_token'] ? '✅ SET' : '❌ EMPTY'; ?></td>
                            <td><?php echo $debug_info['database_values']['access_token'] ? '✅ OK' : '❌ MISSING'; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Refresh Token</strong></td>
                            <td><?php echo $debug_info['property_values']['refresh_token'] ? '✅ SET' : '❌ EMPTY'; ?></td>
                            <td><?php echo $debug_info['database_values']['refresh_token'] ? '✅ SET' : '❌ EMPTY'; ?></td>
                            <td><?php echo $debug_info['database_values']['refresh_token'] ? '✅ OK' : '⚠️ OPTIONAL'; ?></td>
                        </tr>
                    </tbody>
                </table>
                
                <h3>Riassunto Configurazione</h3>
                <p style="font-size: 18px; font-weight: bold;">
                    Stato Finale: 
                    <?php if ($is_configured): ?>
                        <span style="color: green;">✅ CONFIGURATO</span>
                    <?php else: ?>
                        <span style="color: red;">❌ NON CONFIGURATO</span>
                    <?php endif; ?>
                </p>
            </div>
            
            <!-- Log debug -->
            <div class="card">
                <h3>📋 Log Debug</h3>
                <?php if (!empty($debug_logs)): ?>
                <div style="background: #f9f9f9; border: 1px solid #ddd; padding: 10px; max-height: 400px; overflow-y: auto; font-family: monospace; font-size: 12px;">
                    <?php foreach ($debug_logs as $log): ?>
                        <div style="margin-bottom: 5px;">
                            <span style="color: #666;">[<?php echo esc_html($log['timestamp']); ?>]</span>
                            <span style="color: <?php echo $log['level'] === 'ERROR' ? 'red' : ($log['level'] === 'WARNING' ? 'orange' : 'black'); ?>;">
                                <?php echo esc_html($log['message']); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p><em>Nessun log di debug disponibile</em></p>
                <?php endif; ?>
            </div>
            
            <!-- Informazioni sistema -->
            <div class="card">
                <h3>ℹ️ Informazioni Sistema</h3>
                <table class="widefat">
                    <tr>
                        <th>URL Plugin</th>
                        <td><code><?php echo plugin_dir_url(__FILE__); ?></code></td>
                    </tr>
                    <tr>
                        <th>URL Callback Dropbox</th>
                        <td><code><?php echo admin_url('admin.php?page=naval-egt&tab=dropbox&action=callback'); ?></code></td>
                    </tr>
                    <tr>
                        <th>Versione PHP</th>
                        <td><?php echo PHP_VERSION; ?></td>
                    </tr>
                    <tr>
                        <th>Versione WordPress</th>
                        <td><?php echo get_bloginfo('version'); ?></td>
                    </tr>
                    <tr>
                        <th>cURL Abilitato</th>
                        <td><?php echo function_exists('curl_init') ? '✅ Sì' : '❌ No'; ?></td>
                    </tr>
                    <tr>
                        <th>OpenSSL Abilitato</th>
                        <td><?php echo extension_loaded('openssl') ? '✅ Sì' : '❌ No'; ?></td>
                    </tr>
                </table>
            </div>
        </div>
        <?php
    }
}

// Inizializza la classe admin
Naval_EGT_Admin::get_instance();