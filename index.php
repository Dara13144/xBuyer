<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>X-Buyer | Telegram Premium</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Kantumruy Pro', sans-serif; background: #05060f; color: white; }
        .glass { background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(15px); border: 1px solid rgba(255, 215, 0, 0.2); }
        .gold-glow { color: #ffd700; text-shadow: 0 0 10px rgba(255, 215, 0, 0.5); }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-6">

    <div class="w-full max-w-md text-center">
        <h1 class="text-4xl font-bold gold-glow mb-2">Nyx Shop</h1>
        <p class="text-gray-500 text-sm mb-10">Premium Services for x-buyer.vercel.app</p>
        
        <input id="tgUser" type="text" placeholder="Username @ ឬ ID" 
               class="w-full p-5 rounded-2xl bg-white/5 border border-white/10 focus:border-yellow-500 outline-none text-center text-lg mb-8">

        <div class="space-y-4">
            <?php
            $plans = [
                ['name' => '3 Months', 'riel' => 61000, 'usd' => '$14.99'],
                ['name' => '6 Months', 'riel' => 81500, 'usd' => '$19.99'],
                ['name' => '1 Year', 'riel' => 163000, 'usd' => '$39.99']
            ];
            foreach ($plans as $p): ?>
                <div onclick="pay(<?= $p['riel'] ?>, '<?= $p['name'] ?>')" 
                     class="glass p-6 rounded-3xl cursor-pointer hover:bg-white/10 transition-all active:scale-95">
                    <div class="flex justify-between items-center text-lg">
                        <span><?= $p['name'] ?></span>
                        <span class="font-bold gold-glow"><?= $p['usd'] ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div id="modal" class="fixed inset-0 bg-black/95 hidden items-center justify-center p-6 z-50">
        <div class="glass p-10 rounded-[40px] w-full max-w-sm text-center">
            <div id="qrPlace" class="bg-white p-3 rounded-2xl inline-block mb-6 shadow-2xl shadow-yellow-500/10"></div>
            <div id="status" class="text-yellow-500 animate-pulse">កំពុងរង់ចាំការបង់ប្រាក់...</div>
            <button onclick="window.location.reload()" class="mt-8 text-gray-500 underline text-sm">បោះបង់</button>
        </div>
    </div>

    <script>
        const API = "https://mainpy--mdara9695.replit.app";
        let timer;

        async function pay(riel, plan) {
            const user = document.getElementById('tgUser').value.trim();
            if (!user) return alert("សូមបញ្ចូលឈ្មោះ Telegram របស់អ្នក!");

            document.getElementById('modal').classList.replace('hidden', 'flex');

            try {
                const res = await fetch(`${API}/create_order`, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ amount: riel })
                });
                const data = await res.json();

                if (data.success) {
                    document.getElementById('qrPlace').innerHTML = `
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(data.qr)}" class="rounded-xl">
                        <a href="${data.deeplink}" class="mt-6 block bg-red-600 text-white py-3 rounded-xl font-bold">ចុចបង់ក្នុង App បាគង</a>
                    `;
                    startCheck(data.hash, plan, user);
                }
            } catch (e) {
                alert("Connection Error! សូមឆែកមើល Replit របស់អ្នក។");
                location.reload();
            }
        }

        function startCheck(hash, plan, user) {
            timer = setInterval(async () => {
                const res = await fetch(`${API}/check_status/${hash}/${plan}/${user}`);
                const result = await res.json();
                if (result.paid) {
                    clearInterval(timer);
                    document.getElementById('status').innerHTML = "<span class='text-green-500 text-2xl font-bold'>✅ ជោគជ័យ!</span>";
                    setTimeout(() => location.reload(), 3000);
                }
            }, 3000);
        }
    </script>
</body>
</html>
