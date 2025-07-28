<?php
require_once 'db_connect.php';
require_once 'security.php';
require_once 'withdrawal_processor.php';

// Initialize secure session
Security::secureSessionStart();

// Check admin authentication (implement your admin auth logic)
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

$db = new Database();
$conn = $db->getConnection();
$processor = new WithdrawalProcessor();

// Handle admin actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $transactionRef = $_POST['transaction_ref'] ?? '';
    $adminNotes = $_POST['admin_notes'] ?? '';
    
    try {
        switch ($action) {
            case 'approve':
                $result = $processor->manuallyApproveWithdrawal($transactionRef, $adminNotes);
                $_SESSION['success'] = $result['message'];
                break;
                
            case 'reject':
                // Implement rejection logic
                $processor->rejectWithdrawal($transactionRef, $adminNotes);
                $_SESSION['success'] = "Withdrawal rejected successfully";
                break;
                
            case 'assign':
                $assignTo = $_POST['assign_to'] ?? '';
                // Implement assignment logic
                $_SESSION['success'] = "Withdrawal assigned successfully";
                break;
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
    
    header("Location: admin_withdrawals.php");
    exit();
}

// Get pending manual withdrawals
$pendingWithdrawals = $processor->getPendingManualWithdrawals();

// Get withdrawal statistics
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_pending,
        SUM(amount) as total_amount,
        COUNT(CASE WHEN priority = 'high' THEN 1 END) as high_priority,
        COUNT(CASE WHEN priority = 'urgent' THEN 1 END) as urgent_priority
    FROM manual_withdrawals
");
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Manual Withdrawals</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-shield-alt"></i> Admin Dashboard
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="admin_logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h2><i class="fas fa-money-bill-wave"></i> Manual Withdrawal Processing</h2>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h5>Total Pending</h5>
                                <h3><?= $stats['total_pending'] ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h5>Total Amount</h5>
                                <h3>ETB <?= number_format($stats['total_amount'], 2) ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <h5>High Priority</h5>
                                <h3><?= $stats['high_priority'] ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-danger text-white">
                            <div class="card-body">
                                <h5>Urgent</h5>
                                <h3><?= $stats['urgent_priority'] ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Success/Error Messages -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?= $_SESSION['success'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?= $_SESSION['error'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <!-- Pending Withdrawals Table -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-list"></i> Pending Manual Withdrawals</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($pendingWithdrawals)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                <h4>No Pending Withdrawals</h4>
                                <p class="text-muted">All withdrawals have been processed!</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Reference</th>
                                            <th>User</th>
                                            <th>Amount</th>
                                            <th>Method</th>
                                            <th>Priority</th>
                                            <th>Queued</th>
                                            <th>Details</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pendingWithdrawals as $withdrawal): ?>
                                            <?php 
                                            $details = json_decode($withdrawal['processing_details'], true);
                                            $withdrawalDetails = $details['withdrawal_details'];
                                            ?>
                                            <tr>
                                                <td>
                                                    <code><?= $withdrawal['transaction_ref'] ?></code>
                                                </td>
                                                <td>
                                                    <strong><?= htmlspecialchars($withdrawal['username']) ?></strong><br>
                                                    <small class="text-muted"><?= htmlspecialchars($withdrawal['email']) ?></small>
                                                </td>
                                                <td>
                                                    <strong class="text-success">ETB <?= number_format($withdrawal['amount'], 2) ?></strong>
                                                </td>
                                                <td>
                                                    <?php if ($withdrawalDetails['method'] === 'bank_transfer'): ?>
                                                        <i class="fas fa-university text-primary"></i> Bank Transfer<br>
                                                        <small><?= $withdrawalDetails['bank_name'] ?></small>
                                                    <?php else: ?>
                                                        <i class="fas fa-mobile-alt text-success"></i> Mobile Money<br>
                                                        <small><?= $withdrawalDetails['mobile_provider'] ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $priorityClass = [
                                                        'low' => 'secondary',
                                                        'normal' => 'primary',
                                                        'high' => 'warning',
                                                        'urgent' => 'danger'
                                                    ];
                                                    ?>
                                                    <span class="badge bg-<?= $priorityClass[$withdrawal['priority']] ?>">
                                                        <?= ucfirst($withdrawal['priority']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?= date('M j, Y H:i', strtotime($withdrawal['queued_at'])) ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-info" onclick="showDetails('<?= $withdrawal['transaction_ref'] ?>')">
                                                        <i class="fas fa-eye"></i> View
                                                    </button>
                                                </td>
                                                <td>
                                                    <div class="btn-group">
                                                        <button class="btn btn-sm btn-success" onclick="approveWithdrawal('<?= $withdrawal['transaction_ref'] ?>')">
                                                            <i class="fas fa-check"></i> Approve
                                                        </button>
                                                        <button class="btn btn-sm btn-danger" onclick="rejectWithdrawal('<?= $withdrawal['transaction_ref'] ?>')">
                                                            <i class="fas fa-times"></i> Reject
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Approval Modal -->
    <div class="modal fade" id="approvalModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Approve Withdrawal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="transaction_ref" id="approve_ref">
                        
                        <div class="mb-3">
                            <label for="admin_notes" class="form-label">Admin Notes (Optional)</label>
                            <textarea class="form-control" name="admin_notes" rows="3" 
                                      placeholder="Add any notes about this approval..."></textarea>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Confirm:</strong> This will mark the withdrawal as completed and notify the user.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check"></i> Approve Withdrawal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Rejection Modal -->
    <div class="modal fade" id="rejectionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reject Withdrawal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="transaction_ref" id="reject_ref">
                        
                        <div class="mb-3">
                            <label for="reject_notes" class="form-label">Rejection Reason (Required)</label>
                            <textarea class="form-control" name="admin_notes" rows="3" required
                                      placeholder="Please provide a reason for rejection..."></textarea>
                        </div>
                        
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Warning:</strong> This will restore the user's balance and notify them of the rejection.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-times"></i> Reject Withdrawal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function approveWithdrawal(transactionRef) {
            document.getElementById('approve_ref').value = transactionRef;
            new bootstrap.Modal(document.getElementById('approvalModal')).show();
        }
        
        function rejectWithdrawal(transactionRef) {
            document.getElementById('reject_ref').value = transactionRef;
            new bootstrap.Modal(document.getElementById('rejectionModal')).show();
        }
        
        function showDetails(transactionRef) {
            // Implement details modal if needed
            alert('Details for: ' + transactionRef);
        }
    </script>
</body>
</html>