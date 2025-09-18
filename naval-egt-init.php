<?php
/**
 * Naval EGT - Inizializzazione e integrazione del sistema file
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe principale per l'inizializzazione del plugin
 */
class Naval_EGT_Init {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Inizializza gli hook WordPress
     */
    private function init_hooks() {
        // Hook di attivazione plugin
        register_activation_hook(__FILE__, array(__CLASS__, 'on_activation'));
        register_deactivation_hook(__FILE__, 'on_deactivation'));
        
        // Inizializzazione admin
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        
        // Inizializzazione AJAX
        add_action('wp_ajax_naval_egt_ajax', array('Naval_EGT_File_Manager', 'handle_admin_ajax'));
        add_action('wp_ajax_naval_egt_download_file', array('Naval_EGT_File_Manager', 'handle_download'));
        
        // Inizializzazione frontend (se necessario)
        add_action('wp_enqueue_scripts', array($this, 'frontend_enqueue_scripts'));
        
        // Cron job per manutenzione
        add_action('naval_egt_daily_cleanup', array(__CLASS__, 'daily_maintenance'));
        
        // Controllo aggiornamenti database
        add_action('admin_init', array(__CLASS__, 'check_database_updates'));
    }
    
    /**
     * Attivazione plugin
     */
    public static function on_activation() {
        // Crea/aggiorna tabelle database
        Naval_EGT_Database::create_tables();
        
        // Programma eventi cron
        if (!wp_next_scheduled('naval_egt_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'naval_egt_daily_cleanup');
        }
        
        // Flush rewrite rules se necessario
        flush_rewrite_rules();
        
        // Log attivazione
        error_log('Naval EGT Plugin attivato');
    }
    
    /**
     * Disattivazione plugin
     */
    public static function on_deactivation() {
        // Rimuovi eventi cron
        wp_clear_scheduled_hook('naval_egt_daily_cleanup');
        
        // Log disattivazione
        error_log('Naval EGT Plugin disattivato');
    }
    
    /**
     * Inizializzazione area admin
     */
    public function admin_init() {
        // Inizializza gestori AJAX solo se necessario
        if (defined('DOING_AJAX') && DOING_AJAX) {
            // File Manager AJAX handler è già registrato negli hook
            return;
        }
        
        // Verifica permessi per pagine admin
        if (current_user_can('manage_options')) {
            // Inizializzazione completa per amministratori
            $this->init_admin_components();
        }
    }
    
    /**
     * Inizializza componenti admin
     */
    private function init_admin_components() {
        // Inizializza gestori
        Naval_EGT_File_Manager::get_instance();
        Naval_EGT_Database::get_instance();
        
        // Aggiungi meta box se necessario
        add_action('add_meta_boxes', array($this, 'add_admin_meta_boxes'));
    }
    
    /**
     * Carica script e stili admin
     */
    public function admin_enqueue_scripts($hook) {
        // Carica solo nelle pagine del plugin
        if (strpos($hook, 'naval-egt') === false) {
            return;
        }
        
        // Script comuni
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-dialog');
        wp_enqueue_script('jquery-ui-progressbar');
        
        // Stili admin
        wp_enqueue_style('wp-jquery-ui-dialog');
        
        // Script personalizzato per gestione file
        wp_enqueue_script(
            'naval-egt-admin',
            plugin_dir_url(__FILE__) . 'assets/js/admin.js',
            array('jquery', 'jquery-ui-dialog'),
            '1.1.0',
            true
        );
        
        // Variabili JavaScript
        wp_localize_script('naval-egt-admin', 'naval_egt_ajax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('naval_egt_nonce'),
            'strings' => array(
                'confirm_delete' => __('Sei sicuro di voler eliminare questo file?', 'naval-egt'),
                'confirm_delete_multiple' => __('Sei sicuro di voler eliminare i file selezionati?', 'naval-egt'),
                'upload_success' => __('File caricati con successo', 'naval-egt'),
                'upload_error' => __('Errore durante il caricamento', 'naval-egt'),
                'sync_success' => __('Sincronizzazione completata', 'naval-egt'),
                'sync_error' => __('Errore durante la sincronizzazione', 'naval-egt'),
                'no_files_selected' => __('Nessun file selezionato', 'naval-egt'),
                'loading' => __('Caricamento...', 'naval-egt'),
                'select_user_first' => __('Seleziona prima un utente', 'naval-egt'),
                'select_folder_first' => __('Seleziona prima una cartella di destinazione', 'naval-egt')
            ),
            'settings' => array(
                'max_file_size' => Naval_EGT_Database::get_setting('max_file_size', '10485760'),
                'allowed_types' => explode(',', Naval_EGT_Database::get_setting('allowed_file_types', 'pdf,doc,docx,xls,xlsx,jpg,jpeg,png,gif,dwg,dxf,zip,rar')),
                'max_files_per_upload' => 10
            )
        ));
        
        // CSS personalizzato
        wp_enqueue_style(
            'naval-egt-admin',
            plugin_dir_url(__FILE__) . 'assets/css/admin.css',
            array(),
            '1.1.0'
        );
    }
    
    /**
     * Carica script frontend (se necessario)
     */
    public function frontend_enqueue_scripts() {
        // Solo se siamo in una pagina che usa il plugin
        if (!$this->is_naval_egt_page()) {
            return;
        }
        
        wp_enqueue_script(
            'naval-egt-frontend',
            plugin_dir_url(__FILE__) . 'assets/js/frontend.js',
            array('jquery'),
            '1.1.0',
            true
        );
        
        wp_localize_script('naval-egt-frontend', 'naval_egt_ajax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('naval_egt_nonce')
        ));
    }
    
    /**
     * Verifica se siamo in una pagina del plugin
     */
    private function is_naval_egt_page() {
        // Logica per determinare se caricare script frontend
        // Per ora ritorna false, implementare secondo necessità
        return false;
    }
    
    /**
     * Aggiunge meta box admin se necessario
     */
    public function add_admin_meta_boxes() {
        // Implementare se servono meta box specifici
    }
    
    /**
     * Controllo aggiornamenti database
     */
    public static function check_database_updates() {
        $current_version = Naval_EGT_Database::get_db_version();
        $plugin_version = '1.1.0'; // Versione del plugin
        
        if (version_compare($current_version, $plugin_version, '<')) {
            Naval_EGT_Database::maybe_upgrade_database();
        }
    }
    
    /**
     * Manutenzione quotidiana
     */
    public static function daily_maintenance() {
        // Pulizia dati vecchi
        $cleanup_result = Naval_EGT_Database::cleanup_old_data();
        
        // Controllo integrità
        $integrity_check = Naval_EGT_Database::check_database_integrity();
        
        // Log risultati manutenzione
        error_log('Naval EGT Daily Maintenance: ' . json_encode(array(
            'cleanup' => $cleanup_result,
            'integrity' => $integrity_check,
            'timestamp' => current_time('mysql')
        )));
        
        // Invio notifica admin se ci sono problemi
        if (!$integrity_check['healthy']) {
            self::notify_admin_database_issues($integrity_check['issues']);
        }
    }
    
    /**
     * Notifica amministratore di problemi database
     */
    private static function notify_admin_database_issues($issues) {
        $admin_email = get_option('admin_email');
        
        if (!$admin_email) {
            return;
        }
        
        $subject = '[Naval EGT] Problemi Database Rilevati';
        $message = "Sono stati rilevati i seguenti problemi nel database Naval EGT:\n\n";
        $message .= implode("\n", $issues);
        $message .= "\n\nConsigliamo di controllare l'area admin per maggiori dettagli.";
        
        wp_mail($admin_email, $subject, $message);
    }
    
    /**
     * Utility per debug (solo in sviluppo)
     */
    public static function debug_log($message, $data = null) {
        if (!WP_DEBUG || !WP_DEBUG_LOG) {
            return;
        }
        
        $log_message = '[Naval EGT] ' . $message;
        
        if ($data !== null) {
            $log_message .= ' | Data: ' . json_encode($data);
        }
        
        error_log($log_message);
    }
    
    /**
     * Ottieni informazioni sistema per supporto
     */
    public static function get_system_info() {
        global $wpdb;
        
        return array(
            'plugin_version' => '1.1.0',
            'wp_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'mysql_version' => $wpdb->db_version(),
            'db_version' => Naval_EGT_Database::get_db_version(),
            'dropbox_configured' => Naval_EGT_Dropbox::get_instance()->is_configured(),
            'tables_exist' => array(
                'users' => Naval_EGT_Database::table_exists('naval_egt_users'),
                'files' => Naval_EGT_Database::table_exists('naval_egt_files'),
                'logs' => Naval_EGT_Database::table_exists('naval_egt_activity_logs'),
                'settings' => Naval_EGT_Database::table_exists('naval_egt_settings')
            ),
            'stats' => Naval_EGT_Database::get_user_stats(),
            'upload_limits' => array(
                'max_file_size' => Naval_EGT_Database::get_setting('max_file_size'),
                'allowed_types' => Naval_EGT_Database::get_setting('allowed_file_types'),
                'php_upload_max_filesize' => ini_get('upload_max_filesize'),
                'php_post_max_size' => ini_get('post_max_size'),
                'php_max_execution_time' => ini_get('max_execution_time')
            )
        );
    }
}

// Inizializza il plugin
Naval_EGT_Init::get_instance();

/**
 * Funzioni di utilità globali
 */

/**
 * Ottieni istanza File Manager
 */
function naval_egt_file_manager() {
    return Naval_EGT_File_Manager::get_instance();
}

/**
 * Ottieni istanza Database
 */
function naval_egt_database() {
    return Naval_EGT_Database::get_instance();
}

/**
 * Shortcode per visualizzazione file utente (frontend)
 */
function naval_egt_user_files_shortcode($atts = array()) {
    // Implementare shortcode per visualizzazione file frontend
    $atts = shortcode_atts(array(
        'user_id' => 0,
        'limit' => 10,
        'show_upload' => 'true'
    ), $atts, 'naval_egt_user_files');
    
    // Per ora ritorna un placeholder
    return '<div class="naval-egt-user-files">Funzionalità in sviluppo</div>';
}
add_shortcode('naval_egt_user_files', 'naval_egt_user_files_shortcode');

/**
 * Hook per sviluppatori esterni
 */

// Dopo upload file
do_action('naval_egt_after_file_upload', $file_id, $user_id, $file_data);

// Prima eliminazione file
do_action('naval_egt_before_file_delete', $file_id, $file_data);

// Dopo sincronizzazione
do_action('naval_egt_after_sync', $user_id, $sync_results);

// Filtro per tipi file consentiti
apply_filters('naval_egt_allowed_file_types', $allowed_types, $user_id);

// Filtro per dimensione massima file
apply_filters('naval_egt_max_file_size', $max_size, $user_id);
?>