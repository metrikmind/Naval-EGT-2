<?php
/**
 * Tab Gestione Utenti - Dashboard Admin (SENZA link File)
 */

if (!defined('ABSPATH')) {
    exit;
}

// Verifica permessi
if (!current_user_can('manage_options')) {
    wp_die(__('Non hai i permessi per accedere a questa pagina.'));
}

// Parametri per filtri e paginazione
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$per_page = 20;

// Costruisci filtri
$filters = array();
if (!empty($search)) {
    $filters['search'] = $search;
}
if (!empty($status_filter)) {
    $filters['status'] = $status_filter;
}

// Ottieni utenti
$offset = ($paged - 1) * $per_page;
$users = Naval_EGT_User_Manager::get_users($filters, $per_page, $offset);
$total_users = Naval_EGT_User_Manager::count_users($filters);
$total_pages = ceil($total_users / $per_page);
?>

<div class="users-management">
    
    <!-- Header sezione -->
    <div class="users-header">
        <h2>Gestione Utenti</h2>
        <div class="users-actions">
            <button type="button" id="add-user-btn" class="button button-primary">
                <span class="dashicons dashicons-plus"></span>
                Aggiungi Utente
            </button>
            <button type="button" id="refresh-users" class="button">
                <span class="dashicons dashicons-update"></span>
                Aggiorna
            </button>
        </div>
    </div>

    <!-- Filtri e ricerca -->
    <div class="users-filters">
        <form method="get" class="filters-form">
            <input type="hidden" name="page" value="naval-egt">
            <input type="hidden" name="tab" value="users">
            
            <div class="filter-group">
                <label for="user-search">Cerca Utenti:</label>
                <input type="text" id="user-search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Nome, cognome, email o azienda">
            </div>
            
            <div class="filter-group">
                <label for="status-filter">Status:</label>
                <select id="status-filter" name="status">
                    <option value="">Tutti gli status</option>
                    <option value="ATTIVO" <?php selected($status_filter, 'ATTIVO'); ?>>Solo Attivi</option>
                    <option value="SOSPESO" <?php selected($status_filter, 'SOSPESO'); ?>>Solo Sospesi</option>
                </select>
            </div>
            
            <div class="filter-actions">
                <button type="submit" class="button button-primary">Filtra</button>
                <?php if (!empty($search) || !empty($status_filter)): ?>
                    <a href="?page=naval-egt&tab=users" class="button">Pulisci Filtri</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Risultati e bulk actions -->
    <div class="users-results">
        <div class="results-info">
            <span class="results-count">
                <?php 
                if ($total_users > 0) {
                    printf('Visualizzazione %d-%d di %d utenti', 
                        $offset + 1, 
                        min($offset + $per_page, $total_users), 
                        $total_users
                    );
                } else {
                    echo 'Nessun utente trovato';
                }
                ?>
            </span>
        </div>
        
        <?php if (!empty($users)): ?>
        <div class="bulk-actions-top">
            <select id="bulk-action-selector-top" name="action">
                <option value="-1">Azioni di gruppo</option>
                <option value="activate">Attiva</option>
                <option value="suspend">Sospendi</option>
                <option value="delete">Elimina</option>
            </select>
            <button type="button" id="doaction" class="button">Applica</button>
        </div>
        <?php endif; ?>
    </div>

    <!-- Tabella utenti -->
    <div class="users-table-container">
        <?php if (!empty($users)): ?>
        <table class="wp-list-table widefat fixed striped users">
            <thead>
                <tr>
                    <td id="cb" class="manage-column column-cb check-column">
                        <input id="cb-select-all-1" type="checkbox">
                    </td>
                    <th scope="col" class="manage-column column-user-code">
                        <span>Codice</span>
                    </th>
                    <th scope="col" class="manage-column column-name">
                        <span>Nome Completo</span>
                    </th>
                    <th scope="col" class="manage-column column-email">
                        <span>Email</span>
                    </th>
                    <th scope="col" class="manage-column column-company">
                        <span>Azienda</span>
                    </th>
                    <th scope="col" class="manage-column column-status">
                        <span>Status</span>
                    </th>
                    <th scope="col" class="manage-column column-date">
                        <span>Registrato</span>
                    </th>
                    <th scope="col" class="manage-column column-actions">
                        <span>Azioni</span>
                    </th>
                </tr>
            </thead>
            
            <tbody id="users-table-body">
                <?php foreach ($users as $user): ?>
                <?php
                $status_class = $user['status'] === 'ATTIVO' ? 'status-active' : 'status-suspended';
                $last_login = $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : 'Mai';
                $created_at = date('d/m/Y H:i', strtotime($user['created_at']));
                $full_name = $user['nome'] . ' ' . $user['cognome'];
                ?>
                <tr data-user-id="<?php echo $user['id']; ?>">
                    <th scope="row" class="check-column">
                        <input type="checkbox" name="users[]" value="<?php echo $user['id']; ?>">
                    </th>
                    
                    <td class="user-code column-user-code">
                        <strong><?php echo esc_html($user['user_code']); ?></strong>
                    </td>
                    
                    <td class="name column-name">
                        <strong>
                            <a href="#" class="user-name-link" data-user-id="<?php echo $user['id']; ?>">
                                <?php echo esc_html($full_name); ?>
                            </a>
                        </strong>
                        <div class="row-actions">
                            <span class="edit">
                                <a href="#" class="btn-edit-user" data-user-id="<?php echo $user['id']; ?>">Modifica</a> |
                            </span>
                            <span class="status">
                                <a href="#" class="btn-toggle-status" data-user-id="<?php echo $user['id']; ?>" data-current-status="<?php echo $user['status']; ?>">
                                    <?php echo $user['status'] === 'ATTIVO' ? 'Sospendi' : 'Attiva'; ?>
                                </a> |
                            </span>
                            <!-- RIMOSSO: link File -->
                            <span class="delete">
                                <a href="#" class="btn-delete-user text-danger" data-user-id="<?php echo $user['id']; ?>" data-user-name="<?php echo esc_attr($full_name); ?>">Elimina</a>
                            </span>
                        </div>
                    </td>
                    
                    <td class="email column-email">
                        <a href="mailto:<?php echo esc_attr($user['email']); ?>">
                            <?php echo esc_html($user['email']); ?>
                        </a>
                        <?php if (!empty($user['telefono'])): ?>
                            <br><small>📞 <?php echo esc_html($user['telefono']); ?></small>
                        <?php endif; ?>
                    </td>
                    
                    <td class="company column-company">
                        <?php if (!empty($user['ragione_sociale'])): ?>
                            <strong><?php echo esc_html($user['ragione_sociale']); ?></strong>
                            <?php if (!empty($user['partita_iva'])): ?>
                                <br><small>P.IVA: <?php echo esc_html($user['partita_iva']); ?></small>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted">Privato</span>
                        <?php endif; ?>
                    </td>
                    
                    <td class="status column-status">
                        <span class="status-badge <?php echo $status_class; ?>">
                            <?php echo $user['status']; ?>
                        </span>
                        <?php if ($user['last_login']): ?>
                            <br><small title="Ultimo accesso">🕐 <?php echo $last_login; ?></small>
                        <?php endif; ?>
                    </td>
                    
                    <td class="date column-date">
                        <?php echo $created_at; ?>
                        <?php if (!empty($user['dropbox_folder'])): ?>
                            <br><small title="Cartella Dropbox collegata">📁 Collegata</small>
                        <?php else: ?>
                            <br><small class="text-warning" title="Cartella Dropbox non collegata">⚠️ Non collegata</small>
                        <?php endif; ?>
                    </td>
                    
                    <td class="actions column-actions">
                        <div class="action-buttons">
                            <button type="button" class="button-small btn-edit-user" data-user-id="<?php echo $user['id']; ?>" title="Modifica utente">
                                <span class="dashicons dashicons-edit"></span>
                            </button>
                            
                            <button type="button" class="button-small btn-toggle-status" data-user-id="<?php echo $user['id']; ?>" data-current-status="<?php echo $user['status']; ?>" title="<?php echo $user['status'] === 'ATTIVO' ? 'Sospendi utente' : 'Attiva utente'; ?>">
                                <span class="dashicons dashicons-<?php echo $user['status'] === 'ATTIVO' ? 'pause' : 'controls-play'; ?>"></span>
                            </button>
                            
                            <button type="button" class="button-small btn-view-logs" data-user-id="<?php echo $user['id']; ?>" title="Visualizza log attività">
                                <span class="dashicons dashicons-visibility"></span>
                            </button>
                            
                            <button type="button" class="button-small btn-delete-user text-danger" data-user-id="<?php echo $user['id']; ?>" data-user-name="<?php echo esc_attr($full_name); ?>" title="Elimina utente">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php else: ?>
        <div class="no-users-found">
            <div class="no-users-icon">👥</div>
            <h3>Nessun utente trovato</h3>
            <?php if (!empty($search) || !empty($status_filter)): ?>
                <p>Nessun utente corrisponde ai filtri applicati.</p>
                <a href="?page=naval-egt&tab=users" class="button">Rimuovi Filtri</a>
            <?php else: ?>
                <p>Non ci sono ancora utenti registrati nel sistema.</p>
                <button type="button" id="add-first-user" class="button button-primary">Aggiungi Primo Utente</button>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Paginazione -->
    <?php if ($total_pages > 1): ?>
    <div class="tablenav bottom">
        <div class="tablenav-pages">
            <span class="displaying-num"><?php printf('%d elementi', $total_users); ?></span>
            <span class="pagination-links">
                <?php
                $base_url = add_query_arg(array(
                    'page' => 'naval-egt',
                    'tab' => 'users',
                    's' => $search,
                    'status' => $status_filter
                ), admin_url('admin.php'));
                
                // Prima pagina
                if ($paged > 1): ?>
                    <a class="first-page button" href="<?php echo esc_url(add_query_arg('paged', 1, $base_url)); ?>">
                        <span aria-hidden="true">«</span>
                    </a>
                    <a class="prev-page button" href="<?php echo esc_url(add_query_arg('paged', max(1, $paged - 1), $base_url)); ?>">
                        <span aria-hidden="true">‹</span>
                    </a>
                <?php else: ?>
                    <span class="tablenav-pages-navspan button disabled" aria-hidden="true">«</span>
                    <span class="tablenav-pages-navspan button disabled" aria-hidden="true">‹</span>
                <?php endif; ?>
                
                <span class="paging-input">
                    <span class="current-page"><?php echo $paged; ?></span>
                    <span class="tablenav-paging-text"> di </span>
                    <span class="total-pages"><?php echo $total_pages; ?></span>
                </span>
                
                <?php if ($paged < $total_pages): ?>
                    <a class="next-page button" href="<?php echo esc_url(add_query_arg('paged', min($total_pages, $paged + 1), $base_url)); ?>">
                        <span aria-hidden="true">›</span>
                    </a>
                    <a class="last-page button" href="<?php echo esc_url(add_query_arg('paged', $total_pages, $base_url)); ?>">
                        <span aria-hidden="true">»</span>
                    </a>
                <?php else: ?>
                    <span class="tablenav-pages-navspan button disabled" aria-hidden="true">›</span>
                    <span class="tablenav-pages-navspan button disabled" aria-hidden="true">»</span>
                <?php endif; ?>
            </span>
        </div>
        
        <!-- Bulk actions bottom -->
        <?php if (!empty($users)): ?>
        <div class="alignleft actions bulkactions">
            <select id="bulk-action-selector-bottom" name="action2">
                <option value="-1">Azioni di gruppo</option>
                <option value="activate">Attiva</option>
                <option value="suspend">Sospendi</option>
                <option value="delete">Elimina</option>
            </select>
            <button type="button" id="doaction2" class="button">Applica</button>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>

<!-- Modal Aggiungi/Modifica Utente -->
<div id="user-modal" class="modal-overlay" style="display: none;">
    <div class="modal-container large">
        <div class="modal-header">
            <h3 id="user-modal-title">Aggiungi Nuovo Utente</h3>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <form id="user-form" class="naval-form">
                <div class="form-section">
                    <h4>Informazioni Personali</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="user-nome">Nome *</label>
                            <input type="text" id="user-nome" name="nome" required>
                        </div>
                        <div class="form-group">
                            <label for="user-cognome">Cognome *</label>
                            <input type="text" id="user-cognome" name="cognome" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="user-email">Email *</label>
                            <input type="email" id="user-email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label for="user-telefono">Telefono</label>
                            <input type="tel" id="user-telefono" name="telefono">
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h4>Credenziali Accesso</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="user-username">Username *</label>
                            <input type="text" id="user-username" name="username" required>
                        </div>
                        <div class="form-group">
                            <label for="user-password">Password</label>
                            <input type="password" id="user-password" name="password">
                            <small class="form-help password-help" style="display: none;">Lascia vuoto per non modificare</small>
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h4>Informazioni Aziendali (Opzionali)</h4>
                    <div class="form-group">
                        <label for="user-ragione-sociale">Ragione Sociale</label>
                        <input type="text" id="user-ragione-sociale" name="ragione_sociale">
                    </div>
                    <div class="form-group">
                        <label for="user-partita-iva">Partita IVA</label>
                        <input type="text" id="user-partita-iva" name="partita_iva">
                        <small class="form-help">Obbligatoria se specificata la Ragione Sociale</small>
                    </div>
                </div>
                
                <div class="form-section">
                    <h4>Impostazioni Account</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="user-status">Status</label>
                            <select id="user-status" name="status">
                                <option value="SOSPESO">Sospeso</option>
                                <option value="ATTIVO">Attivo</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="user-dropbox-folder">Cartella Dropbox</label>
                            <input type="text" id="user-dropbox-folder" name="dropbox_folder" placeholder="Lascia vuoto per auto-rilevamento">
                            <small class="form-help">Percorso completo cartella Dropbox (opzionale)</small>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="button button-primary" id="save-user-btn">
                        <span class="dashicons dashicons-yes"></span>
                        <span class="button-text">Salva Utente</span>
                    </button>
                    <button type="button" class="button btn-cancel">
                        <span class="dashicons dashicons-no-alt"></span>
                        Annulla
                    </button>
                </div>
                
                <input type="hidden" id="user-id" name="user_id" value="">
                <input type="hidden" id="user-code" name="user_code" value="">
            </form>
        </div>
    </div>
</div>

<style>
/* Stili per la gestione utenti */
.users-management {
    max-width: 100%;
}

.users-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #ddd;
}

.users-header h2 {
    margin: 0;
    color: #333;
}

.users-actions {
    display: flex;
    gap: 10px;
}

.users-actions .button {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    vertical-align: middle;
}

.users-actions .button .dashicons {
    margin-top: 0;
    margin-bottom: 0;
    line-height: 1;
    vertical-align: middle;
    font-size: 16px;
}

.users-filters {
    background: #f9f9f9;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.filters-form {
    display: flex;
    gap: 20px;
    align-items: flex-end;
    flex-wrap: wrap;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.filter-group label {
    font-weight: 600;
    color: #333;
    font-size: 13px;
}

.filter-group input,
.filter-group select {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    min-width: 150px;
    height: 36px;
}

.filter-actions {
    display: flex;
    gap: 10px;
    align-items: flex-end;
    height: 36px;
}

.filter-actions .button {
    height: 36px;
    display: inline-flex;
    align-items: center;
}

.users-results {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.results-count {
    color: #666;
    font-size: 14px;
}

.bulk-actions-top {
    display: flex;
    gap: 10px;
    align-items: center;
}

.users-table-container {
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    overflow: hidden;
}

.status-badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-active {
    background: #d4edda;
    color: #155724;
}

.status-suspended {
    background: #f8d7da;
    color: #721c24;
}

.action-buttons {
    display: flex;
    gap: 5px;
}

.action-buttons .button-small {
    padding: 4px 8px;
    border: 1px solid #ddd;
    background: #f9f9f9;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 12px;
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
}

.action-buttons .button-small:hover {
    background: #e9ecef;
    border-color: #adb5bd;
}

.action-buttons .button-small.text-danger:hover {
    background: #f5c6cb;
    border-color: #f1b0b7;
    color: #721c24;
}

.action-buttons .btn-toggle-status {
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
}

.action-buttons .btn-toggle-status .dashicons {
    display: inline-block !important;
    margin: 0;
    line-height: 1;
}

.user-name-link {
    text-decoration: none;
    color: #0073aa;
    font-weight: 600;
}

.user-name-link:hover {
    color: #005177;
}

.row-actions {
    color: #666;
    font-size: 13px;
}

.row-actions a {
    color: #0073aa;
    text-decoration: none;
}

.row-actions a:hover {
    color: #005177;
}

.row-actions .text-danger {
    color: #dc3545;
}

.row-actions .text-danger:hover {
    color: #c82333;
}

.no-users-found {
    text-align: center;
    padding: 60px 20px;
    color: #666;
}

.no-users-icon {
    font-size: 48px;
    margin-bottom: 20px;
}

.no-users-found h3 {
    margin: 0 0 15px 0;
    color: #333;
}

.text-muted {
    color: #6c757d;
}

.text-warning {
    color: #856404;
}

/* Modal Styles */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 999999;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-container {
    background: white;
    border-radius: 8px;
    max-width: 90vw;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
}

.modal-container.large {
    width: 800px;
}

.modal-header {
    padding: 20px 25px 15px;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    color: #333;
}

.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #666;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-close:hover {
    color: #333;
}

.modal-body {
    padding: 25px;
}

.form-section {
    margin-bottom: 25px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 6px;
}

.form-section h4 {
    margin: 0 0 15px 0;
    color: #4285f4;
    border-bottom: 1px solid #e9ecef;
    padding-bottom: 8px;
}

.form-row {
    display: flex;
    gap: 20px;
    margin-bottom: 15px;
}

.form-group {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.form-group label {
    font-weight: 600;
    color: #333;
}

.form-group input,
.form-group select {
    padding: 10px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: #4285f4;
    box-shadow: 0 0 0 2px rgba(66, 133, 244, 0.1);
}

.form-help {
    color: #6c757d;
    font-size: 12px;
    margin-top: 4px;
}

.form-actions {
    text-align: right;
    padding: 20px 0 0 0;
    border-top: 1px solid #e9ecef;
    margin-top: 20px;
}

.form-actions .button {
    margin-left: 10px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.form-actions .button .dashicons {
    font-size: 16px;
    line-height: 1;
}

/* Responsive */
@media (max-width: 768px) {
    .users-header {
        flex-direction: column;
        align-items: stretch;
        gap: 15px;
    }
    
    .users-filters {
        padding: 15px;
    }
    
    .filters-form {
        flex-direction: column;
        align-items: stretch;
        gap: 15px;
    }
    
    .filter-group input,
    .filter-group select {
        min-width: 100%;
    }
    
    .filter-actions {
        height: auto;
    }
    
    .users-results {
        flex-direction: column;
        align-items: stretch;
        gap: 15px;
    }
    
    .action-buttons {
        flex-wrap: wrap;
    }
    
    .form-row {
        flex-direction: column;
        gap: 15px;
    }
    
    .modal-container.large {
        width: 95vw;
    }
    
    .modal-body {
        padding: 15px;
    }
    
    .form-section {
        padding: 15px;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    
    // Gestione click aggiungi utente
    $('#add-user-btn, #add-first-user').on('click', function() {
        openAddUserModal();
    });
    
    // Gestione click modifica utente
    $(document).on('click', '.btn-edit-user, .user-name-link', function(e) {
        e.preventDefault();
        const userId = $(this).data('user-id');
        openEditUserModal(userId);
    });
    
    // Gestione toggle status
    $(document).on('click', '.btn-toggle-status', function(e) {
        e.preventDefault();
        const userId = $(this).data('user-id');
        const currentStatus = $(this).data('current-status');
        toggleUserStatus(userId, currentStatus);
    });
    
    // Gestione eliminazione utente
    $(document).on('click', '.btn-delete-user', function(e) {
        e.preventDefault();
        const userId = $(this).data('user-id');
        const userName = $(this).data('user-name');
        deleteUser(userId, userName);
    });
    
    // Gestione visualizza log
    $(document).on('click', '.btn-view-logs', function(e) {
        e.preventDefault();
        const userId = $(this).data('user-id');
        viewUserLogs(userId);
    });
    
    // Gestione refresh
    $('#refresh-users').on('click', function() {
        location.reload();
    });
    
    // Auto-submit filtri con delay
    let searchTimeout;
    $('#user-search').on('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            $('#user-search').closest('form').submit();
        }, 500);
    });
    
    $('#status-filter').on('change', function() {
        $(this).closest('form').submit();
    });
    
    // Gestione select all checkboxes
    $('#cb-select-all-1, #cb-select-all-2').on('change', function() {
        $('input[name="users[]"]').prop('checked', this.checked);
    });
    
    // Gestione bulk actions
    $('#doaction, #doaction2').on('click', function() {
        const action = $(this).prev('select').val();
        const selectedUsers = $('input[name="users[]"]:checked').map(function() {
            return this.value;
        }).get();
        
        if (action === '-1' || selectedUsers.length === 0) {
            alert('Seleziona un\'azione e almeno un utente.');
            return;
        }
        
        performBulkAction(action, selectedUsers);
    });
    
    // Funzioni modali
    function openAddUserModal() {
        $('#user-modal-title').text('Aggiungi Nuovo Utente');
        $('#user-form')[0].reset();
        $('#user-id').val('');
        $('#user-code').val('');
        $('#user-password').prop('required', true);
        $('.password-help').hide();
        $('#user-modal').fadeIn(200);
    }
    
    function openEditUserModal(userId) {
        $('#user-modal-title').text('Modifica Utente');
        
        // Mostra loading
        $('#user-form')[0].reset();
        $('#save-user-btn .button-text').text('Caricamento...');
        $('#save-user-btn').prop('disabled', true);
        $('#user-modal').fadeIn(200);
        
        // Carica dati utente via AJAX
        $.post(ajaxurl, {
            action: 'naval_egt_ajax',
            naval_action: 'get_user_data',
            nonce: naval_egt_ajax.nonce,
            user_id: userId
        }, function(response) {
            if (response.success) {
                const user = response.data;
                
                // Popola form
                $('#user-id').val(user.id);
                $('#user-code').val(user.user_code);
                $('#user-nome').val(user.nome);
                $('#user-cognome').val(user.cognome);
                $('#user-email').val(user.email);
                $('#user-telefono').val(user.telefono || '');
                $('#user-username').val(user.username);
                $('#user-ragione-sociale').val(user.ragione_sociale || '');
                $('#user-partita-iva').val(user.partita_iva || '');
                $('#user-status').val(user.status);
                $('#user-dropbox-folder').val(user.dropbox_folder || '');
                
                // Password non obbligatoria in modifica
                $('#user-password').prop('required', false);
                $('.password-help').show();
                
                $('#save-user-btn .button-text').text('Salva Modifiche');
                $('#save-user-btn').prop('disabled', false);
            } else {
                alert('Errore nel caricamento dei dati utente: ' + (response.data || 'Errore sconosciuto'));
                closeModal();
            }
        }).fail(function() {
            alert('Errore nella comunicazione con il server');
            closeModal();
        });
    }
    
    // Gestione submit form
    $('#user-form').on('submit', function(e) {
        e.preventDefault();
        saveUser();
    });
    
    // Gestione chiusura modal
    $('.modal-close, .btn-cancel').on('click', function() {
        closeModal();
    });
    
    // Chiudi modal cliccando fuori
    $('#user-modal').on('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });
    
    function closeModal() {
        $('#user-modal').fadeOut(200);
    }
    
    function saveUser() {
        const formData = {
            action: 'naval_egt_ajax',
            nonce: naval_egt_ajax.nonce,
            nome: $('#user-nome').val().trim(),
            cognome: $('#user-cognome').val().trim(),
            email: $('#user-email').val().trim(),
            telefono: $('#user-telefono').val().trim(),
            username: $('#user-username').val().trim(),
            password: $('#user-password').val(),
            ragione_sociale: $('#user-ragione-sociale').val().trim(),
            partita_iva: $('#user-partita-iva').val().trim(),
            status: $('#user-status').val(),
            dropbox_folder: $('#user-dropbox-folder').val().trim()
        };
        
        const userId = $('#user-id').val();
        if (userId) {
            formData.naval_action = 'update_user';
            formData.user_id = userId;
        } else {
            formData.naval_action = 'create_user';
        }
        
        // Validazione client-side
        if (!formData.nome || !formData.cognome || !formData.email || !formData.username) {
            alert('Nome, cognome, email e username sono obbligatori.');
            return;
        }
        
        if (!userId && !formData.password) {
            alert('La password è obbligatoria per i nuovi utenti.');
            return;
        }
        
        // Validazione ragione sociale / partita iva
        if (formData.ragione_sociale && !formData.partita_iva) {
            alert('Se specifichi la ragione sociale, la partita IVA è obbligatoria.');
            return;
        }
        
        // Disabilita form
        $('#user-form input, #user-form select, #user-form button').prop('disabled', true);
        $('#save-user-btn .button-text').text('Salvataggio...');
        
        $.post(ajaxurl, formData, function(response) {
            if (response.success) {
                closeModal();
                // Ricarica pagina per aggiornare la tabella
                location.reload();
            } else {
                alert('Errore: ' + (response.data || 'Errore sconosciuto'));
                // Riabilita form
                $('#user-form input, #user-form select, #user-form button').prop('disabled', false);
                $('#save-user-btn .button-text').text(userId ? 'Salva Modifiche' : 'Salva Utente');
            }
        }).fail(function() {
            alert('Errore nella comunicazione con il server');
            // Riabilita form
            $('#user-form input, #user-form select, #user-form button').prop('disabled', false);
            $('#save-user-btn .button-text').text(userId ? 'Salva Modifiche' : 'Salva Utente');
        });
    }
    
    function toggleUserStatus(userId, currentStatus) {
        const newStatus = currentStatus === 'ATTIVO' ? 'SOSPESO' : 'ATTIVO';
        const action = newStatus === 'ATTIVO' ? 'attivare' : 'sospendere';
        
        if (confirm(`Sei sicuro di voler ${action} questo utente?`)) {
            $.post(ajaxurl, {
                action: 'naval_egt_ajax',
                naval_action: 'toggle_user_status',
                nonce: naval_egt_ajax.nonce,
                user_id: userId,
                status: newStatus
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Errore: ' + (response.data || 'Errore sconosciuto'));
                }
            });
        }
    }
    
    function deleteUser(userId, userName) {
        if (confirm(`ATTENZIONE: Sei sicuro di voler eliminare l'utente "${userName}"?\n\nQuesta azione eliminerà anche tutti i suoi file e log.\n\nQuesta azione non può essere annullata.`)) {
            $.post(ajaxurl, {
                action: 'naval_egt_ajax',
                naval_action: 'delete_user',
                nonce: naval_egt_ajax.nonce,
                user_id: userId
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Errore: ' + (response.data || 'Errore sconosciuto'));
                }
            });
        }
    }
    
    function viewUserLogs(userId) {
        // Redirect alla tab log con filtro utente
        window.location.href = `?page=naval-egt&tab=logs&user_id=${userId}`;
    }
    
    function performBulkAction(action, userIds) {
        const actionText = {
            'activate': 'attivare',
            'suspend': 'sospendere',
            'delete': 'eliminare'
        };
        
        const confirmText = action === 'delete' ? 
            `ATTENZIONE: Sei sicuro di voler ${actionText[action]} ${userIds.length} utenti?\n\nQuesta azione eliminerà anche tutti i loro file e log.\n\nQuesta azione non può essere annullata.` :
            `Sei sicuro di voler ${actionText[action]} ${userIds.length} utenti?`;
        
        if (confirm(confirmText)) {
            $.post(ajaxurl, {
                action: 'naval_egt_ajax',
                naval_action: 'bulk_user_action',
                nonce: naval_egt_ajax.nonce,
                bulk_action: action,
                user_ids: userIds
            }, function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert('Errore: ' + (response.data || 'Errore sconosciuto'));
                }
            });
        }
    }
    
    // Validazione Partita IVA in tempo reale
    $('#user-ragione-sociale').on('input', function() {
        const pivaField = $('#user-partita-iva');
        if ($(this).val().trim()) {
            pivaField.prop('required', true);
            pivaField.siblings('.form-help').html('<strong>Obbligatoria se specificata la Ragione Sociale</strong>');
        } else {
            pivaField.prop('required', false);
            pivaField.siblings('.form-help').html('Obbligatoria se specificata la Ragione Sociale');
        }
    });
});
</script>