<?php
require '../config.php'; //from person 1
require '../includes/auth.php'; //from person 1

// Check if user is logged in and has Workshop Staff or Admin role
require_role('Workshop Staff', 'Admin');

$message = '';
$messageType = '';
$jobDetails = null;
$labourCost = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jobId = (int) $_POST['job_id'];

    try {
        $pdo->beginTransaction();

        // 1. Get job details to verify it exists and get vehicle ID
        $stmt = $pdo->prepare('
            SELECT mj.id, mj.vehicle_id, mj.date_opened, v.registration_number
            FROM Maintenance_Jobs mj
            JOIN Vehicle v ON mj.vehicle_id = v.id
            WHERE mj.id = ?
        ');
        $stmt->execute([$jobId]);
        $job = $stmt->fetch();

        if (!$job) {
            throw new Exception('Maintenance job not found.');
        }

        if ($job['date_closed'] !== null) {
            throw new Exception('This job has already been closed.');
        }

        // 2. Calculate total labour cost
        // Labour cost = SUM(labour_hours * hourly_rate for each mechanic)
        $stmt = $pdo->prepare('
            SELECT SUM(am.labour_hours * sl.hourly_rate) AS labour_cost
            FROM Activity_Mechanics am
            JOIN Mechanics m ON am.mechanic_id = m.id
            JOIN Skill_Levels sl ON m.skill_id = sl.id
            JOIN Maintenance_Activities ma ON am.activity_id = ma.id
            WHERE ma.job_id = ?
        ');
        $stmt->execute([$jobId]);
        $costResult = $stmt->fetch();
        $labourCost = $costResult['labour_cost'] ?? 0;

        // 3. Calculate down time in hours
        $dateOpened = new DateTime($job['date_opened']);
        $now = new DateTime();
        $downTimeHours = $now->diff($dateOpened)->h + ($now->diff($dateOpened)->days * 24);

        // 4. Close the job
        $stmt = $pdo->prepare('
            UPDATE Maintenance_Jobs
            SET date_closed = NOW(), total_cost = ?, down_time_hours = ?
            WHERE id = ?
        ');
        $stmt->execute([$labourCost, $downTimeHours, $jobId]);

        // 5. Return vehicle to "Available" status
        $stmt = $pdo->prepare("UPDATE Vehicle SET status = 'Available' WHERE id = ?");
        $stmt->execute([$job['vehicle_id']]);

        $pdo->commit();
        $message = 'Maintenance job closed successfully. Total labour cost: $' . number_format($labourCost, 2) . ', Down time: ' . $downTimeHours . ' hours.';
        $messageType = 'success';
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = 'Error: ' . htmlspecialchars($e->getMessage());
        $messageType = 'error';
    }
}

// Fetch list of open maintenance jobs
$jobsStmt = $pdo->prepare('
    SELECT mj.id, mj.vehicle_id, v.registration_number, vm.model_name, w.workshop_name, mj.date_opened
    FROM Maintenance_Jobs mj
    JOIN Vehicle v ON mj.vehicle_id = v.id
    JOIN Vehicle_Models vm ON v.model_id = vm.id
    JOIN Workshops w ON mj.workshop_id = w.id
    WHERE mj.date_closed IS NULL
    ORDER BY mj.date_opened DESC
');
$jobsStmt->execute();
$openJobs = $jobsStmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Close Maintenance Job</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 700px; margin: 0 auto; }
        form { border: 1px solid #ccc; padding: 20px; border-radius: 5px; }
        label { display: block; margin-top: 15px; font-weight: bold; }
        select, input { width: 100%; padding: 8px; margin-top: 5px; box-sizing: border-box; }
        button { margin-top: 20px; padding: 10px 20px; background-color: #4CAF50; color: white; border: none; border-radius: 5px; cursor: pointer; }
        button:hover { background-color: #45a049; }
        .message { padding: 15px; margin-top: 20px; border-radius: 5px; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        a { display: block; margin-top: 20px; color: #007bff; text-decoration: none; }
        a:hover { text-decoration: underline; }
        table { width: 100%; margin-top: 20px; border-collapse: collapse; }
        table th, table td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        table th { background-color: #f2f2f2; }
    </style>
</head>
<body>
  <?php include '../includes/header.php'; ?>
  
  <div class="container">
    <h1>Close a Maintenance Job</h1>
    
    <?php if (!empty($message)): ?>
        <div class="message <?php echo $messageType; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <form method="post">
        <label for="job_id">Select Job to Close:</label>
        <select name="job_id" id="job_id" required>
            <option value="">-- Choose a job --</option>
            <?php foreach ($openJobs as $job): ?>
                <option value="<?php echo htmlspecialchars($job['id']); ?>">
                    <?php echo htmlspecialchars('Job #' . $job['id'] . ' - ' . $job['registration_number'] . ' (' . $job['model_name'] . ')'); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit">Close Job</button>
    </form>

    <h2>Open Maintenance Jobs</h2>
    <?php if (count($openJobs) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Job ID</th>
                    <th>Vehicle</th>
                    <th>Model</th>
                    <th>Workshop</th>
                    <th>Date Opened</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($openJobs as $job): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($job['id']); ?></td>
                        <td><?php echo htmlspecialchars($job['registration_number']); ?></td>
                        <td><?php echo htmlspecialchars($job['model_name']); ?></td>
                        <td><?php echo htmlspecialchars($job['workshop_name']); ?></td>
                        <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($job['date_opened']))); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No open maintenance jobs.</p>
    <?php endif; ?>

    <a href="../index.php">← Back to Dashboard</a>
  </div>
  
  <?php include '../includes/footer.php'; ?>
</body>
</html>
