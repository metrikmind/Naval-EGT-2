<?php
/**
 * Classe per la gestione degli utenti Naval EGT - VERSIONE CORRETTA CON FIX LOGIN
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
        // Assicurati che le sessioni siano avviate presto
        add_action('init', array($this, 'ensure_session_started'), 1);
        add_action('wp_loaded', array($this, 'ensure_session_started'), 1);
    }
    
    /**
     * Assicura che la sessione sia avviata
     */
    public function ensure_session_started() {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
            error_log('Naval EGT: Sessione avviata - Session ID: ' . session_id());
        }
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
     * Ottieni utente per username
     */
    public static function get_user_by_username($username) {
        global $wpdb;
        
        $table_users = $wpdb->prefix . 'naval_egt_users';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_users WHERE username = %s",
            $username
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
     * Genera codice utente unico
     */
    public static function generate_user_code() {
        global $wpdb;
        $table_users = $wpdb->prefix . 'naval_egt_users';
        
        // Trova l'ultimo codice utilizzato
        $last_code = $wpdb->get_var("SELECT MAX(CAST(user_code AS UNSIGNED)) FROM $table_users WHERE user_code REGEXP '^[0-9]+$'");
        
        // Se non ci sono codici, inizia da 100001
        if (!$last_code || $last_code < 100001) {
            $next_code = 100001;
        } else {
            $next_code = $last_code + 1;
        }
        
        // Assicurati che sia unico
        while (!self::is_user_code_unique($next_code)) {
            $next_code++;
        }
        
        return (string)$next_code;
    }
    
    /**
     * Aggiorna status utente
     */
    public static function update_user_status($user_id, $status) {
        global $wpdb;
        
        $table_users = $wpdb->prefix . 'naval_egt_users';
        
        // Ottieni dati utente precedenti
        $old_user = self::get_user_by_id($user_id);
        if (!$old_user) {
            return array('success' => false, 'message' => 'Utente non trovato');
        }
        
        $old_status = $old_user['status'];
        
        // Aggiorna status
        $result = $wpdb->update(
            $table_users,
            array(
                'status' => $status,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $user_id),
            array('%s', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            // Se cambia da SOSPESO ad ATTIVO, invia email di attivazione
            if ($old_status === 'SOSPESO' && $status === 'ATTIVO') {
                $updated_user = self::get_user_by_id($user_id);
                
                try {
                    Naval_EGT_Email::send_activation_email($updated_user);
                } catch (Exception $e) {
                    error_log('Naval EGT: Errore invio email attivazione: ' . $e->getMessage());
                }
            }
            
            return array(
                'success' => true,
                'message' => 'Status utente aggiornato con successo'
            );
        } else {
            return array(
                'success' => false,
                'message' => 'Errore nell\'aggiornamento dello status'
            );
        }
    }
    
    /**
     * Crea nuovo utente - VERSIONE CORRETTA
     */
    public static function create_user($user_data) {
        global $wpdb;
        
        error_log('Naval EGT User Manager: === INIZIO CREAZIONE UTENTE ===');
        error_log('Naval EGT User Manager: Dati ricevuti: ' . json_encode(array_keys($user_data)));
        
        // Validazioni complete
        $errors = self::validate_user_data($user_data);
        if (!empty($errors)) {
            error_log('Naval EGT User Manager: Errori validazione: ' . implode(', ', $errors));
            return array('success' => false, 'message' => implode(', ', $errors));
        }
        
        $table_users = $wpdb->prefix . 'naval_egt_users';
        
        // Genera codice utente unico
        if (empty($user_data['user_code'])) {
            $user_data['user_code'] = self::generate_user_code();
            error_log('Naval EGT User Manager: Codice utente generato: ' . $user_data['user_code']);
        }
        
        // Hash password se fornita
        if (!empty($user_data['password'])) {
            $user_data['password'] = wp_hash_password($user_data['password']);
            error_log('Naval EGT User Manager: Password hashata con successo');
        }
        
        // Imposta status default come SOSPESO
        if (empty($user_data['status'])) {
            $manual_activation = Naval_EGT_Database::get_setting('manual_user_activation', '1');
            $user_data['status'] = ($manual_activation === '1') ? 'SOSPESO' : 'ATTIVO';
            error_log('Naval EGT User Manager: Status impostato: ' . $user_data['status']);
        }
        
        // Imposta timestamp
        $user_data['created_at'] = current_time('mysql');
        $user_data['updated_at'] = current_time('mysql');
        
        // Salva i dati originali per le email
        $email_data = array(
            'nome' => $user_data['nome'],
            'cognome' => $user_data['cognome'],
            'email' => $user_data['email'],
            'username' => $user_data['username'],
            'user_code' => $user_data['user_code'],
            'telefono' => isset($user_data['telefono']) ? $user_data['telefono'] : '',
            'ragione_sociale' => isset($user_data['ragione_sociale']) ? $user_data['ragione_sociale'] : '',
            'partita_iva' => isset($user_data['partita_iva']) ? $user_data['partita_iva'] : '',
            'status' => $user_data['status']
        );
        
        // Inserimento nel database
        $result = $wpdb->insert($table_users, $user_data);
        
        if ($result === false) {
            $db_error = $wpdb->last_error;
            error_log('Naval EGT User Manager: Errore inserimento database: ' . $db_error);
            return array(
                'success' => false,
                'message' => 'Errore nella creazione dell\'utente: ' . $db_error
            );
        }
        
        $user_id = $wpdb->insert_id;
        $email_data['user_id'] = $user_id;
        
        error_log('Naval EGT User Manager: Utente inserito con ID: ' . $user_id);
        
        // Invio email
        try {
            // Avvia output buffering per catturare eventuali output delle email
            ob_start();
            
            Naval_EGT_Email::send_welcome_email($email_data);
            Naval_EGT_Email::send_admin_notification($email_data);
            
            // Scarta qualsiasi output generato dalle email
            ob_end_clean();
            
        } catch (Exception $e) {
            // Pulisci il buffer in caso di errore
            if (ob_get_level()) {
                ob_end_clean();
            }
            error_log('Naval EGT User Manager: Errore invio email: ' . $e->getMessage());
        }
        
        // Log attività
        try {
            Naval_EGT_Activity_Logger::log_activity(
                $user_id,
                $user_data['user_code'],
                'REGISTRATION',
                null,
                null,
                0,
                array(
                    'created_by' => 'user_registration',
                    'status' => $user_data['status'],
                    'email' => $user_data['email']
                )
            );
        } catch (Exception $e) {
            error_log('Naval EGT User Manager: Errore log attività: ' . $e->getMessage());
        }
        
        error_log('Naval EGT User Manager: === UTENTE CREATO CON SUCCESSO ===');
        
        return array(
            'success' => true,
            'user_id' => $user_id,
            'user_code' => $user_data['user_code'],
            'message' => 'Utente creato con successo'
        );
    }
    
    /**
     * Valida dati utente
     */
    private static function validate_user_data($data, $user_id = null) {
        $errors = array();
        
        // Campi obbligatori
        if (empty($data['nome'])) {
            $errors[] = 'Il nome è obbligatorio';
        }
        
        if (empty($data['cognome'])) {
            $errors[] = 'Il cognome è obbligatorio';
        }
        
        if (empty($data['email']) || !is_email($data['email'])) {
            $errors[] = 'Email non valida';
        }
        
        if (empty($data['username'])) {
            $errors[] = 'Lo username è obbligatorio';
        }
        
        // Validazione password solo per nuovi utenti o se specificata
        if (empty($user_id) && (empty($data['password']) || strlen($data['password']) < 6)) {
            $errors[] = 'La password deve essere di almeno 6 caratteri';
        }
        
        // Verifica unicità email
        if (!empty($data['email'])) {
            $existing_email = self::get_user_by_email($data['email']);
            if ($existing_email && $existing_email['id'] != ($user_id ?? 0)) {
                $errors[] = 'Email già esistente';
            }
        }
        
        // Verifica unicità username
        if (!empty($data['username'])) {
            $existing_username = self::get_user_by_username($data['username']);
            if ($existing_username && $existing_username['id'] != ($user_id ?? 0)) {
                $errors[] = 'Username già esistente';
            }
        }
        
        // Se c'è ragione sociale, P.IVA è obbligatoria
        if (!empty($data['ragione_sociale']) && empty($data['partita_iva'])) {
            $errors[] = 'La partita IVA è obbligatoria se si specifica la ragione sociale';
        }
        
        return $errors;
    }
    
    /**
     * Aggiorna utente - VERSIONE CORRETTA
     */
    public static function update_user($user_id, $user_data) {
        global $wpdb;
        
        error_log('Naval EGT User Manager: === AGGIORNAMENTO UTENTE ID: ' . $user_id . ' ===');
        
        $table_users = $wpdb->prefix . 'naval_egt_users';
        
        // Ottieni dati utente precedenti per confronto
        $old_user = self::get_user_by_id($user_id);
        if (!$old_user) {
            return array('success' => false, 'message' => 'Utente non trovato');
        }
        
        $old_status = $old_user['status'];
        
        // Hash password se fornita
        if (!empty($user_data['password'])) {
            $user_data['password'] = wp_hash_password($user_data['password']);
            error_log('Naval EGT User Manager: Password aggiornata e hashata per utente ID: ' . $user_id);
        }
        
        $user_data['updated_at'] = current_time('mysql');
        
        // Aggiornamento database
        $result = $wpdb->update(
            $table_users,
            $user_data,
            array('id' => $user_id),
            null,
            array('%d')
        );
        
        if ($result === false) {
            error_log('Naval EGT User Manager: ERRORE aggiornamento database: ' . $wpdb->last_error);
            return array(
                'success' => false,
                'message' => 'Errore nell\'aggiornamento dell\'utente'
            );
        }
        
        // Controlla se lo status è cambiato da SOSPESO ad ATTIVO
        if (isset($user_data['status']) && 
            $old_status === 'SOSPESO' && 
            $user_data['status'] === 'ATTIVO') {
            
            error_log('Naval EGT User Manager: Rilevato cambio status da SOSPESO ad ATTIVO - invio email attivazione');
            
            // Ottieni dati utente aggiornati per email
            $updated_user = self::get_user_by_id($user_id);
            
            try {
                $activation_sent = Naval_EGT_Email::send_activation_email($updated_user);
                error_log('Naval EGT User Manager: Email attivazione inviata: ' . ($activation_sent ? 'SUCCESS' : 'FAILED'));
                
                Naval_EGT_Activity_Logger::log_activity(
                    $user_id,
                    $updated_user['user_code'],
                    'ADMIN_ACTION',
                    null,
                    null,
                    0,
                    array(
                        'action' => 'user_activated',
                        'previous_status' => $old_status,
                        'new_status' => $user_data['status'],
                        'email_sent' => $activation_sent
                    )
                );
                
            } catch (Exception $e) {
                error_log('Naval EGT User Manager: ERRORE invio email attivazione: ' . $e->getMessage());
            }
        }
        
        error_log('Naval EGT User Manager: Aggiornamento completato con successo');
        
        return array(
            'success' => true,
            'message' => 'Utente aggiornato con successo'
        );
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
     * Autenticazione utente - VERSIONE CORRETTA E SEMPLIFICATA CON DEBUG AVANZATO
     */
    public static function authenticate($username_or_email, $password) {
        global $wpdb;
        
        error_log('Naval EGT User Manager: === INIZIO AUTENTICAZIONE ===');
        error_log('Naval EGT User Manager: Username/Email: ' . $username_or_email);
        error_log('Naval EGT User Manager: Password length: ' . strlen($password));
        error_log('Naval EGT User Manager: Password primi 3 caratteri: ' . substr($password, 0, 3) . '***');
        
        $table_users = $wpdb->prefix . 'naval_egt_users';
        
        // Cerca per username o email
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_users WHERE (username = %s OR email = %s) AND status != 'CANCELLATO'",
            $username_or_email,
            $username_or_email
        ), ARRAY_A);
        
        if (!$user) {
            error_log('Naval EGT User Manager: ERRORE - Utente non trovato per: ' . $username_or_email);
            return array(
                'success' => false,
                'message' => 'Credenziali non valide'
            );
        }
        
        error_log('Naval EGT User Manager: Utente trovato - ID: ' . $user['id'] . ', Username: ' . $user['username'] . ', Status: ' . $user['status']);
        
        // Verifica status
        if ($user['status'] !== 'ATTIVO') {
            $status_messages = array(
                'SOSPESO' => 'Account non ancora attivato. Contatta l\'amministratore.',
                'IN_ATTESA' => 'Account in attesa di attivazione',
                'SCADUTO' => 'Account scaduto'
            );
            
            $message = $status_messages[$user['status']] ?? 'Account non attivo. Contatta l\'amministratore.';
            
            error_log('Naval EGT User Manager: Account non attivo - Status: ' . $user['status']);
            return array(
                'success' => false,
                'message' => $message
            );
        }
        
        // VERIFICA PASSWORD - VERSIONE CON DEBUG DETTAGLIATO
        $stored_password = $user['password'];
        $password_valid = false;
        
        error_log('Naval EGT User Manager: === DEBUG PASSWORD ===');
        error_log('Naval EGT User Manager: Stored password length: ' . strlen($stored_password));
        error_log('Naval EGT User Manager: Stored password primi 10 caratteri: ' . substr($stored_password, 0, 10) . '...');
        error_log('Naval EGT User Manager: Stored password inizia con $: ' . (substr($stored_password, 0, 1) === '$' ? 'SI' : 'NO'));
        
        // Test 1: wp_check_password (per password hashate con WordPress)
        $wp_check_result = wp_check_password($password, $stored_password, $user['id']);
        error_log('Naval EGT User Manager: wp_check_password result: ' . ($wp_check_result ? 'SUCCESS' : 'FAIL'));
        
        if ($wp_check_result) {
            $password_valid = true;
            error_log('Naval EGT User Manager: Password valida con wp_check_password');
        } else {
            // Test 2: Confronto diretto (per password in chiaro)
            $direct_compare = ($password === $stored_password);
            error_log('Naval EGT User Manager: Direct compare result: ' . ($direct_compare ? 'SUCCESS' : 'FAIL'));
            
            if ($direct_compare) {
                $password_valid = true;
                error_log('Naval EGT User Manager: Password valida con confronto diretto - aggiornamento hash');
                
                // Aggiorna la password con hash per i login futuri
                $hashed_password = wp_hash_password($password);
                $wpdb->update(
                    $table_users,
                    array('password' => $hashed_password),
                    array('id' => $user['id']),
                    array('%s'),
                    array('%d')
                );
                error_log('Naval EGT User Manager: Password hashata e salvata nel database');
            } else {
                // Test 3: password_verify (per hash PHP standard)
                if (function_exists('password_verify') && password_verify($password, $stored_password)) {
                    $password_valid = true;
                    error_log('Naval EGT User Manager: Password valida con password_verify');
                } else {
                    // Test 4: md5 (per password legacy md5)
                    if (md5($password) === $stored_password) {
                        $password_valid = true;
                        error_log('Naval EGT User Manager: Password valida con MD5 - aggiornamento hash');
                        
                        // Aggiorna con hash sicuro
                        $hashed_password = wp_hash_password($password);
                        $wpdb->update(
                            $table_users,
                            array('password' => $hashed_password),
                            array('id' => $user['id']),
                            array('%s'),
                            array('%d')
                        );
                    }
                }
            }
        }
        
        error_log('Naval EGT User Manager: === RISULTATO VERIFICA PASSWORD ===');
        error_log('Naval EGT User Manager: Password valida: ' . ($password_valid ? 'SI' : 'NO'));
        
        if (!$password_valid) {
            error_log('Naval EGT User Manager: ERRORE - Password errata per utente: ' . $username_or_email);
            
            // Debug finale per troubleshooting
            error_log('Naval EGT User Manager: === DEBUG FINALE ===');
            error_log('Naval EGT User Manager: Password inserita (base64): ' . base64_encode($password));
            error_log('Naval EGT User Manager: Password stored (base64): ' . base64_encode($stored_password));
            error_log('Naval EGT User Manager: Caratteri speciali nella password: ' . (preg_match('/[^a-zA-Z0-9]/', $password) ? 'SI' : 'NO'));
            
            return array(
                'success' => false,
                'message' => 'Credenziali non valide'
            );
        }
        
        // Password valida - procedi con login
        error_log('Naval EGT User Manager: Password verificata con successo');
        
        // Aggiorna ultimo login
        $wpdb->update(
            $table_users,
            array('last_login' => current_time('mysql')),
            array('id' => $user['id']),
            array('%s'),
            array('%d')
        );
        
        // Gestione sessione
        self::set_current_user($user);
        
        // Verifica che la sessione sia stata impostata correttamente
        $verify_user = self::get_current_user();
        if ($verify_user && $verify_user['id'] == $user['id']) {
            error_log('Naval EGT User Manager: Sessione impostata correttamente per utente ID: ' . $user['id']);
        } else {
            error_log('Naval EGT User Manager: ERRORE - Sessione non impostata correttamente!');
        }
        
        // Log login
        try {
            Naval_EGT_Activity_Logger::log_activity(
                $user['id'],
                $user['user_code'],
                'LOGIN'
            );
        } catch (Exception $e) {
            error_log('Naval EGT User Manager: Errore log attività login: ' . $e->getMessage());
        }
        
        error_log('Naval EGT User Manager: === LOGIN COMPLETATO CON SUCCESSO ===');
        
        return array(
            'success' => true,
            'user' => $user,
            'message' => 'Login effettuato con successo'
        );
    }
    
    /**
     * Verifica credenziali utente (compatibilità)
     */
    public static function verify_credentials($username_or_email, $password) {
        return self::authenticate($username_or_email, $password);
    }
    
    /**
     * Ottieni utente corrente dalla sessione
     */
    public static function get_current_user() {
        // Assicurati che la sessione sia avviata
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        
        if (isset($_SESSION['naval_egt_user_id'])) {
            $user_id = $_SESSION['naval_egt_user_id'];
            
            $user = self::get_user_by_id($user_id);
            
            if ($user && $user['status'] === 'ATTIVO') {
                return $user;
            } else {
                // Pulisci sessione se utente non valido
                self::clear_session();
            }
        }
        
        return null;
    }
    
    /**
     * Imposta utente corrente nella sessione
     */
    public static function set_current_user($user) {
        // Assicurati che la sessione sia avviata
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        
        error_log('Naval EGT User Manager: === SET CURRENT USER ===');
        error_log('Naval EGT User Manager: Impostando utente: ' . $user['nome'] . ' ' . $user['cognome'] . ' (ID: ' . $user['id'] . ')');
        error_log('Naval EGT User Manager: Session ID: ' . session_id());
        
        // Imposta dati sessione
        $_SESSION['naval_egt_user_id'] = $user['id'];
        $_SESSION['naval_egt_user_code'] = $user['user_code'];
        $_SESSION['naval_egt_user_data'] = $user;
        
        error_log('Naval EGT User Manager: Sessione impostata con successo');
    }
    
    /**
     * Verifica se utente è loggato
     */
    public static function is_logged_in() {
        $current_user = self::get_current_user();
        return !empty($current_user);
    }
    
    /**
     * Pulisce la sessione
     */
    private static function clear_session() {
        if (session_status() !== PHP_SESSION_NONE) {
            unset($_SESSION['naval_egt_user_id']);
            unset($_SESSION['naval_egt_user_code']);
            unset($_SESSION['naval_egt_user_data']);
        }
    }
    
    /**
     * Logout utente
     */
    public static function logout() {
        error_log('Naval EGT User Manager: === LOGOUT ===');
        
        $current_user = self::get_current_user();
        
        if ($current_user) {
            // Log logout
            try {
                Naval_EGT_Activity_Logger::log_activity(
                    $current_user['id'],
                    $current_user['user_code'],
                    'LOGOUT'
                );
            } catch (Exception $e) {
                error_log('Naval EGT User Manager: Errore log attività logout: ' . $e->getMessage());
            }
            
            error_log('Naval EGT User Manager: Logout per utente: ' . $current_user['nome'] . ' ' . $current_user['cognome']);
        }
        
        // Pulisci sessione
        self::clear_session();
        
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
        
        $stats = array();
        
        // Statistiche base
        $stats['total'] = (int)$wpdb->get_var("SELECT COUNT(*) FROM $table_users");
        $stats['active'] = (int)$wpdb->get_var("SELECT COUNT(*) FROM $table_users WHERE status = 'ATTIVO'");
        $stats['suspended'] = (int)$wpdb->get_var("SELECT COUNT(*) FROM $table_users WHERE status = 'SOSPESO'");
        
        // Aggiungi pending_users per compatibilità con dashboard
        $stats['pending_users'] = $stats['suspended'];
        
        $stats['recent_registrations'] = (int)$wpdb->get_var("SELECT COUNT(*) FROM $table_users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $stats['recent_logins'] = (int)$wpdb->get_var("SELECT COUNT(*) FROM $table_users WHERE last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        
        // Statistiche file se la tabella esiste
        $table_files = $wpdb->prefix . 'naval_egt_files';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_files'")) {
            $stats['total_files'] = (int)$wpdb->get_var("SELECT COUNT(*) FROM $table_files");
        } else {
            $stats['total_files'] = 0;
        }
        
        return $stats;
    }
    
    /**
     * Funzione di debug per testare password - VERSIONE MIGLIORATA
     */
    public static function debug_password($username_or_email, $test_password) {
        global $wpdb;
        $table_users = $wpdb->prefix . 'naval_egt_users';
        
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT id, username, email, password, status FROM $table_users WHERE (username = %s OR email = %s)",
            $username_or_email, $username_or_email
        ), ARRAY_A);
        
        if (!$user) {
            return array('error' => 'Utente non trovato');
        }
        
        $stored_password = $user['password'];
        
        $debug_info = array(
            'user_id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'status' => $user['status'],
            'password_length' => strlen($stored_password),
            'password_preview' => substr($stored_password, 0, 20) . '...',
            'password_starts_with_dollar' => substr($stored_password, 0, 1) === '$',
            'test_password_length' => strlen($test_password),
            'wp_check_password_result' => wp_check_password($test_password, $stored_password, $user['id']),
            'direct_compare_result' => ($test_password === $stored_password),
            'md5_compare_result' => (md5($test_password) === $stored_password),
            'password_hash_info' => function_exists('password_get_info') ? password_get_info($stored_password) : 'N/A',
            'password_verify_result' => function_exists('password_verify') ? password_verify($test_password, $stored_password) : 'N/A'
        );
        
        error_log('Naval EGT Debug Password: ' . json_encode($debug_info));
        return $debug_info;
    }
    
    /**
     * Funzione per forzare reset password di un utente - UTILITÀ DI DEBUG
     */
    public static function force_reset_password($username_or_email, $new_password) {
        global $wpdb;
        $table_users = $wpdb->prefix . 'naval_egt_users';
        
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT id, username, email FROM $table_users WHERE (username = %s OR email = %s)",
            $username_or_email, $username_or_email
        ), ARRAY_A);
        
        if (!$user) {
            return array('success' => false, 'message' => 'Utente non trovato');
        }
        
        $hashed_password = wp_hash_password($new_password);
        
        $result = $wpdb->update(
            $table_users,
            array('password' => $hashed_password),
            array('id' => $user['id']),
            array('%s'),
            array('%d')
        );
        
        if ($result !== false) {
            error_log("Naval EGT: Password forzata per utente {$user['username']} (ID: {$user['id']})");
            return array(
                'success' => true, 
                'message' => "Password aggiornata per {$user['username']}",
                'user_id' => $user['id']
            );
        } else {
            return array('success' => false, 'message' => 'Errore aggiornamento password');
        }
    }
}
?>