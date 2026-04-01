import os
import hashlib
import random
import string
import requests
import qrcode
from flask import Flask, request, jsonify, render_template
from io import BytesIO
from datetime import datetime
import threading

app = Flask(__name__)
app.secret_key = 'change-this-in-production'

# In‑memory storage (use a database in production)
orders = {}

# ===== CONFIGURATION =====
GLOBAL_SECRET = "rbkUyfi6Vs2hNb7jBYreOQW7E--qmeFknBwsBYfoTWJ7bs"
MERCHANT_ID = "nyx_shop@bkjr"
TELEGRAM_BOT_TOKEN = "8268141549:AAGZX6VaXuH9o9cunOqxPDHHzMiKvExxuZs"
TELEGRAM_CHAT_ID = "5169380878"

# Product plans
PLANS = {
    "3months": {"name": "Telegram Premium 3 months", "price": 13.99, "kh": "ធានី ៣ ខែ"},
    "6months": {"name": "Telegram Premium 6 months", "price": 18.99, "kh": "ធានី ៦ ខែ"},
    "12months": {"name": "Telegram Premium 12 months", "price": 38.99, "kh": "ធានី ១២ ខែ"}
}

# ===== HELPER FUNCTIONS =====
def generate_qr(data):
    img = qrcode.make(data)
    img_io = BytesIO()
    img.save(img_io, 'PNG')
    img_io.seek(0)
    return img_io

def validate_telegram_username(username):
    if not username or not username.strip():
        return False
    username = username.strip().lstrip('@')
    try:
        r = requests.get(f"https://t.me/{username}", timeout=5)
        if "Sorry, this username doesn't exist" in r.text:
            return False
        return True
    except:
        return False

def send_telegram_notification(order_data, transaction_id):
    message = f"""✅ *New Payment Verified!*
👤 Username: @{order_data['username']}
📦 Plan: {order_data['plan_name']} (${order_data['amount']})
💸 Amount: ${order_data['amount']}
🆔 Transaction ID: {transaction_id}
🔑 Order ID: {order_data['order_id']}
⏱ Time: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}
"""
    url = f"https://api.telegram.org/bot{TELEGRAM_BOT_TOKEN}/sendMessage"
    payload = {
        "chat_id": TELEGRAM_CHAT_ID,
        "text": message,
        "parse_mode": "Markdown"
    }
    try:
        requests.post(url, json=payload, timeout=5)
    except Exception as e:
        print(f"Telegram notification failed: {e}")

# ===== ROUTES =====
@app.route('/')
def index():
    return render_template('index.html', plans=PLANS)

@app.route('/validate_username', methods=['POST'])
def validate_username():
    data = request.get_json()
    username = data.get('username', '')
    valid = validate_telegram_username(username)
    return jsonify({'valid': valid})

@app.route('/create_order', methods=['POST'])
def create_order():
    data = request.get_json()
    username = data.get('username')
    plan_key = data.get('plan')

    if not username or not plan_key or plan_key not in PLANS:
        return jsonify({'error': 'Invalid data'}), 400

    if not validate_telegram_username(username):
        return jsonify({'error': 'ឈ្មោះ Telegram មិនត្រឹមត្រូវ'}), 400

    plan = PLANS[plan_key]
    amount = plan['price']

    # Generate order ID and challenge
    order_id = ''.join(random.choices(string.digits, k=8))
    challenge = ''.join(random.choices(string.ascii_uppercase + string.digits, k=6))

    # MD5 hash for verification: MD5(challenge + GLOBAL_SECRET)
    expected_md5 = hashlib.md5((challenge + GLOBAL_SECRET).encode()).hexdigest()

    orders[order_id] = {
        'username': username,
        'plan_key': plan_key,
        'plan_name': plan['name'],
        'amount': amount,
        'challenge': challenge,
        'expected_md5': expected_md5,
        'timestamp': datetime.now(),
        'verified': False,
        'order_id': order_id
    }

    # Generate QR code (Bakong style)
    qr_text = f"Pay to: {MERCHANT_ID}\nAmount: ${amount}\nRef: {challenge}"
    qr_io = generate_qr(qr_text)

    qr_filename = f"qr_{order_id}.png"
    qr_path = os.path.join('static', 'qr_codes', qr_filename)
    os.makedirs(os.path.dirname(qr_path), exist_ok=True)
    with open(qr_path, 'wb') as f:
        f.write(qr_io.getvalue())

    return jsonify({
        'order_id': order_id,
        'qr_url': f'/static/qr_codes/{qr_filename}',
        'challenge': challenge,
        'expected_md5': expected_md5,          # <-- MD5 code to be used as transaction ID
        'amount': amount,
        'expires_in': 300
    })

@app.route('/verify_payment', methods=['POST'])
def verify_payment():
    data = request.get_json()
    order_id = data.get('order_id')
    transaction_id = data.get('transaction_id')      # This should be the MD5 code
    challenge_input = data.get('challenge')

    if not order_id or not transaction_id or not challenge_input:
        return jsonify({'error': 'Missing fields'}), 400

    if order_id not in orders:
        return jsonify({'error': 'Order not found'}), 404

    order = orders[order_id]

    if order.get('verified'):
        return jsonify({'error': 'Order already verified'}), 400

    # Check time limit (5 minutes)
    elapsed = (datetime.now() - order['timestamp']).total_seconds()
    if elapsed > 300:
        return jsonify({'error': 'Payment time expired (5 minutes)'}), 400

    # Verify challenge matches
    if challenge_input != order['challenge']:
        return jsonify({'error': 'Invalid challenge code'}), 400

    # MD5 verification: transaction_id must equal the stored expected_md5
    if transaction_id != order['expected_md5']:
        return jsonify({'error': 'Invalid MD5 code. Please copy the exact MD5 shown on screen.'}), 400

    # All checks passed
    order['verified'] = True
    order['transaction_id'] = transaction_id

    # Send Telegram notification in background
    threading.Thread(target=send_telegram_notification, args=(order, transaction_id)).start()

    return jsonify({'success': True, 'message': 'Payment verified successfully! Premium will be activated soon.'})

if __name__ == '__main__':
    # For Replit, use port 8080 (or 5000)
    app.run(host='0.0.0.0', port=8080, debug=True)
