<?php
// This file will create the payment_logs table if it doesn't exist
include 'config/config.php';

$sql = "
CREATE TABLE IF NOT EXISTS payment_logs (
  id int(11) NOT NULL AUTO_INCREMENT,
  order_id int(11) NOT NULL,
  user_id int(11) DEFAULT NULL,
  payment_method varchar(20) NOT NULL,
  transaction_id varchar(100) DEFAULT NULL,
  amount decimal(12,2) NOT NULL,
  status varchar(20) NOT NULL,
  response_code varchar(10) DEFAULT NULL,
  payment_data text DEFAULT NULL,
  created_at timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

if ($conn->query($sql) === TRUE) {
    echo "Table payment_logs created successfully or already exists";
} else {
    echo "Error creating table: " . $conn->error;
}

// Close connection
$conn->close();
?>
