<?php
require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/auth.php';

// Check if user is logged in and has Workshop Staff or Admin role
require_role('Workshop Staff', 'Admin');

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vehicleId = (int) $_POST['vehicle_id'];
    $workshopId = (int) $_POST['workshop_id'];

    try {
        $pdo->beginTransaction();

        // 1. Check if vehicle exists and get its current status
        $stmt = $pdo->prepare('SELECT id, status, registration_number FROM Vehicle WHERE id = ?');
        $stmt->execute([$vehicleId]);
        $vehicle = $stmt->fetch();

        if (!$vehicle) {
            throw new Exception('Vehicle not found.');
        }

        // 2. Check if workshop exists
        $stmt = $pdo->prepare('SELECT id, workshop_name FROM Workshops WHERE id = ?');
        $stmt->execute([$workshopId]);
        $workshop = $stmt->fetch();

        if (!$workshop) {
            throw new Exception('Workshop not found.');
        }

        // 3. Create the maintenance job
        $stmt = $pdo->prepare('
            INSERT INTO Maintenance_Jobs (vehicle_id, workshop_id, date_opened, date_closed, total_cost, down_time_hours)
            VALUES (?, ?, NOW(), NULL, NULL, NULL)
        ');
        $stmt->execute([$vehicleId, $workshopId]);

        // 4. Update vehicle status to "Under Maintenance"
        $stmt = $pdo->prepare("UPDATE Vehicle SET status = 'Under Maintenance' WHERE id = ?");
        $stmt->execute([$vehicleId]);

        $pdo->commit();
        $message = 'Maintenance job opened successfully for vehicle ' . htmlspecialchars($vehicle['registration_number']) . '.';
        $messageType = 'success';
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = 'Error: ' . htmlspecialchars($e->getMessage());
        $messageType = 'error';
    }
}

// Fetch list of vehicles for the dropdown
$vehiclesStmt = $pdo->prepare('
    SELECT v.id, v.registration_number, v.status, vm.model_name
    FROM Vehicle v
    JOIN Vehicle_Models vm ON v.model_id = vm.id
    ORDER BY v.registration_number
');
$vehiclesStmt->execute();
$vehicles = $vehiclesStmt->fetchAll();

// Fetch list of workshops for the dropdown
$workshopsStmt = $pdo->prepare('
    SELECT w.id, w.workshop_name, d.depot_name
    FROM Workshops w
    JOIN Depot d ON w.depot_id = d.id
    ORDER BY w.workshop_name
');
$workshopsStmt->execute();
$workshops = $workshopsStmt->fetchAll();

$pageTitle = 'Open Maintenance Job';
?>
<?php include BASE_PATH . '/includes/header.php'; ?>
<div class="container" style="max-width: 600px; margin: 20px auto;">
    <h1>Open a New Maintenance Job</h1>
    
    <?php if (!empty($message)): ?>
        <div style="padding: 15px; margin: 20px 0; border-radius: 5px; <?php echo $messageType === 'success' ? 'background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb;' : 'background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;'; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <form method="post" style="border: 1px solid #ccc; padding: 20px; border-radius: 5px;">
        <div style="margin-bottom: 20px;">
            <label for="vehicle_id" style="display: block; font-weight: bold; margin-bottom: 8px;">Select Vehicle:</label>
            <select name="vehicle_id" id="vehicle_id" required style="width: 100%; padding: 8px; box-sizing: border-box;">
                <option value="">-- Choose a vehicle --</option>
                <?php foreach ($vehicles as $vehicle): ?>
                    <option value="<?php echo htmlspecialchars($vehicle['id']); ?>">
                        <?php echo htmlspecialchars($vehicle['registration_number'] . ' - ' . $vehicle['model_name'] . ' (' . $vehicle['status'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="margin-bottom: 20px;">
            <label for="workshop_id" style="display: block; font-weight: bold; margin-bottom: 8px;">Select Workshop:</label>
            <select name="workshop_id" id="workshop_id" required style="width: 100%; padding: 8px; box-sizing: border-box;">
                <option value="">-- Choose a workshop --</option>
                <?php foreach ($workshops as $workshop): ?>
                    <option value="<?php echo htmlspecialchars($workshop['id']); ?>">
                        <?php echo htmlspecialchars($workshop['workshop_name'] . ' (' . $workshop['depot_name'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" style="padding: 10px 20px; background-color: #4CAF50; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: bold;">Open Job</button>
    </form>

    <a href="<?php echo base_url(); ?>/index.php" style="display: block; margin-top: 20px; color: #007bff; text-decoration: none;">← Back to Dashboard</a>
</div>
<?php include BASE_PATH . '/includes/footer.php'; ?>

