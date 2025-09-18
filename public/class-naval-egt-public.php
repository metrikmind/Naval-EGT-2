<?php
/**
 * Classe per la gestione del frontend pubblico - VERSIONE CORRETTA
 */

if (!defined('ABSPATH')) {
    exit;
}

class Naval_EGT_Public {
    
    private static $instance = null;
    private static $session_started = false;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Avvia sessione solo se necessario e possibile
        add_action('init', array($this, 'maybe_start_session'), 1);
        
        // Handlers AJAX per login/registrazione
        add_action('wp_ajax_naval_egt_login', array($this, 'handle_login'));
        add_action('wp_ajax_nopriv_naval_egt_login', array($this, 'handle_login'));
        add_action('wp_ajax_naval_egt_register', array($this, 'handle_registration'));
        add_action('wp_ajax_nopriv_naval_egt_register', array($this, 'handle_registration'));
        add_action('wp_ajax_naval_egt_logout', array($this, 'handle_logout'));
        
        // Handlers per utenti autenticati
        add_action('wp_ajax_naval_egt_get_user_files', array($this, 'get_user_files'));
        add_action('wp_ajax_naval_egt_get_user_activity', array($this, 'get_user_activity'));
        add_action('wp_ajax_naval_egt_get_user_stats', array($this, 'get_user_stats'));
        add_action('wp_ajax_naval_egt_upload_file', array($this, 'upload_user_file'));
        add_action('wp_ajax_naval_egt_delete_file', array($this, 'delete_user_file'));
    }
    
    /**
     * Avvia sessione solo se necessario e sicuro
     */
    public function maybe_start_session() {
        if (self::$session_started || is_admin() || headers_sent()) {
            return;
        }
        
        if ($this->should_start_session()) {
            if (@session_start()) {
                self::$session_started = true;
            }
        }
    }
    
    /**
     * Determina se è necessario avviare una sessione
     */
    private function should_start_session() {
        global $post;
        
        // Avvia sessione se siamo su una pagina con shortcode naval egt
        if ($post && has_shortcode($post->post_content, 'naval_egt_area_riservata')) {
            return true;
        }
        
        // Avvia sessione per AJAX naval egt
        if (defined('DOING_AJAX') && DOING_AJAX) {
            $action = $_REQUEST['action'] ?? '';
            if (strpos($action, 'naval_egt_') === 0) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Garantisce che la sessione sia avviata per operazioni critiche
     */
    private function ensure_session() {
        if (!self::$session_started && !headers_sent()) {
            if (!session_id() && @session_start()) {
                self::$session_started = true;
            }
        }
        return self::$session_started;
    }
    
    /**
     * Gestisce il login utente - VERSIONE CORRETTA
     */
    public function handle_login() {
        check_ajax_referer('naval_egt_nonce', 'nonce');
        
        $this->ensure_session();
        
        $login = sanitize_text_field($_POST['login'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']) && $_POST['remember'] == '1';
        
        if (empty($login) || empty($password)) {
            wp_send_json_error('Inserisci email/username e password');
        }
        
        $result = Naval_EGT_User_Manager::authenticate($login, $password);
        
        if ($result['success']) {
            // Se "ricordami" è selezionato, imposta cookie
            if ($remember) {
                $cookie_expiry = time() + (30 * DAY_IN_SECONDS);
                setcookie('naval_egt_remember', base64_encode($login), $cookie_expiry, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
            }
            
            wp_send_json_success(array(
                'message' => 'Login effettuato con successo',
                'redirect' => $_POST['redirect_to'] ?? '',
                'user' => array(
                    'nome' => $result['user']['nome'],
                    'cognome' => $result['user']['cognome'],
                    'user_code' => $result['user']['user_code']
                )
            ));
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * Gestisce la registrazione utente - VERSIONE CORRETTA
     */
    public function handle_registration() {
        check_ajax_referer('naval_egt_nonce', 'nonce');
        
        // Verifica se la registrazione è abilitata
        $registration_enabled = Naval_EGT_Database::get_setting('user_registration_enabled', '1');
        if ($registration_enabled !== '1') {
            wp_send_json_error('Le registrazioni sono temporaneamente disabilitate');
        }
        
        $data = array(
            'nome' => sanitize_text_field($_POST['nome'] ?? ''),
            'cognome' => sanitize_text_field($_POST['cognome'] ?? ''),
            'email' => sanitize_email($_POST['email'] ?? ''),
            'telefono' => sanitize_text_field($_POST['telefono'] ?? ''),
            'username' => sanitize_user($_POST['username'] ?? ''),
            'password' => $_POST['password'] ?? '',
            'password_confirm' => $_POST['password_confirm'] ?? '',
            'ragione_sociale' => sanitize_text_field($_POST['ragione_sociale'] ?? ''),
            'partita_iva' => sanitize_text_field($_POST['partita_iva'] ?? ''),
            'privacy_policy' => isset($_POST['privacy_policy']) ? '1' : '0'
        );
        
        // Validazioni aggiuntive
        if ($data['password'] !== $data['password_confirm']) {
            wp_send_json_error('Le password non corrispondono');
        }
        
        if ($data['privacy_policy'] !== '1') {
            wp_send_json_error('È necessario accettare la Privacy Policy');
        }
        
        // Rimuovi conferma password dai dati da salvare
        unset($data['password_confirm']);
        unset($data['privacy_policy']);
        
        // Crea l'utente
        $result = Naval_EGT_User_Manager::create_user($data);
        
        if ($result['success']) {
            // Invio email all'admin
            $this->send_admin_registration_notification($data, $result['user_code']);
            
            wp_send_json_success(array(
                'message' => 'Richiesta di registrazione inviata con successo! Il tuo account sarà attivato manualmente dal nostro staff. Riceverai una email di conferma.',
                'user_code' => $result['user_code']
            ));
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * Invia notifica admin per registrazione
     */
    private function send_admin_registration_notification($user_data, $user_code) {
        $email_enabled = Naval_EGT_Database::get_setting('email_notifications', '1');
        if ($email_enabled !== '1') {
            return;
        }
        
        $admin_email = get_option('admin_email');
        $subject = 'Nuova registrazione utente - Naval EGT';
        
        $message = sprintf(
            "Nuova richiesta di registrazione ricevuta:\n\n" .
            "Nome: %s %s\n" .
            "Email: %s\n" .
            "Username: %s\n" .
            "Codice Utente: %s\n" .
            "Azienda: %s\n" .
            "Telefono: %s\n\n" .
            "Vai su %s per attivare l'utente.",
            $user_data['nome'],
            $user_data['cognome'],
            $user_data['email'],
            $user_data['username'],
            $user_code,
            $user_data['ragione_sociale'] ?: 'Non specificata',
            $user_data['telefono'] ?: 'Non specificato',
            admin_url('admin.php?page=naval-egt&tab=users')
        );
        
        wp_mail($admin_email, $subject, $message);
    }
    
    /**
     * Gestisce il logout utente
     */
    public function handle_logout() {
        $current_user = Naval_EGT_User_Manager::get_current_user();
        
        Naval_EGT_User_Manager::logout();
        
        // Rimuovi cookie "ricordami"
        if (isset($_COOKIE['naval_egt_remember'])) {
            setcookie('naval_egt_remember', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
        }
        
        wp_send_json_success(array('message' => 'Logout effettuato con successo'));
    }
    
    /**
     * Ottiene i file dell'utente corrente
     */
    public function get_user_files() {
        check_ajax_referer('naval_egt_nonce', 'nonce');
        
        $current_user = Naval_EGT_User_Manager::get_current_user();
        if (!$current_user) {
            wp_send_json_error('Accesso richiesto');
        }
        
        global $wpdb;
        $table_files = $wpdb->prefix . 'naval_egt_files';
        
        $page = intval($_POST['page'] ?? 1);
        $per_page = 20;
        $offset = ($page - 1) * $per_page;
        $search = sanitize_text_field($_POST['search'] ?? '');
        
        $where_clause = "user_id = %d";
        $params = array($current_user['id']);
        
        if (!empty($search)) {
            $where_clause .= " AND file_name LIKE %s";
            $params[] = '%' . $wpdb->esc_like($search) . '%';
        }
        
        $files = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_files WHERE $where_clause ORDER BY created_at DESC LIMIT %d OFFSET %d",
            array_merge($params, array($per_page, $offset))
        ), ARRAY_A);
        
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_files WHERE $where_clause",
            $params
        ));
        
        // Formatta i file per il frontend
        $formatted_files = array();
        foreach ($files as $file) {
            $formatted_files[] = array(
                'id' => $file['id'],
                'name' => $file['file_name'],
                'size' => size_format($file['file_size']),
                'date' => mysql2date('d/m/Y H:i', $file['created_at']),
                'download_url' => add_query_arg(array(
                    'action' => 'naval_egt_download_file',
                    'file_id' => $file['id'],
                    'nonce' => wp_create_nonce('download_file_' . $file['id'])
                ), admin_url('admin-ajax.php'))
            );
        }
        
        wp_send_json_success(array(
            'files' => $formatted_files,
            'total' => (int)$total,
            'pagination' => array(
                'page' => $page,
                'per_page' => $per_page,
                'total_pages' => ceil($total / $per_page)
            )
        ));
    }
    
    /**
     * Ottiene l'attività dell'utente corrente
     */
    public function get_user_activity() {
        check_ajax_referer('naval_egt_nonce', 'nonce');
        
        $current_user = Naval_EGT_User_Manager::get_current_user();
        if (!$current_user) {
            wp_send_json_error('Accesso richiesto');
        }
        
        $page = intval($_POST['page'] ?? 1);
        $limit = 20;
        $offset = ($page - 1) * $limit;
        
        $activities = Naval_EGT_Activity_Logger::get_logs(
            array('user_id' => $current_user['id']),
            $limit,
            $offset
        );
        
        // Formatta le attività
        $formatted_activities = array();
        foreach ($activities as $activity) {
            $formatted_activities[] = array(
                'id' => $activity['id'],
                'action' => $this->format_action_name($activity['action']),
                'description' => $this->format_activity_description($activity),
                'date' => mysql2date('d/m/Y H:i', $activity['created_at']),
                'ip_address' => $activity['ip_address'] ?? ''
            );
        }
        
        wp_send_json_success(array(
            'activities' => $formatted_activities,
            'total' => count($activities)
        ));
    }
    
    /**
     * Ottiene statistiche utente
     */
    public function get_user_stats() {
        check_ajax_referer('naval_egt_nonce', 'nonce');
        
        $current_user = Naval_EGT_User_Manager::get_current_user();
        if (!$current_user) {
            wp_send_json_error('Accesso richiesto');
        }
        
        global $wpdb;
        $table_files = $wpdb->prefix . 'naval_egt_files';
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_files,
                COALESCE(SUM(file_size), 0) as total_size,
                MAX(created_at) as last_upload
             FROM $table_files 
             WHERE user_id = %d",
            $current_user['id']
        ), ARRAY_A);
        
        wp_send_json_success($stats);
    }
    
    /**
     * Upload file per utente corrente
     */
    public function upload_user_file() {
        check_ajax_referer('naval_egt_nonce', 'nonce');
        
        $current_user = Naval_EGT_User_Manager::get_current_user();
        if (!$current_user) {
            wp_send_json_error('Accesso richiesto');
        }
        
        if (!isset($_FILES['files']) || empty($_FILES['files']['name'][0])) {
            wp_send_json_error('Nessun file selezionato');
        }
        
        // Verifica stato utente
        if ($current_user['status'] !== 'ATTIVO') {
            wp_send_json_error('Account non attivo. Contatta l\'amministratore.');
        }
        
        // Processa file multipli
        $uploaded_files = array();
        $errors = array();
        
        $files = $_FILES['files'];
        $file_count = count($files['name']);
        
        for ($i = 0; $i < $file_count; $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                $errors[] = "Errore nel file {$files['name'][$i]}";
                continue;
            }
            
            // Validazioni file
            $file_name = $files['name'][$i];
            $file_size = $files['size'][$i];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            // Controlla estensione
            $allowed_types = explode(',', Naval_EGT_Database::get_setting('allowed_file_types', 'pdf,doc,docx,xls,xlsx,jpg,jpeg,png,gif'));
            if (!in_array($file_ext, $allowed_types)) {
                $errors[] = "Tipo file non consentito: {$file_name}";
                continue;
            }
            
            // Controlla dimensione
            $max_size = intval(Naval_EGT_Database::get_setting('max_file_size', '20971520')); // 20MB
            if ($file_size > $max_size) {
                $errors[] = "File troppo grande: {$file_name} (" . size_format($file_size) . ")";
                continue;
            }
            
            $file = array(
                'name' => $file_name,
                'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $file_size
            );
            
            // Upload singolo file
            $upload_result = $this->process_single_file_upload($file, $current_user);
            
            if ($upload_result['success']) {
                $uploaded_files[] = $file_name;
            } else {
                $errors[] = $file_name . ': ' . $upload_result['message'];
            }
        }
        
        if (!empty($uploaded_files)) {
            $message = 'File caricati con successo: ' . implode(', ', $uploaded_files);
            if (!empty($errors)) {
                $message .= '. Errori: ' . implode(', ', $errors);
            }
            
            wp_send_json_success(array(
                'message' => $message,
                'uploaded' => $uploaded_files,
                'errors' => $errors
            ));
        } else {
            wp_send_json_error('Nessun file è stato caricato. Errori: ' . implode(', ', $errors));
        }
    }
    
    /**
     * Processa upload di un singolo file
     */
    private function process_single_file_upload($file, $user) {
        // Verifica che Dropbox sia configurato
        if (!class_exists('Naval_EGT_Dropbox')) {
            return array('success' => false, 'message' => 'Sistema archiviazione non disponibile');
        }
        
        $dropbox = Naval_EGT_Dropbox::get_instance();
        
        if (!$dropbox->is_configured()) {
            return array('success' => false, 'message' => 'Sistema archiviazione non configurato');
        }
        
        // Cerca cartella utente
        $folder_result = $dropbox->find_folder_by_code($user['user_code']);
        
        if (!$folder_result['success'] || empty($folder_result['folders'])) {
            return array(
                'success' => false,
                'message' => 'Cartella Dropbox non trovata per il codice ' . $user['user_code']
            );
        }
        
        $user_folder = $folder_result['folders'][0]['path_lower'];
        $dropbox_path = $user_folder . '/' . $file['name'];
        
        // Upload su Dropbox
        $upload_result = $dropbox->upload_file($file['tmp_name'], $dropbox_path);
        
        if (!$upload_result['success']) {
            return array(
                'success' => false,
                'message' => 'Errore upload: ' . $upload_result['message']
            );
        }
        
        // Salva nel database
        global $wpdb;
        $result = $wpdb->insert(
            $wpdb->prefix . 'naval_egt_files',
            array(
                'user_id' => $user['id'],
                'user_code' => $user['user_code'],
                'file_name' => $file['name'],
                'file_path' => $upload_result['data']['path_display'],
                'dropbox_path' => $upload_result['data']['path_lower'],
                'file_size' => $file['size'],
                'dropbox_id' => $upload_result['data']['id'],
                'last_modified' => current_time('mysql'),
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s')
        );
        
        if ($result) {
            // Log upload
            Naval_EGT_Activity_Logger::log_activity(
                $user['id'],
                $user['user_code'],
                'UPLOAD',
                $file['name'],
                $dropbox_path,
                $file['size']
            );
            
            return array('success' => true, 'file_id' => $wpdb->insert_id);
        } else {
            return array('success' => false, 'message' => 'Errore salvataggio database');
        }
    }
    
    /**
     * Elimina file utente
     */
    public function delete_user_file() {
        check_ajax_referer('naval_egt_nonce', 'nonce');
        
        $current_user = Naval_EGT_User_Manager::get_current_user();
        if (!$current_user) {
            wp_send_json_error('Accesso richiesto');
        }
        
        $file_id = intval($_POST['file_id'] ?? 0);
        
        if (!$file_id) {
            wp_send_json_error('ID file non valido');
        }
        
        // Verifica proprietà del file
        global $wpdb;
        $file = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}naval_egt_files WHERE id = %d AND user_id = %d",
            $file_id, $current_user['id']
        ), ARRAY_A);
        
        if (!$file) {
            wp_send_json_error('File non trovato o non autorizzato');
        }
        
        // Elimina dal database
        $wpdb->delete($wpdb->prefix . 'naval_egt_files', array('id' => $file_id), array('%d'));
        
        // Log eliminazione
        Naval_EGT_Activity_Logger::log_activity(
            $current_user['id'],
            $current_user['user_code'],
            'DELETE',
            $file['file_name'],
            $file['dropbox_path'],
            $file['file_size']
        );
        
        wp_send_json_success('File eliminato con successo');
    }
    
    /**
     * Formatta nome azione per display
     */
    private function format_action_name($action) {
        $actions = array(
            'LOGIN' => 'Accesso',
            'LOGOUT' => 'Disconnessione',
            'UPLOAD' => 'Caricamento file',
            'DOWNLOAD' => 'Scaricamento file',
            'DELETE' => 'Eliminazione file',
            'REGISTRATION' => 'Registrazione'
        );
        
        return $actions[$action] ?? $action;
    }
    
    /**
     * Formatta descrizione attività
     */
    private function format_activity_description($activity) {
        switch ($activity['action']) {
            case 'UPLOAD':
                return 'Caricato: ' . ($activity['file_name'] ?? 'file sconosciuto');
            case 'DOWNLOAD':
                return 'Scaricato: ' . ($activity['file_name'] ?? 'file sconosciuto');
            case 'DELETE':
                return 'Eliminato: ' . ($activity['file_name'] ?? 'file sconosciuto');
            case 'LOGIN':
                return 'Accesso effettuato';
            case 'LOGOUT':
                return 'Disconnessione effettuata';
            case 'REGISTRATION':
                return 'Account registrato';
            default:
                return $activity['action'];
        }
    }
    
    /**
     * Ottiene informazioni pubbliche per shortcode
     */
    public static function get_public_info() {
        return array(
            'registration_enabled' => Naval_EGT_Database::get_setting('user_registration_enabled', '1') === '1',
            'support_email' => 'tecnica@naval.it'
        );
    }
    
    /**
     * Inizializzazione del frontend
     */
    public static function init() {
        $instance = self::get_instance();
        
        // Handle logout via GET parameter
        if (isset($_GET['logout']) && $_GET['logout'] == '1') {
            Naval_EGT_User_Manager::logout();
            wp_redirect(remove_query_arg('logout'));
            exit;
        }
    }
}