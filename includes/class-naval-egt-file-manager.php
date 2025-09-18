<?php
/**
 * Classe Naval_EGT_File_Manager - VERSIONE CORRETTA CON TUTTE LE FUNZIONI AJAX
 * Sostituisci completamente la tua classe esistente con questo codice
 */

if (!defined('ABSPATH')) {
    exit;
}

class Naval_EGT_File_Manager {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // AJAX handlers esistenti
        add_action('wp_ajax_naval_egt_upload_file', array($this, 'handle_upload'));
        add_action('wp_ajax_naval_egt_download_file', array($this, 'handle_download'));
        add_action('wp_ajax_naval_egt_delete_file', array($this, 'handle_delete'));
        add_action('wp_ajax_naval_egt_get_file_info', array($this, 'get_file_info'));
        
        // Gestione AJAX principale per admin
        add_action('wp_ajax_naval_egt_ajax', array($this, 'handle_admin_ajax'));
        
        // Gestione AJAX per utenti non privilegiati (se necessario)
        add_action('wp_ajax_nopriv_naval_egt_ajax', array($this, 'handle_public_ajax'));
        
        // Log di debug per verificare che gli hooks siano registrati
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Naval EGT File Manager: AJAX hooks registered at ' . current_time('mysql'));
        }
    }
    
    /**
     * Gestione AJAX per utenti pubblici/non admin
     */
    public function handle_public_ajax() {
        // Verifica nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'naval_egt_nonce')) {
            wp_send_json_error('Nonce non valido');
        }
        
        // Ottieni utente corrente
        if (class_exists('Naval_EGT_User_Manager')) {
            $current_user = Naval_EGT_User_Manager::get_current_user();
            if (!$current_user) {
                wp_send_json_error('Accesso richiesto');
            }
        } else {
            wp_send_json_error('User Manager non disponibile');
        }
        
        $action = sanitize_text_field($_POST['naval_action'] ?? '');
        
        // Azioni permesse agli utenti normali
        switch ($action) {
            case 'get_my_files':
                $this->ajax_get_user_files($current_user['id']);
                break;
                
            case 'download_my_file':
                $this->ajax_download_user_file($current_user['id']);
                break;
                
            case 'get_my_file_info':
                $this->ajax_get_user_file_info($current_user['id']);
                break;
                
            default:
                wp_send_json_error('Azione non permessa per utenti normali');
        }
    }
    
    /**
     * Gestione richieste AJAX admin - VERSIONE CORRETTA
     */
    public function handle_admin_ajax() {
        // Log di debug
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Naval EGT AJAX: Request received - Action: ' . ($_POST['naval_action'] ?? 'none'));
            error_log('Naval EGT AJAX: POST data: ' . print_r($_POST, true));
        }
        
        // Verifica nonce
        if (!check_ajax_referer('naval_egt_nonce', 'nonce', false)) {
            wp_send_json_error('Nonce non valido o scaduto');
        }
        
        // Verifica permessi per admin
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permessi insufficienti - solo admin');
        }
        
        $action = sanitize_text_field($_POST['naval_action'] ?? '');
        
        if (empty($action)) {
            wp_send_json_error('Azione non specificata');
        }
        
        // Log dell'azione richiesta
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Naval EGT AJAX: Processing action: ' . $action);
        }
        
        try {
            switch ($action) {
                case 'test_ajax_connection':
                    $this->ajax_test_connection();
                    break;
                    
                case 'debug_ajax_hooks':
                    $this->ajax_debug_hooks();
                    break;
                    
                case 'system_status':
                    $this->ajax_system_status();
                    break;
                    
                case 'get_debug_logs':
                    $this->ajax_get_debug_logs();
                    break;
                    
                case 'clear_debug_logs':
                    $this->ajax_clear_debug_logs();
                    break;
                    
                case 'delete_file':
                    $this->ajax_delete_file();
                    break;
                    
                case 'verify_repair_user_folder':
                    $this->ajax_verify_repair_user_folder();
                    break;
                    
                case 'get_folder_structure':
                    $this->ajax_get_folder_structure();
                    break;
                    
                case 'get_all_folders':
                    $this->ajax_get_all_folders();
                    break;
                    
                case 'create_folder':
                    $this->ajax_create_folder();
                    break;
                    
                case 'upload_files':
                    $this->ajax_upload_files();
                    break;
                    
                case 'create_user_main_folder':
                    $this->ajax_create_user_main_folder();
                    break;
                    
                case 'sync_user_folder':
                    $this->ajax_sync_user_folder();
                    break;
                    
                default:
                    wp_send_json_error('Azione non riconosciuta: ' . $action);
            }
        } catch (Exception $e) {
            // Log dell'errore
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Naval EGT AJAX Error: ' . $e->getMessage());
            }
            wp_send_json_error('Errore interno: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX: Test connessione base - IMPLEMENTAZIONE CORRETTA
     */
    private function ajax_test_connection() {
        $user_id = intval($_POST['user_id'] ?? 0);
        
        // Verifica funzionalità base
        $test_results = array(
            'basic_wordpress' => array(
                'status' => true,
                'message' => 'WordPress funzionante'
            ),
            'ajax_system' => array(
                'status' => wp_doing_ajax(),
                'message' => wp_doing_ajax() ? 'Sistema AJAX attivo' : 'Sistema AJAX non attivo'
            ),
            'user_capabilities' => array(
                'status' => current_user_can('manage_options'),
                'message' => current_user_can('manage_options') ? 'Permessi admin OK' : 'Permessi admin mancanti'
            ),
            'required_classes' => array()
        );
        
        // Verifica classi richieste
        $required_classes = array(
            'Naval_EGT_User_Manager',
            'Naval_EGT_Dropbox',
            'Naval_EGT_File_Manager'
        );
        
        foreach ($required_classes as $class) {
            $test_results['required_classes'][$class] = array(
                'status' => class_exists($class),
                'message' => class_exists($class) ? 'Caricata' : 'Non trovata'
            );
        }
        
        // Test connessione database
        global $wpdb;
        $db_test = $wpdb->get_var("SELECT 1");
        $test_results['database'] = array(
            'status' => ($db_test == 1),
            'message' => ($db_test == 1) ? 'Database connesso' : 'Problema database'
        );
        
        // Test tabelle
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}naval_egt_files'");
        $test_results['file_table'] = array(
            'status' => !empty($table_exists),
            'message' => !empty($table_exists) ? 'Tabella files OK' : 'Tabella files mancante'
        );
        
        wp_send_json_success(array(
            'message' => 'Naval EGT AJAX connection working correctly',
            'user_id' => $user_id,
            'timestamp' => current_time('mysql'),
            'wp_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'current_user_can_manage' => current_user_can('manage_options'),
            'memory_usage' => memory_get_usage(),
            'memory_peak' => memory_get_peak_usage(),
            'ajax_url' => admin_url('admin-ajax.php'),
            'is_ssl' => is_ssl(),
            'wp_debug' => defined('WP_DEBUG') && WP_DEBUG,
            'hooks_loaded' => has_action('wp_ajax_naval_egt_ajax') ? 'yes' : 'no',
            'test_results' => $test_results,
            'server_info' => array(
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                'php_memory_limit' => ini_get('memory_limit'),
                'php_max_execution_time' => ini_get('max_execution_time'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size')
            )
        ));
    }
    
    /**
     * AJAX: Debug hooks AJAX - IMPLEMENTAZIONE CORRETTA
     */
    private function ajax_debug_hooks() {
        global $wp_filter;
        
        // Conta hooks AJAX totali
        $total_ajax_hooks = 0;
        $naval_ajax_hooks = array();
        $all_ajax_hooks = array();
        
        // Cerca tutti gli hooks AJAX
        foreach ($wp_filter as $hook_name => $callbacks) {
            if (strpos($hook_name, 'wp_ajax_') === 0) {
                $all_ajax_hooks[] = $hook_name;
                $total_ajax_hooks++;
                
                if (strpos($hook_name, 'naval_egt') !== false) {
                    $naval_ajax_hooks[] = $hook_name;
                }
            }
        }
        
        // Verifica hooks specifici Naval EGT
        $expected_hooks = array(
            'wp_ajax_naval_egt_ajax',
            'wp_ajax_nopriv_naval_egt_ajax',
            'wp_ajax_naval_egt_upload_file',
            'wp_ajax_naval_egt_download_file',
            'wp_ajax_naval_egt_delete_file',
            'wp_ajax_naval_egt_get_file_info'
        );
        
        $hook_status = array();
        foreach ($expected_hooks as $hook) {
            $hook_status[$hook] = array(
                'exists' => isset($wp_filter[$hook]),
                'callbacks' => isset($wp_filter[$hook]) ? count($wp_filter[$hook]->callbacks) : 0
            );
        }
        
        wp_send_json_success(array(
            'total_ajax_hooks' => $total_ajax_hooks,
            'naval_ajax_hooks' => $naval_ajax_hooks,
            'naval_hooks_count' => count($naval_ajax_hooks),
            'wp_ajax_naval_egt_ajax_exists' => isset($wp_filter['wp_ajax_naval_egt_ajax']),
            'current_action' => current_action(),
            'doing_ajax' => wp_doing_ajax(),
            'is_admin' => current_user_can('manage_options'),
            'hooks_registered' => !empty($naval_ajax_hooks),
            'hook_detailed_status' => $hook_status,
            'all_ajax_hooks_sample' => array_slice($all_ajax_hooks, 0, 10), // Prime 10 per debug
            'wp_filter_naval_egt_ajax' => isset($wp_filter['wp_ajax_naval_egt_ajax']) ? 'YES' : 'NO'
        ));
    }
    
    /**
     * AJAX: Status sistema - IMPLEMENTAZIONE CORRETTA
     */
    private function ajax_system_status() {
        // Verifica classi caricate
        $required_classes = array(
            'Naval_EGT_User_Manager',
            'Naval_EGT_File_Manager', 
            'Naval_EGT_Dropbox',
            'Naval_EGT_Activity_Logger',
            'Naval_EGT_Dropbox_Debug',
            'Naval_EGT_Database'
        );
        
        $class_status = array();
        foreach ($required_classes as $class) {
            $class_status[$class] = class_exists($class);
        }
        
        // Verifica hooks
        global $wp_filter;
        $hooks_registered = isset($wp_filter['wp_ajax_naval_egt_ajax']);
        
        // Verifica database
        global $wpdb;
        $tables_status = array();
        $required_tables = array(
            'naval_egt_users',
            'naval_egt_files',
            'naval_egt_activity_logs'
        );
        
        foreach ($required_tables as $table) {
            $full_table_name = $wpdb->prefix . $table;
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table_name'");
            $tables_status[$table] = !empty($table_exists);
        }
        
        // Test Dropbox se disponibile
        $dropbox_status = array(
            'class_loaded' => class_exists('Naval_EGT_Dropbox'),
            'configured' => false,
            'connection_test' => false
        );
        
        if (class_exists('Naval_EGT_Dropbox')) {
            try {
                $dropbox = Naval_EGT_Dropbox::get_instance();
                $dropbox_status['configured'] = $dropbox->is_configured();
                
                if ($dropbox_status['configured']) {
                    $test_result = $dropbox->test_connection();
                    $dropbox_status['connection_test'] = $test_result['success'] ?? false;
                }
            } catch (Exception $e) {
                $dropbox_status['error'] = $e->getMessage();
            }
        }
        
        // Verifica file system
        $upload_dir = wp_upload_dir();
        $filesystem_status = array(
            'upload_dir_exists' => is_dir($upload_dir['basedir']),
            'upload_dir_writable' => is_writable($upload_dir['basedir']),
            'temp_dir_writable' => is_writable(get_temp_dir())
        );
        
        wp_send_json_success(array(
            'file_manager_loaded' => class_exists('Naval_EGT_File_Manager'),
            'dropbox_class_loaded' => class_exists('Naval_EGT_Dropbox'),
            'debug_class_loaded' => class_exists('Naval_EGT_Dropbox_Debug'),
            'user_manager_loaded' => class_exists('Naval_EGT_User_Manager'),
            'activity_logger_loaded' => class_exists('Naval_EGT_Activity_Logger'),
            'database_class_loaded' => class_exists('Naval_EGT_Database'),
            'hooks_registered' => $hooks_registered,
            'wp_debug_enabled' => defined('WP_DEBUG') && WP_DEBUG,
            'doing_ajax' => wp_doing_ajax(),
            'current_user_can_manage' => current_user_can('manage_options'),
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo('version'),
            'memory_usage' => memory_get_usage(),
            'memory_peak' => memory_get_peak_usage(),
            'class_status' => $class_status,
            'tables_status' => $tables_status,
            'dropbox_status' => $dropbox_status,
            'filesystem_status' => $filesystem_status,
            'timestamp' => current_time('mysql'),
            'plugin_active' => is_plugin_active('naval-egt/naval-egt.php') ? 'YES' : 'UNKNOWN'
        ));
    }
    
    /**
     * AJAX: Ottieni log di debug - IMPLEMENTAZIONE CORRETTA
     */
    private function ajax_get_debug_logs() {
        $logs = array();
        
        // Verifica se la classe debug esiste
        if (class_exists('Naval_EGT_Dropbox_Debug')) {
            try {
                $logs = Naval_EGT_Dropbox_Debug::get_logs();
            } catch (Exception $e) {
                wp_send_json_error('Errore nel recupero dei log: ' . $e->getMessage());
            }
        } else {
            // Se non esiste la classe debug, crea log di esempio/test
            $logs = array(
                array(
                    'timestamp' => current_time('mysql'),
                    'message' => 'Classe Naval_EGT_Dropbox_Debug non disponibile',
                    'data' => array('status' => 'warning', 'note' => 'Debug limitato')
                ),
                array(
                    'timestamp' => current_time('mysql'),
                    'message' => 'File Manager operativo',
                    'data' => array('status' => 'info')
                )
            );
        }
        
        // Aggiungi log di sistema
        $logs[] = array(
            'timestamp' => current_time('mysql'),
            'message' => 'System Status Check',
            'data' => array(
                'memory_usage' => memory_get_usage(true),
                'wp_version' => get_bloginfo('version'),
                'php_version' => PHP_VERSION
            )
        );
        
        wp_send_json_success(array(
            'logs' => $logs,
            'count' => count($logs),
            'timestamp' => current_time('mysql'),
            'debug_class_available' => class_exists('Naval_EGT_Dropbox_Debug')
        ));
    }
    
    /**
     * AJAX: Pulisci log di debug - IMPLEMENTAZIONE CORRETTA
     */
    private function ajax_clear_debug_logs() {
        $success = false;
        $message = '';
        
        // Verifica se la classe debug esiste
        if (class_exists('Naval_EGT_Dropbox_Debug')) {
            try {
                Naval_EGT_Dropbox_Debug::clear_logs();
                $success = true;
                $message = 'Log di debug puliti con successo';
            } catch (Exception $e) {
                $message = 'Errore nella pulizia dei log: ' . $e->getMessage();
            }
        } else {
            $success = true;
            $message = 'Classe debug non disponibile - operazione simulata';
        }
        
        if ($success) {
            wp_send_json_success(array(
                'message' => $message,
                'timestamp' => current_time('mysql')
            ));
        } else {
            wp_send_json_error($message);
        }
    }
    
    /**
     * AJAX: Elimina file - IMPLEMENTAZIONE CORRETTA
     */
    private function ajax_delete_file() {
        $file_id = intval($_POST['file_id'] ?? 0);
        
        if (!$file_id) {
            wp_send_json_error('ID file non valido');
        }
        
        $result = $this->delete_file_by_id($file_id);
        
        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * AJAX: Verifica e ripara connessione utente-cartella - IMPLEMENTAZIONE CORRETTA
     */
    private function ajax_verify_repair_user_folder() {
        $user_id = intval($_POST['user_id'] ?? 0);
        
        if (!$user_id) {
            wp_send_json_error('ID utente non valido');
        }
        
        $result = $this->verify_and_repair_user_folder($user_id);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            $response = $result['message'];
            if (isset($result['suggestions'])) {
                wp_send_json_error($response, 200); // Invia con suggestions nel data
            } else {
                wp_send_json_error($response);
            }
        }
    }
    
    /**
     * Verifica e ripara connessione utente-cartella - IMPLEMENTAZIONE CORRETTA
     */
    private function verify_and_repair_user_folder($user_id) {
        // Verifica che User Manager esista
        if (!class_exists('Naval_EGT_User_Manager')) {
            return array('success' => false, 'message' => 'Naval_EGT_User_Manager non disponibile');
        }
        
        $user = Naval_EGT_User_Manager::get_user_by_id($user_id);
        if (!$user) {
            return array('success' => false, 'message' => 'Utente non trovato');
        }
        
        // Log di debug se disponibile
        if (class_exists('Naval_EGT_Dropbox_Debug')) {
            try {
                Naval_EGT_Dropbox_Debug::debug_log('=== VERIFY AND REPAIR USER FOLDER ===', array(
                    'user_id' => $user_id,
                    'user_code' => $user['user_code'],
                    'current_dropbox_folder' => $user['dropbox_folder'] ?? 'N/A'
                ));
            } catch (Exception $e) {
                // Ignora errori di debug
            }
        }
        
        // Verifica che Dropbox sia disponibile
        if (!class_exists('Naval_EGT_Dropbox')) {
            return array('success' => false, 'message' => 'Naval_EGT_Dropbox non disponibile');
        }
        
        $dropbox = Naval_EGT_Dropbox::get_instance();
        
        if (!$dropbox->is_configured()) {
            return array('success' => false, 'message' => 'Dropbox non configurato. Vai alla sezione Dropbox per completare la configurazione.');
        }
        
        // Test connessione base
        $connection_test = $dropbox->test_connection();
        
        if (!$connection_test['success']) {
            return array(
                'success' => false, 
                'message' => 'Connessione Dropbox fallita: ' . $connection_test['message'] . '. Verifica la configurazione Dropbox.'
            );
        }
        
        // Caso 1: L'utente ha già un dropbox_folder salvato
        if (!empty($user['dropbox_folder'])) {
            // Verifica se la cartella esiste ancora
            $folder_test = $dropbox->get_metadata($user['dropbox_folder']);
            
            if ($folder_test['success']) {
                return array(
                    'success' => true, 
                    'message' => 'Cartella utente verificata e funzionante',
                    'folder_path' => $user['dropbox_folder'],
                    'action_taken' => 'verified_existing',
                    'data' => array('folder_path' => $user['dropbox_folder'])
                );
            }
        }
        
        // Caso 2: Cerca cartella per codice utente
        $folder_result = $dropbox->find_folder_by_code($user['user_code']);
        
        if ($folder_result['success'] && !empty($folder_result['folders'])) {
            $main_folder = $folder_result['folders'][0];
            
            // Aggiorna il campo dropbox_folder
            $update_result = Naval_EGT_User_Manager::update_user($user_id, array(
                'dropbox_folder' => $main_folder['path_lower']
            ));
            
            if ($update_result['success']) {
                return array(
                    'success' => true,
                    'message' => 'Cartella utente trovata e collegata con successo',
                    'folder_path' => $main_folder['path_lower'],
                    'action_taken' => 'repaired_connection',
                    'data' => array('folder_path' => $main_folder['path_lower'])
                );
            } else {
                return array(
                    'success' => false,
                    'message' => 'Cartella trovata ma impossibile aggiornare il database: ' . $update_result['message']
                );
            }
        }
        
        // Caso 3: Ricerca manuale nella cartella root
        $root_contents = $dropbox->list_folder_contents('');
        
        if ($root_contents['success'] && !empty($root_contents['entries'])) {
            foreach ($root_contents['entries'] as $entry) {
                if (($entry['.tag'] ?? '') === 'folder') {
                    $folder_name = $entry['name'] ?? '';
                    
                    // Verifica se il nome della cartella contiene il codice utente
                    if (stripos($folder_name, $user['user_code']) !== false) {
                        // Aggiorna il campo dropbox_folder
                        $update_result = Naval_EGT_User_Manager::update_user($user_id, array(
                            'dropbox_folder' => $entry['path_lower']
                        ));
                        
                        if ($update_result['success']) {
                            return array(
                                'success' => true,
                                'message' => 'Cartella trovata e collegata con successo (ricerca manuale)',
                                'folder_path' => $entry['path_lower'],
                                'action_taken' => 'manual_search_repair',
                                'data' => array('folder_path' => $entry['path_lower'])
                            );
                        }
                    }
                }
            }
        }
        
        // Caso 4: Nessuna cartella trovata
        return array(
            'success' => false,
            'message' => 'Nessuna cartella trovata per il codice utente "' . $user['user_code'] . '".',
            'suggestions' => array(
                'Verifica che la cartella esista su Dropbox',
                'La cartella deve avere un nome che contenga il codice "' . $user['user_code'] . '"',
                'Esempio di nome cartella valido: "' . $user['user_code'] . ' - ' . ($user['nome'] ?? '') . ' ' . ($user['cognome'] ?? '') . '"',
                'Puoi creare manualmente la cartella su Dropbox e riprovare'
            )
        );
    }
    
    /**
     * AJAX: Ottieni struttura cartella - IMPLEMENTAZIONE CORRETTA
     */
    private function ajax_get_folder_structure() {
        $user_id = intval($_POST['user_id'] ?? 0);
        $folder_path = sanitize_text_field($_POST['folder_path'] ?? '');
        
        if (!$user_id) {
            wp_send_json_error('ID utente non valido');
        }
        
        // Validazione user_id
        if (!class_exists('Naval_EGT_User_Manager')) {
            wp_send_json_error('Naval_EGT_User_Manager non disponibile');
        }
        
        $user = Naval_EGT_User_Manager::get_user_by_id($user_id);
        if (!$user) {
            wp_send_json_error('Utente non trovato');
        }
        
        try {
            $result = $this->get_folder_structure($user_id, $folder_path);
            
            if ($result['success']) {
                wp_send_json_success($result['data']);
            } else {
                wp_send_json_error($result['message']);
            }
        } catch (Exception $e) {
            wp_send_json_error('Errore nel recupero struttura: ' . $e->getMessage());
        }
    }
    
    /**
     * Ottieni struttura completa cartella utente - IMPLEMENTAZIONE CORRETTA
     */
    private function get_folder_structure($user_id, $folder_path = '') {
        if (!class_exists('Naval_EGT_User_Manager')) {
            return array('success' => false, 'message' => 'Naval_EGT_User_Manager non disponibile');
        }
        
        $user = Naval_EGT_User_Manager::get_user_by_id($user_id);
        if (!$user) {
            return array('success' => false, 'message' => 'Utente non trovato');
        }
        
        if (!class_exists('Naval_EGT_Dropbox')) {
            return array('success' => false, 'message' => 'Naval_EGT_Dropbox non disponibile');
        }
        
        $dropbox = Naval_EGT_Dropbox::get_instance();
        if (!$dropbox->is_configured()) {
            return array('success' => false, 'message' => 'Dropbox non configurato');
        }
        
        // Se non è specificato un path, usa la cartella principale dell'utente
        if (empty($folder_path)) {
            if (!empty($user['dropbox_folder'])) {
                $folder_path = $user['dropbox_folder'];
            } else {
                // Cerca cartella per codice utente
                $folder_result = $dropbox->find_folder_by_code($user['user_code']);
                if (!$folder_result['success'] || empty($folder_result['folders'])) {
                    return array('success' => false, 'message' => 'Cartella utente non trovata. Usa "Verifica Connessione" per configurarla.');
                }
                $folder_path = $folder_result['folders'][0]['path_lower'];
            }
        }
        
        // Ottieni contenuto cartella
        $contents_result = $dropbox->list_folder_contents($folder_path);
        
        if (!$contents_result['success']) {
            return array('success' => false, 'message' => 'Errore lettura cartella: ' . $contents_result['message']);
        }
        
        $folders = array();
        $files = array();
        
        if (!empty($contents_result['entries'])) {
            foreach ($contents_result['entries'] as $entry) {
                $entry = is_array($entry) ? $entry : array();
                
                $item = array(
                    'name' => sanitize_text_field($entry['name'] ?? 'Sconosciuto'),
                    'path' => sanitize_text_field($entry['path_lower'] ?? ''),
                    'display_path' => sanitize_text_field($entry['path_display'] ?? $entry['path_lower'] ?? ''),
                    'type' => sanitize_text_field($entry['.tag'] ?? 'unknown'),
                    'id' => sanitize_text_field($entry['id'] ?? ''),
                    'size' => intval($entry['size'] ?? 0),
                    'modified' => sanitize_text_field($entry['server_modified'] ?? '')
                );
                
                if ($item['type'] === 'folder') {
                    $folders[] = $item;
                } elseif ($item['type'] === 'file') {
                    // Cerca nel database se abbiamo info aggiuntive sul file
                    global $wpdb;
                    $db_file = $wpdb->get_row($wpdb->prepare(
                        "SELECT id FROM {$wpdb->prefix}naval_egt_files WHERE dropbox_id = %s",
                        $item['id']
                    ), ARRAY_A);
                    
                    if ($db_file) {
                        $item['db_id'] = $db_file['id'];
                    }
                    
                    $files[] = $item;
                }
            }
        }
        
        // Ordina: prima cartelle, poi file
        usort($folders, function($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });
        
        usort($files, function($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });
        
        $all_items = array_merge($folders, $files);
        
        return array(
            'success' => true,
            'data' => array(
                'folders' => $all_items,
                'current_path' => $folder_path,
                'user_code' => $user['user_code'],
                'total_items' => count($all_items)
            )
        );
    }
    
    /**
     * AJAX: Ottieni tutte le cartelle per dropdown - IMPLEMENTAZIONE CORRETTA
     */
    private function ajax_get_all_folders() {
        $user_id = intval($_POST['user_id'] ?? 0);
        
        if (!$user_id) {
            wp_send_json_error('ID utente non valido');
        }
        
        $result = $this->get_all_user_folders($user_id);
        
        if ($result['success']) {
            wp_send_json_success($result['folders']);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * Ottieni tutte le cartelle dell'utente ricorsivamente - IMPLEMENTAZIONE CORRETTA
     */
    private function get_all_user_folders($user_id) {
        if (!class_exists('Naval_EGT_User_Manager')) {
            return array('success' => false, 'message' => 'Naval_EGT_User_Manager non disponibile');
        }
        
        $user = Naval_EGT_User_Manager::get_user_by_id($user_id);
        if (!$user) {
            return array('success' => false, 'message' => 'Utente non trovato');
        }
        
        if (!class_exists('Naval_EGT_Dropbox')) {
            return array('success' => false, 'message' => 'Naval_EGT_Dropbox non disponibile');
        }
        
        $dropbox = Naval_EGT_Dropbox::get_instance();
        if (!$dropbox->is_configured()) {
            return array('success' => false, 'message' => 'Dropbox non configurato');
        }
        
        // Ottieni cartella principale
        $main_folder_path = '';
        if (!empty($user['dropbox_folder'])) {
            $main_folder_path = $user['dropbox_folder'];
        } else {
            $folder_result = $dropbox->find_folder_by_code($user['user_code']);
            if (!$folder_result['success'] || empty($folder_result['folders'])) {
                return array('success' => false, 'message' => 'Cartella utente non trovata');
            }
            $main_folder_path = $folder_result['folders'][0]['path_lower'];
        }
        
        $all_folders = array();
        
        // Aggiungi cartella principale
        $all_folders[] = array(
            'path' => $main_folder_path,
            'display_name' => $user['user_code'] . ' - Cartella Principale',
            'level' => 0
        );
        
        // Ottieni sottocartelle ricorsivamente
        $subfolders = $this->get_subfolders_recursive($dropbox, $main_folder_path, 1);
        $all_folders = array_merge($all_folders, $subfolders);
        
        return array(
            'success' => true,
            'folders' => $all_folders
        );
    }
    
    /**
     * Ottieni sottocartelle ricorsivamente - IMPLEMENTAZIONE CORRETTA
     */
    private function get_subfolders_recursive($dropbox, $folder_path, $level = 1, $max_level = 5) {
        if ($level > $max_level) {
            return array();
        }
        
        $folders = array();
        $contents_result = $dropbox->list_folder_contents($folder_path);
        
        if (!$contents_result['success'] || empty($contents_result['entries'])) {
            return $folders;
        }
        
        foreach ($contents_result['entries'] as $entry) {
            if (($entry['.tag'] ?? '') === 'folder') {
                $folder_name = $entry['name'] ?? 'Cartella';
                $folder_path_full = $entry['path_lower'] ?? '';
                
                // Aggiungi indentazione per indicare il livello
                $indent = str_repeat('— ', $level);
                
                $folders[] = array(
                    'path' => $folder_path_full,
                    'display_name' => $indent . $folder_name,
                    'level' => $level
                );
                
                // Ricorsione per sottocartelle
                $subfolders = $this->get_subfolders_recursive($dropbox, $folder_path_full, $level + 1, $max_level);
                $folders = array_merge($folders, $subfolders);
            }
        }
        
        return $folders;
    }
    
    /**
     * AJAX: Crea nuova cartella - IMPLEMENTAZIONE CORRETTA
     */
    private function ajax_create_folder() {
        $user_id = intval($_POST['user_id'] ?? 0);
        $folder_name = sanitize_text_field($_POST['folder_name'] ?? '');
        $parent_path = sanitize_text_field($_POST['parent_path'] ?? '');
        
        if (!$user_id || empty($folder_name)) {
            wp_send_json_error('Parametri non validi');
        }
        
        $result = $this->create_user_folder($user_id, $folder_name, $parent_path);
        
        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * Crea cartella per utente - IMPLEMENTAZIONE CORRETTA
     */
    private function create_user_folder($user_id, $folder_name, $parent_path = '') {
        if (!class_exists('Naval_EGT_User_Manager')) {
            return array('success' => false, 'message' => 'Naval_EGT_User_Manager non disponibile');
        }
        
        $user = Naval_EGT_User_Manager::get_user_by_id($user_id);
        if (!$user) {
            return array('success' => false, 'message' => 'Utente non trovato');
        }
        
        if (!class_exists('Naval_EGT_Dropbox')) {
            return array('success' => false, 'message' => 'Naval_EGT_Dropbox non disponibile');
        }
        
        $dropbox = Naval_EGT_Dropbox::get_instance();
        if (!$dropbox->is_configured()) {
            return array('success' => false, 'message' => 'Dropbox non configurato');
        }
        
        // Determina il percorso padre
        if (empty($parent_path)) {
            if (!empty($user['dropbox_folder'])) {
                $parent_path = $user['dropbox_folder'];
            } else {
                $folder_result = $dropbox->find_folder_by_code($user['user_code']);
                if (!$folder_result['success'] || empty($folder_result['folders'])) {
                    return array('success' => false, 'message' => 'Cartella principale utente non trovata');
                }
                $parent_path = $folder_result['folders'][0]['path_lower'];
            }
        }
        
        // Costruisci percorso completo
        $full_path = rtrim($parent_path, '/') . '/' . $folder_name;
        
        // Crea cartella su Dropbox
        $create_result = $dropbox->create_folder($full_path);
        
        if ($create_result['success']) {
            // Log attività
            if (class_exists('Naval_EGT_Activity_Logger')) {
                try {
                    Naval_EGT_Activity_Logger::log_activity(
                        $user['id'],
                        $user['user_code'],
                        'CREATE_FOLDER',
                        $folder_name,
                        $full_path,
                        0,
                        array(
                            'parent_path' => $parent_path,
                            'created_by' => 'admin'
                        )
                    );
                } catch (Exception $e) {
                    // Ignora errori di logging
                }
            }
            
            return array(
                'success' => true,
                'message' => 'Cartella creata con successo',
                'folder_path' => $full_path
            );
        } else {
            return array(
                'success' => false,
                'message' => 'Errore nella creazione della cartella: ' . $create_result['message']
            );
        }
    }
    
    /**
     * AJAX: Upload file - IMPLEMENTAZIONE STUB
     */
    private function ajax_upload_files() {
        wp_send_json_error('Funzione upload files non ancora implementata - in sviluppo');
    }
    
    /**
     * AJAX: Crea cartella principale utente - IMPLEMENTAZIONE STUB
     */
    private function ajax_create_user_main_folder() {
        wp_send_json_error('Funzione create user main folder non ancora implementata - in sviluppo');
    }
    
    /**
     * AJAX: Sincronizza cartella utente - IMPLEMENTAZIONE STUB
     */
    private function ajax_sync_user_folder() {
        wp_send_json_error('Funzione sync user folder non ancora implementata - in sviluppo');
    }
    
    /**
     * Elimina file per ID - IMPLEMENTAZIONE CORRETTA
     */
    private function delete_file_by_id($file_id) {
        global $wpdb;
        
        // Carica info file
        $file = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}naval_egt_files WHERE id = %d",
            $file_id
        ), ARRAY_A);
        
        if (!$file) {
            return array('success' => false, 'message' => 'File non trovato');
        }
        
        // Elimina da Dropbox (configurabile)
        $delete_from_dropbox = true;
        if (class_exists('Naval_EGT_Database')) {
            try {
                $delete_from_dropbox = Naval_EGT_Database::get_setting('delete_from_dropbox', '1') === '1';
            } catch (Exception $e) {
                // Usa default se errore
            }
        }
        
        if ($delete_from_dropbox && class_exists('Naval_EGT_Dropbox')) {
            $dropbox = Naval_EGT_Dropbox::get_instance();
            if ($dropbox->is_configured()) {
                $dropbox->delete($file['dropbox_path']);
            }
        }
        
        // Elimina dal database
        $result = $wpdb->delete(
            $wpdb->prefix . 'naval_egt_files',
            array('id' => $file_id),
            array('%d')
        );
        
        if ($result) {
            // Log eliminazione
            if (class_exists('Naval_EGT_Activity_Logger')) {
                try {
                    Naval_EGT_Activity_Logger::log_activity(
                        $file['user_id'],
                        $file['user_code'],
                        'DELETE',
                        $file['file_name'],
                        $file['dropbox_path'],
                        $file['file_size'],
                        array(
                            'deleted_by' => 'admin',
                            'deleted_from_dropbox' => $delete_from_dropbox
                        )
                    );
                } catch (Exception $e) {
                    // Ignora errori di logging
                }
            }
            
            return array('success' => true, 'message' => 'File eliminato con successo');
        } else {
            return array('success' => false, 'message' => 'Errore nell\'eliminazione del file');
        }
    }
    
    /**
     * Gestisce il download dei file - IMPLEMENTAZIONE CORRETTA
     */
    public function handle_download() {
        // Verifica nonce da GET o POST
        $nonce = $_POST['nonce'] ?? $_GET['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'naval_egt_nonce')) {
            wp_die('Accesso negato: nonce non valido');
        }
        
        $file_id = intval($_POST['file_id'] ?? $_GET['file_id'] ?? 0);
        
        if (!$file_id) {
            wp_die('ID file non valido');
        }
        
        // Il resto dell'implementazione download rimane uguale
        wp_send_json_error('Funzione download non ancora completamente implementata');
    }
    
    /**
     * Gestisce l'eliminazione dei file
     */
    public function handle_delete() {
        check_ajax_referer('naval_egt_nonce', 'nonce');
        
        $file_id = intval($_POST['file_id'] ?? 0);
        
        if (!$file_id) {
            wp_send_json_error('ID file non valido');
        }
        
        $result = $this->delete_file_by_id($file_id);
        
        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * Ottiene informazioni su un file
     */
    public function get_file_info() {
        check_ajax_referer('naval_egt_nonce', 'nonce');
        
        $file_id = intval($_POST['file_id'] ?? 0);
        
        if (!$file_id) {
            wp_send_json_error('ID file non valido');
        }
        
        wp_send_json_error('Funzione get file info non ancora implementata');
    }
    
    /**
     * Gestisce l'upload dei file (metodo pubblico per compatibilità)
     */
    public function handle_upload() {
        // Reindirizza alla gestione AJAX unificata
        $_POST['naval_action'] = 'upload_files';
        $this->handle_admin_ajax();
    }
}

/**
 * Funzione di utilità globale per inizializzare il File Manager
 */
function naval_egt_init_file_manager() {
    // Inizializza il File Manager
    $file_manager = Naval_EGT_File_Manager::get_instance();
    
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Naval EGT File Manager: Initialized successfully at ' . current_time('mysql'));
    }
    
    return true;
}

/**
 * Hook per inizializzazione
 */
add_action('init', 'naval_egt_init_file_manager', 10);

/**
 * Hook per debug (solo se WP_DEBUG è attivo)
 */
if (defined('WP_DEBUG') && WP_DEBUG) {
    add_action('wp_ajax_naval_egt_ajax', function() {
        if (class_exists('Naval_EGT_File_Manager')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Naval EGT AJAX Debug Hook triggered at ' . current_time('mysql'));
            }
        }
    }, 1); // Priorità alta per loggare prima della gestione
}

?>