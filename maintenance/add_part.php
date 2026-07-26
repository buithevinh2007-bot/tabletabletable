<?php
require '../config.php'; //from person 1
require '../includes/auth.php'; //from person 1

// Check if user is logged in and has Workshop Staff or Admin role
require_role('Workshop Staff', 'Admin');

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $activityId = (int) $_POST['activity_id'];
    $partId = (int) $_POST['part_id'];
    $quantityUsed = (int) $_POST['quantity_used'];
    $unitPriceCharged = (float) $_POST['unit_price_charged'];

    try {
        // 1. Verify activity exists
        $stmt = $pdo->prepare('
            SELECT ma.id, ma.job_id, mj.vehicle_id
            FROM Maintenance_Activities ma
            JOIN Maintenance_Jobs mj ON ma.job_id = mj.id
            WHERE ma.id = ?
        ');
        $stmt->execute([$activityId]);
        $activity = $stmt->fetch();

        if (!$activity) {
            throw new Exception('Maintenance activity not found.');
        }

        // 2. Verify part exists
        $stmt = $pdo->prepare('SELECT part_id, description FROM Parts WHERE part_id = ?');
        $stmt->execute([$partId]);
        $part = $stmt->fetch();

        if (!$part) {
            throw new Exception('Part not found.');
        }

        // 3. Check if this part is already added to this activity (unique constraint)
        $stmt = $pdo->prepare('
            SELECT id FROM Activity_Parts
            WHERE activity_id = ? AND part_id = ?
        ');
        $stmt->execute([$activityId, $partId]);
        $existingPart = $stmt->fetch();

        if ($existingPart) {
            throw new Exception('This part has already been added to this activity. Please use a different part or activity.');
        }

        // 4. Insert the part into the activity
        $stmt = $pdo->prepare('
            INSERT INTO Activity_Parts (activity_id, part_id, quantity_used, unit_price_charged)
            VALUES (?, ?, ?, ?)
        ');
        $stmt->execute([$activityId, $partId, $quantityUsed, $unitPriceCharged]);

        $message = 'Part ' . htmlspecialchars($part['description']) . ' added successfully (Qty: ' . $quantityUsed . ', Unit Price: $' . number_format($unitPriceCharged, 2) . ').';
        $messageType = 'success';
    } catch (Exception $e) {
        $message = 'Error: ' . htmlspecialchars($e->getMessage());
        $messageType = 'error';
    }
}

// Fetch list of maintenance activities
$activitiesStmt = $pdo->prepare('
    SELECT ma.id, ma.activity_type, mj.id as job_id, v.registration_number
    FROM Maintenance_Activities ma
    JOIN Maintenance_Jobs mj ON ma.job_id = mj.id
    JOIN Vehicle v ON mj.vehicle_id = v.id
    WHERE mj.date_closed IS NULL
    ORDER BY mj.date_opened DESC
');
$activitiesStmt->execute();
$activities = $activitiesStmt->fetchAll();

// Fetch list of parts
$partsStmt = $pdo->prepare('
    SELECT part_id, part_number, description, standard_unit_price
    FROM Parts
    ORDER BY description
');
$partsStmt->execute();
$parts = $partsStmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Part to Maintenance Activity</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 700px; margin: 0 auto; }
        form { border: 1px solid #ccc; padding: 20px; border-radius: 5px; }
        label { display: block; margin-top: 15px; font-weight: bold; }
        select, input { width: 100%; padding: 8px; margin-top: 5px; box-sizing: border-box; }
        button { margin-top: 20px; padding: 10px 20px; background-color: #008CBA; color: white; border: none; border-radius: 5px; cursor: pointer; }
        button:hover { background-color: #007399; }
        .message { padding: 15px; margin-top: 20px; border-radius: 5px; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        a { display: block; margin-top: 20px; color: #007bff; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .form-row { display: flex; gap: 15px; }
        .form-row > div { flex: 1; }
        .info { background-color: #e7f3ff; padding: 10px; border-radius: 5px; margin-top: 10px; font-size: 14px; }
    </style>
</head>
<body>
  <?php include '../includes/header.php'; ?>
  
  <div class="container">
    <h1>Add Part to Maintenance Activity</h1>
    
    <?php if (!empty($message)): ?>
        <div class="message <?php echo $messageType; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <form method="post">
        <label for="activity_id">Select Maintenance Activity:</label>
        <select name="activity_id" id="activity_id" required>
            <option value="">-- Choose an activity --</option>
            <?php foreach ($activities as $activity): ?>
                <option value="<?php echo htmlspecialchars($activity['id']); ?>">
                    <?php echo htmlspecialchars('Job #' . $activity['job_id'] . ' - ' . $activity['registration_number'] . ' - ' . $activity['activity_type']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <div class="info">Select an active maintenance activity to add a part to.</div>

        <label for="part_id">Select Part:</label>
        <select name="part_id" id="part_id" required>
            <option value="">-- Choose a part --</option>
            <?php foreach ($parts as $part): ?>
                <option value="<?php echo htmlspecialchars($part['part_id']); ?>">
                    <?php echo htmlspecialchars($part['part_number'] . ' - ' . $part['description'] . ' (Std: $' . number_format($part['standard_unit_price'], 2) . ')'); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <div class="form-row">
            <div>
                <label for="quantity_used">Quantity Used:</label>
                <input type="number" name="quantity_used" id="quantity_used" min="1" value="1" required>
            </div>
            <div>
                <label for="unit_price_charged">Unit Price Charged ($):</label>
                <input type="number" name="unit_price_charged" id="unit_price_charged" step="0.01" min="0" value="0.00" required>
            </div>
        </div>

        <button type="submit">Add Part</button>
    </form>

    <a href="../index.php">← Back to Dashboard</a>
  </div>
  
  <?php include '../includes/footer.php'; ?>
</body>
</html>
