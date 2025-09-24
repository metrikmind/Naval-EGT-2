<?php
/**
 * Plugin Name: Naval EGT - Area Riservata Clienti
 * Plugin URI: https://metrikmind.it
 * Description: Plugin completo per gestione area riservata clienti con integrazione Dropbox
 * Version: 1.0.23
 * Author: Metrikmind
 * Author URI: https://metrikmind.it
 * Requires at least: 6.8.2
 * Tested up to: 6.8.2
 * License: GPL v2 or later
 * Text Domain: naval-egt
 * Domain Path: /languages
 */

// Prevenire accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

// Definire costanti del plugin
define('NAVAL_EGT_VERSION', '1.0.23');
define('NAVAL_EGT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('NAVAL_EGT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('NAVAL_EGT_PLUGIN_FILE', __FILE__);

/**
 * Classe principale del plugin Naval EGT
 */
class Naval_EGT_Plugin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
        $this->load_dependencies();
    }
    
    /**
     * Inizializza gli hooks del plugin
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        register_uninstall_hook(__FILE__, array('Naval_EGT_Plugin', 'uninstall'));
        
        add_action('init', array($this, 'init'));
        add_action('admin_init', array($this, 'handle_dropbox_callback'));
        
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'frontend_enqueue_scripts'));
        
        add_action('wp_ajax_naval_egt_configure_dropbox', array($this, 'handle_dropbox_config'));
        add_shortcode('naval_egt_area_riservata', array($this, 'area_riservata_shortcode'));
        
        // Debug helper - RIMUOVERE IN PRODUZIONE
        add_action('wp_ajax_naval_egt_debug_password', array($this, 'debug_password'));
        add_action('wp_ajax_nopriv_naval_egt_debug_password', array($this, 'debug_password'));
        
        // Helper per forzare reset password - RIMUOVERE IN PRODUZIONE
        add_action('wp_ajax_naval_egt_force_reset_password', array($this, 'force_reset_password'));
    }
    
    /**
     * Carica le dipendenze del plugin
     */
    private function load_dependencies() {
        require_once NAVAL_EGT_PLUGIN_DIR . 'includes/class-naval-egt-database.php';
        require_once NAVAL_EGT_PLUGIN_DIR . 'includes/class-naval-egt-user-manager.php';
        require_once NAVAL_EGT_PLUGIN_DIR . 'includes/class-naval-egt-dropbox.php';
        require_once NAVAL_EGT_PLUGIN_DIR . 'includes/class-naval-egt-file-manager.php';
        require_once NAVAL_EGT_PLUGIN_DIR . 'includes/class-naval-egt-activity-logger.php';
        require_once NAVAL_EGT_PLUGIN_DIR . 'includes/class-naval-egt-email.php';
        require_once NAVAL_EGT_PLUGIN_DIR . 'includes/class-naval-egt-export.php';
        require_once NAVAL_EGT_PLUGIN_DIR . 'admin/class-naval-egt-admin.php';
        require_once NAVAL_EGT_PLUGIN_DIR . 'public/class-naval-egt-public.php';
    }
    
    /**
     * Inizializzazione del plugin
     */
    public function init() {
        load_plugin_textdomain('naval-egt', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // IMPORTANTE: Previeni output non desiderato durante le chiamate AJAX
        if (defined('DOING_AJAX') && DOING_AJAX) {
            $action = $_REQUEST['action'] ?? '';
            if (strpos($action, 'naval_egt_') === 0) {
                // Avvia output buffering per le chiamate AJAX del plugin
                ob_start();
            }
        }
        
        // Inizializza le classi principali
        Naval_EGT_Database::get_instance();
        Naval_EGT_User_Manager::get_instance();
        Naval_EGT_Dropbox::get_instance();
        Naval_EGT_File_Manager::get_instance();
        Naval_EGT_Activity_Logger::get_instance();
        Naval_EGT_Email::get_instance();
        Naval_EGT_Admin::get_instance();
        Naval_EGT_Public::get_instance();
        
        // Inizializza frontend pubblico
        Naval_EGT_Public::init();
    }
    
    /**
     * Debug password helper - VERSIONE MIGLIORATA
     */
    public function debug_password() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permessi insufficienti');
        }
        
        $username = sanitize_text_field($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            wp_send_json_error('Username e password richiesti');
        }
        
        error_log('Naval EGT Plugin: DEBUG PASSWORD REQUEST per utente: ' . $username);
        
        $debug_info = Naval_EGT_User_Manager::debug_password($username, $password);
        wp_send_json_success($debug_info);
    }
    
    /**
     * Helper per forzare reset password - VERSIONE MIGLIORATA
     */
    public function force_reset_password() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permessi insufficienti');
        }
        
        $username = sanitize_text_field($_POST['username'] ?? '');
        $new_password = $_POST['new_password'] ?? '';
        
        if (empty($username) || empty($new_password)) {
            wp_send_json_error('Username e nuova password richiesti');
        }
        
        error_log('Naval EGT Plugin: FORCE RESET PASSWORD per utente: ' . $username);
        
        $result = Naval_EGT_User_Manager::force_reset_password($username, $new_password);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * Gestisce la configurazione Dropbox via AJAX (ora automatica)
     */
    public function handle_dropbox_config() {
        check_ajax_referer('naval_egt_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permessi insufficienti');
        }
        
        // Configurazione automatica con credenziali integrate
        $dropbox = Naval_EGT_Dropbox::get_instance();
        $result = $dropbox->auto_configure();
        
        if ($result['success']) {
            wp_send_json_success(array(
                'message' => $result['message'],
                'auth_url' => $result['auth_url']
            ));
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * Gestisce il callback OAuth di Dropbox - VERSIONE CORRETTA
     */
    public function handle_dropbox_callback() {
        if (!is_admin() || !isset($_GET['dropbox_callback']) || $_GET['dropbox_callback'] !== '1') {
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Permessi insufficienti');
        }
        
        error_log('Naval EGT Plugin: Callback OAuth ricevuto - GET params: ' . print_r($_GET, true));
        
        // Gestisce errori OAuth
        if (isset($_GET['error'])) {
            $error_message = isset($_GET['error_description']) ? $_GET['error_description'] : $_GET['error'];
            error_log('Naval EGT Plugin: Errore OAuth ricevuto: ' . $error_message);
            
            add_action('admin_notices', function() use ($error_message) {
                echo '<div class="notice notice-error"><p><strong>Errore Dropbox:</strong> ' . esc_html($error_message) . '</p></div>';
            });
            return;
        }
        
        // Elabora il codice di autorizzazione
        if (isset($_GET['code'])) {
            $code = sanitize_text_field($_GET['code']);
            
            // Costruisce l'URL di redirect esatto
            $site_url = get_site_url();
            if (strpos($site_url, 'https://') !== 0) {
                $site_url = str_replace('http://', 'https://', $site_url);
            }
            $redirect_uri = $site_url . '/wp-admin/admin.php?page=naval-egt-settings&dropbox_callback=1';
            
            error_log('Naval EGT Plugin: Gestione callback OAuth - Code ricevuto: ' . substr($code, 0, 10) . '...');
            error_log('Naval EGT Plugin: Redirect URI utilizzato: ' . $redirect_uri);
            
            $dropbox = Naval_EGT_Dropbox::get_instance();
            $result = $dropbox->exchange_code_for_token($code, $redirect_uri);
            
            if ($result['success']) {
                error_log('Naval EGT Plugin: Token ottenuto con successo, test connessione...');
                
                // Forza il reload delle credenziali
                $dropbox->reload_credentials();
                
                // Breve pausa per assicurarsi che il token sia salvato
                usleep(500000); // 0.5 secondi
                
                // Test della connessione
                $account_info = $dropbox->get_account_info();
                if ($account_info['success']) {
                    add_action('admin_notices', function() use ($account_info) {
                        $name = isset($account_info['data']['name']['display_name']) ? $account_info['data']['name']['display_name'] : 'Utente';
                        $email = isset($account_info['data']['email']) ? $account_info['data']['email'] : '';
                        echo '<div class="notice notice-success is-dismissible">';
                        echo '<p><strong>🎉 Dropbox configurato con successo!</strong></p>';
                        echo '<p>👤 <strong>Account:</strong> ' . esc_html($name) . '</p>';
                        if ($email) {
                            echo '<p>📧 <strong>Email:</strong> ' . esc_html($email) . '</p>';
                        }
                        echo '<p>✅ La connessione è attiva e funzionante.</p>';
                        echo '</div>';
                    });
                    error_log('Naval EGT Plugin: Test connessione riuscito - Account: ' . (isset($account_info['data']['name']['display_name']) ? $account_info['data']['name']['display_name'] : 'N/A'));
                } else {
                    add_action('admin_notices', function() use ($account_info) {
                        echo '<div class="notice notice-warning is-dismissible">';
                        echo '<p><strong>⚠️ Token ottenuto ma test di connessione fallito.</strong></p>';
                        echo '<p><strong>Errore:</strong> ' . esc_html($account_info['message']) . '</p>';
                        echo '<p>Riprova la configurazione se il problema persiste.</p>';
                        echo '</div>';
                    });
                    error_log('Naval EGT Plugin: Test connessione fallito: ' . $account_info['message']);
                }
            } else {
                add_action('admin_notices', function() use ($result) {
                    echo '<div class="notice notice-error is-dismissible">';
                    echo '<p><strong>❌ Errore durante l\'ottenimento del token:</strong></p>';
                    echo '<p>' . esc_html($result['message']) . '</p>';
                    echo '<p>Verifica le credenziali Dropbox e riprova.</p>';
                    echo '</div>';
                });
                error_log('Naval EGT Plugin: Errore ottenimento token: ' . $result['message']);
            }
            
            // Redirect per pulire l'URL - con parametro di stato
            $redirect_url = admin_url('admin.php?page=naval-egt-settings&tab=dropbox');
            if ($result['success']) {
                $redirect_url .= '&auth_result=success';
            } else {
                $redirect_url .= '&auth_result=error';
            }
            
            error_log('Naval EGT Plugin: Redirect a: ' . $redirect_url);
            
            // Usa JavaScript per il redirect se gli header sono già stati inviati
            if (headers_sent()) {
                echo '<script type="text/javascript">window.location.href = "' . esc_url($redirect_url) . '";</script>';
                echo '<noscript><meta http-equiv="refresh" content="0; url=' . esc_url($redirect_url) . '"></noscript>';
                exit;
            } else {
                wp_redirect($redirect_url);
                exit;
            }
        } else {
            error_log('Naval EGT Plugin: Callback ricevuto senza codice di autorizzazione');
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible">';
                echo '<p><strong>Errore:</strong> Codice di autorizzazione non ricevuto da Dropbox.</p>';
                echo '<p>Riprova la configurazione.</p>';
                echo '</div>';
            });
        }
    }
    
    /**
     * Attivazione del plugin
     */
    public function activate() {
        error_log('Naval EGT Plugin: === ATTIVAZIONE PLUGIN ===');
        
        Naval_EGT_Database::create_tables();
        
        // Crea la pagina dell'area riservata se non esiste
        $page_exists = get_page_by_path('area-riservata-naval-egt');
        if (!$page_exists) {
            $page_id = wp_insert_post(array(
                'post_title' => 'Area Riservata Naval EGT',
                'post_content' => '[naval_egt_area_riservata]',
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_name' => 'area-riservata-naval-egt'
            ));
            error_log('Naval EGT Plugin: Pagina area riservata creata con ID: ' . $page_id);
        } else {
            error_log('Naval EGT Plugin: Pagina area riservata già esistente');
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        error_log('Naval EGT Plugin: Attivazione completata');
    }
    
    /**
     * Disattivazione del plugin
     */
    public function deactivate() {
        error_log('Naval EGT Plugin: === DISATTIVAZIONE PLUGIN ===');
        flush_rewrite_rules();
    }
    
    /**
     * Disinstallazione del plugin
     */
    public static function uninstall() {
        error_log('Naval EGT Plugin: === DISINSTALLAZIONE PLUGIN ===');
        
        Naval_EGT_Database::drop_tables();
        
        // Rimuove le opzioni del plugin
        delete_option('naval_egt_settings');
        delete_option('naval_egt_dropbox_access_token');
        delete_option('naval_egt_dropbox_refresh_token');
        
        // Rimuove la pagina dell'area riservata
        $page = get_page_by_path('area-riservata-naval-egt');
        if ($page) {
            wp_delete_post($page->ID, true);
            error_log('Naval EGT Plugin: Pagina area riservata eliminata');
        }
        
        error_log('Naval EGT Plugin: Disinstallazione completata');
    }
    
    /**
     * Enqueue scripts admin
     */
    public function admin_enqueue_scripts($hook) {
        if (strpos($hook, 'naval-egt') !== false) {
            wp_enqueue_script('naval-egt-admin-js', NAVAL_EGT_PLUGIN_URL . 'admin/js/admin.js', array('jquery'), NAVAL_EGT_VERSION, true);
            wp_enqueue_style('naval-egt-admin-css', NAVAL_EGT_PLUGIN_URL . 'admin/css/admin.css', array(), NAVAL_EGT_VERSION);
            
            wp_localize_script('naval-egt-admin-js', 'naval_egt_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('naval_egt_nonce'),
                'strings' => array(
                    'confirm_delete' => __('Sei sicuro di voler eliminare questo elemento?', 'naval-egt'),
                    'loading' => __('Caricamento...', 'naval-egt'),
                    'error' => __('Si è verificato un errore', 'naval-egt')
                )
            ));
        }
    }
    
    /**
     * Enqueue scripts frontend
     */
    public function frontend_enqueue_scripts() {
        global $post;
        
        if (is_page('area-riservata-naval-egt') || ($post && has_shortcode($post->post_content ?? '', 'naval_egt_area_riservata'))) {
            wp_enqueue_script('naval-egt-public-js', NAVAL_EGT_PLUGIN_URL . 'public/js/public.js', array('jquery'), NAVAL_EGT_VERSION, true);
            wp_enqueue_style('naval-egt-public-css', NAVAL_EGT_PLUGIN_URL . 'public/css/public.css', array(), NAVAL_EGT_VERSION);
            
            wp_localize_script('naval-egt-public-js', 'naval_egt_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('naval_egt_nonce'),
                'strings' => array(
                    'login_error' => __('Credenziali non valide', 'naval-egt'),
                    'loading' => __('Caricamento...', 'naval-egt'),
                    'upload_error' => __('Errore durante il caricamento', 'naval-egt')
                )
            ));
            
            error_log('Naval EGT Plugin: Scripts frontend caricati per pagina: ' . ($post ? $post->post_name : 'N/A'));
        }
    }
    
    /**
     * Shortcode per l'area riservata
     */
    public function area_riservata_shortcode($atts) {
        $atts = shortcode_atts(array(
            'template' => 'default'
        ), $atts, 'naval_egt_area_riservata');
        
        ob_start();
        
        // Assicurati che le sessioni siano avviate per lo shortcode
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        
        require_once NAVAL_EGT_PLUGIN_DIR . 'public/views/area-riservata.php';
        return ob_get_clean();
    }
}

// Inizializza il plugin
Naval_EGT_Plugin::get_instance();

// Aggiungi funzione helper per debug da console PHP (RIMUOVERE IN PRODUZIONE)
if (!function_exists('naval_egt_debug_user')) {
    function naval_egt_debug_user($username, $password) {
        if (class_exists('Naval_EGT_User_Manager')) {
            return Naval_EGT_User_Manager::debug_password($username, $password);
        }
        return array('error' => 'User Manager non disponibile');
    }
}

// Funzione per forzare reset password da console PHP (RIMUOVERE IN PRODUZIONE)
if (!function_exists('naval_egt_reset_password')) {
    function naval_egt_reset_password($username, $new_password) {
        if (class_exists('Naval_EGT_User_Manager')) {
            return Naval_EGT_User_Manager::force_reset_password($username, $new_password);
        }
        return array('error' => 'User Manager non disponibile');
    }
}
?>