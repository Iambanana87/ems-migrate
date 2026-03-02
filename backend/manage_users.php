<?php
session_start();

// Redirect if not logged in or not an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php?return=/backend/manage_users.php");
    exit;
}

// Include database configuration
require_once 'config.php';

//connect to the database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Function to validate input
function validateInput($username, $password = '') {
    if (strlen($username) < 4 || strlen($username) > 20 || !preg_match("/^[a-zA-Z0-9_]+$/", $username)) {
        return "Username must be 4-20 characters and contain only letters, numbers, and underscores.";
    }
    if ($password && strlen($password) < 8) {
        return "Password must be at least 8 characters long.";
    }
    return "";
}

// process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $new_username = trim($_POST['new_username']);
    $new_password = trim($_POST['new_password']);
    $new_role = $_POST['new_role'];
    $validationError = validateInput($new_username, $new_password);
    if ($validationError) {
        $error = $validationError;
    } else {
        $new_password = password_hash($new_password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (username, password, role) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $new_username, $new_password, $new_role);
        if ($stmt->execute()) {
            $success = "User added successfully.";
        } else {
            $error = "Error adding user: Username already exists or other error.";
        }
        $stmt->close();
    }
}

// process user updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $edit_id = $_POST['edit_id'];
    $edit_username = trim($_POST['edit_username']);
    $edit_password = trim($_POST['edit_password']);
    $edit_role = $_POST['edit_role'];
    // Validate password only if it's being changed
    $validationError = validateInput($edit_username, $edit_password ?: 'dummypassword'); // Use a dummy valid password if empty
    if ($edit_password && $validationError) {
        $error = $validationError;
    } else {
        $sql = $edit_password ? 
            "UPDATE users SET username = ?, password = ?, role = ? WHERE id = ?" : 
            "UPDATE users SET username = ?, role = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if ($edit_password) {
            $edit_password = password_hash($edit_password, PASSWORD_DEFAULT);
            $stmt->bind_param("sssi", $edit_username, $edit_password, $edit_role, $edit_id);
        } else {
            $stmt->bind_param("ssi", $edit_username, $edit_role, $edit_id);
        }
        if ($stmt->execute()) {
            $success = "User updated successfully.";
        } else {
            $error = "Error updating user. Username may already exist.";
        }
        $stmt->close();
    }
}

//process user deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    $delete_id = $_POST['delete_id'];
    $admin_password = trim($_POST['admin_password']);
    
    // Check if the user is trying to delete their own account
    if ($delete_id == $_SESSION['user_id']) {
        $error = "You cannot delete your own account.";
    } else {
        $sql = "SELECT password FROM users WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $admin = $result->fetch_assoc();

        if ($admin && password_verify($admin_password, $admin['password'])) {
            $sql = "DELETE FROM users WHERE id = ?";
            $stmt_delete = $conn->prepare($sql);
            $stmt_delete->bind_param("i", $delete_id);
            if ($stmt_delete->execute()) {
                $success = "User deleted successfully.";
            } else {
                $error = "Error deleting user.";
            }
            $stmt_delete->close();
        } else {
            $error = "Incorrect admin password.";
        }
        $stmt->close();
    }
}

// user list query
$sql = "SELECT id, username, role, created_at FROM users ORDER BY created_at DESC";
$result = $conn->query($sql);

// user edit query
$edit_user = null;
if (isset($_GET['edit_id'])) {
    $edit_id = $_GET['edit_id'];
    $sql = "SELECT id, username, role FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result_edit = $stmt->get_result();
    $edit_user = $result_edit->fetch_assoc();
    $stmt->close();
}

// user deletion query
$delete_user = null;
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    $sql = "SELECT id, username FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $result_delete = $stmt->get_result();
    $delete_user = $result_delete->fetch_assoc();
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="assets/css/all.min.css"> <link rel="stylesheet" href="assets/css/local-fonts.css"> <link rel="stylesheet" href="assets/css/flatpickr.min.css">
    <link rel="stylesheet" href="assets/css/confirmDate.css">
    <link rel="icon" type="image/png" href="image/icon2.png">
      <link rel="icon" type="image/png" href="../image/icon2.png">
  <link href="../assets/css/all.min.css" rel="stylesheet">
  <link href="../assets/css/local-fonts.css" rel="stylesheet">
  <script src="../assets/js/tailwindcss.js"></script>
    <style>
        .modal { display: none; position: fixed; z-index: 50; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); backdrop-filter: blur(4px); }
        .modal-content { background-color: #fefefe; margin: 10% auto; padding: 24px; border: 1px solid #888; width: 90%; max-width: 500px; border-radius: 0.5rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        .close-button { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
    </style>
</head>
<body class="bg-gray-100 font-sans">
    <header class="bg-white shadow-md sticky top-0 z-40">
        <div class="container mx-auto px-4 py-3 flex items-center justify-between">
      <div class="flex items-center gap-4">
        <a href="backend.php" class="flex items-center gap-4"><img src="../image/icon2.png" class="h-8 md:h-10" alt="Logo"></a>
        <a href="../index.html" class="flex items-center gap-4"><img src="../image/ems_.png" class="h-7 md:h-9" alt="EMS"></a>
        <span class="text-lg text-gray-600 font-semibold ml-4">Manage Users</span>
      </div>
      <div class="relative">
        <button id="menu-toggle-btn" class="p-2 rounded-full hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
          <svg class="h-6 w-6 text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <div id="header-dropdown" class="hidden absolute right-0 mt-2 w-56 bg-white rounded-md shadow-xl z-20">
          <div class="px-4 py-2">
            <p class="text-sm text-gray-500">Welcome,</p>
            <p class="font-semibold text-gray-800 truncate"><?php echo htmlspecialchars($_SESSION['username']); ?></p>
          </div>
          <hr class="my-1 border-gray-200">
          <div class="py-1">
            <button id="add-device-btn" class="w-full text-left flex items-center gap-3 px-4 py-2 text-gray-700 hover:bg-gray-100"><i class="fas fa-plus w-5 text-center"></i><span>Add New Device</span></button>
            <a href="../index.html" class="flex items-center gap-3 px-4 py-2 text-gray-700 hover:bg-gray-100"><i class="fas fa-chart-line w-5 text-center"></i><span>Monitoring Dashboard</span></a>
            <a href="backend.php" class="flex items-center gap-3 px-4 py-2 text-gray-700 hover:bg-gray-100"><i class="fas fa-cogs w-5 text-center"></i><span>System Settings</span></a>
            <hr class="my-1 border-gray-200">
            <a href="logout.php" class="flex items-center gap-3 px-4 py-2 text-gray-700 hover:bg-gray-100"><i class="fas fa-sign-out-alt w-5 text-center"></i><span>Logout</span></a>
          </div>
        </div>
      </div>
    </div>
    </header>
    
    <main class="container mx-auto p-4 md:p-6">
        <h1 class="text-2xl font-semibold mb-5 text-gray-700">User Management</h1>

        <?php if (isset($success)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($success); ?></span>
            </div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- User List -->
            <div class="lg:col-span-2 bg-white shadow-xl rounded-lg p-6">
                <h2 class="text-xl font-bold mb-4">User List</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Username</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Role</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Created At</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td class="px-4 py-3 text-sm text-gray-900 font-medium"><?php echo htmlspecialchars($row['username']); ?></td>
                                    <td class="px-4 py-3 text-sm text-gray-600"><?php echo htmlspecialchars(ucfirst($row['role'])); ?></td>
                                    <td class="px-4 py-3 text-sm text-gray-600"><?php echo htmlspecialchars(date('d M Y, H:i', strtotime($row['created_at']))); ?></td>
                                    <td class="px-4 py-3 text-sm text-center">
                                        <a href="?edit_id=<?php echo $row['id']; ?>" class="text-blue-600 hover:text-blue-800 font-medium">Edit</a>
                                        <?php if ($row['id'] != $_SESSION['user_id']): ?>
                                            <a href="?delete_id=<?php echo $row['id']; ?>" class="text-red-600 hover:text-red-800 font-medium ml-4">Delete</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Add/Edit Form -->
            <div class="bg-white shadow-xl rounded-lg p-6">
                <?php if (isset($edit_user)): ?>
                    <h2 class="text-xl font-bold mb-4">Edit User</h2>
                    <form method="POST" action="manage_users.php">
                        <input type="hidden" name="edit_id" value="<?php echo $edit_user['id']; ?>">
                        <div class="mb-4">
                            <label for="edit_username" class="block text-sm font-medium text-gray-700">Username</label>
                            <input type="text" id="edit_username" name="edit_username" value="<?php echo htmlspecialchars($edit_user['username']); ?>" required
                                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div class="mb-4">
                            <label for="edit_password" class="block text-sm font-medium text-gray-700">New Password</label>
                            <input type="password" id="edit_password" name="edit_password" placeholder="Leave blank to keep unchanged"
                                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div class="mb-4">
                            <label for="edit_role" class="block text-sm font-medium text-gray-700">Role</label>
                            <select id="edit_role" name="edit_role" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                <option value="admin" <?php if ($edit_user['role'] == 'admin') echo 'selected'; ?>>Admin</option>
                                <option value="user" <?php if ($edit_user['role'] == 'user') echo 'selected'; ?>>User</option>
                                <option value="maintenance" <?php if ($edit_user['role'] == 'maintenance') echo 'selected'; ?>>Maintenance</option>
                            </select>
                        </div>
                        <button type="submit" name="update_user"
                                class="w-full bg-green-600 text-white py-2 px-4 rounded-md hover:bg-green-700 font-semibold">
                            Update User
                        </button>
                        <a href="manage_users.php" class="block text-center mt-3 text-gray-600 hover:underline">Cancel Edit</a>
                    </form>
                <?php else: ?>
                    <h2 class="text-xl font-bold mb-4">Add New User</h2>
                    <form method="POST" action="manage_users.php">
                        <div class="mb-4">
                            <label for="new_username" class="block text-sm font-medium text-gray-700">Username</label>
                            <input type="text" id="new_username" name="new_username" required
                                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div class="mb-4">
                            <label for="new_password" class="block text-sm font-medium text-gray-700">Password</label>
                            <input type="password" id="new_password" name="new_password" required
                                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div class="mb-4">
                            <label for="new_role" class="block text-sm font-medium text-gray-700">Role</label>
                            <select id="new_role" name="new_role" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                <option value="admin">Admin</option>
                                <option value="user" selected>User</option>
                                <option value="maintenance">Maintenance</option>
                            </select>
                        </div>
                        <button type="submit" name="add_user"
                                class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 font-semibold">
                            Add User
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <!-- Delete Confirmation Modal -->
    <?php if (isset($delete_user)): ?>
    <div id="deleteConfirmModal" class="modal" style="display: block;">
        <div class="modal-content">
            <a href="manage_users.php" class="close-button">&times;</a>
            <h2 class="text-xl font-semibold mb-4 text-gray-700">Confirm Deletion</h2>
            <p class="mb-4 text-gray-600">To delete user <strong><?php echo htmlspecialchars($delete_user['username']); ?></strong>, please enter your admin password.</p>
            <form method="POST" action="manage_users.php">
                <input type="hidden" name="delete_id" value="<?php echo $delete_user['id']; ?>">
                <div class="mb-4">
                    <label for="admin_password" class="block text-sm font-medium text-gray-700">Admin Password</label>
                    <input type="password" id="admin_password" name="admin_password" required
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div class="flex justify-end space-x-3 mt-6">
                    <a href="manage_users.php" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold py-2 px-4 rounded-lg">Cancel</a>
                    <button type="submit" name="confirm_delete" class="bg-red-600 hover:bg-red-700 text-white font-semibold py-2 px-4 rounded-lg">Confirm Delete</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const menuToggle = document.getElementById('menu-toggle');
        const mainMenu = document.getElementById('mainMenu');
        if (menuToggle && mainMenu) {
            menuToggle.addEventListener('click', function(event) {
                event.stopPropagation();
                mainMenu.classList.toggle('hidden');
            });
            document.addEventListener('click', function(event) {
                if (!mainMenu.contains(event.target) && !menuToggle.contains(event.target)) {
                    mainMenu.classList.add('hidden');
                }
            });
            mainMenu.addEventListener('click', function(event) {
                // Hide menu when a link inside is clicked
                if (event.target.tagName === 'A' || event.target.closest('a')) {
                    mainMenu.classList.add('hidden');
                }
            });
        }
    });
    </script>
</body>
</html>
<?php $conn->close(); ?>
