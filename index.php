<?php
session_start();
if (isset($_SESSION['userId'])) {
    if (isset($_SESSION['userTypeId']) && $_SESSION['userTypeId'] == 1) {
        header('Location: admin/index.php');
        exit;
    } else {
        header('Location: index.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>TPKI || Sign In</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">

    <!-- Favicon -->
    <link href="img/favicon.ico" rel="icon">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Tailwind CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] },
                    colors: {
                        brand: { 50:'#f0fdf4', 100:'#dcfce7', 200:'#bbf7d0', 400:'#4ade80', 500:'#22c55e', 600:'#16a34a', 700:'#15803d', 800:'#166534', 900:'#14532d' },
                        gold:  { 400:'#facc15', 500:'#eab308', 600:'#ca8a04' }
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Inter', system-ui, sans-serif; }
        .bg-building {
            background: url('img/bg-tkpi.png') center/cover no-repeat fixed;
        }
        /* Animated gradient border */
        @keyframes borderGlow {
            0%, 100% { border-color: rgba(22,163,74,.4); }
            50%      { border-color: rgba(234,179,8,.5); }
        }
        .glow-border { animation: borderGlow 4s ease-in-out infinite; }
        /* Subtle float animation for logo */
        @keyframes float { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-6px)} }
        .float { animation: float 3s ease-in-out infinite; }
        /* Glass effect */
        .glass { backdrop-filter: blur(16px) saturate(180%); -webkit-backdrop-filter: blur(16px) saturate(180%); }
        /* Input focus glow */
        input:focus { box-shadow: 0 0 0 3px rgba(22,163,74,.25); }
    </style>
</head>

<body class="bg-building text-white antialiased">

    <!-- Full-screen overlay -->
    <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-black/75 via-black/60 to-black/75 px-4 py-8">

        <div class="w-full max-w-5xl grid grid-cols-1 lg:grid-cols-5 overflow-hidden rounded-2xl shadow-2xl border border-white/10 glow-border">

            <!-- Left Panel — Branding (3/5 on lg) -->
            <div class="hidden lg:flex lg:col-span-3 relative flex-col justify-between p-10 overflow-hidden">
                <!-- Overlay gradient over the building image that bleeds through -->
                <div class="absolute inset-0 bg-gradient-to-br from-brand-900/90 via-brand-800/80 to-black/70 z-0"></div>
                <!-- Decorative circles -->
                <div class="absolute -top-20 -left-20 w-72 h-72 bg-brand-500/10 rounded-full blur-3xl z-0"></div>
                <div class="absolute -bottom-16 -right-16 w-56 h-56 bg-gold-500/10 rounded-full blur-3xl z-0"></div>

                <div class="relative z-10">
                    <img src="img/logo.png" alt="TPKI" class="w-28 mb-8 float drop-shadow-lg">
                    <h1 class="text-4xl font-bold leading-tight mb-3">
                        Welcome to <span class="text-brand-400">TPKI</span>
                    </h1>
                    <p class="text-lg text-gray-300 max-w-md">Talete King Panyulung Kapampangan Inc. &mdash; Microfinance NGO</p>
                </div>

                <div class="relative z-10 space-y-4 mt-8">
                    <div class="flex items-start gap-3">
                        <div class="mt-1 flex-shrink-0 w-8 h-8 rounded-full bg-brand-500/20 flex items-center justify-center">
                            <svg class="w-4 h-4 text-brand-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <div>
                            <p class="font-medium text-white">Client Management</p>
                            <p class="text-sm text-gray-400">Track clients, assets, dependents & records.</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <div class="mt-1 flex-shrink-0 w-8 h-8 rounded-full bg-brand-500/20 flex items-center justify-center">
                            <svg class="w-4 h-4 text-brand-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <div>
                            <p class="font-medium text-white">Financial Tracking</p>
                            <p class="text-sm text-gray-400">Income, expenses & loan payment monitoring.</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <div class="mt-1 flex-shrink-0 w-8 h-8 rounded-full bg-brand-500/20 flex items-center justify-center">
                            <svg class="w-4 h-4 text-brand-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <div>
                            <p class="font-medium text-white">Multi-Branch</p>
                            <p class="text-sm text-gray-400">Manage branches, departments & employees.</p>
                        </div>
                    </div>
                </div>

                <p class="relative z-10 mt-10 text-xs text-gray-500">&copy; 2026 TPKI Microfinance NGO. All rights reserved.</p>
            </div>

            <!-- Right Panel — Sign In Form (2/5 on lg) -->
            <div class="lg:col-span-2 glass bg-neutral-950/70 p-8 sm:p-10 flex flex-col justify-center">

                <!-- Mobile logo (hidden on lg) -->
                <div class="flex items-center gap-3 mb-8 lg:hidden">
                    <img src="img/logo.png" alt="TPKI" class="w-12 drop-shadow-md">
                    <div>
                        <p class="font-bold text-lg text-white leading-none">TPKI</p>
                        <p class="text-xs text-gray-400">Microfinance NGO</p>
                    </div>
                </div>

                <div class="mb-6">
                    <h2 class="text-2xl font-bold text-white">Sign In</h2>
                    <p class="text-sm text-gray-400 mt-1">Enter your credentials to access the dashboard</p>
                </div>

                <?php if (!empty($_SESSION['error'])): ?>
                    <div class="mb-4 rounded-md bg-red-600/20 border border-red-600/30 p-3 text-sm text-red-200">
                        <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="authenticate.php" class="space-y-5">
                    <!-- Email -->
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-gray-400 mb-1.5">Email</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0l-9.75 6.093L2.25 6.75"/></svg>
                            </span>
                            <input name="email" type="email" required
                                class="w-full pl-10 pr-4 py-3 rounded-lg bg-neutral-800/80 border border-neutral-700 text-gray-100 placeholder-gray-500 focus:outline-none focus:border-brand-500 transition-colors"
                                placeholder="you@example.com">
                        </div>
                    </div>

                    <!-- Password -->
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-gray-400 mb-1.5">Password</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
                            </span>
                            <input name="password" type="password" required
                                class="w-full pl-10 pr-4 py-3 rounded-lg bg-neutral-800/80 border border-neutral-700 text-gray-100 placeholder-gray-500 focus:outline-none focus:border-brand-500 transition-colors"
                                placeholder="&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;">
                        </div>
                    </div>

                    <!-- Remember / Forgot -->
                    <div class="flex items-center justify-between text-sm">
                        <label class="inline-flex items-center gap-2 text-gray-300 cursor-pointer select-none">
                            <input type="checkbox" name="remember" class="h-4 w-4 rounded border-neutral-600 bg-neutral-800 text-brand-500 focus:ring-brand-500">
                            Remember me
                        </label>
                        <a href="#" class="text-brand-400 hover:text-brand-300 transition-colors">Forgot password?</a>
                    </div>

                    <!-- Submit -->
                    <button type="submit" class="w-full flex items-center justify-center gap-2 px-4 py-3.5 rounded-lg bg-gradient-to-r from-brand-600 to-brand-700 hover:from-brand-500 hover:to-brand-600 text-white font-semibold shadow-lg shadow-brand-900/30 transition-all duration-200 active:scale-[.98]">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3-3l3-3m0 0l-3-3m3 3H9"/></svg>
                        Sign In
                    </button>

                    
                </form>

                <!-- Divider -->
                <div class="mt-8 pt-6 border-t border-neutral-800 text-center">
                    <p class="text-xs text-gray-600">Secured by TPKI IT Department</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Submit button feedback
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(){
                const btn = this.querySelector('button[type=submit]');
                if(btn){ btn.disabled = true; btn.innerHTML = '<svg class="animate-spin h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg> Signing in...'; }
            });
        });
    </script>
</body>

</html>