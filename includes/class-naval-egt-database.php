<?php
/**
 * Classe per la gestione del database (Aggiornato)
 */

if (!defined('ABSPATH')) {
    exit;
}

class Naval_EGT_Database {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Constructor
    }
    
    /**
     * Crea le tabelle del database
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Tabella utenti area riservata
        $table_users = $wpdb->prefix . 'naval_egt_users';
        $sql_users = "CREATE TABLE $table_users (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_code varchar(6) NOT NULL,
            nome varchar(100) NOT NULL,
            cognome varchar(100) NOT NULL,
            email varchar(100) NOT NULL,
            username varchar(50) NOT NULL,
            password varchar(255) NOT NULL,
            telefono varchar(20),
            ragione_sociale varchar(200),
            partita_iva varchar(20),
            status enum('ATTIVO','SOSPESO') DEFAULT 'SOSPESO',
            dropbox_folder varchar(255),
            last_login datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_code (user_code),
            UNIQUE KEY email (email),
            UNIQUE KEY username (username)
        ) $charset_collate;";
        
        // Tabella log attività
        $table_logs = $wpdb->prefix . 'naval_egt_activity_logs';
        $sql_logs = "CREATE TABLE $table_logs (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id mediumint(9),
            user_code varchar(6),
            action enum('LOGIN','LOGOUT','UPLOAD','DOWNLOAD','REGISTRATION','ADMIN_UPLOAD','ADMIN_ACTION','DELETE') NOT NULL,
            file_name varchar(255),
            file_path varchar(500),
            file_size bigint,
            ip_address varchar(45),
            user_agent text,
            details text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY user_code (user_code),
            KEY action (action),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // AGGIORNATO: Tabella file con campi aggiuntivi
        $table_files = $wpdb->prefix . 'naval_egt_files';
        $sql_files = "CREATE TABLE $table_files (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id mediumint(9) NOT NULL,
            user_code varchar(6) NOT NULL,
            file_name varchar(255) NOT NULL,
            original_name varchar(255),
            file_path varchar(500) NOT NULL,
            dropbox_path varchar(500) NOT NULL,
            file_size bigint,
            mime_type varchar(100),
            dropbox_id varchar(255),
            last_modified datetime,
            uploaded_by varchar(20) DEFAULT 'user',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY user_code (user_code),
            KEY file_name (file_name),
            KEY dropbox_id (dropbox_id),
            KEY uploaded_by (uploaded_by)
        ) $charset_collate;";
        
        // Tabella impostazioni
        $table_settings = $wpdb->prefix . 'naval_egt_settings';
        $sql_settings = "CREATE TABLE $table_settings (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            setting_key varchar(100) NOT NULL,
            setting_value longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY setting_key (setting_key)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($sql_users);
        dbDelta($sql_logs);
        dbDelta($sql_files);
        dbDelta($sql_settings);
        
        // Inserisce impostazioni default
        self::insert_default_settings();
        
        // AGGIORNATO: Aggiorna versione database dopo creazione tabelle
        self::update_db_version('1.1.0');
    }
    
    /**
     * Elimina le tabelle del database
     */
    public static function drop_tables() {
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'naval_egt_users',
            $wpdb->prefix . 'naval_egt_activity_logs',
            $wpdb->prefix . 'naval_egt_files',
            $wpdb->prefix . 'naval_egt_settings'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
    }
    
    /**
     * AGGIORNATO: Aggiorna struttura database se necessario
     */
    public static function maybe_upgrade_database() {
        $current_version = self::get_db_version();
        $target_version = '1.1.0';
        
        if (version_compare($current_version, $target_version, '<')) {
            self::upgrade_database_to_1_1_0();
        }
    }
    
    /**
     * NUOVO: Aggiorna database alla versione 1.1.0
     */
    private static function upgrade_database_to_1_1_0() {
        global $wpdb;
        
        $table_files = $wpdb->prefix . 'naval_egt_files';
        
        // Controlla se i nuovi campi esistono già
        $columns = $wpdb->get_results("DESCRIBE $table_files");
        $existing_columns = array_column($columns, 'Field');
        
        // Aggiungi campi mancanti
        if (!in_array('original_name', $existing_columns)) {
            $wpdb->query("ALTER TABLE $table_files ADD COLUMN original_name varchar(255) AFTER file_name");
        }
        
        if (!in_array('mime_type', $existing_columns)) {
            $wpdb->query("ALTER TABLE $table_files ADD COLUMN mime_type varchar(100) AFTER file_size");
        }
        
        if (!in_array('uploaded_by', $existing_columns)) {
            $wpdb->query("ALTER TABLE $table_files ADD COLUMN uploaded_by varchar(20) DEFAULT 'user' AFTER last_modified");
        }
        
        // Rinomina file_type in mime_type se esiste
        if (in_array('file_type', $existing_columns) && !in_array('mime_type', $existing_columns)) {
            $wpdb->query("ALTER TABLE $table_files CHANGE file_type mime_type varchar(100)");
        }
        
        // Aggiungi indici mancanti
        $indexes = $wpdb->get_results("SHOW INDEX FROM $table_files");
        $existing_indexes = array_column($indexes, 'Key_name');
        
        if (!in_array('dropbox_id', $existing_indexes)) {
            $wpdb->query("ALTER TABLE $table_files ADD INDEX dropbox_id (dropbox_id)");
        }
        
        if (!in_array('uploaded_by', $existing_indexes)) {
            $wpdb->query("ALTER TABLE $table_files ADD INDEX uploaded_by (uploaded_by)");
        }
        
        // Aggiorna log activities per supportare nuove azioni
        $table_logs = $wpdb->prefix . 'naval_egt_activity_logs';
        $wpdb->query("ALTER TABLE $table_logs MODIFY action enum('LOGIN','LOGOUT','UPLOAD','DOWNLOAD','REGISTRATION','ADMIN_UPLOAD','ADMIN_ACTION','DELETE') NOT NULL");
        
        // Aggiorna versione
        self::update_db_version('1.1.0');
    }
    
    /**
     * Inserisce le impostazioni di default
     */
    private static function insert_default_settings() {
        global $wpdb;
        
        $table_settings = $wpdb->prefix . 'naval_egt_settings';
        
        $default_settings = array(
            'dropbox_app_key' => '',
            'dropbox_app_secret' => '',
            'dropbox_access_token' => '',
            'dropbox_refresh_token' => '',
            'email_notifications' => '1',
            'allowed_file_types' => 'pdf,doc,docx,xls,xlsx,jpg,jpeg,png,gif,dwg,dxf,zip,rar',
            'max_file_size' => '10485760', // 10MB
            'user_registration_enabled' => '1',
            'manual_user_activation' => '1',
            'welcome_email_template' => 'Benvenuto nell\'Area Riservata Naval EGT',
            'next_user_code' => '100001',
            'delete_from_dropbox' => '0', // NUOVO: Non elimina da Dropbox per default
            'admin_upload_notifications' => '1' // NUOVO: Notifica upload admin
        );
        
        foreach ($default_settings as $key => $value) {
            $wpdb->replace(
                $table_settings,
                array(
                    'setting_key' => $key,
                    'setting_value' => $value
                ),
                array('%s', '%s')
            );
        }
    }
    
    /**
     * Ottiene un'impostazione
     */
    public static function get_setting($key, $default = '') {
        global $wpdb;
        
        $table_settings = $wpdb->prefix . 'naval_egt_settings';
        $value = $wpdb->get_var($wpdb->prepare(
            "SELECT setting_value FROM $table_settings WHERE setting_key = %s",
            $key
        ));
        
        return $value !== null ? $value : $default;
    }
    
    /**
     * Aggiorna un'impostazione
     */
    public static function update_setting($key, $value) {
        global $wpdb;
        
        $table_settings = $wpdb->prefix . 'naval_egt_settings';
        
        return $wpdb->replace(
            $table_settings,
            array(
                'setting_key' => $key,
                'setting_value' => $value
            ),
            array('%s', '%s')
        );
    }
    
    /**
     * Genera il prossimo codice utente
     */
    public static function get_next_user_code() {
        $next_code = self::get_setting('next_user_code', '100001');
        $new_code = str_pad((int)$next_code + 1, 6, '0', STR_PAD_LEFT);
        self::update_setting('next_user_code', $new_code);
        
        return $next_code;
    }
    
    /**
     * AGGIORNATO: Ottiene statistiche utenti con più dettagli
     */
    public static function get_user_stats() {
        global $wpdb;
        
        $table_users = $wpdb->prefix . 'naval_egt_users';
        $table_files = $wpdb->prefix . 'naval_egt_files';
        
        $total_users = $wpdb->get_var("SELECT COUNT(*) FROM $table_users");
        $active_users = $wpdb->get_var("SELECT COUNT(*) FROM $table_users WHERE status = 'ATTIVO'");
        $suspended_users = $wpdb->get_var("SELECT COUNT(*) FROM $table_users WHERE status = 'SOSPESO'");
        $users_with_files = $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM $table_files");
        $recent_logins = $wpdb->get_var("SELECT COUNT(*) FROM $table_users WHERE last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        
        // Statistiche file
        $total_files = $wpdb->get_var("SELECT COUNT(*) FROM $table_files");
        $total_size = $wpdb->get_var("SELECT SUM(file_size) FROM $table_files");
        $files_today = $wpdb->get_var("SELECT COUNT(*) FROM $table_files WHERE DATE(created_at) = CURDATE()");
        $files_this_week = $wpdb->get_var("SELECT COUNT(*) FROM $table_files WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        
        return array(
            'users' => array(
                'total' => (int)$total_users,
                'active' => (int)$active_users,
                'suspended' => (int)$suspended_users,
                'with_files' => (int)$users_with_files,
                'recent_logins' => (int)$recent_logins
            ),
            'files' => array(
                'total' => (int)$total_files,
                'total_size' => (int)$total_size,
                'today' => (int)$files_today,
                'this_week' => (int)$files_this_week
            )
        );
    }
    
    /**
     * AGGIORNATO: Ottiene attività recenti con più dettagli
     */
    public static function get_recent_activities($limit = 10, $filter_actions = array()) {
        global $wpdb;
        
        $table_logs = $wpdb->prefix . 'naval_egt_activity_logs';
        $table_users = $wpdb->prefix . 'naval_egt_users';
        
        $where_clause = '';
        if (!empty($filter_actions)) {
            $placeholders = implode(',', array_fill(0, count($filter_actions), '%s'));
            $where_clause = "WHERE l.action IN ($placeholders)";
        }
        
        $query = "SELECT l.*, CONCAT(u.nome, ' ', u.cognome) as user_name
                 FROM $table_logs l
                 LEFT JOIN $table_users u ON l.user_id = u.id
                 $where_clause
                 ORDER BY l.created_at DESC
                 LIMIT %d";
        
        $params = array_merge($filter_actions, array($limit));
        
        return $wpdb->get_results($wpdb->prepare($query, $params), ARRAY_A);
    }
    
    /**
     * NUOVO: Ottiene statistiche upload per periodo
     */
    public static function get_upload_stats($period = 'week') {
        global $wpdb;
        
        $table_files = $wpdb->prefix . 'naval_egt_files';
        
        $date_condition = '';
        switch ($period) {
            case 'today':
                $date_condition = "DATE(created_at) = CURDATE()";
                break;
            case 'week':
                $date_condition = "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                break;
            case 'month':
                $date_condition = "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                break;
            default:
                $date_condition = "1=1";
        }
        
        $stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total_uploads,
                SUM(file_size) as total_size,
                COUNT(DISTINCT user_id) as unique_users,
                COUNT(CASE WHEN uploaded_by = 'admin' THEN 1 END) as admin_uploads,
                COUNT(CASE WHEN uploaded_by = 'user' THEN 1 END) as user_uploads
             FROM $table_files 
             WHERE $date_condition",
            ARRAY_A
        );
        
        // Statistiche per tipo di file
        $by_type = $wpdb->get_results(
            "SELECT 
                SUBSTRING_INDEX(file_name, '.', -1) as extension,
                COUNT(*) as count,
                SUM(file_size) as total_size
             FROM $table_files 
             WHERE $date_condition
             GROUP BY extension
             ORDER BY count DESC
             LIMIT 5",
            ARRAY_A
        );
        
        return array(
            'period' => $period,
            'total_uploads' => (int)($stats['total_uploads'] ?? 0),
            'total_size' => (int)($stats['total_size'] ?? 0),
            'unique_users' => (int)($stats['unique_users'] ?? 0),
            'admin_uploads' => (int)($stats['admin_uploads'] ?? 0),
            'user_uploads' => (int)($stats['user_uploads'] ?? 0),
            'by_type' => $by_type
        );
    }
    
    /**
     * NUOVO: Ottiene top utenti per numero di file
     */
    public static function get_top_users_by_files($limit = 10) {
        global $wpdb;
        
        $table_files = $wpdb->prefix . 'naval_egt_files';
        $table_users = $wpdb->prefix . 'naval_egt_users';
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                u.user_code,
                CONCAT(u.nome, ' ', u.cognome) as user_name,
                u.ragione_sociale,
                COUNT(f.id) as file_count,
                SUM(f.file_size) as total_size,
                MAX(f.created_at) as last_upload
            FROM $table_users u
            LEFT JOIN $table_files f ON u.id = f.user_id
            WHERE u.status = 'ATTIVO'
            GROUP BY u.id
            HAVING file_count > 0
            ORDER BY file_count DESC, total_size DESC
            LIMIT %d
        ", $limit), ARRAY_A);
    }
    
    /**
     * Verifica se una tabella esiste
     */
    public static function table_exists($table_name) {
        global $wpdb;
        
        $table = $wpdb->prefix . $table_name;
        return $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
    }
    
    /**
     * Ottiene la versione del database
     */
    public static function get_db_version() {
        return get_option('naval_egt_db_version', '1.0.0');
    }
    
    /**
     * Aggiorna la versione del database
     */
    public static function update_db_version($version) {
        update_option('naval_egt_db_version', $version);
    }
    
    /**
     * NUOVO: Pulisci dati vecchi (manutenzione database)
     */
    public static function cleanup_old_data() {
        global $wpdb;
        
        $table_logs = $wpdb->prefix . 'naval_egt_activity_logs';
        
        // Elimina log più vecchi di 6 mesi
        $deleted_logs = $wpdb->query(
            "DELETE FROM $table_logs 
             WHERE created_at < DATE_SUB(NOW(), INTERVAL 6 MONTH)"
        );
        
        // Ottimizza tabelle
        $wpdb->query("OPTIMIZE TABLE $table_logs");
        
        return array(
            'deleted_logs' => $deleted_logs,
            'success' => true
        );
    }
    
    /**
     * NUOVO: Verifica integrità database
     */
    public static function check_database_integrity() {
        global $wpdb;
        
        $issues = array();
        
        // Controlla file orfani (senza utente)
        $orphaned_files = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->prefix}naval_egt_files f
            LEFT JOIN {$wpdb->prefix}naval_egt_users u ON f.user_id = u.id
            WHERE u.id IS NULL
        ");
        
        if ($orphaned_files > 0) {
            $issues[] = "File orfani trovati: $orphaned_files";
        }
        
        // Controlla log orfani (senza utente)
        $orphaned_logs = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->prefix}naval_egt_activity_logs l
            LEFT JOIN {$wpdb->prefix}naval_egt_users u ON l.user_id = u.id
            WHERE l.user_id IS NOT NULL AND u.id IS NULL
        ");
        
        if ($orphaned_logs > 0) {
            $issues[] = "Log orfani trovati: $orphaned_logs";
        }
        
        // Controlla codici utente duplicati
        $duplicate_codes = $wpdb->get_var("
            SELECT COUNT(*) - COUNT(DISTINCT user_code)
            FROM {$wpdb->prefix}naval_egt_users
        ");
        
        if ($duplicate_codes > 0) {
            $issues[] = "Codici utente duplicati: $duplicate_codes";
        }
        
        return array(
            'issues' => $issues,
            'healthy' => empty($issues)
        );
    }
}
?>