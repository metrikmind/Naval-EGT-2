<?php
/**
 * Classe per la gestione degli utenti Naval EGT
 * Funzionalità: gestione utenti area riservata, autenticazione, profili
 */

if (!defined('ABSPATH')) {
    exit;
}

class Naval_EGT_User_Manager {
    
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
     * Ottieni utente per ID
     */
    public static function get_user_by_id($user_id) {
        global $wpdb;
        
        $table_users = $wpdb->prefix . 'naval_egt_users';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_users WHERE id = %d",
            $user_id
        ), ARRAY_A);
    }
    
    /**
     * Ottieni utente per codice
     */
    public static function get_user_by_code($user_code) {
        global $wpdb;
        
        $table_users = $wpdb->prefix . 'naval_egt_users';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_users WHERE user_code = %s",
            $user_code
        ), ARRAY_A);
    }
    
    /**
     * Ottieni utente per email
     */
    public static function get_user_by_email($email) {
        global $wpdb;
        
        $table_users = $wpdb->prefix . 'naval_egt_users';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_users WHERE email = %s",
            $email
        ), ARRAY_A);
    }
    
    /**
     * Ottieni tutti gli utenti con filtri
     */
    public static function get_users($filters = array(), $limit = 50, $offset = 0) {
        global $wpdb;
        
        $table_users = $wpdb->prefix . 'naval_egt_users';
        
        $where = array('1=1');
        $values = array();
        
        // Filtro per status
        if (!empty($filters['status'])) {
            $where[] = 'status = %s';
            $values[] = $filters['status'];
        }
        
        // Filtro per ricerca
        if (!empty($filters['search'])) {
            $search = '%' . $wpdb->esc_like($filters['search']) . '%';
            $where[] = '(nome LIKE %s OR cognome LIKE %s OR email LIKE %s OR user_code LIKE %s OR ragione_sociale LIKE %s)';
            $values = array_merge($values, array($search, $search, $search, $search, $search));
        }
        
        $where_clause = implode(' AND ', $where);
        
        $sql = "SELECT * FROM $table_users WHERE $where_clause ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $values = array_merge($values, array($limit, $offset));
        
        return $wpdb->get_results($wpdb->prepare($sql, $values), ARRAY_A);
    }
    
    /**
     * Conta utenti con filtri
     */
    public static function count_users($filters = array()) {
        global $wpdb;
        
        $table_users = $wpdb->prefix . 'naval_egt_users';
        
        $where = array('1=1');
        $values = array();
        
        // Filtro per status
        if (!empty($filters['status'])) {
            $where[] = 'status = %s';
            $values[] = $filters['status'];
        }
        
        // Filtro per ricerca
        if (!empty($filters['search'])) {
            $search = '%' . $wpdb->esc_like($filters['search']) . '%';
            $where[] = '(nome LIKE %s OR cognome LIKE %s OR email LIKE %s OR user_code LIKE %s OR ragione_sociale LIKE %s)';
            $values = array_merge($values, array($search, $search, $search, $search, $search));
        }
        
        $where_clause = implode(' AND ', $where);
        
        $sql = "SELECT COUNT(*) FROM $table_users WHERE $where_clause";
        
        if (!empty($values)) {
            return (int)$wpdb->get_var($wpdb->prepare($sql, $values));
        } else {
            return (int)$wpdb->get_var($sql);
        }
    }
    
    /**
     * Crea nuovo utente
     */
    public static function create_user($user_data) {
        global $wpdb;
        
        $table_users = $wpdb->prefix . 'naval_egt_users';
        
        // Genera codice utente se non fornito
        if (empty($user_data['user_code'])) {
            $user_data['user_code'] = Naval_EGT_Database::get_next_user_code();
        }
        
        // Hash password se fornita
        if (!empty($user_data['password'])) {
            $user_data['password'] = wp_hash_password($user_data['password']);
        }
        
        $user_data['created_at'] = current_time('mysql');
        
        $result = $wpdb->insert($table_users, $user_data);
        
        if ($result) {
            $user_id = $wpdb->insert_id;
            
            // Log creazione utente
            Naval_EGT_Activity_Logger::log_activity(
                $user_id,
                $user_data['user_code'],
                'REGISTRATION',
                null,
                null,
                0,
                array('created_by' => 'admin')
            );
            
            return array(
                'success' => true,
                'user_id' => $user_id,
                'message' => 'Utente creato con successo'
            );
        } else {
            return array(
                'success' => false,
                'message' => 'Errore nella creazione dell\'utente'
            );
        }
    }
    
    /**
     * Aggiorna utente
     */
    public static function update_user($user_id, $user_data) {
        global $wpdb;
        
        $table_users = $wpdb->prefix . 'naval_egt_users';
        
        // Hash password se fornita
        if (!empty($user_data['password'])) {
            $user_data['password'] = wp_hash_password($user_data['password']);
        }
        
        $user_data['updated_at'] = current_time('mysql');
        
        $result = $wpdb->update(
            $table_users,
            $user_data,
            array('id' => $user_id),
            null,
            array('%d')
        );
        
        if ($result !== false) {
            return array(
                'success' => true,
                'message' => 'Utente aggiornato con successo'
            );
        } else {
            return array(
                'success' => false,
                'message' => 'Errore nell\'aggiornamento dell\'utente'
            );
        }
    }
    
    /**
     * Elimina utente
     */
    public static function delete_user($user_id) {
        global $wpdb;
        
        $table_users = $wpdb->prefix . 'naval_egt_users';
        
        // Ottieni info utente prima di eliminarlo
        $user = self::get_user_by_id($user_id);
        
        if (!$user) {
            return array(
                'success' => false,
                'message' => 'Utente non trovato'
            );
        }
        
        $result = $wpdb->delete(
            $table_users,
            array('id' => $user_id),
            array('%d')
        );
        
        if ($result) {
            // Log eliminazione
            Naval_EGT_Activity_Logger::log_activity(
                $user_id,
                $user['user_code'],
                'ADMIN_ACTION',
                null,
                null,
                0,
                array('action' => 'user_deleted', 'deleted_by' => 'admin')
            );
            
            return array(
                'success' => true,
                'message' => 'Utente eliminato con successo'
            );
        } else {
            return array(
                'success' => false,
                'message' => 'Errore nell\'eliminazione dell\'utente'
            );
        }
    }
    
    /**
     * Verifica credenziali utente
     */
    public static function verify_credentials($username_or_email, $password) {
        global $wpdb;
        
        $table_users = $wpdb->prefix . 'naval_egt_users';
        
        // Cerca per username o email
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_users WHERE (username = %s OR email = %s) AND status = 'ATTIVO'",
            $username_or_email,
            $username_or_email
        ), ARRAY_A);
        
        if (!$user) {
            return array(
                'success' => false,
                'message' => 'Credenziali non valide'
            );
        }
        
        // Verifica password
        if (wp_check_password($password, $user['password'])) {
            // Aggiorna ultimo login
            $wpdb->update(
                $table_users,
                array('last_login' => current_time('mysql')),
                array('id' => $user['id']),
                array('%s'),
                array('%d')
            );
            
            // Log login
            Naval_EGT_Activity_Logger::log_activity(
                $user['id'],
                $user['user_code'],
                'LOGIN'
            );
            
            return array(
                'success' => true,
                'user' => $user,
                'message' => 'Login effettuato con successo'
            );
        } else {
            return array(
                'success' => false,
                'message' => 'Credenziali non valide'
            );
        }
    }
    
    /**
     * Ottieni utente corrente dalla sessione
     */
    public static function get_current_user() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (isset($_SESSION['naval_egt_user_id'])) {
            return self::get_user_by_id($_SESSION['naval_egt_user_id']);
        }
        
        return null;
    }
    
    /**
     * Imposta utente corrente nella sessione
     */
    public static function set_current_user($user) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION['naval_egt_user_id'] = $user['id'];
        $_SESSION['naval_egt_user_code'] = $user['user_code'];
    }
    
    /**
     * Logout utente
     */
    public static function logout_user() {
        $current_user = self::get_current_user();
        
        if ($current_user) {
            // Log logout
            Naval_EGT_Activity_Logger::log_activity(
                $current_user['id'],
                $current_user['user_code'],
                'LOGOUT'
            );
        }
        
        if (session_status() !== PHP_SESSION_NONE) {
            session_destroy();
        }
        
        return array(
            'success' => true,
            'message' => 'Logout effettuato con successo'
        );
    }
    
    /**
     * Verifica se codice utente è unico
     */
    public static function is_user_code_unique($user_code, $exclude_user_id = null) {
        global $wpdb;
        
        $table_users = $wpdb->prefix . 'naval_egt_users';
        
        $sql = "SELECT COUNT(*) FROM $table_users WHERE user_code = %s";
        $params = array($user_code);
        
        if ($exclude_user_id) {
            $sql .= " AND id != %d";
            $params[] = $exclude_user_id;
        }
        
        $count = $wpdb->get_var($wpdb->prepare($sql, $params));
        
        return $count == 0;
    }
    
    /**
     * Verifica se email è unica
     */
    public static function is_email_unique($email, $exclude_user_id = null) {
        global $wpdb;
        
        $table_users = $wpdb->prefix . 'naval_egt_users';
        
        $sql = "SELECT COUNT(*) FROM $table_users WHERE email = %s";
        $params = array($email);
        
        if ($exclude_user_id) {
            $sql .= " AND id != %d";
            $params[] = $exclude_user_id;
        }
        
        $count = $wpdb->get_var($wpdb->prepare($sql, $params));
        
        return $count == 0;
    }
    
    /**
     * Ottieni statistiche utenti
     */
    public static function get_user_statistics() {
        global $wpdb;
        
        $table_users = $wpdb->prefix . 'naval_egt_users';
        
        return array(
            'total' => $wpdb->get_var("SELECT COUNT(*) FROM $table_users"),
            'active' => $wpdb->get_var("SELECT COUNT(*) FROM $table_users WHERE status = 'ATTIVO'"),
            'suspended' => $wpdb->get_var("SELECT COUNT(*) FROM $table_users WHERE status = 'SOSPESO'"),
            'recent_registrations' => $wpdb->get_var("SELECT COUNT(*) FROM $table_users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"),
            'recent_logins' => $wpdb->get_var("SELECT COUNT(*) FROM $table_users WHERE last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY)")
        );
    }
}

?>