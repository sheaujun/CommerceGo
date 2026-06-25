<?php

function disableExpiredProducts(mysqli $conn): void
{
    $conn->query(
        "UPDATE products
         SET status = 'Inactive'
         WHERE status = 'Active'
           AND expiryDate IS NOT NULL
           AND expiryDate < CURDATE()"
    );
}
