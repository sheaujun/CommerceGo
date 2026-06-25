<?php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['userID']) || ($_SESSION['role'] ?? '') !== 'customer') {
    header('Location: ../login.php');
    exit;
}

$mapConfig = require __DIR__ . '/../includes/maps-config.php';
$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$address = (string) ($mapConfig['address'] ?? '');
$mapsKey = trim((string) ($mapConfig['api_key'] ?? ''));
$hasMapsKey = $mapsKey !== '' && $mapsKey !== 'YOUR_GOOGLE_MAPS_BROWSER_KEY';
$directionsUrl = 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($address);
$detailsUrl = 'https://www.google.com/maps/search/?api=1&query=3%2C%20Jalan%20Kuning%202%2C%20Taman%20Pelangi%2C%2080400%20Johor%20Bahru%2C%20Johor.';
$whatsAppNumber = preg_replace('/\D+/', '', (string) ($mapConfig['whatsapp_number'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Essen Pharmacy - Find Us</title>
    <link rel="stylesheet" href="css/customer-dashboard.css">
    <link rel="stylesheet" href="css/customer-find-us.css">
</head>
<body>
<div class="customer-layout">
    <aside class="sidebar">
        <div>
            <div class="brand">
                <img src="../logo.png" alt="Essen Pharmacy" class="brand-inline-logo" width="22" height="22">
                <div><h1>Essen Pharmacy</h1><p>Customer Portal</p></div>
            </div>
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-item"><span class="nav-icon">&#128230;</span><span>Products</span></a>
                <a href="cart.php" class="nav-item"><span class="nav-icon">&#128722;</span><span>My Cart</span></a>
                <a href="order-history.php" class="nav-item"><span class="nav-icon">&#128220;</span><span>Order History</span></a>
                <a href="find-us.php" class="nav-item active" aria-current="page"><span class="nav-icon">&#128205;</span><span>Find Us</span></a>
                <a href="support-chat.php" class="nav-item"><span class="nav-icon">&#128172;</span><span>Support Chat</span></a>
                <a href="profile.php" class="nav-item"><span class="nav-icon">&#128100;</span><span>Profile</span></a>
                <a href="../logout.php" class="nav-item"><span class="nav-icon">&#8617;</span><span>Sign Out</span></a>
            </nav>
        </div>
        <div class="sidebar-footer">
            <p class="support-title">Need help?</p><p class="support-copy">Contact our pharmacist</p>
            <a href="tel:<?php echo $escape((string) $mapConfig['phone']); ?>" class="support-link">1-800-PHARMACY</a>
        </div>
    </aside>
    <main class="main-content find-us-content">
        <header class="main-header"><div><h1>Find Us</h1><p>Visit Essen Pharmacy in Taman Pelangi.</p></div></header>
        <section class="location-card">
            <div class="location-intro">
                <div class="location-details">
                    <p class="location-kicker">Essen Pharmacy</p>
                    <h2>Find us in Taman Pelangi</h2>
                    <p><strong>Address:</strong> <?php echo $escape($address); ?></p>
                    <p><strong>Hours:</strong> <?php echo $escape((string) $mapConfig['hours']); ?></p>
                    <p><strong>Languages:</strong> <?php echo $escape((string) $mapConfig['languages']); ?></p>
                    <div class="location-actions">
                        <a class="location-button primary" href="<?php echo $escape($directionsUrl); ?>" target="_blank" rel="noopener noreferrer">⌖ Directions</a>
                        <?php if ($whatsAppNumber !== ''): ?>
                            <a class="location-button" href="https://wa.me/<?php echo $escape($whatsAppNumber); ?>" target="_blank" rel="noopener noreferrer">◔ WhatsApp</a>
                        <?php else: ?>
                            <span class="location-button disabled" title="Add whatsapp_number in includes/maps-config.php">◔ WhatsApp</span>
                        <?php endif; ?>
                        <a class="location-button" href="tel:<?php echo $escape((string) $mapConfig['phone']); ?>">☏ Call</a>
                    </div>
                </div>
                <figure class="storefront-panel">
                    <img src="../Essen-Pharmacy-Taman-Pelangi-Store-Front.jpg" alt="Essen Pharmacy Taman Pelangi store front">
                </figure>
            </div>
            <section class="opening-hours-section" aria-labelledby="openingHoursTitle">
                <h2 id="openingHoursTitle">Opening hours</h2>
                <div class="hours-table-wrap">
                    <table class="hours-table">
                        <thead>
                            <tr><th>Day</th><th>Opening Hours</th></tr>
                        </thead>
                        <tbody>
                            <tr><td>Monday – Saturday</td><td>9:00 am – 8:00 pm</td></tr>
                            <tr><td>Sunday</td><td>9:00 am – 6:00 pm</td></tr>
                            <tr><td>Public holidays</td><td>Hours may differ — check our <a href="<?php echo $escape($detailsUrl); ?>" target="_blank" rel="noopener noreferrer">Google profile</a> or call us.</td></tr>
                        </tbody>
                    </table>
                </div>
                <p class="pharmacist-note">Our pharmacist is in-store every day Monday to Saturday — walk in any time during opening hours. On Sundays, message or call us at <a href="tel:0127884057">012-788 4057</a> if you need pharmacist advice and we’ll get back to you the same day.</p>
            </section>
            <div class="map-panel">
                <div id="pharmacyMap" class="pharmacy-map" aria-label="Map showing Essen Pharmacy"></div>
                <?php if (!$hasMapsKey): ?><p class="map-setup-message">Add your Google Maps browser key in <code>includes/maps-config.php</code> to show the live map.</p><?php endif; ?>
            </div>
        </section>

    </main>
</div>
<script>
window.initPharmacyMap = function () {
    const location = <?php echo json_encode(['lat' => (float) ($mapConfig['latitude'] ?? 1.4841), 'lng' => (float) ($mapConfig['longitude'] ?? 103.7778)]); ?>;
    const map = new google.maps.Map(document.getElementById('pharmacyMap'), { center: location, zoom: 16, mapTypeControl: false, streetViewControl: false });
    const marker = new google.maps.Marker({ position: location, map, title: <?php echo json_encode((string) $mapConfig['name']); ?> });
    new google.maps.InfoWindow({ content: <?php echo json_encode('<strong>' . $mapConfig['name'] . '</strong><br>' . $address); ?> }).open({ map, anchor: marker });
};
</script>
<?php if ($hasMapsKey): ?><script async defer src="https://maps.googleapis.com/maps/api/js?key=<?php echo rawurlencode($mapsKey); ?>&callback=initPharmacyMap"></script><?php endif; ?>
</body>
</html>
