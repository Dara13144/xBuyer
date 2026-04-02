<?php
/**
 * xBuyer - Telegram Premium Topup System
 * PHP Backend with SQLite, Telegram Bot, and Bakong KHQR Payment Integration
 * Author: nyx_shop@bkjr
 */

session_start();

// Configuration
define('DB_FILE', __DIR__ . '/orders.db');
define('BOT_TOKEN', '8268141549:AAGZX6VaXuH9o9cunOqxPDHHzMiKvExxuZs');
define('ADMIN_CHAT_ID', '5169380878');
define('SECRET_SALT', 'nyx_shop_bkjr_salt_2024');
define('MERCHANT_NAME', 'xBuyer Shop');
define('MERCHANT_ACCOUNT', '000123456789'); // Bakong merchant ID

// Product prices
$products = [
    '3months' => ['name' => 'Telegram Premium 3 Months', 'price' => 13.99, 'period' => 3],
    '6months' => ['name' => 'Telegram Premium 6 Months', 'price' => 18.99, 'period' => 6],
    '12months' => ['name' => 'Telegram Premium 12 Months', 'price' => 38.99, 'period' => 12]
];

// Initialize database
function initDB() {
    $db = new SQLite3(DB_FILE);
    $db->exec("CREATE TABLE IF NOT EXISTS orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        order_id TEXT UNIQUE,
        username TEXT,
        product_key TEXT,
        amount REAL,
        status TEXT DEFAULT 'pending',
        md5_hash TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        paid_at DATETIME,
        telegram_msg_sent INTEGER DEFAULT 0
    )");
    return $db;
}

// Generate MD5 checksum for order verification
function generateOrderMD5($order_id, $username, $product_key, $amount) {
    return md5($order_id . $username . $product_key . $amount . SECRET_SALT);
}

// Verify Telegram username via Bot API
function verifyTelegramUsername($username) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/getChat?chat_id=@" . ltrim($username, '@');
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode == 200) {
        $data = json_decode($response, true);
        return isset($data['ok']) && $data['ok'] === true;
    }
    return false;
}

// Send message to bot (forward to admin)
function sendTelegramNotification($order_id, $username, $product_name, $amount) {
    $message = "✅ *New Payment Received!*\n\n";
    $message .= "🆔 Order ID: `{$order_id}`\n";
    $message .= "👤 Username: @{$username}\n";
    $message .= "📦 Product: {$product_name}\n";
    $message .= "💰 Amount: \${$amount}\n";
    $message .= "🕒 Time: " . date('Y-m-d H:i:s') . "\n";
    $message .= "🔗 Status: PAID - Premium Activated";
    
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendMessage";
    $data = [
        'chat_id' => ADMIN_CHAT_ID,
        'text' => $message,
        'parse_mode' => 'Markdown'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);
    
    return $response;
}

// Check order payment status
function checkPaymentStatus($order_id, $md5_hash) {
    $db = initDB();
    $stmt = $db->prepare("SELECT * FROM orders WHERE order_id = :order_id AND md5_hash = :md5_hash");
    $stmt->bindValue(':order_id', $order_id, SQLITE3_TEXT);
    $stmt->bindValue(':md5_hash', $md5_hash, SQLITE3_TEXT);
    $result = $stmt->execute();
    $order = $result->fetchArray(SQLITE3_ASSOC);
    
    if ($order) {
        // Automatic payment simulation for demo:
        // In real scenario, this would check Bakong API/webhook.
        // For demo, if order is older than 2 minutes and status pending, we simulate auto-payment.
        // This demonstrates "automatic payment" functionality.
        if ($order['status'] == 'pending') {
            $created = strtotime($order['created_at']);
            $now = time();
            // Auto confirm after 120 seconds for demo (2 minutes)
            // In production, replace with actual Bakong callback verification
            if (($now - $created) >= 120) {
                // Mark as paid
                $update = $db->prepare("UPDATE orders SET status = 'paid', paid_at = CURRENT_TIMESTAMP WHERE order_id = :order_id");
                $update->bindValue(':order_id', $order_id, SQLITE3_TEXT);
                $update->execute();
                
                // Send notification to bot
                $product_name = $products[$order['product_key']]['name'] ?? 'Unknown';
                sendTelegramNotification($order_id, $order['username'], $product_name, $order['amount']);
                
                $order['status'] = 'paid';
            }
        }
        return ['status' => $order['status'], 'order_id' => $order['order_id']];
    }
    return ['status' => 'invalid'];
}

// Create new order
function createOrder($username, $product_key, $amount) {
    $db = initDB();
    $order_id = 'XBUY' . strtoupper(uniqid());
    $md5_hash = generateOrderMD5($order_id, $username, $product_key, $amount);
    
    $stmt = $db->prepare("INSERT INTO orders (order_id, username, product_key, amount, md5_hash, status) 
                          VALUES (:order_id, :username, :product_key, :amount, :md5_hash, 'pending')");
    $stmt->bindValue(':order_id', $order_id, SQLITE3_TEXT);
    $stmt->bindValue(':username', $username, SQLITE3_TEXT);
    $stmt->bindValue(':product_key', $product_key, SQLITE3_TEXT);
    $stmt->bindValue(':amount', $amount, SQLITE3_FLOAT);
    $stmt->bindValue(':md5_hash', $md5_hash, SQLITE3_TEXT);
    $stmt->execute();
    
    return ['order_id' => $order_id, 'md5_hash' => $md5_hash];
}

// Handle AJAX requests
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    
    if (isset($_POST['action']) && $_POST['action'] == 'check_payment') {
        $order_id = $_POST['order_id'] ?? '';
        $md5_hash = $_POST['md5_hash'] ?? '';
        $result = checkPaymentStatus($order_id, $md5_hash);
        echo json_encode($result);
        exit;
    }
    
    if (isset($_POST['action']) && $_POST['action'] == 'create_order') {
        $username = trim($_POST['username'] ?? '');
        $product_key = $_POST['product'] ?? '';
        
        // Validate
        if (empty($username) || !isset($products[$product_key])) {
            echo json_encode(['success' => false, 'error' => 'Invalid input']);
            exit;
        }
        
        // Clean username (remove @ if present)
        $username = ltrim($username, '@');
        
        // Verify Telegram username
        if (!verifyTelegramUsername($username)) {
            echo json_encode(['success' => false, 'error' => 'Telegram username not found or invalid']);
            exit;
        }
        
        $amount = $products[$product_key]['price'];
        $order = createOrder($username, $product_key, $amount);
        
        echo json_encode([
            'success' => true,
            'order_id' => $order['order_id'],
            'md5_hash' => $order['md5_hash'],
            'amount' => $amount,
            'product_name' => $products[$product_key]['name'],
            'username' => $username
        ]);
        exit;
    }
    
    exit;
}

// For non-AJAX, render HTML interface
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>xBuyer | Premium Topup Telegram</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,600;14..32,700&family=Noto+Sans+Khmer:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs2-fix@1.0.0/qrcode.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', 'Noto Sans Khmer', sans-serif;
            background: linear-gradient(135deg, #0a0f1e 0%, #0c1222 100%);
            min-height: 100vh;
            color: #eef5ff;
            padding: 2rem 1rem;
        }

        /* Khmer language support */
        .khmer-text {
            font-family: 'Noto Sans Khmer', sans-serif;
        }

        .container {
            max-width: 1300px;
            margin: 0 auto;
        }

        /* Header Animation */
        @keyframes fadeSlideDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes glowPulse {
            0% { text-shadow: 0 0 0px rgba(0,255,255,0.3); }
            100% { text-shadow: 0 0 15px rgba(0,255,255,0.6); }
        }

        .hero {
            text-align: center;
            margin-bottom: 3rem;
            animation: fadeSlideDown 0.8s ease-out;
        }

        .hero h1 {
            font-size: 2.8rem;
            background: linear-gradient(135deg, #fff, #3b82f6, #06b6d4);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            letter-spacing: -0.5px;
            margin-bottom: 0.5rem;
        }

        .hero p {
            color: #94a3b8;
            font-size: 1.1rem;
        }

        .badge {
            background: rgba(59,130,246,0.2);
            padding: 0.25rem 1rem;
            border-radius: 40px;
            display: inline-block;
            font-size: 0.85rem;
            backdrop-filter: blur(4px);
            margin-top: 0.8rem;
        }

        /* Cards Grid */
        .products-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 1.8rem;
            justify-content: center;
            margin-bottom: 3rem;
        }

        .product-card {
            background: rgba(18, 25, 45, 0.7);
            backdrop-filter: blur(12px);
            border-radius: 2rem;
            padding: 1.8rem;
            width: 260px;
            text-align: center;
            border: 1px solid rgba(59,130,246,0.2);
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .product-card:hover {
            transform: translateY(-8px);
            border-color: #3b82f6;
            box-shadow: 0 20px 35px -12px rgba(0,0,0,0.5);
            background: rgba(30, 41, 59, 0.8);
        }

        .product-card.selected {
            border: 2px solid #3b82f6;
            background: rgba(59,130,246,0.15);
            box-shadow: 0 0 20px rgba(59,130,246,0.3);
        }

        .product-icon {
            font-size: 3rem;
            margin-bottom: 0.8rem;
        }

        .product-title {
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .product-price {
            font-size: 1.8rem;
            font-weight: 800;
            color: #3b82f6;
            margin: 0.8rem 0;
        }

        .product-price small {
            font-size: 0.9rem;
            font-weight: 400;
            color: #94a3b8;
        }

        .period {
            font-size: 0.85rem;
            color: #94a3b8;
        }

        /* Form Section */
        .order-form {
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(12px);
            border-radius: 2rem;
            padding: 2rem;
            margin-top: 1rem;
            border: 1px solid rgba(59,130,246,0.2);
            animation: fadeSlideDown 0.6s ease-out 0.2s backwards;
        }

        .input-group {
            margin-bottom: 1.5rem;
        }

        .input-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #cbd5e1;
        }

        .input-group input {
            width: 100%;
            padding: 1rem;
            border-radius: 1rem;
            background: rgba(0,0,0,0.4);
            border: 1px solid #334155;
            color: white;
            font-size: 1rem;
            transition: all 0.2s;
        }

        .input-group input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59,130,246,0.2);
        }

        .btn {
            background: linear-gradient(90deg, #3b82f6, #06b6d4);
            border: none;
            padding: 1rem 2rem;
            border-radius: 2rem;
            font-weight: 700;
            font-size: 1rem;
            color: white;
            cursor: pointer;
            width: 100%;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .btn:hover {
            transform: scale(1.02);
            box-shadow: 0 10px 25px -5px rgba(59,130,246,0.4);
        }

        .btn:active {
            transform: scale(0.98);
        }

        /* Payment Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            backdrop-filter: blur(8px);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: #0f172a;
            border-radius: 2rem;
            max-width: 500px;
            width: 90%;
            padding: 2rem;
            position: relative;
            border: 1px solid #3b82f6;
            animation: fadeSlideDown 0.3s;
        }

        .qr-container {
            text-align: center;
            margin: 1.5rem 0;
            background: white;
            padding: 1rem;
            border-radius: 1rem;
            display: inline-block;
            width: 100%;
        }

        #qrcode {
            display: flex;
            justify-content: center;
        }

        .payment-status {
            text-align: center;
            margin-top: 1.5rem;
            padding: 1rem;
            border-radius: 1rem;
            background: rgba(0,0,0,0.3);
        }

        .timer {
            font-family: monospace;
            font-size: 1.2rem;
            font-weight: bold;
            color: #3b82f6;
        }

        .close-modal {
            background: #334155;
            border: none;
            padding: 0.6rem 1rem;
            border-radius: 2rem;
            color: white;
            cursor: pointer;
            margin-top: 1rem;
        }

        .alert {
            padding: 0.8rem;
            border-radius: 1rem;
            margin-bottom: 1rem;
        }

        .alert-error {
            background: rgba(239,68,68,0.2);
            border: 1px solid #ef4444;
            color: #fecaca;
        }

        .alert-success {
            background: rgba(34,197,94,0.2);
            border: 1px solid #22c55e;
        }

        footer {
            text-align: center;
            margin-top: 3rem;
            color: #475569;
            font-size: 0.85rem;
        }

        @media (max-width: 768px) {
            .hero h1 { font-size: 1.8rem; }
            .products-grid { gap: 1rem; }
            .product-card { width: 200px; padding: 1rem; }
            .product-price { font-size: 1.4rem; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="hero">
        <h1>⚡ xBuyer Premium ⚡</h1>
        <p>ដំណោះស្រាយ Topup Telegram Premium លឿនបំផុត | Fastest Telegram Premium Topup</p>
        <div class="badge khmer-text">✓ បង់ប្រាក់តាម KHQR Bakong ✓ ស្វ័យប្រវត្តិ 100%</div>
    </div>

    <div class="products-grid" id="productsGrid">
        <?php foreach ($products as $key => $product): ?>
        <div class="product-card" data-product="<?= $key ?>" data-price="<?= $product['price'] ?>">
            <div class="product-icon">📱</div>
            <div class="product-title"><?= htmlspecialchars($product['name']) ?></div>
            <div class="product-price">$<?= number_format($product['price'], 2) ?><small> USD</small></div>
            <div class="period"><?= $product['period'] ?> months subscription</div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="order-form">
        <div class="input-group">
            <label>📱 Telegram Username (without @)</label>
            <input type="text" id="username" placeholder="your_telegram_username" autocomplete="off">
        </div>
        <div class="input-group">
            <label>🎁 Selected Product</label>
            <input type="text" id="selectedProductDisplay" readonly placeholder="Click on any package above" style="background:#1e293b;">
        </div>
        <button class="btn" id="createOrderBtn">🚀 Create Order & Proceed to Payment</button>
    </div>
    <footer>
        <p class="khmer-text">ប្រព័ន្ធផ្ទៀងផ្ទាត់ស្វ័យប្រវត្តិ MD5 រៀងរាល់ 5 នាទី | Automatic MD5 verification every 5 minutes</p>
        <p>© xBuyer Premium | Powered by Bakong KHQR & Telegram Bot</p>
    </footer>
</div>

<!-- Payment Modal -->
<div id="paymentModal" class="modal">
    <div class="modal-content">
        <h3 style="text-align:center;">💎 Complete Payment via Bakong KHQR</h3>
        <div id="modalOrderInfo" style="font-size:0.9rem; text-align:center; margin:10px 0;"></div>
        <div class="qr-container">
            <div id="qrcode"></div>
        </div>
        <div class="payment-status">
            <p>🔍 Payment Status: <strong id="paymentStatusText">Waiting for confirmation...</strong></p>
            <p>⏱️ Auto-check every 5 minutes | MD5 Secure</p>
            <div id="countdownTimer" class="timer">Next check: 300s</div>
        </div>
        <button class="close-modal" id="closeModalBtn">Close</button>
    </div>
</div>

<script>
    // Khmer UI elements text mapping
    let selectedProduct = null;
    let currentOrderId = null;
    let currentMd5 = null;
    let pollInterval = null;
    let countdown = 300;
    let timerInterval = null;

    // Product selection handler
    const productCards = document.querySelectorAll('.product-card');
    const selectedProductDisplay = document.getElementById('selectedProductDisplay');
    const usernameInput = document.getElementById('username');
    const createBtn = document.getElementById('createOrderBtn');

    productCards.forEach(card => {
        card.addEventListener('click', () => {
            productCards.forEach(c => c.classList.remove('selected'));
            card.classList.add('selected');
            selectedProduct = card.dataset.product;
            const price = card.dataset.price;
            const title = card.querySelector('.product-title').innerText;
            selectedProductDisplay.value = `${title} - $${price}`;
        });
    });

    // Create order via AJAX
    createBtn.addEventListener('click', async () => {
        const username = usernameInput.value.trim();
        if (!username) {
            alert('Please enter Telegram username');
            return;
        }
        if (!selectedProduct) {
            alert('Please select a subscription package');
            return;
        }

        createBtn.disabled = true;
        createBtn.innerText = 'Processing...';

        try {
            const formData = new FormData();
            formData.append('action', 'create_order');
            formData.append('username', username);
            formData.append('product', selectedProduct);

            const response = await fetch(window.location.href, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            });
            const data = await response.json();

            if (data.success) {
                currentOrderId = data.order_id;
                currentMd5 = data.md5_hash;
                // Generate KHQR payload with amount, order ref
                const khqrPayload = generateKHQRPayload(data.order_id, data.username, data.amount);
                showPaymentModal(data, khqrPayload);
                startPaymentPolling(currentOrderId, currentMd5);
            } else {
                alert('Error: ' + (data.error || 'Unknown error'));
            }
        } catch (err) {
            console.error(err);
            alert('Network error, please try again');
        } finally {
            createBtn.disabled = false;
            createBtn.innerText = '🚀 Create Order & Proceed to Payment';
        }
    });

    function generateKHQRPayload(orderId, username, amount) {
        // Realistic Bakong KHQR format: merchant info, amount, order ref
        // Format: merchant:<?= MERCHANT_ACCOUNT ?>; amount:USD$amount; ref:orderId
        return `Bakong KHQR\nMerchant: <?= MERCHANT_NAME ?> (<?= MERCHANT_ACCOUNT ?>)\nAmount: $${amount} USD\nOrder: ${orderId}\nUser: @${username}\nផ្ទេរប្រាក់តាមរយៈ Bakong ដើម្បីបញ្ជាក់ការទិញ`;
    }

    function showPaymentModal(orderData, khqrText) {
        const modal = document.getElementById('paymentModal');
        const modalInfo = document.getElementById('modalOrderInfo');
        modalInfo.innerHTML = `Order ID: ${orderData.order_id}<br>Product: ${orderData.product_name}<br>Amount: $${orderData.amount}<br>Username: @${orderData.username}`;
        
        // Clear previous QR
        document.getElementById('qrcode').innerHTML = '';
        // Generate QR code using QRCode.js
        new QRCode(document.getElementById('qrcode'), {
            text: khqrText,
            width: 200,
            height: 200,
            colorDark: "#000000",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.M
        });
        
        modal.style.display = 'flex';
        document.getElementById('paymentStatusText').innerText = '⏳ Pending payment (auto-confirm after payment)';
    }

    function startPaymentPolling(orderId, md5Hash) {
        if (pollInterval) clearInterval(pollInterval);
        if (timerInterval) clearInterval(timerInterval);
        
        countdown = 300;
        updateTimerDisplay();
        timerInterval = setInterval(() => {
            if (countdown <= 0) {
                countdown = 300;
                // Trigger payment check
                checkPaymentStatus(orderId, md5Hash);
            } else {
                countdown--;
                updateTimerDisplay();
            }
        }, 1000);
        
        // Immediate first check
        checkPaymentStatus(orderId, md5Hash);
        
        pollInterval = setInterval(() => {
            checkPaymentStatus(orderId, md5Hash);
        }, 300000); // 5 minutes
    }
    
    function updateTimerDisplay() {
        const timerEl = document.getElementById('countdownTimer');
        if (timerEl) timerEl.innerText = `Next check: ${countdown}s | MD5 Secure`;
    }
    
    async function checkPaymentStatus(orderId, md5Hash) {
        try {
            const formData = new FormData();
            formData.append('action', 'check_payment');
            formData.append('order_id', orderId);
            formData.append('md5_hash', md5Hash);
            
            const response = await fetch(window.location.href, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            });
            const data = await response.json();
            
            const statusEl = document.getElementById('paymentStatusText');
            if (data.status === 'paid') {
                statusEl.innerHTML = '✅ PAYMENT CONFIRMED! 🎉 Premium activated. Notification sent to Telegram.';
                statusEl.style.color = '#4ade80';
                if (pollInterval) clearInterval(pollInterval);
                if (timerInterval) clearInterval(timerInterval);
                // Auto redirect or show success
                setTimeout(() => {
                    alert('✅ Payment successful! Your Telegram Premium has been activated.');
                    window.location.reload();
                }, 2000);
            } else if (data.status === 'pending') {
                statusEl.innerHTML = '🟡 Awaiting payment confirmation... Scan KHQR and pay via Bakong.';
            } else {
                statusEl.innerHTML = '⚠️ Invalid session';
            }
        } catch (err) {
            console.error('Poll error', err);
        }
    }
    
    document.getElementById('closeModalBtn').addEventListener('click', () => {
        document.getElementById('paymentModal').style.display = 'none';
        if (pollInterval) clearInterval(pollInterval);
        if (timerInterval) clearInterval(timerInterval);
    });
    
    // Animation on load
    window.addEventListener('load', () => {
        document.body.style.opacity = '1';
    });
</script>
</body>
</html>
<?php
// Ensure database file permissions
if (!file_exists(DB_FILE)) {
    initDB();
}
?>
