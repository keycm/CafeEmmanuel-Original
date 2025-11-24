<?php
include 'session_check.php';
include 'db_connect.php';
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

// Handle status update action
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id = intval($_GET['id']);
    
    if ($action == 'status' && isset($_GET['new_status'])) {
        $new_status = $_GET['new_status'];
        $stmt = $conn->prepare("UPDATE inquiries SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $id);
        $stmt->execute();
    } elseif ($action == 'delete') {
        $stmt = $conn->prepare("DELETE FROM inquiries WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
    }
    header("Location: admin_inquiries.php");
    exit();
}

$inquiries_result = $conn->query("SELECT * FROM inquiries ORDER BY 
    CASE status 
        WHEN 'new' THEN 1 
        WHEN 'in_progress' THEN 2 
        WHEN 'responded' THEN 3 
        WHEN 'closed' THEN 4 
    END, 
    received_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Customer Inquiries</title>
<link rel="stylesheet" href="CSS/admin.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
<link href="https://fonts.googleapis.com/css?family=Poppins" rel="stylesheet">
<style>
  :root {
      --primary-color: #556ee6;
      --main-bg: #f8f8fb;
      --card-bg: #ffffff;
      --text-color: #495057;
      --subtle-text: #74788d;
      --border-color: #eff2f7;
      --green-accent: #34c38f;
      --red-accent: #f46a6a;
      --blue-accent: #2196F3;
      --yellow-accent: #f1b44c;
  }
  .main-content { background-color: var(--main-bg); }
  .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
  .page-header h1 { font-size: 1.5rem; color: var(--text-color); margin: 0; }

  .inquiries-list { display: flex; flex-direction: column; gap: 20px; }

  .inquiry-card { background: var(--card-bg); border-radius: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); border-left: 4px solid transparent; transition: border-color 0.3s; }
  .inquiry-card.status-new { border-left-color: var(--red-accent); }
  .inquiry-card.status-in_progress { border-left-color: var(--yellow-accent); }
  .inquiry-card.status-responded { border-left-color: var(--green-accent); }
  .inquiry-card.status-closed { border-left-color: var(--subtle-text); }

  .inquiry-header { display: flex; justify-content: space-between; align-items: center; padding: 15px 20px; border-bottom: 1px solid var(--border-color); }
  .inquiry-sender { font-weight: 600; color: var(--text-color); }
  .inquiry-sender .email { font-weight: 400; color: var(--subtle-text); font-size: 0.9rem; margin-left: 8px; }
  .inquiry-meta { display: flex; gap: 15px; align-items: center; }
  .inquiry-time { font-size: 0.85rem; color: var(--subtle-text); }

  .status-badge { padding: 5px 12px; border-radius: 20px; font-weight: 500; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 5px; }
  .status-badge.new { background-color: #ffebee; color: #c62828; }
  .status-badge.in_progress { background-color: #fff3e0; color: #e65100; }
  .status-badge.responded { background-color: #e8f5e9; color: #2e7d32; }
  .status-badge.closed { background-color: #f5f5f5; color: #616161; }

  .inquiry-body { padding: 20px; line-height: 1.7; color: var(--text-color); }

  .response-section { padding: 15px 20px; background: #f8f9fa; border-top: 1px solid var(--border-color); }
  .response-label { font-size: 0.85rem; font-weight: 600; color: var(--subtle-text); margin-bottom: 8px; }
  .response-text { background: white; padding: 15px; border-radius: 6px; border-left: 3px solid var(--green-accent); }
  .response-meta { font-size: 0.8rem; color: var(--subtle-text); margin-top: 8px; }

  .internal-notes { padding: 15px 20px; background: #fff9e6; border-top: 1px solid var(--border-color); }
  .notes-text { font-size: 0.9rem; color: #856404; font-style: italic; }

  .inquiry-actions { display: flex; gap: 10px; padding: 0 20px 15px; flex-wrap: wrap; }
  .btn-action { padding: 8px 15px; border-radius: 6px; border: none; font-weight: 500; cursor: pointer; transition: all 0.2s; font-size: 0.85rem; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
  .btn-action.reply { background: var(--primary-color); color: white; }
  .btn-action.reply:hover { background: #4758c7; }
  .btn-action.status { background: var(--yellow-accent); color: white; }
  .btn-action.status:hover { background: #d9a043; }
  .btn-action.delete { background: var(--red-accent); color: white; }
  .btn-action.delete:hover { background: #e64a4a; }

  /* Modal */
  .modal { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); align-items: center; justify-content: center; }
  .modal.active { display: flex; }
  .modal-container { background: white; border-radius: 12px; width: 90%; max-width: 700px; max-height: 90vh; overflow-y: auto; box-shadow: 0 10px 40px rgba(0,0,0,0.3); }
  .modal-header { padding: 20px 24px; border-bottom: 1px solid #e0e0e0; display: flex; justify-content: space-between; align-items: center; }
  .modal-header h2 { font-size: 18px; font-weight: 600; color: #212121; margin: 0; }
  .close-modal { background: none; border: none; font-size: 24px; color: #757575; cursor: pointer; }
  .modal-body { padding: 24px; }
  .form-group { margin-bottom: 20px; }
  .form-group label { display: block; font-weight: 600; margin-bottom: 8px; color: var(--text-color); }
  .form-group textarea { width: 100%; padding: 12px; border: 2px solid var(--border-color); border-radius: 6px; font-size: 14px; font-family: inherit; resize: vertical; min-height: 120px; }
  .form-group select { width: 100%; padding: 10px 12px; border: 2px solid var(--border-color); border-radius: 6px; font-size: 14px; }
  .quick-responses { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
  .quick-response-btn { padding: 6px 12px; border: 1px solid var(--border-color); background: white; border-radius: 4px; font-size: 12px; cursor: pointer; transition: all 0.2s; }
  .quick-response-btn:hover { background: var(--primary-color); color: white; border-color: var(--primary-color); }
  .original-inquiry { background: #f8f9fa; padding: 15px; border-radius: 6px; margin-bottom: 20px; border-left: 3px solid var(--primary-color); }
  .button-group { display: flex; gap: 12px; margin-top: 24px; }
  .btn-modal { flex: 1; padding: 12px 24px; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; transition: all 0.2s; border: none; }
  .btn-submit { background: var(--primary-color); color: white; }
  .btn-submit:hover { background: #4758c7; }
  .btn-cancel { background: #f1f3f5; color: #495057; }
  .btn-cancel:hover { background: #e9ecef; }

  .alert { padding: 12px 18px; margin-bottom: 20px; border-radius: 6px; font-size: 0.9rem; }
  .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
  .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
  .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
  .no-inquiries { background: var(--card-bg); padding: 40px; border-radius: 8px; text-align: center; color: var(--subtle-text); }
</style>
</head>
<body>
<div class="admin-container">
  <?php include 'admin_sidebar.php'; ?>
  <main class="main-content">
    <header class="page-header">
      <h1><i class="fas fa-envelope"></i> Customer Inquiries</h1>
    </header>

    <?php if (isset($_GET['success'])): ?>
      <div class="alert alert-success">
        <?php 
        if ($_GET['success'] === 'response_sent') {
            echo '<i class="fas fa-check-circle"></i> Response sent successfully and customer notified via email!';
        } elseif ($_GET['success'] === 'response_saved') {
            echo '<i class="fas fa-check-circle"></i> Response saved successfully!';
        }
        ?>
      </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['warning']) && $_GET['warning'] === 'email_failed'): ?>
      <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle"></i> Response was saved but email notification failed to send.
      </div>
    <?php endif; ?>

    <div class="inquiries-list">
        <?php if ($inquiries_result->num_rows > 0): ?>
            <?php while ($row = $inquiries_result->fetch_assoc()): ?>
                <div class="inquiry-card status-<?php echo $row['status']; ?>">
                    <div class="inquiry-header">
                        <div class="inquiry-sender">
                            <?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>
                            <span class="email">&lt;<?php echo htmlspecialchars($row['email']); ?>&gt;</span>
                        </div>
                        <div class="inquiry-meta">
                            <span class="status-badge <?php echo $row['status']; ?>">
                                <?php 
                                $status_icons = [
                                    'new' => 'fa-circle',
                                    'in_progress' => 'fa-clock',
                                    'responded' => 'fa-check-circle',
                                    'closed' => 'fa-times-circle'
                                ];
                                $status_labels = [
                                    'new' => 'New',
                                    'in_progress' => 'In Progress',
                                    'responded' => 'Responded',
                                    'closed' => 'Closed'
                                ];
                                echo '<i class="fas ' . $status_icons[$row['status']] . '"></i> ' . $status_labels[$row['status']];
                                ?>
                            </span>
                            <span class="inquiry-time"><?php echo date("M d, Y h:i A", strtotime($row['received_at'])); ?></span>
                        </div>
                    </div>
                    <div class="inquiry-body">
                        <?php echo nl2br(htmlspecialchars($row['message'])); ?>
                    </div>
                    
                    <?php if (!empty($row['admin_response'])): ?>
                    <div class="response-section">
                        <div class="response-label">Admin Response:</div>
                        <div class="response-text">
                            <?php echo nl2br(htmlspecialchars($row['admin_response'])); ?>
                            <div class="response-meta">
                                Responded on <?php echo date("M d, Y h:i A", strtotime($row['responded_at'])); ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($row['internal_notes'])): ?>
                    <div class="internal-notes">
                        <div class="response-label"><i class="fas fa-sticky-note"></i> Internal Notes (Staff Only):</div>
                        <div class="notes-text"><?php echo nl2br(htmlspecialchars($row['internal_notes'])); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="inquiry-actions">
                        <button onclick="openReplyModal(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($row['message'], ENT_QUOTES); ?>', '<?php echo $row['status']; ?>')" class="btn-action reply">
                            <i class="fas fa-reply"></i> Reply
                        </button>
                        
                        <?php if ($row['status'] !== 'closed'): ?>
                        <select onchange="if(this.value) window.location.href='?action=status&id=<?php echo $row['id']; ?>&new_status=' + this.value" class="btn-action status">
                            <option value="">Change Status</option>
                            <option value="in_progress">Mark as In Progress</option>
                            <option value="responded">Mark as Responded</option>
                            <option value="closed">Mark as Closed</option>
                        </select>
                        <?php endif; ?>
                        
                        <a href="?action=delete&id=<?php echo $row['id']; ?>" class="btn-action delete" onclick="return confirm('Are you sure you want to delete this inquiry?')">
                            <i class="fas fa-trash-alt"></i> Delete
                        </a>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="no-inquiries">
                <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 12px; display: block; opacity: 0.3;"></i>
                <p>No inquiries found.</p>
            </div>
        <?php endif; ?>
    </div>
  </main>
</div>

<!-- Reply Modal -->
<div id="replyModal" class="modal">
    <div class="modal-container">
        <div class="modal-header">
            <h2><i class="fas fa-reply"></i> Reply to Inquiry</h2>
            <button class="close-modal" onclick="closeReplyModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="original-inquiry">
                <strong>Customer:</strong> <span id="modalCustomerName"></span><br>
                <strong>Original Message:</strong><br>
                <div id="modalOriginalMessage" style="margin-top: 8px;"></div>
            </div>
            
            <form method="POST" action="send_inquiry_response.php">
                <input type="hidden" name="inquiry_id" id="modalInquiryId">
                
                <div class="form-group">
                    <label>Quick Response Templates:</label>
                    <div class="quick-responses">
                        <button type="button" class="quick-response-btn" onclick="insertTemplate('Thank you for your inquiry. We will get back to you within 24 hours.')">24hr Response</button>
                        <button type="button" class="quick-response-btn" onclick="insertTemplate('Your reservation has been confirmed. We look forward to serving you!')">Confirm Reservation</button>
                        <button type="button" class="quick-response-btn" onclick="insertTemplate('Thank you for contacting us. Unfortunately, we cannot accommodate your request at this time.')">Cannot Accommodate</button>
                        <button type="button" class="quick-response-btn" onclick="insertTemplate('We have received your feedback and will review it carefully. Thank you!')">Feedback Received</button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Your Response: *</label>
                    <textarea name="response_message" id="responseMessage" required placeholder="Type your response to the customer..."></textarea>
                </div>
                
                <div class="form-group">
                    <label>Internal Notes (Optional - Not visible to customer):</label>
                    <textarea name="internal_notes" id="internalNotes" placeholder="Add private notes for staff reference..." style="min-height: 80px;"></textarea>
                </div>
                
                <div class="form-group">
                    <label>Update Status:</label>
                    <select name="status" id="modalStatus">
                        <option value="in_progress">In Progress</option>
                        <option value="responded" selected>Responded</option>
                        <option value="closed">Closed</option>
                    </select>
                </div>
                
                <div class="button-group">
                    <button type="submit" class="btn-modal btn-submit"><i class="fas fa-paper-plane"></i> Send Response</button>
                    <button type="button" onclick="closeReplyModal()" class="btn-modal btn-cancel">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openReplyModal(id, customerName, originalMessage, currentStatus) {
    document.getElementById('modalInquiryId').value = id;
    document.getElementById('modalCustomerName').textContent = customerName;
    document.getElementById('modalOriginalMessage').innerHTML = originalMessage.replace(/\n/g, '<br>');
    document.getElementById('responseMessage').value = '';
    document.getElementById('internalNotes').value = '';
    
    // Set default status based on current status
    if (currentStatus === 'new') {
        document.getElementById('modalStatus').value = 'in_progress';
    } else {
        document.getElementById('modalStatus').value = 'responded';
    }
    
    document.getElementById('replyModal').classList.add('active');
}

function closeReplyModal() {
    document.getElementById('replyModal').classList.remove('active');
}

function insertTemplate(text) {
    document.getElementById('responseMessage').value = text;
}

// Close modal when clicking outside
document.getElementById('replyModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeReplyModal();
    }
});
</script>
</body>
</html>
<?php $conn->close(); ?>
