<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>xBuyer - កម្មវិធីបញ្ចូលទឹកប្រាក់ Telegram Premium</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .container {
            max-width: 550px;
            width: 100%;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 25px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            overflow: hidden;
            backdrop-filter: blur(10px);
            animation: fadeInUp 0.6s ease-out;
        }
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 25px;
            text-align: center;
        }
        .header h1 {
            font-size: 2rem;
            letter-spacing: 1px;
        }
        .header p {
            opacity: 0.9;
            margin-top: 5px;
        }
        .content {
            padding: 30px;
        }
        .input-group {
            margin-bottom: 20px;
        }
        .input-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        .input-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s;
        }
        .input-group input:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }
        .plans {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .plan-card {
            background: #f8f9ff;
            border-radius: 16px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            border: 2px solid transparent;
        }
        .plan-card.selected {
            border-color: #667eea;
            background: #eef2ff;
            transform: scale(1.02);
        }
        .plan-card h3 {
            color: #333;
            margin-bottom: 5px;
        }
        .price {
            font-size: 1.4rem;
            font-weight: bold;
            color: #2a5298;
        }
        .btn {
            width: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 14px;
            border-radius: 40px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        .btn:active {
            transform: translateY(0);
        }
        .payment-section {
            margin-top: 25px;
            border-top: 1px solid #eaeaea;
            padding-top: 20px;
            display: none;
        }
        .qr-container {
            text-align: center;
            margin: 20px 0;
            animation: pulse 1.5s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.02); }
            100% { transform: scale(1); }
        }
        .qr-container img {
            max-width: 220px;
            border-radius: 20px;
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .info-text {
            background: #f0f4ff;
            padding: 12px;
            border-radius: 12px;
            font-size: 14px;
            margin: 15px 0;
            text-align: center;
        }
        .challenge-code {
            font-family: monospace;
            font-size: 1.2rem;
            font-weight: bold;
            color: #1e3c72;
        }
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .toast {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: #333;
            color: white;
            padding: 12px 24px;
            border-radius: 40px;
            font-size: 14px;
            z-index: 1000;
            opacity: 0;
            transition: opacity 0.3s;
        }
        .toast.show {
            opacity: 1;
        }
        .footer {
            text-align: center;
            padding: 15px;
            background: #f5f5f5;
            font-size: 12px;
            color: #777;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>🇰🇭 xBuyer</h1>
        <p>បញ្ចូលទឹកប្រាក់ Telegram Premium រហ័សទាន់ចិត្ត</p>
    </div>
    <div class="content">
        <div class="input-group">
            <label>📱 ឈ្មោះអ្នកប្រើ Telegram</label>
            <input type="text" id="username" placeholder="@username" autocomplete="off">
            <div id="usernameStatus" style="font-size:12px; margin-top:5px;"></div>
        </div>

        <div class="input-group">
            <label>🎁 ជ្រើសរើសកញ្ចប់</label>
            <div class="plans" id="plansContainer">
                {% for key, plan in plans.items() %}
                <div class="plan-card" data-plan="{{ key }}" data-price="{{ plan.price }}">
                    <h3>{{ plan.kh }}</h3>
                    <div class="price">${{ plan.price }}</div>
                </div>
                {% endfor %}
            </div>
        </div>

        <button class="btn" id="createOrderBtn">បង្កើតការបញ្ជាទិញ</button>

        <div id="paymentSection" class="payment-section">
            <div class="qr-container" id="qrContainer"></div>
            <div class="info-text">
                🔑 លេខកូដយោង: <span id="challengeValue" class="challenge-code"></span><br>
                💵 ចំនួនទឹកប្រាក់: <strong id="amountValue"></strong><br>
                ⏱ សុពលភាព 5 នាទី
            </div>
            <div class="input-group">
                <label>💳 លេខប្រតិបត្តិការ (Transaction ID)</label>
                <input type="text" id="transactionId" placeholder="ឧ. BKG123456789">
            </div>
            <button class="btn" id="verifyBtn">ផ្ទៀងផ្ទាត់ការបង់ប្រាក់</button>
        </div>
    </div>
    <div class="footer">
        © 2025 xBuyer - សេវាកម្មផ្លូវការ
    </div>
</div>
<div id="toast" class="toast"></div>

<script>
    // 🔥 UPDATE THIS TO YOUR ACTUAL REPLIT BACKEND URL 🔥
    const API_BASE = 'https://run-bot-1--xtih1931.replit.app';

    let selectedPlan = null;
    let currentOrderId = null;
    let currentChallenge = null;
    let usernameValid = false;
    let usernameCheckTimeout = null;

    // Plan selection
    document.querySelectorAll('.plan-card').forEach(card => {
        card.addEventListener('click', () => {
            document.querySelectorAll('.plan-card').forEach(c => c.classList.remove('selected'));
            card.classList.add('selected');
            selectedPlan = card.dataset.plan;
        });
    });

    // Username validation
    const usernameInput = document.getElementById('username');
    const usernameStatus = document.getElementById('usernameStatus');

    function validateUsername(username) {
        if (!username || username.length < 3) {
            usernameStatus.innerHTML = '⚠️ សូមបញ្ចូលឈ្មោះអ្នកប្រើឱ្យបានត្រឹមត្រូវ';
            usernameStatus.style.color = 'orange';
            return false;
        }
        usernameStatus.innerHTML = '<span class="loading" style="width:12px;height:12px;"></span> កំពុងផ្ទៀងផ្ទាត់...';
        fetch(`${API_BASE}/validate_username`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({username: username})
        })
        .then(res => res.json())
        .then(data => {
            if (data.valid) {
                usernameStatus.innerHTML = '✅ ឈ្មោះអ្នកប្រើត្រឹមត្រូវ';
                usernameStatus.style.color = 'green';
                usernameValid = true;
            } else {
                usernameStatus.innerHTML = '❌ ឈ្មោះអ្នកប្រើមិនត្រឹមត្រូវ ឬមិនមាន';
                usernameStatus.style.color = 'red';
                usernameValid = false;
            }
        })
        .catch(() => {
            usernameStatus.innerHTML = '⚠️ បរាជ័យក្នុងការផ្ទៀងផ្ទាត់';
            usernameValid = false;
        });
        return true;
    }

    usernameInput.addEventListener('input', () => {
        if (usernameCheckTimeout) clearTimeout(usernameCheckTimeout);
        usernameCheckTimeout = setTimeout(() => {
            if (usernameInput.value.trim()) {
                validateUsername(usernameInput.value.trim());
            } else {
                usernameStatus.innerHTML = '';
                usernameValid = false;
            }
        }, 500);
    });

    // Create order
    document.getElementById('createOrderBtn').addEventListener('click', () => {
        const username = usernameInput.value.trim();
        if (!username) {
            showToast('សូមបញ្ចូលឈ្មោះអ្នកប្រើ Telegram');
            return;
        }
        if (!usernameValid) {
            showToast('ឈ្មោះអ្នកប្រើមិនត្រឹមត្រូវ សូមពិនិត្យម្តងទៀត');
            return;
        }
        if (!selectedPlan) {
            showToast('សូមជ្រើសរើសកញ្ចប់សេវា');
            return;
        }

        const btn = document.getElementById('createOrderBtn');
        btn.innerHTML = '<span class="loading"></span> កំពុងបង្កើត...';
        btn.disabled = true;

        fetch(`${API_BASE}/create_order`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({username: username, plan: selectedPlan})
        })
        .then(res => res.json())
        .then(data => {
            if (data.error) {
                showToast(data.error);
                btn.innerHTML = 'បង្កើតការបញ្ជាទិញ';
                btn.disabled = false;
                return;
            }
            currentOrderId = data.order_id;
            currentChallenge = data.challenge;
            document.getElementById('challengeValue').innerText = currentChallenge;
            document.getElementById('amountValue').innerText = `$${data.amount}`;
            // QR image from absolute URL
            const qrFullUrl = `${API_BASE}${data.qr_url}`;
            document.getElementById('qrContainer').innerHTML = `<img src="${qrFullUrl}?t=${Date.now()}" alt="QR Code">`;
            document.getElementById('paymentSection').style.display = 'block';
            btn.innerHTML = 'បង្កើតការបញ្ជាទិញ';
            btn.disabled = false;
            showToast('បង្កើតដោយជោគជ័យ! សូមបង់ប្រាក់តាម QR');
        })
        .catch(err => {
            showToast('កំហុសប្រព័ន្ធ');
            btn.innerHTML = 'បង្កើតការបញ្ជាទិញ';
            btn.disabled = false;
        });
    });

    // Verify payment
    document.getElementById('verifyBtn').addEventListener('click', () => {
        const transactionId = document.getElementById('transactionId').value.trim();
        if (!transactionId) {
            showToast('សូមបញ្ចូលលេខប្រតិបត្តិការ');
            return;
        }
        if (!currentOrderId || !currentChallenge) {
            showToast('សូមបង្កើតការបញ្ជាទិញជាមុន');
            return;
        }

        const btn = document.getElementById('verifyBtn');
        btn.innerHTML = '<span class="loading"></span> កំពុងផ្ទៀងផ្ទាត់...';
        btn.disabled = true;

        fetch(`${API_BASE}/verify_payment`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                order_id: currentOrderId,
                transaction_id: transactionId,
                challenge: currentChallenge
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.error) {
                showToast(data.error);
                btn.innerHTML = 'ផ្ទៀងផ្ទាត់ការបង់ប្រាក់';
                btn.disabled = false;
                return;
            }
            showToast('✅ ការបង់ប្រាក់ត្រូវបានផ្ទៀងផ្ទាត់! សូមរង់ចាំការដំណើរការ');
            btn.innerHTML = 'បានផ្ទៀងផ្ទាត់';
            btn.disabled = true;
            document.getElementById('transactionId').disabled = true;
        })
        .catch(() => {
            showToast('កំហុសប្រព័ន្ធ');
            btn.innerHTML = 'ផ្ទៀងផ្ទាត់ការបង់ប្រាក់';
            btn.disabled = false;
        });
    });

    function showToast(msg) {
        const toast = document.getElementById('toast');
        toast.innerText = msg;
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 3000);
    }
</script>
</body>
</html>
