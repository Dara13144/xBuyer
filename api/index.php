<?php
// --- PHP BACKEND LOGIC ---
$botToken = "8268141549:AAGZX6VaXuH9o9cunOqxPDHHzMiKvExxuZs";
$chatId = "5169380878";

// Handle AJAX request from the frontend
if (isset($_GET['action']) && $_GET['action'] == 'send_order') {
    $user = $_GET['username'] ?? 'Unknown';
    $plan = $_GET['plan'] ?? 'Unknown';
    $price = $_GET['price'] ?? '0';
    
    // Security: MD5 Check (Simple verification example)
    $md5_verify = md5($user . "nyx_shop@bkjr");
    
    $text = "🔔 *New Order xBuyer*\n";
    $text .= "👤 User: " . $user . "\n";
    $text .= "📦 Plan: " . $plan . "\n";
    $text .= "💰 Price: $" . $price . "\n";
    $text .= "🔑 Hash: " . $md5_verify . "\n";
    $text .= "✅ Status: Pending Verification";

    $url = "https://api.telegram.org/bot$botToken/sendMessage?chat_id=$chatId&text=" . urlencode($text) . "&parse_mode=Markdown";
    file_get_contents($url);
    
    echo json_encode(["status" => "success", "hash" => $md5_verify]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>xBuyer - Telegram Premium</title>
    <style>
        :root { --tg-blue: #0088cc; --dark: #121212; --card: #1e1e1e; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: var(--dark); color: white; margin: 0; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .shop-container { background: var(--card); width: 90%; max-width: 400px; padding: 25px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); text-align: center; animation: slideUp 0.5s ease; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        
        .input-box { width: 100%; padding: 12px; margin: 15px 0; border-radius: 10px; border: 1px solid #333; background: #2c2c2c; color: white; box-sizing: border-box; }
        .plan-grid { display: grid; gap: 10px; margin-bottom: 20px; }
        .plan-item { background: #2c2c2c; padding: 15px; border-radius: 12px; cursor: pointer; border: 2px solid transparent; transition: 0.3s; }
        .plan-item:hover { border-color: var(--tg-blue); }
        .plan-item.active { border-color: var(--tg-blue); background: #253545; }
        
        #qr-area { display: none; margin-top: 20px; padding: 15px; background: white; border-radius: 15px; color: black; }
        .timer { color: #ff4757; font-weight: bold; font-size: 1.2rem; }
        .btn-pay { background: var(--tg-blue); color: white; border: none; width: 100%; padding: 15px; border-radius: 12px; font-weight: bold; cursor: pointer; font-size: 1rem; }
    </style>
</head>
<body>

<div class="shop-container">
    <h1 style="color: var(--tg-blue);">xBuyer Shop</h1>
    <p>បញ្ចូល Username និងជ្រើសរើសគម្រោង</p>

    <input type="text" id="tg_user" class="input-box" placeholder="@username telegram..." required>

    <div class="plan-grid">
        <div class="plan-item" onclick="selectPlan(this, '3 Months', 13.99)">3 Months - $13.99</div>
        <div class="plan-item" onclick="selectPlan(this, '6 Months', 18.99)">6 Months - $18.99</div>
        <div class="plan-item" onclick="selectPlan(this, '12 Months', 38.99)">12 Months - $38.99</div>
    </div>

    <button class="btn-pay" onclick="processPayment()">បង់ប្រាក់ឥឡូវនេះ</button>

    <div id="qr-area">
        <p style="font-weight: bold;">Bakong KHQR Payment</p>
        <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=YOUR_BAKONG_ID_HERE" alt="QR Code">
        <p>សូមរក្សានៅទំព័រនេះ: <span id="timer" class="timer">05:00</span></p>
    </div>
</div>

<script>
    let selected = { plan: '', price: 0 };

    function selectPlan(el, plan, price) {
        document.querySelectorAll('.plan-item').forEach(i => i.classList.remove('active'));
        el.classList.add('active');
        selected = { plan, price };
    }

    async function processPayment() {
        const user = document.getElementById('tg_user').value;
        if (!user || selected.price === 0) { alert("សូមបំពេញព័ត៌មាន!"); return; }

        // Show QR and Start Timer
        document.getElementById('qr-area').style.display = 'block';
        startTimer(300);

        // Call PHP to send Telegram Notification
        const res = await fetch(`?action=send_order&username=${user}&plan=${selected.plan}&price=${selected.price}`);
        const data = await res.json();
        console.log("Order Sent:", data);
    }

    function startTimer(duration) {
        let timer = duration, minutes, seconds;
        const display = document.getElementById('timer');
        const interval = setInterval(() => {
            minutes = parseInt(timer / 60, 10);
            seconds = parseInt(timer % 60, 10);
            display.textContent = `${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;
            if (--timer < 0) {
                clearInterval(interval);
                alert("Transaction Expired");
                location.reload();
            }
        }, 1000);
    }
</script>

</body>
</html>
