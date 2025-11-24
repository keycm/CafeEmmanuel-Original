<?php
$conn = new mysqli("localhost", "root", "", "addproduct");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $action = $_POST['action'];

    // Kunin muna yung record
    $sql = "SELECT * FROM recently_deleted WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if ($action === 'restore') {
            // ibalik sa cart table
            $insert_sql = "INSERT INTO cart (fullname, contact, address, cart, total, status) 
                           VALUES (?, ?, ?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param(
                "ssssds",
                $row['fullname'],
                $row['contact'],
                $row['address'],
                $row['cart'],
                $row['total'],
                $row['status']
            );
            $insert_stmt->execute();
            $insert_stmt->close();

            // tanggalin sa recently_deleted
            $delete_sql = "DELETE FROM recently_deleted WHERE id = ?";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bind_param("i", $id);
            $delete_stmt->execute();
            $delete_stmt->close();

        } elseif ($action === 'permanent') {
            // diretso burahin sa recently_deleted
            $delete_sql = "DELETE FROM recently_deleted WHERE id = ?";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bind_param("i", $id);
            $delete_stmt->execute();
            $delete_stmt->close();
        }
    }

    $stmt->close();
    header("Location: recently_deleted.php");
    exit();
}

$conn->close();
?>
