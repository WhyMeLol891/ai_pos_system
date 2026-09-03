<?php
/**
 * User Management (Admins and Cashiers)
 * AI Camera POS System
 */

$pageTitle = 'User Management';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';

require_admin();

$pdo = get_db_connection();
$currentAdminId = $_SESSION['user_id'];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid security token.');
        header('Location: ' . base_url('admin/users.php'));
        exit;
    }

    $action = $_POST['action'] ?? '';

    // 1. Create User
    if ($action === 'create') {
        $username = trim(strtolower($_POST['username'] ?? ''));
        $fullname = trim($_POST['full_name'] ?? '');
        $role = in_array($_POST['role'] ?? '', ['admin', 'cashier']) ? $_POST['role'] : 'cashier';
        $password = trim($_POST['password'] ?? '');

        if (empty($username) || empty($fullname) || empty($password)) {
            set_flash('danger', 'All fields are required.');
        } elseif (strlen($password) < 6) {
            set_flash('danger', 'Password must be at least 6 characters.');
        } else {
            // Check username uniqueness
            $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE username = :u");
            $stmtCheck->execute(['u' => $username]);
            if ($stmtCheck->fetch()) {
                set_flash('danger', "Username '{$username}' is already taken.");
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, password_hash, full_name, role, status)
                    VALUES (:u, :p, :fn, :role, 'active')
                ");
                $stmt->execute([
                    'u'    => $username,
                    'p'    => $hash,
                    'fn'   => $fullname,
                    'role' => $role
                ]);
                log_audit('USER_CREATE', "Created {$role} user: {$username}");
                set_flash('success', "User '{$username}' created successfully!");
            }
        }
    }

    // 2. Update User
    elseif ($action === 'update') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $fullname = trim($_POST['full_name'] ?? '');
        $role = in_array($_POST['role'] ?? '', ['admin', 'cashier']) ? $_POST['role'] : 'cashier';
        $status = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';
        $newPassword = trim($_POST['new_password'] ?? '');

        // Prevent admin from demoting or deactivating themselves
        if ($userId === $currentAdminId) {
            $role = 'admin';
            $status = 'active';
        }

        if (empty($fullname)) {
            set_flash('danger', 'Full name is required.');
        } else {
            if (!empty($newPassword)) {
                if (strlen($newPassword) < 6) {
                    set_flash('danger', 'New password must be at least 6 characters.');
                    header('Location: ' . base_url('admin/users.php'));
                    exit;
                }
                $hash = password_hash($newPassword, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("
                    UPDATE users SET full_name = :fn, role = :role, status = :st, password_hash = :p WHERE id = :id
                ");
                $stmt->execute(['fn' => $fullname, 'role' => $role, 'st' => $status, 'p' => $hash, 'id' => $userId]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE users SET full_name = :fn, role = :role, status = :st WHERE id = :id
                ");
                $stmt->execute(['fn' => $fullname, 'role' => $role, 'st' => $status, 'id' => $userId]);
            }
            log_audit('USER_UPDATE', "Updated user ID {$userId}");
            set_flash('success', "User updated successfully.");
        }
    }

    // 3. Delete User
    elseif ($action === 'delete') {
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId === $currentAdminId) {
            set_flash('danger', 'You cannot delete your own active administrator account.');
        } else {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
            $stmt->execute(['id' => $userId]);
            log_audit('USER_DELETE', "Deleted user ID {$userId}");
            set_flash('success', "User deleted successfully.");
        }
    }

    header('Location: ' . base_url('admin/users.php'));
    exit;
}

// Fetch all users
$users = $pdo->query("SELECT * FROM users ORDER BY role ASC, full_name ASC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-people text-primary me-2"></i>User Management</h3>
        <p class="text-muted mb-0">Manage cashier and administrator accounts and permissions.</p>
    </div>
    <button type="button" class="btn btn-primary fw-semibold px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
        <i class="bi bi-person-plus-fill me-1"></i> Add New User
    </button>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th>User</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Created Date</th>
                        <th class="text-end" style="width: 140px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="rounded-circle bg-light border d-flex align-items-center justify-content-center fw-bold text-secondary" style="width: 38px; height: 38px;">
                                        <?= strtoupper(substr($u['full_name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <span class="fw-bold text-dark d-block"><?= clean($u['full_name']) ?></span>
                                        <?php if ($u['id'] === $currentAdminId): ?>
                                            <small class="badge bg-primary-subtle text-primary">Current You</small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td><span class="font-monospace text-muted">@<?= clean($u['username']) ?></span></td>
                            <td>
                                <?php if ($u['role'] === 'admin'): ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="bi bi-shield-lock me-1"></i>Administrator</span>
                                <?php else: ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-cart me-1"></i>Cashier</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($u['status'] === 'active'): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted small"><?= format_date($u['created_at'], 'd M Y') ?></td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="openEditUserModal(<?= htmlspecialchars(json_encode($u)) ?>)" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <?php if ($u['id'] !== $currentAdminId): ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmDeleteUser(<?= $u['id'] ?>, '<?= addslashes(clean($u['full_name'])) ?>')" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="<?= base_url('admin/users.php') ?>" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-person-plus me-2 text-primary"></i>Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="full_name" class="form-control" required placeholder="e.g. John Doe">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Username <span class="text-danger">*</span></label>
                    <input type="text" name="username" class="form-control" required placeholder="e.g. cashier2">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Role <span class="text-danger">*</span></label>
                    <select name="role" class="form-select" required>
                        <option value="cashier" selected>Cashier (POS & Sales only)</option>
                        <option value="admin">Administrator (Full Access)</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                    <input type="password" name="password" class="form-control" minlength="6" required placeholder="Minimum 6 characters">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary fw-semibold">Create Account</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="<?= base_url('admin/users.php') ?>" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="user_id" id="editUserId">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2 text-primary"></i>Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Username</label>
                    <input type="text" id="editUsername" class="form-control bg-light" readonly disabled>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="full_name" id="editFullName" class="form-control" required>
                </div>
                <div class="row">
                    <div class="col-6 mb-3">
                        <label class="form-label fw-semibold">Role</label>
                        <select name="role" id="editRole" class="form-select">
                            <option value="cashier">Cashier</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>
                    <div class="col-6 mb-3">
                        <label class="form-label fw-semibold">Status</label>
                        <select name="status" id="editStatus" class="form-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Reset Password</label>
                    <input type="password" name="new_password" class="form-control" minlength="6" placeholder="Leave blank to keep current password">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary fw-semibold">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete User Modal -->
<div class="modal fade" id="deleteUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="<?= base_url('admin/users.php') ?>" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="user_id" id="deleteUserId">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-danger"><i class="bi bi-trash me-2"></i>Delete User Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to permanently delete user account <strong id="deleteUserName"></strong>?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger fw-semibold">Delete User</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditUserModal(u) {
    document.getElementById('editUserId').value = u.id;
    document.getElementById('editUsername').value = '@' + u.username;
    document.getElementById('editFullName').value = u.full_name;
    document.getElementById('editRole').value = u.role;
    document.getElementById('editStatus').value = u.status;
    new bootstrap.Modal(document.getElementById('editUserModal')).show();
}

function confirmDeleteUser(id, name) {
    document.getElementById('deleteUserId').value = id;
    document.getElementById('deleteUserName').textContent = name;
    new bootstrap.Modal(document.getElementById('deleteUserModal')).show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
